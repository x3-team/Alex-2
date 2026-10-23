<?php

namespace App\Models\Lis;

use Illuminate\Database\Eloquent\Model;

class LisFakeReferral extends Model
{
    protected $guarded = [];

    protected $casts = [
        'orders' => 'array',
        'queued' => 'boolean',
        'activated_at' => 'datetime',
        'done_at' => 'datetime',
    ];
}
