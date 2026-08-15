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

Schedule::call(function () {
    \Illuminate\Support\Facades\Notification::route(\App\Channels\LineMessagingChannel::class, '')
        ->notify(new \App\Notifications\DailyDigestLineNotification());
})->dailyAt('08:00');

// ระบบสุ่มตรวจอัตโนมัติ: สร้างตารางการสุ่มตรวจล่วงหน้าทุกๆ เช้ามืดวันจันทร์ เวลา 01:00 น.
Schedule::command('audit:generate')->weeklyOn(1, '01:00');
