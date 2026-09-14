<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;

/**
 * 個人学習目標(EnrollmentGoal)に対する認可ポリシー。
 *
 * - view: 受講生本人 / 担当資格の coach / admin
 * - create / update / delete / 達成・解除: 受講中の受講生本人のみ
 */
class EnrollmentGoalPolicy
{
    public function __construct(private readonly EnrollmentPolicy $enrollmentPolicy) {}

    public function view(User $user, EnrollmentGoal $goal): bool
    {
        $goal->loadMissing('enrollment');

        return $this->enrollmentPolicy->view($user, $goal->enrollment);
    }

    public function create(User $user, Enrollment $enrollment): bool
    {
        return $user->role === UserRole::Student
            && $user->status === UserStatus::InProgress
            && $enrollment->user_id === $user->id
            && ! $enrollment->trashed();
    }

    public function update(User $user, EnrollmentGoal $goal): bool
    {
        return $this->canManage($user, $goal);
    }

    public function delete(User $user, EnrollmentGoal $goal): bool
    {
        return $this->canManage($user, $goal);
    }

    public function markAchieved(User $user, EnrollmentGoal $goal): bool
    {
        return $this->canManage($user, $goal);
    }

    public function unmarkAchieved(User $user, EnrollmentGoal $goal): bool
    {
        return $this->canManage($user, $goal);
    }

    private function canManage(User $user, EnrollmentGoal $goal): bool
    {
        $goal->loadMissing('enrollment');

        return $this->create($user, $goal->enrollment);
    }
}
