<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Services\BusinessNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\QaBoardTestHelpers;
use Tests\TestCase;

class ReplyCrudTest extends TestCase
{
    use QaBoardTestHelpers, RefreshDatabase;

    public function test_new_reply_notifies_thread_author_but_self_reply_does_not(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $thread = QaThread::factory()->for($author, 'user')->create();
        $this->assignCoach($thread->certification, $coach);

        $this->actingAs($coach)->post(route('qa-board.replies.store', $thread), [
            'body' => '回答しました',
        ])->assertRedirect(route('qa-board.show', $thread));

        $this->assertSame(1, $author->notifications()->count());
        $notification = $author->notifications()->sole();
        $this->assertSame('qa_reply_received', $notification->data['notification_type']);
        $this->assertSame(route('qa-board.show', $thread, false), $notification->data['url']);
        $this->assertCount(1, Mail::mailer()->getSymfonyTransport()->messages());

        $this->actingAs($author)->post(route('qa-board.replies.store', $thread), [
            'body' => '自己回答',
        ])->assertRedirect(route('qa-board.show', $thread));

        $this->assertSame(1, $author->notifications()->count());
        $this->assertSame(0, $coach->notifications()->count());
        $this->assertCount(1, Mail::mailer()->getSymfonyTransport()->messages());
    }

    public function test_reply_does_not_notify_graduated_or_deleted_thread_author(): void
    {
        $graduated = User::factory()->student()->graduated()->create();
        $deleted = User::factory()->student()->inProgress()->create();
        $replier = User::factory()->coach()->inProgress()->create();

        foreach ([$graduated, $deleted] as $author) {
            $thread = QaThread::factory()->for($author, 'user')->create();
            $reply = QaReply::factory()->for($thread, 'thread')->for($replier, 'user')->create();

            if ($author->is($deleted)) {
                $author->delete();
            }

            app(BusinessNotificationService::class)->notifyQaReplyReceived($reply);
            $this->assertSame(0, $author->notifications()->count());
        }

        $this->assertCount(0, Mail::mailer()->getSymfonyTransport()->messages());
    }

    public function test_student_and_assigned_coach_can_reply_including_resolved_thread(): void
    {
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $thread = QaThread::factory()->resolved()->create();
        $this->assignCoach($thread->certification, $coach);

        $this->actingAs($student)->post(route('qa-board.replies.store', $thread), [
            'body' => '受講生からの回答',
        ])->assertRedirect(route('qa-board.show', $thread));

        $this->actingAs($coach)->post(route('qa-board.replies.store', $thread), [
            'body' => 'コーチからの回答',
        ])->assertRedirect(route('qa-board.show', $thread));

        $this->assertDatabaseHas('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $student->id,
            'body' => '受講生からの回答',
        ]);
        $this->assertDatabaseHas('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $coach->id,
            'body' => 'コーチからの回答',
        ]);
    }

    public function test_unassigned_coach_cannot_reply(): void
    {
        $coach = User::factory()->coach()->create();
        $thread = QaThread::factory()->create();

        $this->actingAs($coach)->post(route('qa-board.replies.store', $thread), [
            'body' => '担当外回答',
        ])->assertForbidden();
    }

    public function test_reply_body_is_required_and_limited_to_5000_characters(): void
    {
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()->create();

        $this->actingAs($student)
            ->post(route('qa-board.replies.store', $thread), ['body' => ''])
            ->assertSessionHasErrors('body');
        $this->actingAs($student)
            ->post(route('qa-board.replies.store', $thread), ['body' => str_repeat('a', 5001)])
            ->assertSessionHasErrors('body');
    }

    public function test_reply_author_can_edit_update_and_delete_own_reply(): void
    {
        $author = User::factory()->student()->create();
        $thread = QaThread::factory()->create();
        $reply = QaReply::factory()->for($thread, 'thread')->for($author, 'user')->create();

        $this->actingAs($author)
            ->get(route('qa-board.replies.edit', [$thread, $reply]))
            ->assertOk();

        $this->actingAs($author)->patch(route('qa-board.replies.update', [$thread, $reply]), [
            'body' => '更新した回答',
        ])->assertRedirect(route('qa-board.show', $thread));
        $this->assertDatabaseHas('qa_replies', ['id' => $reply->id, 'body' => '更新した回答']);

        $this->actingAs($author)
            ->delete(route('qa-board.replies.destroy', [$thread, $reply]))
            ->assertRedirect(route('qa-board.show', $thread));
        $this->assertDatabaseMissing('qa_replies', ['id' => $reply->id]);
    }

    public function test_other_user_cannot_edit_update_or_delete_reply(): void
    {
        $other = User::factory()->student()->create();
        $thread = QaThread::factory()->create();
        $reply = QaReply::factory()->for($thread, 'thread')->create();

        $this->actingAs($other)
            ->get(route('qa-board.replies.edit', [$thread, $reply]))
            ->assertForbidden();
        $this->actingAs($other)
            ->patch(route('qa-board.replies.update', [$thread, $reply]), ['body' => '不正更新'])
            ->assertForbidden();
        $this->actingAs($other)
            ->delete(route('qa-board.replies.destroy', [$thread, $reply]))
            ->assertForbidden();
    }

    public function test_reply_from_another_thread_cannot_be_operated_through_nested_url(): void
    {
        $author = User::factory()->student()->create();
        $thread = QaThread::factory()->create();
        $otherThread = QaThread::factory()->create();
        $reply = QaReply::factory()->for($otherThread, 'thread')->for($author, 'user')->create();

        $this->actingAs($author)
            ->get(route('qa-board.replies.edit', [$thread, $reply]))
            ->assertNotFound();
        $this->actingAs($author)
            ->patch(route('qa-board.replies.update', [$thread, $reply]), ['body' => '不正更新'])
            ->assertNotFound();
        $this->actingAs($author)
            ->delete(route('qa-board.replies.destroy', [$thread, $reply]))
            ->assertNotFound();

        $this->assertDatabaseHas('qa_replies', ['id' => $reply->id]);
    }
}
