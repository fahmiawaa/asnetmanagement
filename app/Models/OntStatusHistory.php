<?php

namespace App\Models;

use Database\Factories\OntStatusHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OntStatusHistory extends Model
{
    /** @use HasFactory<OntStatusHistoryFactory> */
    use HasFactory;

    protected $fillable = [
        'ont_device_id',
        'old_status',
        'new_status',
        'last_inform_at',
        'rx_power_dbm',
        'observed_at',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'last_inform_at' => 'datetime',
            'rx_power_dbm' => 'decimal:2',
            'observed_at' => 'datetime',
        ];
    }

    public function ontDevice(): BelongsTo
    {
        return $this->belongsTo(OntDevice::class);
    }
}
