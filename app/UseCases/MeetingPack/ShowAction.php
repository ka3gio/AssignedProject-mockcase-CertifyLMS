<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Models\MeetingPack;

/**
 * 面談パック詳細の作成者・最終更新者と直近の購入履歴を読み込む。
 */
final class ShowAction
{
    public function __invoke(MeetingPack $plan): MeetingPack
    {
        return $plan->load([
            'createdBy',
            'updatedBy',
            'payments' => fn ($query) => $query
                ->with('user')
                ->latest()
                ->limit(20),
        ]);
    }
}
