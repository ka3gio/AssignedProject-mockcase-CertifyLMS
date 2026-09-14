<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CertificationStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Database\Seeder;

/** 絞り込み・並び順・回答数・モデレーション確認用のQ&Aデータを投入する。 */
final class QaBoardSeeder extends Seeder
{
    public function run(): void
    {
        $student = User::query()->where('email', 'student@certify-lms.test')->first();
        $otherStudent = User::query()->where('email', 'student-noquota@certify-lms.test')->first();

        if ($student === null || $otherStudent === null) {
            $this->command?->warn('QaBoardSeeder: 固定受講生が存在しないためスキップします。');

            return;
        }

        $published = Certification::query()
            ->published()
            ->with('coaches')
            ->orderBy('name')
            ->get();

        foreach ($published as $certificationIndex => $certification) {
            foreach (range(0, 2) as $threadIndex) {
                $createdAt = now()->subDays(($certificationIndex * 4) + $threadIndex);
                $factory = QaThread::factory()
                    ->for($certification)
                    ->for($threadIndex === 0 ? $student : $otherStudent, 'user')
                    ->state([
                        'title' => $certification->name.'についての質問 '.($threadIndex + 1),
                        'body' => "{$certification->name}の学習中に疑問が生じました。考え方と確認方法を教えてください。",
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);

                $thread = $threadIndex === 2
                    ? $factory->resolved()->create(['resolved_at' => $createdAt->copy()->addHours(6)])
                    : $factory->unresolved()->create();

                $replyCount = [0, 1, 3][$threadIndex];
                for ($replyIndex = 0; $replyIndex < $replyCount; $replyIndex++) {
                    $author = $certification->coaches->get($replyIndex % max(1, $certification->coaches->count()))
                        ?? $otherStudent;

                    QaReply::factory()
                        ->for($thread, 'thread')
                        ->for($author, 'user')
                        ->create([
                            'body' => '回答例 '.($replyIndex + 1).'：ポイントを順番に確認してみましょう。',
                            'created_at' => $createdAt->copy()->addHours($replyIndex + 1),
                            'updated_at' => $createdAt->copy()->addHours($replyIndex + 1),
                        ]);
                }
            }
        }

        Certification::query()
            ->whereNot('status', CertificationStatus::Published->value)
            ->each(function (Certification $certification) use ($student): void {
                QaThread::factory()
                    ->unresolved()
                    ->for($certification)
                    ->for($student, 'user')
                    ->create([
                        'title' => '[管理者確認用] '.$certification->name.'の過去質問',
                        'body' => '公開停止中資格に紐づくモデレーション確認用の質問です。',
                    ]);
            });
    }
}
