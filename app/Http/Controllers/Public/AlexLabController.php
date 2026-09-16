<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Setting;
use Inertia\Inertia;

class AlexLabController extends Controller
{
    /**
     * Универсальный метод для всех секций страницы "Лаборатория"
     * Принимает параметр $section: about, licenses, doctors, contacts, privacy, consent
     */
    public function section($section = 'about')
    {
        // Разрешённые секции
        $allowedSections = ['about', 'licenses', 'doctors', 'contacts', 'privacy', 'consent'];

        // Если секция невалидна — редирект на главную секцию
        if (!in_array($section, $allowedSections)) {
            return redirect()->route('alex-lab');
        }

        $settingsData = Setting::where('key', 'alex_lab_data')->first();
        $settings = $settingsData ? json_decode($settingsData->value, true) : [];

        // Загружаем врачей
        $doctors = User::where('is_admin', false)
            ->withCount(['blogs' => function ($query) {
                $query->where('is_active', true)
                    ->whereNotNull('published_at');
            }])
            ->with(['authorCategories'])
            ->orderBy('name')
            ->get()
            ->map(function ($author) {
                return [
                    'id' => $author->id,
                    'name' => $author->name,
                    'avatar' => $author->avatar,
                    'bio' => $author->bio,
                    'position' => $author->position ?? null,
                    'articles_count' => $author->blogs_count,
                    'categories' => $author->authorCategories->map(fn($c) => [
                        'id' => $c->id,
                        'name' => $c->name,
                        'slug' => $c->slug,
                    ])->values()->toArray(),
                ];
            })->values()->toArray();

        // Определяем title страницы в зависимости от секции
        $pageTitle = match($section) {
            'about' => 'О лаборатории — ALEX LAB',
            'licenses' => 'Лицензии — ALEX LAB',
            'doctors' => 'Врачи и эксперты — ALEX LAB',
            'contacts' => 'Контакты — ALEX LAB',
            'privacy' => 'Политика конфиденциальности — ALEX LAB',
            'consent' => 'Согласие на обработку ПД — ALEX LAB',
            default => 'ALEX LAB',
        };

        $seo = is_array($settings['seo'] ?? null) ? $settings['seo'] : [];
        $seoTitle = trim((string) ($seo['meta_title'] ?? ''));
        $seoDescription = trim((string) ($seo['meta_description'] ?? ''));
        $seoKeywords = trim((string) ($seo['meta_keywords'] ?? ''));

        $pageDescription = match ($section) {
            'licenses' => 'Лицензии и разрешительные документы лаборатории ALEX LAB.',
            'contacts' => 'Контакты лаборатории ALEX LAB: адрес, телефон, реквизиты.',
            'privacy' => 'Политика конфиденциальности лаборатории ALEX LAB.',
            'consent' => 'Согласие на обработку персональных данных ALEX LAB.',
            'doctors' => 'Врачи и эксперты лаборатории ALEX LAB.',
            default => ($seoDescription !== '' ? $seoDescription : 'Лаборатория ALEX LAB — тест на аллергию ALEX².'),
        };

        // Parent CMS SEO title/description apply only to /alex-lab, not child sections.
        $resolvedTitle = ($section === 'about' && $seoTitle !== '') ? $seoTitle : $pageTitle;
        $resolvedDescription = $pageDescription;

        return Inertia::render('Public/AlexLab', [
            'pageTitle' => $resolvedTitle,
            'meta' => [
                'title' => $resolvedTitle,
                'description' => $resolvedDescription,
                'keywords' => $section === 'about' ? $seoKeywords : '',
            ],
            'about' => [
                'title' => 'О лаборатории',
                'content' => $settings['about_content'] ?? '',
            ],
            'licenses' => $settings['licenses'] ?? [],
            'contacts' => array_merge([
                'address' => '',
                'phone' => '',
                'email' => '',
                'work_hours' => [],
                'map_coords' => '',
                'inn' => '',
                'ogrn' => '',
                'kpp' => '',
                'socials' => []
            ], $settings['contacts'] ?? []),
            'doctors' => $doctors,
            'privacy_policy' => [
                'title' => 'Политика конфиденциальности',
                'content' => $settings['privacy_policy_content'] ?? '',
            ],
            'consent' => [
                'title' => 'Согласие на обработку персональных данных',
                'content' => $settings['consent_content'] ?? '',
            ],
            'activeSection' => $section,
        ]);
    }

    // Оставляем старые методы для обратной совместимости
    public function index()
    {
        return $this->section('about');
    }

    public function privacyPolicy()
    {
        return $this->section('privacy');
    }

    public function consent()
    {
        return $this->section('consent');
    }
}