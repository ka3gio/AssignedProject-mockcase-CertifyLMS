<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GoogleCalendarCredential;
use App\Models\Meeting;
use Illuminate\Support\Facades\Log;
use Throwable;

final class GoogleCalendarSyncService
{
    public function __construct(private readonly GoogleCalendarApiGateway $googleCalendar) {}

    public function createForMeeting(Meeting $meeting): void
    {
        if ($meeting->google_calendar_event_id !== null) {
            return;
        }

        $credential = $this->currentCredential($meeting);
        if ($credential === null) {
            return;
        }

        try {
            $eventId = $this->googleCalendar->createMeetingEvent($credential, $meeting);
            $meeting->update([
                'google_calendar_event_id' => $eventId,
                'google_calendar_id' => $credential->calendar_id,
            ]);
        } catch (Throwable $exception) {
            Log::warning('面談の Google カレンダー予定登録に失敗しました。', [
                'meeting_id' => $meeting->id,
                'coach_id' => $meeting->coach_id,
                'exception' => $exception,
            ]);
        }
    }

    public function deleteForMeeting(Meeting $meeting): void
    {
        if ($meeting->google_calendar_event_id === null || $meeting->google_calendar_id === null) {
            return;
        }

        $credential = $this->currentCredential($meeting);
        if ($credential === null || $credential->calendar_id !== $meeting->google_calendar_id) {
            return;
        }

        try {
            $this->googleCalendar->deleteEvent($credential, $meeting->google_calendar_event_id);
            $meeting->update([
                'google_calendar_event_id' => null,
                'google_calendar_id' => null,
            ]);
        } catch (Throwable $exception) {
            Log::warning('面談の Google カレンダー予定削除に失敗しました。', [
                'meeting_id' => $meeting->id,
                'coach_id' => $meeting->coach_id,
                'exception' => $exception,
            ]);
        }
    }

    private function currentCredential(Meeting $meeting): ?GoogleCalendarCredential
    {
        return GoogleCalendarCredential::query()
            ->where('coach_id', $meeting->coach_id)
            ->first();
    }
}
