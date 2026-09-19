<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuota;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

final class HandleStripeWebhookAction
{
    /**
     * @param array<string, mixed> $event
     */
    public function __invoke(array $event): void
    {
        $type = $event['type'] ?? null;
        $object = $event['data']['object'] ?? null;

        if (! is_array($object)) {
            return;
        }

        if ($type === 'checkout.session.completed') {
            $this->completePayment($object);

            return;
        }

        if ($type === 'checkout.session.expired') {
            $this->failPayment($object);
        }
    }

    /**
     * @param array<string, mixed> $session
     */
    private function completePayment(array $session): void
    {
        if (! in_array($session['payment_status'] ?? null, ['paid', 'no_payment_required'], true)) {
            return;
        }

        DB::transaction(function () use ($session): void {
            $payment = $this->lockedPayment($session);
            if ($payment === null || $payment->status === PaymentStatus::Succeeded) {
                return;
            }

            if ($payment->status !== PaymentStatus::Pending
                || ($session['currency'] ?? null) !== $payment->currency
                || (int) ($session['amount_total'] ?? -1) !== $payment->amount) {
                return;
            }

            $paymentIntentId = $session['payment_intent'] ?? null;
            $payment->update([
                'status' => PaymentStatus::Succeeded,
                'stripe_payment_intent_id' => is_string($paymentIntentId) ? $paymentIntentId : null,
                'paid_at' => now(),
            ]);

            MeetingQuotaTransaction::create([
                'user_id' => $payment->user_id,
                'type' => MeetingQuotaTransactionType::Purchased,
                'amount' => $payment->quantity,
                'related_payment_id' => $payment->id,
                'occurred_at' => now(),
            ]);
        });
    }

    /**
     * @param array<string, mixed> $session
     */
    private function failPayment(array $session): void
    {
        DB::transaction(function () use ($session): void {
            $payment = $this->lockedPayment($session);

            if ($payment?->status === PaymentStatus::Pending) {
                $payment->update(['status' => PaymentStatus::Failed]);
            }
        });
    }

    /**
     * @param array<string, mixed> $session
     */
    private function lockedPayment(array $session): ?Payment
    {
        $paymentId = $session['metadata']['payment_id'] ?? null;
        $sessionId = $session['id'] ?? null;

        if (! is_string($paymentId) || ! is_string($sessionId)) {
            return null;
        }

        $payment = Payment::query()->lockForUpdate()->find($paymentId);
        if ($payment === null) {
            return null;
        }

        if ($payment->stripe_checkout_session_id === null) {
            $payment->update(['stripe_checkout_session_id' => $sessionId]);
        } elseif (! hash_equals($payment->stripe_checkout_session_id, $sessionId)) {
            return null;
        }

        return $payment;
    }
}
