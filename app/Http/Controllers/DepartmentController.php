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
        return view('departments.edit', compact('department'));
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
        ]);

        $department->update($request->all());

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
