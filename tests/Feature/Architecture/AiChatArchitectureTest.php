<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Tests\TestCase;

class AiChatArchitectureTest extends TestCase
{
    public function test_gemini_exceptions_are_owned_by_ai_chat_namespace(): void
    {
        $this->assertTrue(class_exists('App\\Exceptions\\AiChat\\GeminiNotConfiguredException'));
        $this->assertTrue(class_exists('App\\Exceptions\\AiChat\\GeminiRequestException'));
        $this->assertFalse(class_exists('App\\Exceptions\\GeminiNotConfiguredException'));
        $this->assertFalse(class_exists('App\\Exceptions\\GeminiRequestException'));
    }

    public function test_conversation_controller_delegates_data_operations_to_actions(): void
    {
        $contents = file_get_contents(app_path('Http/Controllers/AiChatConversationController.php'));

        $this->assertStringContainsString('IndexAction $action', $contents);
        $this->assertStringContainsString('ShowAction $action', $contents);
        $this->assertStringContainsString('UpdateAction $action', $contents);
        $this->assertStringContainsString('DestroyAction $action', $contents);
        $this->assertStringNotContainsString('AiChatConversation::query()', $contents);
        $this->assertStringNotContainsString('$conversation->update(', $contents);
        $this->assertStringNotContainsString('$conversation->delete(', $contents);
        $this->assertStringNotContainsString('private function latestFor(', $contents);
    }

    public function test_gemini_client_returns_array_without_response_value_object(): void
    {
        $contents = file_get_contents(app_path('Services/GeminiClient.php'));

        $this->assertFileDoesNotExist(app_path('Services/GeminiResponse.php'));
        $this->assertStringContainsString(
            '@return array{content: string, model: string, input_tokens: ?int, output_tokens: ?int}',
            $contents,
        );
    }
}
