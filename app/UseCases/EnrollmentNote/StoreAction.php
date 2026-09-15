<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentNote;

use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 受講登録メモの新規作成ユースケース。
 */
final class StoreAction
{
    /**
     * @param array{body: string} $validated EnrollmentNote/StoreRequest::rules() で検証済
     */
    public function __invoke(Enrollment $enrollment, User $author, array $validated): EnrollmentNote
    {
        return DB::transaction(fn () => $enrollment->notes()->create([
            'author_user_id' => $author->id,
            'body' => $validated['body'],
        ]));
    }
}
