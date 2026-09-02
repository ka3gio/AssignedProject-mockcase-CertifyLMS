<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Enums\UserStatus;
use App\Models\Plan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 管理者向けプラン一覧のクエリ Action。プラン名 / 状態で絞り込み、表示順で paginate する。
 */
final class IndexAction
{
    public function __invoke(
        ?string $keyword,
        ?PlanStatus $status,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = Plan::query()->withCount([
            'users' => fn ($query) => $query
                ->where('status', UserStatus::InProgress),
        ]);

        if ($keyword !== null && $keyword !== '') {
            $query->where('name', 'LIKE', "%{$keyword}%");
        }

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        return $query
            ->ordered()
            ->paginate($perPage)
            ->withQueryString();
    }
}
