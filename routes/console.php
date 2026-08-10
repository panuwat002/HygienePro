<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

Schedule::command('schedule:check-missed')->hourly();

Schedule::command('inspections:auto-close-stale')->everyFifteenMinutes();

Schedule::command('inspection:escalate-verifications')->hourly();

Schedule::command('inspection:send-smart-digest')->everyThirtyMinutes();
