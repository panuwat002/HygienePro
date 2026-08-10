<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ApprovalFlow;
use App\Models\ApprovalFlowStep;
use App\Models\User;
use App\Models\Department;

class ApprovalFlowController extends Controller
{
    public function setup(Request $request)
    {
        // By default, just show the first available flow or create a dummy one for the UI.
        // In the future, this can list multiple flows and the user clicks one to edit.
        $flow = ApprovalFlow::first();
        if (!$flow) {
            $flow = ApprovalFlow::create([
                'name' => 'Default Approval Flow',
                'target_model' => 'App\Models\CorrectiveAction', // Default
                'is_active' => true
            ]);
        }

        $roles = ['staff', 'supervisor', 'manager', 'admin', 'executive'];
        $users = User::orderBy('name')->get();
        $departments = Department::orderBy('dept_name')->get();

        // Group users by role for the UI helper
        $usersByRole = [];
        foreach ($roles as $r) {
            $usersByRole[$r] = $users->filter(fn($u) => $u->role === $r)->values();
        }

        return view('admin.approvals.setup', compact('flow', 'roles', 'users', 'usersByRole', 'departments'));
    }

    public function storeStep(Request $request, ApprovalFlow $flow)
    {
        $request->validate([
            'step_order' => 'required|integer|min:1',
            'role' => 'nullable|string',
            'department_id' => 'nullable|exists:departments,id',
            'user_id' => 'nullable|exists:users,id',
        ]);

        if (!$request->role && !$request->user_id) {
            return back()->with('error', 'You must specify either a Role or a Specific User.');
        }

        ApprovalFlowStep::create([
            'approval_flow_id' => $flow->id,
            'step_order' => $request->step_order,
            'role' => $request->role,
            'department_id' => $request->department_id,
            'user_id' => $request->user_id,
        ]);

        return back()->with('success', 'Approval Step added successfully.');
    }

    public function destroyStep(ApprovalFlowStep $step)
    {
        $step->delete();
        return back()->with('success', 'Approval Step removed successfully.');
    }
}
