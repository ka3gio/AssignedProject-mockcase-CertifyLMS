<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\GoogleCalendarCredential;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GoogleCalendarCredentialTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_has_one_google_calendar_credential(): void
    {
        $coach = User::factory()->coach()->create();
        $credential = GoogleCalendarCredential::factory()->for($coach, 'coach')->create();

        $this->assertTrue($coach->fresh()->googleCredential->is($credential));
        $this->assertTrue($credential->coach->is($coach));
    }

    public function test_token_expiration_and_connected_at_are_datetimes(): void
    {
        $credential = GoogleCalendarCredential::factory()->create();

        $this->assertInstanceOf(Carbon::class, $credential->fresh()->token_expires_at);
        $this->assertInstanceOf(Carbon::class, $credential->fresh()->connected_at);
    }

    public function test_only_one_credential_can_be_stored_per_coach(): void
    {
        $coach = User::factory()->coach()->create();
        GoogleCalendarCredential::factory()->for($coach, 'coach')->create();

        $this->expectException(QueryException::class);

        GoogleCalendarCredential::factory()->for($coach, 'coach')->create();
    }
}
