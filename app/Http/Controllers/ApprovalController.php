<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ApprovalRequest;

class ApprovalController extends Controller
{
    public function pending()
    {
        $user = auth()->user();

        // Get requests where status is 'pending' and the current step is assigned to me (by user_id or role)
        $requests = ApprovalRequest::with(['flow.steps', 'approvable'])
            ->pendingForUser($user)
            ->latest()
            ->get();

        return view('approvals.pending', compact('requests'));
    }

    public function approve(Request $request, ApprovalRequest $approval)
    {
        // 1. Authorize via Policy
        \Illuminate\Support\Facades\Gate::authorize('approve', $approval);

        // 2. Determine if there is a next step
        $nextStep = $approval->flow->steps->where('step_order', '>', $approval->current_step_order)->sortBy('step_order')->first();

        if ($nextStep) {
            // Move to next step
            $approval->current_step_order = $nextStep->step_order;
            $approval->save();
            return back()->with('success', 'Request approved. Moved to next step.');
        } else {
            // Fully approved
            $approval->status = 'approved';
            $approval->save();

            // approvable is a morphTo with no FK behind it (the approval_tables
            // migration uses a plain morphs()), so the target row can be gone. When it
            // is, method_exists(null, ...) is false and the else branch wrote a property
            // on null — a fatal Error, not a handled failure.
            if (! $approval->approvable) {
                return back()->with('error', 'รายการที่เกี่ยวข้องถูกลบไปแล้ว (Linked record no longer exists).');
            }

            // Update Target Model Status
            if (method_exists($approval->approvable, 'markAsApproved')) {
                $approval->approvable->markAsApproved();
            } else {
                $approval->approvable->approval_status = 'approved';
                $approval->approvable->save();
            }

            return back()->with('success', 'Request fully approved.');
        }
    }

    public function reject(Request $request, ApprovalRequest $approval)
    {
        $request->validate([
            'reason' => 'nullable|string'
        ]);

        \Illuminate\Support\Facades\Gate::authorize('approve', $approval);

        if ($approval->status !== 'pending') {
            return back()->with('error', 'This request is no longer pending.');
        }

        $approval->status = 'rejected';
        $approval->rejection_reason = $request->reason;
        $approval->save();

        // Same dangling-morph guard as approve().
        if (! $approval->approvable) {
            return back()->with('error', 'รายการที่เกี่ยวข้องถูกลบไปแล้ว (Linked record no longer exists).');
        }

        // Update Target Model Status
        if (method_exists($approval->approvable, 'markAsRejected')) {
            $approval->approvable->markAsRejected($request->reason);
        } else {
            $approval->approvable->approval_status = 'rejected';
            $approval->approvable->save();
        }

        return back()->with('success', 'Request rejected.');
    }
}
