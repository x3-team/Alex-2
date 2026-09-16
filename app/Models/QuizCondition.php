<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizCondition extends Model
{
    protected $fillable = [
        'result_id',
        'question_id',
        'answer_ids',
        'operator',
    ];

    protected $casts = [
        'answer_ids' => 'array',
    ];

    public function result()
    {
        return $this->belongsTo(QuizResult::class);
    }

    public function question()
    {
        return $this->belongsTo(QuizQuestion::class);
    }
}