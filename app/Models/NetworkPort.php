<?php

namespace App\Models;

use Database\Factories\NetworkPortFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkPort extends Model
{
    /** @use HasFactory<NetworkPortFactory> */
    use HasFactory;

    public const TYPES = ['pon', 'input', 'output', 'splitter', 'customer'];

    protected $fillable = [
        'network_node_id',
        'port_number',
        'port_type',
        'capacity',
        'technology',
        'properties',
    ];

    protected function casts(): array
    {
        return [
            'port_number' => 'integer',
            'capacity' => 'integer',
            'properties' => 'array',
        ];
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(NetworkNode::class, 'network_node_id');
    }
}
