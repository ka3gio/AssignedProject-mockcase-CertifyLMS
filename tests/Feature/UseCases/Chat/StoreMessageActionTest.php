<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Chat;

use App\Events\ChatMessageSent;
use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\ChatMember;
use App\Models\ChatRoom;
use App\Models\Enrollment;
use App\Models\User;
use App\UseCases\Chat\StoreMessageAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * StoreMessageAction の責務:
 *
 * - ChatMessage INSERT + 送信者の ChatMember.last_read_at = now() 更新
 * - DB::afterCommit() で ChatMessageSent broadcast を発火
 * - シグネチャは `__invoke(User, ChatRoom, array)`(E-3 撤回後の単一形態)
 */
class StoreMessageActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_insert_message_and_update_sender_last_read_at(): void
    {
        Event::fake([ChatMessageSent::class]);

        $sender = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($sender)->create();
        $room = ChatRoom::factory()->for($enrollment)->create();

        $senderMember = ChatMember::factory()->create([
            'chat_room_id' => $room->id,
            'user_id' => $sender->id,
            'last_read_at' => null,
        ]);
        ChatMember::factory()->create([
            'chat_room_id' => $room->id,
            'user_id' => $coach->id,
            'last_read_at' => null,
        ]);

        $message = app(StoreMessageAction::class)($sender, $room, ['body' => 'こんにちは']);

        $this->assertDatabaseHas('chat_messages', [
            'id' => $message->id,
            'chat_room_id' => $room->id,
            'sender_user_id' => $sender->id,
            'body' => 'こんにちは',
        ]);
        $this->assertNotNull($senderMember->fresh()->last_read_at);

        Event::assertDispatched(ChatMessageSent::class);
    }

    public function test_signature_is_user_chat_room_array(): void
    {
        $reflection = new \ReflectionMethod(StoreMessageAction::class, '__invoke');
        $params = $reflection->getParameters();

        $this->assertCount(3, $params);
        $this->assertSame(User::class, $params[0]->getType()?->getName());
        $this->assertSame(ChatRoom::class, $params[1]->getType()?->getName());
        $this->assertSame('array', $params[2]->getType()?->getName());
    }

    public function test_student_message_notifies_only_current_in_progress_coaches(): void
    {
        Event::fake([ChatMessageSent::class]);

        $student = User::factory()->student()->inProgress()->create();
        $coachA = User::factory()->coach()->inProgress()->create();
        $coachB = User::factory()->coach()->inProgress()->create();
        $detached = User::factory()->coach()->inProgress()->create();
        $unjoined = User::factory()->coach()->inProgress()->create();
        $graduated = User::factory()->coach()->graduated()->create();
        $wrongRole = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        foreach ([$coachA, $coachB, $unjoined, $graduated, $wrongRole] as $coach) {
            CertificationCoachAssignment::factory()->create([
                'certification_id' => $certification->id,
                'user_id' => $coach->id,
            ]);
        }
        CertificationCoachAssignment::factory()->unassigned()->create([
            'certification_id' => $certification->id,
            'user_id' => $detached->id,
        ]);

        $enrollment = Enrollment::factory()->for($student)->for($certification)->create();
        $room = ChatRoom::factory()->for($enrollment)->create();
        foreach ([$student, $coachA, $coachB, $detached] as $member) {
            ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $member->id]);
        }

        $message = app(StoreMessageAction::class)($student, $room, ['body' => '新着メッセージ']);

        foreach ([$coachA, $coachB] as $coach) {
            $this->assertSame(1, $coach->notifications()->count());
            $notification = $coach->notifications()->sole();
            $this->assertSame('chat_message_received', $notification->data['notification_type']);
            $this->assertSame($message->id, $notification->data['chat_message_id']);
            $this->assertSame(route('chat.show', $room, false), $notification->data['url']);
        }
        foreach ([$student, $detached, $unjoined, $graduated, $wrongRole] as $user) {
            $this->assertSame(0, $user->notifications()->count());
        }
        $this->assertCount(2, Mail::mailer()->getSymfonyTransport()->messages());
    }

    public function test_coach_message_notifies_student_and_other_current_coach(): void
    {
        Event::fake([ChatMessageSent::class]);

        $student = User::factory()->student()->inProgress()->create();
        $sender = User::factory()->coach()->inProgress()->create();
        $otherCoach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        foreach ([$sender, $otherCoach] as $coach) {
            CertificationCoachAssignment::factory()->create([
                'certification_id' => $certification->id,
                'user_id' => $coach->id,
            ]);
        }
        $enrollment = Enrollment::factory()->for($student)->for($certification)->create();
        $room = ChatRoom::factory()->for($enrollment)->create();
        foreach ([$student, $sender, $otherCoach] as $member) {
            ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $member->id]);
        }

        app(StoreMessageAction::class)($sender, $room, ['body' => 'コーチからの連絡']);

        $this->assertSame(1, $student->notifications()->count());
        $this->assertSame(1, $otherCoach->notifications()->count());
        $this->assertSame(0, $sender->notifications()->count());
        $this->assertCount(2, Mail::mailer()->getSymfonyTransport()->messages());
    }
}
