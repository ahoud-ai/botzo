<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame text-[var(--ui-text)] min-h-full">
            <UiPageHeader :title="$t('Billing and subscription')" :subtitle="$t('Select the plan that you want to subscribe to')" />

            <div v-if="subscriptionManagedByParent" class="billing-banner billing-banner--info">
                <h3 class="font-semibold">{{ $t('Inherited subscription') }}</h3>
                <p class="mt-1">
                    {{ $t('This branch uses the subscription managed by the parent organization.') }}
                    <span v-if="props.billingOwner?.name">
                        {{ $t('Billing owner') }}: {{ props.billingOwner.name }}.
                    </span>
                </p>
                <p class="mt-1">{{ $t('Upgrade, payment, and coupon management are available from the parent organization only.') }}</p>
            </div>

            <div v-else-if="scheduledPlanChange" class="billing-banner billing-banner--warning">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h3 class="font-semibold">{{ $t('Scheduled plan change') }}</h3>
                        <p class="mt-1">{{ $t('Selected plan will start automatically on the next renewal date.') }}</p>
                        <p class="mt-1">
                            {{ scheduledPlanChange.plan_name }}
                            <span v-if="scheduledPlanChange.effective_at">
                                - {{ $t('will start on') }} {{ scheduledPlanChange.effective_at }}
                            </span>
                        </p>
                    </div>
                    <button type="button" class="billing-btn billing-btn--ghost" @click="cancelScheduledChange">
                        {{ $t('Cancel scheduled change') }}
                    </button>
                </div>
            </div>

            <div v-if="hasPeriodToggle" class="wizard-segmented mb-5">
                <button
                    v-for="option in periodOptions"
                    :key="option.value"
                    type="button"
                    :class="{ 'is-active': selectedPeriod === option.value }"
                    @click="changeBillingPeriod(option.value)"
                >
                    {{ option.label }}
                </button>
            </div>

            <div class="sub-layout">
                <div class="min-w-0">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <SubscriptionPlanCard
                            v-for="item in sortedPlans"
                            :key="item.id"
                            :plan-name="planName(item)"
                            :period-label="planPeriod(item.period)"
                            :price="item.price"
                            :currency-label="$t('SAR')"
                            :price-caption="planPriceCaption(item.period)"
                            :rank-label="item.tier_rank ? `#${item.tier_rank}` : null"
                            :features="planFeatureList(item)"
                            :cta-label="planCtaLabel(item)"
                            :current="isCurrentPlan(item)"
                            :scheduled="isScheduledPlan(item)"
                            :selected="isSelectedPlan(item)"
                            :disabled="subscriptionManagedByParent"
                            @select="selectPlan(item)"
                        />
                    </div>
                </div>

                <div class="summary-card">
                    <h3 class="summary-title">{{ $t('Summary') }}</h3>

                    <div v-if="subscriptionManagedByParent" class="summary-info-box summary-info-box--secondary">
                        <h4 class="summary-info-title">{{ selectedPlanName || $t('Inherited subscription') }}</h4>
                        <p class="mt-1">{{ $t('This branch uses the subscription managed by the parent organization.') }}</p>
                        <p v-if="props.billingOwner?.name" class="mt-1">{{ $t('Billing owner') }}: {{ props.billingOwner.name }}</p>
                        <p class="mt-2">{{ $t('Upgrade, payment, and coupon management are available from the parent organization only.') }}</p>
                    </div>

                    <div v-else-if="!form.plan" class="summary-info-box summary-info-box--muted text-center">
                        {{ $t('Select plan to continue') }}
                    </div>

                    <div v-else-if="selectedChangeAction === 'current_plan'" class="summary-info-box summary-info-box--secondary">
                        <h4 class="summary-info-title">{{ selectedPlanName }}</h4>
                        <p class="mt-1">{{ $t('You are already subscribed to this plan.') }}</p>
                        <p class="mt-1 text-[var(--ui-muted)]">{{ $t('Current plan stays active until the next renewal date.') }}</p>
                    </div>

                    <div
                        v-else-if="['downgrade_at_renewal', 'scheduled_downgrade'].includes(selectedChangeAction)"
                        class="summary-info-box summary-info-box--warning"
                    >
                        <h4 class="summary-info-title">{{ selectedPlanName }}</h4>
                        <p class="mt-1">{{ $t('Selected plan will start automatically on the next renewal date.') }}</p>

                        <div class="billing-detail-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
                            <div>
                                <span class="billing-detail-label">{{ $t('Current plan') }}</span>
                                <span class="billing-detail-value">{{ currentPlanName }}</span>
                            </div>
                            <div>
                                <span class="billing-detail-label">{{ $t('Scheduled plan change') }}</span>
                                <span class="billing-detail-value">{{ selectedPlanName }}</span>
                            </div>
                            <div>
                                <span class="billing-detail-label">{{ $t('Next renewal date') }}</span>
                                <span class="billing-detail-value">{{ effectiveAt || '-' }}</span>
                            </div>
                            <div>
                                <span class="billing-detail-label">{{ $t('Estimated next renewal total') }}</span>
                                <span class="billing-detail-value">{{ renewalAmount }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-else>
                        <div class="summary-row" style="align-items: flex-start;">
                            <div>
                                <div class="font-semibold">{{ selectedPlanName }}</div>
                                <span class="summary-plan-chip">{{ selectedPlanPeriodLabel }}</span>
                            </div>
                            <span class="font-semibold">{{ basePrice }}</span>
                        </div>

                        <div class="summary-row" style="border-top: 1px solid var(--ui-border); margin-top: 0.6rem; padding-top: 0.8rem;">
                            <span>{{ $t('Gross total') }}</span>
                            <span>{{ grossAmount }}</span>
                        </div>

                        <div v-if="taxRates.length > 0" class="summary-box">
                            <div class="summary-box-label">{{ $t('Tax') }}</div>
                            <div v-for="(item, index) in taxRates" :key="index" class="flex justify-between">
                                <span>{{ item.name }} ({{ item.percentage }}%)</span>
                                <span>{{ item.amount }}</span>
                            </div>
                        </div>

                        <div v-if="toFloat(credit.total) > 0" class="summary-box">
                            <div class="flex justify-between">
                                <div>
                                    {{ $t('Available credits') }}
                                    <div class="text-xs text-[var(--ui-muted)]">({{ $t('Applicable credits for this invoice') }})</div>
                                </div>
                                <span class="text-[var(--ui-danger)] font-semibold">
                                    {{ toFloat(-credit.total) <= toFloat(netAmount) ? credit.total : `(${netAmount})` }}
                                </span>
                            </div>
                        </div>

                        <div v-if="toFloat(debit.total) > 0" class="summary-box">
                            <div class="flex justify-between">
                                <div>
                                    {{ $t('Available debits') }}
                                    <div class="text-xs text-[var(--ui-muted)]">({{ $t('Applicable debits due') }})</div>
                                </div>
                                <span class="font-semibold">{{ debit.total }}</span>
                            </div>
                        </div>

                        <div v-if="toFloat(amountDue) > 0" class="summary-box">
                            <div class="summary-box-label">{{ $t('Coupon code') }}</div>
                            <form v-if="coupon.length === 0" class="flex items-center gap-2" @submit.prevent="applyCoupon">
                                <input v-model="form1.coupon" class="ui-input" style="height: 2.3rem; font-size: 0.82rem;">
                                <button type="submit" class="billing-btn billing-btn--solid" style="padding: 0.5rem 0.9rem; font-size: 0.78rem;" :disabled="form1.processing">
                                    <svg v-if="form1.processing" class="animate-spin" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"/></svg>
                                    <span v-else>{{ $t('Apply') }}</span>
                                </button>
                            </form>
                            <span v-if="form1.errors.coupon" class="mt-1 block text-xs text-[var(--ui-danger)]">{{ form1.errors.coupon }}</span>

                            <div v-else class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold">{{ coupon?.code }}</span>
                                    <span v-if="coupon?.type === 'percentage'" class="text-xs text-[var(--ui-muted)]">({{ coupon?.amount }}% {{ $t('OFF') }})</span>
                                    <button type="button" class="text-[var(--ui-danger)]" @click="removeCoupon">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M12 20a8 8 0 1 1 0-16a8 8 0 0 1 0 16M9.707 8.293a1 1 0 0 0-1.414 1.414L10.586 12l-2.293 2.293a1 1 0 1 0 1.414 1.414L12 13.414l2.293 2.293a1 1 0 0 0 1.414-1.414L13.414 12l2.293-2.293a1 1 0 0 0-1.414-1.414L12 10.586z"/></svg>
                                    </button>
                                </div>
                                <span class="font-semibold text-[var(--ui-danger)]">{{ coupon?.discount }}</span>
                            </div>
                        </div>

                        <div class="summary-row total">
                            <span>{{ $t('Total due') }}</span>
                            <span>{{ amountDue }}</span>
                        </div>

                        <template v-if="requiresPaymentMethod">
                            <div class="mb-2 mt-3 text-sm font-semibold">{{ $t('Pay via') }}</div>
                            <div class="grid grid-cols-2 gap-2">
                                <div
                                    v-for="(item, index) in props.methods"
                                    :key="index"
                                    class="billing-method-option"
                                    :class="{ 'border-[var(--ui-secondary)]': form.method === item.name }"
                                    @click="selectPayment(item.name)"
                                >
                                    <span class="billing-method-radio" :class="{ 'billing-method-radio--checked': form.method === item.name }">
                                        <svg v-if="form.method === item.name" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 13 4 4L19 7"/></svg>
                                    </span>
                                    <span class="text-sm">{{ item.name }}</span>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="mt-6">
                        <button
                            v-if="canSubmit"
                            type="button"
                            class="billing-btn billing-btn--solid w-full"
                            @click="submitForm"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M12 13a1.49 1.49 0 0 0-1 2.61V17a1 1 0 0 0 2 0v-1.39A1.49 1.49 0 0 0 12 13m5-4V7A5 5 0 0 0 7 7v2a3 3 0 0 0-3 3v7a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-7a3 3 0 0 0-3-3M9 7a3 3 0 0 1 6 0v2H9Zm9 12a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1Z"/></svg>
                            <span>{{ submitButtonLabel }}</span>
                        </button>
                        <div v-else class="billing-btn w-full" style="background: var(--ui-surface-soft); color: var(--ui-muted); cursor: not-allowed;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M12 13a1.49 1.49 0 0 0-1 2.61V17a1 1 0 0 0 2 0v-1.39A1.49 1.49 0 0 0 12 13m5-4V7A5 5 0 0 0 7 7v2a3 3 0 0 0-3 3v7a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-7a3 3 0 0 0-3-3M9 7a3 3 0 0 1 6 0v2H9Zm9 12a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1Z"/></svg>
                            <span>{{ submitButtonLabel }}</span>
                        </div>
                        <p v-if="requiresPaymentMethod && !form.method" class="mt-2 text-xs text-[var(--ui-muted)]">
                            {{ $t('Select a payment method to continue.') }}
                        </p>
                        <p v-if="requiresPaymentMethod && !hasPaymentMethods" class="mt-2 text-xs text-[var(--ui-danger)]">
                            {{ $t('No payment methods are available right now. Please contact support.') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from './../Layout/App.vue';
import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
import SubscriptionPlanCard from '@/Components/SubscriptionPlanCard.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps([
    'addons',
    'enable_ai_billing',
    'plans',
    'methods',
    'subscription',
    'subscriptionDetails',
    'billingOwner',
    'subscriptionManagedByParent',
    'scheduledPlanChange',
    'selectedPeriod',
    'periodCounts',
]);

const { t, locale } = useI18n();
const subscriptionManagedByParent = computed(() => Boolean(props.subscriptionManagedByParent));
const subscriptionDetails = ref(props.subscriptionDetails ?? {});
const selectedPeriod = computed(() => props.selectedPeriod ?? 'monthly');
const currentPlanMatchesSelectedPeriod = (props.subscription?.plan?.period ?? null) === selectedPeriod.value;

const form = useForm({
    plan: currentPlanMatchesSelectedPeriod ? props.subscription?.plan_id ?? null : null,
    method: null,
    coupon: subscriptionDetails.value?.coupon?.code ?? null,
});

const form1 = useForm({
    coupon: subscriptionDetails.value?.coupon?.code ?? null,
});

const buttonLoading = ref(false);
const periodOptions = computed(() => ([
    { value: 'monthly', label: t('Monthly'), count: props.periodCounts?.monthly ?? 0 },
    { value: 'yearly', label: t('Yearly'), count: props.periodCounts?.yearly ?? 0 },
]).filter((item) => item.count > 0));
const hasPeriodToggle = computed(() => periodOptions.value.length > 1);

const sortedPlans = computed(() => {
    return [...(props.plans?.data ?? [])].sort((left, right) => {
        const rankDiff = (left.tier_rank ?? 0) - (right.tier_rank ?? 0);
        if (rankDiff !== 0) {
            return rankDiff;
        }

        return Number(left.price) - Number(right.price);
    });
});

const scheduledPlanChange = computed(() => subscriptionDetails.value?.scheduledPlanChange ?? props.scheduledPlanChange ?? null);
const selectedChangeAction = computed(() => subscriptionDetails.value?.change_action ?? (form.plan ? 'subscribe_now' : null));
const selectedPlan = computed(() => sortedPlans.value.find((item) => Number(item.id) === Number(form.plan)) ?? null);
const selectedPlanName = computed(() => {
    if (selectedPlan.value) {
        return planName(selectedPlan.value);
    }

    return localizedPlanName(props.subscription?.plan) ?? '';
});
const selectedPlanPeriodLabel = computed(() => selectedPlan.value ? planPeriod(selectedPlan.value.period) : '');
const currentPlanName = computed(() => localizedPlanName(props.subscription?.plan) ?? '-');
const currentPlanId = computed(() => props.subscription?.plan_id ?? null);
const grossAmount = computed(() => subscriptionDetails.value?.grossAmount ?? '0.00');
const netAmount = computed(() => subscriptionDetails.value?.netAmount ?? '0.00');
const amountDue = computed(() => subscriptionDetails.value?.amountDue ?? '0.00');
const taxRates = computed(() => subscriptionDetails.value?.taxRates ?? []);
const credit = computed(() => subscriptionDetails.value?.credit ?? { total: '0.00' });
const debit = computed(() => subscriptionDetails.value?.debit ?? { total: '0.00' });
const basePrice = computed(() => subscriptionDetails.value?.basePrice ?? '0.00');
const coupon = computed(() => subscriptionDetails.value?.coupon ?? []);
const effectiveAt = computed(() => subscriptionDetails.value?.effectiveAt ?? scheduledPlanChange.value?.effective_at ?? null);
const renewalAmount = computed(() => subscriptionDetails.value?.renewalAmount ?? subscriptionDetails.value?.netAmount ?? '0.00');
const hasPaymentMethods = computed(() => Array.isArray(props.methods) && props.methods.length > 0);

const requiresPaymentMethod = computed(() => {
    if (subscriptionManagedByParent.value) {
        return false;
    }

    if (!form.plan) {
        return false;
    }

    if (['current_plan', 'downgrade_at_renewal', 'scheduled_downgrade'].includes(selectedChangeAction.value)) {
        return false;
    }

    return toFloat(amountDue.value) > 0;
});

const canSubmit = computed(() => {
    if (subscriptionManagedByParent.value || !form.plan || buttonLoading.value) {
        return false;
    }

    if (['current_plan', 'scheduled_downgrade'].includes(selectedChangeAction.value)) {
        return false;
    }

    if (requiresPaymentMethod.value && (!form.method || !hasPaymentMethods.value)) {
        return false;
    }

    return true;
});

const submitButtonLabel = computed(() => {
    if (buttonLoading.value) {
        return t('Redirecting you');
    }

    if (requiresPaymentMethod.value && !hasPaymentMethods.value) {
        return t('No payment methods available');
    }

    switch (selectedChangeAction.value) {
        case 'current_plan':
            return t('Current plan');
        case 'scheduled_downgrade':
            return t('Downgrade scheduled');
        case 'downgrade_at_renewal':
            return t('Schedule for renewal');
        default:
            return t('Continue');
    }
});

const parseMetadata = (item) => {
    if (!item?.metadata) {
        return {};
    }

    if (typeof item.metadata === 'object') {
        return item.metadata;
    }

    try {
        return JSON.parse(item.metadata);
    } catch (error) {
        return {};
    }
};

const planName = (item) => item?.display_name ?? item?.name ?? '-';

const localizedPlanName = (item) => {
    if (!item) {
        return null;
    }

    if (item.display_name) {
        return item.display_name;
    }

    const isArabic = String(locale.value ?? '').startsWith('ar');

    if (isArabic) {
        return item.name_ar || item.name || item.name_en || null;
    }

    return item.name_en || item.name || item.name_ar || null;
};

const planPeriod = (period) => (period === 'yearly' ? t('Per year') : t('Per month'));

const planPriceCaption = (period) => (period === 'yearly' ? t('Billed yearly') : t('Billed monthly'));

const normalizeAddonFlag = (value) => {
    if (typeof value === 'boolean') {
        return value;
    }

    if (typeof value === 'number') {
        return value === 1;
    }

    if (typeof value === 'string') {
        return ['1', 'true', 'yes', 'on'].includes(value.trim().toLowerCase());
    }

    return false;
};

const filteredAddons = (item) => {
    const addons = parseMetadata(item).addons ?? {};

    if (!addons || typeof addons !== 'object') {
        return {};
    }

    return Object.entries(addons).reduce((accumulator, [key, value]) => {
        accumulator[key] = normalizeAddonFlag(value);

        return accumulator;
    }, {});
};

const planFeatureList = (item) => {
    const metadata = parseMetadata(item);
    const features = [];
    const formatLimit = (value, labelKey) => `${value === -1 ? t('Unlimited') : value} ${t(labelKey)}`;
    const pushLimit = (key, labelKey) => {
        if (metadata[key] === undefined || metadata[key] === null || metadata[key] === '') {
            return;
        }

        features.push({
            label: formatLimit(metadata[key], labelKey),
        });
    };

    pushLimit('campaign_limit', 'Campaigns');
    pushLimit('message_limit', 'Messages');
    pushLimit('contacts_limit', 'Contacts');
    pushLimit('canned_replies_limit', 'Canned replies');
    pushLimit('team_limit', 'Users');
    pushLimit('branches_limit', 'Branches');

    Object.entries(filteredAddons(item)).forEach(([key, value]) => {
        if (normalizeAddonFlag(value)) {
            features.push({
                label: t(key),
            });
        }
    });

    if (Array.isArray(metadata.custom_features)) {
        metadata.custom_features.forEach((feature) => {
            const localized = String(locale.value).startsWith('ar')
                ? (feature?.text_ar || feature?.text_en)
                : (feature?.text_en || feature?.text_ar);
            if (localized) {
                features.push({ label: localized });
            }
        });
    }

    return features;
};

const isCurrentPlan = (item) => Number(currentPlanId.value) === Number(item.id);

const isScheduledPlan = (item) => Number(scheduledPlanChange.value?.plan_id) === Number(item.id);

const isSelectedPlan = (item) => Number(form.plan) === Number(item.id) && !isCurrentPlan(item) && !isScheduledPlan(item);

const planCtaLabel = (item) => {
    if (isCurrentPlan(item)) {
        return t('Current plan');
    }

    if (isScheduledPlan(item)) {
        return t('Downgrade scheduled');
    }

    return t('Select Plan');
};

const hydratePreview = (response) => {
    const data = response.props.response_data.data;
    subscriptionDetails.value = data;
    form1.coupon = data?.coupon?.code ?? null;
    form.coupon = data?.coupon?.code ?? null;
};

const changeBillingPeriod = (period) => {
    if (period === selectedPeriod.value) {
        return;
    }

    router.get('/subscription', {
        period,
        coupon: form.coupon || undefined,
    }, {
        preserveScroll: true,
    });
};

const selectPlan = (item) => {
    if (subscriptionManagedByParent.value) {
        return;
    }

    form.plan = item.id;
    form.method = null;

    router.get(`/subscription/${item.id}`, {
        coupon: form.coupon || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: hydratePreview,
    });
};

const selectPayment = (method) => {
    if (!requiresPaymentMethod.value || !hasPaymentMethods.value) {
        return;
    }

    form.method = method;
};

const removeCoupon = () => {
    if (subscriptionManagedByParent.value || !form.plan) {
        return;
    }

    form1.coupon = null;
    form.coupon = null;

    router.delete(`/subscription/coupon/remove/${form.plan}`, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: hydratePreview,
    });
};

const applyCoupon = () => {
    if (subscriptionManagedByParent.value || !form.plan) {
        return;
    }

    form1.post(`/subscription/coupon/apply/${form.plan}`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: hydratePreview,
    });
};

const cancelScheduledChange = () => {
    router.post('/subscription/scheduled-change/cancel', {}, {
        preserveScroll: true,
        preserveState: true,
    });
};

const submitForm = async () => {
    if (!canSubmit.value) {
        return;
    }

    buttonLoading.value = true;

    form.post('/subscription', {
        preserveScroll: true,
        onFinish: () => {
            buttonLoading.value = false;
        },
    });
};

const toFloat = (value) => {
    const normalized = String(value ?? '0').replace(/,/g, '');
    const parsed = parseFloat(normalized);

    return Number.isNaN(parsed) ? 0 : parsed;
};
</script>

<style scoped>
.billing-banner {
    margin-bottom: 1rem;
    padding: 1rem 1.15rem;
    border-radius: var(--ui-radius-md);
    font-size: 0.9rem;
    line-height: 1.5;
}

.billing-banner--info {
    background: color-mix(in srgb, var(--ui-secondary) 8%, var(--ui-surface));
    border: 1px solid color-mix(in srgb, var(--ui-secondary) 20%, var(--ui-border));
    color: var(--ui-text);
}

.billing-banner--warning {
    background: color-mix(in srgb, var(--ui-warning) 12%, var(--ui-surface));
    border: 1px solid color-mix(in srgb, var(--ui-warning) 30%, var(--ui-border));
    color: color-mix(in srgb, var(--ui-warning) 75%, var(--ui-text));
}

.billing-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    border-radius: 0.7rem;
    padding: 0.65rem 1.2rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: background-color 160ms ease, filter 160ms ease;
}

.billing-btn--solid {
    color: #fff;
    background: var(--ui-secondary);
}

.billing-btn--solid:hover:not(:disabled) {
    filter: brightness(1.05);
}

.billing-btn--solid:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.billing-btn--ghost {
    color: var(--ui-text);
    background: var(--ui-surface-soft);
    border: 1px solid var(--ui-border);
}

.billing-btn--ghost:hover {
    background: var(--ui-border);
}

.wizard-segmented {
    display: flex;
    border: 1px solid var(--ui-border);
    border-radius: 0.7rem;
    overflow: hidden;
    width: fit-content;
}

.wizard-segmented button {
    border: none;
    background: var(--ui-surface-soft);
    color: var(--ui-muted);
    font-size: 0.82rem;
    font-weight: 700;
    padding: 0.6rem 1.3rem;
    cursor: pointer;
}

.wizard-segmented button.is-active {
    background: var(--ui-secondary);
    color: #fff;
}

.sub-layout {
    display: grid;
    gap: 1.25rem;
    align-items: start;
}

@media (min-width: 1280px) {
    .sub-layout {
        grid-template-columns: minmax(0, 1fr) 22rem;
    }
}

.summary-card {
    position: sticky;
    top: 1.25rem;
    border-radius: 1.25rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    padding: 1.4rem;
}

.summary-title {
    font-size: 1.05rem;
    font-weight: 800;
    margin: 0 0 1rem;
}

.summary-info-box {
    border-radius: 0.9rem;
    padding: 1.1rem;
    font-size: 0.85rem;
    line-height: 1.6;
}

.summary-info-box--muted {
    border: 1px dashed var(--ui-border-strong);
    color: var(--ui-muted);
    padding: 2.5rem 1.1rem;
}

.summary-info-box--secondary {
    background: color-mix(in srgb, var(--ui-secondary) 8%, var(--ui-surface));
    border: 1px solid color-mix(in srgb, var(--ui-secondary) 20%, var(--ui-border));
}

.summary-info-box--warning {
    background: color-mix(in srgb, var(--ui-warning) 10%, var(--ui-surface));
    border: 1px solid color-mix(in srgb, var(--ui-warning) 28%, var(--ui-border));
    color: color-mix(in srgb, var(--ui-warning) 75%, var(--ui-text));
}

.summary-info-title {
    font-weight: 700;
    font-size: 0.92rem;
    margin: 0 0 0.3rem;
    color: var(--ui-text);
}

.summary-plan-chip {
    display: inline-flex;
    font-size: 0.7rem;
    font-weight: 700;
    background: var(--ui-surface-soft);
    border: 1px solid var(--ui-border);
    border-radius: 999px;
    padding: 0.2rem 0.6rem;
    margin-top: 0.3rem;
    text-transform: capitalize;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.85rem;
    padding: 0.4rem 0;
}

.summary-row.total {
    font-size: 1.05rem;
    font-weight: 800;
    border-top: 1px solid var(--ui-border);
    margin-top: 0.4rem;
    padding-top: 0.8rem;
}

.summary-box {
    background: var(--ui-surface-soft);
    border-radius: 0.7rem;
    padding: 0.7rem 0.85rem;
    margin: 0.6rem 0;
    font-size: 0.82rem;
}

.summary-box-label {
    font-size: 0.75rem;
    color: var(--ui-muted);
    border-bottom: 1px dashed var(--ui-border);
    padding-bottom: 0.4rem;
    margin-bottom: 0.4rem;
}

.billing-method-option {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.65rem 0.85rem;
    border-radius: 0.65rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    cursor: pointer;
    transition: border-color 160ms ease;
}

.billing-method-option:hover {
    border-color: var(--ui-secondary);
}

.billing-method-radio {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 1.2rem;
    height: 1.2rem;
    border-radius: 0.35rem;
    border: 1.5px solid var(--ui-border-strong);
    color: #fff;
}

.billing-method-radio--checked {
    background: var(--ui-secondary);
    border-color: var(--ui-secondary);
}

.billing-detail-grid {
    display: grid;
    gap: 1.1rem;
    margin-top: 1rem;
    padding: 1rem;
    border-radius: var(--ui-radius-md);
    background: var(--ui-surface);
}

.billing-detail-label {
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--ui-muted);
}

.billing-detail-value {
    display: block;
    margin-top: 0.35rem;
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--ui-text);
}
</style>
