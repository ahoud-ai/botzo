<template>
    <AppLayout>
        <div class="dashboard-shell ui-page ui-fade-up px-4 pb-8 pt-4 text-[var(--ui-text)] md:px-8">
            <div class="mx-auto flex max-w-[1680px] flex-col gap-6">
                <section class="dashboard-hero">
                    <div class="dashboard-hero-glow dashboard-hero-glow--primary" />
                    <div class="dashboard-hero-glow dashboard-hero-glow--secondary" />
                    <div class="dashboard-hero-glow dashboard-hero-glow--accent" />

                    <div class="relative space-y-6">
                        <div class="dashboard-hero-head">
                            <div class="dashboard-hero-copy">
                                <p class="dashboard-hero-eyebrow">
                                    <span aria-hidden="true">👋</span>
                                    <span>{{ dashboardHeroGreeting }}</span>
                                </p>
                                <h1 class="dashboard-hero-title">{{ $t('Dashboard') }}</h1>
                                <p class="dashboard-hero-subtitle">{{ dashboardHeroSupportText }}</p>
                            </div>

                            <div class="dashboard-action-cluster dashboard-hero-actions">
                                    <Link
                                        v-if="canCreateContact"
                                        href="/contacts/add"
                                        class="dashboard-action dashboard-action--primary"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
                                            <path d="M15 18v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                            <circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="1.8" />
                                            <path d="M19 8v6M16 11h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                        </svg>
                                        <span>{{ $t('Add contact') }}</span>
                                    </Link>
                                    <button
                                        v-else
                                        type="button"
                                        disabled
                                        class="dashboard-action dashboard-action--disabled"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
                                            <path d="M15 18v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                            <circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="1.8" />
                                            <path d="M19 8v6M16 11h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                        </svg>
                                        <span>{{ $t('Add contact') }}</span>
                                    </button>

                                    <Link
                                        v-if="canCreateCampaign"
                                        href="/campaigns/create"
                                        class="dashboard-action dashboard-action--soft"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
                                            <path d="M18 8a6 6 0 0 0-12 0v8a2 2 0 0 0 2 2h2l2 3l2-3h2a2 2 0 0 0 2-2V8Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                                            <path d="M9 11h6M9 14h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                        </svg>
                                        <span>{{ $t('Create campaign') }}</span>
                                    </Link>
                                    <button
                                        v-else
                                        type="button"
                                        disabled
                                        class="dashboard-action dashboard-action--disabled"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
                                            <path d="M18 8a6 6 0 0 0-12 0v8a2 2 0 0 0 2 2h2l2 3l2-3h2a2 2 0 0 0 2-2V8Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                                            <path d="M9 11h6M9 14h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                        </svg>
                                        <span>{{ $t('Create campaign') }}</span>
                                    </button>

                                    <Link
                                        v-if="canCreateTemplate"
                                        href="/templates/create"
                                        class="dashboard-action dashboard-action--ghost"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
                                            <path d="M8 5h8M8 9h8M8 13h5M7 19h10a2 2 0 0 0 2-2V7l-3-3H7a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <span>{{ $t('Create template') }}</span>
                                    </Link>
                                    <button
                                        v-else
                                        type="button"
                                        disabled
                                        class="dashboard-action dashboard-action--disabled"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
                                            <path d="M8 5h8M8 9h8M8 13h5M7 19h10a2 2 0 0 0 2-2V7l-3-3H7a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <span>{{ $t('Create template') }}</span>
                                    </button>

                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            <div class="dashboard-kpi-card dashboard-kpi-card--primary">
                                <UiStatCard
                                    :title="$t('Unread Messages')"
                                    :value="unreadMessagesCount"
                                    :cta-label="$t('View chats')"
                                    cta-href="/chats?unread=1"
                                >
                                    <template #icon>
                                        <span class="dashboard-kpi-orb dashboard-kpi-orb--primary">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5.6 19.92l1.524-1.219l.01-.008c.318-.255.479-.383.658-.474c.16-.082.331-.142.508-.178c.199-.041.406-.041.822-.041h8.681c1.118 0 1.678 0 2.105-.218a2 2 0 0 0 .874-.874C21 16.48 21 15.92 21 14.804V7.197c0-1.118 0-1.678-.218-2.105a2.001 2.001 0 0 0-.875-.874C19.48 4 18.92 4 17.8 4H6.2c-1.12 0-1.68 0-2.108.218a1.999 1.999 0 0 0-.874.874C3 5.52 3 6.08 3 7.2v11.471c0 1.066 0 1.599.218 1.872a1 1 0 0 0 .783.377c.35 0 .766-.334 1.599-1Z"/></svg>
                                        </span>
                                    </template>
                                </UiStatCard>
                            </div>

                            <div class="dashboard-kpi-card dashboard-kpi-card--secondary">
                                <UiStatCard
                                    :title="$t('All chats')"
                                    :value="props.chatCount"
                                    :cta-label="$t('View chats')"
                                    cta-href="/chats"
                                >
                                    <template #icon>
                                        <span class="dashboard-kpi-orb dashboard-kpi-orb--secondary">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 4h-.8c-1.12 0-1.68 0-2.108.218a1.999 1.999 0 0 0-.874.874C4 5.52 4 6.08 4 7.2v9.6c0 1.12 0 1.68.218 2.108a2 2 0 0 0 .874.874c.427.218.987.218 2.105.218H8M8 4h8.8c1.12 0 1.68 0 2.107.218c.377.192.683.497.875.874c.218.427.218.987.218 2.105v9.607c0 1.118 0 1.677-.218 2.104a2.002 2.002 0 0 1-.875.874c-.427.218-.986.218-2.104.218H8M8 4v16m4-9h4m-4-3h4"/></svg>
                                        </span>
                                    </template>
                                </UiStatCard>
                            </div>

                            <div class="dashboard-kpi-card dashboard-kpi-card--accent">
                                <UiStatCard
                                    :title="$t('Contacts')"
                                    :value="props.contactCount"
                                    :cta-label="$t('View contacts')"
                                    cta-href="/contacts"
                                >
                                    <template #icon>
                                        <span class="dashboard-kpi-orb dashboard-kpi-orb--accent">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M15 19v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2m18 0v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75M13 7a4 4 0 1 1-8 0a4 4 0 0 1 8 0Z"/></svg>
                                        </span>
                                    </template>
                                </UiStatCard>
                            </div>

                            <div class="dashboard-kpi-card dashboard-kpi-card--success">
                                <UiStatCard
                                    :title="$t('Scheduled campaigns')"
                                    :value="scheduledCampaignCount"
                                    :cta-label="$t('View campaigns')"
                                    cta-href="/campaigns"
                                >
                                    <template #icon>
                                        <span class="dashboard-kpi-orb dashboard-kpi-orb--success">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 2v4m8-4v4M3 10h18M6 4h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm6 7v4l3 2"/></svg>
                                        </span>
                                    </template>
                                </UiStatCard>
                            </div>
                        </div>

                        <div class="dashboard-chip-row">
                            <span class="dashboard-chip dashboard-chip--primary">
                                <span class="dashboard-chip__dot" />
                                {{ totalInbound }} {{ $t('Inbound chats') }}
                            </span>
                            <span class="dashboard-chip dashboard-chip--secondary">
                                <span class="dashboard-chip__dot" />
                                {{ totalOutbound }} {{ $t('Outbound chats') }}
                            </span>
                            <span class="dashboard-chip dashboard-chip--accent">
                                <span class="dashboard-chip__dot" />
                                {{ pendingCampaignCount }} {{ $t('pending') }}
                            </span>
                            <span
                                v-if="ticketingEnabled"
                                class="dashboard-chip dashboard-chip--warning"
                            >
                                <span class="dashboard-chip__dot" />
                                {{ openTicketCount }} {{ $t('open') }}
                            </span>
                        </div>
                    </div>
                </section>

                <div class="dashboard-main-grid">
                    <div class="dashboard-main-column dashboard-main-column--supplemental">
                        <section class="dashboard-subscription-panel dashboard-subscription-panel--compact" :style="subscriptionPanelStyle">
                            <div class="dashboard-subscription-glow dashboard-subscription-glow--start"></div>
                            <div class="dashboard-subscription-glow dashboard-subscription-glow--end"></div>
                            <div class="dashboard-subscription-head text-[var(--ui-text)]">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="dashboard-subscription-pill dashboard-subscription-pill--status"
                                        :class="{ 'dashboard-subscription-pill--active': subscriptionPanelIsHealthy }"
                                    >
                                        {{ subscriptionPanelEyebrow }}
                                    </span>
                                    <span v-if="subscriptionPlanBadge" class="dashboard-subscription-pill">
                                        {{ subscriptionPlanBadge }}
                                    </span>
                                </div>
                                <div class="dashboard-subscription-icon">
                                    <svg v-if="subscriptionPanelIsHealthy" xmlns="http://www.w3.org/2000/svg" width="34" height="34" viewBox="0 0 24 24" fill="none">
                                        <path d="m12 3l7.2 4.15v8.3L12 19.6l-7.2-4.15v-8.3L12 3Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                                        <path d="m4.95 7.28L12 11.35l7.05-4.07M12 11.35v8.1" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="m8.25 5.38l7.18 4.14" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                                    </svg>
                                    <svg v-else xmlns="http://www.w3.org/2000/svg" width="34" height="34" viewBox="0 0 512 512"><path fill="currentColor" d="M256 32c14.2 0 27.3 7.5 34.5 19.8l216 368c7.3 12.4 7.3 27.7.2 40.1S486.3 480 472 480H40c-14.3 0-27.6-7.7-34.7-20.1s-7-27.8.2-40.1l216-368C228.7 39.5 241.8 32 256 32m0 128c-13.3 0-24 10.7-24 24v112c0 13.3 10.7 24 24 24s24-10.7 24-24V184c0-13.3-10.7-24-24-24m32 224a32 32 0 1 0-64 0a32 32 0 1 0 64 0"/></svg>
                                </div>
                            </div>
                            <div class="dashboard-subscription-copy">
                                <div class="dashboard-subscription-title-row">
                                    <span
                                        class="dashboard-subscription-title-icon"
                                        :class="{ 'dashboard-subscription-title-icon--active': subscriptionPanelIsHealthy }"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
                                            <path d="M7.5 12.3l2.7 2.7l6.3-6.4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </span>
                                    <h2 class="text-lg font-semibold leading-tight text-[var(--ui-text)] md:text-[1.35rem]">{{ subscriptionPanelTitle }}</h2>
                                </div>
                                <p v-if="subscriptionPanelSupportText" class="mt-1.5 max-w-2xl text-sm leading-6 text-[var(--ui-muted)]">{{ subscriptionPanelSupportText }}</p>
                                <div v-if="subscriptionRemainingNotice || subscriptionPanelMeta" class="dashboard-subscription-insight">
                                    <div v-if="subscriptionRemainingNotice" class="dashboard-subscription-countdown">
                                        <span>{{ subscriptionRemainingNotice.label }}</span>
                                        <strong>{{ subscriptionRemainingNotice.value }}</strong>
                                        <small v-if="subscriptionRemainingNotice.caption">{{ subscriptionRemainingNotice.caption }}</small>
                                    </div>
                                    <span v-if="subscriptionPanelMeta" class="dashboard-subscription-meta">
                                        <span>{{ subscriptionPanelMeta.label }}</span>
                                        <strong>{{ subscriptionPanelMeta.date }}</strong>
                                        <span v-if="subscriptionPanelMeta.time">{{ subscriptionPanelMeta.time }}</span>
                                    </span>
                                </div>
                                <div v-if="subscriptionPanelActionHref" class="mt-4">
                                    <Link
                                        :href="subscriptionPanelActionHref"
                                        class="dashboard-inline-action group"
                                        :class="subscriptionPanelActionClass"
                                    >
                                        <span>{{ subscriptionPanelActionLabel }}</span>
                                        <svg class="transition duration-200 group-hover:translate-x-0.5 rtl:group-hover:-translate-x-0.5" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"><path fill="currentColor" d="m13.172 12l-4.95-4.95l1.414-1.414L16 12l-6.364 6.364l-1.414-1.414z"/></svg>
                                    </Link>
                                </div>
                            </div>
                        </section>

                        <div class="dashboard-panel dashboard-panel--accent dashboard-panel--activity">
                            <UiSectionCard :title="$t('Message activity')" :subtitle="$t('Last 7 days')">
                                <template #icon>
                                    <span class="dashboard-period-pill">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none">
                                            <path d="m8 10l4 4l4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        <span>{{ $t('Last 7 days') }}</span>
                                    </span>
                                </template>
                                <div class="flex h-full flex-col gap-4">
                                    <div class="dashboard-activity-summary grid gap-3 sm:grid-cols-2">
                                        <div class="dashboard-mini-kpi dashboard-mini-kpi--accent">
                                            <div class="dashboard-mini-kpi__head">
                                                <span class="dashboard-mini-kpi__icon">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
                                                        <path d="M10.4 13.3L14.3 9.4m0 0h-3m3 0v3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                                        <path d="M6.5 18.5h11c1.4 0 2.1 0 2.63-.272c.467-.238.845-.616 1.083-1.083c.272-.53.272-1.23.272-2.63v-5c0-1.4 0-2.1-.272-2.63a2.75 2.75 0 0 0-1.083-1.083c-.53-.272-1.23-.272-2.63-.272h-11c-1.4 0-2.1 0-2.63.272a2.75 2.75 0 0 0-1.083 1.083c-.272.53-.272 1.23-.272 2.63v5c0 1.4 0 2.1.272 2.63c.238.467.616.845 1.083 1.083c.53.272 1.23.272 2.63.272Z" stroke="currentColor" stroke-width="1.8"/>
                                                    </svg>
                                                </span>
                                                <p class="dashboard-mini-kpi__label">{{ $t('7-day total') }}</p>
                                            </div>
                                            <div class="dashboard-mini-kpi__value">{{ totalMessages7Days }}</div>
                                        </div>
                                        <div class="dashboard-mini-kpi dashboard-mini-kpi--primary">
                                            <div class="dashboard-mini-kpi__head">
                                                <span class="dashboard-mini-kpi__icon">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
                                                        <path d="M4 7.8c0-1.12 0-1.68.218-2.108a2 2 0 0 1 .874-.874C5.52 4.6 6.08 4.6 7.2 4.6h9.6c1.12 0 1.68 0 2.108.218c.376.192.682.497.874.874C20 6.12 20 6.68 20 7.8v8.4c0 1.12 0 1.68-.218 2.108a2 2 0 0 1-.874.874c-.428.218-.988.218-2.108.218H7.2c-1.12 0-1.68 0-2.108-.218a2 2 0 0 1-.874-.874C4 17.88 4 17.32 4 16.2V7.8Z" stroke="currentColor" stroke-width="1.8"/>
                                                        <path d="m6.5 8.2l5.02 4.05c.29.233.435.35.596.395a1 1 0 0 0 .768 0c.16-.046.306-.162.596-.396L18.5 8.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                                    </svg>
                                                </span>
                                                <p class="dashboard-mini-kpi__label">{{ $t('Unread Messages') }}</p>
                                            </div>
                                            <div class="dashboard-mini-kpi__value">{{ unreadMessagesCount }}</div>
                                        </div>
                                    </div>

                                    <div class="dashboard-chart-frame">
                                        <apexchart type="area" height="235" :options="chartOptions" :series="series"></apexchart>
                                    </div>

                                    <div class="dashboard-activity-actions flex flex-wrap items-center gap-2">
                                        <Link href="/chats" class="dashboard-inline-action dashboard-inline-action--soft">
                                            <span>{{ $t('View chats') }}</span>
                                        </Link>
                                        <Link href="/chats?unread=1" class="dashboard-inline-action dashboard-inline-action--primary-soft">
                                            <span>{{ $t('Unread Messages') }}</span>
                                        </Link>
                                        <Link
                                            v-if="ticketingEnabled && unassignedTicketCount > 0"
                                            href="/chats?status=unassigned"
                                            class="dashboard-inline-action dashboard-inline-action--warning"
                                        >
                                            <span>{{ $t('unassigned') }}</span>
                                        </Link>
                                    </div>
                                </div>
                            </UiSectionCard>
                        </div>

                        <div class="dashboard-panel dashboard-panel--primary dashboard-panel--workspace">
                            <UiSectionCard :title="$t('Workspace status')" :subtitle="$t('Only pending items.')">
                                <template #icon>
                                    <span class="dashboard-panel-icon">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none">
                                            <path d="M8 7V6a4 4 0 0 1 8 0v1M5.8 20h12.4c1.12 0 1.68 0 2.108-.218a2 2 0 0 0 .874-.874C21.4 18.48 21.4 17.92 21.4 16.8v-6.6c0-1.12 0-1.68-.218-2.108a2 2 0 0 0-.874-.874C19.88 7 19.32 7 18.2 7H5.8c-1.12 0-1.68 0-2.108.218a2 2 0 0 0-.874.874C2.6 8.52 2.6 9.08 2.6 10.2v6.6c0 1.12 0 1.68.218 2.108c.192.376.497.682.874.874C4.12 20 4.68 20 5.8 20Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                            <path d="m9 13l2 2l4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </span>
                                </template>
                                <div class="flex h-full flex-col gap-3">
                                    <div
                                        v-if="showWhatsappSetupCard"
                                        class="dashboard-status-card dashboard-status-card--primary"
                                    >
                                        <div class="min-w-0">
                                            <h3 class="text-sm font-semibold text-[var(--ui-text)]">{{ $t('Setup whatsapp') }}</h3>
                                            <p class="mt-1 text-sm text-[var(--ui-muted)]">{{ $t('Finish setup from WhatsApp settings.') }}</p>
                                            <Link href="/settings/whatsapp" class="dashboard-inline-action dashboard-inline-action--primary mt-3">
                                                <span>{{ $t('Settings') }}</span>
                                            </Link>
                                        </div>
                                        <span class="dashboard-status-card__icon">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M8.886 7.17c.183.005.386.015.579.443c.128.285.343.81.519 1.238c.137.333.249.607.277.663c.064.128.104.275.02.448l-.028.058a1.43 1.43 0 0 1-.23.37a9.386 9.386 0 0 0-.143.17c-.085.104-.17.206-.242.278c-.129.128-.262.266-.114.522c.149.256.668 1.098 1.435 1.777a6.634 6.634 0 0 0 1.903 1.2c.07.03.127.055.17.076c.257.128.41.108.558-.064c.149-.173.643-.749.817-1.005c.168-.256.34-.216.578-.128c.238.089 1.504.71 1.761.837l.143.07c.179.085.3.144.352.23c.064.109.064.62-.148 1.222c-.218.6-1.267 1.176-1.742 1.22l-.135.016c-.436.052-.988.12-2.956-.655c-2.426-.954-4.027-3.32-4.35-3.799a2.768 2.768 0 0 0-.053-.076l-.006-.008c-.147-.197-1.048-1.402-1.048-2.646c0-1.19.587-1.81.854-2.092l.047-.05a.95.95 0 0 1 .687-.32c.173 0 .347 0 .495.005Z"/><path fill="currentColor" fill-rule="evenodd" d="M2.184 21.331a.4.4 0 0 0 .487.494l4.607-1.204a10 10 0 0 0 4.76 1.207h.004c5.486 0 9.958-4.447 9.958-9.912a9.828 9.828 0 0 0-2.914-7.011A9.917 9.917 0 0 0 12.042 2c-5.486 0-9.958 4.446-9.958 9.911c0 1.739.458 3.447 1.33 4.954l-1.23 4.466Zm2.677-4.068a1.5 1.5 0 0 0-.148-1.15a8.377 8.377 0 0 1-1.129-4.202c0-4.63 3.793-8.411 8.458-8.411c2.27 0 4.388.877 5.986 2.468a8.328 8.328 0 0 1 2.472 5.948c0 4.63-3.793 8.412-8.458 8.412h-.005a8.5 8.5 0 0 1-4.044-1.026a1.5 1.5 0 0 0-1.094-.132l-2.762.721l.724-2.628Z" clip-rule="evenodd"/></svg>
                                        </span>
                                    </div>

                                    <div
                                        v-if="teamPromptVisible"
                                        class="dashboard-status-card dashboard-status-card--warning"
                                    >
                                        <div class="min-w-0">
                                            <h3 class="text-sm font-semibold text-[var(--ui-text)]">{{ teamSetupTitle }}</h3>
                                            <p class="mt-1 text-sm text-[var(--ui-muted)]">{{ teamSetupDescription }}</p>
                                            <div class="mt-3 flex flex-wrap items-center gap-3">
                                                <Link :href="teamSetupHref" class="dashboard-inline-action dashboard-inline-action--primary">
                                                    <span>{{ teamSetupButtonLabel }}</span>
                                                </Link>
                                                <button type="button" class="dashboard-text-action" @click="dismissTeamPrompt">
                                                    {{ $t('Dismiss prompt') }}
                                                </button>
                                            </div>
                                        </div>
                                        <span class="dashboard-status-card__icon">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 20 20"><path fill="currentColor" d="M10 2a4 4 0 0 1 4 4v1.17a3 3 0 0 0 .879 2.122l.293.293A1 1 0 0 1 14.464 11H5.536a1 1 0 0 1-.708-1.707l.293-.293A3 3 0 0 0 6 7.17V6a4 4 0 0 1 4-4Zm0 16a3 3 0 0 0 2.995-2.824L13 15H7a3 3 0 0 0 3 3Z"/></svg>
                                        </span>
                                    </div>

                                    <div
                                        v-if="showWorkspaceReadyState"
                                        class="dashboard-status-card dashboard-status-card--primary"
                                    >
                                        <div class="flex items-start gap-3">
                                            <span class="dashboard-status-card__icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none">
                                                    <path d="m5 13l4 4L19 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                                </svg>
                                            </span>
                                            <div>
                                                <h3 class="text-sm font-semibold text-[var(--ui-text)]">{{ $t('Ready to work') }}</h3>
                                                <p class="mt-1 text-sm text-[var(--ui-muted)]">{{ $t('Everything is ready.') }}</p>
                                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                                    <Link href="/chats" class="dashboard-inline-action dashboard-inline-action--primary">
                                                        <span>{{ $t('View chats') }}</span>
                                                    </Link>
                                                    <Link href="/settings/whatsapp" class="dashboard-inline-action dashboard-inline-action--soft">
                                                        <span>{{ $t('Settings') }}</span>
                                                    </Link>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </UiSectionCard>
                        </div>
                    </div>

                    <div class="dashboard-main-column dashboard-main-column--primary">
                        <div
                            class="dashboard-panel dashboard-panel--primary dashboard-panel--conversation"
                            :class="{ 'dashboard-panel--conversation-filled': hasRecentConversations }"
                        >
                            <UiSectionCard :title="$t('Recent conversations')" :subtitle="$t('Reply faster from here.')">
                                <template #icon>
                                    <span class="dashboard-panel-icon">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none">
                                            <path d="m5.6 19.92l1.524-1.219l.01-.008c.318-.255.479-.383.658-.474c.16-.082.331-.142.508-.178c.199-.041.406-.041.822-.041h8.681c1.118 0 1.678 0 2.105-.218a2 2 0 0 0 .874-.874C21 16.48 21 15.92 21 14.804V7.197c0-1.118 0-1.678-.218-2.105a2.001 2.001 0 0 0-.875-.874C19.48 4 18.92 4 17.8 4H6.2c-1.12 0-1.68 0-2.108.218a1.999 1.999 0 0 0-.874.874C3 5.52 3 6.08 3 7.2v11.471c0 1.066 0 1.599.218 1.872a1 1 0 0 0 .783.377c.35 0 .766-.334 1.599-1Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </span>
                                </template>
                                <div
                                    v-if="props.recentConversations.length"
                                    class="dashboard-scroll-list dashboard-scroll-list--conversations"
                                    :class="{ 'dashboard-scroll-list--conversations-filled': hasRecentConversations }"
                                >
                                    <Link
                                        v-for="conversation in props.recentConversations"
                                        :key="conversation.uuid"
                                        :href="`/chats/${conversation.uuid}`"
                                        class="dashboard-thread-row"
                                    >
                                        <div class="flex min-w-0 flex-1 items-start gap-3">
                                            <div class="dashboard-avatar">
                                                <img
                                                    v-if="conversation.avatar"
                                                    :src="conversation.avatar"
                                                    :alt="conversation.full_name"
                                                    class="h-full w-full object-cover"
                                                />
                                                <span v-else>{{ avatarInitial(conversation.full_name) }}</span>
                                            </div>

                                            <div class="min-w-0 flex-1">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <h3 class="truncate text-sm font-semibold text-[var(--ui-text)]">
                                                        {{ conversation.full_name }}
                                                    </h3>
                                                    <span
                                                        v-if="conversation.unread_messages > 0"
                                                        class="dashboard-unread-badge"
                                                    >
                                                        {{ conversation.unread_messages }}
                                                    </span>
                                                </div>

                                                <p class="mt-1 truncate text-sm text-[var(--ui-muted)]">
                                                    {{ conversationPreview(conversation) }}
                                                </p>

                                            </div>
                                        </div>

                                        <div class="flex shrink-0 items-center gap-2">
                                            <span class="hidden text-xs text-[var(--ui-muted)] sm:inline">
                                                {{ formatConversationTime(conversation.last_chat?.created_at) }}
                                            </span>
                                            <span class="dashboard-arrow-chip">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none">
                                                    <path d="m9 6l6 6l-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                                </svg>
                                            </span>
                                        </div>
                                    </Link>
                                </div>

                                <div
                                    v-else
                                    class="dashboard-empty-state dashboard-empty-state--conversation"
                                >
                                    <div class="dashboard-empty-icon">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                            <path d="m5.6 19.92l1.524-1.219l.01-.008c.318-.255.479-.383.658-.474c.16-.082.331-.142.508-.178c.199-.041.406-.041.822-.041h8.681c1.118 0 1.678 0 2.105-.218a2 2 0 0 0 .874-.874C21 16.48 21 15.92 21 14.804V7.197c0-1.118 0-1.678-.218-2.105a2.001 2.001 0 0 0-.875-.874C19.48 4 18.92 4 17.8 4H6.2c-1.12 0-1.68 0-2.108.218a1.999 1.999 0 0 0-.874.874C3 5.52 3 6.08 3 7.2v11.471c0 1.066 0 1.599.218 1.872a1 1 0 0 0 .783.377c.35 0 .766-.334 1.599-1Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </div>
                                    <p class="mt-4 text-sm font-medium text-[var(--ui-text)]">{{ $t('No conversations yet.') }}</p>
                                    <Link href="/chats" class="dashboard-inline-action dashboard-inline-action--primary mt-4">
                                        <span>{{ $t('View chats') }}</span>
                                    </Link>
                                </div>
                            </UiSectionCard>
                        </div>

                        <div
                            class="dashboard-panel dashboard-panel--secondary dashboard-panel--campaigns"
                            :class="{ 'dashboard-panel--campaigns-filled': hasCampaignQueueItems }"
                        >
                            <UiSectionCard :title="$t('Campaign queue')" :subtitle="$t('Pending and scheduled.')">
                                <template #icon>
                                    <span class="dashboard-panel-icon">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none">
                                            <path d="M14 8.5V5.7c0-.98 0-1.47-.19-1.844a1.75 1.75 0 0 0-.765-.765C12.67 2.9 12.18 2.9 11.2 2.9H6.8c-.98 0-1.47 0-1.844.19a1.75 1.75 0 0 0-.765.765C4 4.23 4 4.72 4 5.7v9.6c0 .98 0 1.47.19 1.844c.168.33.435.597.765.765c.374.19.864.19 1.844.19h2.7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M13.5 9.5l6.8-2.8v10.6l-6.8-2.8V9.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                                            <path d="M13.5 14.5v2.35a1.95 1.95 0 1 1-3.9 0V15.3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                                        </svg>
                                    </span>
                                </template>
                                <div class="flex h-full flex-col gap-4">
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div class="dashboard-mini-kpi dashboard-mini-kpi--warning">
                                            <div class="dashboard-mini-kpi__head">
                                                <span class="dashboard-mini-kpi__icon">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
                                                        <rect x="5" y="11" width="14" height="9" rx="2.5" stroke="currentColor" stroke-width="1.8"/>
                                                        <path d="M8 11V8a4 4 0 1 1 8 0v3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                                    </svg>
                                                </span>
                                                <p class="dashboard-mini-kpi__label">{{ $t('pending') }}</p>
                                            </div>
                                            <div class="dashboard-mini-kpi__value">{{ pendingCampaignCount }}</div>
                                        </div>
                                        <div class="dashboard-mini-kpi dashboard-mini-kpi--secondary">
                                            <div class="dashboard-mini-kpi__head">
                                                <span class="dashboard-mini-kpi__icon">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
                                                        <path d="M7 3v3M17 3v3M3.75 9.25h16.5M6.4 5.75h11.2c1.493 0 2.24 0 2.81.29c.5.255.905.66 1.16 1.16c.29.57.29 1.317.29 2.81v7.6c0 1.493 0 2.24-.29 2.81a2.75 2.75 0 0 1-1.16 1.16c-.57.29-1.317.29-2.81.29H6.4c-1.493 0-2.24 0-2.81-.29a2.75 2.75 0 0 1-1.16-1.16c-.29-.57-.29-1.317-.29-2.81v-7.6c0-1.493 0-2.24.29-2.81c.255-.5.66-.905 1.16-1.16c.57-.29 1.317-.29 2.81-.29Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                                        <path d="M12 13.2v2.7l1.9 1.1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                                    </svg>
                                                </span>
                                                <p class="dashboard-mini-kpi__label">{{ $t('scheduled') }}</p>
                                            </div>
                                            <div class="dashboard-mini-kpi__value">{{ scheduledCampaignCount }}</div>
                                        </div>
                                    </div>

                                    <div v-if="props.campaigns.length === 0" class="dashboard-empty-state dashboard-empty-state--queue">
                                        <div class="dashboard-empty-icon">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                <path d="M14 8.5V5.7c0-.98 0-1.47-.19-1.844a1.75 1.75 0 0 0-.765-.765C12.67 2.9 12.18 2.9 11.2 2.9H6.8c-.98 0-1.47 0-1.844.19a1.75 1.75 0 0 0-.765.765C4 4.23 4 4.72 4 5.7v9.6c0 .98 0 1.47.19 1.844c.168.33.435.597.765.765c.374.19.864.19 1.844.19h2.7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                                <path d="M13.5 9.5l6.8-2.8v10.6l-6.8-2.8V9.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                            </svg>
                                        </div>
                                        <p class="text-sm font-medium text-[var(--ui-text)]">{{ $t('Queue is clear.') }}</p>
                                        <div class="mt-4 flex justify-center">
                                            <Link
                                                v-if="canCreateCampaign"
                                                href="/campaigns/create"
                                                class="dashboard-inline-action dashboard-inline-action--primary"
                                            >
                                                <span>{{ $t('Create campaign') }}</span>
                                            </Link>
                                            <button
                                                v-else
                                                type="button"
                                                disabled
                                                class="dashboard-inline-action dashboard-inline-action--disabled"
                                            >
                                                <span>{{ $t('Create campaign') }}</span>
                                            </button>
                                        </div>
                                    </div>

                                    <div
                                        v-else
                                        class="dashboard-scroll-list dashboard-scroll-list--campaigns"
                                        :class="{ 'dashboard-scroll-list--campaigns-filled': hasCampaignQueueItems }"
                                    >
                                        <div
                                            v-for="item in props.campaigns"
                                            :key="item.uuid || item.id"
                                            class="dashboard-queue-row"
                                        >
                                            <div class="min-w-0">
                                                <h3 class="truncate text-sm font-semibold text-[var(--ui-text)]">{{ item.name }}</h3>
                                                <p class="mt-1 text-xs text-[var(--ui-muted)]">{{ $t('Campaigns') }}</p>
                                            </div>
                                            <span :class="campaignStatusChipClass(item.status)">
                                                {{ $t(item.status) }}
                                            </span>
                                        </div>

                                        <Link href="/campaigns" class="dashboard-inline-link">
                                            <span>{{ $t('View campaigns') }}</span>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none">
                                                <path d="m9 6l6 6l-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        </Link>
                                    </div>
                                </div>
                            </UiSectionCard>
                        </div>

                        <div class="dashboard-insight-grid">
                            <div
                                class="dashboard-panel dashboard-panel--primary dashboard-panel--team"
                                :class="{ 'dashboard-panel--team-warning': teamPromptVisible }"
                            >
                                <UiSectionCard :title="$t('Company team')" :subtitle="$t('Manage members and access from one place.')">
                                    <template #icon>
                                        <span class="dashboard-panel-icon">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none">
                                                <path d="M16 20c0-1.65-1.79-3-4-3s-4 1.35-4 3m11-3c0-1.16-.85-2.16-2.05-2.7M5 17c0-1.16.85-2.16 2.05-2.7M15 7.5a3 3 0 1 1-6 0a3 3 0 0 1 6 0Zm4.5 3a2.5 2.5 0 1 1-3.38-2.34M4.5 10.5a2.5 2.5 0 1 0 3.38-2.34" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </span>
                                    </template>

                                    <div class="dashboard-team-card">
                                        <div class="dashboard-team-metrics">
                                            <div class="dashboard-team-metric dashboard-team-metric--count">
                                                <span class="dashboard-team-metric__number">{{ teamMembersCount }}</span>
                                                <span class="dashboard-team-metric__title">{{ $t('Team Members') }}</span>
                                                <span class="dashboard-team-metric__note">
                                                    {{ teamMembersCount > 0 ? $t('Everything is ready.') : $t('No team members found.') }}
                                                </span>
                                            </div>

                                            <Link :href="teamSetupHref" class="dashboard-team-metric dashboard-team-metric--invite">
                                                <span class="dashboard-team-metric__icon">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
                                                        <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
                                                    </svg>
                                                </span>
                                                <span class="dashboard-team-metric__title">{{ $t('Invite member') }}</span>
                                                <span class="dashboard-team-metric__note">{{ $t('Add new member') }}</span>
                                            </Link>
                                        </div>

                                        <div class="dashboard-team-footer">
                                            <Link :href="teamSetupHref" class="dashboard-inline-link">
                                                <span>{{ $t('Manage team') }}</span>
                                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none">
                                                    <path d="m9 6l6 6l-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                                </svg>
                                            </Link>
                                            <button
                                                v-if="teamPromptVisible"
                                                type="button"
                                                class="dashboard-text-action"
                                                @click="dismissTeamPrompt"
                                            >
                                                {{ $t('Dismiss prompt') }}
                                            </button>
                                        </div>
                                    </div>
                                </UiSectionCard>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from "./Layout/App.vue";
import UiSectionCard from "@/Components/UI/UiSectionCard.vue";
import UiStatCard from "@/Components/UI/UiStatCard.vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useWorkspaceAccess } from '@/Composables/useWorkspaceAccess';

const { t, locale } = useI18n();
const { isWorkspaceOwner, isBranchWorkspace, hasInheritedParentAuthority, canViewBilling, hasPermission } = useWorkspaceAccess();
const page = usePage();

const canCreateContact = computed(() => hasPermission('contacts.create'));
const canCreateCampaign = computed(() => hasPermission('campaigns.add'));
const canCreateTemplate = computed(() => hasPermission('message_templates.add'));

const props = defineProps({
    user: Object,
    auth: Object,
    subscription: Object,
    subscriptionIsActive: Boolean,
    subscriptionDetails: Object,
    subscriptionDisplayState: Object,
    chatCount: Number,
    contactCount: Number,
    campaignCount: Number,
    templateCount: Number,
    teamMemberCount: {
        type: Number,
        default: 0,
    },
    setupWhatsapp: Boolean,
    organization: Object,
    campaigns: {
        type: Array,
        default: () => [],
    },
    campaignSummary: {
        type: Object,
        default: () => ({
            pending: 0,
            scheduled: 0,
        }),
    },
    ticketSummary: {
        type: Object,
        default: () => ({
            enabled: false,
            open: 0,
            unassigned: 0,
        }),
    },
    recentConversations: {
        type: Array,
        default: () => [],
    },
    period: {
        type: Array,
        default: () => [],
    },
    inbound: {
        type: Array,
        default: () => [],
    },
    outbound: {
        type: Array,
        default: () => [],
    },
    embeddedSignupActive: Number,
    appId: String,
    configId: String,
    graphAPIVersion: String,
});

const unreadMessagesCount = computed(() => Number(page.props.unreadMessages ?? 0));
const scheduledCampaignCount = computed(() => Number(props.campaignSummary?.scheduled ?? 0));
const pendingCampaignCount = computed(() => Number(props.campaignSummary?.pending ?? 0));
const openTicketCount = computed(() => Number(props.ticketSummary?.open ?? 0));
const unassignedTicketCount = computed(() => Number(props.ticketSummary?.unassigned ?? 0));
const hasRecentConversations = computed(() => props.recentConversations.length > 0);
const hasCampaignQueueItems = computed(() => props.campaigns.length > 0);
const teamMembersCount = computed(() => Number(props.teamMemberCount ?? 0));
const ticketingEnabled = computed(() => props.ticketSummary?.enabled === true);
const totalInbound = computed(() => props.inbound.reduce((sum, value) => sum + Number(value || 0), 0));
const totalOutbound = computed(() => props.outbound.reduce((sum, value) => sum + Number(value || 0), 0));
const totalMessages7Days = computed(() => totalInbound.value + totalOutbound.value);
const dashboardHeroGreeting = computed(() => (
    locale.value === 'ar'
        ? `مرحبا بعودتك، ${props.auth.user.first_name}`
        : `${t('Welcome back')}, ${props.auth.user.first_name}`
));
const dashboardHeroSupportText = computed(() => (
    locale.value === 'ar'
        ? 'إدارة محادثاتك وجهات الاتصال بسهولة'
        : 'Manage conversations and contacts with clarity.'
));

const organizationMetadata = computed(() => {
    try {
        return props.organization?.metadata ? JSON.parse(props.organization.metadata) : {};
    } catch (error) {
        return {};
    }
});

const teamPromptVisible = computed(() => {
    if (!isWorkspaceOwner.value) {
        return false;
    }

    const promptState = organizationMetadata.value?.dashboard?.team_prompt;

    return promptState === undefined ? true : promptState === true;
});

const showWhatsappSetupCard = computed(() => isWorkspaceOwner.value && props.setupWhatsapp === true);
const showWorkspaceReadyState = computed(() => !showWhatsappSetupCard.value && !teamPromptVisible.value);

const subscriptionDisplayState = computed(() => props.subscriptionDisplayState ?? {});
const subscriptionStateVariant = computed(() => subscriptionDisplayState.value.variant ?? 'billing_pending');
const subscriptionManagedByParent = computed(() => subscriptionDisplayState.value.managed_by_parent === true);
const subscriptionPanelIsHealthy = computed(() => subscriptionManagedByParent.value || props.subscriptionIsActive);
const ownerCanManageBilling = computed(() => canViewBilling.value && !subscriptionManagedByParent.value);
const showSubscribeButton = computed(() => ownerCanManageBilling.value && ['billing_pending', 'inactive', 'trial_active', 'trial_expired'].includes(subscriptionStateVariant.value));
const showRenewPlanButton = computed(() => ownerCanManageBilling.value && subscriptionStateVariant.value === 'payment_required');

const subscriptionPanelActionHref = computed(() => {
    if (showRenewPlanButton.value) {
        return '/subscription';
    }

    if (subscriptionStateVariant.value === 'billing_pending') {
        return '/billing';
    }

    if (showSubscribeButton.value) {
        return '/subscription';
    }

    return null;
});

const subscriptionPanelActionLabel = computed(() => {
    if (showRenewPlanButton.value) {
        return t('Renew plan');
    }

    if (subscriptionStateVariant.value === 'billing_pending') {
        return t('Open billing');
    }

    if (showSubscribeButton.value) {
        return t('Plans');
    }

    return null;
});

const subscriptionPanelTone = computed(() => {
    if (subscriptionManagedByParent.value) {
        return {
            tone: 'var(--ui-primary)',
            accent: 'var(--ui-secondary)',
        };
    }

    if (subscriptionStateVariant.value === 'payment_required') {
        return {
            tone: 'var(--ui-danger)',
            accent: 'var(--ui-accent)',
        };
    }

    if (subscriptionStateVariant.value === 'trial_expired') {
        return {
            tone: 'var(--ui-warning)',
            accent: 'var(--ui-accent)',
        };
    }

    if (subscriptionStateVariant.value === 'inactive') {
        return {
            tone: 'color-mix(in srgb, var(--ui-primary) 78%, var(--ui-secondary) 22%)',
            accent: 'var(--ui-secondary)',
        };
    }

    if (subscriptionStateVariant.value === 'billing_pending') {
        return {
            tone: 'var(--ui-primary)',
            accent: 'var(--ui-secondary)',
        };
    }

    return {
        tone: 'var(--ui-primary)',
        accent: 'var(--ui-secondary)',
    };
});

const subscriptionPanelStatusTone = computed(() => {
    if (subscriptionPanelIsHealthy.value) {
        return 'var(--ui-success)';
    }

    if (subscriptionStateVariant.value === 'payment_required') {
        return 'var(--ui-danger)';
    }

    if (subscriptionStateVariant.value === 'trial_expired') {
        return 'var(--ui-warning)';
    }

    return subscriptionPanelTone.value.tone;
});

const subscriptionPanelStyle = computed(() => ({
    '--dashboard-subscription-tone': subscriptionPanelTone.value.tone,
    '--dashboard-subscription-accent': subscriptionPanelTone.value.accent,
    '--dashboard-subscription-status': subscriptionPanelStatusTone.value,
}));

const subscriptionPanelActionClass = computed(() => {
    if (showRenewPlanButton.value) {
        return 'dashboard-inline-action--danger';
    }

    return 'dashboard-inline-action--primary';
});

const parseMoney = (value) => {
    const normalized = String(value ?? '0').replace(/,/g, '');
    const parsed = parseFloat(normalized);

    return Number.isNaN(parsed) ? 0 : parsed;
};

const renewalAmountDue = computed(() => Math.max(0, parseMoney(props.subscriptionDetails?.amountDue)).toFixed(2));
const renewalRequiresPayment = computed(() => parseMoney(props.subscriptionDetails?.amountDue) > 0);

const subscriptionValidUntilParts = computed(() => {
    const raw = String(props.subscription?.valid_until ?? '').trim();

    if (!raw || raw === '--') {
        return null;
    }

    const parts = raw.split(/\s+/);

    if (parts.length === 1) {
        return {
            date: raw,
            time: null,
        };
    }

    return {
        date: parts.slice(0, -1).join(' '),
        time: parts[parts.length - 1] ?? null,
    };
});

const parseSubscriptionDate = (value) => {
    const raw = String(value ?? '').trim();

    if (!raw || raw === '--') {
        return null;
    }

    const normalized = raw.includes('T') ? raw : raw.replace(' ', 'T');
    const parsed = new Date(normalized);

    return Number.isNaN(parsed.getTime()) ? null : parsed;
};

const subscriptionRemainingDays = computed(() => {
    const endDate = parseSubscriptionDate(props.subscription?.valid_until);

    if (!endDate) {
        return null;
    }

    const millisecondsPerDay = 1000 * 60 * 60 * 24;
    return Math.ceil((endDate.getTime() - Date.now()) / millisecondsPerDay);
});

const subscriptionRemainingNotice = computed(() => {
    if (subscriptionRemainingDays.value === null) {
        return null;
    }

    const isArabic = locale.value === 'ar';
    const days = subscriptionRemainingDays.value;

    if (days < 0) {
        return {
            label: isArabic ? 'حالة الباقة' : t('Plan status'),
            value: isArabic ? 'انتهت' : t('Expired'),
            caption: isArabic ? 'يحتاج الاشتراك إلى تجديد' : t('Renewal is required'),
        };
    }

    if (days === 0) {
        return {
            label: isArabic ? 'تنتهي الباقة' : t('Plan expires'),
            value: isArabic ? 'اليوم' : t('Today'),
            caption: isArabic ? 'راجع التجديد قبل نهاية اليوم' : t('Review renewal before the day ends'),
        };
    }

    return {
        label: isArabic ? 'متبقي على انتهاء الباقة' : t('Remaining until plan ends'),
        value: isArabic ? `${days} ${days === 1 ? 'يوم' : 'يوم'}` : `${days} ${days === 1 ? t('day') : t('days')}`,
        caption: isArabic ? 'قبل تاريخ التجديد القادم' : t('Before the next renewal date'),
    };
});

const subscriptionPanelMeta = computed(() => {
    if (!subscriptionValidUntilParts.value) {
        return null;
    }

    if (subscriptionStateVariant.value === 'trial_active') {
        return {
            label: t('Trial expires on'),
            date: subscriptionValidUntilParts.value.date,
            time: subscriptionValidUntilParts.value.time,
        };
    }

    if (subscriptionStateVariant.value === 'active') {
        return {
            label: t('Valid until'),
            date: subscriptionValidUntilParts.value.date,
            time: subscriptionValidUntilParts.value.time,
        };
    }

    return null;
});

const subscriptionPlanBadge = computed(() => {
    const planName = props.subscription?.plan_name
        ?? props.subscription?.plan?.name
        ?? props.subscription?.plan?.name_en
        ?? props.subscription?.plan?.name_ar
        ?? null;

    if (typeof planName === 'string' && planName.trim() !== '') {
        return planName.trim();
    }

    if (!subscriptionManagedByParent.value && ['billing_pending', 'inactive'].includes(subscriptionStateVariant.value)) {
        return t('Not selected yet');
    }

    return null;
});

const subscriptionPanelEyebrow = computed(() => {
    if (subscriptionManagedByParent.value) {
        return t('Inherited subscription');
    }

    if (subscriptionStateVariant.value === 'payment_required') {
        return t('Renew plan');
    }

    if (subscriptionStateVariant.value === 'billing_pending') {
        return t('Billing setup required');
    }

    if (subscriptionStateVariant.value === 'trial_expired') {
        return t('Subscribe');
    }

    if (subscriptionStateVariant.value === 'active') {
        return t('Active subscription');
    }

    if (subscriptionStateVariant.value === 'trial_active') {
        return t('Trial period');
    }

    return t('Billing and subscription');
});

const subscriptionPanelTitle = computed(() => {
    if (subscriptionManagedByParent.value) {
        return t('Managed from parent subscription');
    }

    if (subscriptionStateVariant.value === 'billing_pending') {
        return t('Finish billing setup');
    }

    if (subscriptionStateVariant.value === 'inactive') {
        return t('No active subscription yet');
    }

    if (subscriptionStateVariant.value === 'trial_expired') {
        return t('Your trial period is over');
    }

    if (subscriptionStateVariant.value === 'payment_required') {
        return t('Subscription renewal required');
    }

    if (subscriptionStateVariant.value === 'trial_active') {
        return t('Trial period');
    }

    return t('Active subscription');
});

const subscriptionPanelSupportText = computed(() => {
    if (subscriptionManagedByParent.value) {
        return t('This branch uses the subscription managed by the parent organization.');
    }

    if (subscriptionStateVariant.value === 'billing_pending') {
        return t('Choose a plan and start billing whenever you are ready.');
    }

    if (subscriptionStateVariant.value === 'inactive') {
        return t('Choose a plan to start billing for this workspace.');
    }

    if (subscriptionStateVariant.value === 'trial_expired') {
        return `${t('Please subscribe to a plan to continue using the app')}.`;
    }

    if (subscriptionStateVariant.value === 'payment_required') {
        if (renewalRequiresPayment.value) {
            return `${t('Renew your current plan to continue using the app. Amount due')} ${renewalAmountDue.value}.`;
        }

        return t('Your renewal is covered by available credit. Renew now to reactivate the subscription.');
    }

    return null;
});

const teamSetupTitle = computed(() => t('Team'));
const teamSetupDescription = computed(() => {
    if (isBranchWorkspace.value && hasInheritedParentAuthority.value) {
        return t('Manage branch access from company team.');
    }

    return t('Manage members and access from one place.');
});
const teamSetupHref = computed(() => '/settings/company-team');
const teamSetupButtonLabel = computed(() => t('Company team'));

const dismissTeamPrompt = () => {
    router.delete('/dismiss-team-prompt/team', {});
};

const avatarInitial = (name) => {
    const value = String(name ?? '').trim();

    return value ? value.substring(0, 1) : '?';
};

const parseChatMetadata = (metadata) => {
    if (!metadata) {
        return null;
    }

    try {
        return JSON.parse(metadata);
    } catch (error) {
        return null;
    }
};

const conversationPreview = (conversation) => {
    if (!conversation?.last_chat || conversation.last_chat.deleted_at) {
        return t('View chats');
    }

    const metadata = parseChatMetadata(conversation.last_chat.metadata);
    const contentType = metadata?.type;

    if (contentType === 'text') {
        return metadata?.text?.body || t('View chats');
    }

    if (contentType === 'button') {
        return metadata?.button?.text || t('View chats');
    }

    if (contentType === 'interactive') {
        return metadata?.interactive?.button_reply?.title
            || metadata?.interactive?.list_reply?.title
            || t('View chats');
    }

    if (contentType === 'image') {
        return t('Photo');
    }

    if (contentType === 'document') {
        return t('File');
    }

    if (contentType === 'video') {
        return t('Video');
    }

    if (contentType === 'audio') {
        return t('Audio');
    }

    if (contentType === 'sticker') {
        return t('Sticker');
    }

    if (contentType === 'contacts') {
        return t('Contacts');
    }

    if (contentType === 'location') {
        return t('Location');
    }

    return t('View chats');
};

const formatConversationTime = (value) => {
    if (!value) {
        return '--';
    }

    const currentTime = new Date();
    const targetTime = new Date(value);

    if (Number.isNaN(targetTime.getTime())) {
        return '--';
    }

    if (
        targetTime.getDate() === currentTime.getDate() &&
        targetTime.getMonth() === currentTime.getMonth() &&
        targetTime.getFullYear() === currentTime.getFullYear()
    ) {
        return new Intl.DateTimeFormat(locale.value || 'en', {
            hour12: true,
            hour: 'numeric',
            minute: 'numeric',
        }).format(targetTime);
    }

    const yesterday = new Date();
    yesterday.setDate(currentTime.getDate() - 1);

    if (
        targetTime.getDate() === yesterday.getDate() &&
        targetTime.getMonth() === yesterday.getMonth() &&
        targetTime.getFullYear() === yesterday.getFullYear()
    ) {
        return t('Yesterday');
    }

    return new Intl.DateTimeFormat(locale.value || 'en', {
        day: 'numeric',
        month: 'numeric',
        year: 'numeric',
    }).format(targetTime);
};

const campaignStatusChipClass = (status) => {
    if (status === 'scheduled') {
        return 'dashboard-soft-badge dashboard-soft-badge--secondary';
    }

    if (status === 'pending') {
        return 'dashboard-soft-badge dashboard-soft-badge--warning';
    }

    return 'dashboard-soft-badge dashboard-soft-badge--neutral';
};

const getThemeColor = (property, fallback) => (
    typeof window !== 'undefined'
        ? getComputedStyle(document.documentElement).getPropertyValue(property).trim() || fallback
        : fallback
);

const chartPrimaryColor = getThemeColor('--ui-primary', getThemeColor('--color-primary', '#0b5fff'));
const chartSecondaryColor = getThemeColor('--ui-secondary', '#0ea5a4');
const chartMutedColor = getThemeColor('--ui-muted', '#475569');
const chartBorderColor = getThemeColor('--ui-border', 'rgba(15, 23, 42, 0.14)');

const formatChartPeriodLabel = (value) => {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat(locale.value || 'en', {
        day: 'numeric',
        month: 'short',
    }).format(date);
};

const chartPeriodLabels = computed(() => props.period.map((date) => formatChartPeriodLabel(date)));

const chartOptions = computed(() => ({
    chart: {
        height: 235,
        type: 'area',
        foreColor: chartMutedColor,
        toolbar: {
            show: false
        },
        zoom: {
            enabled: false
        },
    },
    colors: [chartPrimaryColor, chartSecondaryColor],
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.34,
            opacityTo: 0.06,
            stops: [0, 100]
        }
    },
    dataLabels: {
        enabled: false
    },
    legend: {
        position: 'bottom',
        horizontalAlign: 'center',
    },
    grid: {
        borderColor: chartBorderColor,
        strokeDashArray: 4,
    },
    stroke: {
        width: 3,
        curve: 'smooth'
    },
    xaxis: {
        type: 'category',
        categories: chartPeriodLabels.value,
        tickPlacement: 'between',
        labels: {
            hideOverlappingLabels: false,
            rotate: 0,
            trim: false,
            style: {
                colors: chartMutedColor,
                fontSize: '11px',
            },
        },
    },
    yaxis: {
        labels: {
            style: {
                colors: chartMutedColor,
            },
        },
    },
    tooltip: {
        x: {
            formatter: (value) => value,
        },
    },
}));

const series = computed(() => [
    {
        name: t('Inbound chats'),
        data: props.inbound
    },
    {
        name: t('Outbound chats'),
        data: props.outbound
    }
]);
</script>

<style scoped>
.dashboard-shell {
    --dashboard-glass: rgba(255, 255, 255, 0.94);
}

.dashboard-hero {
    position: relative;
    overflow: hidden;
    padding: clamp(1rem, 1.6vw, 1.35rem);
    border-radius: 1.7rem;
    border: 1px solid color-mix(in srgb, var(--ui-primary) 8%, white);
    background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.985), rgba(255, 255, 255, 0.95)),
        linear-gradient(130deg, color-mix(in srgb, var(--ui-primary) 2%, transparent), color-mix(in srgb, var(--ui-secondary) 2%, transparent));
    box-shadow: 0 24px 64px -52px rgba(15, 23, 42, 0.34);
    backdrop-filter: blur(12px);
}

.dashboard-hero::before {
    content: "";
    position: absolute;
    inset-inline-end: -3rem;
    top: -4.25rem;
    width: 19rem;
    height: 19rem;
    border-radius: 999px;
    background:
        radial-gradient(circle, color-mix(in srgb, var(--ui-primary) 14%, transparent) 1px, transparent 1.8px);
    background-size: 0.58rem 0.58rem;
    mask-image: radial-gradient(circle at center, rgba(0, 0, 0, 0.84), transparent 72%);
    opacity: 0.6;
    pointer-events: none;
}

.dashboard-hero::after {
    content: "";
    position: absolute;
    inset-inline: 1.2rem;
    top: 0;
    height: 2px;
    border-radius: 999px;
    background: linear-gradient(90deg, var(--ui-primary), color-mix(in srgb, var(--ui-secondary) 82%, var(--ui-primary)));
    opacity: 0.52;
}

.dashboard-hero-glow {
    position: absolute;
    border-radius: 999px;
    filter: blur(52px);
    pointer-events: none;
    opacity: 0.12;
}

.dashboard-hero-glow--primary {
    inset-inline-start: -3.5rem;
    top: -2rem;
    width: 12rem;
    height: 12rem;
    background: color-mix(in srgb, var(--ui-primary) 36%, white);
}

.dashboard-hero-glow--secondary {
    inset-inline-end: -2.5rem;
    top: -1rem;
    width: 10rem;
    height: 10rem;
    background: color-mix(in srgb, var(--ui-secondary) 28%, white);
}

.dashboard-hero-glow--accent {
    inset-inline-end: 18%;
    bottom: -3.5rem;
    width: 8rem;
    height: 8rem;
    background: color-mix(in srgb, var(--ui-accent) 22%, white);
}

.dashboard-hero-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
}

.dashboard-hero-copy {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 0.35rem;
}

.dashboard-hero-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: color-mix(in srgb, var(--ui-text) 72%, white 28%);
    font-size: 0.92rem;
    font-weight: 700;
}

.dashboard-hero-title {
    font-size: clamp(2.05rem, 2.8vw, 2.7rem);
    line-height: 1.08;
    letter-spacing: -0.04em;
    font-weight: 900;
    color: var(--ui-text);
}

.dashboard-hero-subtitle {
    max-width: 36rem;
    color: var(--ui-muted);
    font-size: 0.98rem;
    line-height: 1.7;
}

.dashboard-action {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    min-height: 2.8rem;
    padding: 0.68rem 0.98rem;
    border-radius: 1rem;
    border: 1px solid color-mix(in srgb, var(--ui-primary) 10%, white);
    font-size: 0.9rem;
    font-weight: 700;
    transition: transform 180ms ease, box-shadow 180ms ease, border-color 180ms ease, background 180ms ease, color 180ms ease;
}

.dashboard-action svg {
    flex-shrink: 0;
}

.dashboard-action-cluster {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.65rem;
}

.dashboard-hero-actions {
    align-self: flex-start;
    justify-content: flex-start;
}

.dashboard-main-grid {
    display: grid;
    gap: 1.5rem;
    align-items: start;
    direction: ltr;
}

.dashboard-main-column--primary {
    order: 1;
}

.dashboard-main-column--supplemental {
    order: 2;
}

@media (min-width: 1280px) {
    .dashboard-main-grid {
        grid-template-columns: minmax(320px, 0.42fr) minmax(0, 0.58fr);
    }

    .dashboard-main-column--supplemental {
        order: 1;
    }

    .dashboard-main-column--primary {
        order: 2;
    }
}

.dashboard-main-column {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 1.5rem;
    direction: rtl;
}

.dashboard-action:hover {
    transform: translateY(-1px);
}

.dashboard-action--primary {
    color: white;
    border-color: transparent;
    background: linear-gradient(135deg, color-mix(in srgb, var(--ui-primary) 92%, #0b1425), color-mix(in srgb, var(--ui-secondary) 16%, var(--ui-primary)));
    box-shadow: 0 16px 26px -22px color-mix(in srgb, var(--ui-primary) 24%, transparent);
}

.dashboard-action--soft,
.dashboard-action--ghost {
    color: color-mix(in srgb, var(--ui-text) 84%, var(--ui-primary) 16%);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), color-mix(in srgb, var(--ui-primary) 2%, white));
    box-shadow: 0 12px 20px -22px rgba(15, 23, 42, 0.16);
}

.dashboard-action--ghost {
    border-color: color-mix(in srgb, var(--ui-secondary) 10%, white);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.99), color-mix(in srgb, var(--ui-secondary) 2%, white));
}

.dashboard-action--disabled {
    cursor: not-allowed;
    color: color-mix(in srgb, var(--ui-muted) 78%, white);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.86), rgba(244, 247, 252, 0.94));
    border-color: var(--ui-border);
    opacity: 0.72;
}

.dashboard-kpi-card {
    --dashboard-card-tone: var(--ui-primary);
}

.dashboard-kpi-card--secondary {
    --dashboard-card-tone: var(--ui-secondary);
}

.dashboard-kpi-card--accent {
    --dashboard-card-tone: var(--ui-accent);
}

.dashboard-kpi-card--success {
    --dashboard-card-tone: var(--ui-success);
}

.dashboard-kpi-card :deep(.ui-kpi) {
    min-height: 7.75rem;
    padding: 0.95rem 1rem 0.82rem;
    border-radius: 1.35rem;
    border-color: color-mix(in srgb, var(--dashboard-card-tone) 11%, white);
    background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.995), color-mix(in srgb, var(--dashboard-card-tone) 2%, white)) !important;
    box-shadow: 0 16px 32px -28px rgba(15, 23, 42, 0.16);
}

.dashboard-kpi-card :deep(.ui-kpi::before) {
    background:
        radial-gradient(circle at top right, color-mix(in srgb, var(--dashboard-card-tone) 8%, transparent), transparent 42%),
        linear-gradient(135deg, color-mix(in srgb, var(--dashboard-card-tone) 4%, transparent), transparent 58%);
    opacity: 0.72;
}

.dashboard-kpi-card :deep(.ui-kpi:hover) {
    border-color: color-mix(in srgb, var(--dashboard-card-tone) 18%, white);
    box-shadow: 0 20px 36px -28px color-mix(in srgb, var(--dashboard-card-tone) 10%, rgba(15, 23, 42, 0.16));
}

.dashboard-kpi-card :deep(.ui-kpi-title) {
    font-size: 0.92rem;
    font-weight: 700;
    color: color-mix(in srgb, var(--ui-text) 68%, white 32%);
}

.dashboard-kpi-card :deep(.ui-kpi-value) {
    font-size: clamp(1.7rem, 1.8vw, 2rem);
    font-weight: 800;
    color: var(--ui-text);
}

.dashboard-kpi-card :deep(.ui-kpi-icon) {
    width: auto;
    height: auto;
    border: 0;
    background: transparent;
}

.dashboard-kpi-card :deep(.ui-kpi-footer) {
    margin-top: 0.75rem;
    padding-top: 0.65rem;
    border-top: 1px solid color-mix(in srgb, var(--dashboard-card-tone) 10%, white);
}

.dashboard-kpi-card :deep(.ui-kpi-link) {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-weight: 700;
    text-decoration: none;
    color: color-mix(in srgb, var(--dashboard-card-tone) 76%, var(--ui-text));
}

.dashboard-kpi-card :deep(.ui-kpi-link)::after {
    content: "›";
    font-size: 1rem;
}

.dashboard-kpi-orb {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 3rem;
    height: 3rem;
    border-radius: 1rem;
    border: 1px solid color-mix(in srgb, var(--orb-tone) 16%, white);
    background:
        linear-gradient(180deg, color-mix(in srgb, var(--orb-tone) 8%, white), rgba(255, 255, 255, 0.98));
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9);
    color: var(--orb-tone);
}

.dashboard-kpi-orb svg {
    width: 1.35rem;
    height: 1.35rem;
    color: currentColor !important;
}

.dashboard-kpi-orb--primary {
    --orb-tone: var(--ui-primary);
}

.dashboard-kpi-orb--secondary {
    --orb-tone: var(--ui-secondary);
}

.dashboard-kpi-orb--accent {
    --orb-tone: var(--ui-accent);
}

.dashboard-kpi-orb--success {
    --orb-tone: var(--ui-success);
}

.dashboard-chip-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.65rem;
    justify-content: flex-end;
}

.dashboard-chip {
    --dashboard-chip-tone: var(--ui-primary);
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    min-height: 2.15rem;
    padding: 0.42rem 0.78rem;
    border-radius: 999px;
    border: 1px solid color-mix(in srgb, var(--dashboard-chip-tone) 10%, white);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), color-mix(in srgb, var(--dashboard-chip-tone) 2%, white));
    color: color-mix(in srgb, var(--ui-text) 88%, var(--dashboard-chip-tone) 12%);
    font-size: 0.78rem;
    font-weight: 700;
    box-shadow: 0 10px 18px -20px rgba(15, 23, 42, 0.14);
}

.dashboard-chip__dot {
    width: 0.48rem;
    height: 0.48rem;
    border-radius: 999px;
    background: var(--dashboard-chip-tone);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--dashboard-chip-tone) 8%, transparent);
}

.dashboard-chip--primary {
    --dashboard-chip-tone: var(--ui-primary);
}

.dashboard-chip--secondary {
    --dashboard-chip-tone: var(--ui-secondary);
}

.dashboard-chip--accent {
    --dashboard-chip-tone: var(--ui-accent);
}

.dashboard-chip--warning {
    --dashboard-chip-tone: var(--ui-warning);
}

.dashboard-panel {
    --dashboard-panel-tone: var(--ui-primary);
}

.dashboard-panel--secondary {
    --dashboard-panel-tone: var(--ui-secondary);
}

.dashboard-panel--accent {
    --dashboard-panel-tone: var(--ui-accent);
}

.dashboard-panel--success {
    --dashboard-panel-tone: var(--ui-success);
}

.dashboard-panel :deep(.ui-card) {
    border-radius: 1.45rem;
    border-color: color-mix(in srgb, var(--dashboard-panel-tone) 9%, white);
    background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.99), color-mix(in srgb, var(--dashboard-panel-tone) 2%, white)) !important;
    box-shadow: 0 18px 40px -34px rgba(15, 23, 42, 0.14);
}

.dashboard-panel :deep(.ui-card::before) {
    content: "";
    position: absolute;
    inset: 0;
    pointer-events: none;
    background: linear-gradient(135deg, color-mix(in srgb, var(--dashboard-panel-tone) 3%, transparent), transparent 54%);
}

.dashboard-panel :deep(.ui-section-head) {
    padding: 1rem 1rem 0.15rem;
}

.dashboard-panel :deep(.ui-section-head-row) {
    align-items: flex-start;
    gap: 1rem;
}

.dashboard-panel-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.dashboard-panel :deep(.ui-section-icon) {
    width: 3rem;
    height: 3rem;
    border-radius: 1rem;
    border: 1px solid color-mix(in srgb, var(--dashboard-panel-tone) 12%, white);
    background: linear-gradient(180deg, color-mix(in srgb, var(--dashboard-panel-tone) 7%, white), rgba(255, 255, 255, 0.99));
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.92);
    color: color-mix(in srgb, var(--dashboard-panel-tone) 88%, var(--ui-text));
}

.dashboard-panel :deep(.ui-section-title) {
    font-size: 1.2rem;
    font-weight: 800;
    color: var(--ui-text);
    letter-spacing: -0.025em;
}

.dashboard-panel :deep(.ui-section-subtitle) {
    color: var(--ui-muted);
    font-size: 0.9rem;
}

.dashboard-panel :deep(.ui-section-body) {
    padding: 0.9rem 1rem 1rem;
}

.dashboard-panel--conversation :deep(.ui-card) {
    display: flex;
    min-height: 20.5rem;
    height: 100%;
    flex-direction: column;
}

.dashboard-panel--conversation-filled :deep(.ui-card) {
    min-height: 0;
}

.dashboard-panel--conversation :deep(.ui-section-body) {
    display: flex;
    flex: 1;
    flex-direction: column;
}

.dashboard-panel--activity :deep(.ui-card) {
    display: flex;
    min-height: 25.25rem;
    height: 100%;
    flex-direction: column;
}

.dashboard-panel--activity :deep(.ui-section-body) {
    display: flex;
    flex: 1;
    flex-direction: column;
}

.dashboard-panel--activity :deep(.ui-section-body > div) {
    flex: 1;
    min-height: 25.25rem;
}

.dashboard-panel--campaigns :deep(.ui-card) {
    display: flex;
    min-height: 20.25rem;
    height: 100%;
    flex-direction: column;
}

.dashboard-panel--campaigns-filled :deep(.ui-card) {
    min-height: 0;
}

.dashboard-panel--campaigns :deep(.ui-section-body) {
    display: flex;
    flex: 1;
    flex-direction: column;
}

.dashboard-panel--campaigns :deep(.ui-section-body > div) {
    flex: 1;
}

.dashboard-panel--workspace :deep(.ui-card) {
    display: flex;
    min-height: 12.5rem;
    height: 100%;
    flex-direction: column;
}

.dashboard-panel--workspace :deep(.ui-section-body) {
    display: flex;
    flex: 1;
    flex-direction: column;
}

.dashboard-panel--workspace :deep(.ui-section-body > div) {
    flex: 1;
}

.dashboard-insight-grid {
    display: grid;
    gap: 1.5rem;
}

@media (min-width: 1536px) {
    .dashboard-insight-grid {
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    }
}

.dashboard-panel--updates {
    --dashboard-panel-tone: var(--ui-primary);
}

.dashboard-panel--team {
    --dashboard-panel-tone: var(--ui-success);
}

.dashboard-panel--team-warning {
    --dashboard-panel-tone: var(--ui-warning);
}

.dashboard-panel--updates :deep(.ui-card),
.dashboard-panel--team :deep(.ui-card) {
    display: flex;
    min-height: 18rem;
    height: 100%;
    flex-direction: column;
}

.dashboard-panel--updates :deep(.ui-section-body),
.dashboard-panel--team :deep(.ui-section-body) {
    display: flex;
    flex: 1;
    flex-direction: column;
}

.dashboard-update-card {
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 1rem;
}

.dashboard-update-feed,
.dashboard-update-skeleton-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.dashboard-update-item {
    position: relative;
    display: flex;
    width: 100%;
    align-items: flex-start;
    gap: 0.85rem;
    padding: 0.85rem;
    border-radius: 1.1rem;
    border: 1px solid color-mix(in srgb, var(--ui-primary) 9%, white);
    background:
        radial-gradient(circle at top right, color-mix(in srgb, var(--ui-primary) 6%, transparent), transparent 48%),
        linear-gradient(180deg, rgba(255, 255, 255, 0.99), color-mix(in srgb, var(--ui-primary) 2%, white));
    text-align: start;
    box-shadow: 0 14px 26px -26px rgba(15, 23, 42, 0.16);
    transition: transform 180ms ease, border-color 180ms ease, box-shadow 180ms ease;
}

.dashboard-update-item:hover {
    transform: translateY(-1px);
    border-color: color-mix(in srgb, var(--ui-primary) 18%, white);
    box-shadow: 0 20px 32px -26px rgba(15, 23, 42, 0.2);
}

.dashboard-update-item--unread {
    border-color: color-mix(in srgb, var(--ui-primary) 24%, white);
    background:
        radial-gradient(circle at top right, color-mix(in srgb, var(--ui-primary) 11%, transparent), transparent 52%),
        linear-gradient(180deg, rgba(255, 255, 255, 0.99), color-mix(in srgb, var(--ui-primary) 5%, white));
}

.dashboard-update-copy {
    display: flex;
    min-width: 0;
    flex: 1;
    flex-direction: column;
    gap: 0.22rem;
}

.dashboard-update-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.45rem;
    color: color-mix(in srgb, var(--ui-primary) 54%, var(--ui-muted));
    font-size: 0.72rem;
    font-weight: 800;
}

.dashboard-update-title {
    color: var(--ui-text);
    font-size: 0.9rem;
    font-weight: 800;
    line-height: 1.45;
}

.dashboard-update-body {
    display: -webkit-box;
    overflow: hidden;
    color: var(--ui-muted);
    font-size: 0.8rem;
    line-height: 1.55;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.dashboard-update-dot {
    margin-top: 0.45rem;
    width: 0.55rem;
    height: 0.55rem;
    flex-shrink: 0;
    border-radius: 999px;
    background: var(--ui-primary);
    box-shadow: 0 0 0 5px color-mix(in srgb, var(--ui-primary) 10%, transparent);
}

.dashboard-update-skeleton {
    min-height: 4.3rem;
    border-radius: 1.1rem;
    background:
        linear-gradient(90deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.72), rgba(255, 255, 255, 0)),
        color-mix(in srgb, var(--ui-primary) 6%, white);
    background-size: 220% 100%, 100% 100%;
    animation: dashboardShimmer 1.2s ease-in-out infinite;
}

.dashboard-update-action {
    margin-top: auto;
    align-self: flex-start;
}

.dashboard-empty-state--compact {
    min-height: 8rem;
}

.dashboard-team-card {
    display: flex;
    flex: 1;
    align-items: center;
    gap: 1rem;
    min-height: 12rem;
    padding: 1rem;
    border-radius: 1.25rem;
    border: 1px solid color-mix(in srgb, var(--dashboard-panel-tone) 10%, white);
    background:
        radial-gradient(circle at top left, color-mix(in srgb, var(--dashboard-panel-tone) 12%, transparent), transparent 54%),
        linear-gradient(180deg, rgba(255, 255, 255, 0.99), color-mix(in srgb, var(--dashboard-panel-tone) 3%, white));
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.92);
}

.dashboard-team-avatars {
    display: flex;
    min-width: 7rem;
    align-items: center;
    justify-content: center;
    isolation: isolate;
}

.dashboard-team-avatars span {
    display: inline-flex;
    width: 3.05rem;
    height: 3.05rem;
    align-items: center;
    justify-content: center;
    margin-inline-start: -0.65rem;
    border-radius: 999px;
    border: 3px solid rgba(255, 255, 255, 0.96);
    background: linear-gradient(135deg, color-mix(in srgb, var(--dashboard-panel-tone) 74%, var(--ui-primary)), color-mix(in srgb, var(--ui-secondary) 36%, var(--dashboard-panel-tone)));
    color: white;
    font-size: 0.92rem;
    font-weight: 900;
    box-shadow: 0 16px 24px -18px color-mix(in srgb, var(--dashboard-panel-tone) 34%, rgba(15, 23, 42, 0.18));
}

.dashboard-team-avatars span:first-child {
    margin-inline-start: 0;
}

.dashboard-team-copy {
    min-width: 0;
    flex: 1;
}

.dashboard-team-badge {
    display: inline-flex;
    align-items: center;
    margin-bottom: 0.65rem;
    padding: 0.28rem 0.6rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 800;
}

.dashboard-team-badge--success {
    color: color-mix(in srgb, var(--ui-success) 68%, var(--ui-text));
    background: color-mix(in srgb, var(--ui-success) 8%, white);
}

.dashboard-team-badge--warning {
    color: color-mix(in srgb, var(--ui-warning) 62%, var(--ui-text));
    background: color-mix(in srgb, var(--ui-warning) 9%, white);
}

.dashboard-team-copy h3 {
    color: var(--ui-text);
    font-size: 1rem;
    font-weight: 850;
    line-height: 1.45;
}

.dashboard-team-copy p {
    margin-top: 0.35rem;
    color: var(--ui-muted);
    font-size: 0.85rem;
    line-height: 1.65;
}

@keyframes dashboardShimmer {
    0% {
        background-position: 120% 0, 0 0;
    }

    100% {
        background-position: -120% 0, 0 0;
    }
}

.dashboard-thread-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.9rem;
    padding: 0.85rem;
    border-radius: 1.1rem;
    border: 1px solid color-mix(in srgb, var(--ui-primary) 9%, white);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), color-mix(in srgb, var(--ui-primary) 2%, white));
    box-shadow: 0 12px 24px -24px rgba(15, 23, 42, 0.12);
    transition: transform 180ms ease, box-shadow 180ms ease, border-color 180ms ease;
}

.dashboard-thread-row:hover {
    transform: translateY(-1px);
    border-color: color-mix(in srgb, var(--ui-primary) 16%, white);
    box-shadow: 0 18px 30px -24px rgba(15, 23, 42, 0.16);
}

.dashboard-scroll-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.dashboard-scroll-list--conversations {
    min-height: 18rem;
}

.dashboard-scroll-list--conversations-filled {
    min-height: 0;
}

.dashboard-scroll-list--campaigns {
    min-height: 11.5rem;
}

.dashboard-scroll-list--campaigns-filled {
    min-height: 0;
}

@media (min-width: 1280px) {
    .dashboard-scroll-list--conversations {
        max-height: 19.5rem;
        overflow-y: auto;
        padding-inline-end: 0.25rem;
    }

    .dashboard-scroll-list--campaigns {
        max-height: 12.75rem;
        overflow-y: auto;
        padding-inline-end: 0.25rem;
    }
}

.dashboard-avatar {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.55rem;
    height: 2.55rem;
    flex-shrink: 0;
    overflow: hidden;
    border-radius: 0.88rem;
    border: 1px solid color-mix(in srgb, var(--ui-primary) 10%, white);
    background: linear-gradient(180deg, color-mix(in srgb, var(--ui-primary) 5%, white), rgba(255, 255, 255, 0.99));
    color: color-mix(in srgb, var(--ui-primary) 52%, var(--ui-text));
    font-size: 0.88rem;
    font-weight: 700;
}

.dashboard-unread-badge {
    display: inline-flex;
    min-width: 1.85rem;
    align-items: center;
    justify-content: center;
    padding: 0.18rem 0.52rem;
    border-radius: 999px;
    background: linear-gradient(135deg, color-mix(in srgb, var(--ui-primary) 82%, #0b1425), color-mix(in srgb, var(--ui-secondary) 14%, var(--ui-primary)));
    color: white;
    font-size: 0.72rem;
    font-weight: 800;
}

.dashboard-soft-badge {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    border: 1px solid color-mix(in srgb, var(--badge-tone) 10%, white);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), color-mix(in srgb, var(--badge-tone) 4%, white));
    padding: 0.3rem 0.62rem;
    font-size: 0.68rem;
    font-weight: 700;
    color: color-mix(in srgb, var(--badge-tone) 62%, var(--ui-text));
}

.dashboard-soft-badge--success {
    --badge-tone: var(--ui-success);
}

.dashboard-soft-badge--warning {
    --badge-tone: var(--ui-warning);
}

.dashboard-soft-badge--secondary {
    --badge-tone: var(--ui-secondary);
}

.dashboard-soft-badge--neutral {
    --badge-tone: color-mix(in srgb, var(--ui-muted) 70%, white);
}

.dashboard-agent-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border-radius: 999px;
    border: 1px solid color-mix(in srgb, var(--ui-secondary) 8%, white);
    background: rgba(255, 255, 255, 0.94);
    padding: 0.3rem 0.45rem;
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--ui-muted);
}

.dashboard-agent-badge__initials {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 1.3rem;
    height: 1.3rem;
    border-radius: 999px;
    background: linear-gradient(135deg, color-mix(in srgb, var(--ui-primary) 74%, #0b1425), color-mix(in srgb, var(--ui-secondary) 12%, var(--ui-primary)));
    color: white;
    font-size: 0.62rem;
    font-weight: 800;
}

.dashboard-arrow-chip {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.3rem;
    height: 2.3rem;
    border-radius: 0.9rem;
    border: 1px solid color-mix(in srgb, var(--ui-primary) 10%, white);
    background: rgba(255, 255, 255, 0.97);
    color: var(--ui-muted);
    transition: color 180ms ease, transform 180ms ease, border-color 180ms ease;
}

.dashboard-thread-row:hover .dashboard-arrow-chip {
    color: var(--ui-primary);
    transform: translateX(-2px);
    border-color: color-mix(in srgb, var(--ui-primary) 18%, white);
}

.dashboard-empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1.5rem 0.95rem;
    border-radius: 1.3rem;
    border: 1px dashed color-mix(in srgb, var(--ui-primary) 14%, white);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), color-mix(in srgb, var(--ui-secondary) 2%, white));
    text-align: center;
}

.dashboard-empty-state--tall {
    min-height: 22rem;
}

.dashboard-empty-state--medium {
    min-height: 14rem;
}

.dashboard-empty-state--conversation {
    min-height: 15rem;
    margin-top: 0.35rem;
}

.dashboard-empty-state--queue {
    min-height: 11rem;
}

.dashboard-empty-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 3rem;
    height: 3rem;
    margin-inline: auto;
    border-radius: 1rem;
    border: 1px solid color-mix(in srgb, var(--ui-primary) 10%, white);
    background: rgba(255, 255, 255, 0.98);
    color: var(--ui-primary);
}

.dashboard-mini-kpi {
    --dashboard-mini-tone: var(--ui-primary);
    padding: 0.85rem;
    border-radius: 1.15rem;
    border: 1px solid color-mix(in srgb, var(--dashboard-mini-tone) 10%, white);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), color-mix(in srgb, var(--dashboard-mini-tone) 2%, white));
}

.dashboard-mini-kpi__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.7rem;
}

.dashboard-mini-kpi__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.2rem;
    height: 2.2rem;
    flex-shrink: 0;
    border-radius: 0.82rem;
    border: 1px solid color-mix(in srgb, var(--dashboard-mini-tone) 12%, white);
    background: linear-gradient(180deg, color-mix(in srgb, var(--dashboard-mini-tone) 8%, white), rgba(255, 255, 255, 0.98));
    color: color-mix(in srgb, var(--dashboard-mini-tone) 90%, var(--ui-text));
}

.dashboard-mini-kpi--warning {
    --dashboard-mini-tone: var(--ui-warning);
}

.dashboard-mini-kpi--secondary {
    --dashboard-mini-tone: var(--ui-secondary);
}

.dashboard-mini-kpi--accent {
    --dashboard-mini-tone: var(--ui-accent);
}

.dashboard-mini-kpi--primary {
    --dashboard-mini-tone: var(--ui-primary);
}

.dashboard-mini-kpi__label {
    font-size: 0.64rem;
    font-weight: 800;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: color-mix(in srgb, var(--dashboard-mini-tone) 34%, var(--ui-muted));
}

.dashboard-mini-kpi__value {
    margin-top: 0.35rem;
    font-size: 1.55rem;
    font-weight: 800;
    color: var(--ui-text);
}

.dashboard-queue-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.85rem;
    border-radius: 1.15rem;
    border: 1px solid color-mix(in srgb, var(--ui-secondary) 8%, white);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), color-mix(in srgb, var(--ui-secondary) 2%, white));
}

.dashboard-inline-link {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    color: color-mix(in srgb, var(--ui-text) 82%, var(--ui-primary) 18%);
    font-size: 0.88rem;
    font-weight: 700;
    transition: color 180ms ease, transform 180ms ease;
}

.dashboard-inline-link:hover {
    color: var(--ui-primary);
    transform: translateX(-1px);
}

.dashboard-subscription-panel {
    position: relative;
    overflow: hidden;
    padding: 1.05rem 1.1rem;
    border-radius: 1.45rem;
    border: 1px solid color-mix(in srgb, var(--dashboard-subscription-tone) 10%, white);
    background:
        linear-gradient(140deg, color-mix(in srgb, #fff 97%, var(--dashboard-subscription-tone) 3%), color-mix(in srgb, #fff 95%, var(--dashboard-subscription-accent) 5%));
    box-shadow: 0 18px 34px -28px color-mix(in srgb, var(--dashboard-subscription-tone) 10%, rgba(15, 23, 42, 0.16));
}

.dashboard-subscription-panel--compact {
    min-height: 10.5rem;
}

.dashboard-subscription-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
}

.dashboard-subscription-copy {
    position: relative;
    z-index: 1;
    margin-top: 0.95rem;
}

.dashboard-subscription-glow {
    position: absolute;
    border-radius: 999px;
    filter: blur(50px);
    opacity: 0.12;
    pointer-events: none;
}

.dashboard-subscription-glow--start {
    inset-inline-start: -3rem;
    top: -1rem;
    width: 8rem;
    height: 8rem;
    background: color-mix(in srgb, var(--dashboard-subscription-tone) 20%, white);
}

.dashboard-subscription-glow--end {
    inset-inline-end: -2rem;
    bottom: -2.5rem;
    width: 7rem;
    height: 7rem;
    background: color-mix(in srgb, var(--dashboard-subscription-accent) 16%, white);
}

.dashboard-subscription-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.9rem;
    height: 2.9rem;
    flex-shrink: 0;
    border-radius: 0.95rem;
    border: 1px solid color-mix(in srgb, var(--dashboard-subscription-tone) 10%, white);
    background: rgba(255, 255, 255, 0.95);
    color: var(--dashboard-subscription-tone);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.92);
}

.dashboard-subscription-pill,
.dashboard-subscription-meta {
    display: inline-flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.45rem;
    border-radius: 999px;
    border: 1px solid color-mix(in srgb, var(--dashboard-subscription-tone) 8%, white);
    background: rgba(255, 255, 255, 0.9);
    padding: 0.38rem 0.72rem;
    font-size: 0.72rem;
    font-weight: 700;
    color: color-mix(in srgb, var(--ui-text) 90%, var(--dashboard-subscription-tone) 10%);
}

.dashboard-chart-frame {
    border-radius: 1.25rem;
    border: 1px solid color-mix(in srgb, var(--ui-accent) 8%, white);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.995), color-mix(in srgb, var(--ui-accent) 2%, white));
    padding: 0.75rem;
}

.dashboard-status-card {
    --dashboard-status-tone: var(--ui-primary);
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.9rem;
    border-radius: 1.2rem;
    border: 1px solid color-mix(in srgb, var(--dashboard-status-tone) 10%, white);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), color-mix(in srgb, var(--dashboard-status-tone) 2%, white));
}

.dashboard-status-card--primary {
    --dashboard-status-tone: var(--ui-primary);
}

.dashboard-status-card--warning {
    --dashboard-status-tone: var(--ui-warning);
}

.dashboard-status-card--success {
    --dashboard-status-tone: var(--ui-success);
}

.dashboard-status-card__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.55rem;
    height: 2.55rem;
    flex-shrink: 0;
    border-radius: 0.9rem;
    border: 1px solid color-mix(in srgb, var(--dashboard-status-tone) 10%, white);
    background: rgba(255, 255, 255, 0.98);
    color: var(--dashboard-status-tone);
}

.dashboard-inline-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    min-height: 2.6rem;
    padding: 0.6rem 0.9rem;
    border-radius: 0.92rem;
    border: 1px solid color-mix(in srgb, var(--ui-primary) 10%, white);
    font-size: 0.84rem;
    font-weight: 700;
    transition: transform 180ms ease, box-shadow 180ms ease, border-color 180ms ease, background 180ms ease, color 180ms ease;
}

.dashboard-inline-action:hover {
    transform: translateY(-1px);
}

.dashboard-inline-action--primary {
    color: white;
    border-color: transparent;
    background: linear-gradient(135deg, color-mix(in srgb, var(--ui-primary) 88%, #0b1425), color-mix(in srgb, var(--ui-secondary) 16%, var(--ui-primary)));
    box-shadow: 0 14px 22px -20px color-mix(in srgb, var(--ui-primary) 20%, transparent);
}

.dashboard-inline-action--danger {
    color: white;
    border-color: transparent;
    background: linear-gradient(135deg, color-mix(in srgb, var(--ui-danger) 82%, #3f0610), color-mix(in srgb, var(--ui-accent) 12%, var(--ui-danger)));
    box-shadow: 0 14px 22px -20px color-mix(in srgb, var(--ui-danger) 18%, transparent);
}

.dashboard-inline-action--soft {
    color: color-mix(in srgb, var(--ui-text) 84%, var(--ui-primary) 16%);
    background: rgba(255, 255, 255, 0.98);
}

.dashboard-inline-action--primary-soft {
    color: color-mix(in srgb, var(--ui-primary) 60%, var(--ui-text));
    border-color: color-mix(in srgb, var(--ui-primary) 10%, white);
    background: color-mix(in srgb, var(--ui-primary) 4%, white);
}

.dashboard-inline-action--warning {
    color: color-mix(in srgb, var(--ui-warning) 54%, var(--ui-text));
    border-color: color-mix(in srgb, var(--ui-warning) 10%, white);
    background: color-mix(in srgb, var(--ui-warning) 4%, white);
}

.dashboard-inline-action--success {
    color: color-mix(in srgb, var(--ui-success) 54%, var(--ui-text));
    border-color: color-mix(in srgb, var(--ui-success) 10%, white);
    background: color-mix(in srgb, var(--ui-success) 4%, white);
}

.dashboard-inline-action--disabled {
    cursor: not-allowed;
    color: color-mix(in srgb, var(--ui-muted) 80%, white);
    background: rgba(241, 245, 249, 0.94);
    border-color: var(--ui-border);
    opacity: 0.72;
}

.dashboard-text-action {
    font-size: 0.84rem;
    font-weight: 700;
    color: var(--ui-muted);
    text-decoration: underline;
    text-underline-offset: 3px;
    transition: color 180ms ease;
}

.dashboard-text-action:hover {
    color: var(--ui-primary);
}

.dashboard-shell {
    font-size: 0.92rem;
}

.dashboard-shell .max-w-\[1680px\] {
    max-width: 1600px;
}

.dashboard-hero {
    padding: clamp(1rem, 1.35vw, 1.25rem);
    border-radius: 1.3rem;
    box-shadow: 0 22px 54px -48px rgba(15, 23, 42, 0.3);
}

.dashboard-hero > .relative {
    display: flex;
    flex-direction: column;
    gap: 1.3rem;
}

.dashboard-hero > .relative > * + * {
    margin-top: 0 !important;
}

.dashboard-hero-eyebrow {
    display: none;
}

.dashboard-hero-title {
    font-size: clamp(1.65rem, 2.05vw, 2.1rem);
    letter-spacing: -0.025em;
}

.dashboard-hero-subtitle {
    font-size: 0.92rem;
    line-height: 1.55;
}

.dashboard-action-cluster {
    gap: 0.6rem;
}

.dashboard-action {
    min-height: 2.75rem;
    padding: 0.58rem 0.9rem;
    border-radius: 0.86rem;
    font-size: 0.84rem;
}

.dashboard-action--icon-only {
    width: 2.75rem;
    padding-inline: 0;
}

.dashboard-hero-actions :deep(button[aria-label]) {
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 0.86rem;
    border-color: color-mix(in srgb, var(--ui-primary) 10%, white);
    color: color-mix(in srgb, var(--ui-text) 84%, var(--ui-primary) 16%);
    box-shadow: 0 12px 20px -22px rgba(15, 23, 42, 0.16);
}

.dashboard-kpi-card :deep(.ui-kpi) {
    min-height: 7.25rem;
    padding: 0.88rem 0.95rem 0.76rem;
    border-radius: 1.05rem;
}

.dashboard-kpi-card :deep(.ui-kpi-title) {
    font-size: 0.84rem;
}

.dashboard-kpi-card :deep(.ui-kpi-value) {
    font-size: clamp(1.55rem, 1.55vw, 1.8rem);
}

.dashboard-kpi-orb {
    width: 2.65rem;
    height: 2.65rem;
    border-radius: 0.86rem;
}

.dashboard-chip-row {
    display: none;
}

.dashboard-panel :deep(.ui-card) {
    border-radius: 1.2rem;
    box-shadow: 0 18px 38px -34px rgba(15, 23, 42, 0.16);
}

.dashboard-panel :deep(.ui-section-head) {
    padding: 0.95rem 1rem 0.2rem;
}

.dashboard-panel :deep(.ui-section-title) {
    font-size: 1.04rem;
    font-weight: 800;
    letter-spacing: -0.015em;
}

.dashboard-panel :deep(.ui-section-subtitle) {
    font-size: 0.82rem;
}

.dashboard-panel :deep(.ui-section-icon) {
    width: 2.55rem;
    height: 2.55rem;
    border-radius: 0.86rem;
}

.dashboard-panel :deep(.ui-section-body) {
    padding: 0.78rem 1rem 1rem;
}

.dashboard-panel--activity :deep(.ui-section-icon) {
    width: auto;
    height: auto;
    border-radius: 0.76rem;
    background: rgba(255, 255, 255, 0.98);
}

.dashboard-period-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.42rem;
    min-height: 2.15rem;
    padding: 0 0.7rem;
    color: color-mix(in srgb, var(--ui-text) 78%, var(--ui-primary) 22%);
    font-size: 0.78rem;
    font-weight: 700;
}

.dashboard-activity-summary,
.dashboard-activity-actions {
    display: none !important;
}

.dashboard-panel--activity :deep(.ui-card),
.dashboard-panel--conversation :deep(.ui-card),
.dashboard-panel--updates :deep(.ui-card) {
    min-height: 18.9rem;
}

.dashboard-panel--activity :deep(.ui-section-body > div) {
    min-height: 0;
}

.dashboard-chart-frame {
    border: 0;
    background: transparent;
    padding: 0.1rem 0 0;
}

.dashboard-thread-row,
.dashboard-update-item {
    border-width: 0 0 1px;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
}

.dashboard-thread-row {
    padding: 0.58rem 0;
}

.dashboard-thread-row:hover,
.dashboard-update-item:hover {
    transform: none;
    box-shadow: none;
    background: color-mix(in srgb, var(--ui-primary) 2.5%, transparent);
}

.dashboard-avatar {
    width: 2.15rem;
    height: 2.15rem;
    border-radius: 0.72rem;
}

.dashboard-arrow-chip {
    width: 2rem;
    height: 2rem;
    border-radius: 0.72rem;
}

.dashboard-update-card {
    gap: 0.55rem;
}

.dashboard-update-feed,
.dashboard-update-skeleton-list {
    gap: 0;
}

.dashboard-update-item {
    padding: 0.62rem 0;
}

.dashboard-update-title {
    font-size: 0.82rem;
}

.dashboard-update-body {
    font-size: 0.74rem;
}

.dashboard-update-action {
    min-height: 2.2rem;
    padding: 0.48rem 0.75rem;
}

.dashboard-panel--workspace :deep(.ui-card),
.dashboard-panel--team :deep(.ui-card),
.dashboard-subscription-panel {
    min-height: 12.25rem;
}

.dashboard-team-card {
    min-height: 0;
    align-items: stretch;
    flex-direction: column;
    gap: 0.9rem;
    padding: 0;
    border: 0;
    background: transparent;
    box-shadow: none;
}

.dashboard-team-metrics {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.8rem;
}

.dashboard-team-metric {
    display: flex;
    min-height: 4.25rem;
    flex-direction: column;
    justify-content: center;
    gap: 0.18rem;
    border-radius: 1rem;
    border: 1px solid color-mix(in srgb, var(--team-metric-tone) 10%, white);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), color-mix(in srgb, var(--team-metric-tone) 3%, white));
    padding: 0.72rem 0.8rem;
}

.dashboard-team-metric--count {
    --team-metric-tone: var(--ui-success);
}

.dashboard-team-metric--invite {
    --team-metric-tone: var(--ui-primary);
    border-style: dashed;
    color: inherit;
}

.dashboard-team-metric__number {
    display: inline-flex;
    width: 2.25rem;
    height: 2.25rem;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: color-mix(in srgb, var(--ui-success) 9%, white);
    color: color-mix(in srgb, var(--ui-success) 72%, var(--ui-text));
    font-size: 1rem;
    font-weight: 850;
}

.dashboard-team-metric__icon {
    display: inline-flex;
    width: 2.25rem;
    height: 2.25rem;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: color-mix(in srgb, var(--ui-primary) 8%, white);
    color: var(--ui-primary);
}

.dashboard-team-metric__title {
    color: var(--ui-text);
    font-size: 0.82rem;
    font-weight: 800;
}

.dashboard-team-metric__note {
    color: var(--ui-muted);
    font-size: 0.74rem;
    line-height: 1.45;
}

.dashboard-team-footer {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

.dashboard-subscription-panel {
    border-radius: 1.2rem;
    padding: 1rem;
}

.dashboard-subscription-panel--compact {
    min-height: 12.25rem;
}

.dashboard-subscription-copy h2 {
    font-size: 1rem !important;
}

.dashboard-subscription-copy p {
    font-size: 0.8rem !important;
    line-height: 1.6 !important;
}

@media (min-width: 1280px) {
    .dashboard-main-grid {
        grid-template-columns: minmax(0, 1.12fr) minmax(0, 0.84fr) minmax(0, 0.98fr);
        gap: 1.25rem;
        direction: ltr;
    }

    .dashboard-main-column,
    .dashboard-insight-grid {
        display: contents;
    }

    .dashboard-panel,
    .dashboard-subscription-panel {
        min-width: 0;
        direction: rtl;
    }

    .dashboard-panel--activity {
        grid-column: 1;
        grid-row: 1;
    }

    .dashboard-panel--conversation {
        grid-column: 2;
        grid-row: 1;
    }

    .dashboard-panel--updates {
        grid-column: 3;
        grid-row: 1;
    }

    .dashboard-panel--team {
        grid-column: 1;
        grid-row: 2;
    }

    .dashboard-panel--workspace {
        grid-column: 2;
        grid-row: 2;
    }

    .dashboard-subscription-panel {
        grid-column: 3;
        grid-row: 2;
    }

    .dashboard-panel--campaigns {
        display: none;
    }
}

.dashboard-shell {
    padding-top: 0.9rem;
    background:
        radial-gradient(circle at 16% 8%, rgba(219, 234, 254, 0.42), transparent 28%),
        radial-gradient(circle at 86% 18%, rgba(230, 238, 255, 0.54), transparent 30%);
}

.dashboard-shell .max-w-\[1680px\] {
    position: relative;
    gap: 1.25rem;
    padding: clamp(1rem, 1.4vw, 1.35rem);
    border: 1px solid rgba(216, 226, 241, 0.86);
    border-radius: 1.35rem;
    background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(253, 254, 255, 0.94));
    box-shadow: 0 24px 72px -60px rgba(15, 23, 42, 0.42);
}

.dashboard-hero {
    padding: 0;
    border: 0;
    border-radius: 0;
    background: transparent !important;
    box-shadow: none;
}

.dashboard-hero::before,
.dashboard-hero::after,
.dashboard-hero-glow {
    display: none;
}

.dashboard-hero > .relative {
    gap: 1.55rem;
}

.dashboard-hero-head {
    align-items: flex-start;
    min-height: 3.75rem;
}

.dashboard-hero-title {
    font-size: clamp(1.75rem, 2vw, 2.15rem);
    font-weight: 850;
    line-height: 1.1;
}

.dashboard-hero-subtitle {
    margin-top: 0.16rem;
    color: #5f6f86;
    font-size: 0.9rem;
}

.dashboard-action {
    min-height: 2.68rem;
    border-color: #d9e3f2;
    border-radius: 0.78rem;
    background: #fff;
    box-shadow: 0 12px 24px -26px rgba(15, 23, 42, 0.2);
}

.dashboard-action--primary {
    background: linear-gradient(135deg, #315cff, #2f62f5);
    box-shadow: 0 16px 28px -22px rgba(49, 92, 255, 0.56);
}

.dashboard-action--soft,
.dashboard-action--ghost {
    color: #13213a;
    background: rgba(255, 255, 255, 0.96);
}

.dashboard-hero-actions :deep(button[aria-label]),
.dashboard-action--icon-only {
    width: 2.68rem;
    height: 2.68rem;
    border-radius: 0.78rem;
    background: #fff;
}

.dashboard-kpi-card :deep(.ui-kpi) {
    min-height: 8.15rem;
    border-color: color-mix(in srgb, var(--dashboard-card-tone) 12%, #d9e3f2);
    border-radius: 1rem;
    background: linear-gradient(180deg, #fff 0%, color-mix(in srgb, var(--dashboard-card-tone) 2.6%, #fff) 100%) !important;
    box-shadow: 0 14px 30px -28px rgba(15, 23, 42, 0.2);
}

.dashboard-kpi-card :deep(.ui-kpi-title) {
    color: #5d687b;
    font-size: 0.84rem;
}

.dashboard-kpi-card :deep(.ui-kpi-value) {
    color: #10192d;
    font-size: clamp(1.45rem, 1.55vw, 1.75rem);
}

.dashboard-kpi-card :deep(.ui-kpi-footer) {
    border-top-color: #e7edf6;
}

.dashboard-kpi-orb {
    width: 2.55rem;
    height: 2.55rem;
    border-radius: 0.8rem;
}

.dashboard-panel :deep(.ui-card),
.dashboard-subscription-panel {
    border-color: #dfe7f3;
    border-radius: 1rem;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.99), rgba(253, 254, 255, 0.96)) !important;
    box-shadow: 0 16px 36px -34px rgba(15, 23, 42, 0.22);
}

.dashboard-panel :deep(.ui-card::before) {
    opacity: 0.45;
}

.dashboard-panel :deep(.ui-section-title) {
    color: #121a2d;
    font-size: 1rem;
    font-weight: 820;
}

.dashboard-panel :deep(.ui-section-subtitle) {
    color: #718097;
    font-size: 0.8rem;
}

.dashboard-panel :deep(.ui-section-icon) {
    width: 2.45rem;
    height: 2.45rem;
    border-radius: 0.78rem;
}

.dashboard-chart-frame {
    border: 0;
    background: transparent;
    padding: 0;
}

.dashboard-panel--activity :deep(.ui-card),
.dashboard-panel--conversation :deep(.ui-card),
.dashboard-panel--updates :deep(.ui-card) {
    min-height: 19.2rem;
}

.dashboard-panel--workspace :deep(.ui-card),
.dashboard-panel--team :deep(.ui-card),
.dashboard-subscription-panel {
    min-height: 13.1rem;
}

.dashboard-thread-row,
.dashboard-update-item {
    border-bottom-color: #e7edf6;
}

.dashboard-update-action,
.dashboard-inline-action {
    min-height: 2.35rem;
    border-color: #dfe7f3;
    border-radius: 0.78rem;
}

.dashboard-team-metric {
    min-height: 4.45rem;
    border-color: color-mix(in srgb, var(--team-metric-tone) 10%, #dfe7f3);
    border-radius: 0.9rem;
}

.dashboard-subscription-panel {
    padding: 1rem;
}

.dashboard-subscription-panel--compact {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

@media (min-width: 1280px) {
    .dashboard-main-grid {
        grid-template-columns: minmax(0, 1.32fr) minmax(0, 0.96fr) minmax(0, 1fr);
        gap: 1.25rem;
    }

    .dashboard-panel--activity {
        grid-column: 1;
        grid-row: 1;
    }

    .dashboard-panel--conversation {
        grid-column: 2;
        grid-row: 1;
    }

    .dashboard-panel--updates {
        grid-column: 3;
        grid-row: 1;
    }

    .dashboard-panel--team {
        grid-column: 1;
        grid-row: 2;
    }

    .dashboard-panel--workspace {
        grid-column: 2;
        grid-row: 2;
    }

    .dashboard-subscription-panel {
        grid-column: 3;
        grid-row: 2;
    }
}

.dashboard-panel--team,
.dashboard-panel--workspace,
.dashboard-subscription-panel {
    --bottom-card-tone: var(--ui-primary);
    --bottom-card-accent: var(--ui-secondary);
    height: 100%;
}

.dashboard-panel--team {
    --bottom-card-tone: var(--ui-success);
    --bottom-card-accent: var(--ui-primary);
}

.dashboard-panel--workspace {
    --bottom-card-tone: var(--ui-success);
    --bottom-card-accent: var(--ui-accent);
}

.dashboard-panel--team :deep(.ui-card),
.dashboard-panel--workspace :deep(.ui-card),
.dashboard-subscription-panel {
    position: relative;
    overflow: hidden;
    height: 100%;
    min-height: 14rem;
    border-color: color-mix(in srgb, var(--bottom-card-tone) 14%, #dfe7f3) !important;
    background:
        radial-gradient(circle at 14% 18%, color-mix(in srgb, var(--bottom-card-tone) 12%, transparent), transparent 34%),
        radial-gradient(circle at 86% 10%, color-mix(in srgb, var(--bottom-card-accent) 10%, transparent), transparent 28%),
        linear-gradient(145deg, rgba(255, 255, 255, 0.995), color-mix(in srgb, var(--bottom-card-tone) 3%, white)) !important;
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.94),
        0 18px 42px -36px color-mix(in srgb, var(--bottom-card-tone) 34%, rgba(15, 23, 42, 0.24));
}

.dashboard-subscription-panel {
    --bottom-card-tone: var(--dashboard-subscription-tone, var(--ui-primary));
    --bottom-card-accent: var(--dashboard-subscription-accent, var(--ui-secondary));
}

.dashboard-panel--team :deep(.ui-card)::before,
.dashboard-panel--workspace :deep(.ui-card)::before,
.dashboard-subscription-panel::before {
    content: "";
    position: absolute;
    inset-inline-start: 1rem;
    inset-block-start: 0;
    width: min(7.5rem, 38%);
    height: 3px;
    border-radius: 999px;
    background: linear-gradient(90deg, var(--bottom-card-tone), color-mix(in srgb, var(--bottom-card-accent) 76%, white));
    opacity: 0.86;
}

.dashboard-panel--team :deep(.ui-card)::after,
.dashboard-panel--workspace :deep(.ui-card)::after,
.dashboard-subscription-panel::after {
    content: "";
    position: absolute;
    inset-inline-end: -2.8rem;
    inset-block-end: -3.2rem;
    width: 8.25rem;
    height: 8.25rem;
    border-radius: 999px;
    border: 1px solid color-mix(in srgb, var(--bottom-card-tone) 10%, transparent);
    background:
        linear-gradient(135deg, color-mix(in srgb, var(--bottom-card-tone) 9%, transparent), transparent),
        radial-gradient(circle, color-mix(in srgb, var(--bottom-card-accent) 10%, transparent), transparent 58%);
    pointer-events: none;
}

.dashboard-panel--team :deep(.ui-section-body),
.dashboard-panel--workspace :deep(.ui-section-body) {
    position: relative;
    z-index: 1;
    display: flex;
    flex: 1;
    flex-direction: column;
}

.dashboard-panel--team :deep(.ui-section-head),
.dashboard-panel--workspace :deep(.ui-section-head),
.dashboard-subscription-head,
.dashboard-subscription-copy {
    position: relative;
    z-index: 1;
}

.dashboard-team-card {
    flex: 1;
    justify-content: space-between;
}

.dashboard-team-metrics {
    flex: 1;
    align-items: stretch;
}

.dashboard-team-metric {
    position: relative;
    isolation: isolate;
    min-height: 5.35rem;
    overflow: hidden;
    border-color: color-mix(in srgb, var(--team-metric-tone) 14%, #dfe7f3);
    background:
        radial-gradient(circle at 18% 18%, color-mix(in srgb, var(--team-metric-tone) 11%, transparent), transparent 36%),
        linear-gradient(145deg, rgba(255, 255, 255, 0.99), color-mix(in srgb, var(--team-metric-tone) 4%, white));
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.96);
}

.dashboard-team-metric::after {
    content: "";
    position: absolute;
    inset-inline-end: 0.65rem;
    inset-block-start: 0.65rem;
    width: 2.35rem;
    height: 2.35rem;
    border-radius: 0.85rem;
    background: color-mix(in srgb, var(--team-metric-tone) 7%, white);
    opacity: 0.78;
    z-index: -1;
}

.dashboard-team-metric--invite {
    border-style: solid;
}

.dashboard-team-metric--invite:hover {
    transform: translateY(-1px);
    border-color: color-mix(in srgb, var(--ui-primary) 28%, #dfe7f3);
    box-shadow: 0 18px 26px -24px color-mix(in srgb, var(--ui-primary) 42%, rgba(15, 23, 42, 0.18));
}

.dashboard-team-metric__number,
.dashboard-team-metric__icon {
    width: 2.05rem;
    height: 2.05rem;
    box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--team-metric-tone, var(--ui-primary)) 10%, white);
}

.dashboard-team-footer {
    justify-content: space-between;
    min-height: 2.25rem;
    padding-top: 0.25rem;
}

.dashboard-panel--workspace :deep(.ui-section-body > .flex) {
    flex: 1;
    justify-content: space-between;
}

.dashboard-status-card {
    position: relative;
    isolation: isolate;
    min-height: 7.05rem;
    align-items: center;
    border-color: color-mix(in srgb, var(--dashboard-status-tone) 14%, #dfe7f3);
    background:
        radial-gradient(circle at 12% 18%, color-mix(in srgb, var(--dashboard-status-tone) 10%, transparent), transparent 34%),
        linear-gradient(145deg, rgba(255, 255, 255, 0.99), color-mix(in srgb, var(--dashboard-status-tone) 4%, white));
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.96);
}

.dashboard-status-card::after {
    content: "";
    position: absolute;
    inset-inline-end: 0.9rem;
    inset-block-start: 0.9rem;
    width: 2.65rem;
    height: 2.65rem;
    border-radius: 1rem;
    background: color-mix(in srgb, var(--dashboard-status-tone) 7%, white);
    z-index: -1;
}

.dashboard-status-card__icon {
    width: 2.35rem;
    height: 2.35rem;
    background: rgba(255, 255, 255, 0.92);
    box-shadow:
        inset 0 0 0 1px color-mix(in srgb, var(--dashboard-status-tone) 12%, white),
        0 12px 22px -18px color-mix(in srgb, var(--dashboard-status-tone) 42%, rgba(15, 23, 42, 0.22));
}

.dashboard-subscription-panel {
    padding: 1rem 1.05rem;
}

.dashboard-subscription-panel--compact {
    min-height: 14rem;
}

.dashboard-subscription-icon {
    width: 2.65rem;
    height: 2.65rem;
    border-radius: 0.95rem;
    background: rgba(255, 255, 255, 0.9);
    box-shadow:
        inset 0 0 0 1px color-mix(in srgb, var(--dashboard-subscription-tone) 12%, white),
        0 14px 26px -22px color-mix(in srgb, var(--dashboard-subscription-tone) 42%, rgba(15, 23, 42, 0.24));
}

.dashboard-subscription-icon svg {
    width: 1.35rem;
    height: 1.35rem;
}

.dashboard-subscription-copy {
    margin-top: auto;
    padding-top: 0.5rem;
}

.dashboard-subscription-copy h2 {
    font-size: 1rem !important;
}

.dashboard-subscription-copy p {
    max-width: 24rem;
}

.dashboard-subscription-pill,
.dashboard-subscription-meta {
    border-color: color-mix(in srgb, var(--dashboard-subscription-tone) 13%, #dfe7f3);
    background: rgba(255, 255, 255, 0.76);
}

/* Plan B: unified SaaS dashboard system, aligned with admin dashboard primitives. */
.dashboard-shell {
    --dash-radius: 1rem;
    --dash-radius-sm: 0.78rem;
    --dash-border: color-mix(in srgb, var(--ui-primary) 9%, var(--ui-border));
    --dash-border-soft: color-mix(in srgb, var(--ui-primary) 7%, #e7edf6);
    --dash-shadow: 0 14px 30px -28px rgba(15, 23, 42, 0.28);
    --dash-shadow-soft: 0 10px 22px -22px rgba(15, 23, 42, 0.22);
    --dash-surface: rgba(255, 255, 255, 0.985);
    --dash-surface-soft: color-mix(in srgb, #fff 96%, var(--ui-primary) 4%);
    background:
        radial-gradient(900px 360px at 4% 0%, color-mix(in srgb, var(--ui-primary) 8%, transparent), transparent 62%),
        radial-gradient(820px 320px at 98% 6%, color-mix(in srgb, var(--ui-secondary) 7%, transparent), transparent 58%);
    font-size: 0.9rem;
}

.dashboard-shell .max-w-\[1680px\] {
    max-width: 1600px;
    gap: 1rem;
    padding: clamp(1rem, 1.2vw, 1.25rem);
    border: 1px solid var(--dash-border-soft);
    border-radius: 1.25rem;
    background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.99), rgba(252, 254, 255, 0.96));
    box-shadow: 0 20px 58px -56px rgba(15, 23, 42, 0.45);
}

.dashboard-hero {
    overflow: visible;
    padding: 0;
    border: 0;
    border-radius: 0;
    background: transparent !important;
    box-shadow: none;
    backdrop-filter: none;
}

.dashboard-hero::before,
.dashboard-hero::after,
.dashboard-hero-glow {
    display: none;
}

.dashboard-hero > .relative {
    gap: 1.05rem;
}

.dashboard-hero-head {
    min-height: auto;
    align-items: center;
}

.dashboard-hero-copy {
    gap: 0.18rem;
}

.dashboard-hero-eyebrow {
    color: color-mix(in srgb, var(--ui-muted) 86%, var(--ui-primary));
    font-size: 0.82rem;
    font-weight: 700;
}

.dashboard-hero-title {
    color: var(--ui-text);
    font-size: clamp(1.62rem, 2vw, 2rem);
    font-weight: 850;
    letter-spacing: -0.025em;
    line-height: 1.12;
}

.dashboard-hero-subtitle {
    max-width: 30rem;
    color: var(--ui-muted);
    font-size: 0.86rem;
    line-height: 1.6;
}

.dashboard-action-cluster {
    gap: 0.58rem;
}

.dashboard-action {
    min-height: 2.5rem;
    padding: 0.56rem 0.88rem;
    border-radius: var(--dash-radius-sm);
    border-color: var(--dash-border);
    background: #fff;
    color: color-mix(in srgb, var(--ui-text) 88%, var(--ui-primary));
    box-shadow: var(--dash-shadow-soft);
    font-size: 0.84rem;
    font-weight: 750;
}

.dashboard-action--primary {
    border-color: transparent;
    background: linear-gradient(135deg, var(--ui-primary), color-mix(in srgb, var(--ui-primary) 76%, var(--ui-secondary)));
    color: #fff;
    box-shadow: 0 15px 24px -22px color-mix(in srgb, var(--ui-primary) 60%, rgba(15, 23, 42, 0.4));
}

.dashboard-action--soft,
.dashboard-action--ghost {
    background: #fff;
    color: color-mix(in srgb, var(--ui-text) 88%, var(--ui-primary));
}

.dashboard-kpi-card :deep(.ui-kpi),
.dashboard-panel :deep(.ui-card),
.dashboard-subscription-panel {
    border-radius: var(--dash-radius);
    border: 1px solid color-mix(in srgb, var(--card-tone, var(--dashboard-card-tone, var(--dashboard-panel-tone, var(--ui-primary)))) 10%, var(--dash-border-soft)) !important;
    background: var(--dash-surface) !important;
    box-shadow: var(--dash-shadow);
}

.dashboard-kpi-card :deep(.ui-kpi::before),
.dashboard-panel :deep(.ui-card::before),
.dashboard-panel :deep(.ui-card::after),
.dashboard-subscription-panel::before,
.dashboard-subscription-panel::after,
.dashboard-team-metric::after,
.dashboard-status-card::after {
    display: none !important;
}

.dashboard-kpi-card :deep(.ui-kpi) {
    min-height: 7.35rem;
    padding: 0.9rem 0.95rem 0.78rem;
}

.dashboard-kpi-card :deep(.ui-kpi-head) {
    gap: 0.85rem;
}

.dashboard-kpi-card :deep(.ui-kpi-title) {
    color: var(--ui-muted);
    font-size: 0.8rem;
    font-weight: 750;
}

.dashboard-kpi-card :deep(.ui-kpi-value) {
    margin-top: 0.18rem;
    color: var(--ui-text);
    font-size: clamp(1.4rem, 1.45vw, 1.68rem);
    font-weight: 850;
}

.dashboard-kpi-card :deep(.ui-kpi-footer) {
    margin-top: 0.65rem;
    padding-top: 0.56rem;
    border-top: 1px solid var(--dash-border-soft);
}

.dashboard-kpi-card :deep(.ui-kpi-link) {
    color: color-mix(in srgb, var(--dashboard-card-tone) 76%, var(--ui-text));
    font-size: 0.76rem;
    text-decoration: none;
}

.dashboard-kpi-orb,
.dashboard-panel :deep(.ui-section-icon),
.dashboard-subscription-icon,
.dashboard-status-card__icon,
.dashboard-team-metric__number,
.dashboard-team-metric__icon,
.dashboard-mini-kpi__icon {
    width: 2.38rem;
    height: 2.38rem;
    border-radius: 0.78rem;
    border: 1px solid color-mix(in srgb, var(--icon-tone, var(--orb-tone, var(--dashboard-panel-tone, var(--ui-primary)))) 14%, var(--dash-border-soft));
    background: color-mix(in srgb, #fff 90%, var(--icon-tone, var(--orb-tone, var(--dashboard-panel-tone, var(--ui-primary)))) 10%);
    color: var(--icon-tone, var(--orb-tone, var(--dashboard-panel-tone, var(--ui-primary))));
    box-shadow: none;
}

.dashboard-kpi-orb svg,
.dashboard-panel :deep(.ui-section-icon svg),
.dashboard-subscription-icon svg,
.dashboard-status-card__icon svg,
.dashboard-team-metric__icon svg,
.dashboard-mini-kpi__icon svg {
    width: 1.18rem;
    height: 1.18rem;
    color: currentColor !important;
    stroke-width: 1.75;
}

.dashboard-panel :deep(.ui-section-head) {
    padding: 0.88rem 0.95rem 0;
}

.dashboard-panel :deep(.ui-section-head-row) {
    align-items: center;
    gap: 0.8rem;
}

.dashboard-panel :deep(.ui-section-title) {
    color: var(--ui-text);
    font-size: 1rem;
    font-weight: 820;
    letter-spacing: -0.015em;
}

.dashboard-panel :deep(.ui-section-subtitle) {
    margin-top: 0.18rem;
    color: var(--ui-muted);
    font-size: 0.78rem;
    line-height: 1.5;
}

.dashboard-panel :deep(.ui-section-body) {
    padding: 0.8rem 0.95rem 0.95rem;
}

.dashboard-main-grid {
    gap: 1rem;
}

.dashboard-main-column {
    gap: 1rem;
}

.dashboard-panel--activity :deep(.ui-card),
.dashboard-panel--conversation :deep(.ui-card),
.dashboard-panel--updates :deep(.ui-card) {
    min-height: 18.3rem;
}

.dashboard-panel--activity :deep(.ui-section-body > div) {
    min-height: 0;
}

.dashboard-chart-frame {
    overflow: hidden;
    min-height: 11.8rem;
    border: 1px solid var(--dash-border-soft);
    border-radius: 0.9rem;
    background: linear-gradient(180deg, #fff, color-mix(in srgb, var(--ui-primary) 3%, #fff));
    padding: 0.35rem 0.45rem 0;
}

.dashboard-mini-kpi,
.dashboard-thread-row,
.dashboard-update-item,
.dashboard-status-card,
.dashboard-team-metric {
    border-radius: 0.86rem;
    border: 1px solid var(--dash-border-soft);
    background: #fff;
    box-shadow: none;
}

.dashboard-mini-kpi {
    min-height: 4.3rem;
    padding: 0.72rem 0.78rem;
}

.dashboard-mini-kpi__label,
.dashboard-team-metric__note {
    color: var(--ui-muted);
    font-size: 0.72rem;
}

.dashboard-mini-kpi__value {
    color: var(--ui-text);
    font-size: 1.25rem;
}

.dashboard-thread-row,
.dashboard-update-item {
    min-height: 3.65rem;
    padding: 0.62rem 0.7rem;
    border-color: transparent;
    border-bottom: 1px solid var(--dash-border-soft);
    border-radius: 0;
}

.dashboard-thread-row:hover,
.dashboard-update-item:hover {
    background: color-mix(in srgb, var(--ui-primary) 4%, #fff);
}

.dashboard-empty-state {
    border-color: color-mix(in srgb, var(--ui-primary) 15%, var(--dash-border-soft));
    border-radius: 0.95rem;
    background: color-mix(in srgb, #fff 96%, var(--ui-primary) 4%);
}

.dashboard-panel--team :deep(.ui-card),
.dashboard-panel--workspace :deep(.ui-card),
.dashboard-subscription-panel {
    height: 100%;
    min-height: 12.65rem;
}

.dashboard-team-card {
    min-height: 0;
    flex: 1;
    gap: 0.72rem;
    padding: 0;
    border: 0;
    background: transparent;
    box-shadow: none;
}

.dashboard-team-metrics {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.65rem;
}

.dashboard-team-metric {
    min-height: 4.8rem;
    align-items: flex-start;
    justify-content: center;
    gap: 0.18rem;
    padding: 0.72rem;
    border-color: color-mix(in srgb, var(--team-metric-tone) 11%, var(--dash-border-soft));
    background: color-mix(in srgb, #fff 95%, var(--team-metric-tone) 5%);
    color: inherit;
}

.dashboard-team-metric--invite {
    border-style: solid;
}

.dashboard-team-metric__number {
    --icon-tone: var(--ui-success);
    border-radius: 0.78rem;
    font-size: 0.94rem;
    font-weight: 850;
}

.dashboard-team-metric__icon {
    --icon-tone: var(--ui-primary);
}

.dashboard-team-metric__title {
    color: var(--ui-text);
    font-size: 0.78rem;
    font-weight: 800;
}

.dashboard-team-footer {
    justify-content: space-between;
    min-height: 2.1rem;
    gap: 0.5rem;
}

.dashboard-status-card {
    min-height: 6.7rem;
    align-items: center;
    padding: 0.82rem;
    border-color: color-mix(in srgb, var(--dashboard-status-tone) 11%, var(--dash-border-soft));
    background: color-mix(in srgb, #fff 95%, var(--dashboard-status-tone) 5%);
}

.dashboard-status-card__icon {
    --icon-tone: var(--dashboard-status-tone);
}

.dashboard-status-card h3 {
    font-size: 0.86rem !important;
}

.dashboard-status-card p {
    font-size: 0.78rem !important;
    line-height: 1.55;
}

.dashboard-subscription-panel {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 0.95rem;
}

.dashboard-subscription-head {
    align-items: center;
    gap: 0.72rem;
}

.dashboard-subscription-icon {
    --icon-tone: var(--dashboard-subscription-tone);
    flex-shrink: 0;
}

.dashboard-subscription-pill,
.dashboard-subscription-meta {
    border-color: color-mix(in srgb, var(--dashboard-subscription-tone) 10%, var(--dash-border-soft));
    background: #fff;
    color: color-mix(in srgb, var(--dashboard-subscription-tone) 70%, var(--ui-text));
    font-size: 0.7rem;
    font-weight: 780;
}

.dashboard-subscription-copy {
    margin-top: 0.75rem;
    padding-top: 0;
}

.dashboard-subscription-copy h2 {
    color: var(--ui-text);
    font-size: 0.98rem !important;
    font-weight: 820;
}

.dashboard-subscription-copy p {
    color: var(--ui-muted);
    font-size: 0.77rem !important;
    line-height: 1.55 !important;
}

.dashboard-subscription-meta {
    margin-top: 0.1rem;
}

.dashboard-inline-action,
.dashboard-update-action,
.dashboard-inline-link,
.dashboard-text-action {
    border-radius: 0.72rem;
    font-size: 0.78rem;
    font-weight: 780;
}

.dashboard-inline-action {
    min-height: 2.18rem;
    padding: 0.5rem 0.75rem;
    border-color: var(--dash-border-soft);
    background: #fff;
    box-shadow: none;
}

.dashboard-inline-action--primary,
.dashboard-inline-action--success {
    border-color: transparent;
    color: #fff;
    background: linear-gradient(135deg, var(--ui-primary), color-mix(in srgb, var(--ui-primary) 76%, var(--ui-secondary)));
}

.dashboard-inline-action--soft,
.dashboard-inline-action--primary-soft {
    color: color-mix(in srgb, var(--ui-primary) 72%, var(--ui-text));
    background: color-mix(in srgb, #fff 93%, var(--ui-primary) 7%);
}

/* Final polish: compact inner cards and unified system-primary dashboard accents. */
.dashboard-panel--workspace,
.dashboard-panel--team {
    --dashboard-panel-tone: var(--ui-primary);
}

.dashboard-panel--activity {
    --dashboard-panel-tone: var(--ui-primary);
}

.dashboard-panel--activity :deep(.ui-section-icon) {
    width: auto;
    min-width: 6.4rem;
    height: 2.38rem;
    padding: 0 0.72rem;
    border-color: color-mix(in srgb, var(--ui-primary) 14%, var(--dash-border-soft));
    background: color-mix(in srgb, #fff 92%, var(--ui-primary) 8%);
    color: color-mix(in srgb, var(--ui-primary) 78%, var(--ui-text));
}

.dashboard-period-pill {
    width: max-content;
    min-width: max-content;
    white-space: nowrap;
    line-height: 1;
    color: color-mix(in srgb, var(--ui-primary) 78%, var(--ui-text));
}

.dashboard-period-pill svg {
    flex-shrink: 0;
}

.dashboard-scroll-list--conversations-filled {
    gap: 0.55rem;
    padding: 0;
    border: 0;
    border-radius: 0;
    background: transparent;
}

.dashboard-thread-row {
    min-height: 3.15rem;
    padding: 0.52rem 0.6rem;
    border: 1px solid color-mix(in srgb, var(--ui-primary) 16%, var(--dash-border-soft));
    border-radius: 0.95rem;
    background: #fff;
}

.dashboard-thread-row:hover {
    border-color: color-mix(in srgb, var(--ui-primary) 18%, var(--dash-border-soft));
    background: color-mix(in srgb, #fff 94%, var(--ui-primary) 6%);
}

.dashboard-thread-row h3 {
    font-size: 0.78rem !important;
    line-height: 1.25;
}

.dashboard-thread-row p,
.dashboard-thread-row .text-xs,
.dashboard-agent-badge,
.dashboard-soft-badge {
    font-size: 0.68rem !important;
    line-height: 1.25;
}

.dashboard-thread-row .dashboard-avatar,
.dashboard-thread-row .dashboard-arrow-chip {
    width: 2rem;
    height: 2rem;
}

.dashboard-team-metric--count {
    --team-metric-tone: var(--ui-primary);
}

.dashboard-team-metric__number {
    --icon-tone: var(--ui-primary);
}

.dashboard-panel--workspace .dashboard-status-card--primary {
    --dashboard-status-tone: var(--ui-primary);
}

/* Subscription hero card: state-aware package card with clear remaining days. */
.dashboard-subscription-panel {
    isolation: isolate;
    min-height: 13.35rem;
    padding: 1.05rem !important;
    border: 1px solid color-mix(in srgb, var(--dashboard-subscription-tone) 18%, var(--dash-border-soft)) !important;
    background: #fff !important;
    box-shadow:
        0 18px 38px -32px color-mix(in srgb, var(--dashboard-subscription-tone) 38%, rgba(15, 23, 42, 0.26)),
        inset 0 1px 0 rgba(255, 255, 255, 0.9) !important;
}

.dashboard-subscription-panel::before,
.dashboard-subscription-panel::after {
    content: "";
    position: absolute;
    z-index: -1;
    display: block !important;
    pointer-events: none;
}

.dashboard-subscription-panel::before {
    inset-inline-start: 0.95rem;
    bottom: 0.9rem;
    width: 42%;
    height: 0.18rem;
    border-radius: 999px;
    background: linear-gradient(90deg, var(--dashboard-subscription-tone), color-mix(in srgb, var(--dashboard-subscription-tone) 12%, transparent));
    opacity: 0.36;
}

.dashboard-subscription-panel::after {
    display: none !important;
}

.dashboard-subscription-glow--start {
    display: none;
}

.dashboard-subscription-glow--end {
    opacity: 0.14;
}

.dashboard-subscription-head {
    position: relative;
    z-index: 1;
}

.dashboard-subscription-icon {
    width: 3rem;
    height: 3rem;
    border-radius: 1rem;
    border-color: color-mix(in srgb, var(--dashboard-subscription-tone) 20%, white);
    background: color-mix(in srgb, #fff 82%, var(--dashboard-subscription-tone) 18%) !important;
    color: var(--dashboard-subscription-tone);
    box-shadow: 0 14px 24px -24px color-mix(in srgb, var(--dashboard-subscription-tone) 55%, rgba(15, 23, 42, 0.28));
}

.dashboard-subscription-pill {
    border-color: color-mix(in srgb, var(--dashboard-subscription-tone) 15%, #dfe7f3);
    background: rgba(255, 255, 255, 0.78);
    color: color-mix(in srgb, var(--dashboard-subscription-tone) 76%, var(--ui-text));
}

.dashboard-subscription-pill--status {
    display: inline-flex;
    align-items: center;
    gap: 0.36rem;
}

.dashboard-subscription-pill--active {
    border-color: color-mix(in srgb, var(--dashboard-subscription-status) 26%, #dfe7f3);
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.92), color-mix(in srgb, var(--dashboard-subscription-status) 9%, #fff));
    color: color-mix(in srgb, var(--dashboard-subscription-status) 82%, var(--ui-text));
}

.dashboard-subscription-pill--active::before {
    content: "";
    width: 0.42rem;
    height: 0.42rem;
    border-radius: 999px;
    background: var(--dashboard-subscription-status);
    box-shadow: 0 0 0 0.22rem color-mix(in srgb, var(--dashboard-subscription-status) 11%, transparent);
}

.dashboard-subscription-title-row {
    display: flex;
    align-items: center;
    gap: 0.58rem;
}

.dashboard-subscription-title-icon {
    display: inline-flex;
    width: 1.95rem;
    height: 1.95rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 0.72rem;
    border: 1px solid color-mix(in srgb, var(--dashboard-subscription-tone) 14%, #dfe7f3);
    background: color-mix(in srgb, #fff 88%, var(--dashboard-subscription-tone) 12%);
    color: var(--dashboard-subscription-tone);
}

.dashboard-subscription-title-icon--active {
    border-color: color-mix(in srgb, var(--dashboard-subscription-status) 20%, #dfe7f3);
    background: color-mix(in srgb, #fff 88%, var(--dashboard-subscription-status) 12%);
    color: var(--dashboard-subscription-status);
}

.dashboard-subscription-copy {
    position: relative;
    z-index: 1;
    display: grid;
    gap: 0.72rem;
    margin-top: 0.9rem;
    padding-top: 0;
}

.dashboard-subscription-copy h2 {
    font-size: 1rem !important;
}

.dashboard-subscription-insight {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 0.62rem;
}

.dashboard-subscription-countdown {
    display: grid;
    gap: 0.16rem;
    overflow: hidden;
    padding: 0.78rem 0.85rem;
    border-radius: 1rem;
    border: 1px solid color-mix(in srgb, var(--dashboard-subscription-tone) 18%, white);
    background:
        radial-gradient(circle at 100% 0%, rgba(255, 255, 255, 0.32), transparent 36%),
        linear-gradient(135deg, var(--dashboard-subscription-tone), color-mix(in srgb, var(--dashboard-subscription-tone) 72%, var(--dashboard-subscription-accent)));
    color: white;
    box-shadow: 0 18px 30px -26px color-mix(in srgb, var(--dashboard-subscription-tone) 72%, rgba(15, 23, 42, 0.32));
}

.dashboard-subscription-countdown span,
.dashboard-subscription-countdown small {
    font-size: 0.7rem;
    font-weight: 720;
    opacity: 0.86;
}

.dashboard-subscription-countdown strong {
    font-size: 1.08rem;
    font-weight: 900;
    letter-spacing: -0.01em;
}

.dashboard-subscription-meta {
    width: fit-content;
    border-color: color-mix(in srgb, var(--dashboard-subscription-tone) 12%, #dfe7f3);
    background: rgba(255, 255, 255, 0.82);
    color: color-mix(in srgb, var(--ui-text) 84%, var(--dashboard-subscription-tone) 16%);
}

.dashboard-subscription-meta strong {
    color: var(--ui-text);
}

@media (min-width: 1280px) {
    .dashboard-main-grid {
        grid-template-columns: minmax(0, 1.28fr) minmax(0, 0.96fr) minmax(0, 1fr);
        align-items: stretch;
        gap: 1rem;
    }
}

@media (max-width: 767px) {
    .dashboard-hero {
        padding: 0.95rem;
        border-radius: 1.35rem;
    }

    .dashboard-hero-head {
        flex-direction: column;
    }

    .dashboard-hero-title {
        font-size: 1.85rem;
    }

    .dashboard-kpi-card :deep(.ui-kpi) {
        min-height: 7.1rem;
    }

    .dashboard-action,
    .dashboard-inline-action {
        width: 100%;
    }

    .dashboard-team-card {
        align-items: flex-start;
        flex-direction: column;
    }

    .dashboard-team-avatars {
        min-width: 0;
        justify-content: flex-start;
    }
}
</style>
