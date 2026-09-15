<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\AnnouncementTargetType;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\Announcement\AdminAnnouncementNotification;
use App\Services\BusinessNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class BusinessNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_announcement_notifies_only_eligible_students_and_returns_recipient_count(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->inProgress()->create();
        $graduated = User::factory()->student()->graduated()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $announcement = Announcement::factory()->for($admin, 'createdBy')->create([
            'target_type' => AnnouncementTargetType::AllStudents->value,
            'dispatched_count' => 0,
        ]);

        $count = app(BusinessNotificationService::class)->notifyAnnouncement($announcement);

        $this->assertSame(1, $count);
        Notification::assertSentTo($student, AdminAnnouncementNotification::class);
        Notification::assertNotSentTo([$graduated, $coach], AdminAnnouncementNotification::class);
    }
}
