<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AiChatMessage> */
class AiChatMessageFactory extends Factory
{
    protected $model = AiChatMessage::class;

    public function definition(): array
    {
        return [
            'ai_chat_conversation_id' => AiChatConversation::factory(),
            'role' => AiChatMessageRole::User->value,
            'status' => AiChatMessageStatus::Completed->value,
            'content' => fake()->realText(120),
            'model' => null,
            'input_tokens' => null,
            'output_tokens' => null,
            'response_time_ms' => null,
            'error_detail' => null,
        ];
    }

    public function assistant(): static
    {
        return $this->state(fn () => [
            'role' => AiChatMessageRole::Assistant->value,
            'status' => AiChatMessageStatus::Completed->value,
            'model' => 'gemini-2.5-flash',
        ]);
    }

    public function error(): static
    {
        return $this->assistant()->state(fn () => [
            'status' => AiChatMessageStatus::Error->value,
            'content' => null,
            'error_detail' => 'Gemini API request failed.',
        ]);
    }
}
