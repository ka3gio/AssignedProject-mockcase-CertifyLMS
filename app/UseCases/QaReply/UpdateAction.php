<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Models\QaReply;
use Illuminate\Support\Facades\DB;

/** 投稿者が回答本文を更新する。 */
final class UpdateAction
{
    /** @param array{body: string} $validated */
    public function __invoke(QaReply $reply, array $validated): QaReply
    {
        return DB::transaction(function () use ($reply, $validated): QaReply {
            $reply->update(['body' => $validated['body']]);
            $reply->thread->touch();

            return $reply->fresh();
        });
    }
}
