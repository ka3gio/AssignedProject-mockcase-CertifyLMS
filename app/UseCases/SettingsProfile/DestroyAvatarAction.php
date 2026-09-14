<?php

declare(strict_types=1);

namespace App\UseCases\SettingsProfile;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * 認証ユーザー本人のアバター設定とアップロード済み実ファイルを削除するユースケース。
 */
final class DestroyAvatarAction
{
    public function __invoke(User $user): User
    {
        $url = $user->avatar_url;
        $path = $url !== null && str_starts_with($url, '/storage/avatars/')
            ? substr($url, strlen('/storage/'))
            : null;

        $updated = DB::transaction(function () use ($user): User {
            $user->update(['avatar_url' => null]);

            return $user->refresh();
        });

        if ($path !== null) {
            Storage::disk('public')->delete($path);
        }

        return $updated;
    }
}
