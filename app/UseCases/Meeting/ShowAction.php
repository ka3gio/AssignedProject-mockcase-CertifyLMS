<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Models\Meeting;

/** 面談詳細画面で使用する関連データを取得する。 */
final class ShowAction
{
    public function __invoke(Meeting $meeting): Meeting
    {
        return $meeting->loadMissing([
            'enrollment.certification',
            'coach',
            'student',
            'canceledBy',
            'meetingMemo',
        ]);
    }
}
