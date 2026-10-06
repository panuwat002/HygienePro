<?php

use App\Http\Controllers\ReportController;

/**
 * The area form was broken into pages with array_chunk() every 18 rows, which
 * counts rows and knows nothing about what they are. A room whose header
 * landed on row 18 printed alone at the foot of the page with its machines
 * overleaf: a heading for a list that is not there, on a sheet somebody signs.
 */
function pagesOf(array $rows, int $perPage = 18): array
{
    $method = new ReflectionMethod(ReportController::class, 'chunkAreaRowsByRoom');
    $method->setAccessible(true);

    return $method->invoke(app(ReportController::class), $rows, $perPage);
}

function roomRows(string $name, int $machines): array
{
    $rows = [[
        'type' => 'area',
        'info' => (object) ['location_name' => $name],
        'session' => null, 'results' => [], 'department_label' => 'Production',
    ]];

    // Not range(1, $machines): range(1, 0) counts down and yields [1, 0].
    foreach ($machines > 0 ? range(1, $machines) : [] as $n) {
        $rows[] = [
            'type' => 'machine',
            'info' => (object) ['name' => $name . ' เครื่องที่ ' . $n],
            'session' => null, 'results' => [], 'department_label' => 'Production',
        ];
    }

    return $rows;
}

function firstOf(array $page): array
{
    return $page[0];
}

it('does not leave a room alone at the foot of a page', function () {
    // 16 rows, then a room of 4: the old chunker put the header on row 17 and
    // three of its machines overleaf.
    $rows = array_merge(roomRows('ห้อง เจาะมะพร้าว', 15), roomRows('ห้อง แคะมะพร้าว', 3));

    $pages = pagesOf($rows);

    expect($pages)->toHaveCount(2)
        ->and(firstOf($pages[1])['info']->location_name)->toBe('ห้อง แคะมะพร้าว');
});

it('keeps a room and its machines on the same page', function () {
    $rows = array_merge(roomRows('ห้อง ก', 15), roomRows('ห้อง ข', 3));

    foreach (pagesOf($rows) as $page) {
        $roomsOnPage = collect($page)->where('type', '!=', 'machine')->count();

        // Each page carries whole rooms, so every machine on it has its header
        // above it.
        expect($roomsOnPage)->toBeGreaterThan(0);
    }
});

it('fills a page when the rooms do fit', function () {
    $rows = array_merge(roomRows('ห้อง ก', 5), roomRows('ห้อง ข', 5), roomRows('ห้อง ค', 4));

    expect(pagesOf($rows))->toHaveCount(1);
});

/**
 * A room with more machines than fit on any page has to be split. The
 * continuation repeats its header so the machines under it are not orphaned.
 */
it('repeats the header when a room is larger than a page', function () {
    $pages = pagesOf(roomRows('ห้อง ใหญ่', 40));

    expect(count($pages))->toBeGreaterThan(1);

    foreach ($pages as $page) {
        expect(firstOf($page)['type'])->not->toBe('machine');
        expect(firstOf($page)['info']->location_name)->toBe('ห้อง ใหญ่');
    }
});

it('marks a repeated header as a continuation, not a second room', function () {
    $pages = pagesOf(roomRows('ห้อง ใหญ่', 40));

    expect(firstOf($pages[0]))->not->toHaveKey('continued')
        ->and(firstOf($pages[1])['continued'])->toBeTrue();
});

it('never puts more on a page than it holds', function () {
    $rows = array_merge(
        roomRows('ห้อง ก', 9), roomRows('ห้อง ข', 20), roomRows('ห้อง ค', 2)
    );

    foreach (pagesOf($rows) as $page) {
        expect(count($page))->toBeLessThanOrEqual(18);
    }
});

it('loses no row along the way', function () {
    $rows = array_merge(
        roomRows('ห้อง ก', 9), roomRows('ห้อง ข', 20), roomRows('ห้อง ค', 2)
    );

    $printed = collect(pagesOf($rows))->flatten(1)
        ->reject(fn ($row) => ! empty($row['continued']))
        ->count();

    expect($printed)->toBe(count($rows));
});

it('copes with an empty report', function () {
    expect(pagesOf([]))->toBe([]);
});

it('copes with a room that has no machines', function () {
    $pages = pagesOf(roomRows('ห้องว่าง', 0));

    expect($pages)->toHaveCount(1)
        ->and($pages[0])->toHaveCount(1);
});

/**
 * The first rule, applied alone, wasted paper: a room that would not fit in
 * what was left was moved over whole, leaving half a page blank behind it.
 */
it('splits a room to fill the page rather than leaving it blank', function () {
    // 6 rows used, 12 left, then a room of 15.
    $rows = array_merge(roomRows('ห้อง บรรจุน้ำขวด', 5), roomRows('ห้อง ลอกผิวมะพร้าว', 14));

    $pages = pagesOf($rows);

    expect(count($pages[0]))->toBe(18)
        ->and(firstOf($pages[1])['continued'])->toBeTrue();
});

/**
 * But not at any price: filling the page must not leave a heading with almost
 * nothing under it, which is the fault this was all for.
 */
it('does not start a room at the foot of a page for one machine', function () {
    // 16 rows used, 2 left: a header plus one machine is not worth starting.
    $rows = array_merge(roomRows('ห้อง ก', 15), roomRows('ห้อง ข', 12));

    $pages = pagesOf($rows);

    expect(count($pages[0]))->toBe(16)
        ->and(firstOf($pages[1])['info']->location_name)->toBe('ห้อง ข')
        ->and(firstOf($pages[1]))->not->toHaveKey('continued');
});

/**
 * Carrying a single machine over is allowed, and is the case that started
 * this: the user's page 4 had twelve rows free and the next room was thirteen
 * rows, so refusing to carry one machine left those twelve blank. A
 * continuation at the top of a page is followed by the next room on the same
 * page, so it costs nothing.
 */
it('carries a single machine over rather than wasting the page it came from', function () {
    // 6 rows used, 12 left, then a room of 13.
    $rows = array_merge(roomRows('ห้อง บรรจุน้ำขวด', 5), roomRows('ห้อง ลอกผิวมะพร้าว', 12));

    $pages = pagesOf($rows);

    expect(count($pages[0]))->toBe(18)
        ->and(firstOf($pages[1])['continued'])->toBeTrue();
});

it('still never strands a heading, however the rooms fall', function () {
    foreach ([[15, 12], [5, 12], [3, 14], [17, 4], [1, 40]] as [$first, $second]) {
        $pages = pagesOf(array_merge(roomRows('ห้อง ก', $first), roomRows('ห้อง ข', $second)));

        foreach ($pages as $page) {
            // Every page that opens with a heading has machines under it.
            if ($page[0]['type'] !== 'machine') {
                expect(count($page))->toBeGreaterThanOrEqual(2);
            }
        }
    }
});

/**
 * The repeat is only there to say which room these machines are in. Carrying
 * the room's results over printed its X and its "ยังไม่ได้แก้ไข" a second
 * time, so one finding appeared twice on the same form - and on a sheet kept
 * as audit evidence, a finding counted twice is a finding reported wrongly.
 */
it('carries no results onto a repeated header', function () {
    $rows = roomRows('ห้อง ใหญ่', 40);

    // The room's own row holds the area result.
    $rows[0]['results'] = ['42' => 'the area check'];

    $pages = pagesOf($rows);

    expect(firstOf($pages[0])['results'])->toBe(['42' => 'the area check']);

    foreach (array_slice($pages, 1) as $page) {
        expect(firstOf($page)['continued'])->toBeTrue()
            ->and(firstOf($page)['results'])->toBe([]);
    }
});
