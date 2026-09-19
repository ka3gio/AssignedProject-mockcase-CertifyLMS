<?php

declare(strict_types=1);

namespace App\UseCases\SettingsProfile;

use App\Exceptions\SettingsProfile\AvatarStorageException;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 認証ユーザー本人のアバター画像を public disk に保存するユースケース。
 *
 * DB 更新に失敗した場合は新規ファイルを削除し、差し替え成功後に旧アップロード画像を削除する。
 */
final class StoreAvatarAction
{
    /**
     * @throws AvatarStorageException
     */
    public function __invoke(User $user, UploadedFile $file): User
    {
        $extension = strtolower($file->extension() ?: 'png');
        $filename = Str::ulid().'.'.$extension;
        $path = 'avatars/'.$filename;
        $oldUrl = $user->avatar_url;
        $oldPath = $oldUrl !== null && str_starts_with($oldUrl, '/storage/avatars/')
            ? substr($oldUrl, strlen('/storage/'))
            : null;

        try {
            $storedPath = Storage::disk('public')->putFileAs('avatars', $file, $filename);
            if ($storedPath === false) {
                throw new \RuntimeException('Failed to store avatar.');
            }

            $updated = DB::transaction(function () use ($user, $path): User {
                $user->update(['avatar_url' => '/storage/'.$path]);

                return $user->refresh();
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);
            throw new AvatarStorageException($e);
        }

        if ($oldPath !== null) {
            Storage::disk('public')->delete($oldPath);
        }

        return $updated;
    }
}
