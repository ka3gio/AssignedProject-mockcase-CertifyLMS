<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThreadCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_open_create_form_and_post_to_any_published_certification(): void
    {
        $student = User::factory()->student()->create();
        $published = Certification::factory()->published()->create(['name' => '公開資格']);
        $draft = Certification::factory()->draft()->create(['name' => '下書き資格']);

        $this->actingAs($student)
            ->get(route('qa-board.create'))
            ->assertOk()
            ->assertSee('公開資格')
            ->assertDontSee('下書き資格');

        $response = $this->actingAs($student)->post(route('qa-board.store'), [
            'certification_id' => $published->id,
            'title' => '新しい質問',
            'body' => '質問の本文です。',
        ]);

        $thread = QaThread::query()->sole();
        $response->assertRedirect(route('qa-board.show', $thread));
        $this->assertSame($student->id, $thread->user_id);
        $this->assertSame(QaThreadStatus::Unresolved, $thread->status);
        $this->assertNull($thread->resolved_at);
    }

    public function test_thread_store_validates_required_lengths_and_published_certification(): void
    {
        $student = User::factory()->student()->create();
        $draft = Certification::factory()->draft()->create();

        $this->actingAs($student)->post(route('qa-board.store'), [
            'certification_id' => $draft->id,
            'title' => str_repeat('a', 201),
            'body' => str_repeat('b', 5001),
        ])->assertSessionHasErrors(['certification_id', 'title', 'body']);

        $this->assertDatabaseCount('qa_threads', 0);
    }

    public function test_coach_cannot_create_thread(): void
    {
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();

        $this->actingAs($coach)->get(route('qa-board.create'))->assertForbidden();
        $this->actingAs($coach)->post(route('qa-board.store'), [
            'certification_id' => $certification->id,
            'title' => '不正投稿',
            'body' => '本文',
        ])->assertForbidden();
    }

    public function test_author_can_edit_and_update_without_changing_certification(): void
    {
        $author = User::factory()->student()->create();
        $originalCertification = Certification::factory()->published()->create();
        $otherCertification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->for($author, 'user')->for($originalCertification)->create();

        $this->actingAs($author)->get(route('qa-board.edit', $thread))->assertOk();
        $this->actingAs($author)->patch(route('qa-board.update', $thread), [
            'title' => '更新後タイトル',
            'body' => '更新後本文',
            'certification_id' => $otherCertification->id,
        ])->assertRedirect(route('qa-board.show', $thread));

        $thread->refresh();
        $this->assertSame('更新後タイトル', $thread->title);
        $this->assertSame('更新後本文', $thread->body);
        $this->assertSame($originalCertification->id, $thread->certification_id);
    }

    public function test_non_author_cannot_edit_update_resolve_or_delete_thread(): void
    {
        $other = User::factory()->student()->create();
        $thread = QaThread::factory()->create();

        $this->actingAs($other)->get(route('qa-board.edit', $thread))->assertForbidden();
        $this->actingAs($other)->patch(route('qa-board.update', $thread), [
            'title' => '不正更新',
            'body' => '不正更新',
        ])->assertForbidden();
        $this->actingAs($other)->post(route('qa-board.resolve', $thread))->assertForbidden();
        $this->actingAs($other)->delete(route('qa-board.destroy', $thread))->assertForbidden();
    }

    public function test_author_can_toggle_resolution_status(): void
    {
        $author = User::factory()->student()->create();
        $thread = QaThread::factory()->unresolved()->for($author, 'user')->create();

        $this->actingAs($author)
            ->post(route('qa-board.resolve', $thread))
            ->assertRedirect(route('qa-board.show', $thread));
        $this->assertSame(QaThreadStatus::Resolved, $thread->fresh()->status);
        $this->assertNotNull($thread->fresh()->resolved_at);

        $this->actingAs($author)
            ->post(route('qa-board.unresolve', $thread))
            ->assertRedirect(route('qa-board.show', $thread));
        $this->assertSame(QaThreadStatus::Unresolved, $thread->fresh()->status);
        $this->assertNull($thread->fresh()->resolved_at);
    }

    public function test_author_can_delete_only_thread_without_replies(): void
    {
        $author = User::factory()->student()->create();
        $deletable = QaThread::factory()->for($author, 'user')->create();
        $withReply = QaThread::factory()->for($author, 'user')->create();
        QaReply::factory()->for($withReply, 'thread')->create();

        $this->actingAs($author)
            ->delete(route('qa-board.destroy', $deletable))
            ->assertRedirect(route('qa-board.index'));
        $this->assertDatabaseMissing('qa_threads', ['id' => $deletable->id]);

        $this->actingAs($author)
            ->delete(route('qa-board.destroy', $withReply))
            ->assertForbidden();
        $this->assertDatabaseHas('qa_threads', ['id' => $withReply->id]);
    }
}
