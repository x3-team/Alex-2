<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizRule extends Model
{
    protected $fillable = [
        'result_id',
        'priority',
        'conditions',
    ];

    // ЭТО КРИТИЧЕСКИ ВАЖНО: преобразует JSON из БД в массив PHP и обратно
    protected $casts = [
        'conditions' => 'array',
        'priority' => 'integer',
    ];

    public function result()
    {
        return $this->belongsTo(QuizResult::class);
    }
}