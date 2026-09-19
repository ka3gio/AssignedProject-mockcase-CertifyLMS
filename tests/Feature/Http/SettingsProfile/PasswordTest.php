<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SettingsProfile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_roles_and_graduated_student_can_change_password(): void
    {
        $users = [
            User::factory()->student()->create(['password' => 'old-password']),
            User::factory()->coach()->create(['password' => 'old-password']),
            User::factory()->admin()->create(['password' => 'old-password']),
            User::factory()->student()->graduated()->create(['password' => 'old-password']),
        ];

        foreach ($users as $index => $user) {
            $newPassword = "new-password-{$index}";

            $response = $this->actingAs($user)->put(route('settings.password.update'), [
                'current_password' => 'old-password',
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ]);

            $response->assertRedirect(route('settings.profile.edit', ['tab' => 'password']))
                ->assertSessionHas('success', 'パスワードを変更しました。');
            $this->assertTrue(Hash::check($newPassword, $user->fresh()->password));
        }
    }

    public function test_wrong_current_password_is_rejected_in_password_error_bag(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);

        $response = $this->actingAs($user)
            ->from(route('settings.profile.edit', ['tab' => 'password']))
            ->put(route('settings.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response->assertRedirect(route('settings.profile.edit', ['tab' => 'password']))
            ->assertSessionHasErrorsIn('updatePassword', ['current_password']);
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_short_or_unconfirmed_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);

        $this->actingAs($user)->put(route('settings.password.update'), [
            'current_password' => 'old-password',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrorsIn('updatePassword', ['password']);

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_unauthenticated_request_is_redirected_to_login(): void
    {
        $this->put(route('settings.password.update'))
            ->assertRedirect(route('login'));
    }
}
