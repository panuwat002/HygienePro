<?php

use App\Models\Department;
use App\Models\User;

/**
 * <input type="date"> is drawn by the browser in the machine's own locale, so
 * on these PCs every date field read MM/DD/YYYY - 10/03/2026 for the third of
 * October. On a system whose records are evidence, a date that reads two ways
 * is not a small thing.
 *
 * The fix is display-only: flatpickr shows d/m/Y and keeps posting Y-m-d, so
 * nothing on the server changes.
 */
beforeEach(function () {
    $this->dept = Department::create([
        'dept_name' => 'Quality Assurance', 'dept_code' => 'QA', 'visibility_type' => 'global',
    ]);

    $this->admin = User::create([
        'name' => 'Admin', 'email' => 'admin@example.com',
        'password' => bcrypt('password'), 'role' => 'admin', 'level' => 9,
        'department_id' => $this->dept->id,
    ]);
});

it('dresses every date field on the page as d/m/Y', function () {
    $html = $this->actingAs($this->admin)
        ->get(route('reports.index'))
        ->assertSuccessful()
        ->getContent();

    expect($html)->toContain("altFormat: 'd/m/Y'")
        ->and($html)->toContain("dateFormat: 'Y-m-d'");
});

it('loads the picker for every page, not just the ones that remembered to', function () {
    foreach ([route('reports.index'), route('corrective.index')] as $url) {
        $html = $this->actingAs($this->admin)->get($url)->assertSuccessful()->getContent();

        expect($html)->toContain('flatpickr');
    }
});

/**
 * The server still receives ISO. If this ever stopped being true the date
 * filter would silently read the wrong day.
 */
it('still accepts and uses an ISO date from the form', function () {
    $this->actingAs($this->admin)
        ->get(route('reports.daily', ['date' => '2026-10-03']))
        ->assertSuccessful()
        ->assertViewHas('date', '2026-10-03');
});
