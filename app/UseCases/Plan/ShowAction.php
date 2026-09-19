<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Models\Plan;

/**
 * 管理者向けプラン詳細に必要な受講者と作成・更新者を Eager Loading する。
 */
final class ShowAction
{
    public function __invoke(Plan $plan): Plan
    {
        return $plan->load([
            'createdBy',
            'updatedBy',
            'users' => fn ($query) => $query->orderBy('name')->orderBy('email'),
        ]);
    }
}
