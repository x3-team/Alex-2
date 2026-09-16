<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
class AlexLabController extends Controller
{
    public function index()
    {
        $data = \App\Models\Setting::where('key', 'alex_lab_data')->first();
        $settings = $data ? json_decode($data->value, true) : [
            'about_content' => '',
            'privacy_policy_content' => '',
            'consent_content' => '',
            'licenses' => [],
            'contacts' => [
                'address' => '',
                'phone' => '',
                'email' => '',
                'work_hours' => ['ПН-ПТ 9:00-19:00', 'СБ-ВС Выходной'],
                'inn' => '',
                'ogrn' => '',
                'kpp' => '',
                'socials' => [],
                'map_coords' => ''
            ],
            'seo' => [
                'meta_title' => '',
                'meta_description' => '',
                'meta_keywords' => '',
            ],
        ];
        if (!isset($settings['seo']) || !is_array($settings['seo'])) {
            $settings['seo'] = [
                'meta_title' => '',
                'meta_description' => '',
                'meta_keywords' => '',
            ];
        }

        return Inertia::render('Admin/AlexLab/Index', [
            'settings' => $settings
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'about_content' => 'nullable|string',
            'privacy_policy_content' => 'nullable|string', // 🔹 НОВОЕ
            'consent_content' => 'nullable|string', // 🔹 НОВОЕ
            'licenses' => 'array',
            'licenses.*.title' => 'string',
            'licenses.*.file_path' => 'string',
            'contacts' => 'array',
            'contacts.address' => 'nullable|string|max:500',
            'contacts.phone' => 'nullable|string|max:50',
            'contacts.email' => 'nullable|email|max:255',
            'contacts.work_hours' => 'nullable|array',
            'contacts.work_hours.*' => 'string|max:100',
            'contacts.inn' => 'nullable|string',
            'contacts.ogrn' => 'nullable|string',
            'contacts.kpp' => 'nullable|string',
            'contacts.socials' => 'array',
            'contacts.map_coords' => 'nullable|string',
            'seo' => 'nullable|array',
            'seo.meta_title' => 'nullable|string|max:255',
            'seo.meta_description' => 'nullable|string|max:500',
            'seo.meta_keywords' => 'nullable|string|max:500',
        ]);

        $existing = \App\Models\Setting::where('key', 'alex_lab_data')->first();
        $existingData = $existing ? (json_decode($existing->value, true) ?: []) : [];
        $validated['seo'] = [
            'meta_title' => $validated['seo']['meta_title'] ?? ($existingData['seo']['meta_title'] ?? ''),
            'meta_description' => $validated['seo']['meta_description'] ?? ($existingData['seo']['meta_description'] ?? ''),
            'meta_keywords' => $validated['seo']['meta_keywords'] ?? ($existingData['seo']['meta_keywords'] ?? ''),
        ];

        \App\Models\Setting::updateOrCreate(
            ['key' => 'alex_lab_data'],
            ['value' => json_encode($validated)]
        );

        return redirect()->back()->with('success', 'Данные обновлены');
    }

    public function uploadLicense(Request $request)
    {
        try {
            // 🔹 Получаем файл напрямую, без hasFile
            $file = $request->file('file');

            if (!$file) {
                Log::error('Upload License: No file in request', [
                    'all_files' => $request->allFiles(),
                    'all_data' => $request->all(),
                ]);
                return response()->json([
                    'error' => 'Файл не был получен сервером'
                ], 400);
            }

            // 🔹 Проверяем, что файл валидный
            if (!$file->isValid()) {
                Log::error('Upload License: Invalid file', [
                    'error' => $file->getError(),
                ]);
                return response()->json([
                    'error' => 'Файл повреждён или невалиден: ' . $file->getErrorMessage()
                ], 400);
            }

            // 🔹 Валидация
            $validated = $request->validate([
                'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240'
            ]);

            // 🔹 Сохраняем файл
            $path = $file->store('licenses', 'public');

            return response()->json([
                'path' => Storage::url($path),
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime' => $file->getMimeType()
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Upload License: Validation Error', [
                'errors' => $e->errors(),
            ]);
            return response()->json([
                'error' => 'Ошибка валидации',
                'details' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Upload License: Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'Ошибка загрузки файла',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}