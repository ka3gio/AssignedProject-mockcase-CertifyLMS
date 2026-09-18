<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use App\Models\User;
use Database\Seeders\MentoringSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MentoringSeederGoogleCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixed_coaches_include_linked_and_unlinked_google_calendar_states(): void
    {
        $this->seed(UserSeeder::class);
        $this->seed(MentoringSeeder::class);

        $linked = User::query()->where('email', 'coach@certify-lms.test')->firstOrFail();
        $unlinked = User::query()->where('email', 'coach2@certify-lms.test')->firstOrFail();

        $this->assertNotNull($linked->googleCredential);
        $this->assertSame('coach@certify-lms.test', $linked->googleCredential->calendar_id);
        $this->assertNull($unlinked->googleCredential);
    }
}
