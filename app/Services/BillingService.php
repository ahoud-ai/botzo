<?php

namespace App\Services;

use App\Http\Resources\BillingResource;
use App\Models\BillingCredit;
use App\Models\BillingDebit;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\BillingTransaction;
use App\Models\Organization;
use App\Services\SubscriptionService;
use App\Support\BillingPaymentMethodResolver;
use DB;
use Illuminate\Validation\ValidationException;

class BillingService
{
    /**
     * Get all billing history based on the provided request filters.
     *
     * @param Request $request
     * @return mixed
     */
    public function get(object $request, $organizationUuid = NULL)
    {
        return $this->getTransactions($request, $organizationUuid);
    }

    public function getTransactions(object $request, $organizationUuid = NULL)
    {
        if ($organizationUuid !== null) {
            $organization = Organization::with('subscription.plan')->where('uuid', $organizationUuid)->first();
            $organizationId = optional(
                app(OrganizationHierarchyService::class)->billingOwner(optional($organization)->id)
            )->id ?? optional($organization)->id;
        } else {
            $filterOrganizationUuid = trim((string) $request->query('organization_uuid', ''));
            $organizationId = $filterOrganizationUuid !== ''
                ? Organization::query()->where('uuid', $filterOrganizationUuid)->value('id')
                : null;
        }

        $rows = (new BillingTransaction)->listAll(
            $request->query('search'),
            $organizationId,
            $request->query('date_from'),
            $request->query('date_to')
        );

        return BillingResource::collection($rows);
    }

    public function organizationOptions(): array
    {
        $invoiceOrganizationIds = BillingInvoice::query()->select('organization_id');
        $transactionOrganizationIds = BillingTransaction::query()->select('organization_id');

        return Organization::query()
            ->whereIn('id', $invoiceOrganizationIds->union($transactionOrganizationIds))
            ->orderBy('name')
            ->get(['uuid', 'name'])
            ->map(fn (Organization $organization) => [
                'value' => $organization->uuid,
                'label' => $organization->name,
            ])
            ->values()
            ->all();
    }

    /**
     * Store a new billing transaction
     *
     * @param Request $request
     */
    public function store($request){
        return DB::transaction(function () use ($request) {
            $organization = Organization::where('uuid', $request->uuid)->firstOrFail();
            $billingOwner = app(OrganizationHierarchyService::class)->billingOwner($organization->id);

            if ($organization->organization_type === 'branch' && $billingOwner && $billingOwner->id !== $organization->id) {
                throw ValidationException::withMessages([
                    'uuid' => __('Subscription and billing are managed from the parent organization.'),
                ]);
            }
    
            $modelClass = match ($request->type) {
                'credit' => BillingCredit::class,
                'debit' => BillingDebit::class,
                'payment' => BillingPayment::class,
            };

            $transactionData = [
                'organization_id' => $organization->id,
                'amount' => $request->amount,
            ];
            
            if (in_array($request->type, ['credit', 'debit'])) {
                $transactionData['description'] = $request->description;
            }
            
            if ($request->type === 'payment') {
                $transactionData['processor'] = $request->method;
                $transactionData['payment_method'] = BillingPaymentMethodResolver::displayLabel($request->method, $request->method);
            }
    
            $entry = $modelClass::create($transactionData);
    
            $transaction = BillingTransaction::create([
                'organization_id' => $organization->id,
                'entity_type' => $request->type,
                'entity_id' => $entry->id,
                'description' => $request->type === 'payment'
                    ? __('Payment via :method', [
                        'method' => BillingPaymentMethodResolver::displayLabel($entry->payment_method ?? null, $entry->processor ?? $request->method),
                    ])
                    : $request->description,
                'amount' => $request->type === 'debit' || $request->type === 'invoice' ? -$request->amount : $request->amount,
                'created_by' => auth()->user()->id
            ]);

            //Activate organization's plan if credits cover cost of plan
            $subscriptionService = new SubscriptionService();
            $activate = $subscriptionService::activateSubscriptionIfInactiveAndExpiredWithCredits($organization->id, auth()->user()->id);

            return $transaction;
        });
    }
}
