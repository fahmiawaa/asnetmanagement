<?php

namespace App\Http\Controllers;

use App\Models\IsolationLog;
use App\Services\AuditLogService;

class IsolationApprovalController extends Controller
{
    public function approve(IsolationLog $isolationLog, AuditLogService $auditLog)
    {
        if ($isolationLog->approval_status !== 'pending') {
            return redirect()
                ->route('isolation-logs.index')
                ->with(
                    'error',
                    'Log isolir ini sudah diproses sebelumnya.'
                );
        }

        $isolationLog->update([
            'approval_status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        $auditLog->record(
            'isolation.approved',
            $isolationLog,
            'Kandidat isolasi disetujui.'
        );

        return redirect()
            ->route('isolation-logs.index')
            ->with(
                'success',
                'Kandidat isolir berhasil disetujui.'
            );
    }

    public function reject(IsolationLog $isolationLog, AuditLogService $auditLog)
    {
        if ($isolationLog->approval_status !== 'pending') {
            return redirect()
                ->route('isolation-logs.index')
                ->with(
                    'error',
                    'Log isolir ini sudah diproses sebelumnya.'
                );
        }

        $isolationLog->update([
            'approval_status' => 'rejected',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        $auditLog->record(
            'isolation.rejected',
            $isolationLog,
            'Kandidat isolasi ditolak.'
        );

        return redirect()
            ->route('isolation-logs.index')
            ->with(
                'success',
                'Kandidat isolir berhasil ditolak.'
            );
    }
}
