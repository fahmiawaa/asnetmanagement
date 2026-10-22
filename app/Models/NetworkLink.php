<?php

namespace App\Models;

use Database\Factories\NetworkLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkLink extends Model
{
    /** @use HasFactory<NetworkLinkFactory> */
    use HasFactory;

    public const STATUSES = ['active', 'inactive', 'unknown'];

    public const CABLE_TYPES = [
        'drop_core' => 'Drop Core (FTTH)',
        'feeder' => 'Feeder Cable',
        'distribution' => 'Distribution Cable',
        'indoor_fiber' => 'Indoor Fiber Cable',
        'aerial' => 'Kabel Aerial',
        'underground' => 'Kabel Bawah Tanah',
        'adsl' => 'Kabel ADSL/Copper',
        'other' => 'Lainnya',
    ];

    protected $fillable = [
        'from_node_id',
        'from_port_id',
        'to_node_id',
        'to_port_id',
        'path_coordinates',
        'cable_length_m',
        'measured_attenuation_db',
        'calculated_attenuation_db',
        'status',
        'cable_type',
    ];

    protected function casts(): array
    {
        return [
            'path_coordinates' => 'array',
            'cable_length_m' => 'float',
            'measured_attenuation_db' => 'float',
            'calculated_attenuation_db' => 'float',
        ];
    }

    public function fromNode(): BelongsTo
    {
        return $this->belongsTo(NetworkNode::class, 'from_node_id');
    }

    public function toNode(): BelongsTo
    {
        return $this->belongsTo(NetworkNode::class, 'to_node_id');
    }

    public function fromPort(): BelongsTo
    {
        return $this->belongsTo(NetworkPort::class, 'from_port_id');
    }

    public function toPort(): BelongsTo
    {
        return $this->belongsTo(NetworkPort::class, 'to_port_id');
    }
}
