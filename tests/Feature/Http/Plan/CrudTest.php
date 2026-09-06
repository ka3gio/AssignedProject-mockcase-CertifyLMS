<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrudTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => '標準プラン',
            'description' => '標準的な受講プランです。',
            'duration_days' => 90,
            'default_meeting_quota' => 12,
            'sort_order' => 10,
        ], $overrides);
    }

    public function test_admin_can_create_plan_as_draft(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.plans.store'), $this->payload());

        $plan = Plan::query()->where('name', '標準プラン')->firstOrFail();
        $response->assertRedirect(route('admin.plans.show', $plan));
        $this->assertSame('draft', $plan->status->value);
        $this->assertSame($admin->id, $plan->created_by_user_id);
        $this->assertSame($admin->id, $plan->updated_by_user_id);
    }

    public function test_blank_sort_order_is_stored_as_zero(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.plans.store'), $this->payload([
            'sort_order' => '',
        ]))->assertRedirect();

        $this->assertDatabaseHas('plans', ['name' => '標準プラン', 'sort_order' => 0]);
    }

    public function test_admin_can_update_basic_fields_without_changing_status(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();

        $response = $this->actingAs($admin)->put(route('admin.plans.update', $plan), $this->payload([
            'name' => '改定プラン',
            'duration_days' => 120,
            'default_meeting_quota' => 16,
        ]));

        $response->assertRedirect(route('admin.plans.show', $plan));
        $plan->refresh();
        $this->assertSame('改定プラン', $plan->name);
        $this->assertSame(120, $plan->duration_days);
        $this->assertSame(16, $plan->default_meeting_quota);
        $this->assertSame('published', $plan->status->value);
        $this->assertSame($admin->id, $plan->updated_by_user_id);
    }

    public function test_input_boundaries_are_validated(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->from(route('admin.plans.create'))
            ->post(route('admin.plans.store'), $this->payload([
                'name' => str_repeat('a', 101),
                'description' => str_repeat('a', 2001),
                'duration_days' => 3651,
                'default_meeting_quota' => 1001,
                'sort_order' => 65536,
            ]));

        $response->assertSessionHasErrors([
            'name',
            'description',
            'duration_days',
            'default_meeting_quota',
            'sort_order',
        ]);
    }

    public function test_admin_can_view_plan_details_with_linked_users_and_metadata(): void
    {
        $admin = User::factory()->admin()->create(['name' => '管理者']);
        $plan = Plan::factory()->create([
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);
        $student = User::factory()->withPlan($plan)->create(['name' => '受講太郎']);

        $this->actingAs($admin)
            ->get(route('admin.plans.show', $plan))
            ->assertOk()
            ->assertSee($student->name)
            ->assertSee('管理者');
    }
}
