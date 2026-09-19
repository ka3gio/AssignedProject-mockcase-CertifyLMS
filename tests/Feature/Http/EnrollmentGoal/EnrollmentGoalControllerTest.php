<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentGoal;

use App\Models\CertificationCoachAssignment;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 個人学習目標 CRUD・閲覧範囲・親 Enrollment 連動削除の HTTP 統合テスト。
 */
class EnrollmentGoalControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_goal_with_nullable_optional_fields(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        $this->actingAs($student)
            ->post(route('enrollments.goals.store', $enrollment), [
                'title' => '過去問を一周する',
                'description' => null,
                'target_date' => null,
            ])
            ->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertDatabaseHas('enrollment_goals', [
            'enrollment_id' => $enrollment->id,
            'title' => '過去問を一周する',
            'description' => null,
            'target_date' => null,
        ]);
    }

    public function test_store_validates_view_field_limits(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        $this->actingAs($student)
            ->post(route('enrollments.goals.store', $enrollment), [
                'title' => str_repeat('あ', 101),
                'description' => str_repeat('い', 1001),
                'target_date' => 'not-a-date',
            ])
            ->assertSessionHasErrors(['title', 'description', 'target_date']);
    }

    public function test_owner_can_edit_and_update_achieved_goal(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->achieved()->create();

        $this->actingAs($student)
            ->get(route('enrollment-goals.edit', $goal))
            ->assertOk()
            ->assertViewIs('enrollment-goal.edit');

        $this->actingAs($student)
            ->patch(route('enrollment-goals.update', $goal), [
                'title' => '更新後の目標',
                'description' => '更新後の詳細',
                'target_date' => '2026-12-31',
            ])
            ->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertDatabaseHas('enrollment_goals', [
            'id' => $goal->id,
            'title' => '更新後の目標',
            'description' => '更新後の詳細',
            'target_date' => '2026-12-31',
        ]);
    }

    public function test_owner_can_physically_delete_goal(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->create();

        $this->actingAs($student)
            ->delete(route('enrollment-goals.destroy', $goal))
            ->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertDatabaseMissing('enrollment_goals', ['id' => $goal->id]);
    }

    public function test_owner_can_mark_and_unmark_goal_as_achieved(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->create();

        $this->actingAs($student)
            ->post(route('enrollment-goals.markAchieved', $goal))
            ->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertNotNull($goal->fresh()->achieved_at);

        $this->actingAs($student)
            ->delete(route('enrollment-goals.unmarkAchieved', $goal))
            ->assertRedirect(route('enrollments.show', $enrollment));

        $this->assertNull($goal->fresh()->achieved_at);
    }

    public function test_owner_sees_unachieved_goals_before_achieved_goals(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        EnrollmentGoal::factory()->forEnrollment($enrollment)->achieved()->create([
            'title' => '達成済みの目標',
            'target_date' => now()->subDay(),
        ]);
        EnrollmentGoal::factory()->forEnrollment($enrollment)->create([
            'title' => '未達成の目標',
            'target_date' => now()->addMonth(),
        ]);

        $this->actingAs($student)
            ->get(route('enrollments.show', $enrollment))
            ->assertOk()
            ->assertSeeInOrder(['未達成の目標', '達成済みの目標']);
    }

    public function test_assigned_coach_and_admin_can_view_goals_without_operations(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->create([
            'title' => '閲覧専用の目標',
        ]);
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $enrollment->certification_id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        foreach ([$coach, $admin] as $viewer) {
            $this->actingAs($viewer)
                ->get(route('enrollments.show', $enrollment))
                ->assertOk()
                ->assertSee('閲覧専用の目標')
                ->assertDontSee(route('enrollment-goals.edit', $goal), false)
                ->assertDontSee(route('enrollment-goals.markAchieved', $goal), false);
        }
    }

    public function test_other_student_cannot_change_goal(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $otherStudent = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($owner)->learning()->create();
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->create();

        $this->actingAs($otherStudent)
            ->post(route('enrollment-goals.markAchieved', $goal))
            ->assertForbidden();

        $this->assertNull($goal->fresh()->achieved_at);
    }

    public function test_graduated_student_cannot_reach_enrollment_or_goal_operations(): void
    {
        $student = User::factory()->student()->graduated()->create();
        $enrollment = Enrollment::factory()->for($student)->passed()->create();
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->create();

        $this->actingAs($student)
            ->get(route('enrollments.show', $enrollment))
            ->assertForbidden();

        $this->actingAs($student)
            ->post(route('enrollment-goals.markAchieved', $goal))
            ->assertForbidden();
    }

    public function test_deleting_parent_enrollment_physically_deletes_goals(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->create();

        $this->actingAs($student)
            ->delete(route('enrollments.destroy', $enrollment))
            ->assertRedirect(route('enrollments.index'));

        $this->assertSoftDeleted('enrollments', ['id' => $enrollment->id]);
        $this->assertDatabaseMissing('enrollment_goals', ['id' => $goal->id]);
    }
}
