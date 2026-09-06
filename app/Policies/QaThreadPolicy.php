<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Models\QaThread;
use App\Models\User;

class QaThreadPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, QaThread $thread): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        if ($thread->certification->status !== CertificationStatus::Published) {
            return false;
        }

        return match ($user->role) {
            UserRole::Student => true,
            UserRole::Coach => $thread->certification
                ->coaches()
                ->whereKey($user->id)
                ->exists(),
            default => false,
        };
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Student;
    }

    public function update(User $user, QaThread $thread): bool
    {
        return $this->isVisibleStudentAuthor($user, $thread);
    }

    public function delete(User $user, QaThread $thread): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $this->isVisibleStudentAuthor($user, $thread)
            && $thread->replies()->doesntExist();
    }

    public function resolve(User $user, QaThread $thread): bool
    {
        return $this->isVisibleStudentAuthor($user, $thread);
    }

    public function unresolve(User $user, QaThread $thread): bool
    {
        return $this->isVisibleStudentAuthor($user, $thread);
    }

    private function isVisibleStudentAuthor(User $user, QaThread $thread): bool
    {
        return $user->role === UserRole::Student
            && $user->id === $thread->user_id
            && $thread->certification->status === CertificationStatus::Published;
    }
}
