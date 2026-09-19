<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\MeetingPackSeeder;
use Database\Seeders\PaymentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_payment_states_and_grants_quota_only_for_succeeded_payment(): void
    {
        User::factory()->admin()->create();
        $fixed = User::factory()->student()->inProgress()->create(['email' => 'student@certify-lms.test']);
        User::factory()->student()->inProgress()->create(['email' => 'student-noquota@certify-lms.test']);
        $demo = User::factory()->student()->inProgress()->create(['email' => 'demo-student@example.test']);
        $this->seed(MeetingPackSeeder::class);

        $this->seed(PaymentSeeder::class);

        $this->assertDatabaseCount('payments', 3);
        $this->assertEqualsCanonicalizing(
            [PaymentStatus::Pending, PaymentStatus::Succeeded, PaymentStatus::Failed],
            Payment::query()->pluck('status')->all(),
        );
        $this->assertTrue(Payment::query()->where('user_id', $fixed->id)->exists());
        $this->assertTrue(Payment::query()->where('user_id', $demo->id)->exists());
        $succeeded = Payment::query()->where('status', PaymentStatus::Succeeded)->sole();
        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $succeeded->user_id,
            'type' => MeetingQuotaTransactionType::Purchased->value,
            'amount' => $succeeded->quantity,
            'related_payment_id' => $succeeded->id,
        ]);
        $this->assertDatabaseCount('meeting_quota_transactions', 1);
    }
}
