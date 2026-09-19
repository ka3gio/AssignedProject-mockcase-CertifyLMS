<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Models\QaThread;

/** スレッド詳細と回答一覧の表示に必要な関連データを一括取得する。 */
final class ShowAction
{
    public function __invoke(QaThread $thread): QaThread
    {
        return $thread
            ->load([
                'certification',
                'user',
                'replies' => fn ($replies) => $replies
                    ->with('user')
                    ->orderBy('created_at'),
            ])
            ->loadCount('replies');
    }
}
