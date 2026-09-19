<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Models\Enrollment;
use App\Services\MeetingAvailabilityService;
use Carbon\Carbon;

/** 予約画面向けに指定日の空き枠を取得する。 */
final class FetchAvailabilityAction
{
    public function __construct(
        private readonly MeetingAvailabilityService $availability,
    ) {}

    /**
     * @return array{date: string, slots: array<int, array{slot_start: string, slot_end: string, available_coach_count: int}>}
     */
    public function __invoke(Enrollment $enrollment, string $dateValue): array
    {
        $date = Carbon::parse($dateValue);
        $slots = $this->availability->slotsForCertification(
            $enrollment->loadMissing('certification')->certification,
            $date,
        );

        return [
            'date' => $date->toDateString(),
            'slots' => $slots->map(fn (array $slot) => [
                'slot_start' => $slot['slot_start']->toIso8601String(),
                'slot_end' => $slot['slot_end']->toIso8601String(),
                'available_coach_count' => $slot['available_coach_count'],
            ])->all(),
        ];
    }
}
