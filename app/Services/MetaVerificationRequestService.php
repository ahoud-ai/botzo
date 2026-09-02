<?php

namespace App\Services;

use App\Models\MetaVerificationRequest;
use Illuminate\Validation\ValidationException;

class MetaVerificationRequestService
{
    public function advance(MetaVerificationRequest $request): void
    {
        $next = $request->isTerminal() ? null : $request->nextStatus();

        if ($next === null) {
            throw ValidationException::withMessages([
                'status' => __('This request cannot be advanced further.'),
            ]);
        }

        $request->update(['status' => $next]);
    }

    public function reject(MetaVerificationRequest $request, string $reason): void
    {
        if ($request->isTerminal()) {
            throw ValidationException::withMessages([
                'status' => __('This request cannot be advanced further.'),
            ]);
        }

        $request->update([
            'status' => MetaVerificationRequest::STATUS_REJECTED,
            'rejection_reason' => $reason,
        ]);
    }
}
