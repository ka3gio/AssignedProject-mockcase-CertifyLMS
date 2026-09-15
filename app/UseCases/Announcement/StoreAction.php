<?php

declare(strict_types=1);

namespace App\UseCases\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Models\Announcement;
use App\Models\User;
use App\Services\BusinessNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * お知らせと配信実績を作成し、対象受講生へアプリ内通知・メールを同期配信する。
 * 途中失敗時は DB をロールバックする。先行して送信済みのメールが再操作時に重複し得る点は MVP の許容事項。
 */
final class StoreAction
{
    public function __construct(private readonly BusinessNotificationService $notifications) {}

    /**
     * @param array{title: string, body: string, target_type: string, target_certification_id?: ?string, target_user_id?: ?string} $validated
     */
    public function __invoke(User $admin, array $validated): Announcement
    {
        $targetType = AnnouncementTargetType::from($validated['target_type']);

        return DB::transaction(function () use ($admin, $validated, $targetType): Announcement {
            $announcement = Announcement::create([
                'title' => $validated['title'],
                'body' => $validated['body'],
                'target_type' => $targetType->value,
                'target_certification_id' => $targetType === AnnouncementTargetType::Certification
                    ? $validated['target_certification_id']
                    : null,
                'target_user_id' => $targetType === AnnouncementTargetType::User
                    ? $validated['target_user_id']
                    : null,
                'created_by_user_id' => $admin->id,
                'dispatched_count' => 0,
                'dispatched_at' => now(),
            ]);

            $dispatchedCount = $this->notifications->notifyAnnouncement($announcement);

            if ($dispatchedCount === 0) {
                throw ValidationException::withMessages([
                    'target_type' => '配信対象となる受講生がいません。',
                ]);
            }

            $announcement->update(['dispatched_count' => $dispatchedCount]);

            return $announcement;
        });
    }
}
