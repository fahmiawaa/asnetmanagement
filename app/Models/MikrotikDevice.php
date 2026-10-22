<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MikrotikDevice extends Model
{
    use HasFactory, SoftDeletes;

    protected $hidden = [
        'encrypted_password',
    ];

    protected $fillable = [
        'name',
        'host',
        'api_port',
        'use_ssl',
        'username',
        'encrypted_password',
        'connection_status',
        'last_sync_at',
        'notes',
    ];

    protected $casts = [
        'use_ssl' => 'boolean',
        'last_sync_at' => 'datetime',
    ];

    public function pppoeAccounts()
    {
        return $this->hasMany(
            PppoeAccount::class,
            'mikrotik_device_id'
        );
    }

    public function internetPackages()
    {
        return $this->hasMany(InternetPackage::class);
    }

    public function activePppoeSessions()
    {
        return $this->hasMany(PppoeActiveSession::class);
    }
}
