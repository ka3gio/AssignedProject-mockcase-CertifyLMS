<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AnnouncementTargetType;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\User;
use App\Notifications\Announcement\AdminAnnouncementNotification;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Notification;

/**
 * 3 種類の配信対象と、受講生側の未読通知を確認するための開発用データ。
 * Seeder 実行時にメールは送らず database チャネルだけへ投入する。
 */
final class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()
            ->where('role', UserRole::Admin->value)
            ->orderBy('created_at')
            ->first();

        if ($admin === null) {
            $this->command?->warn('AnnouncementSeeder: 管理者が存在しません。先に UserSeeder を実行してください。');

            return;
        }

        $allStudents = $this->eligibleStudents()->get();
        $this->createAndNotify(
            $admin,
            AnnouncementTargetType::AllStudents,
            'システムメンテナンスのお知らせ',
            "今週土曜日 22:00〜23:00 にシステムメンテナンスを実施します。\n作業中は一時的にサービスをご利用いただけません。",
            $allStudents,
            dispatchedAt: now()->subDays(3),
        );

        $certification = Certification::query()
            ->whereHas('enrollments', fn (Builder $query) => $query
                ->whereIn('status', [
                    EnrollmentStatus::Learning->value,
                    EnrollmentStatus::Passed->value,
                ])
                ->whereHas('user', fn (Builder $users) => $users
                    ->where('role', UserRole::Student->value)
                    ->where('status', UserStatus::InProgress->value)))
            ->orderBy('created_at')
            ->first();
        if ($certification !== null) {
            $certificationStudents = $this->eligibleStudents()
                ->whereHas('enrollments', fn (Builder $query) => $query
                    ->where('certification_id', $certification->id)
                    ->whereIn('status', [
                        EnrollmentStatus::Learning->value,
                        EnrollmentStatus::Passed->value,
                    ]))
                ->get();

            $this->createAndNotify(
                $admin,
                AnnouncementTargetType::Certification,
                $certification->name.' 教材更新のお知らせ',
                '対象資格の教材を更新しました。最新の内容をご確認ください。',
                $certificationStudents,
                certificationId: $certification->id,
                dispatchedAt: now()->subDays(2),
            );
        }

        $student = $allStudents->first();
        if ($student !== null) {
            $this->createAndNotify(
                $admin,
                AnnouncementTargetType::User,
                '学習フォローのご案内',
                '現在の学習状況について、担当コーチからのメッセージをご確認ください。',
                new Collection([$student]),
                userId: $student->id,
                dispatchedAt: now()->subDay(),
            );
        }
    }

    /** @return Builder<User> */
    private function eligibleStudents(): Builder
    {
        return User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->orderBy('id');
    }

    /** @param Collection<int, User> $recipients */
    private function createAndNotify(
        User $admin,
        AnnouncementTargetType $targetType,
        string $title,
        string $body,
        Collection $recipients,
        ?string $certificationId = null,
        ?string $userId = null,
        ?CarbonInterface $dispatchedAt = null,
    ): void {
        if ($recipients->isEmpty()) {
            return;
        }

        $announcement = Announcement::create([
            'title' => $title,
            'body' => $body,
            'target_type' => $targetType->value,
            'target_certification_id' => $certificationId,
            'target_user_id' => $userId,
            'created_by_user_id' => $admin->id,
            'dispatched_count' => $recipients->count(),
            'dispatched_at' => $dispatchedAt ?? now(),
        ]);

        Notification::sendNow(
            $recipients,
            new AdminAnnouncementNotification($announcement),
            ['database'],
        );
    }
}
