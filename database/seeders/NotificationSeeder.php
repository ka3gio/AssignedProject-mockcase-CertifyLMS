<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ChatRoom;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * 固定 student / coach に chat・面談リマインダーの既読・未読通知を投入する。
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
        $meeting = Meeting::query()
            ->where(function ($query) use ($user): void {
                $query->where('student_id', $user->id)
                    ->orWhere('coach_id', $user->id);
            })
            ->where('scheduled_at', '>', now())
            ->orderBy('scheduled_at')
            ->first();

        $templates = [];

        if ($room !== null && $chatMessage !== null) {
            $templates[] = [
                'notification_type' => 'chat_message_received',
                'title' => '新しいchatメッセージが届きました',
                'message' => $chatMessage->sender->name.'さん: '.Str::limit(Str::squish($chatMessage->body), 120),
                'url' => route('chat.show', $room, false),
                'chat_room_id' => $room->id,
                'chat_message_id' => $chatMessage->id,
            ];
        }

        if ($meeting !== null) {
            $templates[] = [
                'notification_type' => 'meeting_reminder',
                'title' => '面談の予定が近づいています',
                'message' => $meeting->scheduled_at->format('Y/m/d H:i').' から面談が予定されています。',
                'url' => route('meetings.show', $meeting, false),
                'meeting_id' => $meeting->id,
            ];
        }

        if ($templates === []) {
            return;
        }

        for ($i = 0; $i < 24; $i++) {
            $data = $templates[$i % count($templates)];
            $createdAt = now()->subMinutes(($i + 1) * 17);

            $user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => Notification::class,
                'data' => $data,
                'read_at' => $i % 3 === 0 ? $createdAt->copy()->addMinutes(5) : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }
}
