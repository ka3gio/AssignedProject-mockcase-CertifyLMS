<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Meeting;
use App\Models\User;

final class MeetingCanceledNotification extends BusinessNotification
{
    public function __construct(
        private readonly Meeting $meeting,
        private readonly User $canceledBy,
    ) {}

    /** @return array<string, string> */
    protected function payload(): array
    {
        return [
            'notification_type' => 'meeting_canceled',
            'title' => '面談がキャンセルされました',
            'message' => sprintf(
                '%sさんにより、%s の面談がキャンセルされました。',
                $this->canceledBy->name,
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
