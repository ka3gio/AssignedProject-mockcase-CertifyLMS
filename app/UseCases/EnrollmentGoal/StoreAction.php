<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;

/**
 * Enrollment 配下に個人学習目標を作成する Action。
 */
final class StoreAction
{
    /**
     * @param array{title: string, description?: string|null, target_date?: string|null} $validated
     */
    public function __invoke(Enrollment $enrollment, array $validated): EnrollmentGoal
    {
        return $enrollment->goals()->create($validated);
    }
}
