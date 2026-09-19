<?php

declare(strict_types=1);

namespace App\UseCases\AiChat;

use App\Models\AiChatConversation;
use App\Models\User;

final class IndexAction
{
    public function __invoke(User $user): ?AiChatConversation
    {
        return AiChatConversation::query()
            ->where('user_id', $user->id)
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->first();
    }
}
