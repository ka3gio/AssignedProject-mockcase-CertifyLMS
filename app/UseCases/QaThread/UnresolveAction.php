<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;
use Illuminate\Support\Facades\DB;

/** 解決済みの質問を未解決へ戻す。 */
final class UnresolveAction
{
    public function __invoke(QaThread $thread): QaThread
    {
        if ($thread->status === QaThreadStatus::Unresolved) {
            return $thread;
        }

        return DB::transaction(function () use ($thread): QaThread {
            $thread->update([
                'status' => QaThreadStatus::Unresolved->value,
                'resolved_at' => null,
            ]);

            return $thread->fresh();
        });
    }
}
