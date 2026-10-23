<?php

namespace App\Console\Commands;

use App\Models\Payments\Payment;
use App\Models\Payments\PaymentRefund;
use App\Payments\PaymentGatewayException;
use App\Payments\PaymentService;
use Illuminate\Console\Command;

class PaymentsSync extends Command
{
    protected $signature = 'payments:sync';

    protected $description = 'Досверяет с ЮKassa незавершённые платежи и возвраты (на случай, если уведомление не дошло)';

    public function handle(PaymentService $payments): int
    {
        $ttl = (int) config('payments.pending_ttl_minutes', 1440);
        $failed = 0;

        $open = Payment::whereIn('status', [Payment::PENDING, Payment::WAITING_FOR_CAPTURE])
            ->whereNotNull('provider_payment_id')
            ->where('created_at', '>=', now()->subMinutes($ttl + 60))
            ->where(fn ($q) => $q->whereNull('synced_at')->orWhere('synced_at', '<=', now()->subMinute()))
            ->orderBy('id')
            ->limit(200)
            ->get();

        foreach ($open as $payment) {
            try {
                $payments->sync($payment);
            } catch (PaymentGatewayException $e) {
                $failed++;
                $this->warn("Платёж {$payment->id}: {$e->getMessage()}");
            }
        }

        foreach (PaymentRefund::where('status', PaymentRefund::PENDING)->orderBy('id')->limit(200)->get() as $refund) {
            try {
                $payments->syncRefund($refund);
            } catch (PaymentGatewayException $e) {
                $failed++;
                $this->warn("Возврат {$refund->id}: {$e->getMessage()}");
            }
        }

        $this->info("Платежей сверено: {$open->count()}, ошибок: {$failed}");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
