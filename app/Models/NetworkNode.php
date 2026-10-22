<?php

namespace App\Models;

use Database\Factories\NetworkNodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class NetworkNode extends Model
{
    /** @use HasFactory<NetworkNodeFactory> */
    use HasFactory;

    public const TYPES = ['server', 'mikrotik', 'olt', 'pon_port', 'odc', 'odp', 'ont'];

    public const TECHNOLOGIES = ['gpon', 'epon', 'mixed', 'unknown'];

    protected $fillable = [
        'parent_id',
        'type',
        'name',
        'code',
        'technology',
        'latitude',
        'longitude',
        'properties',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'properties' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function ports(): HasMany
    {
        return $this->hasMany(NetworkPort::class);
    }

    public function incomingLink(): HasOne
    {
        return $this->hasOne(NetworkLink::class, 'to_node_id');
    }

    public function ontDevice(): HasOne
    {
        return $this->hasOne(OntDevice::class, 'network_node_id');
    }
}
