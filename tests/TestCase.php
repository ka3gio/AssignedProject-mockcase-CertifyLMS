<?php

declare(strict_types=1);

namespace Tests;

use App\Services\Contracts\GoogleCalendarGateway;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Stripe\ApiRequestor;
use Stripe\HttpClient\CurlClient;
use Tests\Support\ExternalApi\UnexpectedGoogleCalendarGateway;
use Tests\Support\ExternalApi\UnexpectedStripeHttpClient;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        $this->app->instance(GoogleCalendarGateway::class, new UnexpectedGoogleCalendarGateway);
        ApiRequestor::setHttpClient(new UnexpectedStripeHttpClient);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(CurlClient::instance());

        parent::tearDown();
    }
}
