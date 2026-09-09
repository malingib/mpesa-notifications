<?php

return [
    'sms' => [
        'api_url' => env('TALKSASA_SMS_API_URL', 'https://api.talksasa.com/v1'),
        'api_key' => env('TALKSASA_SMS_API_KEY'),
        'api_secret' => env('TALKSASA_SMS_API_SECRET'),
        'sender_id' => env('TALKSASA_SMS_SENDER_ID', 'TALKSASA'),
        'timeout' => env('TALKSASA_SMS_TIMEOUT', 30),
        'retry_attempts' => env('TALKSASA_SMS_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('TALKSASA_SMS_RETRY_DELAY', 1),
        'low_balance_threshold' => env('TALKSASA_SMS_LOW_BALANCE_THRESHOLD', 100.0),
    ],
];
