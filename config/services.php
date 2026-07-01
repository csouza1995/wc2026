<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'football_data' => [
        'url' => env('FOOTBALL_DATA_ORG_URL', 'https://api.football-data.org/v4'),
        'key' => env('FOOTBALL_DATA_ORG_API_KEY'),
        'competition_code' => env('FOOTBALL_DATA_ORG_COMPETITION_CODE', 'WC'),
    ],

    'api_football' => [
        'url' => env('API_FOOTBALL_URL', 'https://v3.football.api-sports.io'),
        'key' => env('API_FOOTBALL_API_KEY'),
        'daily_quota' => env('API_FOOTBALL_DAILY_QUOTA', 100),
        // World Cup league id in api-football's own catalog.
        'league_id' => env('API_FOOTBALL_LEAGUE_ID', 1),
        'season' => env('API_FOOTBALL_SEASON', 2026),
    ],

];
