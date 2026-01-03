<?php

namespace App\Http\Controllers;

use App\Models\Machine;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MachineController extends Controller
{
    public function index()
    {
        $machines = Machine::with('location')->orderBy('location_id')->get();
        return view('machines.index', compact('machines'));
    }

    public function create()
    {
        $locations = Location::all();
        return view('machines.create', compact('locations'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'location_id' => 'required|exists:locations,id',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
        ]);

        $data = $request->except('image');

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('machines', 'public');
        }

        Machine::create($data);

        return redirect()->route('machines.index')->with('success', 'Machine created successfully.');
    }

    public function edit(Machine $machine)
    {
        $locations = Location::all();
        return view('machines.edit', compact('machine', 'locations'));
    }

    public function update(Request $request, Machine $machine)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'location_id' => 'required|exists:locations,id',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
        ]);

        $data = $request->except('image');

        if ($request->hasFile('image')) {
            if ($machine->image) {
                Storage::disk('public')->delete($machine->image);
            }
            $data['image'] = $request->file('image')->store('machines', 'public');
        }

        $machine->update($data);

        return redirect()->route('machines.index')->with('success', 'Machine updated successfully.');
    }

    public function destroy(Machine $machine)
    {
        $machine->delete();
        return redirect()->route('machines.index')->with('success', 'Machine deleted successfully.');
    }

    public function showMapping(Machine $machine)
    {
        // Get all available checkpoints grouped by category
        // Only show 'area' type checkpoints for machines? Or allow both? 
        // Asking user request: "Check Machine and Equipment". Likely 'area' type or specific 'machine' type if we had one.
        // For now, let's allow all 'area' type checkpoints.
        
        $allCheckpoints = \App\Models\Checkpoint::where('is_active', true)
                            // ->where('type', 'area') // Filter by Area type? Or allow mixed? Let's show all for flexibility or stick to area.
                            // User previously asked for 'Area: Machine/Area'. So stick to 'area' type?
                            // Actually, let's allow ALL for now so they can reuse 'Safety' from Person if needed? 
                            // No, stick to 'area' to prevent confusion with Person hygiene.
                            // UPDATE: Wait, maybe they want specific Machine checks.
                            // Let's simple show ALL but maybe sort/filter in UI.
                            ->with('category')
                            ->get()
                            ->groupBy('category.name');

        $categories = \App\Models\CheckpointCategory::all();

        // Get currently assigned checkpoint IDs
        $selectedCheckpoints = $machine->checkpoints->pluck('id')->toArray();

        return view('machines.map', compact('machine', 'allCheckpoints', 'selectedCheckpoints', 'categories'));
    }

    public function saveMapping(Request $request, Machine $machine)
    {
        $request->validate([
            'checkpoint_ids' => 'array',
            'checkpoint_ids.*' => 'exists:checkpoints,id',
        ]);

        $machine->checkpoints()->sync($request->checkpoint_ids ?? []);

        return redirect()->route('machines.map', $machine->id)
            ->with('success', 'บันทึกรายการจุดตรวจสำหรับเครื่องจักรเรียบร้อยแล้ว');
    }
}
