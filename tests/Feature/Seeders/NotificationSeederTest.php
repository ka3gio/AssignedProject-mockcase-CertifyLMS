<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class NotificationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixed_student_and_coach_receive_mixed_notification_samples(): void
    {
        $this->seed(DatabaseSeeder::class);

        $users = User::query()
            ->whereIn('email', ['student@certify-lms.test', 'coach@certify-lms.test'])
            ->get();

        $this->assertCount(2, $users);

        foreach ($users as $user) {
            $notifications = $user->notifications()->get();
            $types = $notifications
                ->pluck('data.notification_type')
                ->unique()
                ->sort()
                ->values()
                ->all();

            $this->assertCount(24, $notifications);
            $this->assertSame(['chat_message_received', 'meeting_reminder'], $types);
            $this->assertTrue($notifications->contains(fn ($notification) => $notification->read_at === null));
            $this->assertTrue($notifications->contains(fn ($notification) => $notification->read_at !== null));
        }
    }
}
