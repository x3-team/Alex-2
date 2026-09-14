<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartSetting extends Model
{
    protected $fillable = [
        'alex2_title', 'alex2_description', 'alex2_price', 'alex2_is_active',
        'consultation_title', 'consultation_description', 'consultation_price', 'consultation_is_active',
        'urgent_title', 'urgent_description', 'urgent_price', 'urgent_is_active',
    ];

    protected $casts = [
        'alex2_is_active' => 'boolean',
        'consultation_is_active' => 'boolean',
        'urgent_is_active' => 'boolean',
        'alex2_price' => 'decimal:2',
        'consultation_price' => 'decimal:2',
        'urgent_price' => 'decimal:2',
    ];
    
    public static function getSettings()
    {
        return self::first() ?? self::create();
    }
}