<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MeetingStatus;
use App\Models\ChatRoom;
use App\Models\Meeting;
use App\Models\QaReply;
use App\Models\User;
use App\Notifications\ChatMessageReceivedNotification;
use App\Notifications\MeetingCanceledNotification;
use App\Notifications\MeetingReservedNotification;
use App\Notifications\QaReplyReceivedNotification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * 固定 student / coach に既存の業務データに紐づく通知を投入する。
 *
 * 各ユーザー 24 件とし、一覧の 20 件ページネーションも migrate:fresh --seed 直後に確認できる。
 * mail チャンネルは通さず DatabaseNotification のみを直接作成する。
 */
final class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()
            ->whereIn('email', ['student@certify-lms.test', 'coach@certify-lms.test'])
            ->get();

        foreach ($users as $user) {
            $this->seedFor($user);
        }
    }

    private function seedFor(User $user): void
    {
        $room = ChatRoom::query()
            ->forUser($user)
            ->whereHas('messages', fn ($query) => $query->where('sender_user_id', '!=', $user->id))
            ->orderByLastMessage()
            ->first();
        $chatMessage = $room?->messages()
            ->where('sender_user_id', '!=', $user->id)
            ->with('sender')
            ->latest()
            ->first();
        $reply = QaReply::query()
            ->whereHas('thread', fn ($query) => $query->where('user_id', $user->id))
            ->where('user_id', '!=', $user->id)
            ->with(['thread', 'user'])
            ->latest()
            ->first();
        $reservedMeeting = Meeting::query()
            ->where('coach_id', $user->id)
            ->where('status', MeetingStatus::Reserved->value)
            ->where('scheduled_at', '>', now())
            ->orderBy('scheduled_at')
            ->first();
        $canceledMeeting = Meeting::query()
            ->where(function ($query) use ($user): void {
                $query->where('student_id', $user->id)
                    ->orWhere('coach_id', $user->id);
            })
            ->where('status', MeetingStatus::Canceled->value)
            ->whereNotNull('canceled_by_user_id')
            ->where('canceled_by_user_id', '!=', $user->id)
            ->with('canceledBy')
            ->orderByDesc('canceled_at')
            ->first();

        $templates = [];

        if ($chatMessage !== null) {
            $templates[] = new ChatMessageReceivedNotification($chatMessage);
        }

        if ($reply !== null) {
            $templates[] = new QaReplyReceivedNotification($reply);
        }

        if ($reservedMeeting !== null) {
            $templates[] = new MeetingReservedNotification($reservedMeeting);
        }

        if ($canceledMeeting !== null && $canceledMeeting->canceledBy !== null) {
            $templates[] = new MeetingCanceledNotification($canceledMeeting, $canceledMeeting->canceledBy);
        }

        if ($templates === []) {
            return;
        }

        for ($i = 0; $i < 24; $i++) {
            $notification = $templates[$i % count($templates)];
            $createdAt = now()->subMinutes(($i + 1) * 17);

            $user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => $notification::class,
                'data' => $notification->toArray($user),
                'read_at' => $i % 3 === 0 ? $createdAt->copy()->addMinutes(5) : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }
}
