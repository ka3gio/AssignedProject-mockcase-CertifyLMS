<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\MeetingPack;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 開発用の決済状態別購入履歴を投入する。
 */
final class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        $packs = MeetingPack::query()->published()->ordered()->limit(3)->get();
        $fixed = User::query()->where('email', 'student@certify-lms.test')->first();
        $noQuota = User::query()->where('email', 'student-noquota@certify-lms.test')->first();
        $demo = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->whereNotIn('email', ['student@certify-lms.test', 'student-noquota@certify-lms.test'])
            ->orderBy('created_at')
            ->first();

        if ($packs->count() < 3 || $fixed === null || $noQuota === null || $demo === null) {
            $this->command?->warn('PaymentSeeder: 面談パックまたは対象受講生が不足しています。');

            return;
        }

        DB::transaction(function () use ($packs, $fixed, $noQuota, $demo): void {
            $succeeded = Payment::create([
                'user_id' => $fixed->id,
                'meeting_pack_id' => $packs[0]->id,
                'amount' => $packs[0]->price,
                'quantity' => $packs[0]->meeting_count,
                'currency' => 'jpy',
                'status' => PaymentStatus::Succeeded,
                'stripe_checkout_session_id' => 'cs_test_seed_succeeded',
                'stripe_payment_intent_id' => 'pi_test_seed_succeeded',
                'paid_at' => now()->subDays(3),
            ]);

            MeetingQuotaTransaction::create([
                'user_id' => $fixed->id,
                'type' => MeetingQuotaTransactionType::Purchased,
                'amount' => $succeeded->quantity,
                'related_payment_id' => $succeeded->id,
                'occurred_at' => $succeeded->paid_at,
            ]);

            Payment::create([
                'user_id' => $noQuota->id,
                'meeting_pack_id' => $packs[1]->id,
                'amount' => $packs[1]->price,
                'quantity' => $packs[1]->meeting_count,
                'currency' => 'jpy',
                'status' => PaymentStatus::Pending,
                'stripe_checkout_session_id' => 'cs_test_seed_pending',
            ]);

            Payment::create([
                'user_id' => $demo->id,
                'meeting_pack_id' => $packs[2]->id,
                'amount' => $packs[2]->price,
                'quantity' => $packs[2]->meeting_count,
                'currency' => 'jpy',
                'status' => PaymentStatus::Failed,
                'stripe_checkout_session_id' => 'cs_test_seed_failed',
            ]);
        });
    }
}
