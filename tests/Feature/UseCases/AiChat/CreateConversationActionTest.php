<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\AiChat;

use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\User;
use App\UseCases\AiChat\CreateConversationAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateConversationActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_section_conversation_is_reused_for_same_owner(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        [$section, $enrollment] = $this->createViewableSectionFor($student);

        $first = app(CreateConversationAction::class)($student, $section);
        $second = app(CreateConversationAction::class)($student, $section);

        $this->assertTrue($first->is($second));
        $this->assertTrue($first->enrollment->is($enrollment));
        $this->assertSame(1, $student->aiChatConversations()->count());
    }

    public function test_general_conversations_are_always_created_and_keep_valid_default_enrollment(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $student->update(['default_enrollment_id' => $enrollment->id]);

        $first = app(CreateConversationAction::class)($student);
        $second = app(CreateConversationAction::class)($student);

        $this->assertFalse($first->is($second));
        $this->assertTrue($first->enrollment->is($enrollment));
        $this->assertTrue($second->enrollment->is($enrollment));
    }

    /**
     * @return array{0: Section, 1: Enrollment}
     */
    private function createViewableSectionFor(User $student): array
    {
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($certification)->learning()->create();
        $part = Part::factory()->for($certification)->published()->create();
        $chapter = Chapter::factory()->for($part)->published()->create();
        $section = Section::factory()->for($chapter)->published()->create();

        return [$section, $enrollment];
    }
}
