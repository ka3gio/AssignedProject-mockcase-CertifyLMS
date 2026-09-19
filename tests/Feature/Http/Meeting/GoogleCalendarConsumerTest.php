<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Meeting;

use App\Enums\MeetingStatus;
use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\Enrollment;
use App\Models\GoogleCalendarCredential;
use App\Models\Meeting;
use App\Models\User;
use App\Services\Contracts\GoogleCalendarGateway;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\ExternalApiTestCase;

#[Group('external-api')]
final class GoogleCalendarConsumerTest extends ExternalApiTestCase
{
    use RefreshDatabase;

    public function test_booking_uses_calendar_operations_and_persists_the_created_event(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach, $admin);
        CoachAvailability::factory()->forCoach($coach)->onDay(Carbon::MONDAY)->timeRange('09:00:00', '12:00:00')->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        GoogleCalendarCredential::factory()->for($coach, 'coach')->create([
            'calendar_id' => 'coach@example.com',
        ]);
        $gateway = Mockery::mock(GoogleCalendarGateway::class);
        $gateway->shouldReceive('busyPeriods')->once()->andReturn([]);
        $gateway->shouldReceive('createMeetingEvent')->once()->andReturn('event-123');
        $this->app->instance(GoogleCalendarGateway::class, $gateway);
        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0);

        $this->actingAs($student)->post(route('meetings.store', $enrollment), [
            'scheduled_at' => $scheduledAt->format('Y-m-d\TH:i:s'),
            'topic' => '学習計画の相談',
        ])->assertRedirect();

        $meeting = Meeting::query()->where('student_id', $student->id)->sole();
        $this->assertSame('event-123', $meeting->google_calendar_event_id);
        $this->assertSame('coach@example.com', $meeting->google_calendar_id);
    }

    public function test_cancellation_uses_the_delete_operation_and_clears_event_metadata(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        GoogleCalendarCredential::factory()->for($coach, 'coach')->create([
            'calendar_id' => 'coach@example.com',
        ]);
        $meeting = Meeting::factory()
            ->reserved()
            ->forCoach($coach)
            ->forEnrollment($enrollment)
            ->create([
                'scheduled_at' => now()->addDays(3)->startOfHour(),
                'google_calendar_event_id' => 'event-123',
                'google_calendar_id' => 'coach@example.com',
            ]);
        $gateway = Mockery::mock(GoogleCalendarGateway::class);
        $gateway->shouldReceive('deleteEvent')->once();
        $this->app->instance(GoogleCalendarGateway::class, $gateway);

        $this->actingAs($student)
            ->post(route('meetings.cancel', $meeting))
            ->assertRedirect();

        $meeting->refresh();
        $this->assertSame(MeetingStatus::Canceled, $meeting->status);
        $this->assertNull($meeting->google_calendar_event_id);
        $this->assertNull($meeting->google_calendar_id);
    }

    private function attachCoach(Certification $certification, User $coach, User $admin): void
    {
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
    }
}
