<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabDictionarySnapshot extends Model
{
    protected $fillable = [
        'version',
        'payload',
        'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
    ];
}
