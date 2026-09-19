<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Enums\AnnouncementTargetType;
use App\Models\Announcement;
use App\Models\Certification;
use App\Models\User;
use App\Notifications\Announcement\AdminAnnouncementNotification;
use Tests\TestCase;

final class AdminAnnouncementNotificationTest extends TestCase
{
    public function test_all_students_mail_greets_students_and_contains_full_body(): void
    {
        $body = str_repeat('お知らせ本文です。', 30);
        $notification = $this->notification(AnnouncementTargetType::AllStudents, $body);
        $mail = $notification->toMail(new User(['name' => '山田']));

        $this->assertSame('受講生の皆様へ', $mail->greeting);
        $this->assertContains($body, $mail->introLines);
        $this->assertContains('このメールは Certify LMS のご利用に関する通知です。', $mail->outroLines);
    }

    public function test_certification_mail_greets_students_registered_for_that_certification(): void
    {
        $notification = $this->notification(AnnouncementTargetType::Certification);
        $notification->announcement->setRelation('targetCertification', new Certification(['name' => '日商簿記']));

        $mail = $notification->toMail(new User(['name' => '山田']));

        $this->assertSame('日商簿記にご登録の皆様へ', $mail->greeting);
    }

    public function test_user_target_mail_greets_the_recipient_by_name(): void
    {
        $notification = $this->notification(AnnouncementTargetType::User);

        $mail = $notification->toMail(new User(['name' => '山田花子']));

        $this->assertSame('山田花子さんへ', $mail->greeting);
    }

    private function notification(
        AnnouncementTargetType $targetType,
        string $body = 'お知らせ本文です。',
    ): AdminAnnouncementNotification {
        $announcement = new Announcement([
            'title' => '運営からのお知らせ',
            'body' => $body,
            'target_type' => $targetType->value,
        ]);
        $notification = new AdminAnnouncementNotification($announcement);
        $notification->id = '11111111-1111-4111-8111-111111111111';

        return $notification;
    }
}
