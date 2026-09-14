<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Models\EnrollmentGoal;

/**
 * 個人学習目標の入力項目を更新する Action。
 */
final class UpdateAction
{
    /**
     * @param array{title: string, description?: string|null, target_date?: string|null} $validated
     */
    public function __invoke(EnrollmentGoal $goal, array $validated): EnrollmentGoal
    {
        $goal->update($validated);

        return $goal->refresh();
    }
}
