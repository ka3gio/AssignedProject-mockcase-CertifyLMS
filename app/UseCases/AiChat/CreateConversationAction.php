<?php

declare(strict_types=1);

namespace App\UseCases\AiChat;

use App\Enums\EnrollmentStatus;
use App\Models\AiChatConversation;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;

final class CreateConversationAction
{
    public function __invoke(
        User $user,
        ?Section $section = null,
        bool $autoTitleEnabled = true,
    ): AiChatConversation {
        if ($section !== null) {
            $section->loadMissing('chapter.part.certification');
            $enrollment = Enrollment::query()
                ->where('user_id', $user->id)
                ->where('certification_id', $section->chapter->part->certification_id)
                ->whereIn('status', [EnrollmentStatus::Learning->value, EnrollmentStatus::Passed->value])
                ->firstOrFail();

            return AiChatConversation::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'section_id' => $section->id,
                ],
                [
                    'enrollment_id' => $enrollment->id,
                    'title' => '新しい相談',
                    'auto_title_enabled' => $autoTitleEnabled,
                ],
            );
        }

        $enrollment = Enrollment::query()
            ->whereKey($user->default_enrollment_id)
            ->where('user_id', $user->id)
            ->whereIn('status', [EnrollmentStatus::Learning->value, EnrollmentStatus::Passed->value])
            ->first();

        return AiChatConversation::create([
            'user_id' => $user->id,
            'enrollment_id' => $enrollment?->id,
            'title' => '新しい相談',
            'auto_title_enabled' => $autoTitleEnabled,
        ]);
    }
}
