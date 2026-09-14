<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

final class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_index_lists_only_authenticated_users_notifications(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $other = User::factory()->coach()->inProgress()->create();
        $own = $this->createNotification($user, '自分宛の通知');
        $foreign = $this->createNotification($other, '他人宛の通知');

        $response = $this->actingAs($user)->get(route('notifications.index'));

        $response->assertOk()->assertViewIs('notifications.index');
        $response->assertViewHas('notifications', fn ($notifications) => $notifications->contains('id', $own->id)
            && ! $notifications->contains('id', $foreign->id));
    }

    public function test_unread_tab_filters_read_notifications_but_count_is_global_unread_count(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $unread = $this->createNotification($user, '未読');
        $read = $this->createNotification($user, '既読', now());

        $response = $this->actingAs($user)->get(route('notifications.index', ['tab' => 'unread']));

        $response->assertOk();
        $response->assertViewHas('tab', 'unread');
        $response->assertViewHas('unreadCount', 1);
        $response->assertViewHas('notifications', fn ($notifications) => $notifications->contains('id', $unread->id)
            && ! $notifications->contains('id', $read->id));
    }

    public function test_click_marks_own_notification_as_read_and_redirects_to_internal_business_url(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $notification = $this->createNotification($user, '通知', null, '/dashboard');

        $response = $this->actingAs($user)
            ->post(route('notifications.markAsRead', $notification));

        $response->assertRedirect('/dashboard');
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_external_redirect_url_is_rejected(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $notification = $this->createNotification($user, '通知', null, 'https://example.com/phishing');

        $response = $this->actingAs($user)
            ->post(route('notifications.markAsRead', $notification));

        $response->assertRedirect(route('notifications.index'));
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $other = User::factory()->coach()->inProgress()->create();
        $notification = $this->createNotification($other, '他人宛');

        $this->actingAs($user)
            ->post(route('notifications.markAsRead', $notification))
            ->assertForbidden();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_mark_all_marks_only_authenticated_users_unread_notifications(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $other = User::factory()->coach()->inProgress()->create();
        $ownA = $this->createNotification($user, 'A');
        $ownB = $this->createNotification($user, 'B');
        $foreign = $this->createNotification($other, '他人宛');

        $this->actingAs($user)
            ->from(route('notifications.index'))
            ->post(route('notifications.markAllAsRead'))
            ->assertRedirect(route('notifications.index'));

        $this->assertNotNull($ownA->fresh()->read_at);
        $this->assertNotNull($ownB->fresh()->read_at);
        $this->assertNull($foreign->fresh()->read_at);
    }

    public function test_graduated_user_can_view_and_read_existing_notifications(): void
    {
        $user = User::factory()->student()->graduated()->create();
        $notification = $this->createNotification($user, '卒業前の通知', null, '/dashboard');

        $this->actingAs($user)->get(route('notifications.index'))->assertOk();
        $this->actingAs($user)
            ->post(route('notifications.markAsRead', $notification))
            ->assertRedirect('/dashboard');

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_index_is_paginated_at_twenty_items(): void
    {
        $user = User::factory()->student()->inProgress()->create();

        for ($i = 0; $i < 21; $i++) {
            $this->createNotification($user, '通知 '.$i);
        }

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertViewHas('notifications', fn ($notifications) => $notifications->count() === 20
                && $notifications->hasPages());
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get(route('notifications.index'))->assertRedirect('/login');
        $this->post(route('notifications.markAllAsRead'))->assertRedirect('/login');
    }

    private function createNotification(
        User $user,
        string $title,
        mixed $readAt = null,
        string $url = '/dashboard',
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

        return $notification;
    }
}
