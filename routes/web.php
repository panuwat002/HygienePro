<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\InspectionController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\CheckpointCategoryController;
use App\Http\Controllers\CheckpointController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', [InspectionController::class, 'home'])->middleware(['auth', 'verified'])->name('dashboard');

use App\Http\Controllers\LineWebhookController;

// LINE Messaging API Webhook
Route::post('/webhook/line', [LineWebhookController::class, 'handle']);

Route::middleware('auth')->group(function () {
    // Debug Route. Sat in the bare `auth` group with no gate, so any staff account
    // could read the 10 most recent area logs from every department, walking around
    // the isolated-department scoping the rest of the app enforces.
    Route::get('/debug-logs', function() {
        $logs = \App\Models\InspectionLog::with('checkpoint')
            ->whereNotNull('location_id')
            ->whereNull('machine_id')
            ->whereNull('employee_id')
            ->orderBy('id', 'desc')
            ->take(10)
            ->get();
        return response()->json($logs);
    })->middleware('can:manage-system');

    // Notification Routes
    Route::post('/notifications/{id}/read', [App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('notifications.readAll');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::patch('/profile/signature', [ProfileController::class, 'updateSignature'])->name('profile.signature');
    Route::delete('/profile/signature', [ProfileController::class, 'destroySignature'])->name('profile.signature.destroy');

    // Settings
    Route::get('/settings', [\App\Http\Controllers\SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings', [\App\Http\Controllers\SettingsController::class, 'update'])->name('settings.update');
    // Pending Approvals (For all authenticated users that might be approvers)
    Route::get('/approvals/pending', [App\Http\Controllers\ApprovalController::class, 'pending'])->name('approvals.pending');
    Route::post('/approvals/{approval}/approve', [App\Http\Controllers\ApprovalController::class, 'approve'])->name('approvals.approve');
    Route::post('/approvals/{approval}/reject', [App\Http\Controllers\ApprovalController::class, 'reject'])->name('approvals.reject');

    // Inspection Routes
    Route::middleware(['auth'])->group(function () {
        Route::middleware(['can:inspect'])->group(function () {
            Route::get('/inspection/{type}', [InspectionController::class, 'dashboard'])->name('inspection.dashboard')->whereIn('type', ['personnel', 'machine']);
            Route::get('/inspection/summary/{type}/{department}', [InspectionController::class, 'getDepartmentStats'])->name('inspection.stats')->whereIn('type', ['personnel', 'machine']);
            Route::post('/inspection/start/{type}', [InspectionController::class, 'startSession'])->name('inspection.start')->whereIn('type', ['personnel', 'machine']);
            
            // Area Inspection Routes
            Route::get('/inspection/area/bulk/{department}', [App\Http\Controllers\AreaInspectionController::class, 'showBulkChecklist'])->name('inspection.area.bulk');
            Route::get('/inspection/area/{department}/{location}', [App\Http\Controllers\AreaInspectionController::class, 'showChecklist'])->name('inspection.area.checklist');
            Route::post('/inspection/area/{session}/{location}', [App\Http\Controllers\AreaInspectionController::class, 'store'])->name('inspection.area.store');
            Route::post('/inspection/area/{session}/{location}/bulk-no-production', [App\Http\Controllers\AreaInspectionController::class, 'storeBulkNoProduction'])->name('inspection.area.bulk-no-production');
            Route::post('/inspection/area/{session}/{location}/bulk-no-production-remaining', [App\Http\Controllers\AreaInspectionController::class, 'storeBulkNoProductionRemainingMachines'])->name('inspection.area.bulk-no-production-remaining');
            Route::post('/inspection/area/{session}/{location}/bulk-pass', [App\Http\Controllers\AreaInspectionController::class, 'storeBulkPass'])->name('inspection.area.bulk-pass');

            Route::get('/inspection/session/{session}/scan', [InspectionController::class, 'scan'])->name('inspection.scan');
            Route::post('/inspection/session/{session}/bulk-pass', [InspectionController::class, 'bulkPassPersonnel'])->name('inspection.bulk-pass');
            Route::get('/inspection/session/{session}/browse', [InspectionController::class, 'browseEmployees'])->name('inspection.browse');
            Route::post('/inspection/session/{session}/employee/{employee}/mark-absent', [InspectionController::class, 'markAbsent'])->name('inspection.mark-absent');
            Route::get('/inspection/session/{session}/verify/{hash}', [InspectionController::class, 'showChecklist'])->name('inspection.checklist');
            Route::post('/inspection/session/{session}/verify-with-photo/{hash}', [InspectionController::class, 'showChecklist'])->name('inspection.checklist.photo');
            Route::post('/inspection/session/{session}/store', [InspectionController::class, 'storeLog'])->name('inspection.log.store');
            Route::post('/inspection/session/{session}/finish', [InspectionController::class, 'finishSession'])->name('inspection.finish');
            Route::post('/inspection/session/{session}/pause', [InspectionController::class, 'pauseSession'])->name('inspection.pause');
        });

        Route::get('/verification', [InspectionController::class, 'verification'])
            ->middleware('can:access-verification')
            ->name('inspection.verification');
        // The detail modal fetches its own body, so the page does not have to
        // render one per card up front. Same gate as the page it opens from.
        Route::get('/verification/detail', [InspectionController::class, 'verificationDetail'])
            ->middleware('can:access-verification')
            ->name('inspection.verification.detail');
        Route::post('/verification/verify', [InspectionController::class, 'verify'])
            ->middleware('can:verify')
            ->name('inspection.verify');
        Route::post('/verification/approve', [InspectionController::class, 'managerApprove'])
            ->middleware('can:approve')
            ->name('inspection.approve');
        Route::post('/verification/reject', [InspectionController::class, 'reject'])
            ->middleware('can:verify')
            ->name('inspection.reject');
        Route::post('/verification/acknowledge', [InspectionController::class, 'acknowledge'])
            ->middleware('can:acknowledge')
            ->name('inspection.acknowledge');
        Route::post('/corrective-action/escalate', [App\Http\Controllers\CorrectiveActionController::class, 'escalate'])->name('corrective.escalate');
        Route::get('/corrective-actions', [App\Http\Controllers\CorrectiveActionController::class, 'index'])->name('corrective.index');
        Route::post('/corrective-action/resolve', [App\Http\Controllers\CorrectiveActionController::class, 'resolve'])->name('corrective.resolve');
        Route::post('/corrective-action/close', [App\Http\Controllers\CorrectiveActionController::class, 'close'])->name('corrective.close');
        Route::post('/corrective-action/assign', [App\Http\Controllers\CorrectiveActionController::class, 'assign'])->name('corrective.assign');
        
        // Reporting Routes - Manager/Admin Level
        Route::middleware(['can:view-reports'])->controller(App\Http\Controllers\ReportController::class)->group(function () {
            Route::get('/reports', 'index')->name('reports.index');
            Route::get('/reports/daily', 'daily')->name('reports.daily');
            Route::get('/reports/offenders', 'offenders')->name('reports.offenders');
            Route::get('/reports/money', 'moneyReport')->name('reports.money');
            Route::get('/reports/export/pdf', 'exportDailyPdf')->name('reports.export.pdf');
            Route::get('/reports/export/fm-qa-22', 'exportFmQa22')->name('reports.export.fm-qa-22');
            Route::get('/reports/export/monthly', 'exportMonthlyPdf')->name('reports.monthly.pdf');
            Route::get('/reports/export/money', 'exportMoneyPdf')->name('reports.money.pdf');
        });

        // ==========================================================
        // ADMIN & MASTER DATA MANAGEMENT
        // ==========================================================
        
        // Group 1: Admin Only (Manage System Structure)
        Route::middleware(['can:manage-system'])->group(function () {
            Route::resource('users', App\Http\Controllers\UserController::class);
            Route::resource('activity-logs', App\Http\Controllers\Admin\ActivityLogController::class)->only(['index']);

            // Approval Flow Admin Setup
            Route::get('/admin/approvals/setup', [App\Http\Controllers\Admin\ApprovalFlowController::class, 'setup'])->name('admin.approvals.setup');
            Route::post('/admin/approvals/{flow}/steps', [App\Http\Controllers\Admin\ApprovalFlowController::class, 'storeStep'])->name('admin.approvals.steps.store');
            Route::delete('/admin/approvals/steps/{step}', [App\Http\Controllers\Admin\ApprovalFlowController::class, 'destroyStep'])->name('admin.approvals.steps.destroy');
            
            // Email Settings
            Route::get('/admin/settings/email', [App\Http\Controllers\Admin\SystemSettingController::class, 'editEmail'])->name('admin.settings.email');
            Route::post('/admin/settings/email/update', [App\Http\Controllers\Admin\SystemSettingController::class, 'updateEmail'])->name('admin.settings.email.update');
            Route::post('/admin/settings/email/test', [App\Http\Controllers\Admin\SystemSettingController::class, 'testEmail'])->name('admin.settings.email.test');
        });

        // Group 1.5: Master Data Management
        Route::middleware(['can:manage-master-data'])->group(function () {
            Route::resource('departments', App\Http\Controllers\DepartmentController::class);
            Route::get('departments/{department}/roster', [\App\Http\Controllers\EmployeeScheduleController::class, 'index'])->name('departments.roster');
            Route::get('departments/{department}/roster/print', [\App\Http\Controllers\EmployeeScheduleController::class, 'print'])->name('departments.roster.print');
            Route::post('departments/{department}/roster', [\App\Http\Controllers\EmployeeScheduleController::class, 'store'])->name('departments.roster.store');
            Route::match(['get', 'post'], 'departments/{department}/roster/export', [\App\Http\Controllers\EmployeeScheduleController::class, 'export'])->name('departments.roster.export');
            Route::post('departments/{department}/roster/import', [\App\Http\Controllers\EmployeeScheduleController::class, 'import'])->name('departments.roster.import');
            Route::get('departments-export', [App\Http\Controllers\DepartmentController::class, 'export'])->name('departments.export');
            Route::post('departments-import', [App\Http\Controllers\DepartmentController::class, 'import'])->name('departments.import');
            Route::post('locations-bulk-delete', [LocationController::class, 'destroyBulk'])->name('locations.bulk-delete');
            Route::resource('locations', LocationController::class);
            Route::get('locations-export', [LocationController::class, 'export'])->name('locations.export');
            Route::post('locations-import', [LocationController::class, 'import'])->name('locations.import');
            Route::get('locations/{location}/map', [LocationController::class, 'showMapping'])->name('locations.map');
            Route::post('locations/{location}/map', [LocationController::class, 'saveMapping'])->name('locations.map.save');
            Route::get('locations-bulk-map', [LocationController::class, 'showBulkMapping'])->name('locations.bulk-map');
            Route::post('locations-bulk-map', [LocationController::class, 'saveBulkMapping'])->name('locations.bulk-map.save');
            Route::post('machines-bulk-delete', [App\Http\Controllers\MachineController::class, 'destroyBulk'])->name('machines.bulk-delete');
            Route::resource('machines', App\Http\Controllers\MachineController::class);
            Route::get('machines-export', [App\Http\Controllers\MachineController::class, 'export'])->name('machines.export');
            Route::post('machines-import', [App\Http\Controllers\MachineController::class, 'import'])->name('machines.import');
            Route::get('machines/{machine}/map', [App\Http\Controllers\MachineController::class, 'showMapping'])->name('machines.map');
            Route::post('machines/{machine}/map', [App\Http\Controllers\MachineController::class, 'saveMapping'])->name('machines.map.save');
            Route::get('machines-bulk-map', [App\Http\Controllers\MachineController::class, 'showBulkMapping'])->name('machines.bulk-map');
            Route::post('machines-bulk-map', [App\Http\Controllers\MachineController::class, 'saveBulkMapping'])->name('machines.bulk-map.save');
            Route::resource('checkpoint-categories', CheckpointCategoryController::class);
            Route::post('checkpoints/quick-store', [CheckpointController::class, 'quickStore'])->name('checkpoints.quick-store');
            Route::post('checkpoints/reorder', [CheckpointController::class, 'reorder'])->name('checkpoints.reorder');
            Route::resource('checkpoints', CheckpointController::class);
            Route::get('checkpoints-export', [CheckpointController::class, 'export'])->name('checkpoints.export');
            Route::post('checkpoints-import', [CheckpointController::class, 'import'])->name('checkpoints.import');
            Route::get('compliance', [App\Http\Controllers\InspectionScheduleController::class, 'compliance'])->name('compliance.dashboard');
            Route::resource('schedules', App\Http\Controllers\InspectionScheduleController::class);
        });

        // Group 2: Supervisors & Above (Manage Resources)
        Route::middleware(['can:manage-employees'])->group(function () {
            Route::get('employees/export', [EmployeeController::class, 'export'])->name('employees.export');
            Route::post('employees/import', [EmployeeController::class, 'import'])->name('employees.import');
            Route::post('employees/import-schedules', [EmployeeController::class, 'importSchedules'])->name('employees.import_schedules');
            Route::get('employees/download-schedules-template', [EmployeeController::class, 'downloadSchedulesTemplate'])->name('employees.download_schedules_template');
            Route::get('employees/print-selected', [EmployeeController::class, 'printSelected'])->name('employees.print_selected');
            Route::get('employees/{employee}/print-card', [EmployeeController::class, 'printCard'])->name('employees.print_card');
            Route::get('employees-bulk-location', [EmployeeController::class, 'showBulkLocation'])->name('employees.bulk-location');
            Route::post('employees-bulk-location', [EmployeeController::class, 'saveBulkLocation'])->name('employees.bulk-location.save');
            Route::get('employees-bulk-person', [EmployeeController::class, 'showBulkPerson'])->name('employees.bulk-person');
            Route::post('employees-bulk-person', [EmployeeController::class, 'saveBulkPerson'])->name('employees.bulk-person.save');

            // Bulk Shift Assignment
            Route::get('employees-bulk-shift', [EmployeeController::class, 'showBulkShift'])->name('employees.bulk-shift');
            Route::post('employees-bulk-shift', [EmployeeController::class, 'saveBulkShift'])->name('employees.bulk-shift.save');
            Route::post('employees-bulk-department', [EmployeeController::class, 'saveBulkDepartment'])->name('employees.bulk-department.save');
            Route::post('employees-bulk-delete', [EmployeeController::class, 'destroyBulk'])->name('employees.bulk-delete');
            Route::resource('employees', EmployeeController::class);
            Route::resource('shifts', ShiftController::class);
        });
    });

    // AI Mockup Test Route
    Route::get('/ai-test', function () {
        return view('ai_test');
    })->name('ai.test');
    
    Route::post('/ai-test/analyze', function (\Illuminate\Http\Request $request) {
        $request->validate(['image' => 'required|image']);
        $path = $request->file('image')->store('ai_test', 'public');
        
        $result = \App\Services\AIService::verifyImage(storage_path('app/public/' . $path));
        
        return back()->with('result', $result)->with('image_path', $path);
    })->name('ai.analyze');

});

require __DIR__.'/auth.php';
