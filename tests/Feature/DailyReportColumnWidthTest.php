<?php

/**
 * "Production (ห้องแคะ)" wrapped onto two lines in a 6.5% column while the
 * name column beside it sat at 22% holding names half that long. Both widths
 * were chosen when every department was called PD.
 *
 * Each column now asks for what its longest entry needs and no more, and what
 * neither uses goes to the checkpoint columns - the part somebody has to tick,
 * whose headers wrap onto two lines when they are squeezed.
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

it('gives a long department name the room it needs', function () {
    $widths = widthsFor([
        ['น.ส. ขอ สุ', 'Production (ห้องแคะ)'],
        ['น.ส. มา เท เท', 'Production (ห้องแคะ)'],
    ]);

    // Short names, long department - the department ends up the wider of the
    // two, which is the case the old fixed 22% / 6.5% got backwards.
    expect($widths['department'])->toBeGreaterThan($widths['name']);
});

it('gives long names the room instead when the department is short', function () {
    $widths = widthsFor([['นางสาว ประไพพรรณ ศรีสุวรรณชัย', 'QA']]);

    expect($widths['name'])->toBeGreaterThan($widths['department']);
});

/**
 * The reason for the rewrite. A report of short names used to hold the same
 * total as one of long names, so a third of the name column printed empty
 * while the checkpoint headers wrapped.
 */
it('hands the leftover to the checkpoints when the content is short', function () {
    $short = widthsFor([['ก ข', 'QA']]);
    $long = widthsFor([['นางสาว ประไพพรรณ ศรีสุวรรณชัย', 'Production (ห้องแคะ)']]);

    expect($short['name'] + $short['department'])
        ->toBeLessThan($long['name'] + $long['department']);
});

it('never starves either column, however short the content', function () {
    $widths = widthsFor([['ก', 'QA']]);

    expect($widths['name'])->toBeGreaterThanOrEqual(10.0)
        ->and($widths['department'])->toBeGreaterThanOrEqual(7.0);
});

it('never lets the two crowd out the checkpoints', function () {
    $widths = widthsFor([[
        str_repeat('ก', 200),
        str_repeat('X', 200),
    ]]);

    expect(round($widths['name'] + $widths['department'], 2))->toBeLessThanOrEqual(34.0);
});

/**
 * Thai vowels and tone marks sit above and below the consonant, so they print
 * no wider. Counting them made every Thai name ask for about a quarter more
 * room than it uses.
 */
it('does not charge Thai names for marks that take no width', function () {
    $withMarks = widthsFor([['เหนี่ยวงอาจ', 'QA']]);      // 11 code points, 9 spacing
    $withoutMarks = widthsFor([['เหนยวงอาจ', 'QA']]);      // 9 code points, 9 spacing

    expect($withMarks['name'])->toBe($withoutMarks['name']);
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

    expect($widths['name'])->toBeGreaterThanOrEqual(10.0)
        ->and($widths['department'])->toBeGreaterThanOrEqual(7.0);
});
