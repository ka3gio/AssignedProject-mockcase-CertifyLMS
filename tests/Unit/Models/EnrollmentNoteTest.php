<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EnrollmentNote モデルのリレーションと物理削除を検証する。
 */
class EnrollmentNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_relations_return_enrollment_and_author(): void
    {
        $enrollment = Enrollment::factory()->create();
        $author = User::factory()->coach()->create();
        $note = EnrollmentNote::factory()
            ->forEnrollment($enrollment)
            ->authoredBy($author)
            ->create();

        $this->assertTrue($note->enrollment->is($enrollment));
        $this->assertTrue($note->author->is($author));
        $this->assertTrue($enrollment->notes->first()->is($note));
        $this->assertTrue($author->authoredEnrollmentNotes->first()->is($note));
    }

    public function test_note_remains_when_parent_enrollment_is_soft_deleted(): void
    {
        $note = EnrollmentNote::factory()->create();

        $note->enrollment->delete();

        $this->assertDatabaseHas('enrollment_notes', ['id' => $note->id]);
        $this->assertTrue($note->fresh()->enrollment->trashed());
    }

    public function test_delete_physically_removes_note(): void
    {
        $note = EnrollmentNote::factory()->create();

        $note->delete();

        $this->assertDatabaseMissing('enrollment_notes', ['id' => $note->id]);
    }
}
