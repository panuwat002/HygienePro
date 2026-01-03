<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Checkpoint;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function index()
    {
        $locations = Location::withCount('checkpoints')->get();
        return view('locations.index', compact('locations'));
    }

    public function create()
    {
        return view('locations.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'location_name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        Location::create($request->all());

        return redirect()->route('locations.index')->with('success', 'Location created successfully.');
    }

    public function edit(Location $location)
    {
        return view('locations.edit', compact('location'));
    }

    public function update(Request $request, Location $location)
    {
        $request->validate([
            'location_name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $location->update($request->all());

        return redirect()->route('locations.index')->with('success', 'Location updated successfully.');
    }

    public function destroy(Location $location)
    {
        $location->delete();
        return redirect()->route('locations.index')->with('success', 'Location deleted successfully.');
    }

    public function showMapping(Location $location)
    {
        $allCheckpoints = Checkpoint::with('category')->get()->groupBy('category.name');
        $selectedCheckpoints = $location->checkpoints->pluck('id')->toArray();
        $categories = \App\Models\CheckpointCategory::all(); // Fetch all categories
        
        return view('locations.map', compact('location', 'allCheckpoints', 'selectedCheckpoints', 'categories'));
    }

    public function saveMapping(Request $request, Location $location)
    {
        $location->checkpoints()->sync($request->checkpoint_ids ?? []);
        return redirect()->route('locations.index')->with('success', 'Mapping updated successfully.');
    }
}
