<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SettingsProfile;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_roles_and_graduated_student_can_update_profile(): void
    {
        $users = [
            User::factory()->student()->create(),
            User::factory()->coach()->create(),
            User::factory()->admin()->create(),
            User::factory()->student()->graduated()->create(),
        ];

        foreach ($users as $index => $user) {
            $response = $this->actingAs($user)->patch(route('settings.profile.update'), [
                'name' => '更新ユーザー'.$index,
                'bio' => '更新後の自己紹介',
                'meeting_url' => $user->role === UserRole::Coach
                    ? 'https://meet.example.com/new-room'
                    : null,
            ]);

            $response->assertRedirect(route('settings.profile.edit'))
                ->assertSessionHas('success', 'プロフィールを更新しました。');

            $fresh = $user->fresh();
            $this->assertSame('更新ユーザー'.$index, $fresh->name);
            $this->assertSame('更新後の自己紹介', $fresh->bio);
        }
    }

    public function test_email_role_status_and_non_coach_meeting_url_cannot_be_updated(): void
    {
        $student = User::factory()->student()->graduated()->create([
            'email' => 'before@example.test',
        ]);

        $this->actingAs($student)->patch(route('settings.profile.update'), [
            'name' => '更新後',
            'bio' => null,
            'email' => 'after@example.test',
            'role' => UserRole::Admin->value,
            'status' => UserStatus::InProgress->value,
            'meeting_url' => 'https://meet.example.com/forbidden',
        ])->assertRedirect(route('settings.profile.edit'));

        $fresh = $student->fresh();
        $this->assertSame('before@example.test', $fresh->email);
        $this->assertSame(UserRole::Student, $fresh->role);
        $this->assertSame(UserStatus::Graduated, $fresh->status);
        $this->assertNull($fresh->meeting_url);
    }

    public function test_coach_can_clear_meeting_url(): void
    {
        $coach = User::factory()->coach()->create([
            'meeting_url' => 'https://meet.example.com/old-room',
        ]);

        $this->actingAs($coach)->patch(route('settings.profile.update'), [
            'name' => $coach->name,
            'bio' => $coach->bio,
            'meeting_url' => '',
        ])->assertRedirect(route('settings.profile.edit'));

        $this->assertNull($coach->fresh()->meeting_url);
    }

    public function test_profile_validation_rejects_invalid_values(): void
    {
        $coach = User::factory()->coach()->create();

        $response = $this->actingAs($coach)
            ->from(route('settings.profile.edit'))
            ->patch(route('settings.profile.update'), [
                'name' => str_repeat('a', 51),
                'bio' => str_repeat('b', 1001),
                'meeting_url' => 'not-a-url',
            ]);

        $response->assertRedirect(route('settings.profile.edit'))
            ->assertSessionHasErrors(['name', 'bio', 'meeting_url']);
    }

    public function test_unauthenticated_request_is_redirected_to_login(): void
    {
        $this->patch(route('settings.profile.update'), [
            'name' => '更新後',
        ])->assertRedirect(route('login'));
    }
}
