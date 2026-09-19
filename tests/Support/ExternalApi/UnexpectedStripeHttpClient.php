<?php

declare(strict_types=1);

namespace Tests\Support\ExternalApi;

use RuntimeException;
use Stripe\HttpClient\ClientInterface;

final class UnexpectedStripeHttpClient implements ClientInterface
{
    public function request(
        $method,
        $absUrl,
        $headers,
        $params,
        $hasFile,
        $apiMode = 'v1',
        $maxNetworkRetries = null,
    ): never {
        throw new RuntimeException("Stripe の {$method} {$absUrl} がモックされていません。");
    }
}
