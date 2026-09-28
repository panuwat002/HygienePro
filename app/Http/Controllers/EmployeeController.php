<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $this->applyNameSearch($query, $request);

        // ทำงานอยู่ / ลาออก. Defaults to the people who still work here, so a
        // department that has turned over for years is not read as its roster.
        $status = $request->input('status', 'active');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
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

        $this->applyCheckpointCountFilter($query, $request);

        $checkpointCountOptions = $this->checkpointCountBreakdown($scopeDeptId, $request->department_id);

        $employees = $query->orderBy('department_id')->paginate(20)->withQueryString();

        // Narrowing the list from page 6 leaves ?page=6 pointing past the end of
        // a one-row result, and the page renders "ยังไม่มีข้อมูลพนักงาน" over a
        // row that is really there. Send them to the first page of what they
        // actually asked for.
        if ($employees->isEmpty() && $employees->currentPage() > 1) {
            return redirect()->route('employees.index', $request->except('page'));
        }

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

        return view('employees.index', compact('employees', 'departments', 'shifts', 'locations', 'todaySchedules', 'scopeDeptId', 'checkpointCountOptions'));
    }

    /**
     * Find people by anything printed on their row.
     *
     * Shared by the employee list and the bulk-checkpoint page, which had two
     * copies of this and had already drifted: neither looked at `fullname` -
     * the column the list prints as the person's name - and both looked at
     * `fname`/`lname`, which are nullable, were added in a later migration and
     * are empty for everyone imported from Excel. Typing the name on your own
     * row found nothing.
     */
    private function applyNameSearch($query, Request $request): void
    {
        if (! $request->filled('search')) {
            return;
        }

        // Trimmed: a code pasted out of Excel or read by a scanner arrives with
        // whitespace around it, and " 65990" matched nothing.
        $search = trim((string) $request->search);

        $query->where(function ($q) use ($search) {
            $q->where('fullname', 'like', "%{$search}%")
              ->orWhere('fname', 'like', "%{$search}%")
              ->orWhere('lname', 'like', "%{$search}%")
              ->orWhere('employee_id', 'like', "%{$search}%")
              // Typed with the prefix in front, as it is printed.
              ->orWhereRaw($this->joinedName(['prefix', 'fname', 'lname']) . ' LIKE ?', ["%{$search}%"])
              ->orWhereRaw($this->joinedName(['prefix', 'fullname']) . ' LIKE ?', ["%{$search}%"]);
        });
    }

    /**
     * Narrow to people with exactly N checkpoints, when N was actually chosen.
     *
     * filled() would drop a deliberate "0", and nobody-has-any-checkpoints is
     * exactly the case worth surfacing - hence the explicit test. But the test
     * has to be for null, not ''.
     *
     * The filter bar submits every field on every search, so an untouched
     * checkpoint filter arrives as `checkpoint_count=`, and
     * ConvertEmptyStringsToNull has turned that into null before any of this
     * runs. `$request->checkpoint_count !== ''` was therefore true for a blank
     * filter, and the query narrowed to (int) null === 0 - "people with no
     * checkpoints at all".
     *
     * That is the whole of "ค้นหาแล้วไม่เจอ": searching anyone who HAS
     * checkpoints returned nothing, while browsing the same list found them,
     * because browsing sends no checkpoint_count key for has() to see.
     */
    private function applyCheckpointCountFilter($query, Request $request): void
    {
        $count = $request->input('checkpoint_count');

        if ($count !== null && $count !== '') {
            $query->withCheckpointCount((int) $count);
        }
    }

    /**
     * Columns joined with spaces, in this connection's dialect.
     *
     * CONCAT() is MySQL's; SQLite has no such function and answers with
     * "no such function: CONCAT", so the search this builds threw a 500 for
     * every query under SQLite. COALESCE matters on both: prefix, fname and
     * lname are all nullable, and MySQL's CONCAT returns NULL - never a match -
     * if any argument is NULL, so a person with no prefix could not be found
     * by their full name even where the function does exist.
     */
    private function joinedName(array $columns): string
    {
        $parts = array_map(fn ($c) => "COALESCE({$c}, '')", $columns);

        return DB::connection()->getDriverName() === 'sqlite'
            ? '(' . implode(" || ' ' || ", $parts) . ')'
            : 'CONCAT(' . implode(", ' ', ", $parts) . ')';
    }

    /**
     * How many people sit on each checkpoint count, so the filter can offer
     * only the numbers that exist and say how big each group is.
     *
     * This is what makes "111 people, 23 of them on nine checkpoints" legible
     * without paging through six screens of badges.
     *
     * @return \Illuminate\Support\Collection<int, int> count => headcount
     */
    private function checkpointCountBreakdown(?int $scopeDeptId, $requestedDeptId): \Illuminate\Support\Collection
    {
        return Employee::query()
            ->when($scopeDeptId, fn ($q) => $q->where('department_id', $scopeDeptId))
            ->when(! $scopeDeptId && $requestedDeptId, fn ($q) => $q->where('department_id', $requestedDeptId))
            ->withCount('checkpoints')
            ->get()
            ->countBy('checkpoints_count')
            ->sortKeys();
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

        // Somebody who has ever been inspected is part of the record. The
        // foreign key on inspection_logs.employee_id is RESTRICT, so this used
        // to come back as a raw 500 with no idea what to do instead.
        if ($employee->inspectionLogs()->exists()) {
            return back()->with('error',
                "{$employee->fullname} เคยถูกตรวจแล้ว จึงลบไม่ได้เพราะจะทำให้ประวัติการตรวจหายไปจากหลักฐาน "
                . 'ถ้าลาออกแล้วให้ใช้ "ทำเครื่องหมายว่าลาออก" แทน จะหายจากตารางกะและรอบตรวจทันที');
        }

        $employee->delete();
        return redirect()->route('employees.index')->with('success', 'ลบข้อมูลพนักงานเรียบร้อยแล้ว');
    }

    /**
     * Retire somebody who has left, or bring them back.
     *
     * is_active was already the switch the rest of the app reads - the weekly
     * roster, the inspection round's target list and the area dashboard all
     * filter on it - but nothing in any screen could set it, so the only way
     * to take a leaver off the roster was to delete them, and deleting anyone
     * who had ever been inspected failed on a foreign key.
     */
    public function setActive(Employee $employee, Request $request)
    {
        $user = auth()->user();
        if ($user->isRestrictedToOwnDepartment() && $employee->department_id != $user->scopedDepartmentId()) {
            abort(403, 'Unauthorized to change this employee.');
        }

        $request->validate(['is_active' => 'required|boolean']);

        $active = $request->boolean('is_active');
        $employee->update(['is_active' => $active]);

        return back()->with('success', $active
            ? "นำ {$employee->fullname} กลับเข้าตารางกะและรอบตรวจแล้ว"
            : "บันทึก {$employee->fullname} เป็นลาออกแล้ว — หายจากตารางกะและรอบตรวจ แต่ประวัติการตรวจยังอยู่ครบ");
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

        // Same rule as destroy(), applied per person rather than to the batch:
        // one inspected employee in the selection must not block the rest, and
        // must not be deleted either.
        $inspected = (clone $query)->has('inspectionLogs')->get();
        $deletable = (clone $query)->doesntHave('inspectionLogs');

        $deleted = $deletable->count();
        $deletable->delete();

        if ($inspected->isNotEmpty()) {
            return redirect()->route('employees.index')->with('warning',
                "ลบแล้ว {$deleted} คน — ข้าม {$inspected->count()} คนที่เคยถูกตรวจ ({$inspected->pluck('fullname')->take(3)->join(', ')}"
                . ($inspected->count() > 3 ? ' …' : '') . ') '
                . 'เพราะจะทำให้ประวัติการตรวจหายไป ถ้าลาออกแล้วให้ใช้ "ทำเครื่องหมายว่าลาออก" แทน');
        }

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

        $this->applyNameSearch($query, $request);

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        $this->applyCheckpointCountFilter($query, $request);

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

        $checkpointCountOptions = $this->checkpointCountBreakdown(
            $user->isRestrictedToOwnDepartment() ? $user->scopedDepartmentId() : null,
            $request->department_id
        );

        return view('employees.bulk-person', compact('employees', 'departments', 'personCheckpoints', 'categories', 'checkpointCountOptions'));
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
