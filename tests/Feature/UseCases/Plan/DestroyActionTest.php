<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Plan;

use App\Exceptions\Plan\PlanNotDeletableException;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserPlanLog;
use App\UseCases\Plan\DestroyAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_unreferenced_draft_plan(): void
    {
        $plan = Plan::factory()->draft()->create();

        app(DestroyAction::class)($plan);

        $this->assertDatabaseMissing('plans', ['id' => $plan->id]);
    }

    public function test_throws_when_plan_is_not_draft(): void
    {
        $plan = Plan::factory()->published()->create();

        $this->expectException(PlanNotDeletableException::class);

        app(DestroyAction::class)($plan);
    }

    public function test_throws_when_user_reference_exists(): void
    {
        $plan = Plan::factory()->draft()->create();
        User::factory()->withPlan($plan)->create();

        $this->expectException(PlanNotDeletableException::class);

        app(DestroyAction::class)($plan);
    }

    public function test_throws_when_history_reference_exists(): void
    {
        $plan = Plan::factory()->draft()->create();
        UserPlanLog::factory()->for($plan)->create();

        $this->expectException(PlanNotDeletableException::class);

        app(DestroyAction::class)($plan);
    }
}
