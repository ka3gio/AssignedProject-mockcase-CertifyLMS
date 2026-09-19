<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Meeting;

final class MeetingReservedNotification extends BusinessNotification
{
    public function __construct(private readonly Meeting $meeting) {}

    /** @return array<string, string> */
    protected function payload(): array
    {
        $this->meeting->loadMissing('student');

        return [
            'notification_type' => 'meeting_reserved',
            'title' => '面談が予約されました',
            'message' => sprintf(
                '%sさんとの面談が %s に予約されました。',
                $this->meeting->student->name,
                $this->meeting->scheduled_at->format('Y/m/d H:i'),
            ),
            'url' => route('meetings.show', $this->meeting, false),
            'meeting_id' => $this->meeting->id,
        ];
    }

    protected function actionLabel(): string
    {
        return '面談詳細を確認する';
    }
}
