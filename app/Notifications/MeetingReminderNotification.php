<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Meeting;

final class MeetingReminderNotification extends BusinessNotification
{
    public function __construct(private readonly Meeting $meeting) {}

    protected function payload(): array
    {
        $this->meeting->loadMissing(['student', 'coach']);

        $scheduledAt = $this->meeting->scheduled_at->format('Y/m/d H:i');
        $message = "明日、面談が予定されています。開始日時: {$scheduledAt}。受講生: {$this->meeting->student->name}、担当コーチ: {$this->meeting->coach->name}。";

        return [
            'notification_type' => 'meeting_reminder',
            'meeting_id' => $this->meeting->id,
            'title' => '面談のリマインダー（明日）',
            'message' => $message,
            'body' => $message,
            'url' => route('meetings.show', $this->meeting, false),
        ];
    }

    protected function actionLabel(): string
    {
        return '面談詳細を確認する';
    }
}
