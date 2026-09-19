<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingQuota;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_unpublished_pack_cannot_be_purchased_by_direct_post(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $draft = MeetingPack::factory()->draft()->create();

        $this->actingAs($student)
            ->from('/meeting-quota/checkout')
            ->post('/meeting-quota/checkout', ['meeting_pack_id' => $draft->id])
            ->assertRedirect('/meeting-quota/checkout')
            ->assertSessionHasErrors('meeting_pack_id');
    }
}
