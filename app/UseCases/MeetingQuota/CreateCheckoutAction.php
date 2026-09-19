<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuota;

use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use App\Services\StripeApiGateway;
use Throwable;

final class CreateCheckoutAction
{
    public function __construct(private readonly StripeApiGateway $stripe) {}

    /**
     * @return array{payment: Payment, url: string}
     */
    public function __invoke(User $student, MeetingPack $pack): array
    {
        $payment = Payment::create([
            'user_id' => $student->id,
            'meeting_pack_id' => $pack->id,
            'amount' => $pack->price,
            'quantity' => $pack->meeting_count,
            'currency' => 'jpy',
            'status' => PaymentStatus::Pending,
        ]);

        try {
            $session = $this->stripe->createCheckoutSession(
                $payment->load(['user', 'meetingPack']),
                route('meeting-quota.success').'?session_id={CHECKOUT_SESSION_ID}',
                route('meeting-quota.checkout.select'),
            );
        } catch (Throwable $exception) {
            $payment->update(['status' => PaymentStatus::Failed]);

            throw $exception;
        }

        $payment->update(['stripe_checkout_session_id' => $session['id']]);

        return ['payment' => $payment->fresh(), 'url' => $session['url']];
    }
}
