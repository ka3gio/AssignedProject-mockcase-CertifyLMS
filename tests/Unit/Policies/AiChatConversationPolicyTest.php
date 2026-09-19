<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\AiChatConversation;
use App\Models\User;
use App\Policies\AiChatConversationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiChatConversationPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_operate_conversation(): void
    {
        $student = User::factory()->student()->create();
        $conversation = AiChatConversation::factory()->for($student)->create();
        $policy = new AiChatConversationPolicy;

        $this->assertTrue($policy->view($student, $conversation));
        $this->assertTrue($policy->update($student, $conversation));
        $this->assertTrue($policy->delete($student, $conversation));
        $this->assertTrue($policy->sendMessage($student, $conversation));
    }

    public function test_other_student_cannot_operate_conversation(): void
    {
        $student = User::factory()->student()->create();
        $conversation = AiChatConversation::factory()->create();
        $policy = new AiChatConversationPolicy;

        $this->assertFalse($policy->view($student, $conversation));
        $this->assertFalse($policy->update($student, $conversation));
        $this->assertFalse($policy->delete($student, $conversation));
        $this->assertFalse($policy->sendMessage($student, $conversation));
    }

    public function test_non_student_is_denied_even_if_record_points_to_them(): void
    {
        $coach = User::factory()->coach()->create();
        $conversation = AiChatConversation::factory()->for($coach)->create();

        $this->assertFalse((new AiChatConversationPolicy)->view($coach, $conversation));
    }
}
