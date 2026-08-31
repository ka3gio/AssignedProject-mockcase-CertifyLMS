<?php

declare(strict_types=1);

namespace App\Exceptions\MeetingPack;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 公開中の面談パックを削除しようとした場合に返す例外(HTTP 409)。
 */
final class MeetingPackNotDeletableException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('公開中の面談パックは削除できません。', $previous);
    }
}
