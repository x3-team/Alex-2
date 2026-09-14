<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class DemoResultController extends Controller
{
    public function index()
    {
        $pdfPath = Setting::get('demo_result_pdf');
        $demoTitle = trim((string) Setting::get('demo_result_meta_title', ''));
        $demoDescription = trim((string) Setting::get('demo_result_meta_description', ''));
        if ($demoTitle === '' || preg_match('/настройк|управлен|мета-тег|админ/iu', $demoTitle)) {
            $demoTitle = 'Пример результата теста ALEX²';
        }
        if ($demoDescription === '' || preg_match('/настройк|управлен|мета-тег|админ/iu', $demoDescription)) {
            $demoDescription = 'Интерактивный пример результата аллергологического исследования ALEX².';
        }

        return Inertia::render('Public/DemoResult', [
            'pdfUrl' => $pdfPath ? Storage::url($pdfPath) : null,
            'meta' => [
                'title'       => $demoTitle,
                'description' => $demoDescription,
                'keywords'    => Setting::get('demo_result_meta_keywords', ''),
            ],
        ]);
    }
}