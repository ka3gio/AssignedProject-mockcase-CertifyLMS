<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Models\QaReply;
use Illuminate\Support\Facades\DB;

/** 回答を物理削除する。 */
final class DestroyAction
{
    public function __invoke(QaReply $reply): void
    {
        DB::transaction(function () use ($reply): void {
            $thread = $reply->thread;
            $reply->delete();
            $thread->touch();
        });
    }
}
