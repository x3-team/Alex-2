<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Allergen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AllergenController extends Controller
{
    /**
     * Страница списка и поиска аллергенов в админке
     */
    public function index(): Response
    {
        $allergens = Allergen::with('relatedAllergens:id,name')
            ->select(['id', 'name', 'category', 'code', 'protein_family', 'type', 'description', 'included', 'icon'])
            ->get()
            ->map(function ($allergen) {
                return [
                    'id' => $allergen->id,
                    'name' => $allergen->name,
                    'category' => $allergen->category,
                    'code' => $allergen->code,
                    'type' => $allergen->type,
                    'protein_family' => $allergen->protein_family,
                    'description' => $allergen->description,
                    'included' => (bool)$allergen->included,
                    'icon' => $allergen->icon,
                    'icon_url' => $allergen->icon ? Storage::url($allergen->icon) : null,
                    'related' => $allergen->relatedAllergens->pluck('name')->toArray(),
                    'related_ids' => $allergen->relatedAllergens->pluck('id')->toArray(),
                ];
            });

        return Inertia::render('Admin/Search/Index', [
            'allergensData' => $allergens
        ]);
    }

    /**
     * Создание нового аллергена
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'code' => 'nullable|string|max:255',
            'protein_family' => 'nullable|string|max:255',
            'type' => 'required|in:E,M',
            'description' => 'nullable|string',
            'included' => 'boolean',
            'related_ids' => 'nullable|array',
            'related_ids.*' => 'exists:allergens,id',
            'icon' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
        ]);

        if ($request->hasFile('icon')) {
            $data['icon'] = $request->file('icon')->store('allergens', 'public');
        }

        $allergen = Allergen::create($data);

        if (!empty($data['related_ids'])) {
            $allergen->relatedAllergens()->sync($data['related_ids']);
        }

        return redirect()->back()->with('success', 'Аллерген успешно создан');
    }

    /**
     * Обновление существующего аллергена
     */
    public function update(Request $request, Allergen $allergen)
    {
        // Приводим 'included' из строки "true"/"false" к честному boolean
        if ($request->has('included')) {
            $request->merge([
                'included' => filter_var($request->input('included'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'code' => 'nullable|string|max:255',
            'protein_family' => 'nullable|string|max:255',
            'type' => 'required|in:E,M',
            'description' => 'nullable|string',
            'included' => 'boolean',
            'related_ids' => 'nullable|array',
            'related_ids.*' => 'exists:allergens,id',
            'icon' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
        ]);

        if ($request->hasFile('icon')) {
            if ($allergen->icon) {
                Storage::disk('public')->delete($allergen->icon);
            }
            $data['icon'] = $request->file('icon')->store('allergens', 'public');
        } else {
            unset($data['icon']);
        }

        $allergen->update($data);

        // Синхронизируем связанные аллергены
        $allergen->relatedAllergens()->sync($request->input('related_ids', []));

        return redirect()->back()->with('success', 'Аллерген успешно обновлен');
    }

    /**
     * Удаление аллергена
     */
    public function destroy(Allergen $allergen)
    {
        if ($allergen->icon) {
            Storage::disk('public')->delete($allergen->icon);
        }

        $allergen->delete();
        return redirect()->back()->with('success', 'Аллерген удален');
    }
}