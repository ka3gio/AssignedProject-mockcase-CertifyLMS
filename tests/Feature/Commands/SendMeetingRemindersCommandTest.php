<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\MeetingStatus;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class SendMeetingRemindersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_both_parties_once_for_every_meeting_on_the_following_day(): void
    {
        $this->travelTo('2026-09-14 20:00:00');
        $morningMeeting = $this->meetingAt(Carbon::parse('2026-09-15 09:00:00'));
        $eveningMeeting = $this->meetingAt(Carbon::parse('2026-09-15 21:00:00'));

        $this->artisan('notifications:send-meeting-reminders')->assertExitCode(0);
        $this->artisan('notifications:send-meeting-reminders')->assertExitCode(0);

        $this->assertSame(1, $morningMeeting->student->notifications()->count());
        $this->assertSame(1, $morningMeeting->coach->notifications()->count());
        $this->assertSame(1, $eveningMeeting->student->notifications()->count());
        $this->assertSame(1, $eveningMeeting->coach->notifications()->count());
        $this->assertSame(4, Mail::mailer()->getSymfonyTransport()->messages()->count());
        $this->assertDatabaseCount('notifications', 4);
        $this->assertSame('meeting_reminder', $morningMeeting->student->notifications()->first()->data['notification_type']);
    }

    public function test_skips_meetings_outside_the_following_calendar_day(): void
    {
        $this->travelTo('2026-09-14 20:00:00');
        $todayMeeting = $this->meetingAt(Carbon::parse('2026-09-14 23:00:00'));
        $dayAfterTomorrowMeeting = $this->meetingAt(Carbon::parse('2026-09-16 09:00:00'));

        $this->artisan('notifications:send-meeting-reminders')->assertExitCode(0);

        $this->assertSame(0, $todayMeeting->student->notifications()->count());
        $this->assertSame(0, $todayMeeting->coach->notifications()->count());
        $this->assertSame(0, $dayAfterTomorrowMeeting->student->notifications()->count());
        $this->assertSame(0, $dayAfterTomorrowMeeting->coach->notifications()->count());
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_skips_canceled_and_completed_meetings(): void
    {
        $this->travelTo('2026-09-14 20:00:00');
        $this->meetingAt(Carbon::parse('2026-09-15 09:00:00'), MeetingStatus::Canceled);
        $this->meetingAt(Carbon::parse('2026-09-15 10:00:00'), MeetingStatus::Completed);

        $this->artisan('notifications:send-meeting-reminders')->assertExitCode(0);

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_only_in_progress_recipients_receive_reminders(): void
    {
        $this->travelTo('2026-09-14 20:00:00');
        $meeting = $this->meetingAt(Carbon::parse('2026-09-15 09:00:00'));
        $meeting->student->update(['status' => 'graduated']);

        $this->artisan('notifications:send-meeting-reminders')->assertExitCode(0);

        $this->assertSame(0, $meeting->student->notifications()->count());
        $this->assertSame(1, $meeting->coach->notifications()->count());
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_does_not_retroactively_notify_a_meeting_booked_after_its_reminder_time(): void
    {
        $this->travelTo('2026-09-14 20:01:00');
        $meeting = $this->meetingAt(Carbon::parse('2026-09-15 09:00:00'));

        $this->artisan('notifications:send-meeting-reminders')->assertExitCode(0);

        $this->assertSame(0, $meeting->student->notifications()->count());
        $this->assertSame(0, $meeting->coach->notifications()->count());
    }

    public function test_does_not_send_after_the_twenty_oclock_execution_minute(): void
    {
        $this->travelTo('2026-09-14 19:59:00');
        $meeting = $this->meetingAt(Carbon::parse('2026-09-15 09:00:00'));
        $this->travelTo('2026-09-14 20:01:00');

        $this->artisan('notifications:send-meeting-reminders')->assertExitCode(0);

        $this->assertSame(0, $meeting->student->notifications()->count());
        $this->assertSame(0, $meeting->coach->notifications()->count());
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_scheduler_runs_the_reminder_once_daily_at_twenty(): void
    {
        $reminderEvents = collect($this->app->make(Schedule::class)->events())
            ->filter(fn ($event): bool => str_contains($event->command, 'notifications:send-meeting-reminders'));

        $this->assertCount(1, $reminderEvents);
        $this->assertSame('0 20 * * *', $reminderEvents->sole()->expression);
    }

    private function meetingAt(Carbon $scheduledAt, MeetingStatus $status = MeetingStatus::Reserved): Meeting
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $enrollment = Enrollment::factory()->learning()->for($student, 'user')->create();

        return Meeting::factory()
            ->forCoach($coach)
            ->forEnrollment($enrollment)
            ->create([
                'scheduled_at' => $scheduledAt,
                'status' => $status->value,
            ]);
    }
}
