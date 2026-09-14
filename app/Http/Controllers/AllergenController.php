<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Allergen;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AllergenController extends Controller
{
    public function index(): Response
    {
        $allergens = Allergen::with('relatedAllergens:id,name')
            ->select(['id', 'name', 'category', 'code', 'protein_family', 'type', 'description', 'included'])
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
                    'related' => $allergen->relatedAllergens->pluck('name')->toArray(),
                    'related_ids' => $allergen->relatedAllergens->pluck('id')->toArray(),
                ];
            });

        return Inertia::render('Admin/Search/Index', [
            'allergensData' => $allergens
        ]);
    }

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
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $allergen = Allergen::create($data);

        if (!empty($data['related_ids'])) {
            $allergen->relatedAllergens()->sync($data['related_ids']);
        }
        if ($request->hasFile('icon')) {
            $validated['icon'] = $request->file('icon')->store('allergens', 'public');
        }
        return redirect()->back()->with('success', 'Аллерген успешно создан');
    }

    public function update(Request $request, Allergen $allergen)
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
            'icon' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);


        $allergen->update($data);
        $allergen->relatedAllergens()->sync($data['related_ids'] ?? []);

        return redirect()->back()->with('success', 'Аллерген успешно обновлен');
    }

    public function destroy(Allergen $allergen)
    {
        $allergen->delete();
        return redirect()->back()->with('success', 'Аллерген удален');
    }
}