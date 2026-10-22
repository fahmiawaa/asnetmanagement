<?php

namespace App\Services;

use App\Models\OntDevice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class OntAlertService
{
    public function __construct(private readonly OperationalAlertService $alertService) {}

    /**
     * Pindai ONT bermasalah lalu kirim notifikasi dengan cooldown per perangkat.
     *
     * @return array{offline: int, optical: int, notified: int}
     */
    public function scan(): array
    {
        $result = ['offline' => 0, 'optical' => 0, 'notified' => 0];

        if (! config('genieacs.alerts.enabled')) {
            return $result;
        }

        $limit = max(1, (int) config('genieacs.alerts.batch_limit'));

        if (config('genieacs.alerts.notify_offline')) {
            $offline = $this->offlineCandidates($limit);
            $result['offline'] = $offline->count();

            foreach ($offline as $ontDevice) {
                if ($this->notifyOffline($ontDevice)) {
                    $result['notified']++;
                }
            }
        }

        if (config('genieacs.alerts.notify_optical')) {
            $optical = $this->opticalCandidates($limit);
            $result['optical'] = $optical->count();

            foreach ($optical as $ontDevice) {
                if ($this->notifyOptical($ontDevice)) {
                    $result['notified']++;
                }
            }
        }

        return $result;
    }

    /**
     * @param  array<int, string>  $statuses
     * @return Collection<int, OntDevice>
     */
    private function offlineCandidates(int $limit): Collection
    {
        return $this->baseQuery()
            ->whereIn('connection_status', ['offline', 'missing'])
            ->orderByRaw("CASE connection_status WHEN 'offline' THEN 1 ELSE 2 END")
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, OntDevice>
     */
    private function opticalCandidates(int $limit): Collection
    {
        return $this->baseQuery()
            ->whereIn('rx_power_quality', ['critical', 'overload'])
            ->orderByRaw("CASE rx_power_quality WHEN 'overload' THEN 1 ELSE 2 END")
            ->limit($limit)
            ->get();
    }

    private function baseQuery(): Builder
    {
        return OntDevice::query()
            ->with([
                'customer:id,customer_code,name',
                'networkNode:id,name,type',
            ]);
    }

    private function notifyOffline(OntDevice $ontDevice): bool
    {
        return $this->alertService->send(
            key: "ont:{$ontDevice->getKey()}:offline",
            title: "ONT offline: {$this->label($ontDevice)}",
            message: $this->offlineMessage($ontDevice),
            context: $this->context($ontDevice),
            level: 'danger',
            link: $this->alertsLink(),
        );
    }

    private function notifyOptical(OntDevice $ontDevice): bool
    {
        $overload = $ontDevice->rx_power_quality === 'overload';

        return $this->alertService->send(
            key: "ont:{$ontDevice->getKey()}:optical",
            title: ($overload ? 'Redaman overload: ' : 'Redaman kritis: ').$this->label($ontDevice),
            message: $this->opticalMessage($ontDevice),
            context: $this->context($ontDevice),
            level: $overload ? 'danger' : 'warning',
            link: $this->alertsLink(),
        );
    }

    private function offlineMessage(OntDevice $ontDevice): string
    {
        $lastInform = $ontDevice->last_inform_at
            ? $ontDevice->last_inform_at->diffForHumans()
            : 'tidak pernah';

        return sprintf(
            'Inform terakhir %s, status %s. Pelanggan: %s. Periksa jalur optik dan perangkat.',
            $lastInform,
            $ontDevice->connection_status,
            $this->customerLabel($ontDevice),
        );
    }

    private function opticalMessage(OntDevice $ontDevice): string
    {
        $rxPower = $ontDevice->rx_power_dbm !== null
            ? "{$ontDevice->rx_power_dbm} dBm"
            : 'tidak tersedia';

        return sprintf(
            'RX power %s dengan kualitas %s. Pelanggan: %s.',
            $rxPower,
            $ontDevice->rx_power_quality,
            $this->customerLabel($ontDevice),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function context(OntDevice $ontDevice): array
    {
        return [
            'ont_device_id' => $ontDevice->getKey(),
            'serial_number' => $ontDevice->serial_number,
            'genieacs_device_id' => $ontDevice->genieacs_device_id,
            'connection_status' => $ontDevice->connection_status,
            'rx_power_dbm' => $ontDevice->rx_power_dbm,
            'rx_power_quality' => $ontDevice->rx_power_quality,
            'last_inform_at' => $ontDevice->last_inform_at?->toIso8601String(),
            'customer' => $this->customerLabel($ontDevice),
            'network_node' => $ontDevice->networkNode?->name,
        ];
    }

    private function label(OntDevice $ontDevice): string
    {
        return $ontDevice->serial_number ?: $ontDevice->genieacs_device_id;
    }

    private function customerLabel(OntDevice $ontDevice): string
    {
        $customer = $ontDevice->customer;

        if ($customer === null) {
            return 'belum dipetakan';
        }

        return "{$customer->customer_code} - {$customer->name}";
    }

    private function alertsLink(): string
    {
        return route('optical-alerts.index');
    }
}
