<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Certificate;

use App\Models\Certificate;
use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
    }

    public function test_owner_student_can_download_certificate_as_attachment(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certificate = $this->storedCertificate($student);

        $response = $this->actingAs($student)->get($this->downloadUrl($certificate));

        $response->assertOk();
        $response->assertDownload('certificate.pdf');
    }

    public function test_graduated_owner_can_download_certificate(): void
    {
        $student = User::factory()->student()->graduated()->create();
        $certificate = $this->storedCertificate($student);

        $this->actingAs($student)
            ->get($this->downloadUrl($certificate))
            ->assertOk();
    }

    public function test_other_student_cannot_download_certificate(): void
    {
        $certificate = $this->storedCertificate(
            User::factory()->student()->inProgress()->create(),
        );
        $otherStudent = User::factory()->student()->inProgress()->create();

        $this->actingAs($otherStudent)
            ->get($this->downloadUrl($certificate))
            ->assertForbidden();
    }

    public function test_assigned_coach_can_download_certificate(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $certificate = $this->storedCertificate(
            User::factory()->student()->inProgress()->create(),
            $certification,
        );
        CertificationCoachAssignment::factory()->create([
            'certification_id' => $certification->id,
            'user_id' => $coach->id,
        ]);

        $this->actingAs($coach)
            ->get($this->downloadUrl($certificate))
            ->assertOk();
    }

    public function test_unassigned_coach_cannot_download_certificate(): void
    {
        $certificate = $this->storedCertificate(
            User::factory()->student()->inProgress()->create(),
        );
        $coach = User::factory()->coach()->inProgress()->create();

        $this->actingAs($coach)
            ->get($this->downloadUrl($certificate))
            ->assertForbidden();
    }

    public function test_admin_can_download_certificate(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $certificate = $this->storedCertificate(
            User::factory()->student()->inProgress()->create(),
        );

        $this->actingAs($admin)
            ->get($this->downloadUrl($certificate))
            ->assertOk();
    }

    public function test_missing_pdf_returns_not_found(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certificate = $this->storedCertificate($student);
        Storage::disk('private')->delete($certificate->pdf_path);

        $this->actingAs($student)
            ->get($this->downloadUrl($certificate))
            ->assertNotFound();
    }

    private function storedCertificate(User $student, ?Certification $certification = null): Certificate
    {
        $certification ??= Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()
            ->for($student)
            ->for($certification)
            ->passed()
            ->create();
        $certificate = Certificate::factory()->forEnrollment($enrollment)->create();

        Storage::disk('private')->put($certificate->pdf_path, '%PDF-test');

        return $certificate;
    }

    private function downloadUrl(Certificate $certificate): string
    {
        return '/certificates/'.$certificate->id.'/download';
    }
}
