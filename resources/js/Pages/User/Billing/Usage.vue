<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame text-[var(--ui-text)] min-h-full">
            <UiPageHeader :title="$t('Usage and limits')" :subtitle="$t('Live usage snapshot for the billing owner and all linked branches.')">
                <template #actions>
                    <Link href="/billing" class="usage-btn usage-btn--ghost">{{ $t('Back') }}</Link>
                    <Link v-if="!subscriptionManagedByParent" href="/subscription" class="usage-btn usage-btn--solid">{{ $t('Manage plans') }}</Link>
                </template>
            </UiPageHeader>

            <div v-if="subscriptionManagedByParent" class="usage-banner usage-banner--info">
                <h3 class="font-semibold">{{ $t('Inherited subscription') }}</h3>
                <p class="mt-1">
                    {{ $t('This branch uses the subscription managed by the parent organization.') }}
                    <span v-if="billingOwner?.name">{{ $t('Billing owner') }}: {{ billingOwner.name }}.</span>
                </p>
                <p class="mt-1">{{ $t('Upgrade, payment, and coupon management are available from the parent organization only.') }}</p>
            </div>

            <div v-else-if="scheduledPlanChange" class="usage-banner usage-banner--warning">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h3 class="font-semibold">{{ $t('Scheduled plan change') }}</h3>
                        <p class="mt-1">{{ $t('Selected plan will start automatically on the next renewal date.') }}</p>
                        <p class="mt-1">
                            {{ scheduledPlanChange.plan_name }}
                            <span v-if="scheduledPlanChange.effective_at">- {{ $t('will start on') }} {{ scheduledPlanChange.effective_at }}</span>
                        </p>
                    </div>
                    <button type="button" class="usage-btn usage-btn--ghost" @click="cancelScheduledChange">{{ $t('Cancel scheduled change') }}</button>
                </div>
            </div>

            <div v-if="usageNotices.length" class="space-y-3 mb-1">
                <div v-for="notice in usageNotices" :key="notice.key" class="usage-banner" :class="`usage-banner--${notice.type === 'danger' ? 'danger' : notice.type === 'warning' ? 'warning' : 'info'}`">
                    <div class="flex items-start gap-3">
                        <span class="usage-notice-icon" :class="`usage-notice-icon--${notice.type === 'danger' ? 'danger' : notice.type === 'warning' ? 'warning' : 'info'}`" v-html="noticeIconSvg(notice)"></span>
                        <div>
                            <h3 class="font-semibold">{{ $t(notice.title) }}</h3>
                            <p class="mt-1 leading-6">{{ $t(notice.message) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <UiStatCard :title="$t('Current plan')" :value="activePlanName || $t('Not set')">
                    <template #icon>
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 3h10a2 2 0 0 1 2 2v16l-7-3l-7 3V5a2 2 0 0 1 2-2Z"/></svg>
                    </template>
                </UiStatCard>
                <UiStatCard :title="$t('Billing owner')" :value="billingOwner?.name || $t('Not set')">
                    <template #icon>
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8a4 4 0 0 0 0 8Zm12 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </template>
                </UiStatCard>
                <UiStatCard :title="$t('Usage period')" :value="$t(usageSummary?.period_label || 'Current month')">
                    <template #icon>
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 2v3m8-3v3M3.5 9h17M4 5h16a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/></svg>
                    </template>
                </UiStatCard>
            </div>

            <UiSectionCard v-for="group in metricGroups" :key="group.key" :title="group.title" class="mt-6">
                <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                    <article v-for="metric in group.metrics" :key="metric.key" class="usage-metric-card" :class="`usage-metric-card--${metric.status}`">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-start gap-3">
                                <span class="usage-metric-icon" :class="`usage-metric-icon--${metric.status}`" v-html="metric.icon"></span>
                                <div>
                                    <h3 class="font-bold text-[15px] text-[var(--ui-text)]">{{ $t(metric.label) }}</h3>
                                    <p class="mt-0.5 text-xs leading-5 text-[var(--ui-muted)]">{{ $t(metric.helper) }}</p>
                                </div>
                            </div>
                            <span class="usage-metric-badge" :class="`usage-metric-badge--${metric.status}`" dir="ltr">{{ metric.used }} / {{ formatLimit(metric.limit) }}</span>
                        </div>

                        <div v-if="metric.limit >= 0" class="mt-4">
                            <div class="mb-1.5 flex items-center justify-between text-xs text-[var(--ui-muted)]">
                                <span>{{ $t('Usage') }}</span>
                                <span class="font-bold" :class="`usage-metric-percent--${metric.status}`">{{ metric.percentage ?? 0 }}%</span>
                            </div>
                            <div class="usage-meter-track">
                                <div class="usage-meter-fill" :class="`usage-meter-fill--${metric.status}`" :style="{ width: `${metric.percentage ?? 0}%` }"></div>
                            </div>
                            <p class="mt-1.5 text-xs text-[var(--ui-muted)]">{{ $t('Remaining') }}: {{ remainingLabel(metric) }}</p>
                        </div>
                        <div v-else class="mt-4 text-xs font-semibold text-[var(--ui-secondary)]">
                            {{ $t('Unlimited') }}
                        </div>
                    </article>
                </div>
            </UiSectionCard>

            <UiEmptyState v-if="!usageMetrics.length" :title="$t('No usage metrics available yet')" class="mt-6">
                <template #icon>
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 19V9m6 10V5m6 14v-7"/></svg>
                </template>
            </UiEmptyState>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from './../Layout/App.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
import UiSectionCard from '@/Components/UI/UiSectionCard.vue';
import UiStatCard from '@/Components/UI/UiStatCard.vue';
import UiEmptyState from '@/Components/UI/UiEmptyState.vue';

const props = defineProps([
    'subscription',
    'billingOwner',
    'subscriptionManagedByParent',
    'usageSummary',
    'scheduledPlanChange',
]);

const { t, locale } = useI18n();

const iconMap = {
    branches: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M3 21h18M5 21V7l7-4l7 4v14M9 21v-6h6v6M9 11h.01M15 11h.01M9 7h.01M15 7h.01" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    users: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8a4 4 0 0 0 0 8Zm12 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    contacts: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="1.6"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    campaigns: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="m3 11 18-5v12L3 14v-3z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    messages: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    canned_replies: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 2v4a2 2 0 0 0 2 2h4M9 13h6M9 17h6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    ai_text: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    ai_audio: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    ai_system_key: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    active_flows: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"><circle cx="5" cy="6" r="2.2" stroke="currentColor" stroke-width="1.6"/><circle cx="19" cy="6" r="2.2" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="18" r="2.2" stroke="currentColor" stroke-width="1.6"/><path d="M7 7.2 10.5 16M17 7.2 13.5 16" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
    flow_runs: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="m16 18 6-6-6-6M8 6l-6 6 6 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    default: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6"/><path d="M12 8v4l3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
};

const usageMetrics = computed(() => (props.usageSummary?.metrics ?? []).map((metric) => ({
    ...metric,
    icon: iconMap[metric.key] ?? iconMap.default,
})));
const usageNotices = computed(() => props.usageSummary?.notices ?? []);

const METRIC_GROUP_DEFS = [
    { key: 'plan_limits', title: 'Plan limits', keys: ['branches', 'users', 'contacts', 'campaigns', 'messages', 'canned_replies'] },
    { key: 'ai', title: 'AI usage overview', keys: ['ai_text', 'ai_audio', 'ai_system_key'] },
    { key: 'automation', title: 'Automation usage', keys: ['active_flows', 'flow_runs'] },
];

const metricGroups = computed(() => METRIC_GROUP_DEFS
    .map((group) => ({
        key: group.key,
        title: t(group.title),
        metrics: usageMetrics.value.filter((metric) => group.keys.includes(metric.key)),
    }))
    .filter((group) => group.metrics.length > 0));

const billingOwner = computed(() => props.billingOwner ?? null);
const usageSummary = computed(() => props.usageSummary ?? null);
const subscriptionManagedByParent = computed(() => Boolean(props.subscriptionManagedByParent));
const scheduledPlanChange = computed(() => props.scheduledPlanChange ?? null);
const activePlanName = computed(() => localizedPlanName(props.subscription?.plan) ?? '');

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

const cancelScheduledChange = () => {
    router.post('/subscription/scheduled-change/cancel', {}, {
        preserveScroll: true,
        preserveState: true,
    });
};

const noticeIconSvg = (notice) => {
    if (notice.type === 'danger') {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2L1 21h22zm0 4.84L19.53 19H4.47zM11 10h2v5h-2zm0 6h2v2h-2z"/></svg>';
    }

    if (notice.type === 'warning') {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M1 21h22L12 2zm12-3h-2v-2h2zm0-4h-2v-4h2z"/></svg>';
    }

    return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M11 9h2V7h-2zm0 8h2v-6h-2zm1-15C6.48 2 2 6.48 2 12s4.48 10 10 10s10-4.48 10-10S17.52 2 12 2"/></svg>';
};
</script>

<style scoped>
.usage-btn {
    display: inline-flex;
    align-items: center;
    border-radius: 0.7rem;
    padding: 0.6rem 1.2rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: background-color 160ms ease, filter 160ms ease;
}

.usage-btn--solid {
    color: #fff;
    background: var(--ui-secondary);
}

.usage-btn--solid:hover {
    filter: brightness(1.05);
}

.usage-btn--ghost {
    color: var(--ui-text);
    background: var(--ui-surface-soft);
    border: 1px solid var(--ui-border);
}

.usage-btn--ghost:hover {
    background: var(--ui-border);
}

.usage-banner {
    margin-bottom: 1rem;
    padding: 1rem 1.15rem;
    border-radius: var(--ui-radius-md);
    font-size: 0.9rem;
    line-height: 1.5;
}

.usage-banner--info {
    background: color-mix(in srgb, var(--ui-secondary) 8%, var(--ui-surface));
    border: 1px solid color-mix(in srgb, var(--ui-secondary) 20%, var(--ui-border));
    color: var(--ui-text);
}

.usage-banner--warning {
    background: color-mix(in srgb, var(--ui-warning) 12%, var(--ui-surface));
    border: 1px solid color-mix(in srgb, var(--ui-warning) 30%, var(--ui-border));
    color: color-mix(in srgb, var(--ui-warning) 75%, var(--ui-text));
}

.usage-banner--danger {
    background: color-mix(in srgb, var(--ui-danger) 10%, var(--ui-surface));
    border: 1px solid color-mix(in srgb, var(--ui-danger) 28%, var(--ui-border));
    color: color-mix(in srgb, var(--ui-danger) 75%, var(--ui-text));
}

.usage-notice-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 2rem;
    height: 2rem;
    border-radius: 999px;
    background: var(--ui-surface);
    border: 1px solid var(--ui-border);
}

.usage-notice-icon--danger { color: var(--ui-danger); }
.usage-notice-icon--warning { color: var(--ui-warning); }
.usage-notice-icon--info { color: var(--ui-secondary); }

.usage-metric-card {
    border-radius: var(--ui-radius-md);
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    padding: 1.1rem;
    transition: border-color 200ms ease;
}

.usage-metric-card--warning {
    border-color: color-mix(in srgb, var(--ui-warning) 40%, var(--ui-border));
}

.usage-metric-card--exceeded {
    border-color: color-mix(in srgb, var(--ui-danger) 45%, var(--ui-border));
}

.usage-metric-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 2.3rem;
    height: 2.3rem;
    border-radius: 0.7rem;
    background: var(--ui-surface);
    border: 1px solid var(--ui-border);
    color: var(--ui-secondary);
}

.usage-metric-icon--warning { color: var(--ui-warning); }
.usage-metric-icon--exceeded { color: var(--ui-danger); }
.usage-metric-icon--unlimited { color: var(--ui-muted); }

.usage-metric-badge {
    display: inline-flex;
    align-items: center;
    white-space: nowrap;
    border-radius: 999px;
    padding: 0.3rem 0.7rem;
    font-size: 0.76rem;
    font-weight: 700;
    color: var(--ui-secondary);
    background: color-mix(in srgb, var(--ui-secondary) 12%, var(--ui-surface));
}

.usage-metric-badge--warning {
    color: var(--ui-warning);
    background: color-mix(in srgb, var(--ui-warning) 15%, var(--ui-surface));
}

.usage-metric-badge--exceeded {
    color: var(--ui-danger);
    background: color-mix(in srgb, var(--ui-danger) 14%, var(--ui-surface));
}

.usage-metric-badge--unlimited {
    color: var(--ui-muted);
    background: var(--ui-surface);
}

.usage-metric-percent--warning { color: var(--ui-warning); }
.usage-metric-percent--exceeded { color: var(--ui-danger); }
.usage-metric-percent--healthy { color: var(--ui-secondary); }

.usage-meter-track {
    height: 0.5rem;
    border-radius: 999px;
    background: var(--ui-border);
    overflow: hidden;
}

.usage-meter-fill {
    height: 100%;
    border-radius: 999px;
    background: var(--ui-secondary);
    transition: width 500ms ease;
}

.usage-meter-fill--warning { background: var(--ui-warning); }
.usage-meter-fill--exceeded { background: var(--ui-danger); }
</style>
