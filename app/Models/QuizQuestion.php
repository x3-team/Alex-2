<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizQuestion extends Model
{
    protected $fillable = [
        'order',
        'question_text',
        'question_type',
        'audience_target',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    public function answers()
    {
        // Явно указываем 'question_id'
        return $this->hasMany(QuizAnswer::class, 'question_id')->orderBy('order');
    }

    public function nextQuestion()
    {
        return $this->belongsTo(QuizQuestion::class, 'next_question_id');
    }
}