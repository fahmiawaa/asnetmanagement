<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\DeviceTask;
use App\Models\GenieacsServer;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\MikrotikDevice;
use App\Models\NetworkLink;
use App\Models\NetworkNode;
use App\Models\NetworkPort;
use App\Models\OntDevice;
use App\Models\OntParameterProfile;
use App\Models\OntStatusHistory;
use App\Models\Payment;
use App\Models\PppoeAccount;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

class StagingSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seeder data contoh untuk staging dan pilot. Seluruh nama, nomor, alamat,
     * kredensial, dan identitas perangkat dibuat palsu dan mudah dikenali.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'staging', 'testing'])) {
            $this->command?->warn('StagingSeeder hanya untuk environment local, staging, testing.');

            return;
        }

        if (Customer::query()->where('customer_code', 'like', 'STG-%')->exists()) {
            $this->command?->info('Data staging sudah ada, seeding dilewati.');

            return;
        }

        $server = $this->genieacsServer();
        $mikrotik = $this->mikrotikDevice();
        $packages = $this->internetPackages();
        $staff = $this->staff();
        $topology = $this->topology();
        $profile = $this->parameterProfile();

        $count = max(1, (int) config('staging.customer_count'));
        $devices = [];

        for ($index = 1; $index <= $count; $index++) {
            $customer = $this->customer($index, $packages, $mikrotik);

            if ($index === 1) {
                $this->customerUser($customer);
            }

            $device = $this->ontDevice($index, $customer, $server, $topology['odp'], $staff['teknisi']);
            $devices[] = $device;

            $this->statusHistory($device);
            $this->invoices($index, $customer, $staff['teknisi']);
        }

        $this->deviceTasks($devices, $staff['teknisi']);

        $this->command?->info("Data staging dibuat: {$count} pelanggan, ".count($devices).' ONT.');
    }

    private function genieacsServer(): GenieacsServer
    {
        return GenieacsServer::query()->firstOrCreate(
            ['base_url' => 'http://genieacs-staging.internal:7557'],
            [
                'name' => 'GenieACS Staging',
                'username' => null,
                'encrypted_password' => null,
                'is_active' => false,
                'connection_status' => 'unknown',
            ]
        );
    }

    private function mikrotikDevice(): MikrotikDevice
    {
        return MikrotikDevice::query()->firstOrCreate(
            ['host' => '10.10.0.2'],
            [
                'name' => 'MikroTik Staging',
                'api_port' => 8728,
                'use_ssl' => false,
                'username' => 'staging',
                'encrypted_password' => Crypt::encryptString('staging-only'),
                'connection_status' => 'unknown',
                'notes' => 'Perangkat contoh, bukan perangkat produksi.',
            ]
        );
    }

    /** @return array<int, InternetPackage> */
    private function internetPackages(): array
    {
        $definitions = [
            ['PKG-STG10', 'Staging 10 Mbps', '10 Mbps', '5 Mbps', 100000],
            ['PKG-STG20', 'Staging 20 Mbps', '20 Mbps', '10 Mbps', 150000],
            ['PKG-STG30', 'Staging 30 Mbps', '30 Mbps', '15 Mbps', 200000],
        ];

        return array_map(fn (array $definition): InternetPackage => InternetPackage::query()->firstOrCreate(
            ['package_code' => $definition[0]],
            [
                'name' => $definition[1],
                'download_speed' => $definition[2],
                'upload_speed' => $definition[3],
                'price' => $definition[4],
                'status' => 'active',
                'description' => 'Paket contoh untuk staging dan pilot.',
            ]
        ), $definitions);
    }

    /** @return array{owner: User, admin: User, teknisi: User} */
    private function staff(): array
    {
        $domain = (string) config('staging.email_domain');
        $password = Hash::make((string) config('staging.password'));

        $staff = [];

        foreach (['owner', 'admin', 'teknisi'] as $role) {
            $user = User::query()->firstOrCreate(
                ['email' => "{$role}@{$domain}"],
                [
                    'name' => ucfirst($role).' Staging',
                    'password' => $password,
                    'role' => $role,
                    'active' => true,
                ]
            );

            if ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $staff[$role] = $user;
        }

        return $staff;
    }

    /** @return array{odp: NetworkNode} */
    private function topology(): array
    {
        $chain = [
            ['server', 'Server Staging', null],
            ['olt', 'OLT Staging', 'server'],
            ['pon_port', 'PON 1/1 Staging', 'olt'],
            ['odc', 'ODC Staging', 'pon_port'],
            ['odp', 'ODP Staging', 'odc'],
        ];

        $nodes = [];
        $previous = null;

        foreach ($chain as $order => [$type, $name, $parentType]) {
            $parent = $parentType !== null ? $nodes[$parentType] : null;

            $node = NetworkNode::query()->firstOrCreate(
                ['name' => $name],
                [
                    'parent_id' => $parent?->getKey(),
                    'type' => $type,
                    'code' => 'STG-'.strtoupper($type),
                    'technology' => 'gpon',
                    'sort_order' => $order,
                    'is_active' => true,
                ]
            );

            $inputPort = NetworkPort::query()->firstOrCreate(
                [
                    'network_node_id' => $node->getKey(),
                    'port_number' => 1,
                    'port_type' => 'input',
                ],
                ['technology' => 'gpon']
            );
            $outputPort = NetworkPort::query()->firstOrCreate(
                [
                    'network_node_id' => $node->getKey(),
                    'port_number' => 1,
                    'port_type' => 'output',
                ],
                ['technology' => 'gpon']
            );

            if ($previous !== null) {
                NetworkLink::query()->firstOrCreate(
                    ['from_node_id' => $previous['node']->getKey(), 'to_node_id' => $node->getKey()],
                    [
                        'from_port_id' => $previous['output']->getKey(),
                        'to_port_id' => $inputPort->getKey(),
                        'status' => 'active',
                        'cable_length_m' => 100,
                        'measured_attenuation_db' => 0.4,
                        'calculated_attenuation_db' => 0.4,
                    ]
                );
            }

            $nodes[$type] = $node;
            $previous = ['node' => $node, 'output' => $outputPort];
        }

        return ['odp' => $nodes['odp']];
    }

    private function parameterProfile(): OntParameterProfile
    {
        return OntParameterProfile::query()->firstOrCreate(
            ['name' => 'ZTE Staging (Wildcard)'],
            [
                'manufacturer' => null,
                'oui' => null,
                'product_class' => null,
                'pon_mode' => null,
                'ssid_paths' => ['InternetGatewayDevice.WiFi.SSID.1.SSID'],
                'wifi_password_paths' => ['VirtualParameters.WlanPassword'],
                'rx_power_rules' => null,
                'temperature_rules' => null,
                'wan_ip_paths' => null,
                'active_client_paths' => null,
                'is_verified' => true,
                'verified_at' => now(),
            ]
        );
    }

    private function customer(int $index, array $packages, MikrotikDevice $mikrotik): Customer
    {
        $code = sprintf('STG-%04d', $index);
        $package = $packages[($index - 1) % count($packages)];

        $pppoe = PppoeAccount::query()->firstOrCreate(
            [
                'mikrotik_device_id' => $mikrotik->getKey(),
                'username' => sprintf('stg-%04d', $index),
            ],
            [
                'encrypted_password' => Crypt::encryptString('staging-only'),
                'service' => 'pppoe',
                'profile' => 'staging',
                'disabled' => false,
                'is_present' => true,
                'provision_status' => 'synced',
                'provisioned_at' => now(),
                'local_address' => '10.10.10.1',
                'remote_address' => sprintf('10.20.1.%d', $index),
                'comment' => 'Akun contoh staging.',
                'last_synced_at' => now(),
            ]
        );

        return Customer::query()->firstOrCreate(
            ['customer_code' => $code],
            [
                'internet_package_id' => $package->getKey(),
                'pppoe_account_id' => $pppoe->getKey(),
                'name' => sprintf('Pelanggan Uji %02d', $index),
                'whatsapp_number' => sprintf('0812000000%02d', $index),
                'address' => sprintf('Alamat Uji %02d, Staging', $index),
                'latitude' => null,
                'longitude' => null,
                'installation_date' => now()->subMonths(2)->startOfMonth(),
                'due_day' => 10,
                'status' => 'active',
                'notes' => 'Data contoh untuk staging dan pilot.',
            ]
        );
    }

    private function customerUser(Customer $customer): User
    {
        $domain = (string) config('staging.email_domain');

        $user = User::query()->firstOrCreate(
            ['email' => "pelanggan@{$domain}"],
            [
                'name' => 'Pelanggan Uji',
                'password' => Hash::make((string) config('staging.password')),
                'role' => 'pelanggan',
                'active' => true,
                'customer_id' => $customer->getKey(),
            ]
        );

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return $user;
    }

    private function ontDevice(
        int $index,
        Customer $customer,
        GenieacsServer $server,
        NetworkNode $odp,
        User $teknisi,
    ): OntDevice {
        $offline = $index % 4 === 0;
        $rxPower = round(-15.0 + ($index % 5) * 1.1, 2);

        $node = NetworkNode::query()->firstOrCreate(
            ['name' => sprintf('ONT STG-%04d', $index)],
            [
                'parent_id' => $odp->getKey(),
                'type' => 'ont',
                'code' => sprintf('STG-ONT-%04d', $index),
                'technology' => 'gpon',
                'sort_order' => $index,
                'is_active' => true,
            ]
        );

        $splitterPort = NetworkPort::query()->firstOrCreate(
            [
                'network_node_id' => $odp->getKey(),
                'port_number' => $index,
                'port_type' => 'splitter',
            ],
            ['technology' => 'gpon']
        );

        $customerPort = NetworkPort::query()->firstOrCreate(
            [
                'network_node_id' => $node->getKey(),
                'port_number' => 1,
                'port_type' => 'customer',
            ],
            ['technology' => 'gpon']
        );

        NetworkLink::query()->firstOrCreate(
            ['from_node_id' => $odp->getKey(), 'to_node_id' => $node->getKey()],
            [
                'from_port_id' => $splitterPort->getKey(),
                'to_port_id' => $customerPort->getKey(),
                'status' => $offline ? 'inactive' : 'active',
                'cable_length_m' => 30,
                'measured_attenuation_db' => 0.3,
                'calculated_attenuation_db' => 0.3,
            ]
        );

        return OntDevice::query()->firstOrCreate(
            ['genieacs_device_id' => sprintf('STG-DEV-%04d', $index)],
            [
                'customer_id' => $customer->getKey(),
                'genieacs_server_id' => $server->getKey(),
                'network_node_id' => $node->getKey(),
                'serial_number' => sprintf('STGONT%08d', $index),
                'manufacturer' => 'ZTE',
                'oui' => 'F81A67',
                'product_class' => 'F660',
                'software_version' => 'V5.2.10P2N4',
                'pon_mode' => 'gpon',
                'ip_address' => sprintf('10.20.0.%d', $index),
                'pppoe_ip' => sprintf('10.20.1.%d', $index),
                'pon_mac' => sprintf('02:00:00:00:%02X:%02X', $index, $index + 10),
                'pppoe_mac' => sprintf('02:00:00:01:%02X:%02X', $index, $index + 20),
                'inform_interval_seconds' => 300,
                'last_inform_at' => $offline ? now()->subHours(3) : now()->subSeconds(45),
                'connection_status' => $offline ? 'offline' : 'online',
                'rx_power_raw' => (string) $rxPower,
                'rx_power_dbm' => $offline ? null : $rxPower,
                'rx_power_quality' => $offline ? 'unknown' : 'good',
                'rx_power_source' => 'genieacs_sync',
                'rx_power_collected_at' => now(),
                'temperature_celsius' => 34.5,
                'active_wifi_devices' => $offline ? 0 : 2,
                'last_seen_at' => $offline ? now()->subHours(3) : now(),
                'last_synced_at' => now(),
                'mapped_by' => $teknisi->getKey(),
                'mapped_at' => now(),
                'mapping_method' => 'manual',
                'mapping_confidence' => 100,
            ]
        );
    }

    private function statusHistory(OntDevice $device): void
    {
        OntStatusHistory::query()->create([
            'ont_device_id' => $device->getKey(),
            'old_status' => 'offline',
            'new_status' => $device->connection_status,
            'last_inform_at' => $device->last_inform_at,
            'rx_power_dbm' => $device->rx_power_dbm,
            'observed_at' => now()->subHour(),
            'source' => 'genieacs_sync',
        ]);
    }

    private function invoices(int $index, Customer $customer, User $verifier): void
    {
        $amount = (float) ($customer->internetPackage?->price ?? 150000);
        $periodStart = now()->startOfMonth();

        $paid = Invoice::query()->firstOrCreate(
            ['invoice_number' => sprintf('INV-STG-%04d-01', $index)],
            [
                'customer_id' => $customer->getKey(),
                'period_start' => $periodStart->copy()->subMonth(),
                'period_end' => $periodStart->copy()->subMonth()->endOfMonth(),
                'due_date' => $periodStart->copy()->subMonth()->addDays(10),
                'total_amount' => $amount,
                'status' => 'paid',
                'paid_at' => now()->subDays(12),
                'notes' => 'Tagihan contoh staging.',
            ]
        );

        if (! $paid->payments()->exists()) {
            Payment::query()->create([
                'invoice_id' => $paid->getKey(),
                'payment_date' => now()->subDays(12)->toDateString(),
                'amount' => $amount,
                'method' => 'bank_transfer',
                'status' => 'verified',
                'verified_by' => $verifier->getKey(),
                'verified_at' => now()->subDays(12),
                'notes' => 'Pembayaran contoh staging.',
            ]);
        }

        Invoice::query()->firstOrCreate(
            ['invoice_number' => sprintf('INV-STG-%04d-02', $index)],
            [
                'customer_id' => $customer->getKey(),
                'period_start' => $periodStart,
                'period_end' => $periodStart->copy()->endOfMonth(),
                'due_date' => $periodStart->copy()->addDays(10),
                'total_amount' => $amount,
                'status' => 'unpaid',
                'notes' => 'Tagihan contoh staging.',
            ]
        );
    }

    /** @param  array<int, OntDevice>  $devices */
    private function deviceTasks(array $devices, User $teknisi): void
    {
        if ($devices === []) {
            return;
        }

        DeviceTask::query()->firstOrCreate(
            [
                'ont_device_id' => $devices[0]->getKey(),
                'type' => DeviceTask::TYPE_SUMMON,
                'status' => DeviceTask::STATUS_VERIFIED,
            ],
            [
                'requested_by' => $teknisi->getKey(),
                'safe_summary' => ['type' => DeviceTask::TYPE_SUMMON],
                'attempts' => 1,
                'accepted_at' => now()->subDay(),
                'verified_at' => now()->subDay(),
                'expires_at' => now()->addDay(),
            ]
        );

        if (count($devices) < 2) {
            return;
        }

        DeviceTask::query()->firstOrCreate(
            [
                'ont_device_id' => $devices[1]->getKey(),
                'type' => DeviceTask::TYPE_REBOOT,
                'status' => DeviceTask::STATUS_PENDING,
            ],
            [
                'requested_by' => $teknisi->getKey(),
                'safe_summary' => ['type' => DeviceTask::TYPE_REBOOT],
                'attempts' => 0,
                'expires_at' => now()->addMinutes(30),
            ]
        );
    }
}
