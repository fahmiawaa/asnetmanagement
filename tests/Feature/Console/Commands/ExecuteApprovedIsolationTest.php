<?php

namespace Tests\Feature\Console\Commands;

use App\Models\Customer;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\IsolationLog;
use App\Models\MikrotikDevice;
use App\Models\PppoeAccount;
use App\Services\MikrotikConnectionService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ExecuteApprovedIsolationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_executes_only_25_customers_in_one_device_batch(): void
    {
        config([
            'mikrotik.allow_real_actions' => true,
            'mikrotik.isolation_batch_size' => 25,
            'mikrotik.isolation_failure_threshold' => 3,
        ]);
        $device = $this->createDevice();
        $internetPackage = InternetPackage::factory()->create();
        $logs = collect(range(1, 30))
            ->map(fn (int $number): IsolationLog => $this->createCandidate(
                $device,
                $internetPackage,
                $number
            ));
        $connectionService = $this->mock(MikrotikConnectionService::class);
        $connectionService->shouldReceive('isolatePppoeBatch')
            ->once()
            ->withArgs(function (
                MikrotikDevice $receivedDevice,
                array $usernames,
                int $failureThreshold
            ) use ($device): bool {
                return $receivedDevice->is($device)
                    && count($usernames) === 25
                    && $failureThreshold === 3;
            })
            ->andReturnUsing(fn (
                MikrotikDevice $receivedDevice,
                array $usernames
            ): array => collect($usernames)
                ->mapWithKeys(fn (string $username): array => [
                    $username => [
                        'status' => 'executed',
                        'disconnected' => 1,
                        'error' => null,
                    ],
                ])
                ->all());

        $this->artisan('billing:execute-approved', [
            'deviceId' => $device->id,
            '--execute' => true,
            '--confirm' => 'ISOLATE',
        ])->assertSuccessful();

        $this->assertSame(
            25,
            IsolationLog::query()
                ->whereIn('id', $logs->pluck('id'))
                ->where('approval_status', 'executed')
                ->count()
        );
        $this->assertSame(
            5,
            IsolationLog::query()
                ->whereIn('id', $logs->pluck('id'))
                ->where('approval_status', 'approved')
                ->count()
        );
        $this->assertSame(
            25,
            Customer::query()
                ->where('status', 'isolated')
                ->count()
        );
    }

    public function test_transient_failure_is_retried_with_backoff(): void
    {
        $this->travelTo('2026-09-26 12:00:00');
        config([
            'mikrotik.allow_real_actions' => true,
            'mikrotik.isolation_max_attempts' => 5,
            'mikrotik.isolation_retry_base_minutes' => 2,
        ]);
        $device = $this->createDevice();
        $log = $this->createCandidate(
            $device,
            InternetPackage::factory()->create(),
            1
        );
        $connectionService = $this->mock(MikrotikConnectionService::class);
        $connectionService->shouldReceive('isolatePppoeBatch')
            ->once()
            ->andReturn([
                'pelanggan-0001' => [
                    'status' => 'command_failed',
                    'disconnected' => 0,
                    'error' => 'Router tidak merespons.',
                ],
            ]);

        $this->artisan('billing:execute-approved', [
            'deviceId' => $device->id,
            '--execute' => true,
            '--confirm' => 'ISOLATE',
        ])->assertFailed();

        $log->refresh();
        $this->assertSame('approved', $log->approval_status);
        $this->assertSame(1, $log->attempt_count);
        $this->assertSame(
            '2026-09-26 12:02:00',
            $log->next_retry_at?->format('Y-m-d H:i:s')
        );
        $this->assertSame('Router tidak merespons.', $log->last_error);
        $this->assertSame('overdue', $log->customer->fresh()->status);
    }

    public function test_drains_700_candidates_across_28_safe_batches(): void
    {
        config([
            'mikrotik.allow_real_actions' => true,
            'mikrotik.isolation_batch_size' => 25,
        ]);
        $device = $this->createDevice();
        $internetPackage = InternetPackage::factory()->create();

        foreach (range(1, 700) as $number) {
            $this->createCandidate($device, $internetPackage, $number);
        }

        $connectionService = $this->mock(MikrotikConnectionService::class);
        $connectionService->shouldReceive('isolatePppoeBatch')
            ->times(28)
            ->andReturnUsing(fn (
                MikrotikDevice $receivedDevice,
                array $usernames
            ): array => collect($usernames)
                ->mapWithKeys(fn (string $username): array => [
                    $username => [
                        'status' => 'executed',
                        'disconnected' => 1,
                        'error' => null,
                    ],
                ])
                ->all());

        foreach (range(1, 28) as $batchNumber) {
            $this->artisan('billing:execute-approved', [
                'deviceId' => $device->id,
                '--execute' => true,
                '--confirm' => 'ISOLATE',
            ])->assertSuccessful();
        }

        $this->assertSame(
            700,
            IsolationLog::query()
                ->where('action', 'isolate')
                ->where('approval_status', 'executed')
                ->count()
        );
        $this->assertSame(
            700,
            Customer::query()
                ->where('status', 'isolated')
                ->count()
        );
    }

    private function createDevice(): MikrotikDevice
    {
        return MikrotikDevice::query()->create([
            'name' => 'Router Desa A',
            'host' => '10.10.10.1',
            'api_port' => 8728,
            'use_ssl' => false,
            'username' => 'billing-automation',
            'encrypted_password' => 'encrypted-test-value',
            'connection_status' => 'connected',
        ]);
    }

    private function createCandidate(
        MikrotikDevice $device,
        InternetPackage $internetPackage,
        int $number
    ): IsolationLog {
        $username = 'pelanggan-'.str_pad(
            (string) $number,
            4,
            '0',
            STR_PAD_LEFT
        );
        $pppoeAccount = PppoeAccount::query()->create([
            'mikrotik_device_id' => $device->id,
            'username' => $username,
            'disabled' => false,
            'is_present' => true,
        ]);
        $customer = Customer::factory()
            ->for($internetPackage)
            ->create([
                'customer_code' => 'CUST-'.str_pad(
                    (string) $number,
                    4,
                    '0',
                    STR_PAD_LEFT
                ),
                'pppoe_account_id' => $pppoeAccount->id,
                'status' => 'overdue',
            ]);
        $invoice = Invoice::factory()
            ->for($customer)
            ->create([
                'invoice_number' => 'INV-ISO-'.str_pad(
                    (string) $number,
                    4,
                    '0',
                    STR_PAD_LEFT
                ),
                'status' => 'overdue',
                'due_date' => today()->subDays(10),
            ]);

        return IsolationLog::query()->create([
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'action' => 'isolate',
            'old_status' => 'overdue',
            'new_status' => 'isolated',
            'execution_mode' => 'simulation',
            'approval_status' => 'approved',
        ]);
    }
}
