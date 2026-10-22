<?php

namespace App\Http\Controllers;

use App\Models\IsolationLog;
use Illuminate\Http\Request;

class IsolationLogController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'action' => 'nullable|in:isolate,restore',
            'approval_status' => 'nullable|in:pending,approved,processing,rejected,executed,failed',
            'execution_mode' => 'nullable|in:simulation,real',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $search = trim((string) ($validated['q'] ?? ''));
        $action = $validated['action'] ?? '';
        $approvalStatus = $validated['approval_status'] ?? '';
        $executionMode = $validated['execution_mode'] ?? '';
        $dateFrom = $validated['date_from'] ?? '';
        $dateTo = $validated['date_to'] ?? '';

        $logs = IsolationLog::query()
            ->with(['customer', 'invoice', 'executor'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('notes', 'like', "%{$search}%")
                        ->orWhere('last_error', 'like', "%{$search}%")
                        ->orWhere('reason', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($query) use ($search) {
                            $query
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('customer_code', 'like', "%{$search}%");
                        })
                        ->orWhereHas('invoice', function ($query) use ($search) {
                            $query->where('invoice_number', 'like', "%{$search}%");
                        });
                });
            })
            ->when($action !== '', fn ($query) => $query->where('action', $action))
            ->when($approvalStatus !== '', fn ($query) => $query->where('approval_status', $approvalStatus))
            ->when($executionMode !== '', fn ($query) => $query->where('execution_mode', $executionMode))
            ->when($dateFrom !== '', fn ($query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($query) => $query->whereDate('created_at', '<=', $dateTo))
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('isolation-logs.index', compact(
            'logs',
            'search',
            'action',
            'approvalStatus',
            'executionMode',
            'dateFrom',
            'dateTo'
        ));
    }
}
