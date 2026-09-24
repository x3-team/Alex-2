<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomeSetting extends Model
{
    protected $fillable = [
        'type',
        'advantages',
        'results',
        'how_to_pass', // 👈 Добавили в fillable
        'faq',
        'featured_blog_ids',
        'how_to_test_text',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'hero_title',
        'hero_subtitle',
        'why_title',
        'why_subtitle',
        'results_intro_title',
        'cta_text',
        'cta_url',
    ];

    protected $casts = [
        'advantages'        => 'array',
        'results'           => 'array',
        'how_to_pass'       => 'array', 
        'faq'               => 'array',
        'featured_blog_ids' => 'array',
    ];

    // Метод для получения настроек или создания дефолтных
    public static function getSettings(string $type = 'patient')
    {
        return self::firstOrCreate(
            ['type' => $type],
            [
                'advantages'        => [],
                'results'           => [],
                'how_to_pass'       => [],
                'faq'               => [],
                'featured_blog_ids' => [],
                'how_to_test_text'  => 'Здесь будет инструкция о том, как сдать тест...',
            ]
        );
    }
}