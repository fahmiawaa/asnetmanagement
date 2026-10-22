<?php

namespace App\Services;

use App\Models\GenieacsServer;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GenieAcsTaskService
{
    /**
     * Kirim connection request agar perangkat segera menghubungi ACS.
     *
     * @return array{accepted: bool, status_code: int, remote_task_id: ?string, error: ?string}
     */
    public function summon(GenieacsServer $server, string $deviceId): array
    {
        $command = config('genieacs.tasks.commands.summon');

        return $this->sendCommand($server, $deviceId, is_array($command) ? $command : ['name' => 'ConnectionRequest']);
    }

    /**
     * @return array{accepted: bool, status_code: int, remote_task_id: ?string, error: ?string}
     */
    public function reboot(GenieacsServer $server, string $deviceId): array
    {
        $command = config('genieacs.tasks.commands.reboot');

        return $this->sendCommand($server, $deviceId, is_array($command) ? $command : ['name' => 'Reboot']);
    }

    /**
     * Tulis satu parameter melalui task GenieACS. Nilai tidak pernah dicatat ke log.
     *
     * @return array{accepted: bool, status_code: int, remote_task_id: ?string, error: ?string}
     */
    public function setParameter(
        GenieacsServer $server,
        string $deviceId,
        string $path,
        string $value,
    ): array {
        $template = config('genieacs.tasks.set_parameter_body');
        $template = is_array($template)
            ? $template
            : ['name' => 'SetVirtualParameter', 'objectName' => '{path}', 'value' => '{value}'];

        $body = array_map(
            fn (mixed $item): mixed => is_string($item)
                ? strtr($item, ['{path}' => $path, '{value}' => $value])
                : $item,
            $template,
        );

        $response = $this->request($server)
            ->post($this->path('task', $deviceId), $body);

        return $this->result($response->status(), $response->json(), $value, $response->body());
    }

    public function readParameter(GenieacsServer $server, string $deviceId, string $path): ?string
    {
        $this->guardRead($path);

        $response = $this->request($server)
            ->get($this->path('read', $deviceId), ['projection' => $path]);
        $response->throw();

        return $this->scalar($response->json($path));
    }

    public function readLastInform(GenieacsServer $server, string $deviceId): ?CarbonImmutable
    {
        $response = $this->request($server)
            ->get($this->path('read', $deviceId), ['projection' => '_lastInform']);
        $response->throw();

        $value = $response->json('_lastInform');

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{accepted: bool, status_code: int, remote_task_id: ?string, error: ?string}
     */
    private function sendCommand(GenieacsServer $server, string $deviceId, array $body): array
    {
        $response = $this->request($server)
            ->post($this->path('command', $deviceId), $body);

        return $this->result($response->status(), $response->json(), body: $response->body());
    }

    /**
     * GenieACS membalas 202 ketika perintah diterima ke antrean, bukan ketika diterapkan.
     *
     * @return array{accepted: bool, status_code: int, remote_task_id: ?string, error: ?string}
     */
    private function result(int $status, mixed $json, ?string $secret = null, ?string $body = null): array
    {
        $accepted = in_array($status, [200, 201, 202, 204], true);

        $remoteTaskId = null;

        if (is_array($json)) {
            $candidate = $json['id'] ?? $json['_id'] ?? null;
            $remoteTaskId = is_scalar($candidate) ? (string) $candidate : null;
        }

        if ($accepted) {
            return [
                'accepted' => true,
                'status_code' => $status,
                'remote_task_id' => $remoteTaskId,
                'error' => null,
            ];
        }

        if (is_string($json) && $json !== '') {
            $message = $json;
        } elseif (is_array($json)) {
            $message = (string) json_encode($json);
        } elseif (is_string($body) && trim($body) !== '') {
            $message = $body;
        } else {
            $message = 'Respons tidak diterima';
        }

        if ($secret !== null && $secret !== '') {
            $message = str_replace($secret, '***', $message);
        }

        return [
            'accepted' => false,
            'status_code' => $status,
            'remote_task_id' => null,
            'error' => str($message)->limit(500)->toString(),
        ];
    }

    private function request(GenieacsServer $server): PendingRequest
    {
        $request = Http::baseUrl(rtrim($server->base_url, '/'))
            ->acceptJson()
            ->connectTimeout((int) config('genieacs.connect_timeout_seconds'))
            ->timeout((int) config('genieacs.tasks.command_timeout_seconds'));

        if (filled($server->username) && filled($server->encrypted_password)) {
            $request->withBasicAuth(
                (string) $server->username,
                (string) $server->encrypted_password
            );
        }

        return $request;
    }

    private function path(string $endpoint, string $deviceId): string
    {
        $template = (string) config("genieacs.tasks.endpoints.{$endpoint}");

        if ($template === '') {
            throw new RuntimeException("Endpoint GenieACS '{$endpoint}' belum dikonfigurasi.");
        }

        return strtr($template, ['{device}' => rawurlencode($deviceId)]);
    }

    private function guardRead(string $path): void
    {
        $denylist = config('genieacs.tasks.read_denylist_fragments', []);

        if (! is_array($denylist)) {
            throw new RuntimeException('Konfigurasi denylist pembacaan GenieACS tidak valid.');
        }

        $lowered = strtolower($path);

        foreach ($denylist as $fragment) {
            if (is_string($fragment) && str_contains($lowered, strtolower($fragment))) {
                throw new RuntimeException('Parameter tersebut masuk denylist dan tidak boleh dibaca.');
            }
        }
    }

    private function scalar(mixed $value): ?string
    {
        if (is_array($value) && array_key_exists('_value', $value)) {
            $value = $value['_value'];
        }

        if (! is_scalar($value)) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
