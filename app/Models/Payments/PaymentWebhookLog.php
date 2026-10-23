<?php

namespace App\Models\Payments;

use Illuminate\Database\Eloquent\Model;

class PaymentWebhookLog extends Model
{
    protected $table = 'payment_webhook_log';

    public const UPDATED_AT = null;

    protected $guarded = [];
}
