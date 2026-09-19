<?php

declare(strict_types=1);

namespace App\UseCases\AiChat;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Exceptions\AiChat\AiChatDailyLimitExceededException;
use App\Exceptions\AiChat\AiChatResponseException;
use App\Exceptions\AiChat\GeminiNotConfiguredException;
use App\Exceptions\AiChat\GeminiRequestException;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\User;
use App\Services\GeminiClient;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class SendMessageAction
{
    public function __construct(private readonly GeminiClient $gemini) {}

    /**
     * @return array{user_message: AiChatMessage, assistant_message: AiChatMessage}
     */
    public function __invoke(User $user, AiChatConversation $conversation, string $content): array
    {
        if ($conversation->user_id !== $user->id) {
            throw new AuthorizationException;
        }

        [$userMessage, $assistantMessage] = DB::transaction(function () use ($user, $conversation, $content): array {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $dayStartsAt = now()->startOfDay();

            $sentCount = AiChatMessage::query()
                ->where('role', AiChatMessageRole::User->value)
                ->where('created_at', '>=', $dayStartsAt)
                ->where('created_at', '<', $dayStartsAt->copy()->addDay())
                ->whereHas(
                    'conversation',
                    fn ($query) => $query->where('user_id', $user->id),
                )
                ->count();

            if ($sentCount >= (int) config('ai-chat.daily_message_limit')) {
                throw new AiChatDailyLimitExceededException;
            }

            $userMessage = $conversation->messages()->create([
                'role' => AiChatMessageRole::User,
                'status' => AiChatMessageStatus::Completed,
                'content' => $content,
            ]);
            $assistantMessage = $conversation->messages()->create([
                'role' => AiChatMessageRole::Assistant,
                'status' => AiChatMessageStatus::Pending,
                'content' => '',
            ]);

            return [$userMessage, $assistantMessage];
        });

        $contents = $this->recentContents($conversation);
        $systemPrompt = $this->systemPrompt($conversation);
        $startedAt = hrtime(true);

        try {
            $response = $this->gemini->generate($contents, $systemPrompt);
            $responseTimeMs = $this->elapsedMilliseconds($startedAt);

            $assistantMessage->update([
                'status' => AiChatMessageStatus::Completed,
                'content' => $response['content'],
                'model' => $response['model'],
                'input_tokens' => $response['input_tokens'],
                'output_tokens' => $response['output_tokens'],
                'response_time_ms' => $responseTimeMs,
                'error_detail' => null,
            ]);

            $this->generateInitialTitle($conversation, $content, $response['content']);

            return [
                'user_message' => $userMessage->fresh(),
                'assistant_message' => $assistantMessage->fresh(),
            ];
        } catch (GeminiNotConfiguredException|GeminiRequestException $exception) {
            $responseTimeMs = $this->elapsedMilliseconds($startedAt);
            $isNotConfigured = $exception instanceof GeminiNotConfiguredException;
            $upstreamStatus = $exception instanceof GeminiRequestException
                ? $exception->upstreamStatus
                : null;
            $assistantMessage->update([
                'status' => AiChatMessageStatus::Error,
                'content' => $isNotConfigured
                    ? 'AI 相談は現在利用できません。システム設定が完了するまでお待ちください。'
                    : '',
                'model' => config('ai-chat.gemini.model'),
                'response_time_ms' => $responseTimeMs,
                'error_detail' => $upstreamStatus === null
                    ? $exception->getMessage()
                    : "Gemini API request failed ({$upstreamStatus}).",
            ]);

            Log::warning('AI chat Gemini request failed.', [
                'conversation_id' => $conversation->id,
                'assistant_message_id' => $assistantMessage->id,
                'model' => config('ai-chat.gemini.model'),
                'upstream_status' => $upstreamStatus,
                'response_time_ms' => $responseTimeMs,
            ]);

            if ($isNotConfigured) {
                return [
                    'user_message' => $userMessage->fresh(),
                    'assistant_message' => $assistantMessage->fresh(),
                ];
            }

            throw new AiChatResponseException($upstreamStatus, $exception);
        }
    }

    /**
     * @return array<int, array{role: string, parts: array<int, array{text: string}>}>
     */
    private function recentContents(AiChatConversation $conversation): array
    {
        $messages = $conversation->messages()
            ->where('status', AiChatMessageStatus::Completed->value)
            ->whereIn('role', [AiChatMessageRole::User->value, AiChatMessageRole::Assistant->value])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(max(1, (int) config('ai-chat.history_message_limit')))
            ->get()
            ->reverse()
            ->values();

        return $messages->map(fn (AiChatMessage $message): array => [
            'role' => $message->role === AiChatMessageRole::Assistant ? 'model' : 'user',
            'parts' => [['text' => (string) $message->content]],
        ])->all();
    }

    private function systemPrompt(AiChatConversation $conversation): string
    {
        $conversation->loadMissing([
            'enrollment.certification',
            'section.chapter.part.certification',
        ]);

        $lines = [(string) config('ai-chat.system_prompt'), '', '以下の学習文脈を踏まえて回答してください。'];

        if ($conversation->section !== null) {
            $section = $conversation->section;
            $lines[] = '資格: '.$section->chapter->part->certification->name;
            $lines[] = 'Part: '.$section->chapter->part->title;
            $lines[] = 'Chapter: '.$section->chapter->title;
            $lines[] = 'Section: '.$section->title;
            $lines[] = '説明: '.($section->description ?? '');
            $lines[] = '教材本文:';
            $lines[] = $section->body;
        } elseif ($conversation->enrollment?->certification !== null) {
            $lines[] = '資格: '.$conversation->enrollment->certification->name;
        } else {
            $lines[] = '資格: 指定なし';
        }

        return implode("\n", $lines);
    }

    private function generateInitialTitle(
        AiChatConversation $conversation,
        string $userContent,
        string $assistantContent,
    ): void {
        if (! $conversation->auto_title_enabled || $conversation->title !== '新しい相談') {
            return;
        }

        $startedAt = hrtime(true);

        try {
            $response = $this->gemini->generate([
                [
                    'role' => 'user',
                    'parts' => [[
                        'text' => "質問: {$userContent}\n\n回答: {$assistantContent}",
                    ]],
                ],
            ], 'この会話を表す日本語の会話タイトルを40文字以内で1つだけ生成してください。引用符や説明は付けないでください。');

            $title = $this->normalizeTitle($response['content']);
            if ($title === '') {
                return;
            }

            $conversation->update(['title' => $title]);

            Log::info('AI chat title generated.', [
                'conversation_id' => $conversation->id,
                'model' => $response['model'],
                'input_tokens' => $response['input_tokens'],
                'output_tokens' => $response['output_tokens'],
                'response_time_ms' => $this->elapsedMilliseconds($startedAt),
            ]);
        } catch (GeminiNotConfiguredException|GeminiRequestException $exception) {
            Log::warning('AI chat title generation failed.', [
                'conversation_id' => $conversation->id,
                'upstream_status' => $exception instanceof GeminiRequestException
                    ? $exception->upstreamStatus
                    : null,
                'response_time_ms' => $this->elapsedMilliseconds($startedAt),
            ]);
        }
    }

    private function normalizeTitle(string $content): string
    {
        $firstLine = preg_split('/\R/u', trim($content))[0] ?? '';
        $title = preg_replace('/\A[「『"\'\s]+|[」』"\'\s]+\z/u', '', $firstLine) ?? '';

        return Str::limit(trim($title), 100, '');
    }

    private function elapsedMilliseconds(int $startedAt): int
    {
        return max(0, (int) round((hrtime(true) - $startedAt) / 1_000_000));
    }
}
