<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabPage extends Model
{
    protected $fillable = [
        'key',
        'title',
        'content',
    ];

    // Получить контент по ключу
    public static function getContent(string $key): string
    {
        $page = self::where('key', $key)->first();
        return $page?->content ?? '';
    }

    // Получить заголовок по ключу
    public static function getTitle(string $key): string
    {
        $page = self::where('key', $key)->first();
        return $page?->title ?? '';
    }
}