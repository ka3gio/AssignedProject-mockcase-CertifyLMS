<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_latest_twenty_payments_for_a_pack(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();
        $pack = MeetingPack::factory()->published()->create();
        Payment::factory()->succeeded()->count(21)->for($student)->for($pack, 'meetingPack')->create();

        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.show', $pack));

        $response->assertOk();
        $this->assertCount(20, $response->viewData('plan')->payments);
        $this->assertTrue($response->viewData('plan')->payments->every(
            fn (Payment $payment): bool => $payment->relationLoaded('user'),
        ));
    }

    public function test_admin_pack_index_contains_payment_counts(): void
    {
        $admin = User::factory()->admin()->create();
        $pack = MeetingPack::factory()->published()->create();
        Payment::factory()->succeeded()->count(2)->for($pack, 'meetingPack')->create();

        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.index'));

        $response->assertOk();
        $actual = $response->viewData('plans')->getCollection()->firstWhere('id', $pack->id);
        $this->assertSame(2, $actual->payments_count);
    }
}
