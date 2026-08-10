<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\DepartmentsExport;
use App\Imports\DepartmentsImport;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $departments = Department::orderBy('id', 'asc')->paginate(10);
        return view('departments.index', compact('departments'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('departments.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'dept_name' => 'required|string|max:255|unique:departments,dept_name',
            'dept_code' => 'required|string|max:10|unique:departments,dept_code',
            'visibility_type' => 'required|in:global,isolated',
        ]);

        Department::create($request->all());

        return redirect()->route('departments.index')->with('success', 'Department created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Department $department)
    {
        $users = \App\Models\User::orderBy('name')->get();
        return view('departments.edit', compact('department', 'users'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Department $department)
    {
        $request->validate([
            'dept_name' => 'required|string|max:255|unique:departments,dept_name,' . $department->id,
            'dept_code' => 'required|string|max:10|unique:departments,dept_code,' . $department->id,
            'visibility_type' => 'required|in:global,isolated',
            'manager_id' => 'nullable|exists:users,id',
        ]);

        $department->update($request->except('manager_id'));

        if ($request->has('manager_id')) {
            $newManagerId = $request->manager_id;
            
            if ($newManagerId) {
                // Demote current managers in this department
                \App\Models\User::where('department_id', $department->id)
                    ->where(function($q) {
                        $q->where('role', 'manager')->orWhere('level', '>=', 5);
                    })
                    ->where('id', '!=', $newManagerId)
                    ->update(['role' => 'staff', 'level' => 3]);
                    
                // Promote the new manager and move them to this department if needed
                \App\Models\User::where('id', $newManagerId)
                    ->update(['department_id' => $department->id, 'role' => 'manager', 'level' => 5]);
            } else {
                // If cleared, demote all managers in this department
                \App\Models\User::where('department_id', $department->id)
                    ->where(function($q) {
                        $q->where('role', 'manager')->orWhere('level', '>=', 5);
                    })
                    ->update(['role' => 'staff', 'level' => 3]);
            }
        }

        return redirect()->route('departments.index')->with('success', 'Department updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Department $department)
    {
        // Check if department has users or employees? (Including soft-deleted employees)
        if ($department->users()->count() > 0 || $department->employees()->withTrashed()->count() > 0) {
            return back()->with('error', 'Cannot delete department with associated users or employees.');
        }

        $department->delete();

        return redirect()->route('departments.index')->with('success', 'Department deleted successfully.');
    }

    public function export()
    {
        return Excel::download(new DepartmentsExport, 'departments.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        Excel::import(new DepartmentsImport, $request->file('file'));

        return redirect()->route('departments.index')->with('success', 'นำเข้าข้อมูลแผนกเรียบร้อยแล้ว');
    }
}
