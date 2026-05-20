<?php

namespace App\Contracts;

use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

interface WebhookVerificationContract
{
    public function verifyWhatsappRequest(Request $request, Organization $organization): ?JsonResponse;
}
