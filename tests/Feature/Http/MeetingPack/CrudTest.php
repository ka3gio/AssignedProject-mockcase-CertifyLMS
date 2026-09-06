<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_management_pages(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->create([
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)->get(route('admin.meeting-packs.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.meeting-packs.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.meeting-packs.show', $plan))->assertOk();
        $this->actingAs($admin)->get(route('admin.meeting-packs.edit', $plan))->assertOk();
    }

    public function test_admin_creates_meeting_pack_as_draft(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.meeting-packs.store'), [
                'name' => '3 回パック',
                'description' => '追加面談用',
                'meeting_count' => 3,
                'price' => 8000,
                'stripe_price_id' => 'price_test',
                'sort_order' => 10,
                'status' => MeetingPackStatus::Published->value,
            ]);

        $plan = MeetingPack::query()->sole();

        $response->assertRedirect(route('admin.meeting-packs.show', $plan));
        $this->assertSame(MeetingPackStatus::Draft, $plan->status);
        $this->assertSame($admin->id, $plan->created_by_user_id);
        $this->assertSame($admin->id, $plan->updated_by_user_id);
    }

    public function test_store_uses_zero_as_default_sort_order(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.meeting-packs.store'), [
                'name' => '1 回パック',
                'description' => null,
                'meeting_count' => 1,
                'price' => 0,
                'stripe_price_id' => null,
            ])
            ->assertRedirect();

        $this->assertSame(0, MeetingPack::query()->sole()->sort_order);
    }

    public function test_store_rejects_invalid_fields(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.meeting-packs.store'), [
                'name' => str_repeat('a', 101),
                'description' => str_repeat('a', 2001),
                'meeting_count' => 101,
                'price' => 1000001,
                'stripe_price_id' => str_repeat('a', 256),
                'sort_order' => -1,
            ])
            ->assertSessionHasErrors([
                'name',
                'description',
                'meeting_count',
                'price',
                'stripe_price_id',
                'sort_order',
            ]);

        $this->assertDatabaseCount('meeting_packs', 0);
    }

    public function test_admin_updates_basic_information_without_changing_status(): void
    {
        $creator = User::factory()->admin()->create();
        $updater = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->published()->create([
            'created_by_user_id' => $creator->id,
            'updated_by_user_id' => $creator->id,
            'sort_order' => 5,
        ]);

        $response = $this->actingAs($updater)
            ->patch(route('admin.meeting-packs.update', $plan), [
                'name' => '更新後パック',
                'description' => null,
                'meeting_count' => 10,
                'price' => 21000,
                'stripe_price_id' => null,
                'sort_order' => 20,
                'status' => MeetingPackStatus::Archived->value,
            ]);

        $response->assertRedirect(route('admin.meeting-packs.show', $plan));

        $plan->refresh();
        $this->assertSame('更新後パック', $plan->name);
        $this->assertSame(10, $plan->meeting_count);
        $this->assertSame(21000, $plan->price);
        $this->assertSame(20, $plan->sort_order);
        $this->assertSame(MeetingPackStatus::Published, $plan->status);
        $this->assertSame($creator->id, $plan->created_by_user_id);
        $this->assertSame($updater->id, $plan->updated_by_user_id);
    }

    public function test_admin_can_physically_delete_draft_and_archived_packs(): void
    {
        $admin = User::factory()->admin()->create();
        $draft = MeetingPack::factory()->draft()->create();
        $archived = MeetingPack::factory()->archived()->create();

        $this->actingAs($admin)
            ->delete(route('admin.meeting-packs.destroy', $draft))
            ->assertRedirect(route('admin.meeting-packs.index'));
        $this->actingAs($admin)
            ->delete(route('admin.meeting-packs.destroy', $archived))
            ->assertRedirect(route('admin.meeting-packs.index'));

        $this->assertDatabaseMissing('meeting_packs', ['id' => $draft->id]);
        $this->assertDatabaseMissing('meeting_packs', ['id' => $archived->id]);
    }

    public function test_published_pack_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $published = MeetingPack::factory()->published()->create();

        $this->actingAs($admin)
            ->deleteJson(route('admin.meeting-packs.destroy', $published))
            ->assertStatus(409);

        $this->assertDatabaseHas('meeting_packs', ['id' => $published->id]);
    }

    public function test_student_and_coach_cannot_access_admin_meeting_pack_routes(): void
    {
        $plan = MeetingPack::factory()->draft()->create();

        foreach ([User::factory()->student()->create(), User::factory()->coach()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.meeting-packs.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.meeting-packs.create'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.meeting-packs.store'), [])->assertForbidden();
            $this->actingAs($user)->get(route('admin.meeting-packs.show', $plan))->assertForbidden();
            $this->actingAs($user)->get(route('admin.meeting-packs.edit', $plan))->assertForbidden();
            $this->actingAs($user)->patch(route('admin.meeting-packs.update', $plan), [])->assertForbidden();
            $this->actingAs($user)->delete(route('admin.meeting-packs.destroy', $plan))->assertForbidden();
            $this->actingAs($user)->post(route('admin.meeting-packs.publish', $plan))->assertForbidden();
            $this->actingAs($user)->post(route('admin.meeting-packs.archive', $plan))->assertForbidden();
            $this->actingAs($user)->post(route('admin.meeting-packs.unarchive', $plan))->assertForbidden();
        }
    }
}
