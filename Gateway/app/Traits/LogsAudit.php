<?php

namespace App\Traits;

use App\Models\AuditLog;

trait LogsAudit
{
    protected function auditLog(string $action, string $resource, mixed $resourceId, array $payload = []): void
    {
        $user = auth()->user();

        $sanitized = array_filter($payload, function ($value, $key) {
            return !($value instanceof \Illuminate\Http\UploadedFile)
                && !str_contains(strtolower((string) $key), 'password');
        }, ARRAY_FILTER_USE_BOTH);

        AuditLog::create([
            'action'      => $action,
            'resource'    => $resource,
            'resource_id' => $resourceId !== null ? (string) $resourceId : null,
            'performed_by'=> $user?->email,
            'role'        => $user?->role,
            'ip_address'  => request()->ip(),
            'payload'     => empty($sanitized) ? null : $sanitized,
        ]);
    }
}
