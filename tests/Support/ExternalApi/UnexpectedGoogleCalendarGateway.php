<?php

declare(strict_types=1);

namespace Tests\Support\ExternalApi;

use App\Models\GoogleCalendarCredential;
use App\Models\Meeting;
use App\Services\Contracts\GoogleCalendarGateway;
use Carbon\CarbonInterface;
use RuntimeException;

final class UnexpectedGoogleCalendarGateway implements GoogleCalendarGateway
{
    public function authorizationUrl(string $state): string
    {
        $this->fail('authorizationUrl');
    }

    public function exchangeCode(string $code): array
    {
        $this->fail('exchangeCode');
    }

    public function primaryCalendarId(array $token): string
    {
        $this->fail('primaryCalendarId');
    }

    public function busyPeriods(
        GoogleCalendarCredential $credential,
        CarbonInterface $start,
        CarbonInterface $end,
    ): array {
        $this->fail('busyPeriods');
    }

    public function createMeetingEvent(GoogleCalendarCredential $credential, Meeting $meeting): string
    {
        $this->fail('createMeetingEvent');
    }

    public function deleteEvent(GoogleCalendarCredential $credential, string $eventId): void
    {
        $this->fail('deleteEvent');
    }

    public function revoke(GoogleCalendarCredential $credential): void
    {
        $this->fail('revoke');
    }

    private function fail(string $operation): never
    {
        throw new RuntimeException("Google Calendar の {$operation} がモックされていません。");
    }
}
