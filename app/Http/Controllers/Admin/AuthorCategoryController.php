<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuthorCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuthorCategoryController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/AuthorCategory/Index', [
            'categories' => AuthorCategory::latest()->paginate(10)
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/AuthorCategory/Form', [
            'category' => null
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:author_categories,name',
        ]);

        // 🔹 Автоматически генерируем slug из name
        \App\Models\AuthorCategory::create([
            'name' => $request->name,
            'slug' => \Illuminate\Support\Str::slug($request->name),
        ]);

        return redirect()->route('admin.author-categories.index')
            ->with('success', 'Категория успешно создана!');
    }

    public function edit(AuthorCategory $authorCategory)
    {
        return Inertia::render('Admin/AuthorCategory/Form', [
            'category' => $authorCategory
        ]);
    }

    public function update(Request $request, \App\Models\AuthorCategory $authorCategory)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:author_categories,name,' . $authorCategory->id,
        ]);

        // 🔹 Автоматически обновляем slug при изменении name
        $authorCategory->update([
            'name' => $request->name,
            'slug' => \Illuminate\Support\Str::slug($request->name),
        ]);

        return redirect()->route('admin.author-categories.index')
            ->with('success', 'Категория успешно обновлена!');
    }

    public function destroy(AuthorCategory $authorCategory)
    {
        $authorCategory->delete();
        return redirect()->back()->with('success', 'Категория удалена!');
    }
}