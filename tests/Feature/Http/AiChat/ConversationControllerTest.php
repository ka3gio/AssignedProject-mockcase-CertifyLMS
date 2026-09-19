<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChat;

use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['ai-chat.enabled' => true]);
    }

    public function test_index_redirects_to_latest_owned_conversation(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        AiChatConversation::factory()->for($student)->create([
            'last_message_at' => now()->subHour(),
        ]);
        $latest = AiChatConversation::factory()->for($student)->create([
            'last_message_at' => now(),
        ]);
        AiChatConversation::factory()->create(['last_message_at' => now()->addHour()]);

        $this->actingAs($student)->get('/ai-chat')
            ->assertRedirect("/ai-chat/conversations/{$latest->id}");
    }

    public function test_index_shows_empty_state_when_user_has_no_conversation(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $this->actingAs($student)->get('/ai-chat')
            ->assertOk()
            ->assertSee('まだ相談履歴はありません');
    }

    public function test_widget_create_returns_created_then_existing_section_conversation(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $section = $this->createViewableSectionFor($student);

        $created = $this->actingAs($student)->postJson('/ai-chat/conversations', [
            'source' => 'widget',
            'section_id' => $section->id,
        ])->assertCreated();
        $conversationId = $created->json('conversation.id');

        $this->actingAs($student)->postJson('/ai-chat/conversations', [
            'source' => 'widget',
            'section_id' => $section->id,
        ])->assertOk()->assertJsonPath('conversation.id', $conversationId);
        $this->assertSame(1, $student->aiChatConversations()->count());
    }

    public function test_full_screen_create_redirects_to_new_general_conversation(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($student)->post('/ai-chat/conversations', [
            'source' => 'full-screen',
            'auto_title_enabled' => '0',
        ]);

        $conversation = $student->aiChatConversations()->sole();
        $response->assertRedirect("/ai-chat/conversations/{$conversation->id}");
        $this->assertFalse($conversation->auto_title_enabled);
    }

    public function test_full_screen_initial_message_redirects_with_error_when_daily_limit_is_reached(): void
    {
        config(['ai-chat.daily_message_limit' => 1]);
        $student = User::factory()->student()->inProgress()->create();
        $existing = AiChatConversation::factory()->for($student)->create();
        AiChatMessage::factory()->for($existing, 'conversation')->create();

        $response = $this->actingAs($student)->post('/ai-chat/conversations', [
            'source' => 'full-screen',
            'message' => '上限到達後の質問です。',
        ]);

        $created = $student->aiChatConversations()->whereKeyNot($existing->id)->sole();
        $response
            ->assertRedirect("/ai-chat/conversations/{$created->id}")
            ->assertSessionHas('error', '本日の利用上限に達しました。明日 0:00 以降に再度ご利用ください。');
        $this->assertSame(0, $created->messages()->count());
    }

    public function test_full_screen_initial_message_keeps_api_key_unavailable_notice_in_conversation(): void
    {
        config(['ai-chat.gemini.api_key' => null]);
        $student = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($student)->post('/ai-chat/conversations', [
            'source' => 'full-screen',
            'message' => '設定前の質問です。',
        ]);

        $conversation = $student->aiChatConversations()->sole();
        $response
            ->assertRedirect("/ai-chat/conversations/{$conversation->id}")
            ->assertSessionMissing('warning');
        $this->assertDatabaseHas('ai_chat_messages', [
            'ai_chat_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'status' => 'error',
            'content' => 'AI 相談は現在利用できません。システム設定が完了するまでお待ちください。',
            'error_detail' => 'Gemini API key is not configured.',
        ]);

        $this->actingAs($student)
            ->get("/ai-chat/conversations/{$conversation->id}")
            ->assertOk()
            ->assertSee('AI 相談は現在利用できません。システム設定が完了するまでお待ちください。');
    }

    public function test_section_context_must_be_widget_source_and_viewable_by_student(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $section = $this->createViewableSectionFor($student);

        $this->actingAs($student)->postJson('/ai-chat/conversations', [
            'source' => 'full-screen',
            'section_id' => $section->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('section_id');

        $otherStudent = User::factory()->student()->inProgress()->create();
        $this->actingAs($otherStudent)->postJson('/ai-chat/conversations', [
            'source' => 'widget',
            'section_id' => $section->id,
        ])->assertForbidden();
    }

    public function test_show_returns_json_messages_for_owner_and_forbids_other_student(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $conversation = AiChatConversation::factory()->for($owner)->create();
        $message = $conversation->messages()->create([
            'role' => 'user',
            'status' => 'completed',
            'content' => '質問です。',
        ]);

        $this->actingAs($owner)->getJson("/ai-chat/conversations/{$conversation->id}")
            ->assertOk()
            ->assertJsonPath('conversation.id', $conversation->id)
            ->assertJsonPath('messages.0.id', $message->id)
            ->assertJsonPath('messages.0.role', 'user');
        $this->actingAs($other)->getJson("/ai-chat/conversations/{$conversation->id}")
            ->assertForbidden();
    }

    public function test_owner_can_rename_and_delete_then_is_redirected_to_remaining_latest(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $remaining = AiChatConversation::factory()->for($student)->create([
            'last_message_at' => now()->subMinute(),
        ]);
        $target = AiChatConversation::factory()->for($student)->create([
            'last_message_at' => now(),
        ]);

        $this->actingAs($student)->patch("/ai-chat/conversations/{$target->id}", [
            'title' => '二分探索について',
        ])->assertRedirect("/ai-chat/conversations/{$target->id}");
        $this->assertDatabaseHas('ai_chat_conversations', [
            'id' => $target->id,
            'title' => '二分探索について',
        ]);

        $this->actingAs($student)->delete("/ai-chat/conversations/{$target->id}")
            ->assertRedirect("/ai-chat/conversations/{$remaining->id}");
        $this->assertDatabaseMissing('ai_chat_conversations', ['id' => $target->id]);
    }

    public function test_owner_cannot_change_auto_title_setting_after_creation(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $conversation = AiChatConversation::factory()->for($student)->create([
            'auto_title_enabled' => true,
        ]);

        $this->actingAs($student)->patch("/ai-chat/conversations/{$conversation->id}", [
            'title' => '更新後のタイトル',
            'auto_title_enabled' => '0',
        ])->assertRedirect("/ai-chat/conversations/{$conversation->id}");

        $this->assertTrue($conversation->fresh()->auto_title_enabled);
    }

    public function test_feature_off_and_non_student_cannot_access(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        config(['ai-chat.enabled' => false]);
        $this->actingAs($student)->get('/ai-chat')->assertNotFound();

        config(['ai-chat.enabled' => true]);
        $coach = User::factory()->coach()->inProgress()->create();
        $this->actingAs($coach)->get('/ai-chat')->assertForbidden();
    }

    private function createViewableSectionFor(User $student): Section
    {
        $certification = Certification::factory()->published()->create();
        Enrollment::factory()->for($student)->for($certification)->learning()->create();
        $part = Part::factory()->for($certification)->published()->create();
        $chapter = Chapter::factory()->for($part)->published()->create();

        return Section::factory()->for($chapter)->published()->create();
    }
}
