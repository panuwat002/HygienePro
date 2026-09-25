<?php

/**
 * "Production (ห้องแคะ)" wrapped onto two lines in a 6.5% column while the
 * name column sat at 22% holding names half that long. The two share a fixed
 * budget, so the fix is to split it by what the page actually contains rather
 * than by a number chosen when every department was called PD.
 *
 * The budget stays fixed on purpose: the checkpoint columns divide whatever is
 * left, and a form whose columns move about between departments is harder to
 * read side by side.
 */

use App\Http\Controllers\ReportController;

function widthsFor(array $rows): array
{
    $method = new ReflectionMethod(ReportController::class, 'nameAndDepartmentWidths');
    $method->setAccessible(true);

    $matrix = [];
    foreach ($rows as $i => [$name, $dept]) {
        $matrix[$i] = [
            'info' => (object) ['fullname' => $name, 'department' => (object) ['dept_name' => $dept]],
            'session' => (object) ['department' => (object) ['dept_name' => $dept]],
        ];
    }

    return $method->invoke(app(ReportController::class), $matrix);
}

it('always spends the same total, so the checkpoint columns do not move', function () {
    $wide = widthsFor([['น.ส. ทา ทิน ชา ลุย', 'Production (ห้องแคะ)']]);
    $narrow = widthsFor([['สมชาย ใจดี', 'PD']]);

    expect(round($wide['name'] + $wide['department'], 2))->toBe(28.5)
        ->and(round($narrow['name'] + $narrow['department'], 2))->toBe(28.5);
});

it('gives a long department name the room it needs', function () {
    $widths = widthsFor([
        ['น.ส. ขอ สุ', 'Production (ห้องแคะ)'],
        ['น.ส. มา เท เท', 'Production (ห้องแคะ)'],
    ]);

    // Short names, long department - the department must end up the wider of
    // the two, which is the case the old fixed 22% / 6.5% got backwards.
    expect($widths['department'])->toBeGreaterThan($widths['name']);
});

it('gives long names the room instead when the department is short', function () {
    $widths = widthsFor([
        ['นางสาว ประไพพรรณ ศรีสุวรรณชัย', 'QA'],
    ]);

    expect($widths['name'])->toBeGreaterThan($widths['department']);
});

it('never starves either column, however lopsided the content', function () {
    $widths = widthsFor([['ก', 'Department With An Extremely Long Name Indeed']]);

    expect($widths['name'])->toBeGreaterThanOrEqual(12.0)
        ->and($widths['department'])->toBeGreaterThanOrEqual(8.0);
});

it('sizes to the longest row, not the first one', function () {
    $widths = widthsFor([
        ['ก', 'QA'],
        ['นางสาว ประไพพรรณ ศรีสุวรรณชัย', 'QA'],
    ]);

    expect($widths['name'])->toBeGreaterThan($widths['department']);
});

it('copes with an empty report', function () {
    $widths = widthsFor([]);

    expect(round($widths['name'] + $widths['department'], 2))->toBe(28.5);
});
