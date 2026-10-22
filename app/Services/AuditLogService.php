<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogService
{
    public function record(
        string $action,
        ?Model $auditable,
        string $description,
        array $metadata = []
    ): AuditLog {
        return AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'description' => $description,
            'metadata' => $metadata ?: null,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            'user_agent' => app()->runningInConsole() ? null : request()->userAgent(),
        ]);
    }
}
