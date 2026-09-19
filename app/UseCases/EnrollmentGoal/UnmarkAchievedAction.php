<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Models\EnrollmentGoal;

/**
 * 個人学習目標の達成を解除する Action。
 */
final class UnmarkAchievedAction
{
    public function __invoke(EnrollmentGoal $goal): EnrollmentGoal
    {
        if ($goal->achieved_at !== null) {
            $goal->update(['achieved_at' => null]);
        }

        return $goal->refresh();
    }
}
