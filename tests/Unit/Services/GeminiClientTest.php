<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\AiChat\GeminiNotConfiguredException;
use App\Services\GeminiClient;
use Tests\TestCase;
use Throwable;

class GeminiClientTest extends TestCase
{
    public function test_generate_throws_not_configured_exception_when_api_key_is_empty(): void
    {
        config(['ai-chat.gemini.api_key' => null]);

        try {
            app(GeminiClient::class)->generate([], 'system');
            $this->fail('API キー未設定専用の例外が送出されるべきです。');
        } catch (Throwable $exception) {
            $this->assertInstanceOf(GeminiNotConfiguredException::class, $exception);
        }
    }
}
