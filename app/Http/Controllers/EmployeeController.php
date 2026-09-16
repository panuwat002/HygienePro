<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\EmployeesExport;
use App\Imports\EmployeesImport;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = Employee::with(['department', 'shift', 'location']);

        // --- SCOPE DEFINITION ---
        $scopeDeptId = null;
        if ($user->isRestrictedToOwnDepartment()) {
            $scopeDeptId = $user->scopedDepartmentId();
            // Force filter by user's department
            $query->where('department_id', $scopeDeptId);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('fname', 'like', "%{$search}%")
                  ->orWhere('lname', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%")
                  ->orWhereRaw("CONCAT(prefix, ' ', fname, ' ', lname) LIKE ?", ["%{$search}%"]);
            });
        }

        if ($request->filled('department_id')) {
            // User requested specific department, check if allowed
            if ($scopeDeptId && $request->department_id != $scopeDeptId) {
                // If scoped user tries to see other dept, ignore it or force back (already forced above)
            } else {
                $query->where('department_id', $request->department_id);
            }
        }

        if ($request->filled('shift_id')) {
            $query->where('shift_id', $request->shift_id);
        }

        $employees = $query->orderBy('department_id')->paginate(20);
        
        // Fetch today's schedule (Roster) for these paginated employees
        $todaySchedules = \App\Models\EmployeeSchedule::with('shift')
            ->where('date', now()->startOfDay())
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get()
            ->keyBy('employee_id');

        $departments = Department::all(); // View might need all for filtering if global
        // Optimizing departments list for Isolated users? Maybe just show theirs.
        if ($scopeDeptId) {
            $departments = Department::where('id', $scopeDeptId)->get();
        }

        $shifts = \App\Models\Shift::all();
        $locations = \App\Models\Location::all();

        return view('employees.index', compact('employees', 'departments', 'shifts', 'locations', 'todaySchedules', 'scopeDeptId'));
    }

    public function create()
    {
        $user = auth()->user();
        
        $departments = Department::all();
        if ($user->isRestrictedToOwnDepartment()) {
            $departments = Department::where('id', $user->scopedDepartmentId())->get();
        }

        $shifts = \App\Models\Shift::all();
        $locations = \App\Models\Location::all();
        return view('employees.create', compact('departments', 'shifts', 'locations'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        
        $request->validate([
            'prefix' => 'required',
            'fname' => 'required',
            'lname' => 'required',
            'employee_id' => 'required|unique:employees,employee_id',
            'department_id' => 'required|exists:departments,id',
            'shift_id' => 'nullable|exists:shifts,id',
            'location_id' => 'nullable|exists:locations,id',
            'level' => 'nullable|string|max:50',
            'profile_image' => 'nullable|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $data = $request->all();

        // Enforce Scope
        if ($user->isRestrictedToOwnDepartment()) {
            if ($data['department_id'] != $user->scopedDepartmentId()) {
                return back()->with('error', 'Unauthorized to add employee to this department.');
            }
        }

        $data['fullname'] = $data['prefix'] . ' ' . $data['fname'] . ' ' . $data['lname'];
        
        // Generate QR Hash automatically if not provided (though usually auto-generated)
        $data['qr_code_hash'] = Str::uuid();

        if ($request->hasFile('profile_image')) {
            $file = $request->file('profile_image');
            $filename = 'profile_' . time() . '.webp';
            $path = 'profiles/' . $filename;

            try {
                $image = Image::read($file);
                $image->scale(width: 400); // Resize for profile
                $encoded = $image->toWebp(quality: 80);
                Storage::disk('public')->put($path, $encoded);
                $data['profile_image'] = '/storage/' . $path;
            } catch (\Exception $e) {
                // Fallback
                $path = $file->store('profiles', 'public');
                $data['profile_image'] = '/storage/' . $path;
            }
        }

        Employee::create($data);

        return redirect()->route('employees.index')->with('success', 'Employee created successfully.');
    }

    public function edit(Employee $employee)
    {
        $user = auth()->user();
        
        // Check Scope
        if ($user->isRestrictedToOwnDepartment()) {
            if ($employee->department_id != $user->scopedDepartmentId()) {
                abort(403, 'Unauthorized access to this employee.');
            }
            $departments = Department::where('id', $user->scopedDepartmentId())->get();
        } else {
            $departments = Department::all();
        }

        $shifts = \App\Models\Shift::all();
        $locations = \App\Models\Location::all();
        return view('employees.edit', compact('employee', 'departments', 'shifts', 'locations'));
    }

    public function update(Request $request, Employee $employee)
    {
        $user = auth()->user();

        // Check Scope (Before Update)
        if ($user->isRestrictedToOwnDepartment()) {
             // Cannot access employee from another department
             if ($employee->department_id != $user->scopedDepartmentId()) {
                abort(403, 'Unauthorized update to this employee.');
             }
        }

        $request->validate([
            'prefix' => 'required',
            'fname' => 'required',
            'lname' => 'required',
            'employee_id' => 'required|unique:employees,employee_id,' . $employee->id,
            'department_id' => 'required|exists:departments,id',
            'shift_id' => 'nullable|exists:shifts,id',
            'location_id' => 'nullable|exists:locations,id',
            'level' => 'nullable|string|max:50',
            'profile_image' => 'nullable|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $data = $request->all();
        
        // Prevent moving employee to another department if user is isolated
        if ($user->isRestrictedToOwnDepartment()) {
             if ($data['department_id'] != $user->scopedDepartmentId()) {
                 return back()->with('error', 'Unauthorized to move employee to this department.');
             }
        }

        $data['fullname'] = $data['prefix'] . ' ' . $data['fname'] . ' ' . $data['lname'];

        if ($request->hasFile('profile_image')) {
            // Delete old image if exists
            if ($employee->profile_image) {
                $oldPath = str_replace('/storage/', '', $employee->profile_image);
                Storage::disk('public')->delete($oldPath);
            }

            $file = $request->file('profile_image');
            $filename = 'profile_' . time() . '.webp';
            $path = 'profiles/' . $filename;

            try {
                $image = Image::read($file);
                $image->scale(width: 400);
                $encoded = $image->toWebp(quality: 80);
                Storage::disk('public')->put($path, $encoded);
                $data['profile_image'] = '/storage/' . $path;
            } catch (\Exception $e) {
                $path = $file->store('profiles', 'public');
                $data['profile_image'] = '/storage/' . $path;
            }
        }

        $employee->update($data);

        return redirect()->route('employees.index')->with('success', 'Employee updated successfully.');
    }

    public function destroy(Employee $employee)
    {
        $user = auth()->user();
        if ($user->isRestrictedToOwnDepartment()) {
             if ($employee->department_id != $user->scopedDepartmentId()) {
                abort(403, 'Unauthorized to delete this employee.');
             }
        }

        $employee->delete();
        return redirect()->route('employees.index')->with('success', 'ลบข้อมูลพนักงานเรียบร้อยแล้ว');
    }

    public function destroyBulk(Request $request)
    {
        $request->validate([
            'employee_ids' => 'required|array',
            'employee_ids.*' => 'exists:employees,id',
        ]);

        $user = auth()->user();
        $query = Employee::whereIn('id', $request->employee_ids);

        if ($user->isRestrictedToOwnDepartment()) {
             $query->where('department_id', $user->scopedDepartmentId());
        }

        $query->delete();

        return redirect()->route('employees.index')->with('success', 'ลบข้อมูลพนักงานที่เลือกเรียบร้อยแล้ว');
    }

    public function export()
    {
        $user = auth()->user();
        return Excel::download(new EmployeesExport($user), 'employees.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        try {
            $user = auth()->user();
            Excel::import(new EmployeesImport($user), $request->file('file'));
            return redirect()->route('employees.index')->with('success', 'Employees imported successfully.');
        } catch (\Exception $e) {
            $msg = 'เกิดข้อผิดพลาดในการนำเข้าพนักงาน: ' . $e->getMessage();
            if (str_contains($e->getMessage(), 'Duplicate entry') || str_contains($e->getMessage(), 'default value')) {
                $msg .= ' (กรุณาตรวจสอบว่าคุณนำเข้า "ไฟล์พนักงานหลัก" ถูกต้องหรือไม่ หากเป็น "ไฟล์ตารางกะ" ให้ใช้ปุ่ม "นำเข้าตารางกะ" แทนครับ)';
            }
            return redirect()->route('employees.index')->with('error', $msg);
        }
    }

    public function importSchedules(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        try {
            $user = auth()->user();
            $import = new \App\Imports\SchedulesImport($user);
            Excel::import($import, $request->file('file'));
            
            $msg = "นำเข้าตารางกะสำเร็จ {$import->importedCount} รายการ";
            
            if ($import->skippedCount > 0) {
                return redirect()->route('employees.index')->with('warning', $msg . " แต่ข้ามไป {$import->skippedCount} รายการ เนื่องจากไม่พบรหัสพนักงานในระบบ (อาจถูกลบไปแล้ว)");
            }

            return redirect()->route('employees.index')->with('success', $msg);
        } catch (\Exception $e) {
            return redirect()->route('employees.index')->with('error', 'เกิดข้อผิดพลาดในการนำเข้า: ' . $e->getMessage());
        }
    }

    public function downloadSchedulesTemplate()
    {
        return Excel::download(new \App\Exports\SchedulesTemplateExport, 'schedules-template.xlsx');
    }

    public function printCard($id)
    {
        $user = auth()->user();
        $employee = Employee::with('department')->findOrFail($id);

        if ($user->isRestrictedToOwnDepartment()) {
            if ($employee->department_id != $user->scopedDepartmentId()) {
                abort(403, 'Unauthorized to view this employee card.');
            }
        }

        return view('employees.card', compact('employee'));
    }

    public function printSelected(Request $request)
    {
        $ids = explode(',', $request->query('selected_ids', ''));
        if (empty($ids)) {
            return redirect()->back()->with('error', 'No employees selected.');
        }

        $user = auth()->user();
        $query = Employee::with('department')->whereIn('id', $ids);

        if ($user->isRestrictedToOwnDepartment()) {
            $query->where('department_id', $user->scopedDepartmentId());
        }

        $employees = $query->get();
        return view('employees.cards_bulk', compact('employees'));
    }

    public function showBulkLocation()
    {
        $user = auth()->user();
        $query = Employee::with(['department', 'location'])->orderBy('department_id');
        
        if ($user->isRestrictedToOwnDepartment()) {
            $query->where('department_id', $user->scopedDepartmentId());
        }

        $employees = $query->get();
        $departments = Department::all();
        $locations = \App\Models\Location::withCount('employees')->orderBy('location_name')->get();

        return view('employees.bulk-location', compact('employees', 'departments', 'locations'));
    }

    public function saveBulkLocation(Request $request)
    {
        $request->validate([
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'exists:employees,id',
            'location_id' => 'required|exists:locations,id',
        ]);

        $user = auth()->user();
        $query = Employee::whereIn('id', $request->employee_ids);

        if ($user->isRestrictedToOwnDepartment()) {
            $query->where('department_id', $user->scopedDepartmentId());
        }

        $employees = $query->get();
        $count = 0;
        foreach ($employees as $employee) {
            $employee->location_id = $request->location_id;
            if ($employee->save()) {
                $count++;
            }
        }

        $location = \App\Models\Location::find($request->location_id);
        
        return redirect()->route('employees.index')
            ->with('success', "ย้ายพนักงาน {$count} คน ไปยัง {$location->location_name} เรียบร้อยแล้ว");
    }

    public function showBulkPerson(Request $request)
    {
        $user = auth()->user();
        $query = Employee::with(['department', 'location'])->orderBy('department_id');
        
        if ($user->isRestrictedToOwnDepartment()) {
            $query->where('department_id', $user->scopedDepartmentId());
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('fname', 'like', "%{$search}%")
                  ->orWhere('lname', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        $employees = $query->paginate(20);

        if ($request->ajax()) {
            return view('employees.partials.bulk-person-list', compact('employees'))->render();
        }

        $departments = Department::all();
        $personCheckpoints = \App\Models\Checkpoint::where('type', 'person')
            ->where('is_active', true)
            ->orderBy('category_id')
            ->get()
            ->groupBy(fn($cp) => $cp->category?->name ?? 'ไม่มีหมวดหมู่');

        $categories = \App\Models\CheckpointCategory::all();

        return view('employees.bulk-person', compact('employees', 'departments', 'personCheckpoints', 'categories'));
    }

    public function saveBulkPerson(Request $request)
    {
        $request->validate([
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'exists:employees,id',
            'checkpoint_ids' => 'required|array|min:1',
            'checkpoint_ids.*' => 'exists:checkpoints,id',
            'mode' => 'required|in:add,replace',
        ]);

        $user = auth()->user();
        $query = Employee::whereIn('id', $request->employee_ids);

        if ($user->isRestrictedToOwnDepartment()) {
            $query->where('department_id', $user->scopedDepartmentId());
        }

        $employees = $query->get();
        $checkpointIds = $request->checkpoint_ids;
        $count = $employees->count();

        foreach ($employees as $employee) {
            if ($request->mode === 'replace') {
                $employee->checkpoints()->sync($checkpointIds);
            } else {
                $employee->checkpoints()->syncWithoutDetaching($checkpointIds);
            }
        }

        return redirect()->route('employees.index')
            ->with('success', "กำหนดจุดตรวจสุขลักษณะ " . count($checkpointIds) . " รายการ ให้กับพนักงาน {$count} คน เรียบร้อยแล้ว");
    }

    public function showBulkShift()
    {
        $user = auth()->user();
        $query = Employee::with(['department', 'shift'])->orderBy('department_id');
        
        if ($user->isRestrictedToOwnDepartment()) {
            $query->where('department_id', $user->scopedDepartmentId());
        }

        $employees = $query->get();
        $departments = Department::all();
        $shifts = \App\Models\Shift::withCount('employees')->orderBy('shift_name')->get();

        return view('employees.bulk-shift', compact('employees', 'departments', 'shifts'));
    }

    public function saveBulkShift(Request $request)
    {
        $request->validate([
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'exists:employees,id',
            'shift_id' => 'required|exists:shifts,id',
        ]);

        $user = auth()->user();
        $query = Employee::whereIn('id', $request->employee_ids);

        if ($user->isRestrictedToOwnDepartment()) {
            $query->where('department_id', $user->scopedDepartmentId());
        }

        $employees = $query->get();
        $count = 0;
        foreach ($employees as $employee) {
            $employee->shift_id = $request->shift_id;
            if ($employee->save()) {
                $count++;
            }
        }

        $shift = \App\Models\Shift::find($request->shift_id);
        
        return redirect()->route('employees.index')
            ->with('success', "กำหนดกะ {$shift->shift_name} ให้กับพนักงาน {$count} คน เรียบร้อยแล้ว");
    }

    public function saveBulkDepartment(Request $request)
    {
        $request->validate([
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'exists:employees,id',
            'department_id' => 'required|exists:departments,id',
        ]);

        $user = auth()->user();
        
        // Ensure isolated users can only move THEIR OWN employees
        // AND prevent them from moving employees to OTHER departments!
        if ($user->isRestrictedToOwnDepartment()) {
            if ($request->department_id != $user->scopedDepartmentId()) {
                abort(403, 'Unauthorized. Cannot move employees to a different department.');
            }
        }

        $query = Employee::whereIn('id', $request->employee_ids);
        
        if ($user->isRestrictedToOwnDepartment()) {
            $query->where('department_id', $user->scopedDepartmentId());
        }

        $employees = $query->get();
        $count = 0;
        foreach ($employees as $employee) {
            $employee->department_id = $request->department_id;
            if ($employee->save()) {
                $count++;
            }
        }

        return redirect()->route('employees.index')
            ->with('success', "ย้ายแผนกให้พนักงาน {$count} คน เรียบร้อยแล้ว");
    }
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'exists:employees,id',
        ]);

        $user = auth()->user();
        $query = Employee::whereIn('id', $request->employee_ids);

        if ($user->isRestrictedToOwnDepartment()) {
            $query->where('department_id', $user->scopedDepartmentId());
        }

        $count = $query->delete();

        return redirect()->route('employees.index')
            ->with('success', "ลบข้อมูลพนักงาน {$count} รายการ เรียบร้อยแล้ว");
    }
}
