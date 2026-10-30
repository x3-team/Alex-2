<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabOutboundJob extends Model
{
    protected $fillable = [
        'lab_order_id',
        'step',
        'status',
        'attempts',
        'reason',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(LabOrder::class, 'lab_order_id');
    }
}
