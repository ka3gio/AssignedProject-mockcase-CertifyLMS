<?php

declare(strict_types=1);

namespace App\Exceptions\MeetingPack;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 公開中、または決済履歴のある面談パックを削除しようとした場合に返す例外(HTTP 409)。
 */
final class MeetingPackNotDeletableException extends ConflictHttpException
{
    public static function forPublished(): self
    {
        return new self('公開中の面談パックは削除できません。');
    }

    public static function forPaymentHistory(): self
    {
        return new self('購入履歴のある面談パックは削除できません。');
    }

    private function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
