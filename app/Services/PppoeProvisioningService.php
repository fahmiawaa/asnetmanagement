<?php

namespace App\Services;

use App\Models\PppoeAccount;
use Illuminate\Support\Facades\Crypt;
use RouterOS\Query;
use RuntimeException;

class PppoeProvisioningService
{
    public function __construct(
        private readonly MikrotikConnectionService $connectionService
    ) {}

    public function provision(PppoeAccount $account, string $password): string
    {
        return $this->reconcile($account, $password);
    }

    public function update(
        PppoeAccount $account,
        string $oldUsername,
        ?string $password = null
    ): string {
        return $this->reconcile($account, $password, $oldUsername);
    }

    public function setDisabled(PppoeAccount $account, bool $disabled): int
    {
        $this->assertRealActionsEnabled();

        $device = $account->mikrotikDevice;

        if (! $device) {
            throw new RuntimeException('Perangkat MikroTik untuk akun PPPoE tidak ditemukan.');
        }

        $liveAccount = collect($this->connectionService->getPppoeSecrets($device))
            ->firstWhere('name', $account->username);

        if (! $liveAccount || empty($liveAccount['.id'])) {
            throw new RuntimeException("Akun {$account->username} tidak ditemukan di MikroTik.");
        }

        $endpoint = $disabled
            ? '/ppp/secret/disable'
            : '/ppp/secret/enable';

        $this->connectionService
            ->connect($device)
            ->query((new Query($endpoint))->equal('.id', $liveAccount['.id']))
            ->read();

        if (! $disabled) {
            return 0;
        }

        return $this->connectionService->disconnectPppoeSessions(
            $device,
            $account->username
        );
    }

    private function reconcile(
        PppoeAccount $account,
        ?string $password,
        ?string $oldUsername = null
    ): string {
        $this->assertRealActionsEnabled();

        $device = $account->mikrotikDevice;

        if (! $device) {
            throw new RuntimeException('Perangkat MikroTik untuk akun PPPoE tidak ditemukan.');
        }

        $liveAccounts = $this->connectionService->getPppoeSecrets($device);
        $lookupUsername = $oldUsername ?: $account->username;
        $liveAccount = collect($liveAccounts)
            ->firstWhere('name', $lookupUsername);

        $fields = [
            'name' => $account->username,
            'service' => $account->service ?: 'pppoe',
            'disabled' => $account->disabled ? 'yes' : 'no',
        ];

        if ($password !== null && $password !== '') {
            $fields['password'] = $password;
        }

        if ($account->profile) {
            $fields['profile'] = $account->profile;
        }

        if ($account->local_address) {
            $fields['local-address'] = $account->local_address;
        }

        if ($account->remote_address) {
            $fields['remote-address'] = $account->remote_address;
        }

        if ($account->comment) {
            $fields['comment'] = $account->comment;
        }

        if ($liveAccount && ! empty($liveAccount['.id'])) {
            $query = (new Query('/ppp/secret/set'))
                ->equal('.id', $liveAccount['.id']);

            foreach ($fields as $key => $value) {
                $query->equal($key, $value);
            }

            $this->connectionService
                ->connect($device)
                ->query($query)
                ->read();

            return 'updated';
        }

        if ($password === null || $password === '') {
            throw new RuntimeException(
                "Akun {$account->username} tidak ditemukan di MikroTik dan password diperlukan untuk membuatnya."
            );
        }

        $query = new Query('/ppp/secret/add');

        foreach ($fields as $key => $value) {
            $query->equal($key, $value);
        }

        $this->connectionService
            ->connect($device)
            ->query($query)
            ->read();

        return 'created';
    }

    private function assertRealActionsEnabled(): void
    {
        if (! config('mikrotik.allow_real_actions')) {
            throw new RuntimeException(
                'Aksi real MikroTik masih dinonaktifkan. Set MIKROTIK_ALLOW_REAL_ACTIONS=true setelah koneksi diuji.'
            );
        }
    }

    public function encryptPassword(string $password): string
    {
        return Crypt::encryptString($password);
    }
}
