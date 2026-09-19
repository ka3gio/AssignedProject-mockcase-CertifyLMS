<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('AI_CHAT_ENABLED', false),
    'daily_message_limit' => 50,
    'history_message_limit' => 20,
    'system_prompt' => 'あなたは資格学習を支援するAIアシスタントです。正確で簡潔な日本語で回答してください。',

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => 'gemini-3.5-flash',
        'base_url' => 'https://generativelanguage.googleapis.com',
        'timeout' => 30,
    ],
];
