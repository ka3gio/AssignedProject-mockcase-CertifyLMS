<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\MeetingPack;
use App\Models\User;

/**
 * 面談パック管理の認可ルール。すべての操作を admin のみに許可する。
 */
class MeetingPackPolicy
{
    public function viewAny(User $auth): bool
    {
        return $this->isAdmin($auth);
    }

    public function view(User $auth, MeetingPack $plan): bool
    {
        return $this->isAdmin($auth);
    }

    public function create(User $auth): bool
    {
        return $this->isAdmin($auth);
    }

    public function update(User $auth, MeetingPack $plan): bool
    {
        return $this->isAdmin($auth);
    }

    public function delete(User $auth, MeetingPack $plan): bool
    {
        return $this->isAdmin($auth);
    }

    public function publish(User $auth, MeetingPack $plan): bool
    {
        return $this->isAdmin($auth);
    }

    public function archive(User $auth, MeetingPack $plan): bool
    {
        return $this->isAdmin($auth);
    }

    public function unarchive(User $auth, MeetingPack $plan): bool
    {
        return $this->isAdmin($auth);
    }

    private function isAdmin(User $auth): bool
    {
        return $auth->role === UserRole::Admin;
    }
}
