<?php

namespace App\Services\Salla;

use App\Exceptions\SallaStoreNotFound;
use App\Models\SallaStore;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Support\SallaDate;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Applies Salla's trial and subscription events to the store's Botzo subscription.
 *
 * Trials are saved as status "active" on the trial plan. The "trial" status would put them under
 * the site-wide `trial_addons` setting, which could switch the assistant off for them.
 */
class SallaSubscriptions
{
    public const EVENTS = [
        'app.trial.started',
        'app.trial.expired',
        'app.trial.canceled',
        'app.subscription.started',
        'app.subscription.renewed',
        'app.subscription.canceled',
        'app.subscription.expired',
    ];

    private const TRIAL_FALLBACK_DAYS = 7;

    public function __construct(private readonly SallaPlans $plans)
    {
    }

    /** Returns false when the event changes nothing. */
    public function apply(string $event, int $merchantId, array $data, CarbonInterface $occurredAt): bool
    {
        $store = SallaStore::query()->where('merchant_id', $merchantId)->first() ?? throw new SallaStoreNotFound();

        // Add-ons are extras on top of a plan, and do not start or end it.
        if (($data['item_type'] ?? 'plan') === 'addon') {
            return false;
        }

        if ($store->subscription_event_at && $occurredAt->lt($store->subscription_event_at)) {
            return false;
        }

        $applied = DB::transaction(fn () => match ($event) {
            'app.trial.started' => $this->startTrial($store, $data),
            'app.trial.expired', 'app.trial.canceled' => $this->endTrial($store),
            'app.subscription.started', 'app.subscription.renewed' => $this->startPaid($store, $data, $merchantId),
            'app.subscription.canceled' => $this->cancel($store, $data),
            'app.subscription.expired' => $this->expire($store, $data),
        });

        if ($applied) {
            $store->forceFill(['subscription_event_at' => $occurredAt])->save();
        }

        return $applied;
    }

    private function startTrial(SallaStore $store, array $data): bool
    {
        // A merchant who is already paying keeps the paid plan.
        if ($this->current($store) && ! $this->onTrial($store) && $this->isRunning($store)) {
            return false;
        }

        $start = SallaDate::parse($data['start_date'] ?? null) ?? now();

        $this->save($store, $this->plans->trial(), $start, SallaDate::parse($data['end_date'] ?? null, true) ?? $start->copy()->addDays(self::TRIAL_FALLBACK_DAYS));

        return true;
    }

    private function endTrial(SallaStore $store): bool
    {
        if (! $this->onTrial($store)) {
            return false;
        }

        $this->current($store)->update(['valid_until' => now()]);

        return true;
    }

    private function startPaid(SallaStore $store, array $data, int $merchantId): bool
    {
        $start = SallaDate::parse($data['start_date'] ?? null) ?? now();
        $months = max(1, (int) ($data['plan_period'] ?? 1));

        $this->save(
            $store,
            $this->plans->forName(isset($data['plan_name']) ? (string) $data['plan_name'] : null, $merchantId),
            $start,
            SallaDate::parse($data['end_date'] ?? null, true) ?? $start->copy()->addMonths($months)
        );

        $store->salla_subscription_id = isset($data['subscription_id']) ? (string) $data['subscription_id'] : null;

        return true;
    }

    /** A canceled subscription has been paid for up to its end date, so service runs until then. */
    private function cancel(SallaStore $store, array $data): bool
    {
        if ($this->isAnotherSubscription($store, $data) || ! $this->current($store)) {
            return false;
        }

        $end = SallaDate::parse($data['end_date'] ?? null, true);

        $this->current($store)->update(['valid_until' => $end && $end->isFuture() ? $end : now()]);

        return true;
    }

    private function expire(SallaStore $store, array $data): bool
    {
        if ($this->isAnotherSubscription($store, $data) || ! $this->current($store)) {
            return false;
        }

        $this->current($store)->update(['valid_until' => now()]);

        return true;
    }

    /** After an upgrade, the old subscription's end must not stop the new one. */
    private function isAnotherSubscription(SallaStore $store, array $data): bool
    {
        return isset($data['subscription_id'])
            && $store->salla_subscription_id !== null
            && (string) $data['subscription_id'] !== $store->salla_subscription_id;
    }

    private function save(SallaStore $store, SubscriptionPlan $plan, CarbonInterface $start, CarbonInterface $end): void
    {
        $values = [
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => $start,
            'valid_until' => $end,
        ];

        $subscription = $this->current($store);

        $subscription ? $subscription->update($values) : Subscription::create(['organization_id' => $store->organization_id] + $values);
    }

    private function current(SallaStore $store): ?Subscription
    {
        return Subscription::query()->where('organization_id', $store->organization_id)->first();
    }

    private function onTrial(SallaStore $store): bool
    {
        return $this->current($store)?->plan?->metadataValue('salla_plan_key') === SallaPlans::TRIAL;
    }

    private function isRunning(SallaStore $store): bool
    {
        $validUntil = $this->current($store)?->getRawOriginal('valid_until');

        return $validUntil !== null && now()->lt($validUntil);
    }
}
