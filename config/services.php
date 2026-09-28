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

    'ai' => [
        'provider' => env('AI_PROVIDER', 'openai'),
    ],

    'openai' => [
        'api_key'          => env('OPENAI_API_KEY'),
        'model'            => env('OPENAI_MODEL', 'gpt-5.5'),
        'transcribe_model' => env('OPENAI_TRANSCRIBE_MODEL', 'whisper-1'),
        'base_url'         => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'pricing'          => [
            'prompt_per_million'     => (float) env('OPENAI_PROMPT_PER_MILLION', 1.25),
            'candidates_per_million' => (float) env('OPENAI_CANDIDATES_PER_MILLION', 10.00),
            'whisper_per_minute'     => (float) env('OPENAI_WHISPER_PER_MINUTE', 0.006),
        ],
    ],

    'gemini' => [
        'api_key'          => env('GEMINI_API_KEY'),
        'transcribe_key'   => env('GEMINI_TRANSCRIBE_KEY', env('GEMINI_API_KEY')),
        'model'            => env('GEMINI_MODEL', 'gemini-3.6-flash'),
        'transcribe_model' => env('GEMINI_TRANSCRIBE_MODEL', 'gemini-3.5-transcribe'),
        'base_url'         => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'pricing'          => [
            'prompt_per_million'     => (float) env('GEMINI_PROMPT_PER_MILLION', 0.075),
            'candidates_per_million' => (float) env('GEMINI_CANDIDATES_PER_MILLION', 0.30),
        ],
    ],

    'python' => [
        'path' => env('PYTHON_PATH', 'C:\\laragon\\bin\\python\\python-3.13\\python.exe'),
    ],

];
