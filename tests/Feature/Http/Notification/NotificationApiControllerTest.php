<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

final class NotificationApiControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_latest_twenty_notifications_for_authenticated_user_only(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $other = User::factory()->coach()->inProgress()->create();

        for ($i = 0; $i < 21; $i++) {
            $this->createNotification(
                $user,
                '通知 '.$i,
                createdAt: now()->subMinutes($i),
            );
        }

        $foreign = $this->createNotification($other, '他人宛の通知', createdAt: now()->addMinute());

        $response = $this->actingAs($user)->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonCount(20, 'notifications')
            ->assertJsonPath('notifications.0.title', '通知 0')
            ->assertJsonPath('notifications.0.is_unread', true)
            ->assertJsonPath('notifications.0.url', '/dashboard')
            ->assertJsonPath('unread_count', 21)
            ->assertJsonMissing(['id' => $foreign->id])
            ->assertJsonMissing(['title' => '通知 20'])
            ->assertJsonStructure([
                'notifications' => [[
                    'id',
                    'title',
                    'message',
                    'url',
                    'is_unread',
                    'created_at',
                    'created_at_human',
                ]],
                'unread_count',
            ]);
    }

    public function test_unread_tab_queries_latest_twenty_from_all_unread_notifications(): void
    {
        $user = User::factory()->student()->inProgress()->create();

        for ($i = 0; $i < 20; $i++) {
            $this->createNotification(
                $user,
                '新しい既読 '.$i,
                readAt: now(),
                createdAt: now()->subMinutes($i),
            );
        }

        for ($i = 0; $i < 21; $i++) {
            $this->createNotification(
                $user,
                '古い未読 '.$i,
                createdAt: now()->subDay()->subMinutes($i),
            );
        }

        $response = $this->actingAs($user)->getJson('/api/v1/notifications?tab=unread');

        $response->assertOk()
            ->assertJsonCount(20, 'notifications')
            ->assertJsonPath('notifications.0.title', '古い未読 0')
            ->assertJsonPath('unread_count', 21)
            ->assertJsonMissing(['title' => '新しい既読 0'])
            ->assertJsonMissing(['title' => '古い未読 20']);
    }

    public function test_invalid_tab_falls_back_to_all_notifications(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $this->createNotification($user, '既読通知', readAt: now());

        $this->actingAs($user)
            ->getJson('/api/v1/notifications?tab=invalid')
            ->assertOk()
            ->assertJsonPath('notifications.0.title', '既読通知');
    }

    public function test_mark_as_read_updates_own_notification_and_returns_safe_redirect_and_count(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $notification = $this->createNotification($user, '通知', url: '/dashboard');
        $this->createNotification($user, 'もう一件');

        $response = $this->actingAs($user)
            ->postJson('/api/v1/notifications/'.$notification->id.'/read');

        $response->assertOk()
            ->assertExactJson([
                'redirect_url' => '/dashboard',
                'unread_count' => 1,
            ]);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_as_read_rejects_another_users_notification(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $other = User::factory()->coach()->inProgress()->create();
        $notification = $this->createNotification($other, '他人宛');

        $this->actingAs($user)
            ->postJson('/api/v1/notifications/'.$notification->id.'/read')
            ->assertForbidden();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_mark_as_read_replaces_external_redirect_with_notifications_page(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $notification = $this->createNotification($user, '通知', url: 'https://example.com/phishing');

        $this->actingAs($user)
            ->postJson('/api/v1/notifications/'.$notification->id.'/read')
            ->assertOk()
            ->assertJsonPath('redirect_url', '/notifications');
    }

    public function test_mark_as_read_replaces_backslash_network_path_with_notifications_page(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $notification = $this->createNotification($user, '通知', url: '/\\evil.example');

        $this->actingAs($user)
            ->postJson('/api/v1/notifications/'.$notification->id.'/read')
            ->assertOk()
            ->assertJsonPath('redirect_url', '/notifications');
    }

    public function test_mark_as_read_replaces_control_character_network_path_with_notifications_page(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $notification = $this->createNotification($user, '通知', url: "/\n/evil.example");

        $this->actingAs($user)
            ->postJson('/api/v1/notifications/'.$notification->id.'/read')
            ->assertOk()
            ->assertJsonPath('redirect_url', '/notifications');
    }

    public function test_mark_all_as_read_updates_only_authenticated_users_notifications(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $other = User::factory()->coach()->inProgress()->create();
        $ownA = $this->createNotification($user, 'A');
        $ownB = $this->createNotification($user, 'B');
        $foreign = $this->createNotification($other, '他人宛');

        $this->actingAs($user)
            ->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertExactJson(['unread_count' => 0]);

        $this->assertNotNull($ownA->fresh()->read_at);
        $this->assertNotNull($ownB->fresh()->read_at);
        $this->assertNull($foreign->fresh()->read_at);
    }

    public function test_admin_can_access_only_own_notifications_through_api(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->inProgress()->create();
        $own = $this->createNotification($admin, '管理者本人宛');
        $foreign = $this->createNotification($student, '受講生宛');

        $this->actingAs($admin)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonFragment(['id' => $own->id])
            ->assertJsonMissing(['id' => $foreign->id]);
    }

    public function test_unauthenticated_user_cannot_use_notification_api(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
        $this->postJson('/api/v1/notifications/missing/read')->assertUnauthorized();
        $this->postJson('/api/v1/notifications/read-all')->assertUnauthorized();
    }

    private function createNotification(
        User $user,
        string $title,
        mixed $readAt = null,
        string $url = '/dashboard',
        ?Carbon $createdAt = null,
    ): DatabaseNotification {
        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'test',
            'data' => [
                'notification_type' => 'test',
                'title' => $title,
                'message' => 'テスト通知です。',
                'url' => $url,
            ],
            'read_at' => $readAt,
        ]);

        if ($createdAt !== null) {
            $notification->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();
        }

        return $notification;
    }
}
