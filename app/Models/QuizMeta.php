<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizMeta extends Model
{
    use HasFactory;

    protected $table = 'quiz_metas';

    protected $fillable = [
        'title',
        'description',
        'keywords',
        'is_split_enabled', // 🔴 Добавлено для сохранения переключателя разделения
    ];

    protected $casts = [
        'is_split_enabled' => 'boolean',
    ];
}