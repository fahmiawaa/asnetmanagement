<?php

namespace App\Services;

use App\Models\GenieacsServer;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use LogicException;
use RuntimeException;

class GenieAcsService
{
    /**
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    public function fetchDevices(
        GenieacsServer $server,
        array $query = [],
        int $limit = 100,
        int $skip = 0,
    ): array {
        $projection = $this->safeProjection();
        $parameters = [
            'projection' => implode(',', $projection),
            'limit' => max(1, $limit),
            'skip' => max(0, $skip),
        ];

        if ($query !== []) {
            $parameters['query'] = json_encode($query, JSON_THROW_ON_ERROR);
        }

        $response = $this->request($server)
            ->retry([200, 500], throw: false)
            ->get('/devices', $parameters);

        $response->throw();
        $devices = $response->json();

        if (! is_array($devices) || ! array_is_list($devices)) {
            throw new RuntimeException('Respons daftar perangkat GenieACS tidak valid.');
        }

        return $devices;
    }

    public function testConnection(GenieacsServer $server): bool
    {
        try {
            $this->fetchDevices($server, limit: 1);
            $server->forceFill([
                'connection_status' => 'connected',
                'last_connected_at' => now(),
                'last_error_at' => null,
                'last_error_message' => null,
            ])->save();

            return true;
        } catch (\Throwable $exception) {
            $server->forceFill([
                'connection_status' => 'failed',
                'last_error_at' => now(),
                'last_error_message' => str($exception->getMessage())->limit(1000),
            ])->save();

            return false;
        }
    }

    private function request(GenieacsServer $server): PendingRequest
    {
        $request = Http::baseUrl(rtrim($server->base_url, '/'))
            ->acceptJson()
            ->connectTimeout((int) config('genieacs.connect_timeout_seconds'))
            ->timeout((int) config('genieacs.request_timeout_seconds'));

        if (filled($server->username) && filled($server->encrypted_password)) {
            $request->withBasicAuth(
                (string) $server->username,
                (string) $server->encrypted_password
            );
        }

        return $request;
    }

    /**
     * @return array<int, string>
     */
    private function safeProjection(): array
    {
        $projection = config('genieacs.projection', []);
        $denied = config('genieacs.denied_projection_fragments', []);

        if (! is_array($projection) || ! is_array($denied)) {
            throw new LogicException('Konfigurasi projection GenieACS tidak valid.');
        }

        foreach ($projection as $parameter) {
            if (! is_string($parameter)) {
                throw new LogicException('Parameter projection GenieACS tidak valid.');
            }

            foreach ($denied as $fragment) {
                if (str_contains(strtolower($parameter), strtolower((string) $fragment))) {
                    throw new LogicException('Projection GenieACS memuat parameter terlarang.');
                }
            }
        }

        return array_values($projection);
    }
}
