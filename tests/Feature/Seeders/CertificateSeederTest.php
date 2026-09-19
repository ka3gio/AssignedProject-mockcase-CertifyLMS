<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use App\Models\Certificate;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use Database\Seeders\CertificateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificateSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_certificates_have_pdf_files(): void
    {
        Storage::fake('private');
        User::factory()->student()->graduated()->create();
        Certification::factory()->published()->create();

        app(CertificateSeeder::class)->run();

        $certificates = Certificate::all();

        $this->assertNotEmpty($certificates);
        foreach ($certificates as $certificate) {
            Storage::disk('private')->assertExists($certificate->pdf_path);
        }
    }

    public function test_existing_certificate_records_also_receive_pdf_files(): void
    {
        Storage::fake('private');
        $certification = Certification::factory()->published()->create();
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->passed()
            ->create();
        $existingCertificate = Certificate::factory()->forEnrollment($enrollment)->create();
        User::factory()->student()->graduated()->create();

        app(CertificateSeeder::class)->run();

        Storage::disk('private')->assertExists($existingCertificate->pdf_path);
    }
}
