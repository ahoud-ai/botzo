<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\StoreBillingTransaction;
use App\Services\BillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BillingController extends BaseController
{
    private BillingService $billingService;

    public function __construct()
    {
        $this->billingService = new BillingService();
    }

    /**
     * Previous billing screen routes now render the unified payment logs hub directly.
     */
    public function index(Request $request, PaymentController $paymentController)
    {
        return $paymentController->index($request);
    }

    public function create(Request $request, PaymentController $paymentController)
    {
        return $paymentController->index($request);
    }

    /**
     * Store a newly created billing transaction in storage.
     */
    public function store(StoreBillingTransaction $request)
    {
        $this->billingService->store($request);

        return back()->with(
            'status',
            [
                'type' => 'success',
                'message' => __('Transaction created successfully!'),
            ]
        );
    }

    public function show(Request $request, string $id, PaymentController $paymentController)
    {
        return $paymentController->index($request);
    }

    public function edit(Request $request, string $id, PaymentController $paymentController)
    {
        return $paymentController->index($request);
    }
}
