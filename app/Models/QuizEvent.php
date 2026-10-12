<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];
}
