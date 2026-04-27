<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogger
{
    public static function log(
        Request $request,
        User $actor,
        string $action,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?array $metadata = null,
    ): void {
        AuditLog::create([
            'id' => Str::uuid()->toString(),
            'admin_id' => $actor->id,
            'actor_user_id' => $actor->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'metadata' => $metadata,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);
    }
}
