<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingReminderNotification;
use App\Services\BusinessNotificationService;
use Illuminate\Support\Facades\DB;

final class SendMeetingReminderAction
{
    public function __construct(private readonly BusinessNotificationService $notifications) {}

    /**
     * 同じ面談・受信者の通知は、既存のアプリ内通知を送信済み記録として一度だけ処理する。
     */
    public function __invoke(Meeting $meeting, User $recipient): bool
    {
        return DB::transaction(function () use ($meeting, $recipient): bool {
            $lockedMeeting = Meeting::query()->whereKey($meeting->id)->lockForUpdate()->first();
            if ($lockedMeeting === null || $lockedMeeting->status !== MeetingStatus::Reserved) {
                return false;
            }

            $dueAt = $lockedMeeting->scheduled_at->copy()->subDay()->setTime(20, 0);
            if ($lockedMeeting->created_at->greaterThan($dueAt)) {
                return false;
            }

            $lockedRecipient = User::query()->whereKey($recipient->id)->lockForUpdate()->first();
            if ($lockedRecipient === null) {
                return false;
            }

            $alreadySent = $lockedRecipient->notifications()
                ->where('type', MeetingReminderNotification::class)
                ->where('data->meeting_id', $lockedMeeting->id)
                ->exists();
            if ($alreadySent) {
                return false;
            }

            $lockedMeeting->loadMissing(['student', 'coach']);
            if (! $this->notifications->notifyMeetingReminder($lockedMeeting, $lockedRecipient)) {
                return false;
            }

            return true;
        });
    }
}
