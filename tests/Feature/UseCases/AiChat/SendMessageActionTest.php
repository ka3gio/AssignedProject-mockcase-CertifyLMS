<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\AiChat;

use App\Models\AiChatConversation;
use App\Models\User;
use App\UseCases\AiChat\SendMessageAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Tests\ExternalApiTestCase;

#[Group('external-api')]
class SendMessageActionTest extends ExternalApiTestCase
{
    use RefreshDatabase;

    public function test_initial_title_is_generated_from_a_user_request_containing_the_transcript(): void
    {
        config([
            'ai-chat.gemini.api_key' => 'test-api-key',
            'ai-chat.gemini.model' => 'gemini-test',
        ]);

        $student = User::factory()->student()->inProgress()->create();
        $conversation = AiChatConversation::factory()->for($student)->create([
            'title' => '新しい相談',
            'auto_title_enabled' => true,
        ]);
        $requestCount = 0;

        Http::fake(function (Request $request) use (&$requestCount) {
            $requestCount++;

            if ($requestCount === 1) {
                return Http::response($this->geminiResponse('二分探索木では探索範囲を半分ずつ絞り込みます。'));
            }

            $contents = $request->data()['contents'] ?? [];
            $latestContent = $contents[array_key_last($contents)] ?? [];
            $latestText = data_get($latestContent, 'parts.0.text', '');

            if (($latestContent['role'] ?? null) !== 'user'
                || ! str_contains($latestText, '質問: 二分探索木が O(log n) になる理由は？')
                || ! str_contains($latestText, '回答: 二分探索木では探索範囲を半分ずつ絞り込みます。')) {
                return Http::response(['usageMetadata' => []]);
            }

            return Http::response($this->geminiResponse('二分探索木の計算量'));
        });

        app(SendMessageAction::class)(
            $student,
            $conversation,
            '二分探索木が O(log n) になる理由は？',
        );

        $this->assertSame('二分探索木の計算量', $conversation->fresh()->title);
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
            'modelVersion' => 'gemini-test',
            'usageMetadata' => [
                'promptTokenCount' => 10,
                'candidatesTokenCount' => 5,
            ],
        ];
    }
}
