<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentNote;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_coach_can_view_all_notes_and_only_sees_controls_for_own_note(): void
    {
        [$coach, $enrollment] = $this->assignedCoachAndEnrollment();
        $otherCoach = User::factory()->coach()->create();
        $own = EnrollmentNote::factory()->forEnrollment($enrollment)->authoredBy($coach)->create([
            'body' => '自分のメモ',
            'created_at' => now()->subDay(),
        ]);
        $other = EnrollmentNote::factory()->forEnrollment($enrollment)->authoredBy($otherCoach)->create([
            'body' => '他コーチのメモ',
            'created_at' => now(),
        ]);

        $this->actingAs($coach)
            ->get(route('enrollments.show', $enrollment))
            ->assertOk()
            ->assertSee('コーチメモ')
            ->assertSeeInOrder([$other->body, $own->body])
            ->assertSee(route('enrollment-notes.edit', $own), false)
            ->assertDontSee(route('enrollment-notes.edit', $other), false);
    }

    public function test_assigned_coach_can_create_update_and_delete_own_note(): void
    {
        [$coach, $enrollment] = $this->assignedCoachAndEnrollment();

        $this->actingAs($coach)
            ->post(route('enrollments.notes.store', $enrollment), ['body' => '新しいメモ'])
            ->assertRedirect(route('enrollments.show', $enrollment));

        $note = EnrollmentNote::query()->sole();
        $this->assertSame($coach->id, $note->author_user_id);

        $this->actingAs($coach)
            ->get(route('enrollment-notes.edit', $note))
            ->assertOk()
            ->assertViewIs('enrollment-note.edit');

        $this->actingAs($coach)
            ->patch(route('enrollment-notes.update', $note), ['body' => '更新後のメモ'])
            ->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertDatabaseHas('enrollment_notes', [
            'id' => $note->id,
            'body' => '更新後のメモ',
        ]);

        $this->actingAs($coach)
            ->delete(route('enrollment-notes.destroy', $note))
            ->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertDatabaseMissing('enrollment_notes', ['id' => $note->id]);
    }

    public function test_coach_cannot_update_or_delete_another_authors_note(): void
    {
        [$coach, $enrollment] = $this->assignedCoachAndEnrollment();
        $otherCoach = User::factory()->coach()->create();
        $note = EnrollmentNote::factory()->forEnrollment($enrollment)->authoredBy($otherCoach)->create();

        $this->actingAs($coach)
            ->patchJson(route('enrollment-notes.update', $note), ['body' => '不正な更新'])
            ->assertForbidden();

        $this->actingAs($coach)
            ->deleteJson(route('enrollment-notes.destroy', $note))
            ->assertForbidden();

        $this->assertDatabaseHas('enrollment_notes', ['id' => $note->id]);
    }

    public function test_admin_can_create_update_and_delete_any_note(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->create();
        $coachNote = EnrollmentNote::factory()->forEnrollment($enrollment)->authoredBy($coach)->create();

        $this->actingAs($admin)
            ->post(route('enrollments.notes.store', $enrollment), ['body' => '管理者メモ'])
            ->assertRedirect();

        $this->assertDatabaseHas('enrollment_notes', [
            'enrollment_id' => $enrollment->id,
            'author_user_id' => $admin->id,
            'body' => '管理者メモ',
        ]);

        $this->actingAs($admin)
            ->patch(route('enrollment-notes.update', $coachNote), ['body' => '管理者による修正'])
            ->assertRedirect();

        $this->actingAs($admin)
            ->delete(route('enrollment-notes.destroy', $coachNote))
            ->assertRedirect();

        $this->assertDatabaseMissing('enrollment_notes', ['id' => $coachNote->id]);
    }

    public function test_student_cannot_view_section_or_use_note_endpoints(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->for($student)->create();
        $coach = User::factory()->coach()->create();
        $note = EnrollmentNote::factory()->forEnrollment($enrollment)->authoredBy($coach)->create([
            'body' => '受講生には見えないメモ',
        ]);

        $this->actingAs($student)
            ->get(route('enrollments.show', $enrollment))
            ->assertOk()
            ->assertDontSee('コーチメモ')
            ->assertDontSee($note->body);

        $this->actingAs($student)
            ->postJson(route('enrollments.notes.store', $enrollment), ['body' => '不正な追加'])
            ->assertForbidden();
        $this->actingAs($student)
            ->get(route('enrollment-notes.edit', $note))
            ->assertForbidden();
        $this->actingAs($student)
            ->patchJson(route('enrollment-notes.update', $note), ['body' => '不正な更新'])
            ->assertForbidden();
        $this->actingAs($student)
            ->deleteJson(route('enrollment-notes.destroy', $note))
            ->assertForbidden();
    }

    public function test_unassigned_coach_cannot_use_note_endpoints(): void
    {
        $coach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->create();
        $note = EnrollmentNote::factory()->forEnrollment($enrollment)->authoredBy($coach)->create();

        $this->actingAs($coach)
            ->postJson(route('enrollments.notes.store', $enrollment), ['body' => '不正な追加'])
            ->assertForbidden();
        $this->actingAs($coach)
            ->patchJson(route('enrollment-notes.update', $note), ['body' => '不正な更新'])
            ->assertForbidden();
        $this->actingAs($coach)
            ->deleteJson(route('enrollment-notes.destroy', $note))
            ->assertForbidden();
    }

    public function test_body_is_required_and_limited_to_2000_characters(): void
    {
        [$coach, $enrollment] = $this->assignedCoachAndEnrollment();

        $this->actingAs($coach)
            ->post(route('enrollments.notes.store', $enrollment), ['body' => '   '])
            ->assertSessionHasErrors('body');

        $this->actingAs($coach)
            ->post(route('enrollments.notes.store', $enrollment), ['body' => str_repeat('あ', 2001)])
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount('enrollment_notes', 0);
    }

    public function test_notes_are_hidden_and_operations_rejected_after_parent_soft_delete(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->create();
        $note = EnrollmentNote::factory()->forEnrollment($enrollment)->authoredBy($admin)->create([
            'body' => '削除前のメモ',
        ]);
        $enrollment->delete();

        $this->actingAs($admin)
            ->get(route('enrollments.show', $enrollment))
            ->assertOk()
            ->assertDontSee('コーチメモ')
            ->assertDontSee($note->body);

        $this->actingAs($admin)
            ->get(route('enrollment-notes.edit', $note))
            ->assertForbidden();
        $this->actingAs($admin)
            ->patchJson(route('enrollment-notes.update', $note), ['body' => '不正な更新'])
            ->assertForbidden();
        $this->actingAs($admin)
            ->deleteJson(route('enrollment-notes.destroy', $note))
            ->assertForbidden();

        $this->assertDatabaseHas('enrollment_notes', ['id' => $note->id]);
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $enrollment = Enrollment::factory()->create();

        $this->post(route('enrollments.notes.store', $enrollment), ['body' => 'メモ'])
            ->assertRedirect(route('login'));
    }

    /**
     * @return array{User, Enrollment}
     */
    private function assignedCoachAndEnrollment(): array
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
        $enrollment = Enrollment::factory()->for($certification)->create();

        return [$coach, $enrollment];
    }
}
