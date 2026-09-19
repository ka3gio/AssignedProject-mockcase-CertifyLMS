<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\Announcement\AdminAnnouncementNotification;
use App\UseCases\Announcement\StoreAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function payload(array $override = []): array
    {
        return array_merge([
            'title' => 'メンテナンスのお知らせ',
            'body' => 'サービス停止時間をご確認ください。',
            'target_type' => AnnouncementTargetType::AllStudents->value,
        ], $override);
    }

    public function test_admin_can_dispatch_to_all_in_progress_students_only(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $first = User::factory()->student()->inProgress()->create();
        $second = User::factory()->student()->inProgress()->create();
        $graduated = User::factory()->student()->graduated()->create();
        $invited = User::factory()->student()->invited()->create();
        $coach = User::factory()->coach()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.announcements.store'), $this->payload());

        $announcement = Announcement::query()->sole();
        $response->assertRedirect(route('admin.announcements.show', $announcement));
        $this->assertSame(2, $announcement->dispatched_count);
        $this->assertSame($admin->id, $announcement->created_by_user_id);
        Notification::assertSentTo(
            [$first, $second],
            AdminAnnouncementNotification::class,
            fn ($notification, array $channels): bool => $channels === ['database', 'mail'],
        );
        Notification::assertNotSentTo([$graduated, $invited, $coach], AdminAnnouncementNotification::class);
    }

    public function test_certification_target_includes_learning_and_passed_but_excludes_failed(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();
        $otherCertification = Certification::factory()->published()->create();
        $learning = User::factory()->student()->inProgress()->create();
        $passed = User::factory()->student()->inProgress()->create();
        $failed = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();

        Enrollment::factory()->for($learning)->for($certification)->learning()->create();
        Enrollment::factory()->for($passed)->for($certification)->passed()->create();
        Enrollment::factory()->for($failed)->for($certification)->failed()->create();
        Enrollment::factory()->for($other)->for($otherCertification)->learning()->create();

        $this->actingAs($admin)->post(route('admin.announcements.store'), $this->payload([
            'target_type' => AnnouncementTargetType::Certification->value,
            'target_certification_id' => $certification->id,
        ]))->assertRedirect();

        $announcement = Announcement::query()->sole();
        $this->assertSame(2, $announcement->dispatched_count);
        $this->assertSame($certification->id, $announcement->target_certification_id);
        Notification::assertSentTo([$learning, $passed], AdminAnnouncementNotification::class);
        Notification::assertNotSentTo([$failed, $other], AdminAnnouncementNotification::class);
    }

    public function test_user_target_dispatches_to_exactly_one_eligible_student(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $target = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();

        $this->actingAs($admin)->post(route('admin.announcements.store'), $this->payload([
            'target_type' => AnnouncementTargetType::User->value,
            'target_user_id' => $target->id,
        ]))->assertRedirect();

        $announcement = Announcement::query()->sole();
        $this->assertSame(1, $announcement->dispatched_count);
        $this->assertSame($target->id, $announcement->target_user_id);
        Notification::assertSentTo($target, AdminAnnouncementNotification::class);
        Notification::assertNotSentTo($other, AdminAnnouncementNotification::class);
    }

    public function test_target_specific_fields_are_conditionally_validated(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.announcements.store'), $this->payload([
            'target_type' => AnnouncementTargetType::Certification->value,
        ]))->assertSessionHasErrors('target_certification_id');

        $this->actingAs($admin)->post(route('admin.announcements.store'), $this->payload([
            'target_type' => AnnouncementTargetType::User->value,
        ]))->assertSessionHasErrors('target_user_id');

        $graduated = User::factory()->student()->graduated()->create();
        $this->actingAs($admin)->post(route('admin.announcements.store'), $this->payload([
            'target_type' => AnnouncementTargetType::User->value,
            'target_user_id' => $graduated->id,
        ]))->assertSessionHasErrors('target_user_id');
    }

    public function test_dispatch_with_no_recipients_is_rejected_without_history(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.announcements.store'), $this->payload())
            ->assertSessionHasErrors('target_type');

        $this->assertDatabaseCount('announcements', 0);
        Notification::assertNothingSent();
    }

    public function test_request_does_not_open_mail_transport_before_queuing_announcement(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->student()->inProgress()->create();
        config([
            'mail.default' => 'undefined-test-mailer',
            'queue.default' => 'database',
        ]);

        $announcement = $this->app->make(StoreAction::class)(
            $admin,
            $this->payload([
                'target_type' => AnnouncementTargetType::User->value,
                'target_user_id' => $target->id,
            ]),
        );

        $this->assertSame(1, $announcement->dispatched_count);
        $this->assertDatabaseHas('announcements', ['id' => $announcement->id]);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_admin_can_view_create_index_and_detail_pages(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->inProgress()->create(['name' => '対象受講生']);
        User::factory()->student()->graduated()->create(['name' => '卒業済み受講生']);
        $announcement = Announcement::factory()->for($admin, 'createdBy')->create([
            'title' => '配信履歴タイトル',
            'target_user_id' => $student->id,
            'target_type' => AnnouncementTargetType::User->value,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.announcements.create'))
            ->assertOk()
            ->assertSee('対象受講生')
            ->assertDontSee('卒業済み受講生');

        $this->actingAs($admin)
            ->get(route('admin.announcements.index'))
            ->assertOk()
            ->assertSee('配信履歴タイトル');

        $this->actingAs($admin)
            ->get(route('admin.announcements.show', $announcement))
            ->assertOk()
            ->assertSee('配信履歴タイトル');
    }

    public function test_non_admin_cannot_access_management_routes(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $this->actingAs($student)->get(route('admin.announcements.index'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.announcements.create'))->assertForbidden();
        $this->actingAs($student)
            ->post(route('admin.announcements.store'), $this->payload())
            ->assertForbidden();
    }

    public function test_edit_delete_and_resend_routes_do_not_exist(): void
    {
        $this->assertFalse(Route::has('admin.announcements.edit'));
        $this->assertFalse(Route::has('admin.announcements.update'));
        $this->assertFalse(Route::has('admin.announcements.destroy'));
    }
}
