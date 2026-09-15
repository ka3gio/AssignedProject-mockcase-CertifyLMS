<?php

declare(strict_types=1);

namespace App\Notifications\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Models\Announcement;
use App\Notifications\BusinessNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/**
 * 管理者お知らせをアプリ内通知とメールへ同期配信する。
 */
final class AdminAnnouncementNotification extends BusinessNotification
{
    public function __construct(public readonly Announcement $announcement) {}

    /** @return array<string, string> */
    protected function payload(): array
    {
        return [
            'notification_type' => 'admin_announcement',
            'announcement_id' => $this->announcement->id,
            'title' => $this->announcement->title,
            'message' => Str::limit($this->announcement->body, 160),
            'body' => $this->announcement->body,
            'url' => route('notifications.show', $this->id, false),
        ];
    }

    protected function actionLabel(): string
    {
        return 'お知らせを確認する';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $payload = $this->payload();
        $greeting = match ($this->announcement->target_type) {
            AnnouncementTargetType::AllStudents => '受講生の皆様へ',
            AnnouncementTargetType::Certification => ($this->announcement->targetCertification?->name ?? '対象資格').'にご登録の皆様へ',
            AnnouncementTargetType::User => $notifiable->name.'さんへ',
        };

        return (new MailMessage)
            ->subject($payload['title'])
            ->greeting($greeting)
            ->line($payload['body'])
            ->action($this->actionLabel(), url($payload['url']))
            ->line('このメールは Certify LMS のご利用に関する通知です。')
            ->salutation('Certify LMS 運営チーム');
    }
}
