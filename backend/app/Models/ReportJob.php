<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ReportJob extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_FINISHED = 'finished';
    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_RUNNING,
        self::STATUS_FINISHED,
        self::STATUS_FAILED,
    ];

    protected $fillable = [
        'user_id',
        'format',
        'status',
        'progress',
        'filters',
        'file_name',
        'file_path',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    protected $appends = [
        'download_url',
        'is_finished',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getDownloadUrlAttribute(): ?string
    {
        if (! $this->file_path || $this->status !== self::STATUS_FINISHED) {
            return null;
        }

        return route('admin.reports.queue.download', $this);
    }

    public function getIsFinishedAttribute(): bool
    {
        return $this->status === self::STATUS_FINISHED;
    }

    public function hasFile(): bool
    {
        return $this->file_path !== null && Storage::disk('local')->exists($this->file_path);
    }
}
