<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Models\User;
use App\Notifications\BusinessNotification;
use Tests\TestCase;

final class BusinessNotificationTest extends TestCase
{
    public function test_exposes_common_database_and_mail_representation(): void
    {
        $user = new User(['name' => 'テストユーザー']);
        $notification = new class extends BusinessNotification
        {
            protected function payload(): array
            {
                return [
                    'notification_type' => 'sample',
                    'title' => '通知タイトル',
                    'message' => '通知本文',
                    'url' => '/dashboard',
                ];
            }

            protected function actionLabel(): string
            {
                return '確認する';
            }
        };

        $this->assertSame(['database', 'mail'], $notification->via($user));
        $this->assertSame('sample', $notification->toArray($user)['notification_type']);
        $this->assertSame('通知タイトル', $notification->toMail($user)->subject);
        $this->assertStringEndsWith('/dashboard', $notification->toMail($user)->actionUrl);
    }
}
