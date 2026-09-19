<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiChatVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_feature_off_hides_sidebar_link_and_widget(): void
    {
        config(['ai-chat.enabled' => false]);
        $student = User::factory()->student()->inProgress()->create();

        $this->actingAs($student)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('AI 相談')
            ->assertDontSee('data-ai-chat-widget', false);
    }

    public function test_feature_on_shows_sidebar_link_and_widget_for_in_progress_student(): void
    {
        config([
            'ai-chat.enabled' => true,
            'ai-chat.gemini.model' => 'internal-model-name',
        ]);
        $student = User::factory()->student()->inProgress()->create();

        $this->actingAs($student)->get('/dashboard')
            ->assertOk()
            ->assertSee('AI 相談')
            ->assertSee('data-ai-chat-widget', false)
            ->assertDontSee('internal-model-name');
    }

    public function test_conversation_does_not_display_operational_metadata(): void
    {
        config([
            'ai-chat.enabled' => true,
            'ai-chat.gemini.model' => 'internal-config-model',
        ]);
        $student = User::factory()->student()->inProgress()->create();
        $conversation = AiChatConversation::factory()->for($student)->create();
        AiChatMessage::factory()->assistant()->for($conversation, 'conversation')->create([
            'model' => 'internal-response-model',
            'input_tokens' => 987,
            'output_tokens' => 654,
            'response_time_ms' => 4321,
        ]);

        $this->actingAs($student)->get("/ai-chat/conversations/{$conversation->id}")
            ->assertOk()
            ->assertSee('会話内容からタイトルを自動生成する')
            ->assertSee('name="auto_title_enabled"', false)
            ->assertDontSee('internal-config-model')
            ->assertDontSee('internal-response-model')
            ->assertDontSee('654 tokens')
            ->assertDontSee('4.3 s');
    }

    public function test_new_conversation_form_shows_auto_title_option(): void
    {
        config(['ai-chat.enabled' => true]);
        $student = User::factory()->student()->inProgress()->create();

        $this->actingAs($student)->get('/ai-chat')
            ->assertOk()
            ->assertSee('会話内容からタイトルを自動生成する')
            ->assertSee('name="auto_title_enabled"', false);
    }

    public function test_feature_on_does_not_show_ai_chat_ui_for_coach(): void
    {
        config(['ai-chat.enabled' => true]);
        $coach = User::factory()->coach()->inProgress()->create();

        $this->actingAs($coach)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('AI 相談')
            ->assertDontSee('data-ai-chat-widget', false);
    }
}
