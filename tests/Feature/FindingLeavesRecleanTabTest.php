<?php

/**
 * A finding that had been fixed and verified could never leave สั่งแก้ไข.
 *
 * statusFromCounts() sent any card carrying a finding to the สั่งแก้ไข tab,
 * and is_finding stays true until a log reaches 'approved'. Approving happens
 * on the รออนุมัติ tab, which lists only cards reported as 'verified' - a
 * state the shortcut made unreachable. So the one action that would release
 * the card was only available from a tab the card could never enter.
 *
 * What a supervisor saw: fixed on the 7th, verified on the 7th, and on the
 * 12th the สั่งแก้ไข badge still read 1, with nothing in the card's detail
 * view left to press.
 */

use App\Support\VerificationGroups;

function countsRow(array $overrides = []): object
{
    return (object) array_merge([
        'n_total' => 1,
        'n_unverified' => 0,
        'n_rejected' => 0,
        'n_reclean' => 0,
        'n_approved' => 0,
        'n_auto' => 0,
        'is_finding' => false,
    ], $overrides);
}

function tabFor(array $overrides): string
{
    return (new VerificationGroups())->statusFromCounts(countsRow($overrides));
}

test('a brand new finding still goes straight to สั่งแก้ไข', function () {
    // The reason the shortcut exists: a finding must not sit in รอทวนสอบ
    // mixed in with the round's passes.
    expect(tabFor(['is_finding' => true, 'n_unverified' => 1]))->toBe('reclean');
});

test('a finding sent back for a re-clean stays in สั่งแก้ไข', function () {
    expect(tabFor(['is_finding' => true, 'n_reclean' => 1]))->toBe('reclean');
});

test('a finding QA has verified moves on to รออนุมัติ', function () {
    // Nothing outstanding: verified_at set, not flagged reclean, not yet
    // approved. This is the case that used to have nowhere to go.
    expect(tabFor(['is_finding' => true]))->toBe('verified');
});

test('once approved it leaves the queue entirely', function () {
    expect(tabFor(['is_finding' => true, 'n_approved' => 1]))->toBe('approved');
});

test('a rejected record still outranks everything', function () {
    expect(tabFor(['is_finding' => true, 'n_rejected' => 1, 'n_unverified' => 1]))->toBe('pending');
});

test('a card with a verified finding and an unverified pass is not finished', function () {
    // Two logs, one of them never looked at. The card is still work.
    expect(tabFor(['n_total' => 2, 'is_finding' => true, 'n_unverified' => 1]))->toBe('reclean');
});

test('a pass-only card is unaffected', function () {
    expect(tabFor(['n_unverified' => 1]))->toBe('pending');
    expect(tabFor([]))->toBe('verified');
    expect(tabFor(['n_reclean' => 1]))->toBe('reclean');
    expect(tabFor(['n_auto' => 1]))->toBe('auto_verified');
});
