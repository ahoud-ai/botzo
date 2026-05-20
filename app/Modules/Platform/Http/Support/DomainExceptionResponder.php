<?php

namespace App\Modules\Platform\Http\Support;

use App\Modules\Platform\Domain\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DomainExceptionResponder
{
    public function respond(Request $request, DomainException $exception): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => $exception->context(),
            ], $exception->status());
        }

        return redirect()->back()->with('status', [
            'type' => 'error',
            'message' => $exception->getMessage(),
            'errors' => $exception->context(),
        ]);
    }
}
