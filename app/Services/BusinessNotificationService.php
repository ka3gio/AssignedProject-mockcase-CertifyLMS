<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AnnouncementTargetType;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\ChatMessage;
use App\Models\Meeting;
use App\Models\QaReply;
use App\Models\User;
use App\Notifications\Announcement\AdminAnnouncementNotification;
use App\Notifications\ChatMessageReceivedNotification;
use App\Notifications\MeetingCanceledNotification;
use App\Notifications\MeetingReservedNotification;
use App\Notifications\QaReplyReceivedNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

/** 通知種別ごとに受信可能なユーザーを選定し、業務通知を送信する。 */
final class BusinessNotificationService
{
    public function notifyAnnouncement(Announcement $announcement): int
    {
        $recipients = $this->announcementRecipients($announcement);

        if ($recipients->isEmpty()) {
            return 0;
        }

        Notification::send($recipients, new AdminAnnouncementNotification($announcement));

        return $recipients->count();
    }

    public function notifyChatMessageReceived(ChatMessage $message): void
    {
        $message->loadMissing('chatRoom.enrollment.certification');

        $enrollment = $message->chatRoom->enrollment;
        $memberIds = $message->chatRoom->members()->pluck('user_id');
        $users = $enrollment->certification->coaches()
            ->where('users.role', UserRole::Coach->value)
            ->get()
            ->push(User::query()->find($enrollment->user_id));
        $recipients = $users
            ->filter(fn (?User $user): bool => $user !== null
                && $memberIds->contains($user->id)
                && $user->id !== $message->sender_user_id
                && $this->canReceive($user))
            ->unique('id')
            ->values();

        Notification::send($recipients, new ChatMessageReceivedNotification($message));
    }

    public function notifyQaReplyReceived(QaReply $reply): void
    {
        $reply->loadMissing('thread.user');
        $author = $reply->thread->user;

        if ($author === null || $author->id === $reply->user_id || ! $this->canReceive($author)) {
            return;
        }

        Notification::send($author, new QaReplyReceivedNotification($reply));
    }

    public function notifyMeetingReserved(Meeting $meeting): void
    {
        $coach = User::query()->find($meeting->coach_id);

        if (! $this->canReceive($coach)) {
            return;
        }

        Notification::send($coach, new MeetingReservedNotification($meeting));
    }

    public function notifyMeetingCanceled(Meeting $meeting, User $actor): void
    {
        if ($actor->id !== $meeting->student_id && $actor->id !== $meeting->coach_id) {
            return;
        }

        $recipientId = $actor->id === $meeting->student_id
            ? $meeting->coach_id
            : $meeting->student_id;
        $recipient = User::query()->find($recipientId);

        if (! $this->canReceive($recipient)) {
            return;
        }

        Notification::send($recipient, new MeetingCanceledNotification($meeting, $actor));
    }

    /** @return Collection<int, User> */
    private function announcementRecipients(Announcement $announcement): Collection
    {
        $query = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value);

        match ($announcement->target_type) {
            AnnouncementTargetType::AllStudents => null,
            AnnouncementTargetType::Certification => $query->whereHas(
                'enrollments',
                fn (Builder $enrollments) => $enrollments
                    ->where('certification_id', $announcement->target_certification_id)
                    ->whereIn('status', [
                        EnrollmentStatus::Learning->value,
                        EnrollmentStatus::Passed->value,
                    ]),
            ),
            AnnouncementTargetType::User => $query->whereKey($announcement->target_user_id),
        };

        return $query->orderBy('id')->get();
    }

    private function canReceive(?User $user): bool
    {
        return $user !== null
            && $user->deleted_at === null
            && $user->status === UserStatus::InProgress
            && in_array($user->role, [UserRole::Student, UserRole::Coach], true);
    }
}
