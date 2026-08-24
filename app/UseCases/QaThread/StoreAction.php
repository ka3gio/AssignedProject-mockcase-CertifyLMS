<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 質問を新規作成するユースケース。
 */
final class StoreAction
{
	/**
	 * @param array{name: string, category_id: string, difficulty: string, description?: ?string} $validated
	 */
	public function __invoke(User $user, array $validated): QaThread
	{
		// dd($validated);
		return DB::transaction(fn() => QaThread::create([
			'certification_id' => $validated['certification_id'],
			'user_id' => $user->id,
			'title' => $validated['title'],
			'body' => $validated['body'],
			'status' => QaThreadStatus::Unresolved->value,
		]));
	}
}
