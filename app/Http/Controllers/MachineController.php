<?php

namespace App\Http\Controllers;

use App\Models\Machine;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\MachinesExport;


class MachineController extends Controller
{
    public function index()
    {
        $machines = Machine::with('location')->orderBy('location_id')->paginate(15);
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
        return redirect()->route('machines.index')->with('success', 'ลบข้อมูลเครื่องจักรเรียบร้อยแล้ว');
    }

    public function destroyBulk(Request $request)
    {
        $request->validate([
            'machine_ids' => 'required|array',
            'machine_ids.*' => 'exists:machines,id',
        ]);

        Machine::whereIn('id', $request->machine_ids)->delete();

        return redirect()->route('machines.index')->with('success', 'ลบข้อมูลเครื่องจักรที่เลือกเรียบร้อยแล้ว');
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

    public function export()
    {
        return Excel::download(new MachinesExport, 'machines.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        $file = $request->file('file');
        
        // Get real path of the uploaded temp file (e.g., C:\Windows\Temp\php1234.tmp)
        $fullPath = $file->getRealPath();
        
        // Normalize directory separators for Windows
        $fullPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $fullPath);
        
        if (!file_exists($fullPath)) {
            return redirect()->route('machines.index')->with('error', 'PHP Error: File does not exist at ' . $fullPath);
        }
        
        // Execute python script using specified Python executable or default
        $scriptPath = base_path('scripts/python/smart_machine_import.py');
        $pythonExecutable = env('PYTHON_PATH', 'python');
        $command = escapeshellarg($pythonExecutable) . " " . escapeshellarg($scriptPath) . " " . escapeshellarg($fullPath) . " 2>&1";
        $output = shell_exec($command);
        
        if (!$output) {
            return redirect()->route('machines.index')->with('error', 'ไม่สามารถประมวลผลไฟล์ได้ (ไม่มีการตอบกลับจาก Python)');
        }
        
        // Extract JSON part in case there are warnings printed before it
        $jsonStart = strpos($output, '{');
        if ($jsonStart === false) {
            // Output is likely an error message
            return redirect()->route('machines.index')->with('error', 'Python Error: ' . $output);
        }
        
        $output = substr($output, $jsonStart);
        
        $result = json_decode($output, true);
        
        if (!$result || isset($result['error'])) {
            $errorMsg = $result['error'] ?? 'เกิดข้อผิดพลาดในการประมวลผลไฟล์ด้วย Python';
            return redirect()->route('machines.index')->with('error', $errorMsg);
        }
        
        $machinesData = $result['data'] ?? [];
        
        foreach ($machinesData as $data) {
            $id = $data['id'] ?? '';
            $code = $data['code'] ?? '';
            $name = $data['name'] ?? '';
            $locationName = $data['location_name'] ?? '';
            $description = $data['description'] ?? '';
            $status = $data['status'] ?? 'ใช้งาน';
            
            if (!$code) {
                $code = 'M-' . \Illuminate\Support\Str::random(6);
            }
            
            if (!$locationName) {
                $locationName = 'ส่วนกลาง';
            }
            
            $location = Location::firstOrCreate(
                ['location_name' => $locationName],
                ['description' => 'Imported via Excel']
            );
            
            $isActive = !in_array(strtolower($status), ['ไม่ใช้งาน', 'inactive', '0', 'no', 'false']);
            
            $machineData = [
                'code'        => $code,
                'name'        => $name,
                'location_id' => $location->id,
                'description' => $description,
                'is_active'   => $isActive,
            ];
            
            if ($id) {
                $machine = Machine::find($id);
                if ($machine) {
                    $machine->update($machineData);
                    continue;
                }
            }
            
            $machine = Machine::where('code', $code)->first();
            if ($machine) {
                $machine->update($machineData);
                continue;
            }
            
            Machine::create($machineData);
        }

        return redirect()->route('machines.index')->with('success', 'นำเข้าข้อมูลเครื่องจักรเรียบร้อยแล้ว (Powered by Python)');
    }

    public function showBulkMapping()
    {
        $machines = Machine::with('location')->orderBy('location_id')->get();
        $locations = Location::all();
        
        $allCheckpoints = \App\Models\Checkpoint::where('is_active', true)
                            ->with('category')
                            ->get()
                            ->groupBy('category.name');

        return view('machines.bulk-map', compact('machines', 'locations', 'allCheckpoints'));
    }

    public function saveBulkMapping(Request $request)
    {
        $request->validate([
            'machine_ids' => 'required|array|min:1',
            'machine_ids.*' => 'exists:machines,id',
            'checkpoint_ids' => 'required|array|min:1',
            'checkpoint_ids.*' => 'exists:checkpoints,id',
            'mode' => 'required|in:add,replace',
        ]);

        $machineIds = $request->machine_ids;
        $checkpointIds = $request->checkpoint_ids;
        $mode = $request->mode;

        $count = 0;
        foreach ($machineIds as $machineId) {
            $machine = Machine::find($machineId);
            if ($machine) {
                if ($mode === 'replace') {
                    // Replace: sync (removes old, adds new)
                    $machine->checkpoints()->sync($checkpointIds);
                } else {
                    // Add: syncWithoutDetaching (keeps existing, adds new)
                    $machine->checkpoints()->syncWithoutDetaching($checkpointIds);
                }
                $count++;
            }
        }

        $modeText = $mode === 'replace' ? 'แทนที่' : 'เพิ่ม';
        return redirect()->route('machines.index')
            ->with('success', "{$modeText}จุดตรวจ " . count($checkpointIds) . " รายการ ให้เครื่องจักร {$count} รายการ เรียบร้อยแล้ว");
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'machine_ids' => 'required|array|min:1',
            'machine_ids.*' => 'exists:machines,id',
        ]);

        $count = Machine::whereIn('id', $request->machine_ids)->delete();

        return redirect()->route('machines.index')
            ->with('success', "ลบข้อมูลเครื่องจักร {$count} รายการ เรียบร้อยแล้ว");
    }
}
