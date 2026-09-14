<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Services\BusinessNotificationService;
use Illuminate\Support\Facades\DB;

/** 質問へ回答を投稿する。 */
final class StoreAction
{
    public function __construct(private readonly BusinessNotificationService $notifications) {}

    /** @param array{body: string} $validated */
    public function __invoke(User $user, QaThread $thread, array $validated): QaReply
    {
        return DB::transaction(function () use ($user, $thread, $validated): QaReply {
            $reply = $thread->replies()->create([
                'user_id' => $user->id,
                'body' => $validated['body'],
            ]);
            $thread->touch();

            DB::afterCommit(fn () => $this->notifications->notifyQaReplyReceived($reply));

            return $reply;
        });
    }
}
