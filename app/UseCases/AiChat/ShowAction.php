<?php

declare(strict_types=1);

namespace App\UseCases\AiChat;

use App\Models\AiChatConversation;

final class ShowAction
{
    public function __invoke(AiChatConversation $conversation): AiChatConversation
    {
        return $conversation->load([
            'enrollment.certification',
            'section',
            'messages' => fn ($query) => $query->orderBy('created_at')->orderBy('id'),
        ]);
    }
}
