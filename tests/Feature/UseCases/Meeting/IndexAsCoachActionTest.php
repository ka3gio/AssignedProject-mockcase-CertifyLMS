<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Models\Meeting;
use App\Models\User;
use App\UseCases\Meeting\IndexAsCoachAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexAsCoachActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_the_coachs_upcoming_meetings_by_student(): void
    {
        $coach = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(2)->startOfHour(),
        ]);
        Meeting::factory()->reserved()->forCoach($coach)->forStudent($otherStudent)->create([
            'scheduled_at' => now()->addDay()->startOfHour(),
        ]);
        Meeting::factory()->reserved()->forCoach($otherCoach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);

        $result = app(IndexAsCoachAction::class)($coach, ['student' => $student->id]);

        $this->assertSame('upcoming', $result['filter']);
        $this->assertSame($student->id, $result['studentFilter']);
        $this->assertNull($result['enrollmentFilter']);
        $this->assertSame([$meeting->id], $result['meetings']->pluck('id')->all());
    }
}
