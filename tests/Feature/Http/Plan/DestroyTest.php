<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use App\Models\UserPlanLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_unreferenced_draft_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        $this->actingAs($admin)->delete(route('admin.plans.destroy', $plan))
            ->assertRedirect(route('admin.plans.index'));

        $this->assertDatabaseMissing('plans', ['id' => $plan->id]);
    }

    public function test_published_or_archived_plan_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ([Plan::factory()->published()->create(), Plan::factory()->archived()->create()] as $plan) {
            $this->actingAs($admin)
                ->delete(route('admin.plans.destroy', $plan))
                ->assertForbidden();

            $this->assertDatabaseHas('plans', ['id' => $plan->id]);
        }
    }

    public function test_plan_linked_to_user_cannot_be_deleted_even_when_user_is_soft_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();
        $student = User::factory()->withPlan($plan)->create();
        $student->delete();

        $this->actingAs($admin)
            ->delete(route('admin.plans.destroy', $plan))
            ->assertForbidden();

        $this->assertDatabaseHas('plans', ['id' => $plan->id]);
    }

    public function test_plan_with_audit_history_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();
        UserPlanLog::factory()->for($plan)->create();

        $this->actingAs($admin)
            ->delete(route('admin.plans.destroy', $plan))
            ->assertForbidden();

        $this->assertDatabaseHas('plans', ['id' => $plan->id]);
    }
}
