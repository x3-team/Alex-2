<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabResultBlank extends Model
{
    protected $fillable = [
        'lab_order_id',
        'blank_id',
        'blank_guid',
        'storage_path',
        'fetched_at',
    ];

    protected $casts = [
        'fetched_at' => 'datetime',
    ];
}
