<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller as BaseController;
use Illuminate\Http\Request;

class OnboardingTourController extends BaseController
{
    public function finish(Request $request)
    {
        $validated = $request->validate([
            'status' => 'required|in:completed,skipped',
        ]);

        $request->user()->markOnboardingTourStatus($validated['status']);

        return back();
    }
}
