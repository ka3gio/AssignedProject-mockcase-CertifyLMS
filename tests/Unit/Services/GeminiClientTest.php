<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\AiChat\GeminiNotConfiguredException;
use App\Exceptions\AiChat\GeminiRequestException;
use App\Services\GeminiClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Tests\ExternalApiTestCase;
use Throwable;

#[Group('external-api')]
class GeminiClientTest extends ExternalApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai-chat.gemini.api_key' => 'test-api-key',
            'ai-chat.gemini.model' => 'gemini-test',
            'ai-chat.gemini.retry_times' => 2,
            'ai-chat.gemini.retry_sleep_ms' => 0,
        ]);
    }

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

    public function test_generate_returns_content_and_usage_from_a_successful_response(): void
    {
        Http::fake([
            '*' => Http::response($this->geminiResponse('木構造として探索します。')),
        ]);

        $result = app(GeminiClient::class)->generate([
            ['role' => 'user', 'parts' => [['text' => '二分探索木とは？']]],
        ], '日本語で簡潔に回答してください。');

        $this->assertSame([
            'content' => '木構造として探索します。',
            'model' => 'gemini-test-version',
            'input_tokens' => 12,
            'output_tokens' => 7,
        ], $result);
    }

    public function test_generate_wraps_a_connection_failure(): void
    {
        Http::fake(static function (): never {
            throw new ConnectionException('connection refused');
        });

        $this->expectException(GeminiRequestException::class);

        app(GeminiClient::class)->generate([], 'system');
    }

    public function test_generate_rejects_an_empty_response(): void
    {
        Http::fake([
            '*' => Http::response(['candidates' => []]),
        ]);

        $this->expectException(GeminiRequestException::class);

        app(GeminiClient::class)->generate([], 'system');
    }

    public function test_generate_retries_a_temporary_server_error_and_returns_the_next_response(): void
    {
        Http::fakeSequence()
            ->push(['error' => ['message' => 'temporarily unavailable']], 503)
            ->push($this->geminiResponse('再試行後の回答です。'));

        $result = app(GeminiClient::class)->generate([], 'system');

        $this->assertSame('再試行後の回答です。', $result['content']);
        $this->assertCount(2, Http::recorded());
    }

    public function test_generate_sends_the_expected_prompt_structure_and_authentication_header(): void
    {
        Http::fake([
            '*' => Http::response($this->geminiResponse('回答')),
        ]);
        $contents = [
            ['role' => 'user', 'parts' => [['text' => '質問本文']]],
            ['role' => 'model', 'parts' => [['text' => '以前の回答']]],
        ];

        app(GeminiClient::class)->generate($contents, 'システム指示');

        Http::assertSent(function (Request $request) use ($contents): bool {
            return $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-test:generateContent'
                && $request->hasHeader('x-goog-api-key', 'test-api-key')
                && $request->data() === [
                    'system_instruction' => [
                        'parts' => [['text' => 'システム指示']],
                    ],
                    'contents' => $contents,
                ];
        });
    }

    /** @return array<string, mixed> */
    private function geminiResponse(string $content): array
    {
        return [
            'candidates' => [[
                'content' => [
                    'parts' => [['text' => $content]],
                ],
            ]],
            'modelVersion' => 'gemini-test-version',
            'usageMetadata' => [
                'promptTokenCount' => 12,
                'candidatesTokenCount' => 7,
            ],
        ];
    }
}
