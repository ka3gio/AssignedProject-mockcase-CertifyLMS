<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Exceptions\MeetingPack\MeetingPackNotDeletableException;
use App\Models\MeetingPack;

/**
 * 面談パックを物理削除する。公開中、または決済履歴がある場合は削除不可とする。
 */
final class DestroyAction
{
    /**
     * @throws MeetingPackNotDeletableException
     */
    public function __invoke(MeetingPack $plan): void
    {
        $connection = $plan->getConnection();

        $connection->transaction(function () use ($plan, $connection) {
            $plan = $plan->newQuery()->lockForUpdate()->findOrFail($plan->getKey());

            if ($plan->status === MeetingPackStatus::Published) {
                throw MeetingPackNotDeletableException::forPublished();
            }

            // payments は後続の追加面談購入 Feature 所有。未導入時は履歴照合を行わない。
            // 連携契約: payments.meeting_pack_id。導入時は並行購入との整合性のため削除制限 FK も追加する。
            // Payment モデルや決済ステータスには依存せず、紐づく履歴が 1 件でもあれば保護する。
            if ($connection->getSchemaBuilder()->hasTable('payments')
                && $connection->table('payments')->where('meeting_pack_id', $plan->getKey())->exists()) {
                throw MeetingPackNotDeletableException::forPaymentHistory();
            }

            $plan->delete();
        });
    }
}
