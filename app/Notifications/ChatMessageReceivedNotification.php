<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ChatMessage;
use Illuminate\Support\Str;

final class ChatMessageReceivedNotification extends BusinessNotification
{
    public function __construct(private readonly ChatMessage $message) {}

    /** @return array<string, string> */
    protected function payload(): array
    {
        $this->message->loadMissing('sender');

        return [
            'notification_type' => 'chat_message_received',
            'title' => '新しいchatメッセージが届きました',
            'message' => $this->message->sender->name.'さん: '.Str::limit(Str::squish($this->message->body), 120),
            'url' => route('chat.show', ['room' => $this->message->chat_room_id], false),
            'chat_room_id' => $this->message->chat_room_id,
            'chat_message_id' => $this->message->id,
        ];
    }

    protected function actionLabel(): string
    {
        return 'chatを確認する';
    }
}
