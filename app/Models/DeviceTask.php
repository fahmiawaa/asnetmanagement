<?php

namespace App\Models;

use Database\Factories\DeviceTaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceTask extends Model
{
    /** @use HasFactory<DeviceTaskFactory> */
    use HasFactory;

    public const TYPE_SUMMON = 'summon';

    public const TYPE_REBOOT = 'reboot';

    public const TYPE_SET_WIFI = 'set_wifi';

    public const TYPES = [self::TYPE_SUMMON, self::TYPE_REBOOT, self::TYPE_SET_WIFI];

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_ACCEPTED,
        self::STATUS_VERIFIED,
        self::STATUS_FAILED,
        self::STATUS_EXPIRED,
        self::STATUS_CANCELLED,
    ];

    public const OPEN_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_ACCEPTED,
    ];

    protected $fillable = [
        'ont_device_id',
        'type',
        'status',
        'requested_by',
        'payload_encrypted',
        'safe_summary',
        'remote_task_id',
        'attempts',
        'accepted_at',
        'verified_at',
        'failed_at',
        'expires_at',
        'error_code',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'payload_encrypted' => 'encrypted',
            'safe_summary' => 'array',
            'attempts' => 'integer',
            'accepted_at' => 'datetime',
            'verified_at' => 'datetime',
            'failed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function ontDevice(): BelongsTo
    {
        return $this->belongsTo(OntDevice::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_VERIFIED,
            self::STATUS_FAILED,
            self::STATUS_EXPIRED,
            self::STATUS_CANCELLED,
        ], true);
    }

    /**
     * @param  array<int, string>  $statuses
     */
    public function scopeWithStatuses(Builder $query, array $statuses): Builder
    {
        return $query->whereIn('status', $statuses);
    }
}
