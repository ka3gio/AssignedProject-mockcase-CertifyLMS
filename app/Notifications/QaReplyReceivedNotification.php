<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\QaReply;
use Illuminate\Support\Str;

final class QaReplyReceivedNotification extends BusinessNotification
{
    public function __construct(private readonly QaReply $reply) {}

    /** @return array<string, string> */
    protected function payload(): array
    {
        $this->reply->loadMissing(['thread', 'user']);

        return [
            'notification_type' => 'qa_reply_received',
            'title' => '質問に回答が届きました',
            'message' => sprintf(
                '%sさんが「%s」に回答しました: %s',
                $this->reply->user->name,
                Str::limit(Str::squish($this->reply->thread->title), 50),
                Str::limit(Str::squish($this->reply->body), 100),
            ),
            'url' => route('qa-board.show', $this->reply->thread, false),
            'qa_thread_id' => $this->reply->qa_thread_id,
            'qa_reply_id' => $this->reply->id,
        ];
    }

    protected function actionLabel(): string
    {
        return '回答を確認する';
    }
}
