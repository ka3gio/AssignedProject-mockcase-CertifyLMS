<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SettingsProfile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_roles_and_graduated_student_can_view_own_profile(): void
    {
        $users = [
            User::factory()->student()->create(),
            User::factory()->coach()->create(),
            User::factory()->admin()->create(),
            User::factory()->student()->graduated()->create(),
        ];

        foreach ($users as $user) {
            $response = $this->actingAs($user)->get(route('settings.profile.edit'));

            $response->assertOk()
                ->assertViewIs('settings.profile')
                ->assertViewHas('user', fn (User $viewUser) => $viewUser->is($user));
        }
    }

    public function test_password_tab_can_be_displayed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('settings.profile.edit', ['tab' => 'password']))
            ->assertOk()
            ->assertSee('現在のパスワード')
            ->assertDontSee('新しい画像を選ぶ');
    }

    public function test_unauthenticated_request_is_redirected_to_login(): void
    {
        $this->get(route('settings.profile.edit'))
            ->assertRedirect(route('login'));
    }
}
