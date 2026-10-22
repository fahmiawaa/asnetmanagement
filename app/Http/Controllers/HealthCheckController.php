<?php

namespace App\Http\Controllers;

use App\Services\SystemHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HealthCheckController extends Controller
{
    public function __invoke(Request $request, SystemHealthService $health): JsonResponse
    {
        $expectedToken = config('operations.health_token');

        if ($expectedToken && ! hash_equals(
            (string) $expectedToken,
            (string) $request->header('X-ASNet-Health-Token')
        )) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $report = $health->check();
        $publicChecks = collect($report['checks'])
            ->map(fn (array $check): array => [
                'status' => $check['status'],
                'message' => $check['message'],
            ]);

        return response()->json([
            'status' => $report['status'],
            'checked_at' => $report['checked_at'],
            'checks' => $publicChecks,
        ], $report['status'] === 'critical' ? 503 : 200);
    }
}
