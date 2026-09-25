<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\RandomAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The random audit schedule, and what became of each entry.
 *
 * Until now the schedule existed only as rows nobody could see: the dashboard
 * showed the audits due today and nothing showed the ones that had been, so
 * there was no way to answer "was the sampling plan actually followed?" - the
 * question an external audit asks.
 */
class RandomAuditController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        // Weeks are ISO weeks, the same unit audit:generate draws in.
        $week = (int) ($request->query('week') ?: now()->isoWeek());
        $year = (int) ($request->query('year') ?: now()->year);

        $query = RandomAudit::with(['department', 'auditor', 'session'])
            ->where('week_number', $week)
            ->where('year', $year);

        // An isolated user sees their own department's audits and those of any
        // sub-department under it - ห้องแคะ's audit is Production's business.
        if (! $user->isAdmin() && ! $user->hasGlobalVisibility()) {
            $visible = Department::where('id', $user->department_id)
                ->orWhere('parent_department_id', $user->department_id)
                ->pluck('id');

            $query->whereIn('department_id', $visible);
        }

        $audits = $query->orderBy('audit_date')->get();

        // Retiring an overdue audit is the scheduled command's job, not this
        // page's - a read must not write. What the page can do is say so, so a
        // row reading 'pending' the day after is not mistaken for outstanding.
        $audits->each(function (RandomAudit $audit) {
            $audit->setAttribute('is_overdue', $audit->isMissed());
        });

        return view('audits.index', [
            'audits' => $audits,
            'week' => $week,
            'year' => $year,
            'counts' => $audits->countBy('status'),
            'previousWeek' => now()->setISODate($year, $week)->subWeek(),
            'nextWeek' => now()->setISODate($year, $week)->addWeek(),
        ]);
    }
}
