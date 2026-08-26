<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\QaBoardTestHelpers;
use Tests\TestCase;

class IndexShowTest extends TestCase
{
    use QaBoardTestHelpers, RefreshDatabase;

    public function test_student_sees_threads_for_all_published_certifications_only(): void
    {
        $student = User::factory()->student()->create();
        $published = Certification::factory()->published()->create();
        $draft = Certification::factory()->draft()->create();
        QaThread::factory()->for($published)->create(['title' => '公開質問']);
        QaThread::factory()->for($draft)->create(['title' => '非公開質問']);

        $this->actingAs($student)
            ->get(route('qa-board.index'))
            ->assertOk()
            ->assertSee('公開質問')
            ->assertDontSee('非公開質問');
    }

    public function test_coach_sees_only_assigned_published_certification_threads(): void
    {
        $coach = User::factory()->coach()->create();
        $assigned = Certification::factory()->published()->create();
        $unassigned = Certification::factory()->published()->create();
        $draft = Certification::factory()->draft()->create();
        $this->assignCoach($assigned, $coach);
        $this->assignCoach($draft, $coach);
        QaThread::factory()->for($assigned)->create(['title' => '担当公開質問']);
        QaThread::factory()->for($unassigned)->create(['title' => '担当外質問']);
        QaThread::factory()->for($draft)->create(['title' => '担当非公開質問']);

        $this->actingAs($coach)
            ->get(route('qa-board.index'))
            ->assertOk()
            ->assertSee('担当公開質問')
            ->assertDontSee('担当外質問')
            ->assertDontSee('担当非公開質問');
    }

    public function test_index_filters_by_certification_status_and_keyword_and_paginates(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        QaThread::factory()->resolved()->for($certification)->create([
            'title' => '対象タイトル',
            'body' => 'ネットワークについて確認したいです',
        ]);
        QaThread::factory()->unresolved()->for($certification)->create([
            'title' => '除外タイトル',
            'body' => 'データベースについて確認したいです',
        ]);

        $response = $this->actingAs($student)->get(route('qa-board.index', [
            'certification_id' => $certification->id,
            'status' => 'resolved',
            'keyword' => 'ネットワーク',
        ]));

        $response->assertOk()->assertSee('対象タイトル')->assertDontSee('除外タイトル');
        $this->assertSame(20, $response->viewData('threads')->perPage());

        $this->actingAs($student)->get(route('qa-board.index', [
            'certification_id' => $certification->id,
            'status' => 'unresolved',
            'keyword' => 'データベース',
        ]))
            ->assertOk()
            ->assertSee('除外タイトル')
            ->assertDontSee('対象タイトル');
    }

    public function test_index_eager_loads_card_relations_and_reply_count(): void
    {
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()->create();
        QaReply::factory()->count(2)->for($thread, 'thread')->create();

        $threadFromView = $this->actingAs($student)
            ->get(route('qa-board.index'))
            ->assertOk()
            ->viewData('threads')
            ->first();

        $this->assertTrue($threadFromView->relationLoaded('user'));
        $this->assertTrue($threadFromView->relationLoaded('certification'));
        $this->assertSame(2, $threadFromView->replies_count);
    }

    public function test_student_and_assigned_coach_can_view_published_thread(): void
    {
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $thread = QaThread::factory()->create();
        $this->assignCoach($thread->certification, $coach);

        $this->actingAs($student)->get(route('qa-board.show', $thread))->assertOk();
        $this->actingAs($coach)->get(route('qa-board.show', $thread))->assertOk();
    }

    public function test_coach_cannot_view_unassigned_thread_and_public_users_cannot_view_unpublished_thread(): void
    {
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $unassigned = QaThread::factory()->create();
        $draftThread = QaThread::factory()
            ->for(Certification::factory()->draft())
            ->create();
        $this->assignCoach($draftThread->certification, $coach);

        $this->actingAs($coach)->get(route('qa-board.show', $unassigned))->assertForbidden();
        $this->actingAs($student)->get(route('qa-board.show', $draftThread))->assertForbidden();
        $this->actingAs($coach)->get(route('qa-board.show', $draftThread))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('qa-board.index'))->assertRedirect('/login');
    }
}
