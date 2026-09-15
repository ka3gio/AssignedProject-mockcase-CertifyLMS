<?php

declare(strict_types=1);

namespace App\Exceptions\SettingsProfile;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * アバター画像の保存に失敗した場合の例外。
 */
final class AvatarStorageException extends HttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct(500, 'アイコン画像の保存に失敗しました。時間をおいて再度お試しください。', $previous);
    }
}
