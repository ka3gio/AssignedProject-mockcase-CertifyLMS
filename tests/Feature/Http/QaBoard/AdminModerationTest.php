<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_index_includes_threads_for_published_and_unpublished_certifications(): void
    {
        $admin = User::factory()->admin()->create();
        $published = QaThread::factory()->create(['title' => '公開資格の質問']);
        $draft = QaThread::factory()
            ->for(Certification::factory()->draft())
            ->create(['title' => '下書き資格の質問']);

        $response = $this->actingAs($admin)->get(route('admin.qa-board.index'));

        $response->assertOk()
            ->assertSee($published->title)
            ->assertSee($draft->title);
        $this->assertSame(2, $response->viewData('threads')->total());
    }

    public function test_only_admin_can_access_moderation_routes(): void
    {
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()->create();

        $this->actingAs($student)->get(route('admin.qa-board.index'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.qa-board.show', $thread))->assertForbidden();
    }

    public function test_admin_can_view_unpublished_thread_but_cannot_edit_or_resolve_through_public_routes(): void
    {
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()
            ->for(Certification::factory()->archived())
            ->create();

        $this->actingAs($admin)
            ->get(route('admin.qa-board.show', $thread))
            ->assertOk()
            ->assertSee('管理者は閲覧 + モデレーション削除のみ可能');
        $this->actingAs($admin)->get(route('qa-board.edit', $thread))->assertForbidden();
        $this->actingAs($admin)->post(route('qa-board.resolve', $thread))->assertForbidden();
    }

    public function test_admin_can_delete_any_thread_and_its_replies(): void
    {
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()
            ->for(Certification::factory()->draft())
            ->create();
        $reply = QaReply::factory()->for($thread, 'thread')->create();

        $this->actingAs($admin)
            ->delete(route('admin.qa-board.destroy', $thread))
            ->assertRedirect(route('admin.qa-board.index'));

        $this->assertDatabaseMissing('qa_threads', ['id' => $thread->id]);
        $this->assertDatabaseMissing('qa_replies', ['id' => $reply->id]);
    }

    public function test_admin_can_delete_arbitrary_reply(): void
    {
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()
            ->for(Certification::factory()->archived())
            ->create();
        $reply = QaReply::factory()->for($thread, 'thread')->create();

        $this->actingAs($admin)
            ->delete(route('admin.qa-board.replies.destroy', [$thread, $reply]))
            ->assertRedirect(route('admin.qa-board.show', $thread));

        $this->assertDatabaseMissing('qa_replies', ['id' => $reply->id]);
        $this->assertDatabaseHas('qa_threads', ['id' => $thread->id]);
    }
}
