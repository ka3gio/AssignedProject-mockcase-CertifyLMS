<?php

declare(strict_types=1);

namespace Tests\Support;

final class StripeWebhookSignature
{
    public static function generate(string $payload, string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return "t={$timestamp},v1={$signature}";
    }
}
