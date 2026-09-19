<?php

declare(strict_types=1);

namespace App\UseCases\AiChat;

use App\Models\AiChatConversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DestroyAction
{
    public function __construct(private readonly IndexAction $index) {}

    public function __invoke(User $user, AiChatConversation $conversation): ?AiChatConversation
    {
        return DB::transaction(function () use ($user, $conversation): ?AiChatConversation {
            $conversation->delete();

            return ($this->index)($user);
        });
    }
}
