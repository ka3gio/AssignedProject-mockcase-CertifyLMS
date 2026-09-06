<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** 質問を新規作成する。 */
final class StoreAction
{
    /** @param array{certification_id: string, title: string, body: string} $validated */
    public function __invoke(User $user, array $validated): QaThread
    {
        return DB::transaction(fn () => QaThread::create([
            'certification_id' => $validated['certification_id'],
            'user_id' => $user->id,
            'title' => $validated['title'],
            'body' => $validated['body'],
            'status' => QaThreadStatus::Unresolved->value,
        ]));
    }
}
