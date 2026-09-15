<?php

declare(strict_types=1);

namespace App\Console\Commands\Notifications;

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\UseCases\Meeting\SendMeetingReminderAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SendMeetingRemindersCommand extends Command
{
    protected $signature = 'notifications:send-meeting-reminders';

    protected $description = '翌日に予定されている予約済み面談のリマインダーを送信する';

    public function handle(SendMeetingReminderAction $sendReminder): int
    {
        $now = now();
        $dueAt = $now->copy()->startOfDay()->setTime(20, 0);
        if ($now->lessThan($dueAt) || $now->greaterThanOrEqualTo($dueAt->copy()->addMinute())) {
            $this->info('面談リマインダーの配信時刻ではないため、送信をスキップしました。');

            return self::SUCCESS;
        }

        $targetStart = $now->copy()->addDay()->startOfDay();
        $targetEnd = $now->copy()->addDay()->endOfDay();
        $sent = 0;
        $failures = 0;

        Meeting::query()
            ->where('status', MeetingStatus::Reserved->value)
            ->whereBetween('scheduled_at', [$targetStart, $targetEnd])
            ->with(['student', 'coach'])
            ->chunkById(100, function ($meetings) use ($sendReminder, &$sent, &$failures): void {
                foreach ($meetings as $meeting) {
                    foreach ([$meeting->student, $meeting->coach] as $recipient) {
                        if ($recipient === null) {
                            continue;
                        }

                        try {
                            if ($sendReminder($meeting, $recipient)) {
                                $sent++;
                            }
                        } catch (Throwable $exception) {
                            $failures++;
                            Log::error('面談リマインダー送信失敗', [
                                'meeting_id' => $meeting->id,
                                'recipient_id' => $recipient->id,
                                'exception' => $exception,
                            ]);
                            $this->error("面談 {$meeting->id} / 受信者 {$recipient->id} の通知に失敗しました。");
                        }
                    }
                }
            });

        $this->info("面談リマインダーを {$sent} 件送信しました（失敗 {$failures} 件）。");

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
