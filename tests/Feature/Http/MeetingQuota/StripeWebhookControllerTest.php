<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingQuota;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use Tests\ExternalApiTestCase;
use Tests\Support\StripeWebhookSignature;

#[Group('external-api')]
final class StripeWebhookControllerTest extends ExternalApiTestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'whsec_test-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.stripe.webhook_secret' => self::WEBHOOK_SECRET]);
    }

    public function test_valid_signature_completes_the_payment_and_grants_meeting_quota(): void
    {
        $payment = $this->pendingPayment();
        $payload = $this->completedEventPayload($payment, 'evt_valid');

        $this->postWebhook(
            $payload,
            StripeWebhookSignature::generate($payload, self::WEBHOOK_SECRET),
        )->assertOk();

        $payment->refresh();
        $this->assertSame(PaymentStatus::Succeeded, $payment->status);
        $this->assertSame('pi_test_123', $payment->stripe_payment_intent_id);
        $this->assertNotNull($payment->paid_at);
        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $payment->user_id,
            'type' => MeetingQuotaTransactionType::Purchased->value,
            'amount' => $payment->quantity,
            'related_payment_id' => $payment->id,
        ]);
    }

    public function test_invalid_signature_is_rejected_without_changing_the_payment(): void
    {
        $payment = $this->pendingPayment();
        $payload = $this->completedEventPayload($payment, 'evt_invalid_signature');

        $this->postWebhook(
            $payload,
            StripeWebhookSignature::generate($payload, 'whsec_wrong-secret'),
        )->assertBadRequest();

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    public function test_missing_signature_is_rejected_without_changing_the_payment(): void
    {
        $payment = $this->pendingPayment();
        $payload = $this->completedEventPayload($payment, 'evt_missing_signature');

        $this->postWebhook($payload)->assertBadRequest();

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    public function test_duplicate_delivery_grants_quota_only_once(): void
    {
        $payment = $this->pendingPayment();
        $payload = $this->completedEventPayload($payment, 'evt_duplicate');
        $signature = StripeWebhookSignature::generate($payload, self::WEBHOOK_SECRET);

        $this->postWebhook($payload, $signature)->assertOk();
        $this->postWebhook($payload, $signature)->assertOk();

        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
        $this->assertSame(
            1,
            MeetingQuotaTransaction::query()
                ->where('related_payment_id', $payment->id)
                ->where('type', MeetingQuotaTransactionType::Purchased->value)
                ->count(),
        );
    }

    private function pendingPayment(): Payment
    {
        $student = User::factory()->student()->inProgress()->create();
        $pack = MeetingPack::factory()->published()->withCount(3)->withPrice(9000)->create();

        return Payment::factory()
            ->pending()
            ->for($student)
            ->for($pack, 'meetingPack')
            ->create([
                'amount' => 9000,
                'quantity' => 3,
                'currency' => 'jpy',
                'stripe_checkout_session_id' => 'cs_test_123',
            ]);
    }

    private function completedEventPayload(Payment $payment, string $eventId): string
    {
        return json_encode([
            'id' => $eventId,
            'object' => 'event',
            'api_version' => '2026-08-27.basil',
            'created' => time(),
            'livemode' => false,
            'pending_webhooks' => 1,
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_123',
                    'object' => 'checkout.session',
                    'amount_total' => 9000,
                    'currency' => 'jpy',
                    'payment_status' => 'paid',
                    'payment_intent' => 'pi_test_123',
                    'metadata' => [
                        'payment_id' => $payment->id,
                        'user_id' => $payment->user_id,
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    private function postWebhook(string $payload, ?string $signature = null): TestResponse
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if ($signature !== null) {
            $server['HTTP_STRIPE_SIGNATURE'] = $signature;
        }

        return $this->call(
            'POST',
            route('webhooks.stripe'),
            server: $server,
            content: $payload,
        );
    }
}
