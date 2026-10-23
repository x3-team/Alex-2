<?php

namespace App\Payments\Events;

use App\Models\Payments\PaymentRefund;
use Illuminate\Foundation\Events\Dispatchable;

class RefundSucceeded
{
    use Dispatchable;

    public function __construct(public readonly PaymentRefund $refund) {}
}
