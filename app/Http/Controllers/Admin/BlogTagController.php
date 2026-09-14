<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogTag;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BlogTagController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/BlogTag/Index', [
            'tags' => BlogTag::latest()->paginate(15)
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/BlogTag/Form', ['tag' => null]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:blog_tags,name',
            'slug' => 'nullable|string|max:255|unique:blog_tags,slug'
        ]);
        
        if (empty($validated['slug'])) {
            $validated['slug'] = \Illuminate\Support\Str::slug($validated['name']);
        }
        
        BlogTag::create($validated);
        return redirect()->route('admin.blog-tags.index')->with('success', 'Тег создан!');
    }

    public function edit(BlogTag $blogTag)
    {
        return Inertia::render('Admin/BlogTag/Form', ['tag' => $blogTag]);
    }

    public function update(Request $request, BlogTag $blogTag)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:blog_tags,name,'.$blogTag->id,
            'slug' => 'nullable|string|max:255|unique:blog_tags,slug,'.$blogTag->id
        ]);
        
        if (empty($validated['slug'])) {
            $validated['slug'] = \Illuminate\Support\Str::slug($validated['name']);
        }
        
        $blogTag->update($validated);
        return redirect()->route('admin.blog-tags.index')->with('success', 'Тег обновлён!');
    }

    public function destroy(BlogTag $blogTag)
    {
        $blogTag->delete();
        return redirect()->back()->with('success', 'Тег удалён!');
    }
}