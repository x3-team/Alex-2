<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartService extends Model
{
    protected $fillable = ['title', 'description', 'price', 'is_active', 'order'];

    protected $casts = [
        'is_active' => 'boolean',
        'price' => 'decimal:2',
    ];
}