<template>
    <AppLayout>
        <div class="admin-dashboard ui-page ui-fade-up text-[var(--ui-text)]">
            <div class="admin-dashboard__inner">
                <section class="admin-hero">
                    <div class="admin-hero__copy">
                        <p class="admin-hero__eyebrow">{{ $t('Admin command center') }}</p>
                        <h1 class="admin-hero__title">{{ $t('Dashboard') }}</h1>
                        <p class="admin-hero__subtitle">{{ welcomeMessage }}</p>

                        <div class="admin-hero__chips">
                            <span class="admin-hero-chip admin-hero-chip--primary">
                                <Activity class="h-4 w-4" />
                                {{ $t('Live operations') }}
                            </span>
                            <span class="admin-hero-chip admin-hero-chip--secondary">
                                <CreditCard class="h-4 w-4" />
                                {{ $t('SAR billing') }}
                            </span>
                        </div>
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

                <section class="admin-stat-grid" :aria-label="$t('Operational overview')">
                    <article
                        v-for="card in statCards"
                        :key="card.key"
                        class="admin-stat-card"
                        :style="card.style"
                    >
                        <div class="admin-stat-card__body">
                            <span class="admin-stat-card__icon">
                                <component :is="card.icon" class="h-6 w-6" />
                            </span>
                            <div class="admin-stat-card__copy">
                                <p class="admin-stat-card__title">{{ card.title }}</p>
                                <p class="admin-stat-card__value">{{ card.value }}</p>
                            </div>
                        </div>

                        <Link v-if="card.href" :href="card.href" class="admin-stat-card__link">
                            <span>{{ $t('View all') }}</span>
                            <ArrowUpRight class="h-4 w-4" />
                        </Link>
                    </article>
                </section>

                <section class="admin-dashboard-grid">
                    <article class="admin-panel admin-panel--chart">
                        <div class="admin-panel__header">
                            <div>
                                <p class="admin-panel__eyebrow">{{ $t('Revenue pulse') }}</p>
                                <h2 class="admin-panel__title">{{ $t('Revenue and growth') }}</h2>
                                <p class="admin-panel__subtitle">{{ $t('New users and revenue across the selected period.') }}</p>
                            </div>
                            <span class="admin-panel__icon">
                                <TrendingUp class="h-5 w-5" />
                            </span>
                        </div>

                        <div class="admin-chart-frame">
                            <apexchart type="area" height="360" :options="chartOptions" :series="series"></apexchart>
                        </div>
                    </article>

                    <article class="admin-panel admin-panel--activity">
                        <div class="admin-panel__header">
                            <div>
                                <p class="admin-panel__eyebrow">{{ $t('Recent activity') }}</p>
                                <h2 class="admin-panel__title">{{ $t('Latest billing movement') }}</h2>
                                <p class="admin-panel__subtitle">{{ $t('Recent Moyasar payments by organization.') }}</p>
                            </div>
                            <span class="admin-panel__icon admin-panel__icon--accent">
                                <ReceiptText class="h-5 w-5" />
                            </span>
                        </div>

                        <div v-if="hasPayments" class="admin-transaction-list">
                            <div
                                v-for="(item, index) in recentPayments"
                                :key="index"
                                class="admin-transaction-row"
                            >
                                <div class="admin-transaction-row__main">
                                    <span class="admin-transaction-row__mark">
                                        <ReceiptText class="h-4 w-4" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="admin-transaction-row__title">{{ item.organization?.name ?? $t('Organization') }}</p>
                                        <p class="admin-transaction-row__meta">{{ $t('Moyasar transaction') }}</p>
                                    </div>
                                </div>
                                <span class="admin-transaction-row__amount">{{ item.amount }}</span>
                            </div>

                            <Link href="/admin/payment-logs" class="admin-panel-link">
                                <span>{{ $t('Open billing logs') }}</span>
                                <ArrowUpRight class="h-4 w-4" />
                            </Link>
                        </div>

                        <div v-else class="admin-empty-state">
                            <span class="admin-empty-state__icon">
                                <ReceiptText class="h-6 w-6" />
                            </span>
                            <h3>{{ $t('No billing activity yet') }}</h3>
                            <p>{{ $t('Keep an eye on invoices and Moyasar activity from here.') }}</p>
                        </div>
                    </article>
                </section>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
    import AppLayout from "./Layout/App.vue";
    import { computed, defineProps } from "vue";
    import { Link, usePage } from "@inertiajs/vue3";
    import { useI18n } from "vue-i18n";
    import {
        Activity,
        ArrowUpRight,
        Building2,
        CreditCard,
        MessageSquareText,
        ReceiptText,
        TicketCheck,
        TrendingUp,
        UserPlus,
        UsersRound,
    } from "lucide-vue-next";

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
    });

    const cssVar = (name, fallback) => {
        if (typeof window === 'undefined') {
            return fallback;
        }

        return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
    };

    const primaryColor = cssVar('--ui-primary', '#0b5fff');
    const secondaryColor = cssVar('--ui-secondary', '#0ea5a4');
    const welcomeMessage = computed(() => `${t('Welcome back')}, ${user.value?.first_name ?? t('Admin')}. ${t('A cleaner operational view for revenue, users, tickets, and message traffic.')}`);
    const recentPayments = computed(() => props.payments?.data ?? []);
    const hasPayments = computed(() => recentPayments.value.length > 0);

    const statCards = computed(() => [
        {
            key: 'revenue',
            title: t('Total revenue'),
            value: props.totalRevenue,
            href: '/admin/payment-logs',
            icon: TrendingUp,
            style: {
                '--stat-tone': 'var(--ui-primary)',
                '--stat-accent': 'var(--ui-secondary)',
            },
        },
        {
            key: 'users',
            title: t('Active users'),
            value: props.userCount,
            href: '/admin/users',
            icon: UsersRound,
            style: {
                '--stat-tone': 'var(--ui-secondary)',
                '--stat-accent': 'var(--ui-primary)',
            },
        },
        {
            key: 'tickets',
            title: t('Open tickets'),
            value: props.openTickets,
            href: '/admin/support',
            icon: TicketCheck,
            style: {
                '--stat-tone': 'var(--ui-accent)',
                '--stat-accent': 'var(--ui-primary)',
            },
        },
        {
            key: 'messages',
            title: t('Total messages'),
            value: props.totalMessages,
            href: null,
            icon: MessageSquareText,
            style: {
                '--stat-tone': 'var(--ui-success)',
                '--stat-accent': 'var(--ui-secondary)',
            },
        },
    ]);

    const chartOptions = computed(() => ({
        chart: {
            height: 360,
            type: 'area',
            toolbar: { show: false },
            zoom: { enabled: false },
            fontFamily: 'inherit',
            foreColor: '#64748b',
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
                    colors: '#64748b',
                    fontSize: '12px',
                },
            },
        },
        yaxis: {
            labels: {
                style: {
                    colors: '#64748b',
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
        linear-gradient(135deg, color-mix(in srgb, #fff 92%, var(--ui-primary) 8%), color-mix(in srgb, #fff 90%, var(--ui-secondary) 10%));
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

.admin-hero__subtitle {
    margin: 0.6rem 0 0;
    max-width: 42rem;
    color: var(--ui-muted);
    font-size: 0.98rem;
    line-height: 1.7;
}

.admin-hero__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.55rem;
    margin-top: 1rem;
}

.admin-hero-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    min-height: 2.15rem;
    border-radius: 999px;
    border: 1px solid color-mix(in srgb, var(--chip-tone, var(--ui-primary)) 18%, transparent);
    padding: 0.45rem 0.75rem;
    background: color-mix(in srgb, #fff 88%, var(--chip-tone, var(--ui-primary)) 12%);
    color: color-mix(in srgb, var(--chip-tone, var(--ui-primary)) 72%, var(--ui-text));
    font-size: 0.82rem;
    font-weight: 700;
}

.admin-hero-chip--primary {
    --chip-tone: var(--ui-primary);
}

.admin-hero-chip--secondary {
    --chip-tone: var(--ui-secondary);
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
    background: rgba(255, 255, 255, 0.82);
    box-shadow: 0 16px 28px -24px rgba(15, 23, 42, 0.42);
}

.admin-stat-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
}

.admin-stat-card {
    position: relative;
    overflow: hidden;
    min-height: 9.25rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem;
    border: 1px solid color-mix(in srgb, var(--stat-tone, var(--ui-primary)) 18%, var(--ui-border));
    border-radius: var(--ui-radius-md);
    background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.98), color-mix(in srgb, #fff 92%, var(--stat-tone, var(--ui-primary)) 8%));
    box-shadow: 0 18px 44px -34px color-mix(in srgb, var(--stat-tone, var(--ui-primary)) 48%, rgba(15, 23, 42, 0.38));
    transition: transform 170ms ease, border-color 170ms ease, box-shadow 170ms ease;
}

.admin-stat-card:hover {
    transform: translateY(-3px);
    border-color: color-mix(in srgb, var(--stat-tone, var(--ui-primary)) 32%, var(--ui-border));
    box-shadow: 0 24px 54px -38px color-mix(in srgb, var(--stat-tone, var(--ui-primary)) 58%, rgba(15, 23, 42, 0.44));
}

.admin-stat-card::before {
    content: "";
    position: absolute;
    inset: 0;
    pointer-events: none;
    background:
        linear-gradient(120deg, color-mix(in srgb, var(--stat-tone, var(--ui-primary)) 9%, transparent), transparent 46%),
        linear-gradient(300deg, color-mix(in srgb, var(--stat-accent, var(--ui-secondary)) 8%, transparent), transparent 56%);
}

.admin-stat-card__body,
.admin-stat-card__link {
    position: relative;
    z-index: 1;
}

.admin-stat-card__body {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.9rem;
}

.admin-stat-card__copy {
    min-width: 0;
    flex: 1;
}

.admin-stat-card__title {
    color: var(--ui-muted);
    font-size: 0.9rem;
    line-height: 1.35;
}

.admin-stat-card__value {
    margin-top: 0.25rem;
    color: color-mix(in srgb, var(--ui-text) 90%, var(--stat-tone, var(--ui-primary)) 10%);
    font-size: 1.55rem;
    line-height: 1.2;
    font-weight: 850;
    overflow-wrap: anywhere;
}

.admin-stat-card__icon,
.admin-panel__icon,
.admin-transaction-row__mark,
.admin-empty-state__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.admin-stat-card__icon {
    width: 3.25rem;
    height: 3.25rem;
    border-radius: 1rem;
    border: 1px solid color-mix(in srgb, var(--stat-tone, var(--ui-primary)) 22%, transparent);
    background: color-mix(in srgb, #fff 84%, var(--stat-tone, var(--ui-primary)) 16%);
    color: color-mix(in srgb, var(--stat-tone, var(--ui-primary)) 82%, var(--ui-text));
}

.admin-stat-card__link,
.admin-panel-link {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    width: fit-content;
    color: color-mix(in srgb, var(--stat-tone, var(--ui-primary)) 72%, var(--ui-text));
    font-size: 0.84rem;
    font-weight: 800;
    text-decoration: none;
}

.admin-stat-card__link:hover,
.admin-panel-link:hover {
    color: var(--ui-primary);
}

.admin-dashboard-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.05fr) minmax(20rem, 0.95fr);
    gap: 1rem;
}

.admin-panel {
    position: relative;
    overflow: hidden;
    min-width: 0;
    border: 1px solid color-mix(in srgb, var(--panel-tone, var(--ui-primary)) 13%, var(--ui-border));
    border-radius: var(--ui-radius-lg);
    background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.98), color-mix(in srgb, #fff 96%, var(--panel-tone, var(--ui-primary)) 4%));
    box-shadow: 0 20px 48px -36px rgba(15, 23, 42, 0.48);
}

.admin-panel--chart {
    --panel-tone: var(--ui-primary);
}

.admin-panel--activity {
    --panel-tone: var(--ui-accent);
}

.admin-panel__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.15rem 1.15rem 0.5rem;
}

.admin-panel__title {
    margin-top: 0.25rem;
    color: var(--ui-text);
    font-size: 1.15rem;
    line-height: 1.35;
    font-weight: 800;
}

.admin-panel__subtitle {
    margin-top: 0.3rem;
    color: var(--ui-muted);
    font-size: 0.88rem;
    line-height: 1.55;
}

.admin-panel__icon {
    width: 2.7rem;
    height: 2.7rem;
    border-radius: 0.9rem;
    border: 1px solid color-mix(in srgb, var(--panel-tone, var(--ui-primary)) 20%, transparent);
    background: color-mix(in srgb, #fff 86%, var(--panel-tone, var(--ui-primary)) 14%);
    color: color-mix(in srgb, var(--panel-tone, var(--ui-primary)) 78%, var(--ui-text));
}

.admin-panel__icon--accent {
    --panel-tone: var(--ui-accent);
}

.admin-chart-frame {
    padding: 0.25rem 0.75rem 0.75rem;
}

.admin-transaction-list {
    display: flex;
    flex-direction: column;
    gap: 0.7rem;
    padding: 0.75rem 1.15rem 1.15rem;
}

.admin-transaction-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.9rem;
    min-height: 4.1rem;
    border: 1px solid color-mix(in srgb, var(--ui-accent) 12%, var(--ui-border));
    border-radius: var(--ui-radius-md);
    padding: 0.7rem;
    background: color-mix(in srgb, #fff 93%, var(--ui-accent) 7%);
}

.admin-transaction-row__main {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    min-width: 0;
}

.admin-transaction-row__mark {
    width: 2.4rem;
    height: 2.4rem;
    border-radius: 0.8rem;
    color: color-mix(in srgb, var(--ui-accent) 76%, var(--ui-text));
    background: color-mix(in srgb, #fff 82%, var(--ui-accent) 18%);
}

.admin-transaction-row__title {
    color: var(--ui-text);
    font-size: 0.9rem;
    font-weight: 800;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.admin-transaction-row__meta {
    margin-top: 0.1rem;
    color: var(--ui-muted);
    font-size: 0.76rem;
}

.admin-transaction-row__amount {
    flex-shrink: 0;
    border-radius: 999px;
    padding: 0.35rem 0.65rem;
    background: color-mix(in srgb, #fff 80%, var(--ui-warning) 20%);
    color: color-mix(in srgb, var(--ui-warning) 80%, var(--ui-text));
    font-size: 0.82rem;
    font-weight: 850;
}

.admin-empty-state {
    margin: 0.75rem 1.15rem 1.15rem;
    display: flex;
    min-height: 15rem;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.65rem;
    border: 1px dashed color-mix(in srgb, var(--ui-primary) 20%, var(--ui-border));
    border-radius: var(--ui-radius-md);
    background: color-mix(in srgb, #fff 92%, var(--ui-primary) 8%);
    text-align: center;
}

.admin-empty-state__icon {
    width: 3.2rem;
    height: 3.2rem;
    border-radius: 1rem;
    background: color-mix(in srgb, #fff 84%, var(--ui-primary) 16%);
    color: var(--ui-primary);
}

.admin-empty-state h3 {
    color: var(--ui-text);
    font-size: 1rem;
    font-weight: 800;
}

.admin-empty-state p {
    max-width: 18rem;
    color: var(--ui-muted);
    font-size: 0.86rem;
    line-height: 1.6;
}

@media (max-width: 1180px) {
    .admin-stat-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .admin-dashboard-grid {
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

    .admin-stat-grid {
        grid-template-columns: 1fr;
    }

    .admin-stat-card {
        min-height: 8.4rem;
    }

    .admin-panel__header {
        padding: 1rem 1rem 0.4rem;
    }

    .admin-chart-frame {
        padding-inline: 0.35rem;
    }

    .admin-transaction-list {
        padding: 0.65rem 1rem 1rem;
    }
}
</style>
