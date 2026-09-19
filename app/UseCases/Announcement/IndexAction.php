<?php

declare(strict_types=1);

namespace App\UseCases\Announcement;

use App\Models\Announcement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 配信済みお知らせを配信日時の新しい順で取得する。
 */
final class IndexAction
{
    public function __invoke(int $perPage = 20): LengthAwarePaginator
    {
        return Announcement::query()
            ->with(['targetCertification', 'targetUser', 'createdBy'])
            ->orderByDesc('dispatched_at')
            ->paginate($perPage);
    }
}
