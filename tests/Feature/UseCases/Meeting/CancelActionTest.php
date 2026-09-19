<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingCanceledNotification;
use App\UseCases\Meeting\CancelAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class CancelActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancels_refunds_and_notifies_the_other_party(): void
    {
        Notification::fake();
        [$meeting, $student, $coach] = $this->cancelContext();
        $result = app(CancelAction::class)($meeting, $student);

        $this->assertSame(MeetingStatus::Canceled, $result->status);
        $this->assertSame($student->id, $result->canceled_by_user_id);
        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $student->id,
            'type' => MeetingQuotaTransactionType::Refunded->value,
            'amount' => 1,
            'related_meeting_id' => $meeting->id,
        ]);
        Notification::assertSentTo($coach, MeetingCanceledNotification::class);
    }

    public function test_rolls_back_the_cancellation_when_notification_fails(): void
    {
        [$meeting, $student] = $this->cancelContext();
        Notification::shouldReceive('send')
            ->once()
            ->andThrow(new RuntimeException('notification failed'));

        try {
            app(CancelAction::class)($meeting, $student);
            $this->fail('通知失敗が伝播するはずです。');
        } catch (RuntimeException $exception) {
            $this->assertSame('notification failed', $exception->getMessage());
        }

        $meeting->refresh();
        $this->assertSame(MeetingStatus::Reserved, $meeting->status);
        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    /**
     * @return array{Meeting, User, User}
     */
    private function cancelContext(): array
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 5]);
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);

        return [$meeting, $student, $coach];
    }
}
