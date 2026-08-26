<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Models\QaThread;
use Illuminate\Support\Facades\DB;

/** 質問スレッドを回答ごと物理削除する。削除可否はPolicyで判定する。 */
final class DestroyAction
{
    public function __invoke(QaThread $thread): void
    {
        DB::transaction(fn () => $thread->delete());
    }
}
