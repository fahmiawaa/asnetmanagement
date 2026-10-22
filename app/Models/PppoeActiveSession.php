<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PppoeActiveSession extends Model
{
    protected $fillable = [
        'mikrotik_device_id',
        'router_session_id',
        'username',
        'service',
        'profile',
        'address',
        'caller_id',
        'uptime',
        'is_active',
        'last_synced_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function mikrotikDevice()
    {
        return $this->belongsTo(MikrotikDevice::class)->withTrashed();
    }
}
