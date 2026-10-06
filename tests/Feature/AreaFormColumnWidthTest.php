<?php

use App\Http\Controllers\ReportController;

/**
 * The area table's name and department columns were fixed at 40% and 8%. Eight
 * per cent cannot hold "Production (ห้องแคะ)", so the department wrapped onto
 * two lines and every row on the sheet grew to match it - while the 40% beside
 * it sat half empty on a report of short machine names.
 *
 * Same sizing the personnel table already uses: each column asks for what its
 * longest entry needs, and what neither uses goes to the checkpoints.
 */
function areaWidths(array $rows): array
{
    $method = new ReflectionMethod(ReportController::class, 'areaColumnWidths');
    $method->setAccessible(true);

    return $method->invoke(app(ReportController::class), $rows);
}

function areaRow(string $name, string $department, string $type = 'machine'): array
{
    return [
        'type' => $type,
        'info' => (object) ($type === 'machine' ? ['name' => $name] : ['location_name' => $name]),
        'session' => null,
        'results' => [],
        'department_label' => $department,
    ];
}

it('gives a long department the room it needs', function () {
    $widths = areaWidths([
        areaRow('ถังกรอง 1 μm', 'Production (ห้องแคะ)'),
        areaRow('เขียงพลาสติก', 'Production (ห้องแคะ)'),
    ]);

    // Short machine names, long department: the department ends up the wider of
    // the two, which the fixed 40% / 8% got backwards.
    expect($widths['department'])->toBeGreaterThan($widths['name']);
});

it('gives the names the room instead when the department is short', function () {
    $widths = areaWidths([
        areaRow('กะละมังสแตนเลสใส่เนื้อมะพร้าว', 'QA'),
    ]);

    expect($widths['name'])->toBeGreaterThan($widths['department']);
});

/**
 * The reason for sizing rather than fixing: a report of short names should not
 * hold the same width as one of long names.
 */
it('hands the leftover to the checkpoints when the content is short', function () {
    $short = areaWidths([areaRow('ถัง', 'QA')]);
    $long = areaWidths([areaRow('กะละมังสแตนเลสใส่เนื้อมะพร้าว', 'Production (ห้องแคะ)')]);

    expect($short['name'] + $short['department'])
        ->toBeLessThan($long['name'] + $long['department']);
});

it('never starves either column', function () {
    $widths = areaWidths([areaRow('ถัง', 'QA')]);

    expect($widths['name'])->toBeGreaterThanOrEqual(10.0)
        ->and($widths['department'])->toBeGreaterThanOrEqual(7.0);
});

it('never lets the two crowd out the checkpoints', function () {
    $widths = areaWidths([areaRow(str_repeat('ก', 200), str_repeat('X', 200))]);

    expect(round($widths['name'] + $widths['department'], 2))->toBeLessThanOrEqual(46.0);
});

/**
 * A machine row prints indented and prefixed with "- ", so it needs measuring
 * two characters wider than it reads.
 */
it('allows for the dash a machine row is printed with', function () {
    $machine = areaWidths([areaRow('เครื่องผ่ามะพร้าว', 'QA', 'machine')]);
    $area = areaWidths([areaRow('เครื่องผ่ามะพร้าว', 'QA', 'area')]);

    expect($machine['name'])->toBeGreaterThan($area['name']);
});

it('does not charge Thai names for marks that take no width', function () {
    $withMarks = areaWidths([areaRow('เหนี่ยวงอาจ', 'QA')]);
    $withoutMarks = areaWidths([areaRow('เหนยวงอาจ', 'QA')]);

    expect($withMarks['name'])->toBe($withoutMarks['name']);
});

it('sizes to the longest row, not the first one', function () {
    $widths = areaWidths([
        areaRow('ถัง', 'QA'),
        areaRow('กะละมังสแตนเลสใส่เนื้อมะพร้าว', 'QA'),
    ]);

    expect($widths['name'])->toBeGreaterThan($widths['department']);
});

it('copes with an empty report', function () {
    $widths = areaWidths([]);

    expect($widths['name'])->toBeGreaterThanOrEqual(10.0)
        ->and($widths['department'])->toBeGreaterThanOrEqual(7.0);
});
