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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Optional AI providers for realistic Auto AI replies.
    // Primary free: GROQ_API_KEY from https://console.groq.com/keys
    // Fallback: GEMINI_API_KEY from https://aistudio.google.com/apikey
    'groq' => [
        'key' => env('GROQ_API_KEY'),
        // Current Groq free models (llama-3.1-8b-instant retired Aug 2026)
        'model' => env('GROQ_MODEL', 'openai/gpt-oss-20b'),
    ],
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.6-flash'),
    ],
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],
    'openrouter' => [
        'key' => env('OPENROUTER_API_KEY'),
        'model' => env('OPENROUTER_MODEL', 'google/gemma-2-9b-it:free'),
    ],
    'claude' => [
        'key' => env('CLAUDE_API_KEY', env('ANTHROPIC_API_KEY')),
        'model' => env('CLAUDE_MODEL', env('ANTHROPIC_MODEL', 'claude-3-5-haiku-20241022')),
    ],

    'razorpay' => [
        'key' => env('RAZORPAY_KEY_ID'),
        'secret' => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    ],

    'wallet' => [
        'min_balance' => (float) env('WALLET_MIN_BALANCE', 250),
        'price_marketing' => (float) env('WALLET_PRICE_MARKETING', 0.86),
        'price_utility' => (float) env('WALLET_PRICE_UTILITY', 0.17),
        'price_authentication' => (float) env('WALLET_PRICE_AUTHENTICATION', 0.17),
    ],

    'fcm' => [
        'server_key'       => env('FCM_SERVER_KEY'),           // Legacy (fallback)
        'credentials_path' => env('FCM_CREDENTIALS_PATH', 'storage/app/firebase/firebase-credentials.json'),
        'project_id'       => env('FCM_PROJECT_ID', 'whatsapp-bulk-14b86'),
    ],

    'whatsapp_session' => [
        'url' => env('WHATSAPP_SESSION_URL', 'http://127.0.0.1:3001'),
        'secret' => env('WHATSAPP_SESSION_SECRET', 'wa_session_secret_key_2026'),
        'timeout' => (int) env('WHATSAPP_SESSION_TIMEOUT', 15),
    ],

];
