<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChat;

use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai-chat.enabled' => true,
            'ai-chat.gemini.api_key' => null,
        ]);
    }

    public function test_message_content_is_required_and_limited_to_2000_characters(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $conversation = AiChatConversation::factory()->for($student)->create();

        $this->actingAs($student)
            ->postJson("/ai-chat/conversations/{$conversation->id}/messages", ['content' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content');
        $this->actingAs($student)
            ->postJson("/ai-chat/conversations/{$conversation->id}/messages", [
                'content' => str_repeat('あ', 2001),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content');
    }

    public function test_missing_api_key_returns_error_message_in_successful_response_and_keeps_internal_detail(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $conversation = AiChatConversation::factory()->for($student)->create();

        $this->actingAs($student)
            ->postJson("/ai-chat/conversations/{$conversation->id}/messages", [
                'content' => '設定前の質問です。',
            ])
            ->assertOk()
            ->assertJsonPath('user_message.content', '設定前の質問です。')
            ->assertJsonPath('assistant_message.status', AiChatMessageStatus::Error->value)
            ->assertJsonPath('assistant_message.content', 'AI 相談は現在利用できません。システム設定が完了するまでお待ちください。')
            ->assertJsonMissingPath('assistant_message.error_detail');

        $this->assertDatabaseHas('ai_chat_messages', [
            'ai_chat_conversation_id' => $conversation->id,
            'content' => '設定前の質問です。',
        ]);
        $this->assertDatabaseHas('ai_chat_messages', [
            'ai_chat_conversation_id' => $conversation->id,
            'status' => AiChatMessageStatus::Error->value,
            'content' => 'AI 相談は現在利用できません。システム設定が完了するまでお待ちください。',
            'error_detail' => 'Gemini API key is not configured.',
        ]);
    }

    public function test_other_student_cannot_send_to_conversation(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $conversation = AiChatConversation::factory()->for($owner)->create();

        $this->actingAs($other)
            ->postJson("/ai-chat/conversations/{$conversation->id}/messages", [
                'content' => '他人の質問です。',
            ])
            ->assertForbidden();
    }

    public function test_daily_limit_returns_429_before_persisting_or_calling_gemini(): void
    {
        $this->travelTo('2026-09-19 23:59:00');
        config(['ai-chat.daily_message_limit' => 50]);
        $student = User::factory()->student()->inProgress()->create();
        $conversation = AiChatConversation::factory()->for($student)->create();
        $otherConversation = AiChatConversation::factory()->for($student)->create();
        AiChatMessage::factory()->count(25)->for($conversation, 'conversation')->create();
        AiChatMessage::factory()->count(25)->for($otherConversation, 'conversation')->create();

        $this->actingAs($student)
            ->postJson("/ai-chat/conversations/{$conversation->id}/messages", [
                'content' => '51通目の質問です。',
            ])
            ->assertStatus(429)
            ->assertJsonPath('message', '本日の利用上限に達しました。明日 0:00 以降に再度ご利用ください。')
            ->assertJsonPath('error_code', 'AI_CHAT_DAILY_LIMIT_EXCEEDED');

        $this->assertSame(50, AiChatMessage::query()->where('role', 'user')->count());
    }

    public function test_daily_limit_resets_at_midnight_in_application_timezone(): void
    {
        config(['ai-chat.daily_message_limit' => 50]);
        $this->travelTo('2026-09-19 23:59:00');
        $student = User::factory()->student()->inProgress()->create();
        $conversation = AiChatConversation::factory()->for($student)->create();
        AiChatMessage::factory()->count(50)->for($conversation, 'conversation')->create();

        $this->travelTo('2026-09-20 00:00:00');

        $this->actingAs($student)
            ->postJson("/ai-chat/conversations/{$conversation->id}/messages", [
                'content' => '翌日の最初の質問です。',
            ])
            ->assertOk()
            ->assertJsonPath('assistant_message.status', AiChatMessageStatus::Error->value);

        $this->assertSame(51, AiChatMessage::query()->where('role', 'user')->count());
    }

    public function test_fiftieth_message_is_allowed(): void
    {
        config(['ai-chat.daily_message_limit' => 50]);
        $student = User::factory()->student()->inProgress()->create();
        $conversation = AiChatConversation::factory()->for($student)->create();
        AiChatMessage::factory()->count(49)->for($conversation, 'conversation')->create();

        $this->actingAs($student)
            ->postJson("/ai-chat/conversations/{$conversation->id}/messages", [
                'content' => '50通目の質問です。',
            ])
            ->assertOk()
            ->assertJsonPath('assistant_message.status', AiChatMessageStatus::Error->value);

        $this->assertSame(50, AiChatMessage::query()->where('role', 'user')->count());
    }

    public function test_failed_send_counts_toward_daily_limit(): void
    {
        config([
            'ai-chat.daily_message_limit' => 1,
            'ai-chat.gemini.api_key' => null,
        ]);
        $student = User::factory()->student()->inProgress()->create();
        $conversation = AiChatConversation::factory()->for($student)->create();

        $this->actingAs($student)
            ->postJson("/ai-chat/conversations/{$conversation->id}/messages", [
                'content' => 'APIキー未設定時の質問です。',
            ])
            ->assertOk()
            ->assertJsonPath('assistant_message.status', AiChatMessageStatus::Error->value);

        $this->actingAs($student)
            ->postJson("/ai-chat/conversations/{$conversation->id}/messages", [
                'content' => '同日の再質問です。',
            ])
            ->assertStatus(429)
            ->assertJsonPath('error_code', 'AI_CHAT_DAILY_LIMIT_EXCEEDED');

        $this->assertSame(1, AiChatMessage::query()->where('role', 'user')->count());
    }
}
