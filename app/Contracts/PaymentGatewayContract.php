<?php

namespace App\Contracts;

use Illuminate\Http\Request;

interface PaymentGatewayContract
{
    public function handlePayment($amount, $planId = null);

    public function handleWebhook(Request $request);
}
