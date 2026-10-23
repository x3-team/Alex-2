<?php

namespace App\Payments;

use App\Models\Payments\Payment;
use App\Models\Payments\PaymentRefund;
use App\Payments\Data\Receipt;
use App\Payments\Events\PaymentCanceled;
use App\Payments\Events\PaymentSucceeded;
use App\Payments\Events\RefundSucceeded;
use App\Payments\Gateway\PaymentGateway;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Жизненный цикл платежа: создание с чеком, сверка статуса (вебхук и досверка по
 * расписанию идут сюда же), возврат и второй чек «полный расчёт».
 * События PaymentSucceeded / PaymentCanceled / RefundSucceeded срабатывают ровно один раз.
 */
class PaymentService
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    /**
     * Создаёт платёж. Сумма — всегда сумма позиций чека, считается на сервере.
     * Если ЮKassa не ответила, запись остаётся в статусе new; повторный вызов start()
     * для неё пойдёт с тем же Idempotence-Key.
     */
    public function create(?Model $payable, Receipt $receipt, string $description, string $returnUrl, array $metadata = []): Payment
    {
        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'payable_type' => $payable?->getMorphClass(),
            'payable_id' => $payable?->getKey(),
            'provider' => (string) config('payments.driver'),
            'status' => Payment::NEW,
            'amount_kopecks' => $receipt->totalKopecks(),
            'description' => mb_substr($description, 0, 128),
            'receipt_items' => array_map(fn ($i) => $i->toArray(), $receipt->items),
            'receipt_contact' => ['email' => $receipt->email, 'phone' => $receipt->phone],
        ]);

        return $this->start($payment, $returnUrl, $metadata);
    }

    public function start(Payment $payment, string $returnUrl, array $metadata = []): Payment
    {
        if ($payment->status !== Payment::NEW) {
            return $payment;
        }

        try {
            $remote = $this->gateway->createPayment(
                $payment->uuid,
                $payment->amount_kopecks,
                $payment->description,
                $returnUrl,
                $this->receipt($payment),
                $metadata + ['payment_uuid' => $payment->uuid],
            );
        } catch (PaymentGatewayException $e) {
            $payment->update(['last_error' => mb_substr($e->getMessage(), 0, 255)]);
            throw $e;
        }

        $payment->update([
            'provider_payment_id' => $remote->id,
            'status' => $remote->status,
            'confirmation_url' => $remote->confirmationUrl,
            'test' => $remote->test,
            'last_error' => null,
            'synced_at' => now(),
        ]);

        return $payment;
    }

    /** Сверка с ЮKassa. Вебхуку не верим на слово: статус всегда берём из API. */
    public function sync(Payment $payment): Payment
    {
        if ($payment->provider_payment_id === null) {
            return $payment;
        }

        $remote = $this->gateway->getPayment($payment->provider_payment_id);

        $event = DB::transaction(function () use ($payment, $remote) {
            $locked = Payment::whereKey($payment->getKey())->lockForUpdate()->first();
            $event = null;
            $changes = ['synced_at' => now(), 'refunded_kopecks' => $remote->refundedKopecks];

            if (! $locked->isFinal() && $remote->status !== $locked->status) {
                $changes['status'] = $remote->status;
                if ($remote->status === Payment::SUCCEEDED) {
                    $changes['paid_at'] = now();
                    $event = PaymentSucceeded::class;
                } elseif ($remote->status === Payment::CANCELED) {
                    $changes['canceled_at'] = now();
                    $changes['cancellation_reason'] = $remote->cancellationReason;
                    $event = PaymentCanceled::class;
                }
            }

            $locked->update($changes);

            return $event;
        });

        $payment->refresh();
        if ($event !== null) {
            $event::dispatch($payment);
        }

        return $payment;
    }

    /** Полный возврат оставшейся суммы. Частичный — позже, когда будет понятно, какие позиции возвращаем. */
    public function refund(Payment $payment, string $reason, ?int $userId = null): PaymentRefund
    {
        $amount = $payment->refundableKopecks();
        if ($amount <= 0 || $payment->refunded_kopecks > 0) {
            throw new InvalidArgumentException('Платёж не оплачен или уже возвращён');
        }
        if ($payment->refunds()->whereIn('status', [PaymentRefund::NEW, PaymentRefund::PENDING])->exists()) {
            throw new InvalidArgumentException('Возврат по этому платежу уже оформляется');
        }

        $refund = $payment->refunds()->create([
            'uuid' => (string) Str::uuid(),
            'status' => PaymentRefund::NEW,
            'amount_kopecks' => $amount,
            'reason' => mb_substr($reason, 0, 250),
            'user_id' => $userId,
        ]);

        return $this->startRefund($refund);
    }

    public function startRefund(PaymentRefund $refund): PaymentRefund
    {
        if ($refund->status !== PaymentRefund::NEW) {
            return $refund;
        }
        $payment = $refund->payment;

        try {
            $remote = $this->gateway->createRefund($refund->uuid, $payment->provider_payment_id, $refund->amount_kopecks, $refund->reason, $this->receipt($payment));
        } catch (PaymentGatewayException $e) {
            $refund->update(['last_error' => mb_substr($e->getMessage(), 0, 255)]);
            throw $e;
        }

        $refund->update(['provider_refund_id' => $remote->id, 'status' => PaymentRefund::PENDING, 'last_error' => null]);

        return $this->applyRefundStatus($refund, $remote->status);
    }

    public function syncRefund(PaymentRefund $refund): PaymentRefund
    {
        if ($refund->provider_refund_id === null) {
            return $refund;
        }

        return $this->applyRefundStatus($refund, $this->gateway->getRefund($refund->provider_refund_id)->status);
    }

    /** Второй чек «полный расчёт» после оказания услуги. Повтор не отбивает чек дважды. */
    public function settle(Payment $payment): Payment
    {
        if ($payment->settled_at !== null || $payment->status !== Payment::SUCCEEDED || $payment->refunded_kopecks > 0) {
            return $payment;
        }

        $receiptId = $this->gateway->createSettlementReceipt('settle-'.$payment->uuid, $payment->provider_payment_id, $this->receipt($payment));
        $payment->update(['settlement_receipt_id' => $receiptId ?: null, 'settled_at' => now()]);

        return $payment;
    }

    private function applyRefundStatus(PaymentRefund $refund, string $status): PaymentRefund
    {
        $fire = DB::transaction(function () use ($refund, $status) {
            $locked = PaymentRefund::whereKey($refund->getKey())->lockForUpdate()->first();
            if (in_array($locked->status, [PaymentRefund::SUCCEEDED, PaymentRefund::CANCELED], true) || $locked->status === $status) {
                return false;
            }
            $locked->update(['status' => $status]);
            if ($status === PaymentRefund::SUCCEEDED) {
                $payment = Payment::whereKey($locked->payment_id)->lockForUpdate()->first();
                $payment->update(['refunded_kopecks' => min($payment->amount_kopecks, $payment->refunded_kopecks + $locked->amount_kopecks)]);

                return true;
            }

            return false;
        });

        $refund->refresh();
        if ($fire) {
            RefundSucceeded::dispatch($refund);
        }

        return $refund;
    }

    private function receipt(Payment $payment): Receipt
    {
        $contact = $payment->receipt_contact ?? [];

        return Receipt::fromStoredItems($payment->receipt_items, $contact['email'] ?? null, $contact['phone'] ?? null);
    }
}
