<?php

namespace App\Models\Lis;

use Illuminate\Database\Eloquent\Model;

class LisExchangeLog extends Model
{
    protected $table = 'lis_exchange_log';

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
