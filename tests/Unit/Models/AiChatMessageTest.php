<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiChatMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_relation_returns_parent(): void
    {
        // Arrange
        $conversation = AiChatConversation::factory()->create();
        $message = AiChatMessage::factory()->for($conversation, 'conversation')->create();

        // Act / Assert
        $this->assertTrue($message->conversation->is($conversation));
    }

    public function test_role_status_and_metrics_are_cast(): void
    {
        // Arrange
        $message = AiChatMessage::factory()->create([
            'role' => AiChatMessageRole::Assistant->value,
            'status' => AiChatMessageStatus::Completed->value,
            'input_tokens' => '12',
            'output_tokens' => '34',
            'response_time_ms' => '56',
        ]);

        // Act
        $fresh = $message->fresh();

        // Assert
        $this->assertSame(AiChatMessageRole::Assistant, $fresh->role);
        $this->assertSame(AiChatMessageStatus::Completed, $fresh->status);
        $this->assertSame(12, $fresh->input_tokens);
        $this->assertSame(34, $fresh->output_tokens);
        $this->assertSame(56, $fresh->response_time_ms);
    }

    public function test_creating_message_updates_conversation_last_message_at(): void
    {
        // Arrange
        $conversation = AiChatConversation::factory()->create(['last_message_at' => null]);

        // Act
        $message = AiChatMessage::factory()->for($conversation, 'conversation')->create();

        // Assert
        $this->assertSame(
            $message->created_at->format('Y-m-d H:i:s'),
            $conversation->fresh()->last_message_at?->format('Y-m-d H:i:s'),
        );
    }
}
