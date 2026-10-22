<?php

namespace App\Http\Controllers;

use App\Models\MikrotikDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class MikrotikWebhookController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $token = (string) config('mikrotik.webhook_token');
        $providedToken = (string) $request->header('X-ASNet-Mikrotik-Token');
        $allowedIps = config('mikrotik.webhook_allowed_ips', []);

        if (! $token || ! $providedToken || ! hash_equals($token, $providedToken)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($allowedIps && ! in_array($request->ip(), $allowedIps, true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $deviceId = $request->input('device_id', $request->input('mikrotik_id'));
        $device = MikrotikDevice::find($deviceId);

        if (! $device) {
            return response()->json(['message' => 'Unknown MikroTik device'], 422);
        }

        $event = (string) $request->input('event', $request->input('monitoring', 'unknown'));

        Log::info('MikroTik webhook received.', [
            'device_id' => $device->id,
            'event' => $event,
            'source_ip' => $request->ip(),
        ]);

        $exitCode = Artisan::call('mikrotik:sync-pppoe', [
            'deviceId' => $device->id,
        ]);

        if ($exitCode !== 0) {
            return response()->json([
                'message' => 'Webhook diterima, tetapi sinkronisasi gagal.',
            ], 202);
        }

        return response()->json([
            'message' => 'Webhook diterima dan sinkronisasi selesai.',
        ]);
    }
}
