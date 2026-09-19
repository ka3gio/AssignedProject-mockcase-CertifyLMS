<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\SectionProgress;
use App\Services\LearningProgressService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 学習進捗集計 Service の公開教材絞り込み・階層別完了判定・一括集計を検証する。
 */
class LearningProgressServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_summarize_returns_completion_counts_and_ratios_for_published_contents(): void
    {
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($certification)->create();

        $part1 = Part::factory()->forCertification($certification)->published()->create();
        $part2 = Part::factory()->forCertification($certification)->published()->create();
        $chapter1 = Chapter::factory()->forPart($part1)->published()->create();
        $chapter2 = Chapter::factory()->forPart($part1)->published()->create();
        $chapter3 = Chapter::factory()->forPart($part2)->published()->create();
        $section1 = Section::factory()->forChapter($chapter1)->published()->create();
        $section2 = Section::factory()->forChapter($chapter1)->published()->create();
        $section3 = Section::factory()->forChapter($chapter2)->published()->create();
        Section::factory()->forChapter($chapter3)->published()->create();

        SectionProgress::factory()->forEnrollment($enrollment)->forSection($section1)->create();
        SectionProgress::factory()->forEnrollment($enrollment)->forSection($section2)->create();
        SectionProgress::factory()->forEnrollment($enrollment)->forSection($section3)->create();

        $draftPart = Part::factory()->forCertification($certification)->draft()->create();
        $chapterInDraftPart = Chapter::factory()->forPart($draftPart)->published()->create();
        $sectionInDraftPart = Section::factory()->forChapter($chapterInDraftPart)->published()->create();
        $draftChapter = Chapter::factory()->forPart($part2)->draft()->create();
        $sectionInDraftChapter = Section::factory()->forChapter($draftChapter)->published()->create();
        $draftSection = Section::factory()->forChapter($chapter3)->draft()->create();

        SectionProgress::factory()->forEnrollment($enrollment)->forSection($sectionInDraftPart)->create();
        SectionProgress::factory()->forEnrollment($enrollment)->forSection($sectionInDraftChapter)->create();
        SectionProgress::factory()->forEnrollment($enrollment)->forSection($draftSection)->create();

        $summary = app(LearningProgressService::class)->summarize($enrollment);

        $this->assertSame(4, $summary->sectionsTotal);
        $this->assertSame(3, $summary->sectionsCompleted);
        $this->assertSame(0.75, $summary->sectionCompletionRatio);
        $this->assertSame(3, $summary->chaptersTotal);
        $this->assertSame(2, $summary->chaptersCompleted);
        $this->assertSame(0.6667, $summary->chapterCompletionRatio);
        $this->assertSame(2, $summary->partsTotal);
        $this->assertSame(1, $summary->partsCompleted);
        $this->assertSame(0.5, $summary->partCompletionRatio);
        $this->assertSame(0.75, $summary->overallCompletionRatio);
    }

    public function test_summarize_returns_zero_counts_and_ratios_when_no_published_contents_exist(): void
    {
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($certification)->create();

        $summary = app(LearningProgressService::class)->summarize($enrollment);

        $this->assertSame(0, $summary->sectionsTotal);
        $this->assertSame(0, $summary->sectionsCompleted);
        $this->assertSame(0.0, $summary->sectionCompletionRatio);
        $this->assertSame(0, $summary->chaptersTotal);
        $this->assertSame(0, $summary->chaptersCompleted);
        $this->assertSame(0.0, $summary->chapterCompletionRatio);
        $this->assertSame(0, $summary->partsTotal);
        $this->assertSame(0, $summary->partsCompleted);
        $this->assertSame(0.0, $summary->partCompletionRatio);
        $this->assertSame(0.0, $summary->overallCompletionRatio);
    }

    public function test_batch_section_completion_ratios_keeps_enrollment_progress_separate(): void
    {
        $certification = Certification::factory()->published()->create();
        $firstEnrollment = Enrollment::factory()->for($certification)->create();
        $secondEnrollment = Enrollment::factory()->for($certification)->create();
        $emptyCertification = Certification::factory()->published()->create();
        $emptyEnrollment = Enrollment::factory()->for($emptyCertification)->create();

        $part = Part::factory()->forCertification($certification)->published()->create();
        $chapter = Chapter::factory()->forPart($part)->published()->create();
        $section1 = Section::factory()->forChapter($chapter)->published()->create();
        Section::factory()->forChapter($chapter)->published()->create();
        SectionProgress::factory()->forEnrollment($firstEnrollment)->forSection($section1)->create();

        $ratios = app(LearningProgressService::class)->batchSectionCompletionRatios(new EloquentCollection([
            $firstEnrollment,
            $secondEnrollment,
            $emptyEnrollment,
        ]));

        $this->assertSame([
            $firstEnrollment->id => 0.5,
            $secondEnrollment->id => 0.0,
            $emptyEnrollment->id => 0.0,
        ], $ratios);
    }
}
