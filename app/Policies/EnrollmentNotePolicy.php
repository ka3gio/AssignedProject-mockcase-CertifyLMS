<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;

/**
 * 受講登録メモの認可ポリシー。
 *
 * - admin: 未削除の受講登録配下にある全メモを管理可
 * - coach: 担当資格配下を閲覧でき、自分が作成したメモのみ更新 / 削除可
 * - student: 閲覧を含め全拒否
 */
class EnrollmentNotePolicy
{
    public function viewAny(User $auth, Enrollment $enrollment): bool
    {
        return $this->canAccessEnrollment($auth, $enrollment);
    }

    public function create(User $auth, Enrollment $enrollment): bool
    {
        return $this->canAccessEnrollment($auth, $enrollment);
    }

    public function update(User $auth, EnrollmentNote $note): bool
    {
        if (! $this->canAccessEnrollment($auth, $note->enrollment)) {
            return false;
        }

        return $auth->role === UserRole::Admin
            || ($auth->role === UserRole::Coach && $note->author_user_id === $auth->id);
    }

    public function delete(User $auth, EnrollmentNote $note): bool
    {
        return $this->update($auth, $note);
    }

    private function canAccessEnrollment(User $auth, ?Enrollment $enrollment): bool
    {
        if ($enrollment === null || $enrollment->trashed()) {
            return false;
        }

        if ($auth->role === UserRole::Admin) {
            return true;
        }

        if ($auth->role !== UserRole::Coach) {
            return false;
        }

        $enrollment->loadMissing('certification.coaches');

        return $enrollment->certification?->coaches->contains('id', $auth->id) ?? false;
    }
}
