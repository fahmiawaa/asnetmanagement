<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InternetPackage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'mikrotik_device_id',
        'package_code',
        'name',
        'download_speed',
        'upload_speed',
        'price',
        'mikrotik_profile',
        'sync_source',
        'is_present',
        'last_synced_at',
        'status',
        'description',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_present' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function customers()
    {
        return $this->hasMany(Customer::class)->withTrashed();
    }

    public function mikrotikDevice()
    {
        return $this->belongsTo(MikrotikDevice::class)->withTrashed();
    }
}
