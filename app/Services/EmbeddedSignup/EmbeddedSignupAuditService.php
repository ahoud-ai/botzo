<?php

namespace App\Services\EmbeddedSignup;

use App\Models\EmbeddedSignupAudit;
use Illuminate\Support\Facades\Log;

class EmbeddedSignupAuditService
{
    public function record(
        string $event,
        string $status,
        array $context = [],
        ?string $errorCode = null,
        ?int $organizationId = null,
        ?int $userId = null,
        ?string $message = null
    ): void {
        EmbeddedSignupAudit::create([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'event' => $event,
            'status' => $status,
            'error_code' => $errorCode,
            'message' => $message,
            'context' => $context,
        ]);

        $logLevel = $status === 'success' ? 'info' : 'warning';

        Log::{$logLevel}('Embedded signup event', [
            'event' => $event,
            'status' => $status,
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'error_code' => $errorCode,
            'message' => $message,
            'context' => $context,
        ]);
    }
}

