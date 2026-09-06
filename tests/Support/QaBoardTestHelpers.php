<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\User;

trait QaBoardTestHelpers
{
    protected function assignCoach(Certification $certification, User $coach): void
    {
        CertificationCoachAssignment::factory()->create([
            'certification_id' => $certification->id,
            'user_id' => $coach->id,
        ]);
    }
}
