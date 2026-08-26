<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaBoard;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** 質問一覧の関連取得でN+1が再発しないことを検証する。 */
class IndexQueryCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_query_count_does_not_grow_with_thread_count(): void
    {
        $viewer = User::factory()->student()->create();
        $author = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $this->createThreads($certification, $author, 2);

        $baseline = $this->countQueriesFor(
            fn () => $this->actingAs($viewer)->get(route('qa-board.index')),
        );

        $this->createThreads($certification, $author, 10);
        $scaled = $this->countQueriesFor(
            fn () => $this->actingAs($viewer)->get(route('qa-board.index')),
        );

        $this->assertLessThanOrEqual(
            $baseline + 3,
            $scaled,
            "質問一覧でN+1が発生している（基準 {$baseline} → 増加後 {$scaled}）",
        );
    }

    private function createThreads(Certification $certification, User $author, int $count): void
    {
        QaThread::factory()
            ->count($count)
            ->for($certification)
            ->for($author, 'user')
            ->create()
            ->each(fn (QaThread $thread) => QaReply::factory()->for($thread, 'thread')->create());
    }

    private function countQueriesFor(\Closure $callback): int
    {
        $count = 0;
        DB::listen(function () use (&$count): void {
            $count++;
        });

        $callback();

        return $count;
    }
}
