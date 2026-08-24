<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\CertificationStatus;
use App\Enums\QaThreadStatus;
use App\Enums\UserRole;
use App\Models\QaThread;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 */
final class IndexAction
{
	public function __invoke(
		User $viewer,
		?string $keyword,
		?string $certification_id,
		?QaThreadStatus $status,
		int $perPage = 20,
	): array {

		$query = QaThread::query()
			->whereHas('certification', function ($q) {
				$q->where('status', CertificationStatus::Published->value);
			})
			->with(['user', 'certification'])
			->withCount('replies');

		if ($viewer->role === UserRole::Coach) {
			$query->whereHas('certification', function ($q) use ($viewer) {
				$q->assignedTo($viewer);
			});
		}

		$query->keyword($keyword);

		if ($status !== null) {
			$query->where('status', $status->value);
		}

		if ($certification_id !== null && $certification_id !== '') {
			$query->where('certification_id', $certification_id);
		}

		$threads = $query->orderByDesc('created_at')->paginate($perPage)->withQueryString();

		$certification = Certification::query()
			->where('status', CertificationStatus::Published->value)
			->when($viewer->role === UserRole::Coach, function ($q) use ($viewer) {
				$q->assignedTo($viewer);
			})
			->orderBy('name')
			->get();

		return [
			'threads' => $threads,
			'certifications' => $certification,
		];
	}
}
