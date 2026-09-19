<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Models\GoogleCalendarCredential;
use App\Models\Meeting;
use Carbon\CarbonInterface;

interface GoogleCalendarGateway
{
    public function authorizationUrl(string $state): string;

    /** @return array<string, mixed> */
    public function exchangeCode(string $code): array;

    /** @param array<string, mixed> $token */
    public function primaryCalendarId(array $token): string;

    /** @return array<int, array{start: CarbonInterface, end: CarbonInterface}> */
    public function busyPeriods(
        GoogleCalendarCredential $credential,
        CarbonInterface $start,
        CarbonInterface $end,
    ): array;

    public function createMeetingEvent(GoogleCalendarCredential $credential, Meeting $meeting): string;

    public function deleteEvent(GoogleCalendarCredential $credential, string $eventId): void;

    public function revoke(GoogleCalendarCredential $credential): void;
}
