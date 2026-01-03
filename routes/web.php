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

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Inspection Routes
    Route::middleware(['auth'])->group(function () {
        Route::get('/inspection', [InspectionController::class, 'dashboard'])->name('inspection.dashboard');
        Route::get('/inspection/stats/{department}', [InspectionController::class, 'getDepartmentStats'])->name('inspection.stats');
        Route::post('/inspection/start', [InspectionController::class, 'startSession'])->name('inspection.start');
        
        // Area Inspection Routes
        Route::get('/inspection/area/{department}/{location}', [App\Http\Controllers\AreaInspectionController::class, 'showChecklist'])->name('inspection.area.checklist');
        Route::post('/inspection/area/{session}/{location}', [App\Http\Controllers\AreaInspectionController::class, 'store'])->name('inspection.area.store');

        Route::get('/inspection/session/{session}/scan', [InspectionController::class, 'scan'])->name('inspection.scan');
        Route::get('/inspection/session/{session}/scan', [InspectionController::class, 'scan'])->name('inspection.scan');
        Route::get('/inspection/session/{session}/verify/{hash}', [InspectionController::class, 'showChecklist'])->name('inspection.checklist');
        Route::post('/inspection/session/{session}/store', [InspectionController::class, 'storeLog'])->name('inspection.log.store');

        Route::get('/verification', [InspectionController::class, 'verification'])->name('inspection.verification');
    Route::post('/verification/approve', [InspectionController::class, 'approve'])->name('inspection.approve');
        
        // Master Data Routes
        Route::get('employees/export', [EmployeeController::class, 'export'])->name('employees.export');
        Route::post('employees/import', [EmployeeController::class, 'import'])->name('employees.import');
        Route::resource('employees', EmployeeController::class);
        
        Route::resource('shifts', ShiftController::class);
        Route::resource('locations', LocationController::class);
        Route::get('locations/{location}/map', [LocationController::class, 'showMapping'])->name('locations.map');
        Route::post('locations/{location}/map', [LocationController::class, 'saveMapping'])->name('locations.map.save');
        Route::resource('machines', App\Http\Controllers\MachineController::class);
        Route::get('machines/{machine}/map', [App\Http\Controllers\MachineController::class, 'showMapping'])->name('machines.map');
        Route::post('machines/{machine}/map', [App\Http\Controllers\MachineController::class, 'saveMapping'])->name('machines.map.save');
        
        Route::resource('checkpoint-categories', CheckpointCategoryController::class);
        Route::post('checkpoints/quick-store', [CheckpointController::class, 'quickStore'])->name('checkpoints.quick-store');
        Route::resource('checkpoints', CheckpointController::class);
    });
});

require __DIR__.'/auth.php';
