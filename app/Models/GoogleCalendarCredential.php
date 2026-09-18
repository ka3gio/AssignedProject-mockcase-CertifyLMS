<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GoogleCalendarCredentialFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleCalendarCredential extends Model
{
    /** @use HasFactory<GoogleCalendarCredentialFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'coach_id',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'calendar_id',
        'connected_at',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'connected_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }
}
