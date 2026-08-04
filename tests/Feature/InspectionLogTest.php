<?php

use App\Models\InspectionLog;
use App\Models\CorrectiveAction;

test('isResolved returns false when result is pass', function () {
    $log = new InspectionLog();
    $log->result = 'pass';

    expect($log->isResolved())->toBeFalse();
});

test('isResolved returns false when result is fail and no corrective action exists', function () {
    $log = new InspectionLog();
    $log->result = 'fail';

    expect($log->isResolved())->toBeFalse();
});

test('isResolved returns false when corrective action status is in_progress', function () {
    $log = new InspectionLog();
    $log->result = 'fail';
    
    $ca = new CorrectiveAction();
    $ca->status = 'in_progress';
    
    // Simulate the relationship
    $log->setRelation('correctiveAction', $ca);

    expect($log->isResolved())->toBeFalse();
});

test('isResolved returns true when corrective action status is resolved, closed, or verified', function ($status) {
    $log = new InspectionLog();
    $log->result = 'fail';
    
    $ca = new CorrectiveAction();
    $ca->status = $status;
    
    // Simulate the relationship
    $log->setRelation('correctiveAction', $ca);

    expect($log->isResolved())->toBeTrue();
})->with(['resolved', 'closed', 'verified']);
