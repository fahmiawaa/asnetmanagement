<?php

namespace App\Models;

use Database\Factories\OntParameterProfileFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OntParameterProfile extends Model
{
    /** @use HasFactory<OntParameterProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'manufacturer',
        'oui',
        'product_class',
        'pon_mode',
        'ssid_paths',
        'wifi_password_paths',
        'rx_power_rules',
        'temperature_rules',
        'wan_ip_paths',
        'active_client_paths',
        'is_verified',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'ssid_paths' => 'array',
            'wifi_password_paths' => 'array',
            'rx_power_rules' => 'array',
            'temperature_rules' => 'array',
            'wan_ip_paths' => 'array',
            'active_client_paths' => 'array',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * Profil yang identitasnya cocok dengan perangkat. Atrib kosong berarti wildcard.
     */
    public function scopeMatching(Builder $query, OntDevice $ontDevice): Builder
    {
        return $query->where(function (Builder $query) use ($ontDevice): void {
            $query->where(function (Builder $query) use ($ontDevice): void {
                $query->whereNull('manufacturer')
                    ->orWhere('manufacturer', $ontDevice->manufacturer);
            })->where(function (Builder $query) use ($ontDevice): void {
                $query->whereNull('oui')
                    ->orWhere('oui', $ontDevice->oui);
            })->where(function (Builder $query) use ($ontDevice): void {
                $query->whereNull('product_class')
                    ->orWhere('product_class', $ontDevice->product_class);
            })->where(function (Builder $query) use ($ontDevice): void {
                $query->whereNull('pon_mode')
                    ->orWhere('pon_mode', $ontDevice->pon_mode);
            });
        });
    }

    public function matches(OntDevice $ontDevice): bool
    {
        return $this->newQuery()->whereKey($this->getKey())->matching($ontDevice)->exists();
    }

    /**
     * @return array<int, string>
     */
    public function ssidPaths(): array
    {
        return array_values(array_filter((array) $this->ssid_paths, 'is_string'));
    }

    /**
     * @return array<int, string>
     */
    public function passwordPaths(): array
    {
        return array_values(array_filter((array) $this->wifi_password_paths, 'is_string'));
    }

    public function specificity(): int
    {
        return count(array_filter([
            $this->manufacturer,
            $this->oui,
            $this->product_class,
            $this->pon_mode,
        ]));
    }
}
