<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Exceptions\MeetingQuota\InsufficientMeetingQuotaException;
use App\Exceptions\Mentoring\MeetingNoAvailableCoachException;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\Services\BusinessNotificationService;
use App\Services\CoachMeetingLoadService;
use App\Services\GoogleCalendarSyncService;
use App\Services\MeetingAvailabilityService;
use App\Services\MeetingQuotaService;
use App\UseCases\MeetingQuota\ConsumeQuotaAction;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** 受講生の面談予約と、それに伴う回数消費・カレンダー連携・通知を実行する。 */
final class StoreAction
{
    public function __construct(
        private readonly MeetingAvailabilityService $availability,
        private readonly CoachMeetingLoadService $coachLoad,
        private readonly MeetingQuotaService $quota,
        private readonly ConsumeQuotaAction $consume,
        private readonly BusinessNotificationService $notifications,
        private readonly GoogleCalendarSyncService $calendarSync,
    ) {}

    /**
     * @param array{scheduled_at: string, topic: string} $validated
     */
    public function __invoke(Enrollment $enrollment, array $validated): Meeting
    {
        $scheduledAt = Carbon::parse($validated['scheduled_at']);
        $student = $enrollment->user;

        return DB::transaction(function () use ($enrollment, $student, $scheduledAt, $validated) {
            if ($this->quota->remaining($student) < 1) {
                throw new InsufficientMeetingQuotaException;
            }

            $this->availability->validateSlot($enrollment->certification, $scheduledAt);

            $candidates = $this->findAvailableCoaches($enrollment->certification, $scheduledAt);
            if ($candidates->isEmpty()) {
                throw new MeetingNoAvailableCoachException;
            }

            $coach = $this->coachLoad->leastLoadedCoach($candidates);

            try {
                $meeting = Meeting::create([
                    'enrollment_id' => $enrollment->id,
                    'coach_id' => $coach->id,
                    'student_id' => $student->id,
                    'scheduled_at' => $scheduledAt,
                    'status' => MeetingStatus::Reserved->value,
                    'topic' => $validated['topic'],
                    'meeting_url_snapshot' => $coach->meeting_url,
                ]);
            } catch (UniqueConstraintViolationException $exception) {
                throw new MeetingNoAvailableCoachException($exception);
            }

            $transaction = ($this->consume)($student, $meeting->id);
            $meeting->update(['meeting_quota_transaction_id' => $transaction->id]);

            $meeting = $meeting->fresh();
            $this->notifications->notifyMeetingReserved($meeting);
            $this->calendarSync->createForMeeting($meeting);

            return $meeting->fresh();
        });
    }

    /**
     * @return Collection<int, User>
     */
    private function findAvailableCoaches(Certification $certification, Carbon $scheduledAt): Collection
    {
        $time = $scheduledAt->format('H:i:s');
        $slotEnd = $scheduledAt->copy()->addHour();

        return $certification->coaches()
            ->with('googleCredential')
            ->whereHas('coachAvailabilities', function ($query) use ($scheduledAt, $time) {
                $query->where('day_of_week', $scheduledAt->dayOfWeek)
                    ->where('is_active', true)
                    ->where('start_time', '<=', $time)
                    ->where('end_time', '>', $time);
            })
            ->whereDoesntHave('meetingsAsCoach', function ($query) use ($scheduledAt) {
                $query->where('scheduled_at', $scheduledAt)
                    ->whereIn('status', [MeetingStatus::Reserved->value, MeetingStatus::Completed->value]);
            })
            ->get()
            ->reject(fn (User $coach): bool => $this->availability->isGoogleBusyForSlot(
                $coach,
                $scheduledAt,
                $slotEnd,
            ))
            ->values();
    }
}
