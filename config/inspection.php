<?php

return [
    'auto_close' => [
        'enabled'           => env('AUTO_CLOSE_ENABLED', true),      // kill-switch
        'grace_hours'       => env('AUTO_CLOSE_GRACE_HOURS', 2),     // close this long after shift end
        'idle_minutes'      => env('AUTO_CLOSE_IDLE_MINUTES', 30),   // defer if activity within this window
        'heartbeat_minutes' => env('AUTO_CLOSE_HEARTBEAT_MINUTES', 10), // dashboard throttle
    ],
];
