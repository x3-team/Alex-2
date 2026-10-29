<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CartService extends Model
{
    use SoftDeletes;

    protected $fillable = ['title', 'description', 'price', 'is_active', 'order'];

    protected $casts = [
        'is_active' => 'boolean',
        'price' => 'decimal:2',
    ];
}