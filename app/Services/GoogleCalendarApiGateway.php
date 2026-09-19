<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GoogleCalendarCredential;
use App\Models\Meeting;
use App\Services\Contracts\GoogleCalendarGateway;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Google\Client;
use Google\Service\Calendar as GoogleCalendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Google\Service\Calendar\FreeBusyRequest;
use Google\Service\Calendar\FreeBusyRequestItem;
use Google\Service\Exception as GoogleServiceException;
use Psr\Http\Client\ClientInterface;
use RuntimeException;

final class GoogleCalendarApiGateway implements GoogleCalendarGateway
{
    public function __construct(private readonly ?ClientInterface $httpClient = null) {}

    public function authorizationUrl(string $state): string
    {
        $client = $this->newClient();
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setIncludeGrantedScopes(true);
        $client->setState($state);

        return $client->createAuthUrl();
    }

    public function exchangeCode(string $code): array
    {
        $token = $this->newClient()->fetchAccessTokenWithAuthCode($code);
        if (isset($token['error'])) {
            throw new RuntimeException((string) ($token['error_description'] ?? $token['error']));
        }

        return $token;
    }

    public function primaryCalendarId(array $token): string
    {
        $client = $this->newClient();
        $client->setAccessToken($token);
        $calendar = (new GoogleCalendar($client))->calendars->get('primary');

        return (string) $calendar->getId();
    }

    public function busyPeriods(
        GoogleCalendarCredential $credential,
        CarbonInterface $start,
        CarbonInterface $end,
    ): array {
        $service = new GoogleCalendar($this->authorizedClient($credential));
        $request = new FreeBusyRequest([
            'timeMin' => $start->toRfc3339String(),
            'timeMax' => $end->toRfc3339String(),
            'timeZone' => config('app.timezone', 'Asia/Tokyo'),
            'items' => [new FreeBusyRequestItem(['id' => $credential->calendar_id])],
        ]);
        $calendars = $service->freebusy->query($request)->getCalendars();
        $calendar = $calendars[$credential->calendar_id] ?? null;

        if ($calendar === null || ($calendar->getErrors() ?? []) !== []) {
            throw new RuntimeException('Google カレンダーの空き状況を取得できませんでした。');
        }

        return array_map(
            static fn ($period): array => [
                'start' => Carbon::parse($period->getStart()),
                'end' => Carbon::parse($period->getEnd()),
            ],
            $calendar->getBusy() ?? [],
        );
    }

    public function createMeetingEvent(GoogleCalendarCredential $credential, Meeting $meeting): string
    {
        $event = (new GoogleCalendar($this->authorizedClient($credential)))->events->insert(
            $credential->calendar_id,
            $this->makeMeetingEvent($meeting),
            ['sendUpdates' => 'none'],
        );

        return (string) $event->getId();
    }

    public function deleteEvent(GoogleCalendarCredential $credential, string $eventId): void
    {
        try {
            (new GoogleCalendar($this->authorizedClient($credential)))->events->delete(
                $credential->calendar_id,
                $eventId,
                ['sendUpdates' => 'none'],
            );
        } catch (GoogleServiceException $exception) {
            if ($exception->getCode() !== 404) {
                throw $exception;
            }
        }
    }

    public function revoke(GoogleCalendarCredential $credential): void
    {
        $client = $this->newClient();
        $client->revokeToken($credential->refresh_token ?: $credential->access_token);
    }

    private function makeMeetingEvent(Meeting $meeting): Event
    {
        $timezone = (string) config('app.timezone', 'Asia/Tokyo');
        $start = $meeting->scheduled_at->copy()->timezone($timezone);
        $end = $start->copy()->addHour();
        $description = $meeting->meeting_url_snapshot
            ? '面談URL: '.$meeting->meeting_url_snapshot
            : 'Certify LMS の面談予定です。';

        return new Event([
            'summary' => 'Certify LMS 面談',
            'description' => $description,
            'visibility' => 'private',
            'attendees' => [],
            'start' => new EventDateTime([
                'dateTime' => $start->toRfc3339String(),
                'timeZone' => $timezone,
            ]),
            'end' => new EventDateTime([
                'dateTime' => $end->toRfc3339String(),
                'timeZone' => $timezone,
            ]),
        ]);
    }

    private function newClient(): Client
    {
        $config = config('services.google_calendar');
        if (! is_array($config)
            || empty($config['client_id'])
            || empty($config['client_secret'])
            || empty($config['redirect_uri'])) {
            throw new RuntimeException('Google Calendar OAuth の設定が不足しています。');
        }

        $client = new Client;
        $client->setClientId((string) $config['client_id']);
        $client->setClientSecret((string) $config['client_secret']);
        $client->setRedirectUri((string) $config['redirect_uri']);
        $client->addScope(GoogleCalendar::CALENDAR_EVENTS_OWNED);
        $client->addScope(GoogleCalendar::CALENDAR_FREEBUSY);
        $client->addScope(GoogleCalendar::CALENDAR_CALENDARS_READONLY);
        if ($this->httpClient !== null) {
            $client->setHttpClient($this->httpClient);
        }

        return $client;
    }

    private function authorizedClient(GoogleCalendarCredential $credential): Client
    {
        $client = $this->newClient();
        $expiresIn = max(0, now()->diffInSeconds($credential->token_expires_at, false));
        $client->setAccessToken([
            'access_token' => $credential->access_token,
            'refresh_token' => $credential->refresh_token,
            'expires_in' => $expiresIn,
            'created' => now()->timestamp,
        ]);

        if ($credential->token_expires_at?->isFuture()) {
            return $client;
        }

        if (blank($credential->refresh_token)) {
            throw new RuntimeException('Google Calendar の認証期限が切れています。');
        }

        $token = $client->fetchAccessTokenWithRefreshToken($credential->refresh_token);
        if (isset($token['error'])) {
            throw new RuntimeException((string) ($token['error_description'] ?? $token['error']));
        }

        $credential->update([
            'access_token' => (string) $token['access_token'],
            'refresh_token' => $token['refresh_token'] ?? $credential->refresh_token,
            'token_expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 3600)),
        ]);
        $client->setAccessToken(array_merge($token, [
            'refresh_token' => $token['refresh_token'] ?? $credential->refresh_token,
        ]));

        return $client;
    }
}
