<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingQuota;

use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutSuccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_view_own_checkout_result(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $pack = MeetingPack::factory()->published()->create();
        $payment = Payment::factory()->pending()->for($student)->for($pack, 'meetingPack')->create([
            'stripe_checkout_session_id' => 'cs_test_owned',
        ]);

        $this->actingAs($student)
            ->get('/meeting-quota/success?session_id=cs_test_owned')
            ->assertOk()
            ->assertViewIs('meeting-quota.success')
            ->assertViewHas('payment', fn (Payment $actual): bool => $actual->is($payment));
    }

    public function test_student_cannot_view_another_students_checkout_result(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $viewer = User::factory()->student()->inProgress()->create();
        $pack = MeetingPack::factory()->published()->create();
        Payment::factory()->pending()->for($owner)->for($pack, 'meetingPack')->create([
            'stripe_checkout_session_id' => 'cs_test_other',
        ]);

        $this->actingAs($viewer)
            ->get('/meeting-quota/success?session_id=cs_test_other')
            ->assertNotFound();
    }
}
