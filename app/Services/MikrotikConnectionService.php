<?php

namespace App\Services;

use App\Models\MikrotikDevice;
use Illuminate\Support\Facades\Crypt;
use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;
use Throwable;

class MikrotikConnectionService
{
    public function connect(MikrotikDevice $device): Client
    {
        $password = Crypt::decryptString(
            $device->encrypted_password
        );

        $config = new Config([
            'host' => $device->host,
            'user' => $device->username,
            'pass' => $password,
            'port' => $device->api_port,
            'ssl' => $device->use_ssl,
            'timeout' => 10,
            'socket_timeout' => 30,
        ]);

        return new Client($config);
    }

    public function getPppoeSecrets(MikrotikDevice $device): array
    {
        $client = $this->connect($device);

        return $client
            ->query('/ppp/secret/print')
            ->read();
    }

    public function getPppProfiles(MikrotikDevice $device): array
    {
        $client = $this->connect($device);

        return $client
            ->query('/ppp/profile/print')
            ->read();
    }

    public function disconnectPppoeSessions(
        MikrotikDevice $device,
        string $username
    ): int {
        $client = $this->connect($device);

        $activeSessions = $client
            ->query('/ppp/active/print')
            ->read();

        $disconnectedCount = 0;

        foreach ($activeSessions as $session) {
            if (($session['name'] ?? null) !== $username) {
                continue;
            }

            $sessionId = $session['.id'] ?? null;

            if (! $sessionId) {
                continue;
            }

            $query = (new Query('/ppp/active/remove'))
                ->equal('.id', $sessionId);

            $client
                ->query($query)
                ->read();

            $disconnectedCount++;
        }

        return $disconnectedCount;
    }

    public function getActivePppoeSessions(
        MikrotikDevice $device
    ): array {
        $client = $this->connect($device);

        return $client
            ->query('/ppp/active/print')
            ->read();
    }

    /**
     * @param  array<int, string>  $usernames
     * @return array<string, array{status: string, disconnected: int, error: string|null}>
     */
    public function isolatePppoeBatch(
        MikrotikDevice $device,
        array $usernames,
        int $failureThreshold
    ): array {
        $client = $this->connect($device);
        $usernames = array_values(array_unique(array_filter(
            array_map('trim', $usernames)
        )));
        $accounts = collect(
            $client->query('/ppp/secret/print')->read()
        )
            ->filter(fn (array $account): bool => ! empty($account['name']))
            ->keyBy('name');
        $sessions = collect(
            $client->query('/ppp/active/print')->read()
        )
            ->filter(fn (array $session): bool => ! empty($session['name']))
            ->groupBy('name');

        $results = [];
        $commandFailures = 0;

        foreach ($usernames as $username) {
            if ($commandFailures >= $failureThreshold) {
                $results[$username] = [
                    'status' => 'circuit_open',
                    'disconnected' => 0,
                    'error' => 'Batas kegagalan batch tercapai; tindakan tidak dijalankan.',
                ];

                continue;
            }

            $account = $accounts->get($username);

            if (! $account || empty($account['.id'])) {
                $results[$username] = [
                    'status' => 'missing',
                    'disconnected' => 0,
                    'error' => 'Akun PPPoE tidak ditemukan di MikroTik.',
                ];

                continue;
            }

            $wasAlreadyDisabled = $this->routerBoolean(
                $account['disabled'] ?? false
            );

            try {
                if (! $wasAlreadyDisabled) {
                    $client
                        ->query((new Query('/ppp/secret/disable'))
                            ->equal('.id', $account['.id']))
                        ->read();
                }

                $disconnected = 0;

                foreach ($sessions->get($username, collect()) as $session) {
                    if (empty($session['.id'])) {
                        continue;
                    }

                    $client
                        ->query((new Query('/ppp/active/remove'))
                            ->equal('.id', $session['.id']))
                        ->read();
                    $disconnected++;
                }

                $results[$username] = [
                    'status' => $wasAlreadyDisabled
                        ? 'already_disabled'
                        : 'action_sent',
                    'disconnected' => $disconnected,
                    'error' => null,
                ];
            } catch (Throwable $exception) {
                $commandFailures++;
                $results[$username] = [
                    'status' => 'command_failed',
                    'disconnected' => 0,
                    'error' => $exception->getMessage(),
                ];
            }
        }

        $verificationAccounts = collect(
            $client->query('/ppp/secret/print')->read()
        )
            ->filter(fn (array $account): bool => ! empty($account['name']))
            ->keyBy('name');
        $activeUsernames = collect(
            $client->query('/ppp/active/print')->read()
        )
            ->pluck('name')
            ->filter()
            ->flip();

        foreach ($results as $username => $result) {
            if (! in_array($result['status'], ['action_sent', 'already_disabled'], true)) {
                continue;
            }

            $verifiedAccount = $verificationAccounts->get($username);
            $isDisabled = $verifiedAccount
                && $this->routerBoolean($verifiedAccount['disabled'] ?? false);
            $isStillActive = $activeUsernames->has($username);

            if (! $isDisabled || $isStillActive) {
                $results[$username]['status'] = 'verification_failed';
                $results[$username]['error'] = ! $isDisabled
                    ? 'RouterOS belum mengonfirmasi PPP secret sebagai disabled.'
                    : 'Sesi PPPoE masih aktif setelah perintah disconnect.';

                continue;
            }

            $results[$username]['status'] = 'executed';
        }

        return $results;
    }

    private function routerBoolean(mixed $value): bool
    {
        return in_array(
            strtolower((string) $value),
            ['true', 'yes', '1'],
            true
        );
    }
}
