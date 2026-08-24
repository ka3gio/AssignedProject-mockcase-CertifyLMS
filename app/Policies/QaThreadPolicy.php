<?php

namespace App\Policies;

use App\Models\QaThread;
use App\Models\User;
use App\Enums\UserRole;
use App\Enums\CertificationStatus;
use Illuminate\Auth\Access\Response;

class QaThreadPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, QaThread $qaThread): bool
    {
        if ($qaThread->certification->status !== CertificationStatus::Published) {
            return false;
        }

        return match ($user->role) {
            UserRole::Student => true,
            UserRole::Coach => $qaThread->certification
                ->coaches()
                ->where('users.id', $user->id)
                ->exists(),
            default => false,
        };
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->role === UserRole::Student;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, QaThread $qaThread): bool
    {
        return $user->role === UserRole::Student && $user->id === $qaThread->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, QaThread $qaThread): bool
    {
        return $user->role === UserRole::Student && $user->id === $qaThread->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, QaThread $qaThread): bool
    {
        return true;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, QaThread $qaThread): bool
    {
        return true;
    }
}
