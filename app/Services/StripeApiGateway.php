<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Payment;
use RuntimeException;
use Stripe\StripeClient;
use Stripe\Webhook;

final class StripeApiGateway
{
    public function createCheckoutSession(Payment $payment, string $successUrl, string $cancelUrl): array
    {
        $secret = config('services.stripe.secret');
        if (! is_string($secret) || $secret === '') {
            throw new RuntimeException('Stripe API キーが設定されていません。');
        }

        $session = (new StripeClient($secret))->checkout->sessions->create([
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'customer_email' => $payment->user->email,
            'client_reference_id' => $payment->id,
            'metadata' => [
                'payment_id' => $payment->id,
                'user_id' => $payment->user_id,
            ],
            'line_items' => [[
                'price_data' => [
                    'currency' => $payment->currency,
                    'unit_amount' => $payment->amount,
                    'product_data' => [
                        'name' => $payment->meetingPack->name,
                    ],
                ],
                'quantity' => 1,
            ]],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);

        return [
            'id' => (string) $session->id,
            'url' => (string) $session->url,
        ];
    }

    public function constructWebhookEvent(string $payload, string $signature): array
    {
        $secret = config('services.stripe.webhook_secret');
        if (! is_string($secret) || $secret === '') {
            throw new RuntimeException('Stripe Webhook署名シークレットが設定されていません。');
        }

        return Webhook::constructEvent($payload, $signature, $secret)->toArray();
    }
}
