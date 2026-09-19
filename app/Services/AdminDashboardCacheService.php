<?php

declare(strict_types=1);

namespace App\Services;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 管理者ダッシュボードの重い集計結果をキャッシュする Service。
 */
final class AdminDashboardCacheService
{
    /**
     * @param Closure(): array<string, mixed> $resolve
     *
     * @return array<string, mixed>
     */
    public function rememberKpi(Closure $resolve): array
    {
        return Cache::remember(
            config('dashboard.admin_kpi_cache_key'),
            config('dashboard.admin_cache_ttl_seconds'),
            $resolve,
        );
    }

    /**
     * @param Closure(): Collection<int, array<string, mixed>> $resolve
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function rememberCompletionRate(Closure $resolve): Collection
    {
        return Cache::remember(
            config('dashboard.admin_completion_rate_cache_key'),
            config('dashboard.admin_cache_ttl_seconds'),
            $resolve,
        );
    }

    /**
     * 集計値を無効化する。
     *
     * トランザクション中は即時削除に加えて commit 後にも再度削除し、commit 前の値が
     * 別リクエストから再キャッシュされる競合を防ぐ。
     */
    public function forget(): void
    {
        $this->forgetNow();

        if (DB::transactionLevel() > 0) {
            DB::afterCommit(fn () => $this->forgetNow());
        }
    }

    private function forgetNow(): void
    {
        Cache::forget(config('dashboard.admin_kpi_cache_key'));
        Cache::forget(config('dashboard.admin_completion_rate_cache_key'));
    }
}
