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
    public function index()
    {
        $employees = Employee::with('department')->orderBy('department_id')->get();
        return view('employees.index', compact('employees'));
    }

    public function create()
    {
        $departments = Department::all();
        $shifts = \App\Models\Shift::all();
        $locations = \App\Models\Location::all();
        return view('employees.create', compact('departments', 'shifts', 'locations'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'prefix' => 'required',
            'fname' => 'required',
            'lname' => 'required',
            'employee_id' => 'required|unique:employees,employee_id',
            'department_id' => 'required|exists:departments,id',
            'shift_id' => 'nullable|exists:shifts,id',
            'location_id' => 'nullable|exists:locations,id',
            'level' => 'nullable|string|max:50',
            'profile_image' => 'nullable|image|max:2048',
        ]);

        $data = $request->all();
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
        $departments = Department::all();
        $shifts = \App\Models\Shift::all();
        $locations = \App\Models\Location::all();
        return view('employees.edit', compact('employee', 'departments', 'shifts', 'locations'));
    }

    public function update(Request $request, Employee $employee)
    {
        $request->validate([
            'prefix' => 'required',
            'fname' => 'required',
            'lname' => 'required',
            'employee_id' => 'required|unique:employees,employee_id,' . $employee->id,
            'department_id' => 'required|exists:departments,id',
            'shift_id' => 'nullable|exists:shifts,id',
            'location_id' => 'nullable|exists:locations,id',
            'level' => 'nullable|string|max:50',
            'profile_image' => 'nullable|image|max:2048',
        ]);

        $data = $request->all();
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
        $employee->delete(); // Soft delete
        return redirect()->route('employees.index')->with('success', 'Employee deleted successfully.');
    }

    public function export()
    {
        return Excel::download(new EmployeesExport, 'employees.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        Excel::import(new EmployeesImport, $request->file('file'));

        return redirect()->route('employees.index')->with('success', 'Employees imported successfully.');
    }
}
