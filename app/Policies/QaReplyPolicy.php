<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;

class QaReplyPolicy
{
    public function create(User $user, QaThread $thread): bool
    {
        return $user->can('view', $thread);
    }

    public function update(User $user, QaReply $reply): bool
    {
        return $reply->user_id === $user->id
            && $user->can('view', $reply->thread);
    }

    public function delete(User $user, QaReply $reply): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $this->update($user, $reply);
    }
}
