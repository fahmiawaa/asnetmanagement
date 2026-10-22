<?php

namespace App\Models;

use Database\Factories\OntDeviceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OntDevice extends Model
{
    /** @use HasFactory<OntDeviceFactory> */
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'network_node_id',
        'mapped_by',
        'mapped_at',
        'mapping_method',
        'mapping_confidence',
        'genieacs_server_id',
        'genieacs_device_id',
        'serial_number',
        'pon_mac',
        'manufacturer',
        'oui',
        'product_class',
        'software_version',
        'pon_mode',
        'ip_address',
        'pppoe_ip',
        'pppoe_mac',
        'inform_interval_seconds',
        'last_inform_at',
        'connection_status',
        'rx_power_raw',
        'rx_power_dbm',
        'rx_power_quality',
        'rx_power_source',
        'rx_power_collected_at',
        'temperature_raw',
        'temperature_celsius',
        'active_wifi_devices',
        'device_uptime_seconds',
        'pppoe_uptime_seconds',
        'last_seen_at',
        'last_synced_at',
        'missing_since',
    ];

    protected function casts(): array
    {
        return [
            'last_inform_at' => 'datetime',
            'mapped_at' => 'datetime',
            'rx_power_dbm' => 'decimal:2',
            'rx_power_collected_at' => 'datetime',
            'temperature_celsius' => 'decimal:2',
            'last_seen_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'missing_since' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function networkNode(): BelongsTo
    {
        return $this->belongsTo(NetworkNode::class);
    }

    public function mappedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mapped_by');
    }

    public function genieacsServer(): BelongsTo
    {
        return $this->belongsTo(GenieacsServer::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OntStatusHistory::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(DeviceTask::class);
    }
}
