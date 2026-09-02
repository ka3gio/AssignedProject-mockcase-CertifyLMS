<?php

declare(strict_types=1);

namespace App\Exceptions\Plan;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 状態または参照整合性の条件を満たさず、プランを削除できない場合の例外(HTTP 409)。
 */
final class PlanNotDeletableException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('下書きかつ受講者・プラン履歴が紐づいていないプランのみ削除できます。', $previous);
    }
}
