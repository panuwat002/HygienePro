<?php

namespace App\Http\Controllers;

use App\Models\Checkpoint;
use App\Models\CheckpointCategory;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\CheckpointsExport;
use App\Imports\CheckpointsImport;

class CheckpointController extends Controller
{
    public function index()
    {
        $checkpoints = Checkpoint::with('category')->get();
        return view('checkpoints.index', compact('checkpoints'));
    }

    public function create()
    {
        $categories = CheckpointCategory::all();
        return view('checkpoints.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:checkpoint_categories,id',
            'image_good' => 'nullable|image|max:10240',
            'image_bad' => 'nullable|image|max:10240',
        ]);

        $data = $request->all();

        if ($request->hasFile('image_good')) {
            $data['image_good'] = $request->file('image_good')->store('standards', 'public');
        }
        if ($request->hasFile('image_bad')) {
            $data['image_bad'] = $request->file('image_bad')->store('standards', 'public');
        }

        Checkpoint::create($data);

        return redirect()->route('checkpoints.index')->with('success', 'Checkpoint created successfully.');
    }

    public function edit(Checkpoint $checkpoint)
    {
        $categories = CheckpointCategory::all();
        return view('checkpoints.edit', compact('checkpoint', 'categories'));
    }

    public function update(Request $request, Checkpoint $checkpoint)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:checkpoint_categories,id',
            'image_good' => 'nullable|image|max:10240',
            'image_bad' => 'nullable|image|max:10240',
        ]);

        $data = $request->all();

        if ($request->hasFile('image_good')) {
            $data['image_good'] = $request->file('image_good')->store('standards', 'public');
        }
        if ($request->hasFile('image_bad')) {
            $data['image_bad'] = $request->file('image_bad')->store('standards', 'public');
        }

        $checkpoint->update($data);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Updated successfully']);
        }

        return redirect()->route('checkpoints.index')->with('success', 'Checkpoint updated successfully.');
    }

    public function destroy(Checkpoint $checkpoint)
    {
        $checkpoint->delete();

        if (request()->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Deleted successfully']);
        }

        return redirect()->route('checkpoints.index')->with('success', 'Checkpoint deleted successfully.');
    }

    public function export()
    {
        return Excel::download(new CheckpointsExport, 'checkpoints.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        Excel::import(new CheckpointsImport, $request->file('file'));

        return redirect()->route('checkpoints.index')->with('success', 'นำเข้าข้อมูลจุดตรวจเรียบร้อยแล้ว');
    }

    public function quickStore(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:checkpoint_categories,id',
            'new_category_name' => 'nullable|string|max:255|unique:checkpoint_categories,name',
            'type' => 'nullable|in:person,area',
            'image_good' => 'nullable|image|max:10240',
            'image_bad' => 'nullable|image|max:10240',
        ]);

        $categoryId = $request->category_id;

        // Create new category if requested
        if ($request->filled('new_category_name')) {
            $category = CheckpointCategory::create([
                'name' => $request->new_category_name,
                'description' => 'Created via Quick Add',
                'is_active' => true
            ]);
            $categoryId = $category->id;
        }

        $data = [
            'title' => $request->title,
            'description' => $request->description,
            'category_id' => $categoryId,
            'is_active' => true,
            'type' => $request->type ?? 'person',
        ];

        if ($request->hasFile('image_good')) {
            $data['image_good'] = $request->file('image_good')->store('standards', 'public');
        }
        if ($request->hasFile('image_bad')) {
            $data['image_bad'] = $request->file('image_bad')->store('standards', 'public');
        }

        $checkpoint = Checkpoint::create($data);

        // Return JSON with category name for UI grouping
        $checkpoint->load('category');
        
        return response()->json([
            'success' => true,
            'checkpoint' => [
                'id' => $checkpoint->id,
                'title' => $checkpoint->title,
                'description' => $checkpoint->description,
                'category_name' => $checkpoint->category->name ?? 'ไม่มีหมวดหมู่',
                'category_id' => $checkpoint->category_id
            ]
        ]);
    }
}
