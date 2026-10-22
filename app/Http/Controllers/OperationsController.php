<?php

namespace App\Http\Controllers;

use App\Models\IsolationLog;
use App\Models\MikrotikDevice;
use App\Services\SystemHealthService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OperationsController extends Controller
{
    public function index(SystemHealthService $health): View
    {
        return view('operations.index', [
            'report' => $health->check(),
            'devices' => MikrotikDevice::query()
                ->orderBy('name')
                ->get(['id', 'name', 'host', 'connection_status', 'last_sync_at']),
            'failedActions' => IsolationLog::query()
                ->with('customer:id,name')
                ->where('approval_status', 'failed')
                ->latest('last_attempt_at')
                ->limit(20)
                ->get(),
            'failedJobs' => Schema::hasTable('failed_jobs')
                ? DB::table('failed_jobs')->latest('failed_at')->limit(20)->get()
                : collect(),
        ]);
    }
}
