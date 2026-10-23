<?php

namespace App\Payments\Gateway;

use App\Payments\Data\GatewayPayment;
use App\Payments\Data\GatewayRefund;
use App\Payments\Data\Receipt;
use App\Payments\PaymentGatewayException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Эмулятор ЮKassa без ключей (PAYMENTS_DRIVER=fake). Хранит платежи в кэше на сутки.
 * Оплатить или отменить платёж: FakeGateway::complete($id, 'succeeded'|'canceled').
 */
class FakeGateway implements PaymentGateway
{
    private const PREFIX = 'fake_yookassa:';

    /** @var list<PaymentGatewayException> */
    private array $failures = [];

    public function failNext(PaymentGatewayException $e): void
    {
        $this->failures[] = $e;
    }

    public function createPayment(string $idempotenceKey, int $amountKopecks, string $description, string $returnUrl, Receipt $receipt, array $metadata = []): GatewayPayment
    {
        $this->maybeFail();
        if (abs($receipt->totalKopecks() - $amountKopecks) > 0) {
            throw new PaymentGatewayException('ЮKassa 400 invalid_request: сумма позиций чека не равна сумме платежа', httpStatus: 400, errorCode: 'invalid_request');
        }

        // Как в ЮKassa: повтор с тем же ключом возвращает тот же платёж.
        $existing = Cache::get(self::PREFIX.'key:'.$idempotenceKey);
        if ($existing) {
            return $this->getPayment($existing);
        }

        $id = 'fake-'.Str::uuid();
        Cache::put(self::PREFIX.'key:'.$idempotenceKey, $id, now()->addDay());
        $this->store($id, [
            'status' => 'pending',
            'amount' => $amountKopecks,
            'refunded' => 0,
            'confirmation_url' => $returnUrl.(str_contains($returnUrl, '?') ? '&' : '?').'fake_payment='.$id,
        ]);

        return $this->getPayment($id);
    }

    public function getPayment(string $paymentId): GatewayPayment
    {
        $this->maybeFail();
        $p = Cache::get(self::PREFIX.$paymentId);
        if (! $p) {
            throw new PaymentGatewayException('ЮKassa 404 not_found', httpStatus: 404, errorCode: 'not_found');
        }

        return new GatewayPayment($paymentId, $p['status'], $p['amount'], $p['confirmation_url'], true, $p['reason'] ?? null, $p['party'] ?? null, $p['refunded']);
    }

    public function createRefund(string $idempotenceKey, string $paymentId, int $amountKopecks, string $description, Receipt $receipt): GatewayRefund
    {
        $this->maybeFail();
        $p = Cache::get(self::PREFIX.$paymentId);
        if (! $p || $p['status'] !== 'succeeded' || $p['refunded'] + $amountKopecks > $p['amount']) {
            throw new PaymentGatewayException('ЮKassa 400 invalid_request: возврат невозможен', httpStatus: 400, errorCode: 'invalid_request');
        }
        $existing = Cache::get(self::PREFIX.'refund-key:'.$idempotenceKey);
        if ($existing) {
            return $this->getRefund($existing);
        }

        $p['refunded'] += $amountKopecks;
        $this->store($paymentId, $p);
        $id = 'fake-refund-'.Str::uuid();
        Cache::put(self::PREFIX.'refund-key:'.$idempotenceKey, $id, now()->addDay());
        Cache::put(self::PREFIX.$id, ['status' => 'succeeded', 'amount' => $amountKopecks], now()->addDay());

        return $this->getRefund($id);
    }

    public function getRefund(string $refundId): GatewayRefund
    {
        $this->maybeFail();
        $r = Cache::get(self::PREFIX.$refundId);
        if (! $r) {
            throw new PaymentGatewayException('ЮKassa 404 not_found', httpStatus: 404, errorCode: 'not_found');
        }

        return new GatewayRefund($refundId, $r['status'], $r['amount']);
    }

    public function createSettlementReceipt(string $idempotenceKey, string $paymentId, Receipt $receipt): string
    {
        $this->maybeFail();

        return 'fake-receipt-'.substr(hash('sha256', $idempotenceKey), 0, 16);
    }

    public static function complete(string $paymentId, string $status = 'succeeded', ?string $reason = null): void
    {
        $p = Cache::get(self::PREFIX.$paymentId);
        if ($p) {
            $p['status'] = $status;
            $p['reason'] = $status === 'canceled' ? ($reason ?? 'expired_on_confirmation') : null;
            $p['party'] = $status === 'canceled' ? 'yoo_money' : null;
            Cache::put(self::PREFIX.$paymentId, $p, now()->addDay());
        }
    }

    private function store(string $id, array $data): void
    {
        Cache::put(self::PREFIX.$id, $data, now()->addDay());
    }

    private function maybeFail(): void
    {
        if ($this->failures !== []) {
            throw array_shift($this->failures);
        }
    }
}
