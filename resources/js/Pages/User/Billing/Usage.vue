<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame text-[var(--ui-text)] min-h-full">
            <div class="flex flex-col gap-4 md:mt-0 md:flex-row md:items-start md:justify-between">
                <div>
                    <h2 class="text-xl mb-1">{{ $t('Usage and limits') }}</h2>
                    <p class="mb-6 flex items-center text-sm leading-6 text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                        <span class="ms-1 mt-1">{{ $t('Live usage snapshot for the billing owner and all linked branches.') }}</span>
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link
                        href="/billing"
                        class="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm transition hover:bg-slate-50"
                    >
                        {{ $t('Back') }}
                    </Link>
                    <Link
                        v-if="!subscriptionManagedByParent"
                        href="/subscription"
                        class="rounded-md bg-primary px-3 py-2 text-sm text-white shadow-sm transition hover:bg-indigo-500"
                    >
                        {{ $t('Manage plans') }}
                    </Link>
                </div>
            </div>

            <div v-if="subscriptionManagedByParent" class="mb-4 rounded-[0.75rem] border border-sky-100 bg-sky-50 px-4 py-4 text-sm text-sky-900">
                <h3 class="font-medium">{{ $t('Inherited subscription') }}</h3>
                <p class="mt-1">
                    {{ $t('This branch uses the subscription managed by the parent organization.') }}
                    <span v-if="billingOwner?.name">
                        {{ $t('Billing owner') }}: {{ billingOwner.name }}.
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
                        <p class="mt-1">{{ $t('Selected plan will start automatically on the next renewal date.') }}</p>
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

            <div v-if="usageNotices.length" class="mb-4 space-y-3">
                <div
                    v-for="notice in usageNotices"
                    :key="notice.key"
                    class="rounded-[0.85rem] border px-4 py-4 text-sm"
                    :class="noticeClasses(notice)"
                >
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full" :class="noticeIconClasses(notice)" v-html="noticeIconSvg(notice)"></span>
                        <div>
                            <h3 class="font-semibold">{{ $t(notice.title) }}</h3>
                            <p class="mt-1 leading-6">{{ $t(notice.message) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-[0.9rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $t('Current plan') }}</p>
                    <h3 class="mt-3 text-lg font-semibold text-slate-900">{{ activePlanName || $t('Not set') }}</h3>
                    <p class="mt-2 text-sm text-slate-500">{{ currentPlanPeriodLabel }}</p>
                </article>

                <article class="rounded-[0.9rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $t('Billing owner') }}</p>
                    <h3 class="mt-3 text-lg font-semibold text-slate-900">{{ billingOwner?.name || $t('Not set') }}</h3>
                    <p class="mt-2 text-sm text-slate-500">{{ subscriptionManagedByParent ? $t('Managed from parent subscription') : $t('Billing workspace') }}</p>
                </article>

                <article class="rounded-[0.9rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $t('Usage period') }}</p>
                    <h3 class="mt-3 text-lg font-semibold text-slate-900">{{ $t(usageSummary?.period_label || 'Current month') }}</h3>
                    <p class="mt-2 text-sm text-slate-500">{{ $t('Current month') }}</p>
                </article>

                <article class="rounded-[0.9rem] border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $t('Tracked metrics') }}</p>
                    <h3 class="mt-3 text-lg font-semibold text-slate-900">{{ usageMetrics.length }}</h3>
                    <p class="mt-2 text-sm text-slate-500">{{ $t('Usage and limits') }}</p>
                </article>
            </div>

            <div class="mt-6 rounded-[1rem] border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">{{ $t('Usage and limits') }}</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ $t('Live usage snapshot for the billing owner and all linked branches.') }}</p>
                    </div>
                    <div class="rounded-full bg-slate-100 px-4 py-2 text-xs font-medium text-slate-600">
                        {{ billingOwner?.name || $t('Not set') }}
                    </div>
                </div>

                <div v-if="usageMetrics.length" class="mt-6 grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="metric in usageMetrics"
                        :key="metric.key"
                        class="rounded-[1rem] border p-4 transition-all duration-200"
                        :class="metricCardClasses(metric)"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-start gap-3">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border bg-white text-primary shadow-sm" v-html="metric.icon"></span>
                                <div>
                                    <h3 class="text-sm font-semibold text-slate-900">{{ $t(metric.label) }}</h3>
                                    <p class="mt-1 text-xs leading-5 text-slate-500">{{ $t(metric.helper) }}</p>
                                </div>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-medium" :class="metricBadgeClasses(metric)">
                                {{ metric.used }} / {{ formatLimit(metric.limit) }}
                            </span>
                        </div>

                        <div class="mt-4 grid grid-cols-3 gap-3 text-sm">
                            <div class="rounded-2xl bg-white px-3 py-3">
                                <p class="text-xs text-slate-500">{{ $t('Used') }}</p>
                                <p class="mt-1 font-semibold text-slate-900">{{ metric.used }}</p>
                            </div>
                            <div class="rounded-2xl bg-white px-3 py-3">
                                <p class="text-xs text-slate-500">{{ $t('Remaining') }}</p>
                                <p class="mt-1 font-semibold text-slate-900">{{ remainingLabel(metric) }}</p>
                            </div>
                            <div class="rounded-2xl bg-white px-3 py-3">
                                <p class="text-xs text-slate-500">{{ $t('Limit') }}</p>
                                <p class="mt-1 font-semibold text-slate-900">{{ formatLimit(metric.limit) }}</p>
                            </div>
                        </div>

                        <div v-if="metric.limit >= 0" class="mt-4">
                            <div class="mb-2 flex items-center justify-between text-xs text-slate-500">
                                <span>{{ $t('Usage') }}</span>
                                <span>{{ metric.percentage ?? 0 }}%</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-slate-200">
                                <div class="h-full rounded-full transition-all duration-500" :class="metricProgressClasses(metric)" :style="{ width: `${metric.percentage ?? 0}%` }"></div>
                            </div>
                        </div>
                    </article>
                </div>

                <div v-else class="mt-6 rounded-[0.9rem] border border-dashed border-slate-200 px-5 py-12 text-center text-sm text-slate-500">
                    {{ $t('No usage metrics available yet') }}
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from './../Layout/App.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps([
    'subscription',
    'billingOwner',
    'subscriptionManagedByParent',
    'usageSummary',
    'scheduledPlanChange',
]);

const { t, locale } = useI18n();

const iconMap = {
    branches: '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M7 7h10v4h2V7a2 2 0 0 0-2-2h-4V2h-2v3H7a2 2 0 0 0-2 2v4h2zm-2 6v4a2 2 0 0 0 2 2h4v3h2v-3h4a2 2 0 0 0 2-2v-4h-2v4H7v-4z"/></svg>',
    users: '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M12 12a4 4 0 1 0-4-4a4 4 0 0 0 4 4m0 2c-4.33 0-8 2.17-8 5v1h16v-1c0-2.83-3.67-5-8-5"/></svg>',
    contacts: '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M19 3H5a2 2 0 0 0-2 2v14l4-3h12a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2m-7 3a3 3 0 1 1-3 3a3 3 0 0 1 3-3m4 8H8v-1c0-1.33 2.67-2 4-2s4 .67 4 2z"/></svg>',
    campaigns: '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="m3 11l18-5v2l-8 2.22V18l-4-2v-4.67L3 13z"/></svg>',
    messages: '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M4 4h16a2 2 0 0 1 2 2v14l-4-3H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2m2 5h12V7H6zm0 4h8v-2H6z"/></svg>',
    canned_replies: '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M4 4h16v12H5.17L4 17.17zm2 2v6h10V6zm11 13H7v-2h10z"/></svg>',
    ai_text: '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2m-1 15H9v-2h2zm4-4H9V7h6z"/></svg>',
    ai_audio: '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M14 3.23v17.54a1 1 0 0 1-1.64.77L7 17H3a1 1 0 0 1-1-1v-8a1 1 0 0 1 1-1h4l5.36-4.54A1 1 0 0 1 14 3.23m3.54 4.05l1.42-1.42A8 8 0 0 1 21 12a8 8 0 0 1-2.04 5.14l-1.42-1.42A6 6 0 0 0 19 12a6 6 0 0 0-1.46-4.72"/></svg>',
    ai_system_key: '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M7 14a3 3 0 1 1 2.83-4H20v2h-2v2h-2v-2h-2v-2H9.83A3 3 0 0 1 7 14m0-2a1 1 0 1 0 0-2a1 1 0 0 0 0 2m10 10a3 3 0 0 1-2.83-2H4v-2h10.17A3 3 0 1 1 17 22m0-2a1 1 0 1 0 0-2a1 1 0 0 0 0 2"/></svg>',
    active_flows: '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M7 3a4 4 0 0 1 4 4c0 .73-.2 1.41-.55 2H13.5A3.5 3.5 0 1 1 17 12.5V14a4 4 0 1 1-2 0v-1.5A3.5 3.5 0 0 1 11.5 9H8.45C8.8 9.59 9 10.27 9 11a4 4 0 1 1-2-3.45A4 4 0 0 1 7 3m0 2a2 2 0 1 0 2 2a2 2 0 0 0-2-2m8.5 5a1.5 1.5 0 1 0 1.5 1.5A1.5 1.5 0 0 0 15.5 10M5 15a2 2 0 1 0 2 2a2 2 0 0 0-2-2m10 2a2 2 0 1 0 2 2a2 2 0 0 0-2-2"/></svg>',
    flow_runs: '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M11 2v2.07A8.001 8.001 0 0 0 4.07 11H2a10 10 0 0 1 9-8.93M13 2a10 10 0 0 1 9 8.93h-2.07A8.001 8.001 0 0 0 13 4.07zM4.07 13A8.001 8.001 0 0 0 11 19.93V22a10 10 0 0 1-8.93-9zm15.86 0H22a10 10 0 0 1-9 9v-2.07A8.001 8.001 0 0 0 19.93 13M12 8l4 4l-4 4l-1.41-1.41L12.17 13H8v-2h4.17l-1.58-1.59z"/></svg>',
    default: '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M11 17h2v-6h-2zm0-8h2V7h-2zm1 13C6.48 22 2 17.52 2 12S6.48 2 12 2s10 4.48 10 10s-4.48 10-10 10"/></svg>',
};

const usageMetrics = computed(() => (props.usageSummary?.metrics ?? []).map((metric) => ({
    ...metric,
    icon: iconMap[metric.key] ?? iconMap.default,
})));
const usageNotices = computed(() => props.usageSummary?.notices ?? []);

const billingOwner = computed(() => props.billingOwner ?? null);
const usageSummary = computed(() => props.usageSummary ?? null);
const subscriptionManagedByParent = computed(() => Boolean(props.subscriptionManagedByParent));
const scheduledPlanChange = computed(() => props.scheduledPlanChange ?? null);
const activePlanName = computed(() => localizedPlanName(props.subscription?.plan) ?? '');
const currentPlanPeriodLabel = computed(() => {
    if (props.subscription?.plan?.period === 'yearly') {
        return t('Per year');
    }

    return t('Per month');
});

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

const formatLimit = (limit) => limit < 0 ? t('Unlimited') : limit;
const remainingLabel = (metric) => metric.limit < 0 ? t('Unlimited') : metric.remaining;

const metricBadgeClasses = (metric) => {
    if (metric.status === 'exceeded') {
        return 'bg-red-100 text-red-700';
    }

    if (metric.status === 'warning') {
        return 'bg-amber-100 text-amber-700';
    }

    if (metric.status === 'unlimited') {
        return 'bg-slate-100 text-slate-700';
    }

    return 'bg-emerald-100 text-emerald-700';
};

const metricCardClasses = (metric) => {
    if (metric.status === 'exceeded') {
        return 'border-red-200 bg-red-50/70';
    }

    if (metric.status === 'warning') {
        return 'border-amber-200 bg-amber-50/70';
    }

    return 'border-slate-200 bg-slate-50/80';
};

const metricProgressClasses = (metric) => {
    if (metric.status === 'exceeded') {
        return 'bg-red-500';
    }

    if (metric.status === 'warning') {
        return 'bg-amber-500';
    }

    return 'bg-primary';
};

const cancelScheduledChange = () => {
    router.post('/subscription/scheduled-change/cancel', {}, {
        preserveScroll: true,
        preserveState: true,
    });
};

const noticeClasses = (notice) => {
    if (notice.type === 'danger') {
        return 'border-red-200 bg-red-50 text-red-950';
    }

    if (notice.type === 'warning') {
        return 'border-amber-200 bg-amber-50 text-amber-950';
    }

    return 'border-sky-200 bg-sky-50 text-sky-950';
};

const noticeIconClasses = (notice) => {
    if (notice.type === 'danger') {
        return 'bg-red-100 text-red-700';
    }

    if (notice.type === 'warning') {
        return 'bg-amber-100 text-amber-700';
    }

    return 'bg-sky-100 text-sky-700';
};

const noticeIconSvg = (notice) => {
    if (notice.type === 'danger') {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2L1 21h22zm0 4.84L19.53 19H4.47zM11 10h2v5h-2zm0 6h2v2h-2z"/></svg>';
    }

    if (notice.type === 'warning') {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M1 21h22L12 2zm12-3h-2v-2h2zm0-4h-2v-4h2z"/></svg>';
    }

    return '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M11 9h2V7h-2zm0 8h2v-6h-2zm1-15C6.48 2 2 6.48 2 12s4.48 10 10 10s10-4.48 10-10S17.52 2 12 2"/></svg>';
};
</script>
