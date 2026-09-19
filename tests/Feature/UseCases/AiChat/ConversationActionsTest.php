<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\AiChat;

use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\User;
use App\UseCases\AiChat\DestroyAction;
use App\UseCases\AiChat\IndexAction;
use App\UseCases\AiChat\ShowAction;
use App\UseCases\AiChat\UpdateAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_action_returns_latest_owned_conversation(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        AiChatConversation::factory()->for($student)->create([
            'last_message_at' => now()->subHour(),
        ]);
        $latest = AiChatConversation::factory()->for($student)->create([
            'last_message_at' => now(),
        ]);
        AiChatConversation::factory()->create([
            'last_message_at' => now()->addHour(),
        ]);

        $result = app(IndexAction::class)($student);

        $this->assertTrue($result->is($latest));
    }

    public function test_show_action_loads_conversation_context_and_ordered_messages(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $conversation = AiChatConversation::factory()->for($student)->create();
        $later = AiChatMessage::factory()->assistant()->for($conversation, 'conversation')->create([
            'role' => 'assistant',
            'status' => 'completed',
            'content' => '後の回答',
            'created_at' => now(),
        ]);
        $earlier = AiChatMessage::factory()->for($conversation, 'conversation')->create([
            'role' => 'user',
            'status' => 'completed',
            'content' => '先の質問',
            'created_at' => now()->subMinute(),
        ]);

        $result = app(ShowAction::class)($conversation);

        $this->assertTrue($result->relationLoaded('enrollment'));
        $this->assertTrue($result->relationLoaded('section'));
        $this->assertTrue($result->relationLoaded('messages'));
        $this->assertSame([$earlier->id, $later->id], $result->messages->pluck('id')->all());
    }

    public function test_update_action_updates_title(): void
    {
        $conversation = AiChatConversation::factory()->create([
            'title' => '更新前',
        ]);

        $result = app(UpdateAction::class)($conversation, ['title' => '更新後']);

        $this->assertSame('更新後', $result->title);
        $this->assertSame('更新後', $conversation->fresh()->title);
    }

    public function test_destroy_action_deletes_conversation_and_returns_remaining_latest(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $remaining = AiChatConversation::factory()->for($student)->create([
            'last_message_at' => now()->subMinute(),
        ]);
        $target = AiChatConversation::factory()->for($student)->create([
            'last_message_at' => now(),
        ]);

        $result = app(DestroyAction::class)($student, $target);

        $this->assertDatabaseMissing('ai_chat_conversations', ['id' => $target->id]);
        $this->assertTrue($result->is($remaining));
    }
}
