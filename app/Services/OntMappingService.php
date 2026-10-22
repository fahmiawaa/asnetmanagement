<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\OntDevice;
use App\Models\PppoeAccount;
use App\Models\PppoeActiveSession;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OntMappingService
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * Kandidat pelanggan dari sesi PPPoE aktif MikroTik yang cocok dengan
     * MAC/IP PPPoE yang dilaporkan ONT. Hanya saran; tidak pernah menulis data.
     *
     * @return Collection<int, array{customer: Customer, method: string, confidence: int}>
     */
    public function candidates(OntDevice $ont): Collection
    {
        $mac = $this->normalizeMac($ont->pppoe_mac);
        $ip = filled($ont->pppoe_ip) ? (string) $ont->pppoe_ip : null;

        if ($mac === null && $ip === null) {
            return collect();
        }

        $sessions = PppoeActiveSession::query()
            ->where('is_active', true)
            ->where(function ($query) use ($mac, $ip): void {
                if ($mac !== null) {
                    $query->whereRaw('UPPER(caller_id) = ?', [$mac]);
                }

                if ($ip !== null) {
                    $query->orWhere('address', $ip);
                }
            })
            ->get(['id', 'mikrotik_device_id', 'username', 'address', 'caller_id']);

        $found = [];

        foreach ($sessions as $session) {
            $macMatch = $mac !== null && strtoupper((string) $session->caller_id) === $mac;
            $ipMatch = $ip !== null && $session->address === $ip;
            $customer = $this->customerForSession($session);

            if ($customer === null) {
                continue;
            }

            $attached = $customer->ontDevice;

            if ($attached !== null && $attached->id !== $ont->id) {
                continue;
            }

            [$method, $confidence] = match (true) {
                $macMatch && $ipMatch => ['pppoe_mac_ip', 95],
                $macMatch => ['pppoe_mac', 90],
                default => ['pppoe_ip', 75],
            };

            if (! isset($found[$customer->id]) || $found[$customer->id]['confidence'] < $confidence) {
                $found[$customer->id] = [
                    'customer' => $customer,
                    'method' => $method,
                    'confidence' => $confidence,
                ];
            }
        }

        return collect($found)->sortByDesc('confidence')->values();
    }

    /**
     * @throws ValidationException
     */
    public function map(OntDevice $ontDevice, Customer $customer, User $actor): OntDevice
    {
        return DB::transaction(function () use ($ontDevice, $customer, $actor): OntDevice {
            $ont = OntDevice::query()->lockForUpdate()->findOrFail($ontDevice->id);
            $lockedCustomer = Customer::query()->lockForUpdate()->find($customer->id);

            if ($lockedCustomer === null) {
                throw ValidationException::withMessages([
                    'customer_id' => 'Pelanggan tidak ditemukan atau sudah dihapus.',
                ]);
            }

            if ($ont->customer_id !== null) {
                throw ValidationException::withMessages([
                    'customer_id' => 'ONT ini sudah dipetakan. Lepas mapping lebih dulu sebelum memilih pelanggan lain.',
                ]);
            }

            if (OntDevice::query()->where('customer_id', $lockedCustomer->id)->exists()) {
                throw ValidationException::withMessages([
                    'customer_id' => 'Pelanggan ini sudah memiliki ONT utama.',
                ]);
            }

            $match = $this->candidates($ont)
                ->first(fn (array $candidate): bool => $candidate['customer']->id === $lockedCustomer->id);
            $method = $match['method'] ?? 'manual';
            $confidence = $match['confidence'] ?? 100;

            try {
                $ont->forceFill([
                    'customer_id' => $lockedCustomer->id,
                    'mapped_by' => $actor->id,
                    'mapped_at' => now(),
                    'mapping_method' => $method,
                    'mapping_confidence' => $confidence,
                ])->save();
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'customer_id' => 'Pelanggan ini sudah memiliki ONT utama.',
                ]);
            }

            $this->auditLog->record(
                'ont.mapping.created',
                $ont,
                "ONT {$ont->serial_number} dipetakan ke pelanggan {$lockedCustomer->customer_code}.",
                [
                    'customer_id' => $lockedCustomer->id,
                    'customer_code' => $lockedCustomer->customer_code,
                    'method' => $method,
                    'confidence' => $confidence,
                ]
            );

            return $ont;
        });
    }

    public function unmap(OntDevice $ontDevice, User $actor): OntDevice
    {
        return DB::transaction(function () use ($ontDevice, $actor): OntDevice {
            $ont = OntDevice::query()->lockForUpdate()->findOrFail($ontDevice->id);

            if ($ont->customer_id === null) {
                return $ont;
            }

            $previousCustomerId = $ont->customer_id;
            $previousMethod = $ont->mapping_method;

            $ont->forceFill([
                'customer_id' => null,
                'mapped_by' => null,
                'mapped_at' => null,
                'mapping_method' => null,
                'mapping_confidence' => null,
            ])->save();

            $this->auditLog->record(
                'ont.mapping.removed',
                $ont,
                "Mapping ONT {$ont->serial_number} dilepas dari pelanggan.",
                [
                    'previous_customer_id' => $previousCustomerId,
                    'previous_method' => $previousMethod,
                    'removed_by' => $actor->id,
                ]
            );

            return $ont;
        });
    }

    private function customerForSession(PppoeActiveSession $session): ?Customer
    {
        $account = PppoeAccount::query()
            ->where('mikrotik_device_id', $session->mikrotik_device_id)
            ->where('username', $session->username)
            ->first(['id']);

        if ($account === null) {
            return null;
        }

        return Customer::query()
            ->with('ontDevice')
            ->where('pppoe_account_id', $account->id)
            ->first(['id', 'customer_code', 'name']);
    }

    private function normalizeMac(?string $mac): ?string
    {
        $hex = strtoupper((string) preg_replace('/[^0-9A-Fa-f]/', '', (string) $mac));

        if (strlen($hex) !== 12) {
            return null;
        }

        return implode(':', str_split($hex, 2));
    }
}
