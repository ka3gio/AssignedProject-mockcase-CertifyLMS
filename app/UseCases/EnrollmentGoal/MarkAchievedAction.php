<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Models\EnrollmentGoal;

/**
 * 個人学習目標を達成済みにする Action。既に達成済みの場合は達成日時を維持する。
 */
final class MarkAchievedAction
{
    public function __invoke(EnrollmentGoal $goal): EnrollmentGoal
    {
        if ($goal->achieved_at === null) {
            $goal->update(['achieved_at' => now()]);
        }

        return $goal->refresh();
    }
}
