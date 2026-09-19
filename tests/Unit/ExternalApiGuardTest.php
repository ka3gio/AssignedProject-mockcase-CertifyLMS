<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Contracts\GoogleCalendarGateway;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Stripe\StripeClient;
use Tests\ExternalApiTestCase;

#[Group('external-api')]
final class ExternalApiGuardTest extends ExternalApiTestCase
{
    public function test_unmocked_laravel_http_request_fails(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Attempted request to [https://example.test/unmocked] without a matching fake.');

        Http::get('https://example.test/unmocked');
    }

    public function test_unmocked_google_calendar_operation_fails(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Google Calendar の authorizationUrl がモックされていません。');

        app(GoogleCalendarGateway::class)->authorizationUrl('state');
    }

    public function test_unmocked_stripe_api_request_fails(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Stripe の get https://api.stripe.com/v1/balance がモックされていません。');

        (new StripeClient('sk_test_not-a-real-key'))->balance->retrieve();
    }
}
