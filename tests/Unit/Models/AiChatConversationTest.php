<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiChatConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_relations_return_owner_context_and_messages(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->for($student)->create();
        $section = Section::factory()->create();
        $conversation = AiChatConversation::factory()
            ->for($student)
            ->for($enrollment)
            ->for($section)
            ->create();
        $message = AiChatMessage::factory()->for($conversation, 'conversation')->create();

        // Act / Assert
        $this->assertTrue($conversation->user->is($student));
        $this->assertTrue($conversation->enrollment->is($enrollment));
        $this->assertTrue($conversation->section->is($section));
        $this->assertTrue($conversation->messages->first()->is($message));
    }

    public function test_last_message_at_is_cast_to_datetime(): void
    {
        // Arrange
        $conversation = AiChatConversation::factory()->create([
            'last_message_at' => '2026-09-16 10:00:00',
        ]);

        // Act
        $fresh = $conversation->fresh();

        // Assert
        $this->assertSame('2026-09-16 10:00:00', $fresh->last_message_at?->format('Y-m-d H:i:s'));
    }

    public function test_auto_title_is_enabled_by_default_and_cast_to_boolean(): void
    {
        $conversation = AiChatConversation::query()->create([
            'user_id' => User::factory()->student()->create()->id,
        ]);

        $this->assertTrue($conversation->fresh()->auto_title_enabled);
    }

    public function test_deleting_conversation_cascades_messages(): void
    {
        $conversation = AiChatConversation::factory()->create();
        $message = AiChatMessage::factory()->for($conversation, 'conversation')->create();

        $conversation->delete();

        $this->assertDatabaseMissing('ai_chat_messages', ['id' => $message->id]);
    }
}
