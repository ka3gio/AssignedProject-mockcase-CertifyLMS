<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use App\Enums\MeetingStatus;
use App\Models\ChatMessage;
use App\Models\Meeting;
use App\Models\QaReply;
use App\Models\User;
use App\Notifications\ChatMessageReceivedNotification;
use App\Notifications\MeetingCanceledNotification;
use App\Notifications\MeetingReservedNotification;
use App\Notifications\QaReplyReceivedNotification;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\NotificationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class NotificationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixed_student_and_coach_receive_current_event_notification_samples(): void
    {
        $this->seed(DatabaseSeeder::class);

        $users = User::query()
            ->whereIn('email', ['student@certify-lms.test', 'coach@certify-lms.test'])
            ->get();

        $this->assertCount(2, $users);

        $expectedTypes = [
            'student@certify-lms.test' => ['chat_message_received', 'qa_reply_received'],
            'coach@certify-lms.test' => ['chat_message_received', 'meeting_canceled', 'meeting_reserved'],
        ];
        $expectedClasses = [
            'chat_message_received' => ChatMessageReceivedNotification::class,
            'qa_reply_received' => QaReplyReceivedNotification::class,
            'meeting_reserved' => MeetingReservedNotification::class,
            'meeting_canceled' => MeetingCanceledNotification::class,
        ];

        foreach ($users as $user) {
            $notifications = $user->notifications()->get()
                ->reject(fn ($notification) => $notification->data['notification_type'] === 'admin_announcement');
            $types = $notifications
                ->pluck('data.notification_type')
                ->unique()
                ->sort()
                ->values()
                ->all();

            $this->assertCount(24, $notifications);
            $this->assertSame($expectedTypes[$user->email], $types);
            $this->assertTrue($notifications->contains(fn ($notification) => $notification->read_at === null));
            $this->assertTrue($notifications->contains(fn ($notification) => $notification->read_at !== null));

            foreach ($notifications as $notification) {
                $this->assertSame($expectedClasses[$notification->data['notification_type']], $notification->type);
                $this->assertStringStartsWith('/', $notification->data['url']);
            }
        }
    }

    public function test_samples_reference_real_business_events_for_the_recipient(): void
    {
        $this->seed(DatabaseSeeder::class);

        $student = User::query()->where('email', 'student@certify-lms.test')->firstOrFail();
        $coach = User::query()->where('email', 'coach@certify-lms.test')->firstOrFail();

        foreach ([$student, $coach] as $user) {
            $chat = $user->notifications()->get()
                ->first(fn ($notification) => $notification->data['notification_type'] === 'chat_message_received');
            $message = ChatMessage::query()->findOrFail($chat->data['chat_message_id']);
            $this->assertSame($message->chat_room_id, $chat->data['chat_room_id']);
            $this->assertNotSame($user->id, $message->sender_user_id);
            $this->assertSame(route('chat.show', ['room' => $message->chat_room_id], false), $chat->data['url']);
        }

        $qa = $student->notifications()->get()
            ->first(fn ($notification) => $notification->data['notification_type'] === 'qa_reply_received');
        $reply = QaReply::query()->findOrFail($qa->data['qa_reply_id']);
        $this->assertSame($student->id, $reply->thread->user_id);
        $this->assertNotSame($student->id, $reply->user_id);
        $this->assertSame(route('qa-board.show', $reply->thread, false), $qa->data['url']);

        foreach (['meeting_reserved' => MeetingStatus::Reserved, 'meeting_canceled' => MeetingStatus::Canceled] as $type => $status) {
            $notification = $coach->notifications()->get()
                ->first(fn ($item) => $item->data['notification_type'] === $type);
            $meeting = Meeting::query()->findOrFail($notification->data['meeting_id']);
            $this->assertSame($coach->id, $meeting->coach_id);
            $this->assertSame($status, $meeting->status);
            $this->assertSame(route('meetings.show', $meeting, false), $notification->data['url']);

            if ($status === MeetingStatus::Canceled) {
                $this->assertNotSame($coach->id, $meeting->canceled_by_user_id);
            }
        }
    }

    public function test_qa_sample_does_not_use_a_self_reply(): void
    {
        $this->seed(DatabaseSeeder::class);

        $student = User::query()->where('email', 'student@certify-lms.test')->firstOrFail();
        $reply = QaReply::query()
            ->whereHas('thread', fn ($query) => $query->where('user_id', $student->id))
            ->firstOrFail();
        QaReply::factory()
            ->for($reply->thread, 'thread')
            ->for($student, 'user')
            ->create(['created_at' => now()->addDay()]);

        $existingIds = $student->notifications()->pluck('id');
        $this->seed(NotificationSeeder::class);

        $newNotifications = $student->notifications()->whereNotIn('id', $existingIds)->get();
        $replyIds = $newNotifications
            ->filter(fn ($notification) => $notification->data['notification_type'] === 'qa_reply_received')
            ->pluck('data.qa_reply_id')
            ->unique()
            ->values()
            ->all();

        $this->assertCount(24, $newNotifications);
        $this->assertSame([$reply->id], $replyIds);
    }
}
