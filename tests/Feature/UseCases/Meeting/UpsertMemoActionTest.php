<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Exceptions\Mentoring\MeetingStatusTransitionException;
use App\Models\Meeting;
use App\UseCases\Meeting\UpsertMemoAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpsertMemoActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_and_updates_one_memo_for_the_meeting(): void
    {
        $meeting = Meeting::factory()->completed()->create();
        $action = app(UpsertMemoAction::class);

        $created = $action($meeting, '初回メモ');
        $updated = $action($meeting, '更新メモ');

        $this->assertSame($created->id, $updated->id);
        $this->assertSame('更新メモ', $updated->body);
        $this->assertDatabaseCount('meeting_memos', 1);
    }

    public function test_rejects_a_memo_for_a_canceled_meeting(): void
    {
        $meeting = Meeting::factory()->canceled()->create();

        $this->expectException(MeetingStatusTransitionException::class);

        app(UpsertMemoAction::class)($meeting, '保存できないメモ');
    }
}
