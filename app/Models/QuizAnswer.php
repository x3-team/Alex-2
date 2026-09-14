<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizAnswer extends Model
{
    protected $fillable = [
        'question_id',
        'answer_text',
        'order',
        'target_audience',
        'next_question_id',
        'result_id',
    ];

    protected $casts = [
        'order' => 'integer',
    ];

    public function question()
    {
        // Явно указываем имя колонки 'question_id' вместо дефолтного 'quiz_question_id'
        return $this->belongsTo(QuizQuestion::class, 'question_id');
    }

    public function nextQuestion()
    {
        return $this->belongsTo(QuizQuestion::class, 'next_question_id');
    }

    public function result()
    {
        // Явно указываем 'result_id' вместо дефолтного 'quiz_result_id'
        return $this->belongsTo(QuizResult::class, 'result_id');
    }
}