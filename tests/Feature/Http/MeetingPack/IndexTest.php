<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_filters_by_name_keyword_and_status(): void
    {
        $admin = User::factory()->admin()->create();
        MeetingPack::factory()->published()->create(['name' => '対象 5 回パック']);
        MeetingPack::factory()->draft()->create(['name' => '対象 下書きパック']);
        MeetingPack::factory()->published()->create(['name' => '別商品']);

        $this->actingAs($admin)
            ->get(route('admin.meeting-packs.index', [
                'keyword' => '対象',
                'status' => 'published',
            ]))
            ->assertOk()
            ->assertSee('対象 5 回パック')
            ->assertDontSee('対象 下書きパック')
            ->assertDontSee('別商品');
    }

    public function test_index_orders_by_sort_order_then_newest_creation(): void
    {
        $admin = User::factory()->admin()->create();
        MeetingPack::factory()->create([
            'name' => '後ろ',
            'sort_order' => 20,
        ]);
        MeetingPack::factory()->create([
            'name' => '先頭',
            'sort_order' => 10,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.meeting-packs.index'))
            ->assertOk()
            ->assertSeeInOrder(['先頭', '後ろ']);
    }

    public function test_index_paginates_twenty_packs_and_preserves_filters(): void
    {
        $admin = User::factory()->admin()->create();
        MeetingPack::factory()->count(21)->draft()->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.meeting-packs.index', [
                'status' => 'draft',
            ]));

        $response->assertOk();
        $this->assertSame(20, $response->viewData('plans')->perPage());
        $response->assertSee('status=draft', false);
    }

    public function test_invalid_filter_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.meeting-packs.index', ['status' => 'invalid']))
            ->assertSessionHasErrors('status');
    }
}
