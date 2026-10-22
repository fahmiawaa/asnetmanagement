<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PppoeAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'mikrotik_device_id',
        'username',
        'encrypted_password',
        'service',
        'profile',
        'disabled',
        'is_present',
        'provision_status',
        'provisioned_at',
        'provision_error',
        'local_address',
        'remote_address',
        'comment',
        'last_synced_at',
    ];

    protected $casts = [
        'disabled' => 'boolean',
        'is_present' => 'boolean',
        'provisioned_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    protected $hidden = [
        'encrypted_password',
    ];

    public function mikrotikDevice()
    {
        return $this->belongsTo(
            MikrotikDevice::class,
            'mikrotik_device_id'
        )->withTrashed();
    }

    public function customer()
    {
        return $this->hasOne(
            Customer::class,
            'pppoe_account_id'
        )->withTrashed();
    }
}
