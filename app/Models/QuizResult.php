<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizResult extends Model
{
    protected $fillable = [
        'title',
        'description',
        'primary_button_text',
        'primary_button_url',
        'secondary_button_text',
        'secondary_button_url',
        'is_active',
        'order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    public function answers()
    {
        // Явно указываем 'result_id'
        return $this->hasMany(QuizAnswer::class, 'result_id');
    }

    public function conditions()
    {
        return $this->hasMany(QuizCondition::class, 'result_id');
    }
}