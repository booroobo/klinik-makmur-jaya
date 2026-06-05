<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    public const SEVERITY_INFO = 'info';
    public const SEVERITY_SUCCESS = 'success';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_CRITICAL = 'critical';

    public const SEVERITIES = [
        self::SEVERITY_INFO,
        self::SEVERITY_SUCCESS,
        self::SEVERITY_WARNING,
        self::SEVERITY_CRITICAL,
    ];

    protected $fillable = [
        'user_id',
        'role_target',
        'type',
        'title',
        'message',
        'severity',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    protected $appends = [
        'is_read',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isVisibleTo(User $user): bool
    {
        return $this->user_id === $user->id || $this->role_target === $user->role;
    }

    public function getIsReadAttribute(): bool
    {
        return $this->read_at !== null;
    }
}
