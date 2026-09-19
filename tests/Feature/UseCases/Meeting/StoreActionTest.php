<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\MeetingStatus;
use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\MeetingReservedNotification;
use App\UseCases\Meeting\StoreAction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class StoreActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_reserves_a_meeting_and_performs_all_related_side_effects(): void
    {
        Notification::fake();
        [$student, $coach, $enrollment, $scheduledAt] = $this->reservationContext();
        $meeting = app(StoreAction::class)($enrollment, [
            'scheduled_at' => $scheduledAt->format('Y-m-d\TH:i:s'),
            'topic' => '学習計画の相談',
        ]);

        $this->assertSame(MeetingStatus::Reserved, $meeting->status);
        $this->assertSame($student->id, $meeting->student_id);
        $this->assertSame($coach->id, $meeting->coach_id);
        $this->assertNotNull($meeting->meeting_quota_transaction_id);
        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $student->id,
            'type' => MeetingQuotaTransactionType::Consumed->value,
            'amount' => -1,
            'related_meeting_id' => $meeting->id,
        ]);
        Notification::assertSentTo($coach, MeetingReservedNotification::class);
    }

    public function test_rolls_back_the_reservation_when_notification_fails(): void
    {
        [, , $enrollment, $scheduledAt] = $this->reservationContext();
        Notification::shouldReceive('send')
            ->once()
            ->andThrow(new RuntimeException('notification failed'));

        try {
            app(StoreAction::class)($enrollment, [
                'scheduled_at' => $scheduledAt->format('Y-m-d\TH:i:s'),
                'topic' => 'ロールバック確認',
            ]);
            $this->fail('通知失敗が伝播するはずです。');
        } catch (RuntimeException $exception) {
            $this->assertSame('notification failed', $exception->getMessage());
        }

        $this->assertDatabaseCount('meetings', 0);
        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    /**
     * @return array{User, User, Enrollment, Carbon}
     */
    private function reservationContext(): array
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        $certification = Certification::factory()->published()->create();
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '18:00:00')->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0);

        return [$student, $coach, $enrollment, $scheduledAt];
    }
}
