<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\GoogleCalendarCredential;
use App\Models\Meeting;
use App\Services\GoogleCalendarApiGateway;
use ArrayObject;
use Carbon\Carbon;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Tests\ExternalApiTestCase;

#[Group('external-api')]
final class GoogleCalendarApiGatewayTest extends ExternalApiTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google_calendar.client_id' => 'google-client-id',
            'services.google_calendar.client_secret' => 'google-client-secret',
            'services.google_calendar.redirect_uri' => 'https://lms.test/settings/google-calendar/callback',
        ]);
    }

    public function test_authorization_url_contains_oauth_settings_and_state(): void
    {
        [$gateway] = $this->gateway([]);

        $url = $gateway->authorizationUrl('csrf-state-value');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame('google-client-id', $query['client_id']);
        $this->assertSame('https://lms.test/settings/google-calendar/callback', $query['redirect_uri']);
        $this->assertSame('csrf-state-value', $query['state']);
        $this->assertSame('offline', $query['access_type']);
    }

    public function test_exchange_code_returns_the_token_from_the_oauth_endpoint(): void
    {
        [$gateway, $history] = $this->gateway([
            $this->jsonResponse([
                'access_token' => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ]),
        ]);

        $token = $gateway->exchangeCode('authorization-code');

        $this->assertSame('new-access-token', $token['access_token']);
        $this->assertCount(1, $history);
        $this->assertSame('https://oauth2.googleapis.com/token', (string) $history[0]->getUri());
        $this->assertStringContainsString('code=authorization-code', (string) $history[0]->getBody());
    }

    public function test_primary_calendar_id_is_read_from_the_calendar_api(): void
    {
        [$gateway] = $this->gateway([
            $this->jsonResponse(['kind' => 'calendar#calendar', 'id' => 'coach@example.com']),
        ]);

        $calendarId = $gateway->primaryCalendarId([
            'access_token' => 'access-token',
            'expires_in' => 3600,
            'created' => now()->timestamp,
        ]);

        $this->assertSame('coach@example.com', $calendarId);
    }

    public function test_busy_periods_returns_the_external_calendar_periods(): void
    {
        $credential = GoogleCalendarCredential::factory()->create([
            'calendar_id' => 'coach@example.com',
            'token_expires_at' => now()->addHour(),
        ]);
        [$gateway, $history] = $this->gateway([
            $this->jsonResponse([
                'kind' => 'calendar#freeBusy',
                'calendars' => [
                    'coach@example.com' => [
                        'busy' => [[
                            'start' => '2026-09-21T10:00:00+09:00',
                            'end' => '2026-09-21T11:00:00+09:00',
                        ]],
                    ],
                ],
            ]),
        ]);

        $periods = $gateway->busyPeriods(
            $credential,
            Carbon::parse('2026-09-21 00:00:00'),
            Carbon::parse('2026-09-22 00:00:00'),
        );

        $this->assertSame('2026-09-21T10:00:00+09:00', $periods[0]['start']->toIso8601String());
        $this->assertSame('2026-09-21T11:00:00+09:00', $periods[0]['end']->toIso8601String());
        $this->assertSame('/calendar/v3/freeBusy', $history[0]->getUri()->getPath());
    }

    public function test_create_meeting_event_returns_the_external_event_id(): void
    {
        $credential = GoogleCalendarCredential::factory()->create([
            'calendar_id' => 'coach@example.com',
            'token_expires_at' => now()->addHour(),
        ]);
        $meeting = Meeting::factory()->reserved()->create([
            'scheduled_at' => Carbon::parse('2026-09-21 10:00:00'),
            'meeting_url_snapshot' => 'https://meet.example.com/room',
        ]);
        [$gateway, $history] = $this->gateway([
            $this->jsonResponse(['kind' => 'calendar#event', 'id' => 'event-123']),
        ]);

        $eventId = $gateway->createMeetingEvent($credential, $meeting);

        $this->assertSame('event-123', $eventId);
        $body = json_decode((string) $history[0]->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('Certify LMS 面談', $body['summary']);
        $this->assertSame('2026-09-21T10:00:00+09:00', $body['start']['dateTime']);
        $this->assertSame('2026-09-21T11:00:00+09:00', $body['end']['dateTime']);
    }

    public function test_delete_event_sends_a_delete_request(): void
    {
        $credential = GoogleCalendarCredential::factory()->create([
            'calendar_id' => 'coach@example.com',
            'token_expires_at' => now()->addHour(),
        ]);
        [$gateway, $history] = $this->gateway([new Response(204)]);

        $gateway->deleteEvent($credential, 'event-123');

        $this->assertSame('DELETE', $history[0]->getMethod());
        $this->assertSame('/calendar/v3/calendars/coach%40example.com/events/event-123', $history[0]->getUri()->getPath());
    }

    public function test_delete_event_treats_an_already_deleted_event_as_success(): void
    {
        $credential = GoogleCalendarCredential::factory()->create([
            'token_expires_at' => now()->addHour(),
        ]);
        [$gateway] = $this->gateway([
            $this->jsonResponse([
                'error' => [
                    'code' => 404,
                    'message' => 'Not Found',
                    'status' => 'NOT_FOUND',
                ],
            ], 404),
        ]);

        $gateway->deleteEvent($credential, 'already-deleted-event');

        $this->addToAssertionCount(1);
    }

    public function test_revoke_sends_the_refresh_token_to_the_oauth_endpoint(): void
    {
        $credential = GoogleCalendarCredential::factory()->create([
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
        ]);
        [$gateway, $history] = $this->gateway([new Response(200)]);

        $gateway->revoke($credential);

        $this->assertSame('POST', $history[0]->getMethod());
        $this->assertSame('https://oauth2.googleapis.com/revoke', (string) $history[0]->getUri());
        $this->assertSame('token=refresh-token', (string) $history[0]->getBody());
    }

    public function test_expired_credential_is_refreshed_before_the_calendar_operation_is_retried(): void
    {
        $credential = GoogleCalendarCredential::factory()->create([
            'access_token' => 'expired-access-token',
            'refresh_token' => 'refresh-token',
            'calendar_id' => 'coach@example.com',
            'token_expires_at' => now()->subMinute(),
        ]);
        [$gateway, $history] = $this->gateway([
            $this->jsonResponse([
                'access_token' => 'refreshed-access-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ]),
            $this->jsonResponse([
                'kind' => 'calendar#freeBusy',
                'calendars' => ['coach@example.com' => ['busy' => []]],
            ]),
        ]);

        $periods = $gateway->busyPeriods(
            $credential,
            Carbon::parse('2026-09-21 00:00:00'),
            Carbon::parse('2026-09-22 00:00:00'),
        );

        $this->assertSame([], $periods);
        $this->assertSame('refreshed-access-token', $credential->fresh()->access_token);
        $this->assertCount(2, $history);
        $this->assertSame('https://oauth2.googleapis.com/token', (string) $history[0]->getUri());
        $this->assertSame('/calendar/v3/freeBusy', $history[1]->getUri()->getPath());
    }

    public function test_refresh_failure_stops_before_calling_the_calendar_operation(): void
    {
        $credential = GoogleCalendarCredential::factory()->create([
            'token_expires_at' => now()->subMinute(),
            'refresh_token' => 'invalid-refresh-token',
        ]);
        [$gateway, $history] = $this->gateway([
            $this->jsonResponse([
                'error' => 'invalid_grant',
                'error_description' => 'Token has been expired or revoked.',
            ], 400),
        ]);

        try {
            $gateway->busyPeriods(
                $credential,
                Carbon::parse('2026-09-21 00:00:00'),
                Carbon::parse('2026-09-22 00:00:00'),
            );
            $this->fail('期限切れトークンの更新失敗は例外になるべきです。');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('invalid_grant', $exception->getMessage());
        }

        $this->assertCount(1, $history);
    }

    /**
     * @param list<Response> $responses
     *
     * @return array{GoogleCalendarApiGateway, ArrayObject<int, RequestInterface>}
     */
    private function gateway(array $responses): array
    {
        /** @var ArrayObject<int, RequestInterface> $history */
        $history = new ArrayObject;
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::tap(
            static function (RequestInterface $request) use ($history): void {
                $history->append($request);
            },
        ));
        $http = new HttpClient(['handler' => $stack]);

        return [new GoogleCalendarApiGateway($http), $history];
    }

    /** @param array<string, mixed> $body */
    private function jsonResponse(array $body, int $status = 200): Response
    {
        return new Response(
            $status,
            ['Content-Type' => 'application/json'],
            json_encode($body, JSON_THROW_ON_ERROR),
        );
    }
}
