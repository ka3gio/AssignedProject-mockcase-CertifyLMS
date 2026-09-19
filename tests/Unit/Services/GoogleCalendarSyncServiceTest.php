<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\GoogleCalendarCredential;
use App\Models\Meeting;
use App\Services\Contracts\GoogleCalendarGateway;
use App\Services\GoogleCalendarSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\ExternalApiTestCase;

#[Group('external-api')]
final class GoogleCalendarSyncServiceTest extends ExternalApiTestCase
{
    use RefreshDatabase;

    public function test_create_for_meeting_persists_the_created_event_metadata(): void
    {
        $meeting = Meeting::factory()->reserved()->create();
        $credential = GoogleCalendarCredential::factory()->for($meeting->coach, 'coach')->create();
        $gateway = Mockery::mock(GoogleCalendarGateway::class);
        $gateway->shouldReceive('createMeetingEvent')
            ->once()
            ->withArgs(fn ($actualCredential, $actualMeeting): bool => $actualCredential->is($credential)
                && $actualMeeting->is($meeting))
            ->andReturn('event-123');

        (new GoogleCalendarSyncService($gateway))->createForMeeting($meeting);

        $this->assertSame('event-123', $meeting->fresh()->google_calendar_event_id);
        $this->assertSame($credential->calendar_id, $meeting->fresh()->google_calendar_id);
    }

    public function test_create_for_meeting_falls_back_without_event_metadata_when_google_fails(): void
    {
        $meeting = Meeting::factory()->reserved()->create();
        GoogleCalendarCredential::factory()->for($meeting->coach, 'coach')->create();
        $gateway = Mockery::mock(GoogleCalendarGateway::class);
        $gateway->shouldReceive('createMeetingEvent')->once()->andThrow(new RuntimeException('unavailable'));

        (new GoogleCalendarSyncService($gateway))->createForMeeting($meeting);

        $this->assertNull($meeting->fresh()->google_calendar_event_id);
        $this->assertNull($meeting->fresh()->google_calendar_id);
    }

    public function test_delete_for_meeting_clears_event_metadata_after_the_calendar_operation(): void
    {
        $meeting = Meeting::factory()->reserved()->create();
        $credential = GoogleCalendarCredential::factory()->for($meeting->coach, 'coach')->create();
        $meeting->update([
            'google_calendar_event_id' => 'event-123',
            'google_calendar_id' => $credential->calendar_id,
        ]);
        $gateway = Mockery::mock(GoogleCalendarGateway::class);
        $gateway->shouldReceive('deleteEvent')->once();

        (new GoogleCalendarSyncService($gateway))->deleteForMeeting($meeting);

        $this->assertNull($meeting->fresh()->google_calendar_event_id);
        $this->assertNull($meeting->fresh()->google_calendar_id);
    }

    public function test_delete_for_meeting_keeps_metadata_when_google_fails(): void
    {
        $meeting = Meeting::factory()->reserved()->create();
        $credential = GoogleCalendarCredential::factory()->for($meeting->coach, 'coach')->create();
        $meeting->update([
            'google_calendar_event_id' => 'event-123',
            'google_calendar_id' => $credential->calendar_id,
        ]);
        $gateway = Mockery::mock(GoogleCalendarGateway::class);
        $gateway->shouldReceive('deleteEvent')->once()->andThrow(new RuntimeException('unavailable'));

        (new GoogleCalendarSyncService($gateway))->deleteForMeeting($meeting);

        $this->assertSame('event-123', $meeting->fresh()->google_calendar_event_id);
        $this->assertSame($credential->calendar_id, $meeting->fresh()->google_calendar_id);
    }
}
