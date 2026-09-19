<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\User;

/**
 * 修了証 PDF のダウンロード認可。
 *
 * 受講生本人 / 現在の担当資格のコーチ / 管理者のみ許可する。
 */
class CertificatePolicy
{
    public function download(User $user, Certificate $certificate): bool
    {
        return match ($user->role) {
            UserRole::Admin => true,
            UserRole::Student => $certificate->user_id === $user->id,
            UserRole::Coach => $certificate->certification
                ->coaches()
                ->where('users.id', $user->id)
                ->exists(),
        };
    }
}
