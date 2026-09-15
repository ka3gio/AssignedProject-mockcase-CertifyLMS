<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AnnouncementTargetType;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 管理者が受講生へ即時配信した、編集・取消不可のお知らせ。
 */
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'title',
        'body',
        'target_type',
        'target_certification_id',
        'target_user_id',
        'created_by_user_id',
        'dispatched_count',
        'dispatched_at',
    ];

    protected $casts = [
        'target_type' => AnnouncementTargetType::class,
        'dispatched_count' => 'integer',
        'dispatched_at' => 'datetime',
    ];

    /** @return BelongsTo<Certification, $this> */
    public function targetCertification(): BelongsTo
    {
        return $this->belongsTo(Certification::class, 'target_certification_id');
    }

    /** @return BelongsTo<User, $this> */
    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id')->withTrashed();
    }
}
