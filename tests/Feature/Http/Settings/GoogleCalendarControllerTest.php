<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Settings;

use App\Models\GoogleCalendarCredential;
use App\Models\User;
use App\Services\Contracts\GoogleCalendarGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\ExternalApiTestCase;

#[Group('external-api')]
final class GoogleCalendarControllerTest extends ExternalApiTestCase
{
    use RefreshDatabase;

    public function test_connect_starts_oauth_with_a_session_bound_state(): void
    {
        $coach = User::factory()->coach()->create();
        $gateway = Mockery::mock(GoogleCalendarGateway::class);
        $gateway->shouldReceive('authorizationUrl')
            ->once()
            ->andReturnUsing(fn (string $state): string => 'https://accounts.google.test/oauth?state='.$state);
        $this->app->instance(GoogleCalendarGateway::class, $gateway);

        $response = $this->actingAs($coach)->get(route('settings.google-calendar.redirect'));

        $state = session('google_calendar_oauth_state');
        $this->assertIsString($state);
        $this->assertSame(64, strlen($state));
        $response->assertRedirect('https://accounts.google.test/oauth?state='.$state);
    }

    public function test_callback_exchanges_the_code_and_persists_calendar_credentials(): void
    {
        $coach = User::factory()->coach()->create();
        $gateway = Mockery::mock(GoogleCalendarGateway::class);
        $gateway->shouldReceive('exchangeCode')->once()->with('authorization-code')->andReturn([
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'created' => now()->timestamp,
            'expires_in' => 3600,
        ]);
        $gateway->shouldReceive('primaryCalendarId')->once()->andReturn('coach@example.com');
        $this->app->instance(GoogleCalendarGateway::class, $gateway);

        $this->actingAs($coach)
            ->withSession(['google_calendar_oauth_state' => 'expected-state'])
            ->get(route('settings.google-calendar.callback', [
                'state' => 'expected-state',
                'code' => 'authorization-code',
            ]))
            ->assertRedirect(route('settings.availability.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('google_calendar_credentials', [
            'coach_id' => $coach->id,
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'calendar_id' => 'coach@example.com',
        ]);
    }

    public function test_callback_rejects_a_state_that_does_not_match_the_session(): void
    {
        $coach = User::factory()->coach()->create();

        $this->actingAs($coach)
            ->withSession(['google_calendar_oauth_state' => 'expected-state'])
            ->get(route('settings.google-calendar.callback', [
                'state' => 'different-state',
                'code' => 'authorization-code',
            ]))
            ->assertForbidden();

        $this->assertDatabaseCount('google_calendar_credentials', 0);
    }

    public function test_disconnect_revokes_and_removes_the_stored_credential(): void
    {
        $coach = User::factory()->coach()->create();
        $credential = GoogleCalendarCredential::factory()->for($coach, 'coach')->create();
        $gateway = Mockery::mock(GoogleCalendarGateway::class);
        $gateway->shouldReceive('revoke')->once()->withArgs(
            fn ($actualCredential): bool => $actualCredential->is($credential),
        );
        $this->app->instance(GoogleCalendarGateway::class, $gateway);

        $this->actingAs($coach)
            ->delete(route('settings.google-calendar.destroy'))
            ->assertRedirect(route('settings.availability.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('google_calendar_credentials', ['id' => $credential->id]);
    }

    public function test_settings_screen_shows_the_unlinked_state(): void
    {
        $this->withoutVite();
        $coach = User::factory()->coach()->create();

        $this->actingAs($coach)
            ->get(route('settings.availability.index'))
            ->assertOk()
            ->assertSee('未連携')
            ->assertSee('Googleカレンダーと連携する');
    }

    public function test_settings_screen_shows_the_linked_calendar(): void
    {
        $this->withoutVite();
        $coach = User::factory()->coach()->create();
        GoogleCalendarCredential::factory()->for($coach, 'coach')->create([
            'calendar_id' => 'coach@example.com',
        ]);

        $this->actingAs($coach)
            ->get(route('settings.availability.index'))
            ->assertOk()
            ->assertSee('連携中')
            ->assertSee('coach@example.com')
            ->assertSee('連携を解除する');
    }
}
