<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'action' => 'nullable|string|max:100',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $search = trim((string) ($validated['q'] ?? ''));
        $action = trim((string) ($validated['action'] ?? ''));
        $dateFrom = $validated['date_from'] ?? '';
        $dateTo = $validated['date_to'] ?? '';

        $logs = AuditLog::query()
            ->with('user')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('description', 'like', "%{$search}%")
                        ->orWhere('action', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($query) use ($search) {
                            $query
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->when($action !== '', fn ($query) => $query->where('action', $action))
            ->when($dateFrom !== '', fn ($query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($query) => $query->whereDate('created_at', '<=', $dateTo))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $actions = AuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return view('audit-logs.index', compact(
            'logs',
            'actions',
            'search',
            'action',
            'dateFrom',
            'dateTo'
        ));
    }
}
