<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Exceptions\Mentoring\MeetingAlreadyStartedException;
use App\Exceptions\Mentoring\MeetingStatusTransitionException;
use App\Models\Meeting;
use App\Models\User;
use App\Services\BusinessNotificationService;
use App\Services\GoogleCalendarSyncService;
use App\UseCases\MeetingQuota\RefundQuotaAction;
use Illuminate\Support\Facades\DB;

/** 当事者による面談キャンセルと、回数返却・カレンダー連携・通知を実行する。 */
final class CancelAction
{
    public function __construct(
        private readonly RefundQuotaAction $refund,
        private readonly BusinessNotificationService $notifications,
        private readonly GoogleCalendarSyncService $calendarSync,
    ) {}

    public function __invoke(Meeting $meeting, User $actor): Meeting
    {
        return DB::transaction(function () use ($meeting, $actor) {
            $locked = Meeting::query()->whereKey($meeting->id)->lockForUpdate()->first();
            if ($locked === null || $locked->status !== MeetingStatus::Reserved) {
                throw MeetingStatusTransitionException::forCancel();
            }

            if ($locked->scheduled_at->lessThanOrEqualTo(now())) {
                throw new MeetingAlreadyStartedException;
            }

            $locked->update([
                'status' => MeetingStatus::Canceled->value,
                'canceled_by_user_id' => $actor->id,
                'canceled_at' => now(),
            ]);

            ($this->refund)($locked->student, $locked->id);

            $locked = $locked->fresh();
            $this->notifications->notifyMeetingCanceled($locked, $actor);
            $this->calendarSync->deleteForMeeting($locked);

            return $locked->fresh();
        });
    }
}
