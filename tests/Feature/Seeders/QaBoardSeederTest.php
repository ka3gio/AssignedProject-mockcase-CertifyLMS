<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\User;
use Database\Seeders\QaBoardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QaBoardSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_mixed_threads_replies_and_unpublished_moderation_data(): void
    {
        $student = User::factory()->student()->create(['email' => 'student@certify-lms.test']);
        User::factory()->student()->create(['email' => 'student-noquota@certify-lms.test']);
        $published = Certification::factory()->published()->create();
        $draft = Certification::factory()->draft()->create();

        $this->seed(QaBoardSeeder::class);

        $this->assertDatabaseCount('qa_threads', 4);
        $this->assertDatabaseCount('qa_replies', 4);
        $this->assertDatabaseHas('qa_threads', [
            'certification_id' => $published->id,
            'user_id' => $student->id,
            'status' => QaThreadStatus::Unresolved->value,
        ]);
        $this->assertDatabaseHas('qa_threads', [
            'certification_id' => $published->id,
            'status' => QaThreadStatus::Resolved->value,
        ]);
        $this->assertDatabaseHas('qa_threads', [
            'certification_id' => $draft->id,
        ]);
    }
}
