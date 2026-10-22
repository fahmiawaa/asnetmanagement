<?php

namespace App\Models;

use Database\Factories\GenieacsServerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GenieacsServer extends Model
{
    /** @use HasFactory<GenieacsServerFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'base_url',
        'username',
        'encrypted_password',
        'is_active',
        'connection_status',
        'last_connected_at',
        'last_sync_at',
        'last_error_at',
        'last_error_message',
    ];

    protected $hidden = [
        'encrypted_password',
    ];

    protected function casts(): array
    {
        return [
            'encrypted_password' => 'encrypted',
            'is_active' => 'boolean',
            'last_connected_at' => 'datetime',
            'last_sync_at' => 'datetime',
            'last_error_at' => 'datetime',
        ];
    }

    public function ontDevices(): HasMany
    {
        return $this->hasMany(OntDevice::class);
    }
}
