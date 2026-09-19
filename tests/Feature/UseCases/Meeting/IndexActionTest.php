<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Models\Meeting;
use App\Models\User;
use App\UseCases\Meeting\IndexAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_only_the_students_upcoming_meetings_and_remaining_quota(): void
    {
        $student = User::factory()->student()->create(['max_meetings' => 4]);
        $otherStudent = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $upcoming = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(2)->startOfHour(),
        ]);
        Meeting::factory()->completed()->forCoach($coach)->forStudent($student)->create();
        Meeting::factory()->reserved()->forCoach($coach)->forStudent($otherStudent)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);

        $result = app(IndexAction::class)($student, []);

        $this->assertSame('upcoming', $result['filter']);
        $this->assertSame(4, $result['meetingsRemaining']);
        $this->assertSame([$upcoming->id], $result['meetings']->pluck('id')->all());
    }
}
