<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsOutbox extends Model
{
    protected $table = 'analytics_outbox';

    protected $fillable = [
        'event_id', 'event_name', 'platform', 'inquiry_ref', 'order_id', 'params',
        'client_id', 'consent', 'status', 'reason', 'attempts', 'occurred_at', 'sent_at',
    ];

    protected $casts = [
        'params' => 'array',
        'occurred_at' => 'datetime',
        'sent_at' => 'datetime',
    ];
}
