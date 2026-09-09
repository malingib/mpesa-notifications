<?php

return [
    'validation_url' => env('MPESA_VALIDATION_URL'),
    'confirmation_url' => env('MPESA_CONFIRMATION_URL'),
    'consumer_key' => env('MPESA_CONSUMER_KEY'),
    'consumer_secret' => env('MPESA_CONSUMER_SECRET'),
    'passkey' => env('MPESA_PASSKEY'),
    'shortcode' => env('MPESA_SHORTCODE'),
    'environment' => env('MPESA_ENVIRONMENT', 'sandbox'),
    'webhook_secret' => env('WEBHOOK_SECRET_KEY'),
];
