<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $metadata
     * @param  array<string, mixed>  $attributes  additional (fillable) columns,
     *                                           e.g. the structured correction data
     */
    public function log(string $action, string $description, ?string $subjectType = null, ?int $subjectId = null, ?array $metadata = null, array $attributes = []): AuditLog
    {
        return AuditLog::create(array_merge([
            'user_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'description' => $description,
            'metadata' => $metadata,
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255),
        ], $attributes));
    }
}
