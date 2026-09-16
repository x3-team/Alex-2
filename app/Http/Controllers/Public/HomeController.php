<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\HomeSetting;
use App\Models\Blog;
use Inertia\Inertia;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        return $this->renderPage('patient');
    }

    public function doctorIndex(Request $request)
    {
        return $this->renderPage('doctor');
    }

    private function renderPage(string $mode = 'patient')
    {
        $patientSettings = HomeSetting::getSettings('patient');
        $doctorSettings  = HomeSetting::getSettings('doctor');

        // Выбираем активные настройки в зависимости от URL
        $currentSettings = ($mode === 'doctor') ? $doctorSettings : $patientSettings;

        $blogIds = array_filter((array) ($currentSettings->featured_blog_ids ?? []));

        if (!empty($blogIds)) {
            $idsString = implode(',', array_map('intval', $blogIds));
            $featuredBlogs = Blog::with(['author' => function($q) {
                $q->select('id', 'name', 'avatar', 'description', 'bio', 'is_admin');
            }, 'category'])
                ->where('is_active', true)
                ->whereIn('id', $blogIds)
                ->when($mode === 'doctor', function ($q) {
                    $q->where('audience', 'doctors');
                }, function ($q) {
                    $q->where(function ($inner) {
                        $inner->where('audience', 'patients')->orWhereNull('audience');
                    });
                })
                ->orderByRaw("FIELD(id, {$idsString})")
                ->get();
            $featuredBlogs->each->hideNonPublicAuthor();
        } else {
            $featuredBlogs = [];
        }

        $faqData = null;
        if (!empty($currentSettings->faq)) {
            $faqData = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => array_map(function ($item) {
                    return [
                        '@type' => 'Question',
                        'name' => $item['title'] ?? $item['question'] ?? '',
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => strip_tags($item['description'] ?? $item['answer'] ?? '')
                        ]
                    ];
                }, $currentSettings->faq)
            ];
        }

        $cmsTitle = trim((string) ($currentSettings->meta_title ?? ''));
        $cmsDescription = trim((string) ($currentSettings->meta_description ?? ''));
        $cmsKeywords = trim((string) ($currentSettings->meta_keywords ?? ''));
        $fallbackTitle = $mode === 'doctor'
            ? 'ALEX² для врачей — тест на аллергию'
            : 'Тест на аллергию ALEX² — 300 аллергенов за один анализ';
        $titleLower = mb_strtolower($cmsTitle);
        if (
            $cmsTitle === ''
            || $titleLower === mb_strtolower($cmsDescription)
            || $titleLower === mb_strtolower($cmsKeywords)
            || $titleLower === 'лаборатория alexlab'
            || $titleLower === 'ffffff'
        ) {
            $cmsTitle = $fallbackTitle;
        }

        $organizationData = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'ALEX²',
            'url' => config('app.url'),
            'logo' => [
                '@type' => 'ImageObject',
                'url' => rtrim((string) config('app.url'), '/') . '/og-favicon.png',
                'width' => 512,
                'height' => 512,
            ],
            'description' => $currentSettings->meta_description ?? '',
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'telephone' => '+7(977)-447-49-07',
                'contactType' => 'customer service',
                'areaServed' => 'RU',
                'availableLanguage' => ['Russian']
            ]
        ];

        return Inertia::render('Public/Welcome', [
            'isDoctorRoute' => ($mode === 'doctor'),
            'meta' => [
                'title'       => $cmsTitle,
                'description' => $currentSettings->meta_description ?? '',
                'keywords'    => $currentSettings->meta_keywords ?? '',
            ],
            'homeSettings' => [
                'patient' => [
                    'advantages'    => $patientSettings->advantages ?: [],
                    'results'       => $patientSettings->results ?: [],
                    'how_to_pass'   => $patientSettings->how_to_pass ?: [],
                    'faq'           => $patientSettings->faq ?: [],
                    'hero_title'    => $patientSettings->hero_title ?? '',
                    'hero_subtitle' => $patientSettings->hero_subtitle ?? '',
                    'why_subtitle'  => $patientSettings->why_subtitle ?? '',
                    'cta_text'      => $patientSettings->cta_text ?? '',
                    'cta_url'       => $patientSettings->cta_url ?? '',
                ],
                'doctor' => [
                    'advantages'    => $doctorSettings->advantages ?: [],
                    'results'       => $doctorSettings->results ?: [],
                    'how_to_pass'   => $doctorSettings->how_to_pass ?: [],
                    'faq'           => $doctorSettings->faq ?: [],
                    'hero_title'    => $doctorSettings->hero_title ?? '',
                    'hero_subtitle' => $doctorSettings->hero_subtitle ?? '',
                    'why_subtitle'  => $doctorSettings->why_subtitle ?? '',
                    'cta_text'      => $doctorSettings->cta_text ?? '',
                    'cta_url'       => $doctorSettings->cta_url ?? '',
                ],
            ],
            'featuredBlogs' => $featuredBlogs,
            'seoJsonLd' => array_filter([$organizationData, $faqData]),
        ]);
    }
}