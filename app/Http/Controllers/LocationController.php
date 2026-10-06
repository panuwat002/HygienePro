<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Checkpoint;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\LocationsExport;
use App\Imports\LocationsImport;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class LocationController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware(function ($request, $next) {
                $user = auth()->user();
                $readOnlyMethods = ['index', 'show', 'export', 'showMapping', 'showBulkMapping'];
                
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
        // A HAVING with no GROUP BY. MySQL allows it, so this worked; it is not
        // valid SQL, so the page could not be exercised by a test at all -
        // SQLite answers "HAVING clause on a non-aggregate query". Same
        // predicate, written as the WHERE it always was.
        $locations = Location::with('department')
            ->withCount(['checkpoints', 'machines'])
            ->where(function ($q) {
                $q->whereHas('checkpoints')
                  ->orWhereHas('machines')
                  ->orWhere('description', '!=', 'Auto Created from Schedule Import')
                  ->orWhereNull('description');
            })
            ->get();

        return view('locations.index', [
            'locations' => $locations,
            'departments' => $this->departments(),
        ]);
    }

    public function create()
    {
        return view('locations.create', ['departments' => $this->departments()]);
    }

    /**
     * Who may own an area. Which department runs a room decides who a finding
     * raised in it is handed to - see InspectionLog::owningDepartmentId().
     */
    private function departments()
    {
        return \App\Models\Department::orderBy('dept_name')->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'location_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        Location::create($request->all());

        return redirect()->route('locations.index')->with('success', 'Location created successfully.');
    }

    public function edit(Location $location)
    {
        return view('locations.edit', ['location' => $location, 'departments' => $this->departments()]);
    }

    public function update(Request $request, Location $location)
    {
        $request->validate([
            'location_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        $location->update($request->all());

        return redirect()->route('locations.index')->with('success', 'Location updated successfully.');
    }

    /**
     * Put many areas under one department in a single step.
     *
     * Every area has to be told who runs it before findings raised in it reach
     * the right people, and a plant has dozens of rooms that mostly belong to
     * the same department. Opening each one in turn to set the same value is
     * the kind of work that does not get finished, and an area left unassigned
     * silently falls back to billing its findings to whoever walked the round.
     */
    public function assignDepartmentBulk(Request $request)
    {
        $request->validate([
            'location_ids' => 'required|array',
            'location_ids.*' => 'integer|exists:locations,id',
            // '' clears it, which is how a room gets handed back to the old
            // fallback if it was assigned by mistake.
            'department_id' => 'nullable|exists:departments,id',
        ]);

        $departmentId = $request->input('department_id') ?: null;

        $updated = Location::whereIn('id', $request->location_ids)
            ->update(['department_id' => $departmentId]);

        $name = $departmentId
            ? (\App\Models\Department::find($departmentId)?->dept_name ?? '-')
            : null;

        return redirect()->route('locations.index')->with('success', $name
            ? "กำหนดให้แผนก {$name} ดูแล {$updated} พื้นที่เรียบร้อยแล้ว"
            : "ล้างแผนกที่ดูแลออกจาก {$updated} พื้นที่แล้ว");
    }

    public function destroy(Location $location)
    {
        try {
            $location->delete();
            return redirect()->route('locations.index')->with('success', 'ลบข้อมูลจุดประจำการเรียบร้อยแล้ว');
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() == "23000") {
                return redirect()->route('locations.index')->with('error', 'ไม่สามารถลบข้อมูลได้ เนื่องจากห้องนี้มีประวัติการถูกใช้งานในระบบแล้ว');
            }
            return redirect()->route('locations.index')->with('error', 'เกิดข้อผิดพลาดในการลบข้อมูล');
        }
    }

    public function destroyBulk(Request $request)
    {
        $request->validate([
            'location_ids' => 'required|array',
            'location_ids.*' => 'exists:locations,id',
        ]);

        try {
            Location::whereIn('id', $request->location_ids)->delete();
            return redirect()->route('locations.index')->with('success', 'ลบข้อมูลจุดประจำการที่เลือกเรียบร้อยแล้ว');
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() == "23000") {
                return redirect()->route('locations.index')->with('error', 'ไม่สามารถลบห้องบางส่วนได้ เนื่องจากมีประวัติการถูกใช้งานในระบบแล้ว');
            }
            return redirect()->route('locations.index')->with('error', 'เกิดข้อผิดพลาดในการลบข้อมูล');
        }
    }

    public function export()
    {
        return Excel::download(new LocationsExport, 'locations.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        Excel::import(new LocationsImport, $request->file('file'));

        return redirect()->route('locations.index')->with('success', 'นำเข้าข้อมูลจุดประจำการเรียบร้อยแล้ว');
    }

    public function showMapping(Location $location)
    {
        $allCheckpoints = Checkpoint::with('category')->orderBy('sort_order')->orderBy('id')->get()->groupBy('category.name');
        $selectedCheckpoints = $location->checkpoints->pluck('id')->toArray();
        $categories = \App\Models\CheckpointCategory::all(); // Fetch all categories
        
        return view('locations.map', compact('location', 'allCheckpoints', 'selectedCheckpoints', 'categories'));
    }

    public function saveMapping(Request $request, Location $location)
    {
        $location->checkpoints()->sync($request->checkpoint_ids ?? []);
        return redirect()->route('locations.index')->with('success', 'Mapping updated successfully.');
    }

    public function showBulkMapping()
    {
        $locations = Location::withCount('checkpoints')->orderBy('location_name')->get();
        
        $allCheckpoints = Checkpoint::where('is_active', true)
                            ->with('category')
                            ->orderBy('sort_order')
                            ->orderBy('id')
                            ->get()
                            ->groupBy('category.name');

        return view('locations.bulk-map', compact('locations', 'allCheckpoints'));
    }

    public function saveBulkMapping(Request $request)
    {
        $request->validate([
            'location_ids' => 'required|array|min:1',
            'location_ids.*' => 'exists:locations,id',
            'checkpoint_ids' => 'required|array|min:1',
            'checkpoint_ids.*' => 'exists:checkpoints,id',
            'mode' => 'required|in:add,replace',
        ]);

        $locationIds = $request->location_ids;
        $checkpointIds = $request->checkpoint_ids;
        $mode = $request->mode;

        $count = 0;
        foreach ($locationIds as $locationId) {
            $location = Location::find($locationId);
            if ($location) {
                if ($mode === 'replace') {
                    $location->checkpoints()->sync($checkpointIds);
                } else {
                    $location->checkpoints()->syncWithoutDetaching($checkpointIds);
                }
                $count++;
            }
        }

        $modeText = $mode === 'replace' ? 'แทนที่' : 'เพิ่ม';
        return redirect()->route('locations.index')
            ->with('success', "{$modeText}จุดตรวจ " . count($checkpointIds) . " รายการ ให้สถานที่ {$count} รายการ เรียบร้อยแล้ว");
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'location_ids' => 'required|array|min:1',
            'location_ids.*' => 'exists:locations,id',
        ]);

        $count = Location::whereIn('id', $request->location_ids)->delete();

        return redirect()->route('locations.index')
            ->with('success', "ลบข้อมูลพื้นที่ {$count} รายการ เรียบร้อยแล้ว");
    }
}
