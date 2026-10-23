<?php

namespace App\Models\Payments;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRefund extends Model
{
    public const NEW = 'new';
    public const PENDING = 'pending';
    public const SUCCEEDED = 'succeeded';
    public const CANCELED = 'canceled';

    protected $guarded = [];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
