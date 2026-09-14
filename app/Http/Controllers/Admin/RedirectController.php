<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Redirect;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RedirectController extends Controller
{
    public function index(Request $request)
    {
        $query = Redirect::query();

        // Поиск
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('from_url', 'like', "%{$search}%")
                    ->orWhere('to_url', 'like', "%{$search}%");
            });
        }

        // Фильтр по статусу
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $redirects = $query->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Seo/Redirects/Index', [
            'redirects' => $redirects,
            'filters' => [
                'search' => $request->search,
                'status' => $request->status,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Seo/Redirects/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'from_url' => 'required|string|max:255',
            'to_url' => 'required|string|max:255',
            'status_code' => 'required|in:301,302',
            'is_active' => 'boolean',
        ]);

        // Нормализуем URL
        $validated['from_url'] = Redirect::normalizeUrl($validated['from_url']);
        $validated['to_url'] = Redirect::flattenToUrl($validated['to_url'], $validated['from_url']);

        // Проверяем уникальность from_url
        $exists = Redirect::where('from_url', $validated['from_url'])->exists();
        if ($exists) {
            return back()->withErrors([
                'from_url' => 'Редирект с этого URL уже существует'
            ])->withInput();
        }

        // Проверяем, что from_url != to_url
        if ($validated['from_url'] === $validated['to_url']) {
            return back()->withErrors([
                'to_url' => 'URL источника и назначения не могут совпадать'
            ])->withInput();
        }

        Redirect::create($validated);

        return redirect()->route('admin.redirects.index')
            ->with('success', 'Редирект создан!');
    }

    public function edit(Redirect $redirect)
    {
        return Inertia::render('Admin/Seo/Redirects/Edit', [
            'redirect' => $redirect,
        ]);
    }

    public function update(Request $request, Redirect $redirect)
    {
        $validated = $request->validate([
            'from_url' => 'required|string|max:255',
            'to_url' => 'required|string|max:255',
            'status_code' => 'required|in:301,302',
            'is_active' => 'boolean',
        ]);

        // Нормализуем URL
        $validated['from_url'] = Redirect::normalizeUrl($validated['from_url']);
        $validated['to_url'] = Redirect::flattenToUrl($validated['to_url'], $validated['from_url']);

        // Проверяем уникальность (исключая текущий)
        $exists = Redirect::where('from_url', $validated['from_url'])
            ->where('id', '!=', $redirect->id)
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'from_url' => 'Редирект с этого URL уже существует'
            ])->withInput();
        }

        if ($validated['from_url'] === $validated['to_url']) {
            return back()->withErrors([
                'to_url' => 'URL источника и назначения не могут совпадать'
            ])->withInput();
        }

        $redirect->update($validated);

        return redirect()->route('admin.redirects.index')
            ->with('success', 'Редирект обновлён!');
    }

    public function destroy(Redirect $redirect)
    {
        $redirect->delete();
        return redirect()->route('admin.redirects.index')
            ->with('success', 'Редирект удалён!');
    }

    public function toggle(Redirect $redirect)
    {
        $redirect->update(['is_active' => !$redirect->is_active]);
        return back()->with('success', 'Статус изменён!');
    }
}