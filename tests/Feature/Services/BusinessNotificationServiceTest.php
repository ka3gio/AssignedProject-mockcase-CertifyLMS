<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingReminderNotification;
use App\Services\BusinessNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class BusinessNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_meeting_reminder_to_an_eligible_recipient(): void
    {
        Notification::fake();
        $this->travelTo('2026-09-14 10:00:00');
        [$meeting, $student] = $this->meetingWithStudentStatus('in_progress');

        $sent = app(BusinessNotificationService::class)
            ->notifyMeetingReminder($meeting, $student);

        $this->assertTrue($sent);
        Notification::assertSentTo(
            $student,
            MeetingReminderNotification::class,
            function (MeetingReminderNotification $notification) use ($student): bool {
                $data = $notification->toArray($student);

                return $data['notification_type'] === 'meeting_reminder'
                    && $data['title'] === '面談のリマインダー（明日）'
                    && str_contains($data['message'], '2026/09/15 10:00');
            },
        );
    }

    public function test_does_not_send_meeting_reminder_to_an_ineligible_recipient(): void
    {
        Notification::fake();
        [$meeting, $student] = $this->meetingWithStudentStatus('graduated');

        $sent = app(BusinessNotificationService::class)
            ->notifyMeetingReminder($meeting, $student);

        $this->assertFalse($sent);
        Notification::assertNothingSent();
    }

    /** @return array{Meeting, User} */
    private function meetingWithStudentStatus(string $status): array
    {
        $student = User::factory()->student()->create(['status' => $status]);
        $coach = User::factory()->coach()->inProgress()->create();
        $enrollment = Enrollment::factory()->learning()->for($student, 'user')->create();
        $meeting = Meeting::factory()
            ->forCoach($coach)
            ->forEnrollment($enrollment)
            ->create(['scheduled_at' => now()->addDay()]);

        return [$meeting, $student];
    }
}
