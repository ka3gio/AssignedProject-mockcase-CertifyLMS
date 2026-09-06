<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Models\MeetingPack;

/**
 * 面談パック詳細の作成者・最終更新者を読み込む。
 * 決済履歴は追加面談購入 Feature の導入後に同 Feature 側で連携する。
 */
final class ShowAction
{
    public function __invoke(MeetingPack $plan): MeetingPack
    {
        return $plan->load(['createdBy', 'updatedBy']);
    }
}
