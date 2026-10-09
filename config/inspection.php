<?php

return [
    'auto_close' => [
        'enabled'           => env('AUTO_CLOSE_ENABLED', true),      // kill-switch
        'grace_hours'       => env('AUTO_CLOSE_GRACE_HOURS', 2),     // close this long after shift end
        'idle_minutes'      => env('AUTO_CLOSE_IDLE_MINUTES', 30),   // defer if activity within this window
        'heartbeat_minutes' => env('AUTO_CLOSE_HEARTBEAT_MINUTES', 10), // dashboard throttle
    ],

    'car' => [
        // How long a finding may sit at 'resolved' before QA is reminded to
        // verify it. Measured from resolved_at, not from due_date: the person
        // who fixed it has finished, and the clock that matters now is how
        // long the check has been waiting.
        'verify_reminder_hours' => env('CAR_VERIFY_REMINDER_HOURS', 24),
    ],
];
