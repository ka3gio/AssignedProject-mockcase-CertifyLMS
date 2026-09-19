<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Enums\UserRole;
use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/** ロール別の閲覧範囲、一覧フィルタ、関連データの一括取得を担う。 */
final class IndexAction
{
    /** @return array{threads: LengthAwarePaginator, certifications: Collection<int, Certification>} */
    public function __invoke(
        User $viewer,
        ?string $keyword,
        ?string $certificationId,
        ?QaThreadStatus $status,
        int $perPage = 20,
    ): array {
        $query = QaThread::query()
            ->with(['user', 'certification'])
            ->withCount('replies');

        if ($viewer->role !== UserRole::Admin) {
            $query->visibleFor($viewer);
        }

        $query->keyword($keyword);

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        if ($certificationId !== null && $certificationId !== '') {
            $query->where('certification_id', $certificationId);
        }

        $threads = $query
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        $certifications = Certification::query()
            ->when(
                $viewer->role !== UserRole::Admin,
                fn ($query) => $query->published(),
            )
            ->when(
                $viewer->role === UserRole::Coach,
                fn ($query) => $query->assignedTo($viewer),
            )
            ->orderBy('name')
            ->get();

        return [
            'threads' => $threads,
            'certifications' => $certifications,
        ];
    }
}
