<?php

declare(strict_types=1);

namespace App\UseCases\SettingsProfile;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 認証ユーザー本人のプロフィールを更新するユースケース。
 *
 * email / role / status は入力値にかかわらず更新しない。meeting_url はコーチの場合のみ更新する。
 */
final class UpdateProfileAction
{
    /**
     * @param array{name: string, bio?: ?string, meeting_url?: ?string} $validated
     */
    public function __invoke(User $user, array $validated): User
    {
        $attributes = [
            'name' => $validated['name'],
            'bio' => $validated['bio'] ?? null,
        ];

        if ($user->role === UserRole::Coach) {
            $attributes['meeting_url'] = $validated['meeting_url'] ?? null;
        }

        return DB::transaction(function () use ($user, $attributes): User {
            $user->update($attributes);

            return $user->refresh();
        });
    }
}
