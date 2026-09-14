<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SettingsProfile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_roles_and_graduated_student_can_upload_avatar(): void
    {
        Storage::fake('public');

        $users = [
            User::factory()->student()->create(),
            User::factory()->coach()->create(),
            User::factory()->admin()->create(),
            User::factory()->student()->graduated()->create(),
        ];

        foreach ($users as $index => $user) {
            $response = $this->actingAs($user)->post(route('settings.avatar.store'), [
                'avatar' => UploadedFile::fake()->image("avatar-{$index}.png", 200, 200),
            ]);

            $response->assertRedirect(route('settings.profile.edit'))
                ->assertSessionHas('success', 'アイコン画像を更新しました。');

            $path = $this->pathFromAvatarUrl($user->fresh()->avatar_url);
            Storage::disk('public')->assertExists($path);
        }
    }

    public function test_uploading_new_avatar_replaces_and_deletes_old_uploaded_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/old.png', 'old-avatar');
        $user = User::factory()->create([
            'avatar_url' => '/storage/avatars/old.png',
        ]);

        $this->actingAs($user)->post(route('settings.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('new.jpg', 200, 200),
        ])->assertRedirect(route('settings.profile.edit'));

        Storage::disk('public')->assertMissing('avatars/old.png');
        Storage::disk('public')->assertExists($this->pathFromAvatarUrl($user->fresh()->avatar_url));
    }

    public function test_avatar_can_be_deleted_and_returns_to_unset_state(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/current.png', 'avatar');
        $user = User::factory()->create([
            'avatar_url' => '/storage/avatars/current.png',
        ]);

        $response = $this->actingAs($user)->delete(route('settings.avatar.destroy'));

        $response->assertRedirect(route('settings.profile.edit'))
            ->assertSessionHas('success', 'アイコン画像を削除しました。');
        $this->assertNull($user->fresh()->avatar_url);
        Storage::disk('public')->assertMissing('avatars/current.png');
    }

    public function test_avatar_validation_rejects_invalid_format_and_oversized_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('settings.avatar.store'), [
                'avatar' => UploadedFile::fake()->create('avatar.svg', 10, 'image/svg+xml'),
            ])
            ->assertSessionHasErrors(['avatar']);

        $this->actingAs($user)
            ->post(route('settings.avatar.store'), [
                'avatar' => UploadedFile::fake()->create('avatar.png', 2049, 'image/png'),
            ])
            ->assertSessionHasErrors(['avatar']);

        $this->assertNull($user->fresh()->avatar_url);
    }

    public function test_unauthenticated_avatar_operations_are_redirected_to_login(): void
    {
        $this->post(route('settings.avatar.store'))
            ->assertRedirect(route('login'));
        $this->delete(route('settings.avatar.destroy'))
            ->assertRedirect(route('login'));
    }

    private function pathFromAvatarUrl(?string $url): string
    {
        $this->assertNotNull($url);
        $this->assertStringStartsWith('/storage/', $url);

        return substr($url, strlen('/storage/'));
    }
}
