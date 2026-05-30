<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:categories.view')->only(['index', 'show']);
        $this->middleware('can:categories.create')->only(['create', 'store']);
        $this->middleware('can:categories.edit')->only(['edit', 'update']);
        $this->middleware('can:categories.delete')->only(['destroy']);
    }

    public function index()
    {
        $categories = Category::withCount('products')->orderBy('name')->get();
        return view('categories.index', compact('categories'));
    }

    public function create()
    {
        return view('categories.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string'
        ]);

        Category::create($validated);

        return redirect()->route('categories.index')
            ->with('success', 'تم إضافة الفئة بنجاح');
    }

    public function edit(Category $category)
    {
        return view('categories.edit', compact('category'));
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string'
        ]);

        $category->update($validated);

        return redirect()->route('categories.index')
            ->with('success', 'تم تحديث الفئة بنجاح');
    }

    public function destroy(Category $category)
    {
        if ($category->products()->count() > 0) {
            return back()->with('error', 'لا يمكن حذف الفئة لأنها تحتوي على منتجات');
        }

        $category->delete();

        return redirect()->route('categories.index')
            ->with('success', 'تم حذف الفئة بنجاح');
    }
}
