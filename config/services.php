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

    /*
    |--------------------------------------------------------------------------
    | Agen Klinik (PoC PBL)
    |--------------------------------------------------------------------------
    */

    // LLM yang jadi "otak" percakapan (gateway OpenAI-compatible)
    'llm' => [
        'base_url' => env('LLM_BASE_URL', 'http://127.0.0.1:20128/v1'),
        'api_key' => env('LLM_API_KEY'),
        'model' => env('LLM_MODEL', 'cmc/deepseek/deepseek-v4.1-flash'),
        // Batas token keluaran. Setiap token tambahan menambah waktu tunggu;
        // tanpa batas, model bisa menulis sepanjang yang ia mau.
        'max_tokens' => (int) env('LLM_MAX_TOKENS', 300),
        // Dipakai otomatis kalau model utama sedang down (mis. 503 dari penyedia)
        'model_fallback' => env('LLM_MODEL_FALLBACK', 'cmc/zai-org/GLM-5.2-Fast'),
    ],

    // Gemini Live API untuk percakapan suara langsung di browser
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_LIVE_MODEL', 'models/gemini-2.5-flash-native-audio-preview-12-2025'),
        'voice' => env('GEMINI_LIVE_VOICE', 'Kore'),
        // Kirim expireTime/newSessionExpireTime dari jam komputer ini?
        // Bawaan: tidak. Waktu dihitung dari jam lokal, jadi jam yang melenceng
        // bisa membuat token dianggap kedaluwarsa sejak dibuat.
        'kirim_waktu_token' => (bool) env('GEMINI_KIRIM_WAKTU_TOKEN', false),
    ],

];
