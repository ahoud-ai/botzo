<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame text-[var(--ui-text)] min-h-full">
            <div class="flex justify-between mt-8 md:mt-0">
                <div>
                    <h2 class="text-xl mb-1">{{ $t('Billing and subscription') }}</h2>
                    <p class="mb-6 flex items-center text-sm leading-6 text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                        <span class="ms-1 mt-1">{{ $t('Select the plan that you want to subscribe to') }}</span>
                    </p>
                </div>
            </div>

            <div v-if="subscriptionManagedByParent" class="mb-4 rounded-[0.75rem] border border-sky-100 bg-sky-50 px-4 py-4 text-sm text-sky-900">
                <h3 class="font-medium">{{ $t('Inherited subscription') }}</h3>
                <p class="mt-1">
                    {{ $t('This branch uses the subscription managed by the parent organization.') }}
                    <span v-if="props.billingOwner?.name">
                        {{ $t('Billing owner') }}: {{ props.billingOwner.name }}.
                    </span>
                </p>
                <p class="mt-1 text-sky-800">{{ $t('Upgrade, payment, and coupon management are available from the parent organization only.') }}</p>
            </div>

            <div
                v-else-if="scheduledPlanChange"
                class="mb-4 rounded-[0.85rem] border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-950"
            >
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h3 class="font-medium">{{ $t('Scheduled plan change') }}</h3>
                        <p class="mt-1">
                            {{ $t('Selected plan will start automatically on the next renewal date.') }}
                        </p>
                        <p class="mt-1">
                            {{ scheduledPlanChange.plan_name }}
                            <span v-if="scheduledPlanChange.effective_at">
                                - {{ $t('will start on') }} {{ scheduledPlanChange.effective_at }}
                            </span>
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-md border border-amber-300 bg-white px-3 py-2 text-sm text-amber-900 transition hover:bg-amber-100"
                        @click="cancelScheduledChange"
                    >
                        {{ $t('Cancel scheduled change') }}
                    </button>
                </div>
            </div>

            <div v-if="hasPeriodToggle" class="mb-5 flex justify-center md:justify-start">
                <div class="inline-flex items-center rounded-full border border-slate-200 bg-white p-1 shadow-sm">
                    <button
                        v-for="option in periodOptions"
                        :key="option.value"
                        type="button"
                        class="rounded-full px-4 py-2 text-sm font-medium transition"
                        :class="selectedPeriod === option.value ? 'bg-primary text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100'"
                        @click="changeBillingPeriod(option.value)"
                    >
                        {{ option.label }}
                    </button>
                </div>
            </div>

            <div class="xl:grid xl:grid-cols-[minmax(0,1fr)_22rem] xl:items-start xl:gap-4">
                <div class="min-w-0">
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <div
                            v-for="item in sortedPlans"
                            :key="item.id"
                            class="relative rounded-[0.9rem] border p-5 shadow-sm transition-all duration-200"
                            :class="planCardClasses(item)"
                            @click="selectPlan(item)"
                        >
                            <div class="absolute inset-x-0 -top-3 flex justify-center gap-2">
                                <span
                                    v-if="isCurrentPlan(item)"
                                    class="rounded-full bg-slate-900 px-4 py-1 text-xs font-semibold text-white shadow-lg"
                                >
                                    {{ $t('Current plan') }}
                                </span>
                                <span
                                    v-if="isScheduledPlan(item)"
                                    class="rounded-full bg-amber-500 px-4 py-1 text-xs font-semibold text-white shadow-lg"
                                >
                                    {{ $t('Downgrade scheduled') }}
                                </span>
                            </div>

                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h3 class="text-lg font-semibold">{{ planName(item) }}</h3>
                                    <p class="mt-1 text-sm text-slate-500">{{ planPeriod(item.period) }}</p>
                                </div>
                                <div class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-600">
                                    #{{ item.tier_rank }}
                                </div>
                            </div>

                            <div class="mt-6">
                                <div class="flex items-end gap-2">
                                    <span class="text-4xl font-semibold tracking-tight">{{ item.price }}</span>
                                    <span class="pb-1 text-sm text-slate-500">{{ $t('SAR') }}</span>
                                </div>
                                <p class="mt-2 text-sm text-slate-500">{{ planPriceCaption(item.period) }}</p>
                            </div>

                            <div class="mt-6 space-y-3">
                                <div
                                    v-for="feature in planFeatureList(item)"
                                    :key="`${item.id}-${feature.label}`"
                                    class="flex items-start gap-2 text-sm"
                                >
                                    <span class="mt-0.5 text-emerald-500">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16px" height="16px" viewBox="0 0 16 16"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"><path d="M14.25 8.75c-.5 2.5-2.385 4.854-5.03 5.38A6.25 6.25 0 0 1 3.373 3.798C5.187 1.8 8.25 1.25 10.75 2.25"/><path d="m5.75 7.75l2.5 2.5l6-6.5"/></g></svg>
                                    </span>
                                    <span>{{ feature.label }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 xl:sticky xl:top-6 xl:mt-0">
                    <div class="rounded-[0.9rem] bg-white p-5 shadow-md">
                        <h3 class="text-xl mb-4">{{ $t('Summary') }}</h3>

                        <div v-if="subscriptionManagedByParent" class="rounded-[0.8rem] border border-dashed border-sky-200 bg-sky-50 p-5 text-sm text-sky-900">
                            <h3 class="font-medium">{{ selectedPlanName || $t('Inherited subscription') }}</h3>
                            <p class="mt-2">{{ $t('This branch uses the subscription managed by the parent organization.') }}</p>
                            <p v-if="props.billingOwner?.name" class="mt-1">{{ $t('Billing owner') }}: {{ props.billingOwner.name }}</p>
                            <p class="mt-3 text-sky-800">{{ $t('Upgrade, payment, and coupon management are available from the parent organization only.') }}</p>
                        </div>

                        <div v-else-if="!form.plan" class="rounded-[0.8rem] border border-dashed border-slate-200 p-10 text-center text-sm text-slate-500">
                            {{ $t('Select plan to continue') }}
                        </div>

                        <div v-else-if="selectedChangeAction === 'current_plan'" class="rounded-[0.8rem] border border-slate-200 bg-slate-50 p-5 text-sm text-slate-700">
                            <h3 class="text-base font-semibold text-slate-900">{{ selectedPlanName }}</h3>
                            <p class="mt-2">{{ $t('You are already subscribed to this plan.') }}</p>
                            <p class="mt-1 text-slate-500">{{ $t('Current plan stays active until the next renewal date.') }}</p>
                        </div>

                        <div
                            v-else-if="['downgrade_at_renewal', 'scheduled_downgrade'].includes(selectedChangeAction)"
                            class="rounded-[0.8rem] border border-amber-200 bg-amber-50 p-5 text-sm text-amber-950"
                        >
                            <div class="space-y-3">
                                <div>
                                    <h3 class="text-base font-semibold">{{ selectedPlanName }}</h3>
                                    <p class="mt-1 text-amber-800">{{ $t('Selected plan will start automatically on the next renewal date.') }}</p>
                                </div>

                                <div class="grid gap-3 rounded-[0.75rem] bg-white/70 p-4 text-sm md:grid-cols-2">
                                    <div>
                                        <div class="text-xs uppercase tracking-wide text-amber-700">{{ $t('Current plan') }}</div>
                                        <div class="mt-1 font-medium text-slate-900">{{ currentPlanName }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs uppercase tracking-wide text-amber-700">{{ $t('Scheduled plan change') }}</div>
                                        <div class="mt-1 font-medium text-slate-900">{{ selectedPlanName }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs uppercase tracking-wide text-amber-700">{{ $t('Next renewal date') }}</div>
                                        <div class="mt-1 font-medium text-slate-900">{{ effectiveAt || '-' }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs uppercase tracking-wide text-amber-700">{{ $t('Estimated next renewal total') }}</div>
                                        <div class="mt-1 font-medium text-slate-900">{{ renewalAmount }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else>
                            <div class="flex justify-between mb-4 text-sm">
                                <div>
                                    <h3>{{ selectedPlanName }}</h3>
                                    <h3 class="mt-1 w-fit rounded-md bg-slate-100 px-2 py-1 text-xs capitalize">{{ selectedPlanPeriodLabel }}</h3>
                                </div>
                                <h3>{{ basePrice }}</h3>
                            </div>
                            <hr>
                            <div class="mt-4 flex justify-between text-sm">
                                <h3>{{ $t('Gross total') }}</h3>
                                <h3>{{ grossAmount }}</h3>
                            </div>
                            <div v-if="taxRates.length > 0" class="mt-2 mb-2 space-y-2 rounded-md bg-slate-100 px-2 py-2">
                                <h3 class="border-b border-dashed text-sm">{{ $t('Tax') }}</h3>
                                <div v-for="(item, index) in taxRates" :key="index" class="flex justify-between text-sm">
                                    <h3>{{ item.name }} <span>({{ item.percentage }}%)</span></h3>
                                    <h3>{{ item.amount }}</h3>
                                </div>
                            </div>
                            <div v-if="toFloat(credit.total) > 0" class="mt-2 mb-2 space-y-2 rounded-md bg-slate-100 px-2 py-2">
                                <div class="flex justify-between text-sm">
                                    <div>{{ $t('Available credits') }} <br><span class="text-xs">({{ $t('Applicable credits for this invoice') }})</span></div>
                                    <h3 class="text-red-500">
                                        {{ toFloat(-credit.total) <= toFloat(netAmount) ? credit.total : `(${netAmount})` }}
                                    </h3>
                                </div>
                            </div>
                            <div v-if="toFloat(debit.total) > 0" class="mt-2 mb-2 space-y-2 rounded-md bg-slate-100 px-2 py-2">
                                <div class="flex justify-between text-sm">
                                    <div>{{ $t('Available debits') }} <br><span class="text-xs">({{ $t('Applicable debits due') }})</span></div>
                                    <h3>{{ debit.total }}</h3>
                                </div>
                            </div>
                            <div v-if="toFloat(amountDue) > 0" class="mt-2 mb-2 space-y-2 rounded-md bg-slate-100 px-2 py-2">
                                <div class="text-sm">
                                    <div class="border-b border-dashed text-sm">{{ $t('Coupon code') }}</div>
                                    <form
                                        v-if="coupon.length === 0"
                                        class="mt-2 w-full rounded-md border-0 bg-white py-1 ps-2 pe-1 text-gray-900 shadow-sm outline-none ring-1 ring-inset placeholder:text-gray-400 sm:text-sm sm:leading-6"
                                        @submit.prevent="applyCoupon"
                                    >
                                        <div class="flex items-center justify-between">
                                            <input v-model="form1.coupon" class="h-full w-3/4 outline-none">
                                            <button type="submit" :class="['flex h-full w-fit items-center justify-center rounded-md bg-primary py-0.5 px-2 text-[12px] text-white', { 'opacity-50': form1.processing }]" :disabled="form1.processing">
                                                <svg v-if="form1.processing" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                                                <span v-else>{{ $t('Apply') }}</span>
                                            </button>
                                        </div>
                                        <span v-if="form1.errors.coupon" class="text-xs text-red-500">{{ form1.errors.coupon }}</span>
                                    </form>

                                    <div v-else class="mt-2 flex justify-between text-sm">
                                        <div class="flex items-center">
                                            <h3>{{ coupon?.code }}</h3>
                                            <span v-if="coupon?.type === 'percentage'">({{ coupon?.amount }}% {{ $t('OFF') }})</span>
                                            <button type="button" class="text-red-500" @click="removeCoupon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 20a8 8 0 1 1 0-16a8 8 0 0 1 0 16M9.707 8.293a1 1 0 0 0-1.414 1.414L10.586 12l-2.293 2.293a1 1 0 1 0 1.414 1.414L12 13.414l2.293 2.293a1 1 0 0 0 1.414-1.414L13.414 12l2.293-2.293a1 1 0 0 0-1.414-1.414L12 10.586z"/></svg>
                                            </button>
                                        </div>
                                        <h3 class="text-red-500">{{ coupon?.discount }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4 mb-4 flex justify-between text-xl">
                                <h3>{{ $t('Total due') }}</h3>
                                <h3>{{ amountDue }}</h3>
                            </div>
                            <hr>
                            <h2 v-if="requiresPaymentMethod" class="mt-3 mb-2 text-[14px]">{{ $t('Pay via') }}</h2>
                            <div v-if="requiresPaymentMethod" class="grid grid-cols-2 gap-2">
                                <div v-for="(item, index) in props.methods" :key="index">
                                    <div class="flex items-center">
                                        <label class="cursor-pointer" @click="selectPayment(item.name)">
                                            <div class="flex h-5 w-5 items-center justify-center rounded-md border border-gray-400" :class="form.method === item.name ? 'bg-[color:var(--ui-text)]' : ''">
                                                <svg v-if="form.method === item.name" class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                </svg>
                                            </div>
                                        </label>
                                        <span class="ms-2 cursor-pointer text-sm" @click="selectPayment(item.name)">{{ item.name }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-8">
                            <button
                                v-if="canSubmit"
                                type="button"
                                class="flex w-full items-center justify-center gap-x-1 rounded-md bg-primary px-3 py-2 text-sm text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                                @click="submitForm"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M12 13a1.49 1.49 0 0 0-1 2.61V17a1 1 0 0 0 2 0v-1.39A1.49 1.49 0 0 0 12 13m5-4V7A5 5 0 0 0 7 7v2a3 3 0 0 0-3 3v7a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-7a3 3 0 0 0-3-3M9 7a3 3 0 0 1 6 0v2H9Zm9 12a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1Z"/></svg>
                                <span>{{ submitButtonLabel }}</span>
                            </button>
                            <div
                                v-else
                                class="flex w-full items-center justify-center gap-x-1 rounded-md bg-gray-300 px-3 py-2 text-sm text-gray-400 shadow-sm"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M12 13a1.49 1.49 0 0 0-1 2.61V17a1 1 0 0 0 2 0v-1.39A1.49 1.49 0 0 0 12 13m5-4V7A5 5 0 0 0 7 7v2a3 3 0 0 0-3 3v7a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-7a3 3 0 0 0-3-3M9 7a3 3 0 0 1 6 0v2H9Zm9 12a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1Z"/></svg>
                                <span>{{ submitButtonLabel }}</span>
                            </div>
                            <p v-if="requiresPaymentMethod && !form.method" class="mt-2 text-xs text-slate-500">
                                {{ $t('Select a payment method to continue.') }}
                            </p>
                            <p v-if="requiresPaymentMethod && !hasPaymentMethods" class="mt-2 text-xs text-rose-600">
                                {{ $t('No payment methods are available right now. Please contact support.') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from './../Layout/App.vue';
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

const addonLabelKeyMap = {
    'Flow builder': 'Flow Builder',
};

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

const resolveAddonLabelKey = (addon) => addonLabelKeyMap[addon] ?? addon;

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

    return Object.entries(props.addons ?? {}).reduce((accumulator, [key, value]) => {
        if (Number(value) === 1 && Object.prototype.hasOwnProperty.call(addons, key)) {
            accumulator[key] = normalizeAddonFlag(addons[key]);
        }

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
                label: t(resolveAddonLabelKey(key)),
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

const planCardClasses = (item) => {
    if (subscriptionManagedByParent.value) {
        return 'cursor-not-allowed border-slate-100 bg-slate-50 opacity-70';
    }

    if (isCurrentPlan(item)) {
        return 'cursor-pointer border-slate-900 bg-slate-50';
    }

    if (isScheduledPlan(item)) {
        return 'cursor-pointer border-amber-300 bg-amber-50/60';
    }

    if (Number(form.plan) === Number(item.id)) {
        return 'cursor-pointer border-primary bg-indigo-50/40';
    }

    return 'cursor-pointer border-slate-100 bg-white hover:border-slate-300';
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
