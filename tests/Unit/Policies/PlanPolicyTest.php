<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Plan;
use App\Models\User;
use App\Models\UserPlanLog;
use App\Policies\PlanPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PlanPolicy の admin 専用認可と、提供済み View の削除ボタン活性条件を検証する。
 */
class PlanPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_perform_plan_operations(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();
        $policy = new PlanPolicy;

        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->view($admin, $plan));
        $this->assertTrue($policy->create($admin));
        $this->assertTrue($policy->update($admin, $plan));
        $this->assertTrue($policy->delete($admin, $plan));
        $this->assertTrue($policy->publish($admin, $plan));
        $this->assertTrue($policy->archive($admin, $plan));
        $this->assertTrue($policy->unarchive($admin, $plan));
    }

    public function test_coach_and_student_cannot_manage_plans(): void
    {
        $plan = Plan::factory()->draft()->create();
        $policy = new PlanPolicy;

        foreach ([User::factory()->coach()->create(), User::factory()->student()->create()] as $user) {
            $this->assertFalse($policy->viewAny($user));
            $this->assertFalse($policy->view($user, $plan));
            $this->assertFalse($policy->create($user));
            $this->assertFalse($policy->update($user, $plan));
            $this->assertFalse($policy->delete($user, $plan));
            $this->assertFalse($policy->publish($user, $plan));
            $this->assertFalse($policy->archive($user, $plan));
            $this->assertFalse($policy->unarchive($user, $plan));
        }
    }

    public function test_delete_requires_draft_without_user_or_history_references(): void
    {
        $admin = User::factory()->admin()->create();
        $policy = new PlanPolicy;

        $published = Plan::factory()->published()->create();
        $withUser = Plan::factory()->draft()->create();
        User::factory()->withPlan($withUser)->create();
        $withHistory = Plan::factory()->draft()->create();
        UserPlanLog::factory()->for($withHistory)->create();

        $this->assertFalse($policy->delete($admin, $published));
        $this->assertFalse($policy->delete($admin, $withUser));
        $this->assertFalse($policy->delete($admin, $withHistory));
    }
}
