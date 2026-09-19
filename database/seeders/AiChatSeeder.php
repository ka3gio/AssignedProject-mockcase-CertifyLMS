<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Enums\EnrollmentStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Seeder;

final class AiChatSeeder extends Seeder
{
    public function run(): void
    {
        $student = User::query()->where('email', 'student@certify-lms.test')->first();

        if ($student === null) {
            $this->command?->warn('AiChatSeeder: 固定受講生が存在しません。先に UserSeeder を実行してください。');

            return;
        }

        $enrollment = Enrollment::query()
            ->where('user_id', $student->id)
            ->whereIn('status', [EnrollmentStatus::Learning->value, EnrollmentStatus::Passed->value])
            ->with('certification')
            ->orderBy('created_at')
            ->first();

        $general = AiChatConversation::query()->firstOrCreate(
            ['user_id' => $student->id, 'title' => '学習計画の立て方'],
            ['enrollment_id' => $enrollment?->id, 'section_id' => null],
        );
        $this->seedMessages($general, [
            [
                'role' => AiChatMessageRole::User,
                'status' => AiChatMessageStatus::Completed,
                'content' => '試験日までの学習計画を立てるコツを教えてください。',
                'created_at' => now()->subDays(8)->subMinute(),
            ],
            [
                'role' => AiChatMessageRole::Assistant,
                'status' => AiChatMessageStatus::Completed,
                'content' => "残り期間を週単位に分け、インプット・演習・復習の順で配分しましょう。\n\n毎週末に進捗を見直すと調整しやすくなります。",
                'created_at' => now()->subDays(8),
                'model' => 'gemini-2.5-flash',
                'input_tokens' => 96,
                'output_tokens' => 68,
                'response_time_ms' => 840,
            ],
        ]);

        $section = $enrollment === null
            ? null
            : Section::query()
                ->published()
                ->whereHas(
                    'chapter.part',
                    fn ($query) => $query->where('certification_id', $enrollment->certification_id),
                )
                ->with('chapter.part')
                ->orderBy('created_at')
                ->first();

        if ($section !== null) {
            $sectionConversation = AiChatConversation::query()->firstOrCreate(
                ['user_id' => $student->id, 'section_id' => $section->id],
                ['enrollment_id' => $enrollment->id, 'title' => $section->title.'について'],
            );
            $this->seedMessages($sectionConversation, [
                [
                    'role' => AiChatMessageRole::User,
                    'status' => AiChatMessageStatus::Completed,
                    'content' => 'このSectionの重要ポイントを3つにまとめてください。',
                    'created_at' => now()->subDays(2)->subMinute(),
                ],
                [
                    'role' => AiChatMessageRole::Assistant,
                    'status' => AiChatMessageStatus::Completed,
                    'content' => "重要ポイントは次の3点です。\n\n1. 基本用語を定義ごと理解する\n2. 具体例で手順を確認する\n3. 問題演習で定着を確かめる",
                    'created_at' => now()->subDays(2),
                    'model' => 'gemini-2.5-flash',
                    'input_tokens' => 184,
                    'output_tokens' => 92,
                    'response_time_ms' => 1120,
                ],
            ]);
        } else {
            $this->command?->warn('AiChatSeeder: 固定受講生が閲覧できる公開 Section が存在しません。');
        }

        $error = AiChatConversation::query()->firstOrCreate(
            ['user_id' => $student->id, 'title' => '再質問する相談'],
            ['enrollment_id' => $enrollment?->id, 'section_id' => null],
        );
        $this->seedMessages($error, [
            [
                'role' => AiChatMessageRole::User,
                'status' => AiChatMessageStatus::Completed,
                'content' => 'この用語をもう少し簡単に説明してください。',
                'created_at' => now()->subMinute(),
            ],
            [
                'role' => AiChatMessageRole::Assistant,
                'status' => AiChatMessageStatus::Error,
                'content' => '',
                'created_at' => now(),
                'model' => 'gemini-2.5-flash',
                'response_time_ms' => 30000,
                'error_detail' => 'Gemini API request failed (503).',
            ],
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function seedMessages(AiChatConversation $conversation, array $rows): void
    {
        foreach ($rows as $row) {
            AiChatMessage::query()->firstOrCreate(
                [
                    'ai_chat_conversation_id' => $conversation->id,
                    'role' => $row['role']->value,
                    'content' => $row['content'],
                ],
                [
                    ...$row,
                    'role' => $row['role']->value,
                    'status' => $row['status']->value,
                ],
            );
        }
    }
}
