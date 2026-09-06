<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_plans_by_name_and_status(): void
    {
        $admin = User::factory()->admin()->create();
        Plan::factory()->published()->create(['name' => '標準プラン']);
        Plan::factory()->draft()->create(['name' => '標準プラン 下書き']);
        Plan::factory()->published()->create(['name' => '短期プラン']);

        $response = $this->actingAs($admin)->get(route('admin.plans.index', [
            'keyword' => '標準',
            'status' => 'published',
        ]));

        $response->assertOk();
        $response->assertViewIs('plan.management.index');
        $response->assertSee('標準プラン');
        $response->assertDontSee('標準プラン 下書き');
        $response->assertDontSee('短期プラン');
    }

    public function test_list_counts_only_in_progress_users(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();
        User::factory()->invited()->withPlan($plan)->create();
        User::factory()->inProgress()->withPlan($plan)->create();
        User::factory()->inProgress()->withPlan($plan)->create()->delete();
        User::factory()->graduated()->withPlan($plan)->create();
        User::factory()->withdrawn()->withPlan($plan)->create();

        $response = $this->actingAs($admin)->get(route('admin.plans.index'));

        $plans = $response->viewData('plans');
        $this->assertSame(1, $plans->firstWhere('id', $plan->id)->users_count);
    }

    public function test_list_is_paginated_twenty_per_page(): void
    {
        $admin = User::factory()->admin()->create();
        Plan::factory()->count(22)->create();

        $response = $this->actingAs($admin)->get(route('admin.plans.index'));

        $plans = $response->viewData('plans');
        $this->assertSame(20, $plans->perPage());
        $this->assertSame(22, $plans->total());
    }

    public function test_student_and_coach_cannot_access_plan_list(): void
    {
        foreach ([User::factory()->student()->create(), User::factory()->coach()->create()] as $user) {
            $this->actingAs($user)
                ->get(route('admin.plans.index'))
                ->assertForbidden();
        }
    }
}
