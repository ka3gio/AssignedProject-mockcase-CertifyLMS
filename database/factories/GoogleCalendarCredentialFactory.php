<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GoogleCalendarCredential;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoogleCalendarCredential>
 */
class GoogleCalendarCredentialFactory extends Factory
{
    protected $model = GoogleCalendarCredential::class;

    public function definition(): array
    {
        return [
            'coach_id' => User::factory()->coach(),
            'access_token' => fake()->sha256(),
            'refresh_token' => fake()->sha256(),
            'token_expires_at' => now()->addHour(),
            'calendar_id' => fake()->unique()->safeEmail(),
            'connected_at' => now(),
        ];
    }
}
