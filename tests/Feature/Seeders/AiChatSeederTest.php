<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use App\Enums\AiChatMessageStatus;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\User;
use Database\Seeders\AiChatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiChatSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_general_section_and_error_conversations_idempotently(): void
    {
        $student = User::factory()->student()->inProgress()->create([
            'email' => 'student@certify-lms.test',
        ]);
        $certification = Certification::factory()->published()->create();
        Enrollment::factory()->for($student)->for($certification)->learning()->create();
        $part = Part::factory()->for($certification)->published()->create();
        $chapter = Chapter::factory()->for($part)->published()->create();
        Section::factory()->for($chapter)->published()->create();

        $this->seed(AiChatSeeder::class);
        $this->seed(AiChatSeeder::class);

        $this->assertSame(3, $student->aiChatConversations()->count());
        $this->assertTrue($student->aiChatConversations()->whereNotNull('section_id')->exists());
        $this->assertTrue($student->aiChatConversations()
            ->whereHas('messages', fn ($query) => $query->where('status', AiChatMessageStatus::Error->value))
            ->exists());
    }
}
