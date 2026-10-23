<?php

namespace App\Payments\Events;

use App\Models\Payments\Payment;
use Illuminate\Foundation\Events\Dispatchable;

/** Срабатывает один раз, при переходе платежа в финальный статус. */
class PaymentCanceled
{
    use Dispatchable;

    public function __construct(public readonly Payment $payment) {}
}
