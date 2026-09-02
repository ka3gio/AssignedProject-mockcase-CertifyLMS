<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Exceptions\Plan\PlanNotDeletableException;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

/**
 * 未参照の下書きプランを削除する。ユーザー・監査履歴の参照があれば削除しない。
 */
final class DestroyAction
{
    /**
     * @throws PlanNotDeletableException
     */
    public function __invoke(Plan $plan): void
    {
        DB::transaction(function () use ($plan) {
            $lockedPlan = Plan::query()->lockForUpdate()->findOrFail($plan->id);

            if (
                $lockedPlan->status !== PlanStatus::Draft
                || $lockedPlan->users()->withTrashed()->exists()
                || $lockedPlan->userPlanLogs()->exists()
            ) {
                throw new PlanNotDeletableException;
            }

            $lockedPlan->delete();
        });
    }
}
