<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Exceptions\Mentoring\MeetingStatusTransitionException;
use App\Models\Meeting;
use App\Models\MeetingMemo;
use Illuminate\Support\Facades\DB;

/** 担当コーチによる面談メモの作成・更新を実行する。 */
final class UpsertMemoAction
{
    public function __invoke(Meeting $meeting, string $body): MeetingMemo
    {
        return DB::transaction(function () use ($meeting, $body) {
            if (! in_array($meeting->status, [MeetingStatus::Reserved, MeetingStatus::Completed], true)) {
                throw MeetingStatusTransitionException::forMemo();
            }

            return MeetingMemo::updateOrCreate(
                ['meeting_id' => $meeting->id],
                ['body' => $body],
            );
        });
    }
}
