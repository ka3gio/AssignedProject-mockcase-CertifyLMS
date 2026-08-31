<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_follow_the_complete_lifecycle(): void
    {
        $creator = User::factory()->admin()->create();
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->draft()->create([
            'created_by_user_id' => $creator->id,
            'updated_by_user_id' => $creator->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.meeting-packs.publish', $plan))
            ->assertRedirect(route('admin.meeting-packs.show', $plan));
        $this->assertSame(MeetingPackStatus::Published, $plan->refresh()->status);
        $this->assertSame($admin->id, $plan->updated_by_user_id);

        $this->actingAs($admin)
            ->post(route('admin.meeting-packs.archive', $plan))
            ->assertRedirect(route('admin.meeting-packs.show', $plan));
        $this->assertSame(MeetingPackStatus::Archived, $plan->refresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.meeting-packs.unarchive', $plan))
            ->assertRedirect(route('admin.meeting-packs.show', $plan));
        $this->assertSame(MeetingPackStatus::Draft, $plan->refresh()->status);
    }

    public function test_publish_rejects_non_draft_states(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ([
            MeetingPack::factory()->published()->create(),
            MeetingPack::factory()->archived()->create(),
        ] as $plan) {
            $this->actingAs($admin)
                ->postJson(route('admin.meeting-packs.publish', $plan))
                ->assertStatus(409);
        }
    }

    public function test_archive_rejects_non_published_states(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ([
            MeetingPack::factory()->draft()->create(),
            MeetingPack::factory()->archived()->create(),
        ] as $plan) {
            $this->actingAs($admin)
                ->postJson(route('admin.meeting-packs.archive', $plan))
                ->assertStatus(409);
        }
    }

    public function test_unarchive_rejects_non_archived_states(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ([
            MeetingPack::factory()->draft()->create(),
            MeetingPack::factory()->published()->create(),
        ] as $plan) {
            $this->actingAs($admin)
                ->postJson(route('admin.meeting-packs.unarchive', $plan))
                ->assertStatus(409);
        }
    }
}
