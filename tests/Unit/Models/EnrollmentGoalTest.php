<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EnrollmentGoal のリレーションと日付 Cast を検証する Unit テスト。
 */
class EnrollmentGoalTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrollment_relation_returns_parent(): void
    {
        $enrollment = Enrollment::factory()->learning()->create();
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->create();

        $this->assertTrue($goal->enrollment->is($enrollment));
    }

    public function test_target_date_and_achieved_at_are_cast_to_dates(): void
    {
        $goal = EnrollmentGoal::factory()->achieved()->create([
            'target_date' => '2026-12-31',
        ])->fresh();

        $this->assertSame('2026-12-31', $goal->target_date->format('Y-m-d'));
        $this->assertNotNull($goal->achieved_at);
    }
}
