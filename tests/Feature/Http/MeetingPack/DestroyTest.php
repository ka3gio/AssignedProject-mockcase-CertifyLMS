<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 後続 Feature の payments を専用のインメモリ DB 内だけで再現する。
 * 本番用マイグレーションや既存 MySQL DB にはテーブルを追加しない。
 */
class DestroyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        // payments の本番マイグレーション追加後も、未導入・導入済みを独立して検証する。
        $this->artisan('migrate', [
            '--path' => [
                'database/migrations/2014_10_12_000000_create_users_table.php',
                'database/migrations/2026_05_17_000010_create_meeting_packs_table.php',
            ],
            '--force' => true,
        ])->assertExitCode(0);
        $this->withoutVite();
    }

    /**
     * @dataProvider deletableStatuses
     */
    public function test_pack_can_be_deleted_before_payments_table_is_introduced(string $status): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->create(['status' => $status]);

        $this->assertFalse(Schema::hasTable('payments'));
        $this->actingAs($admin)
            ->delete(route('admin.meeting-packs.destroy', $plan))
            ->assertRedirect(route('admin.meeting-packs.index'));

        $this->assertDatabaseMissing('meeting_packs', ['id' => $plan->id]);
    }

    /**
     * @dataProvider deletableStatuses
     */
    public function test_pack_with_payment_history_cannot_be_deleted(string $status): void
    {
        $this->createPaymentsTable();
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->create(['status' => $status]);
        $paymentId = (string) Str::ulid();
        DB::table('payments')->insert(['id' => $paymentId, 'meeting_pack_id' => $plan->id]);

        $this->actingAs($admin)
            ->deleteJson(route('admin.meeting-packs.destroy', $plan))
            ->assertStatus(409)
            ->assertJsonPath('message', '購入履歴のある面談パックは削除できません。');

        $this->assertDatabaseHas('meeting_packs', ['id' => $plan->id, 'status' => $status]);
        $this->assertDatabaseHas('payments', ['id' => $paymentId, 'meeting_pack_id' => $plan->id]);
    }

    /**
     * @dataProvider deletableStatuses
     */
    public function test_payment_history_for_another_pack_does_not_block_deletion(string $status): void
    {
        $this->createPaymentsTable();
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->create(['status' => $status]);
        $otherPlan = MeetingPack::factory()->create();
        DB::table('payments')->insert([
            'id' => (string) Str::ulid(),
            'meeting_pack_id' => $otherPlan->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.meeting-packs.destroy', $plan))
            ->assertRedirect(route('admin.meeting-packs.index'));

        $this->assertDatabaseMissing('meeting_packs', ['id' => $plan->id]);
        $this->assertDatabaseHas('meeting_packs', ['id' => $otherPlan->id]);
        $this->assertDatabaseHas('payments', ['meeting_pack_id' => $otherPlan->id]);
    }

    public function test_browser_deletion_with_payment_history_returns_with_error(): void
    {
        $this->createPaymentsTable();
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->archived()->create();
        DB::table('payments')->insert([
            'id' => (string) Str::ulid(),
            'meeting_pack_id' => $plan->id,
        ]);
        $detailUrl = route('admin.meeting-packs.show', $plan);

        $this->actingAs($admin)
            ->from($detailUrl)
            ->delete(route('admin.meeting-packs.destroy', $plan))
            ->assertRedirect($detailUrl)
            ->assertSessionHas('error', '購入履歴のある面談パックは削除できません。');

        $this->assertDatabaseHas('meeting_packs', ['id' => $plan->id]);
    }

    public function test_payment_history_guard_activates_when_table_is_introduced(): void
    {
        $admin = User::factory()->admin()->create();
        $beforeIntroduction = MeetingPack::factory()->archived()->create();

        $this->actingAs($admin)
            ->delete(route('admin.meeting-packs.destroy', $beforeIntroduction))
            ->assertRedirect(route('admin.meeting-packs.index'));
        $this->assertDatabaseMissing('meeting_packs', ['id' => $beforeIntroduction->id]);

        $this->createPaymentsTable();
        $afterIntroduction = MeetingPack::factory()->archived()->create();
        DB::table('payments')->insert([
            'id' => (string) Str::ulid(),
            'meeting_pack_id' => $afterIntroduction->id,
        ]);

        $this->actingAs($admin)
            ->deleteJson(route('admin.meeting-packs.destroy', $afterIntroduction))
            ->assertStatus(409)
            ->assertJsonPath('message', '購入履歴のある面談パックは削除できません。');
        $this->assertDatabaseHas('meeting_packs', ['id' => $afterIntroduction->id]);
    }

    public static function deletableStatuses(): array
    {
        return [
            '下書き' => ['draft'],
            'アーカイブ' => ['archived'],
        ];
    }

    private function createPaymentsTable(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('meeting_pack_id')->index();
        });
    }
}
