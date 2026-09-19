<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
final class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'meeting_pack_id' => MeetingPack::factory()->published(),
            'amount' => 3000,
            'quantity' => 1,
            'currency' => 'jpy',
            'status' => PaymentStatus::Pending,
            'stripe_checkout_session_id' => 'cs_test_'.fake()->unique()->regexify('[A-Za-z0-9]{20}'),
            'stripe_payment_intent_id' => null,
            'paid_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Pending,
            'stripe_payment_intent_id' => null,
            'paid_at' => null,
        ]);
    }

    public function succeeded(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Succeeded,
            'stripe_payment_intent_id' => 'pi_test_'.fake()->unique()->regexify('[A-Za-z0-9]{20}'),
            'paid_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Failed,
            'stripe_payment_intent_id' => null,
            'paid_at' => null,
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Refunded,
            'stripe_payment_intent_id' => 'pi_test_'.fake()->unique()->regexify('[A-Za-z0-9]{20}'),
            'paid_at' => now()->subDay(),
        ]);
    }
}
