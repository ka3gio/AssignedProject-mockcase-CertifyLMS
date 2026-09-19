<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 業務イベント通知の共通チャンネルとメール表現。
 */
abstract class BusinessNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    /** 初回実行と、段階的な待機を挟む3回の再試行。 */
    public int $tries = 4;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $payload = $this->payload();

        return (new MailMessage)
            ->subject($payload['title'])
            ->greeting($notifiable->name.'さん')
            ->line($payload['message'])
            ->action($this->actionLabel(), url($payload['url']))
            ->line('このメールは Certify LMS のご利用に関する通知です。')
            ->salutation('Certify LMS 運営チーム');
    }

    /**
     * @return array<string, string>
     */
    abstract protected function payload(): array;

    abstract protected function actionLabel(): string;
}
