<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingQuota;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_in_progress_student_sees_only_published_packs_in_display_order(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $second = MeetingPack::factory()->published()->create(['name' => '後のパック', 'sort_order' => 20]);
        $first = MeetingPack::factory()->published()->create(['name' => '先のパック', 'sort_order' => 10]);
        MeetingPack::factory()->draft()->create(['name' => '下書きパック', 'sort_order' => 1]);
        MeetingPack::factory()->archived()->create(['name' => '終了パック', 'sort_order' => 2]);

        $response = $this->actingAs($student)->get('/meeting-quota/checkout');

        $response
            ->assertOk()
            ->assertViewIs('meeting-quota.checkout-select')
            ->assertViewHas('plans', fn ($plans): bool => $plans->modelKeys() === [$first->id, $second->id]);
    }

    public function test_non_student_cannot_open_checkout(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();

        $this->actingAs($coach)
            ->get('/meeting-quota/checkout')
            ->assertForbidden();
    }

    public function test_graduated_student_cannot_open_checkout(): void
    {
        $student = User::factory()->student()->graduated()->create();

        $this->actingAs($student)
            ->get('/meeting-quota/checkout')
            ->assertForbidden();
    }
}
