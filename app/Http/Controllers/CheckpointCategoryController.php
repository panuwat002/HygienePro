<?php

namespace App\Http\Controllers;

use App\Models\CheckpointCategory;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CheckpointCategoryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware(function ($request, $next) {
                $user = auth()->user();
                $readOnlyMethods = ['index', 'show', 'export'];
                
                if ($user && !in_array($request->route()->getActionMethod(), $readOnlyMethods)) {
                    if (!$user->isAdmin() && !$user->hasGlobalVisibility()) {
                        abort(403, 'Unauthorized. Only users with global visibility can modify system-wide master data.');
                    }
                }
                return $next($request);
            }),
        ];
    }
    public function index()
    {
        $categories = CheckpointCategory::withCount('checkpoints')->get();
        return view('checkpoint-categories.index', compact('categories'));
    }

    public function create()
    {
        return view('checkpoint-categories.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'nullable|string|max:50',
        ]);

        CheckpointCategory::create($request->all());

        return redirect()->route('checkpoint-categories.index')->with('success', 'Category created successfully.');
    }

    public function edit(CheckpointCategory $checkpoint_category)
    {
        return view('checkpoint-categories.edit', compact('checkpoint_category'));
    }

    public function update(Request $request, CheckpointCategory $checkpoint_category)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'nullable|string|max:50',
        ]);

        $checkpoint_category->update($request->all());

        return redirect()->route('checkpoint-categories.index')->with('success', 'Category updated successfully.');
    }

    public function destroy(CheckpointCategory $checkpoint_category)
    {
        $checkpoint_category->delete();
        return redirect()->route('checkpoint-categories.index')->with('success', 'Category deleted successfully.');
    }
}
