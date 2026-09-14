<template>
    <AppLayout>
        <div class="admin-dashboard ui-page ui-fade-up text-[var(--ui-text)]">
            <div class="admin-dashboard__inner">
                <section class="admin-hero">
                    <div class="admin-hero__copy">
                        <p class="admin-hero__eyebrow">{{ $t('Admin command center') }}</p>
                        <h1 class="admin-hero__title">{{ $t('Welcome back') }}, {{ firstName }}</h1>
                        <p class="admin-hero__datetime">
                            <CalendarDays class="h-4 w-4" />
                            <span>{{ currentDateLabel }}</span>
                            <span class="admin-hero__dot"></span>
                            <span>{{ currentTimeLabel }}</span>
                        </p>
                    </div>

                    <div class="admin-hero__actions">
                        <Link href="/admin/organizations/create" class="admin-action admin-action--primary">
                            <Building2 class="h-4 w-4" />
                            <span>{{ $t('Add organization') }}</span>
                        </Link>
                        <Link href="/admin/users/create" class="admin-action admin-action--soft">
                            <UserPlus class="h-4 w-4" />
                            <span>{{ $t('Add user') }}</span>
                        </Link>
                    </div>
                </section>

                <section class="admin-kpi-grid" :aria-label="$t('Operational overview')">
                    <div v-for="card in statCards" :key="card.key" class="admin-kpi-card">
                        <UiStatCard
                            :title="card.title"
                            :value="card.value"
                            :cta-label="card.href ? $t('View all') : null"
                            :cta-href="card.href"
                        >
                            <template #icon>
                                <component :is="card.icon" class="h-6 w-6" />
                            </template>
                        </UiStatCard>
                    </div>
                </section>

                <section>
                    <p class="admin-section-title"><span class="admin-section-dot"></span>{{ $t('Needs attention') }}</p>
                    <div class="admin-kpi-grid">
                        <div
                            v-for="card in attentionCards"
                            :key="card.key"
                            class="admin-kpi-card admin-kpi-card--attention"
                            :style="{ '--tone': card.tone }"
                        >
                            <span v-if="card.urgent" class="admin-attention-dot"></span>
                            <UiStatCard
                                :title="card.title"
                                :value="card.value"
                                :cta-label="card.href ? $t('View all') : null"
                                :cta-href="card.href"
                            >
                                <template #icon>
                                    <component :is="card.icon" class="h-6 w-6" />
                                </template>
                            </UiStatCard>
                        </div>
                    </div>
                </section>

                <section class="admin-charts-grid">
                    <div class="admin-panel admin-panel--chart">
                        <UiSectionCard
                            :title="$t('Revenue and growth')"
                            :subtitle="$t('New users and payment activity over the last 7 days.')"
                        >
                            <template #icon>
                                <span class="admin-panel__icon">
                                    <TrendingUp class="h-5 w-5" />
                                </span>
                            </template>

                            <div class="admin-chart-frame">
                                <div v-if="!chartsReady" class="admin-chart-skeleton" style="height: 360px" aria-hidden="true"></div>
                                <apexchart v-else type="area" height="360" :options="chartOptions" :series="series"></apexchart>
                            </div>
                        </UiSectionCard>
                    </div>

                    <div class="admin-panel admin-panel--breakdown">
                        <UiSectionCard
                            :title="$t('Needs attention breakdown')"
                            :subtitle="$t('Open items by category.')"
                        >
                            <template #icon>
                                <span class="admin-panel__icon admin-panel__icon--accent">
                                    <ListChecks class="h-5 w-5" />
                                </span>
                            </template>

                            <div class="admin-chart-frame">
                                <div v-if="!chartsReady" class="admin-chart-skeleton" style="height: 280px" aria-hidden="true"></div>
                                <apexchart v-else-if="hasAttentionData" type="bar" height="280" :options="attentionChartOptions" :series="attentionSeries"></apexchart>
                                <UiEmptyState v-else :title="$t('Nothing needs attention right now')">
                                    <template #icon>
                                        <ListChecks class="h-6 w-6" />
                                    </template>
                                </UiEmptyState>
                            </div>
                        </UiSectionCard>
                    </div>
                </section>

                <section class="admin-signal-card">
                    <h3>{{ $t('System signals') }}</h3>
                    <div class="admin-signal-grid">
                        <div v-for="signal in systemSignalRows" :key="signal.key" class="admin-signal-tile" :style="{ '--tone': signal.tone }">
                            <span class="admin-signal-icon">
                                <component :is="signal.icon" class="h-4 w-4" />
                            </span>
                            <div class="min-w-0">
                                <p class="admin-signal-value">{{ signal.value }}</p>
                                <p class="admin-signal-label">{{ signal.label }}</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="admin-panel admin-panel--activity">
                    <UiSectionCard :title="$t('Latest billing movement')">
                        <template #icon>
                            <span class="admin-panel__icon admin-panel__icon--accent">
                                <ReceiptText class="h-5 w-5" />
                            </span>
                        </template>

                        <template v-if="hasPayments">
                            <div class="admin-billing-table-wrap">
                                <table class="admin-billing-table">
                                    <thead>
                                        <tr>
                                            <th>{{ $t('Organization') }}</th>
                                            <th>{{ $t('Description') }}</th>
                                            <th>{{ $t('Type') }}</th>
                                            <th class="text-end">{{ $t('Amount') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(item, index) in recentPayments" :key="index">
                                            <td class="admin-billing-org">{{ item.organization?.name ?? $t('Organization') }}</td>
                                            <td class="admin-billing-desc">{{ item.description ?? $t('Billing activity') }}</td>
                                            <td>
                                                <span class="admin-billing-badge" :class="entityTypeBadgeClass(item.entity_type)">
                                                    {{ entityTypeLabel(item.entity_type) }}
                                                </span>
                                            </td>
                                            <td class="admin-billing-amount text-end">{{ item.amount }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <Link href="/admin/payment-logs" class="admin-panel-link">
                                <span>{{ $t('Open billing logs') }}</span>
                                <ArrowUpRight class="h-4 w-4" />
                            </Link>
                        </template>

                        <UiEmptyState
                            v-else
                            :title="$t('No billing activity yet')"
                            :description="$t('Keep an eye on invoices and billing activity from here.')"
                        >
                            <template #icon>
                                <ReceiptText class="h-6 w-6" />
                            </template>
                        </UiEmptyState>
                    </UiSectionCard>
                </section>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
    import AppLayout from "./Layout/App.vue";
    import { computed, defineProps, onMounted, onUnmounted, ref } from "vue";
    import { Link, usePage } from "@inertiajs/vue3";
    import { useI18n } from "vue-i18n";
    import {
        AlertTriangle,
        ArrowUpRight,
        BadgeCheck,
        Building2,
        CalendarDays,
        Clock,
        ListChecks,
        MessageSquareText,
        ReceiptText,
        Rocket,
        Sparkles,
        TicketCheck,
        TicketX,
        TrendingUp,
        UserPlus,
        UsersRound,
    } from "lucide-vue-next";
    import UiStatCard from "@/Components/UI/UiStatCard.vue";
    import UiSectionCard from "@/Components/UI/UiSectionCard.vue";
    import UiEmptyState from "@/Components/UI/UiEmptyState.vue";

    const user = computed(() => usePage().props.auth.user);
    const { t } = useI18n();

    const props = defineProps({
        title: { type: String },
        payments: { type: Object },
        totalRevenue: { type: String },
        userCount: { type: Number },
        openTickets: { type: Number },
        totalMessages: { type: Number },
        period: { type: Object },
        newUsers: { type: Object },
        revenue: { type: Object },
        needsAttention: { type: Object, default: () => ({}) },
        systemSignals: { type: Object, default: () => ({}) },
    });

    const cssVar = (name, fallback) => {
        if (typeof window === 'undefined') {
            return fallback;
        }

        return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
    };

    const primaryColor = cssVar('--ui-primary', '#0b5fff');
    const secondaryColor = cssVar('--ui-secondary', '#0ea5a4');
    const mutedColor = cssVar('--ui-muted', '#64748b');
    const warningColor = cssVar('--ui-warning', '#d97706');
    const dangerColor = cssVar('--ui-danger', '#dc2626');

    const firstName = computed(() => user.value?.first_name ?? t('Admin'));
    const recentPayments = computed(() => props.payments?.data ?? []);
    const hasPayments = computed(() => recentPayments.value.length > 0);

    const numberFormatter = computed(() => new Intl.NumberFormat(locale.value));
    const formatNumber = (value) => numberFormatter.value.format(Number(value) || 0);

    const locale = computed(() => (usePage().props.currentLanguage === 'ar' ? 'ar-SA' : 'en-US'));

    const now = ref(new Date());
    let clockTimer = null;
    onMounted(() => {
        clockTimer = setInterval(() => {
            now.value = new Date();
        }, 60000);
    });
    onUnmounted(() => {
        if (clockTimer) {
            clearInterval(clockTimer);
        }
    });

    const currentDateLabel = computed(() => {
        return new Intl.DateTimeFormat(locale.value, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }).format(now.value);
    });
    const currentTimeLabel = computed(() => {
        return new Intl.DateTimeFormat(locale.value, { hour: '2-digit', minute: '2-digit' }).format(now.value);
    });

    const chartsReady = ref(false);
    onMounted(() => {
        requestAnimationFrame(() => {
            chartsReady.value = true;
        });
    });

    const statCards = computed(() => [
        {
            key: 'revenue',
            title: t('Total revenue'),
            value: props.totalRevenue,
            href: '/admin/payment-logs',
            icon: TrendingUp,
        },
        {
            key: 'users',
            title: t('Active users'),
            value: formatNumber(props.userCount),
            href: '/admin/users',
            icon: UsersRound,
        },
        {
            key: 'tickets',
            title: t('Open tickets'),
            value: formatNumber(props.openTickets),
            href: '/admin/support',
            icon: TicketCheck,
        },
        {
            key: 'messages',
            title: t('Total messages'),
            value: formatNumber(props.totalMessages),
            href: null,
            icon: MessageSquareText,
        },
    ]);

    const attentionCards = computed(() => [
        {
            key: 'demo-requests',
            title: t('Demo requests'),
            value: props.needsAttention?.demoRequests?.count ?? 0,
            href: props.needsAttention?.demoRequests?.href ?? null,
            icon: Rocket,
            tone: 'var(--ui-muted)',
            urgent: false,
        },
        {
            key: 'meta-verification',
            title: t('Meta verification pending'),
            value: props.needsAttention?.metaVerificationsPending?.count ?? 0,
            href: props.needsAttention?.metaVerificationsPending?.href ?? null,
            icon: BadgeCheck,
            tone: 'var(--ui-secondary)',
            urgent: false,
        },
        {
            key: 'expiring-subscriptions',
            title: t('Subscriptions expiring soon'),
            value: props.needsAttention?.subscriptionsExpiring?.count ?? 0,
            href: props.needsAttention?.subscriptionsExpiring?.href ?? null,
            icon: Clock,
            tone: 'var(--ui-warning)',
            urgent: true,
        },
        {
            key: 'unassigned-tickets',
            title: t('Unassigned tickets'),
            value: props.needsAttention?.unassignedTickets?.count ?? 0,
            href: props.needsAttention?.unassignedTickets?.href ?? null,
            icon: TicketX,
            tone: 'var(--ui-danger)',
            urgent: true,
        },
    ]);

    const systemSignalRows = computed(() => [
        {
            key: 'failed-jobs',
            label: t('Failed jobs'),
            value: props.systemSignals?.failedJobs?.count ?? 0,
            icon: AlertTriangle,
            tone: 'var(--ui-danger)',
        },
        {
            key: 'new-orgs',
            label: t('New organizations this week'),
            value: props.systemSignals?.newOrgsThisWeek?.count ?? 0,
            icon: Building2,
            tone: 'var(--ui-secondary)',
        },
        {
            key: 'trial-accounts',
            label: t('Trial accounts'),
            value: props.systemSignals?.trialAccounts?.count ?? 0,
            icon: Sparkles,
            tone: 'var(--ui-muted)',
        },
        {
            key: 'aging-tickets',
            label: t('Aging open tickets'),
            value: props.systemSignals?.agingOpenTickets?.count ?? 0,
            icon: TicketCheck,
            tone: 'var(--ui-warning)',
        },
    ]);

    const chartOptions = computed(() => ({
        chart: {
            height: 360,
            type: 'area',
            toolbar: { show: false },
            zoom: { enabled: false },
            fontFamily: 'inherit',
            foreColor: mutedColor,
        },
        colors: [primaryColor, secondaryColor],
        dataLabels: { enabled: false },
        stroke: {
            width: 3,
            curve: 'smooth',
            lineCap: 'round',
        },
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 0.75,
                opacityFrom: 0.34,
                opacityTo: 0.04,
                stops: [0, 72, 100],
            },
        },
        grid: {
            borderColor: 'rgba(148, 163, 184, 0.24)',
            strokeDashArray: 4,
            padding: {
                left: 8,
                right: 8,
                top: 4,
                bottom: 0,
            },
        },
        markers: {
            size: 0,
            strokeWidth: 0,
            hover: {
                size: 5,
            },
        },
        legend: {
            position: 'bottom',
            horizontalAlign: 'center',
            fontSize: '12px',
            markers: {
                radius: 8,
            },
        },
        xaxis: {
            type: 'datetime',
            categories: props.period,
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: {
                style: {
                    colors: mutedColor,
                    fontSize: '12px',
                },
            },
        },
        yaxis: {
            labels: {
                style: {
                    colors: mutedColor,
                    fontSize: '12px',
                },
            },
        },
        tooltip: {
            theme: 'light',
            x: {
                format: 'dd/MM/yy HH:mm',
            },
        },
    }));

    const series = computed(() => [
        {
            name: t('New customers'),
            data: props.newUsers,
        },
        {
            name: t('Revenue'),
            data: props.revenue,
        },
    ]);

    const hasAttentionData = computed(() => attentionCards.value.some((card) => Number(card.value) > 0));

    const attentionChartOptions = computed(() => ({
        chart: {
            type: 'bar',
            height: 280,
            toolbar: { show: false },
            fontFamily: 'inherit',
            foreColor: mutedColor,
        },
        plotOptions: {
            bar: {
                horizontal: true,
                distributed: true,
                borderRadius: 6,
                barHeight: '55%',
            },
        },
        colors: [mutedColor, secondaryColor, warningColor, dangerColor],
        dataLabels: {
            enabled: true,
            style: { fontSize: '12px', fontWeight: 700, colors: [mutedColor] },
            background: { enabled: false },
            offsetX: 0,
        },
        grid: {
            borderColor: 'rgba(148, 163, 184, 0.24)',
            strokeDashArray: 4,
            xaxis: { lines: { show: true } },
            yaxis: { lines: { show: false } },
        },
        xaxis: {
            categories: attentionCards.value.map((card) => card.title),
            labels: { show: false },
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: {
            labels: {
                style: { fontSize: '12px', colors: mutedColor },
            },
        },
        legend: { show: false },
        tooltip: {
            theme: 'light',
            y: { formatter: (val) => val },
        },
    }));

    const attentionSeries = computed(() => [
        {
            name: t('Count'),
            data: attentionCards.value.map((card) => Number(card.value) || 0),
        },
    ]);

    const ENTITY_TYPE_LABELS = {
        payment: 'Payment',
        invoice: 'Invoice',
        credit: 'Credit',
        debit: 'Debit',
    };
    const ENTITY_TYPE_TONES = {
        payment: 'ui-chip-success',
        invoice: 'ui-chip-neutral',
        credit: 'ui-chip-info',
        debit: 'ui-chip-warning',
    };
    const entityTypeLabel = (type) => t(ENTITY_TYPE_LABELS[type] ?? 'Billing activity');
    const entityTypeBadgeClass = (type) => ENTITY_TYPE_TONES[type] ?? 'ui-chip-neutral';
</script>

<style scoped>
.admin-dashboard {
    min-height: 100%;
    padding: 1rem;
}

.admin-dashboard__inner {
    width: 100%;
    max-width: 1680px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.admin-hero {
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: stretch;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.25rem;
    border: 1px solid color-mix(in srgb, var(--ui-primary) 14%, var(--ui-border));
    border-radius: var(--ui-radius-lg);
    background:
        linear-gradient(135deg, color-mix(in srgb, var(--ui-surface) 92%, var(--ui-primary) 8%), color-mix(in srgb, var(--ui-surface) 90%, var(--ui-secondary) 10%));
    box-shadow: 0 24px 58px -42px rgba(15, 23, 42, 0.58);
}

.admin-hero::before {
    content: "";
    position: absolute;
    inset: 0;
    pointer-events: none;
    background:
        linear-gradient(90deg, color-mix(in srgb, var(--ui-primary) 8%, transparent), transparent 34%),
        linear-gradient(270deg, color-mix(in srgb, var(--ui-secondary) 7%, transparent), transparent 40%);
    opacity: 0.78;
}

.admin-hero__copy,
.admin-hero__actions {
    position: relative;
    z-index: 1;
}

.admin-hero__copy {
    min-width: 0;
    max-width: 46rem;
}

.admin-hero__eyebrow,
.admin-panel__eyebrow {
    margin: 0;
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: color-mix(in srgb, var(--ui-primary) 72%, var(--ui-text));
}

.admin-hero__title {
    margin: 0.35rem 0 0;
    color: var(--ui-text);
    font-size: 2rem;
    line-height: 1.2;
    font-weight: 800;
}

.admin-hero__datetime {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin: 0.6rem 0 0;
    color: var(--ui-muted);
    font-size: 0.9rem;
    font-weight: 600;
}

.admin-hero__dot {
    width: 4px;
    height: 4px;
    border-radius: 999px;
    background: var(--ui-muted);
    opacity: 0.6;
}

.admin-hero__actions {
    display: flex;
    flex-wrap: wrap;
    align-content: flex-start;
    justify-content: flex-end;
    gap: 0.6rem;
}

.admin-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    min-height: 2.65rem;
    border-radius: 999px;
    border: 1px solid transparent;
    padding: 0.7rem 1rem;
    font-size: 0.9rem;
    font-weight: 800;
    transition: transform 160ms ease, box-shadow 160ms ease, border-color 160ms ease;
}

.admin-action:hover {
    transform: translateY(-2px);
}

.admin-action--primary {
    color: #fff;
    background: linear-gradient(135deg, var(--ui-primary), color-mix(in srgb, var(--ui-primary) 76%, var(--ui-secondary)));
    box-shadow: 0 18px 30px -24px color-mix(in srgb, var(--ui-primary) 74%, rgba(15, 23, 42, 0.55));
}

.admin-action--soft {
    color: color-mix(in srgb, var(--ui-primary) 74%, var(--ui-text));
    border-color: color-mix(in srgb, var(--ui-primary) 22%, var(--ui-border));
    background: var(--ui-surface-soft);
    box-shadow: 0 16px 28px -24px rgba(15, 23, 42, 0.42);
}

.admin-kpi-grid {
    display: grid;
    grid-template-columns: repeat(1, minmax(0, 1fr));
    gap: 1rem;
}

.admin-kpi-card :deep(.ui-kpi-icon) {
    background: color-mix(in srgb, var(--ui-primary) 14%, transparent);
    border-color: color-mix(in srgb, var(--ui-primary) 24%, transparent);
}
.admin-kpi-card :deep(.ui-kpi-icon svg) {
    color: var(--ui-primary) !important;
}

.admin-section-title {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    margin: 0 0 0.9rem;
    font-size: 0.85rem;
    font-weight: 800;
    letter-spacing: 0.01em;
}

.admin-section-dot {
    width: 7px;
    height: 7px;
    border-radius: 999px;
    background: var(--ui-danger);
}

.admin-kpi-card--attention {
    position: relative;
    border-color: color-mix(in srgb, var(--tone) 20%, var(--ui-border));
    border-inline-start: 4px solid var(--tone);
    box-shadow: 0 24px 50px -30px color-mix(in srgb, var(--tone) 40%, rgba(15, 23, 42, 0.55));
    background: linear-gradient(175deg, var(--ui-surface), color-mix(in srgb, var(--ui-surface) 93%, var(--tone) 7%));
    transition: transform 180ms ease, box-shadow 180ms ease;
}

.admin-kpi-card--attention:hover {
    transform: translateY(-3px);
    box-shadow: 0 30px 58px -30px color-mix(in srgb, var(--tone) 50%, rgba(15, 23, 42, 0.6));
}

.admin-kpi-card--attention :deep(.ui-kpi-icon) {
    background: color-mix(in srgb, var(--tone) 16%, transparent);
    border-color: color-mix(in srgb, var(--tone) 26%, transparent);
}

.admin-kpi-card--attention :deep(.ui-kpi-icon svg) {
    color: var(--tone) !important;
}

.admin-kpi-card--attention :deep(.ui-kpi-value) {
    font-size: 1.85rem;
}

.admin-attention-dot {
    position: absolute;
    top: 14px;
    inset-inline-end: 14px;
    width: 8px;
    height: 8px;
    border-radius: 999px;
    background: var(--tone);
    animation: adminAttnPulse 2s ease-in-out infinite;
}

@keyframes adminAttnPulse {
    0%, 100% { box-shadow: 0 0 0 0 color-mix(in srgb, var(--tone) 45%, transparent); }
    50% { box-shadow: 0 0 0 6px color-mix(in srgb, var(--tone) 0%, transparent); }
}

@media (prefers-reduced-motion: reduce) {
    .admin-attention-dot { animation: none; }
}

.admin-charts-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.65fr) minmax(18rem, 1fr);
    gap: 1rem;
}

.admin-charts-grid :deep(.ui-card) {
    height: 100%;
    display: flex;
    flex-direction: column;
}

.admin-panel--chart {
    --panel-tone: var(--ui-primary);
}

.admin-panel--breakdown,
.admin-panel--activity {
    --panel-tone: var(--ui-accent);
}

.admin-panel__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 2.7rem;
    height: 2.7rem;
    border-radius: 0.9rem;
    border: 1px solid color-mix(in srgb, var(--panel-tone, var(--ui-primary)) 20%, transparent);
    background: color-mix(in srgb, var(--ui-surface) 86%, var(--panel-tone, var(--ui-primary)) 14%);
    color: color-mix(in srgb, var(--panel-tone, var(--ui-primary)) 78%, var(--ui-text));
}

.admin-panel__icon--accent {
    --panel-tone: var(--ui-accent);
}

.admin-chart-frame {
    padding: 0.25rem 0.75rem 0.75rem;
}

.admin-chart-skeleton {
    border-radius: var(--ui-radius-md);
    background: linear-gradient(90deg, var(--ui-surface-soft) 25%, color-mix(in srgb, var(--ui-surface-soft) 60%, var(--ui-border)) 37%, var(--ui-surface-soft) 63%);
    background-size: 400% 100%;
    animation: adminChartShimmer 1.4s ease infinite;
}

@keyframes adminChartShimmer {
    0% { background-position: 100% 50%; }
    100% { background-position: 0 50%; }
}

@media (prefers-reduced-motion: reduce) {
    .admin-chart-skeleton { animation: none; }
}

.admin-signal-card {
    background: var(--ui-surface-soft);
    border: 1px solid var(--ui-border);
    border-radius: var(--ui-radius-lg);
    padding: 1rem 1.25rem 1.25rem;
}

.admin-signal-card h3 {
    font-size: 0.9rem;
    font-weight: 800;
    margin: 0 0 0.85rem;
    color: var(--ui-muted);
}

.admin-signal-grid {
    display: grid;
    grid-template-columns: repeat(1, minmax(0, 1fr));
    gap: 0.75rem;
}

.admin-signal-tile {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    border: 1px solid var(--ui-border);
    border-radius: var(--ui-radius-md);
    background: var(--ui-surface);
    padding: 0.85rem 1rem;
}

.admin-signal-icon {
    width: 2.2rem;
    height: 2.2rem;
    border-radius: 0.6rem;
    background: color-mix(in srgb, var(--tone) 13%, transparent);
    color: var(--tone);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.admin-signal-value {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--ui-text);
    line-height: 1.2;
}

.admin-signal-label {
    margin-top: 0.1rem;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--ui-muted);
}

.admin-panel-link {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    width: fit-content;
    margin: 0.85rem 1.15rem 1.15rem;
    color: color-mix(in srgb, var(--ui-accent) 72%, var(--ui-text));
    font-size: 0.84rem;
    font-weight: 800;
    text-decoration: none;
}

.admin-panel-link:hover {
    color: var(--ui-primary);
}

.admin-billing-table-wrap {
    overflow-x: auto;
    padding: 0.5rem 1.15rem 0;
}

.admin-billing-table {
    width: 100%;
    min-width: 32rem;
    border-collapse: collapse;
    font-size: 0.85rem;
}

.admin-billing-table thead th {
    padding: 0.5rem 0.6rem;
    text-align: start;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--ui-muted);
    border-bottom: 1px solid var(--ui-border);
}

.admin-billing-table tbody td {
    padding: 0.7rem 0.6rem;
    border-bottom: 1px solid var(--ui-border);
    color: var(--ui-text);
    vertical-align: middle;
}

.admin-billing-table tbody tr:last-child td {
    border-bottom: none;
}

.admin-billing-org {
    font-weight: 700;
    white-space: nowrap;
}

.admin-billing-desc {
    color: var(--ui-muted);
    max-width: 16rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.admin-billing-amount {
    font-weight: 800;
    white-space: nowrap;
}

.admin-billing-badge {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.2rem 0.65rem;
    font-size: 0.72rem;
    font-weight: 700;
}

@media (min-width: 768px) {
    .admin-kpi-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .admin-signal-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (min-width: 1180px) {
    .admin-kpi-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .admin-signal-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}

@media (max-width: 1180px) {
    .admin-charts-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .admin-dashboard {
        padding: 0.9rem;
    }

    .admin-hero {
        flex-direction: column;
        padding: 1rem;
    }

    .admin-hero__title {
        font-size: 1.6rem;
    }

    .admin-hero__actions {
        justify-content: stretch;
    }

    .admin-action {
        flex: 1 1 100%;
    }

    .admin-chart-frame {
        padding-inline: 0.35rem;
    }
}
</style>
