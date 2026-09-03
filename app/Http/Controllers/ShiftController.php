<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $query = Shift::withCount('employees')->with('department');
        
        if ($user && !$user->isAdmin() && !$user->hasGlobalVisibility()) {
            $query->where(function($q) use ($user) {
                $q->where('department_id', $user->department_id)
                  ->orWhereNull('department_id');
            });
        }
        
        $shifts = $query->paginate(10);
        return view('shifts.index', compact('shifts'));
    }

    public function create()
    {
        return view('shifts.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'shift_name' => 'required|string|max:255',
            'start_time' => 'required',
            'end_time' => 'required',
        ]);

        $data = $request->all();
        $data['shift_type'] = $this->resolveShiftType($data);

        $user = auth()->user();
        if ($user && !$user->isAdmin() && !$user->hasGlobalVisibility()) {
            $data['department_id'] = $user->department_id;
        }

        Shift::create($data);

        return redirect()->route('shifts.index')->with('success', 'Shift created successfully.');
    }

    public function edit(Shift $shift)
    {
        $user = auth()->user();
        if ($user && !$user->isAdmin() && !$user->hasGlobalVisibility()) {
            if ($shift->department_id != $user->department_id) {
                abort(403, 'Unauthorized to edit this shift.');
            }
        }
        return view('shifts.edit', compact('shift'));
    }

    public function update(Request $request, Shift $shift)
    {
        $user = auth()->user();
        if ($user && !$user->isAdmin() && !$user->hasGlobalVisibility()) {
            if ($shift->department_id != $user->department_id) {
                abort(403, 'Unauthorized to update this shift.');
            }
        }

        $request->validate([
            'shift_name' => 'required|string|max:255',
            'start_time' => 'required',
            'end_time' => 'required',
        ]);

        $data = $request->only(['shift_name', 'start_time', 'end_time', 'shift_type', 'is_dayoff']);
        $data['shift_type'] = $this->resolveShiftType($data);

        $shift->update($data);

        return redirect()->route('shifts.index')->with('success', 'Shift updated successfully.');
    }

    public function destroy(Shift $shift)
    {
        $user = auth()->user();
        if ($user && !$user->isAdmin() && !$user->hasGlobalVisibility()) {
            if ($shift->department_id != $user->department_id) {
                abort(403, 'Unauthorized to delete this shift.');
            }
        }

        // Inspection sessions store the picked card as "custom_<id>" — a plain string with no
        // foreign key. Deleting a shift still in use leaves those rounds pointing at nothing,
        // and employees' shift_id is nulled out by the cascade. Refuse instead.
        $employeeCount = \App\Models\Employee::where('shift_id', $shift->id)->count();
        $scheduleCount = \App\Models\EmployeeSchedule::where('shift_id', $shift->id)->count();

        if ($employeeCount > 0 || $scheduleCount > 0) {
            return redirect()->route('shifts.index')->with(
                'error',
                "ลบกะนี้ไม่ได้ เพราะยังมีพนักงาน {$employeeCount} คน และตารางกะ {$scheduleCount} รายการอ้างอิงอยู่ "
                . 'กรุณาย้ายพนักงานไปกะอื่นก่อน'
            );
        }

        $shift->delete();
        return redirect()->route('shifts.index')->with('success', 'Shift deleted successfully.');
    }

    /**
     * Keep shift_type populated whatever the form sends.
     *
     * An inspection session started without picking a shift card stores a generic key
     * ('morning'/'afternoon'/'night') that resolves only through shift_type — a blank one
     * leaves the inspector with an empty employee list.
     */
    private function resolveShiftType(array $data): string
    {
        $submitted = trim((string) ($data['shift_type'] ?? ''));

        if ($submitted !== '' && in_array($submitted, Shift::types(), true)) {
            return $submitted;
        }

        return Shift::deriveType(
            $data['shift_name'] ?? null,
            $data['start_time'] ?? null,
            !empty($data['is_dayoff'])
        );
    }
}
