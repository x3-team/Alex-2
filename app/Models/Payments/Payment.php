<?php

namespace App\Models\Payments;

use App\Payments\Data\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    public const NEW = 'new';
    public const PENDING = 'pending';
    public const WAITING_FOR_CAPTURE = 'waiting_for_capture';
    public const SUCCEEDED = 'succeeded';
    public const CANCELED = 'canceled';

    protected $guarded = [];

    protected $hidden = ['receipt_contact'];

    protected $casts = [
        'receipt_items' => 'array',
        'receipt_contact' => 'encrypted:array',
        'test' => 'boolean',
        'paid_at' => 'datetime',
        'canceled_at' => 'datetime',
        'settled_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class);
    }

    public function isFinal(): bool
    {
        return in_array($this->status, [self::SUCCEEDED, self::CANCELED], true);
    }

    public function refundableKopecks(): int
    {
        return $this->status === self::SUCCEEDED ? max(0, $this->amount_kopecks - $this->refunded_kopecks) : 0;
    }

    public function amountRubles(): string
    {
        return Money::toRubles($this->amount_kopecks);
    }
}
