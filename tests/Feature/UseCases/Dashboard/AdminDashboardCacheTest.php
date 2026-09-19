<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Dashboard;

use App\Enums\EnrollmentStatus;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\EnrollmentStatusChangeService;
use App\UseCases\Dashboard\FetchAdminDashboardAction;
use App\UseCases\Enrollment\DestroyAction;
use App\UseCases\User\WithdrawAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 管理者ダッシュボード集計のキャッシュ挙動を検証する Feature テスト。
 * 全体 KPI と資格別修了率の 2 キーそれぞれについて、連続表示で集計が Cache::remember から
 * 再利用されること(重い集計を再実行しない)と、受講状態の遷移(EnrollmentStatusChangeService)で
 * 両キーが無効化され最新値に更新されること(片方の forget 漏れも検出する)を網羅する。
 */
class AdminDashboardCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_kpi_is_served_from_cache_on_second_fetch(): void
    {
        // Arrange: admin + 受講中 2 件、キャッシュは空の状態から始める
        $admin = User::factory()->admin()->inProgress()->create();
        $cert = Certification::factory()->published()->create();
        Enrollment::factory()->for($cert)->learning()->count(2)->create();
        Cache::flush();

        // Act: 1 回目で集計してキャッシュ → 無効化経路を通さず DB を直接増やす → クエリ計測しつつ 2 回目
        $first = app(FetchAdminDashboardAction::class)($admin);
        Enrollment::factory()->for($cert)->learning()->count(3)->create();

        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });
        $second = app(FetchAdminDashboardAction::class)($admin);

        // Assert: 2 回目はキャッシュヒットで重い集計クエリが 1 本も走らず、値も 1 回目のまま(直接 INSERT は反映されない)
        $this->assertSame(2, $first->kpi['learning_count']);
        $this->assertSame(
            2,
            $second->kpi['learning_count'],
            'TTL 内で無効化イベントが無ければ集計はキャッシュから返るはず(直接 INSERT は反映されない)',
        );
        $this->assertSame(
            0,
            $queryCount,
            'キャッシュヒット時は重い集計クエリが 1 本も発行されないはず',
        );
        $this->assertTrue(
            Cache::has(config('dashboard.admin_kpi_cache_key')),
            '管理者 KPI 集計がキャッシュキーに保存されているはず',
        );
    }

    public function test_completion_rate_is_served_from_cache_on_second_fetch(): void
    {
        // Arrange: admin + 受講中 2 件(修了率 0 %)、キャッシュは空の状態から始める
        $admin = User::factory()->admin()->inProgress()->create();
        $cert = Certification::factory()->published()->create();
        Enrollment::factory()->for($cert)->learning()->count(2)->create();
        Cache::flush();

        // Act: 1 回目で集計してキャッシュ → 無効化経路を通さず合格者を直接増やす → クエリ計測しつつ 2 回目
        $first = app(FetchAdminDashboardAction::class)($admin);
        Enrollment::factory()->for($cert)->passed()->create();

        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });
        $second = app(FetchAdminDashboardAction::class)($admin);

        // Assert: 2 回目はキャッシュヒットで集計クエリが走らず、修了率も 1 回目のまま(直接 INSERT は反映されない)
        $firstRate = $first->completionRateByCertification->firstWhere('certification_id', $cert->id)['completion_rate'];
        $secondRate = $second->completionRateByCertification->firstWhere('certification_id', $cert->id)['completion_rate'];

        $this->assertSame(0.0, $firstRate);
        $this->assertSame(
            0.0,
            $secondRate,
            'TTL 内で無効化イベントが無ければ修了率はキャッシュから返るはず(直接 INSERT は反映されない)',
        );
        $this->assertSame(
            0,
            $queryCount,
            'キャッシュヒット時は重い集計クエリが 1 本も発行されないはず',
        );
        $this->assertTrue(
            Cache::has(config('dashboard.admin_completion_rate_cache_key')),
            '資格別修了率の集計がキャッシュキーに保存されているはず',
        );
    }

    public function test_cached_aggregates_are_recalculated_after_configured_ttl_expires(): void
    {
        // Arrange: 短い TTL を設定し、受講中 1 件をキャッシュする
        config(['dashboard.admin_cache_ttl_seconds' => 5]);
        $admin = User::factory()->admin()->inProgress()->create();
        $cert = Certification::factory()->published()->create();
        Enrollment::factory()->for($cert)->learning()->create();
        Cache::flush();
        app(FetchAdminDashboardAction::class)($admin);

        // Act: 無効化経路を通さず DB を更新し、設定した TTL より先へ時刻を進める
        Enrollment::factory()->for($cert)->passed()->create();
        $this->travel(6)->seconds();
        $after = app(FetchAdminDashboardAction::class)($admin);

        // Assert: 期限切れ後は DB から再集計される
        $this->assertSame(1, $after->kpi['passed_count']);
        $rate = $after->completionRateByCertification->firstWhere('certification_id', $cert->id)['completion_rate'];
        $this->assertSame(0.5, $rate);
    }

    public function test_admin_kpi_cache_is_invalidated_on_enrollment_status_change(): void
    {
        // Arrange: admin + 受講中 2 件を 1 度集計してキャッシュさせる
        $admin = User::factory()->admin()->inProgress()->create();
        $cert = Certification::factory()->published()->create();
        $enrollments = Enrollment::factory()->for($cert)->learning()->count(2)->create();
        Cache::flush();
        app(FetchAdminDashboardAction::class)($admin);

        // Act: 1 件を合格に遷移(状態遷移チョークポイント経由でキャッシュが無効化される)
        $target = $enrollments->first();
        $target->update(['status' => EnrollmentStatus::Passed]);
        app(EnrollmentStatusChangeService::class)->recordStatusChange(
            $target,
            EnrollmentStatus::Learning,
            EnrollmentStatus::Passed,
            $admin,
        );
        $after = app(FetchAdminDashboardAction::class)($admin);

        // Assert: KPI キャッシュが無効化され、最新の集計(learning 1 / passed 1)が返る
        $this->assertSame(
            1,
            $after->kpi['learning_count'],
            '状態遷移後は KPI キャッシュが無効化され、最新の受講中件数が返るはず',
        );
        $this->assertSame(1, $after->kpi['passed_count']);
    }

    public function test_completion_rate_cache_is_invalidated_on_enrollment_status_change(): void
    {
        // Arrange: admin + 受講中 2 件(修了率 0 %)を 1 度集計してキャッシュさせる
        $admin = User::factory()->admin()->inProgress()->create();
        $cert = Certification::factory()->published()->create();
        $enrollments = Enrollment::factory()->for($cert)->learning()->count(2)->create();
        Cache::flush();
        app(FetchAdminDashboardAction::class)($admin);

        // Act: 1 件を合格に遷移(状態遷移チョークポイント経由でキャッシュが無効化される)
        $target = $enrollments->first();
        $target->update(['status' => EnrollmentStatus::Passed]);
        app(EnrollmentStatusChangeService::class)->recordStatusChange(
            $target,
            EnrollmentStatus::Learning,
            EnrollmentStatus::Passed,
            $admin,
        );
        $after = app(FetchAdminDashboardAction::class)($admin);

        // Assert: 修了率キャッシュも無効化され、最新の修了率(合格 1 / 全 2 = 0.5)が返る
        $afterRate = $after->completionRateByCertification->firstWhere('certification_id', $cert->id)['completion_rate'];
        $this->assertSame(
            0.5,
            $afterRate,
            '状態遷移後は修了率キャッシュも無効化され、最新の修了率(1/2)が返るはず',
        );
    }

    public function test_both_caches_are_invalidated_when_learning_enrollment_is_deleted(): void
    {
        // Arrange: 受講中 1 件を含む両集計をキャッシュする
        $admin = User::factory()->admin()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();
        $cert = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($cert)->learning()->create();
        Cache::flush();
        app(FetchAdminDashboardAction::class)($admin);

        // Act: 状態遷移ログを通らない受講解除で Enrollment を SoftDelete する
        app(DestroyAction::class)($enrollment);
        $after = app(FetchAdminDashboardAction::class)($admin);

        // Assert: KPI と修了率の両方が最新状態へ更新される
        $this->assertTrue($after->isEmptyState);
        $this->assertSame(0, $after->kpi['learning_count']);
        $this->assertTrue($after->completionRateByCertification->isEmpty());
    }

    public function test_both_caches_are_invalidated_when_user_is_withdrawn(): void
    {
        // Arrange: 受講中 1 件をキャッシュした後、無効化経路を通さず 1 件追加する
        $admin = User::factory()->admin()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();
        $cert = Certification::factory()->published()->create();
        Enrollment::factory()->for($student)->for($cert)->learning()->create();
        Cache::flush();
        app(FetchAdminDashboardAction::class)($admin);
        Enrollment::factory()->for($cert)->learning()->create();

        // Act: User の退会経路を実行してから再表示する
        app(WithdrawAction::class)($student, $admin);
        $after = app(FetchAdminDashboardAction::class)($admin);

        // Assert: 現行の集計定義は退会者の Enrollment も含むが、退会を契機に最新値へ再集計される
        $this->assertSame(2, $after->kpi['learning_count']);
        $row = $after->completionRateByCertification->firstWhere('certification_id', $cert->id);
        $this->assertSame(2, $row['total']);
    }
}
