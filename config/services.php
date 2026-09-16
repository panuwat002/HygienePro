<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
     * env() only reads the .env file before the config cache is built. Once
     * `php artisan config:cache` runs in production it returns null everywhere
     * outside this directory, so anything calling env() at runtime goes silently
     * dead. These keys were read that way from LineMessagingChannel and
     * MachineController; read them through config() instead.
     */
    'line' => [
        'token' => env('LINE_CHANNEL_ACCESS_TOKEN'),
        'secret' => env('LINE_CHANNEL_SECRET'),
        'group_id' => env('LINE_GROUP_ID'),
    ],

    'python' => [
        'path' => env('PYTHON_PATH', 'python'),
    ],

];
