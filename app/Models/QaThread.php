<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QaThreadStatus;
use App\Enums\UserRole;
use Database\Factories\QaThreadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** 公開Q&A掲示板の質問スレッド。 */
class QaThread extends Model
{
    /** @use HasFactory<QaThreadFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'certification_id',
        'user_id',
        'title',
        'body',
        'status',
        'resolved_at',
    ];

    protected $casts = [
        'status' => QaThreadStatus::class,
        'resolved_at' => 'datetime',
    ];

    /** @return BelongsTo<Certification, $this> */
    public function certification(): BelongsTo
    {
        return $this->belongsTo(Certification::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<QaReply, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(QaReply::class);
    }

    public function scopeKeyword(Builder $query, ?string $keyword): Builder
    {
        if ($keyword === null || $keyword === '') {
            return $query;
        }

        $like = '%'.$keyword.'%';

        return $query->where(function (Builder $query) use ($like): void {
            $query->where('title', 'LIKE', $like)
                ->orWhere('body', 'LIKE', $like);
        });
    }

    /** 公開側でユーザーが閲覧できるスレッドだけに絞る。 */
    public function scopeVisibleFor(Builder $query, User $viewer): Builder
    {
        return $query->whereHas(
            'certification',
            function (Builder $certifications) use ($viewer): void {
                $certifications->published();

                if ($viewer->role === UserRole::Coach) {
                    $certifications->assignedTo($viewer);
                }
            },
        );
    }
}
