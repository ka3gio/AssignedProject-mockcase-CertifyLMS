<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_move_plan_through_lifecycle(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        $this->actingAs($admin)->post(route('admin.plans.publish', $plan))
            ->assertRedirect(route('admin.plans.show', $plan));
        $this->assertSame('published', $plan->fresh()->status->value);

        $this->actingAs($admin)->post(route('admin.plans.archive', $plan))
            ->assertRedirect(route('admin.plans.show', $plan));
        $this->assertSame('archived', $plan->fresh()->status->value);

        $this->actingAs($admin)->post(route('admin.plans.unarchive', $plan))
            ->assertRedirect(route('admin.plans.show', $plan));
        $this->assertSame('draft', $plan->fresh()->status->value);
    }

    public function test_non_admin_cannot_change_plan_status(): void
    {
        $coach = User::factory()->coach()->create();
        $plan = Plan::factory()->draft()->create();

        $this->actingAs($coach)
            ->post(route('admin.plans.publish', $plan))
            ->assertForbidden();
    }
}
