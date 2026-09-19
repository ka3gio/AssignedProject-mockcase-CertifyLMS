<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PlanStatus;
use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\User;

/**
 * プランマスタ管理の認可ルール。すべての操作を admin のみに制限する。
 * delete は提供済み View の @can に削除ボタンの活性条件を反映するため、参照有無も判定する。
 * 実行時の競合を考慮した最終ガードは `App\UseCases\Plan\DestroyAction` が再度行う。
 */
class PlanPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function view(User $auth, Plan $plan): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function create(User $auth): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function update(User $auth, Plan $plan): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function delete(User $auth, Plan $plan): bool
    {
        return $auth->role === UserRole::Admin
            && $plan->status === PlanStatus::Draft
            && ! $plan->users()->withTrashed()->exists()
            && ! $plan->userPlanLogs()->exists();
    }

    public function publish(User $auth, Plan $plan): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function archive(User $auth, Plan $plan): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function unarchive(User $auth, Plan $plan): bool
    {
        return $auth->role === UserRole::Admin;
    }
}
