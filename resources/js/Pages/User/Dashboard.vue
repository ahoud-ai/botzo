<template>
    <AppLayout>
        <div class="dashboard-shell ui-page ui-fade-up px-4 pb-8 pt-4 text-[var(--ui-text)] md:px-8">
            <div class="mx-auto flex max-w-[1680px] flex-col gap-6">
                <header class="dashboard-header">
                    <div class="dashboard-header-copy">
                        <h1 class="ui-page-title">{{ $t('Dashboard') }}</h1>
                        <p class="ui-page-subtitle">{{ dashboardHeroSupportText }}</p>
                    </div>

                    <div class="dashboard-action-cluster">
                        <Link
                            v-if="canCreateContact"
                            href="/contacts/add"
                            class="dashboard-action dashboard-action--outline"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
                                <path d="M2 21a8 8 0 0 1 13.29-6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                                <circle cx="10" cy="8" r="5" stroke="currentColor" stroke-width="1.75" />
                                <path d="M19 16v6M22 19h-6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
                            </svg>
                            <span>{{ $t('Add contact') }}</span>
                        </Link>
                        <button v-else type="button" disabled class="dashboard-action dashboard-action--disabled">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
                                <path d="M2 21a8 8 0 0 1 13.29-6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                                <circle cx="10" cy="8" r="5" stroke="currentColor" stroke-width="1.75" />
                                <path d="M19 16v6M22 19h-6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" />
                            </svg>
                            <span>{{ $t('Add contact') }}</span>
                        </button>

                        <Link
                            v-if="canCreateTemplate"
                            href="/templates/create"
                            class="dashboard-action dashboard-action--outline"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M14 2v6h6M9 15h6M12 12v6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <span>{{ $t('Create template') }}</span>
                        </Link>
                        <button v-else type="button" disabled class="dashboard-action dashboard-action--disabled">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M14 2v6h6M9 15h6M12 12v6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <span>{{ $t('Create template') }}</span>
                        </button>

                        <Link
                            v-if="canCreateCampaign"
                            href="/campaigns/create"
                            class="dashboard-action dashboard-action--primary"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
                                <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" />
                            </svg>
                            <span>{{ $t('Create campaign') }}</span>
                        </Link>
                        <button v-else type="button" disabled class="dashboard-action dashboard-action--disabled">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
                                <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" />
                            </svg>
                            <span>{{ $t('Create campaign') }}</span>
                        </button>
                    </div>
                </header>

                <div class="dashboard-overview-head">
                    <span class="dashboard-period-chip">{{ $t('Last 30 days') }}</span>
                    <h2 class="dashboard-overview-title">{{ $t('Overview') }}</h2>
                </div>

                <div class="dashboard-kpi-grid">
                    <div class="dashboard-kpi-card dashboard-kpi-card--a">
                        <UiStatCard :title="$t('Team members')" :value="teamMembersCount">
                            <template #icon>
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/><circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </template>
                        </UiStatCard>
                    </div>

                    <div class="dashboard-kpi-card dashboard-kpi-card--b">
                        <UiStatCard :title="$t('Open rate')" :value="`${props.messageEngagement.openRate}%`">
                            <template #icon>
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M22 7 13.5 15.5 8.5 10.5 2 17" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/><path d="M16 7h6v6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </template>
                        </UiStatCard>
                    </div>

                    <div class="dashboard-kpi-card dashboard-kpi-card--c">
                        <UiStatCard :title="$t('Scheduled campaigns')" :value="scheduledCampaignCount">
                            <template #icon>
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"><rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="1.75"/><path d="M16 2v4M8 2v4M3 10h18" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/><path d="M12 14v3.5l2.5 1.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </template>
                        </UiStatCard>
                    </div>

                    <div class="dashboard-kpi-card dashboard-kpi-card--d">
                        <UiStatCard :title="$t('Contacts')" :value="props.contactCount">
                            <template #icon>
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="5" stroke="currentColor" stroke-width="1.75"/><path d="M20 21a8 8 0 0 0-16 0" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>
                            </template>
                        </UiStatCard>
                    </div>

                    <div class="dashboard-kpi-card dashboard-kpi-card--e">
                        <UiStatCard :title="$t('Read messages')" :value="props.messageEngagement.readMessagesCount">
                            <template #icon>
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M18 6 7 17l-5-5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/><path d="m22 10-7.5 7.5L13 16" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </template>
                        </UiStatCard>
                    </div>

                    <div class="dashboard-kpi-card dashboard-kpi-card--f">
                        <UiStatCard :title="$t('All conversations')" :value="props.chatCount">
                            <template #icon>
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </template>
                        </UiStatCard>
                    </div>
                </div>

                <div class="dashboard-split-grid">
                    <div class="dashboard-panel dashboard-panel--conversations">
                        <UiSectionCard :title="$t('Recent conversations')">
                            <template #icon>
                                <Link href="/chats" class="dashboard-viewall-link">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none">
                                        <path d="m9 6l6 6l-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <span>{{ $t('View all') }}</span>
                                </Link>
                            </template>

                            <div v-if="props.recentConversations.length" class="dashboard-conversation-list">
                                <Link
                                    v-for="conversation in props.recentConversations"
                                    :key="conversation.uuid"
                                    :href="`/chats/${conversation.uuid}`"
                                    class="dashboard-conversation-row"
                                >
                                    <span
                                        v-if="conversation.unread_messages > 0"
                                        class="dashboard-unread-dot"
                                    >{{ conversation.unread_messages }}</span>

                                    <div class="dashboard-avatar" :class="avatarToneClass(conversation.full_name)">
                                        <img v-if="conversation.avatar" :src="conversation.avatar" :alt="conversation.full_name" class="h-full w-full object-cover" />
                                        <span v-else>{{ avatarInitial(conversation.full_name) }}</span>
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-2">
                                            <h3 class="dashboard-conversation-name">{{ conversation.full_name }}</h3>
                                            <span class="dashboard-conversation-time">{{ formatConversationTime(conversation.last_chat?.created_at) }}</span>
                                        </div>
                                        <p class="dashboard-conversation-preview">{{ conversationPreview(conversation) }}</p>
                                    </div>
                                </Link>
                            </div>

                            <div v-else class="dashboard-empty-state">
                                <p>{{ $t('No conversations yet.') }}</p>
                                <Link href="/chats" class="dashboard-inline-action dashboard-inline-action--primary">
                                    <span>{{ $t('View chats') }}</span>
                                </Link>
                            </div>
                        </UiSectionCard>
                    </div>

                    <div class="dashboard-panel dashboard-panel--chart">
                        <UiSectionCard :title="$t('Message activity')">
                            <template #icon>
                                <span class="dashboard-period-pill">
                                    <span>{{ $t('Last 7 days') }}</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none">
                                        <path d="m8 10l4 4l4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                            </template>
                            <div class="dashboard-chart-frame">
                                <apexchart type="area" height="235" :options="chartOptions" :series="series"></apexchart>
                            </div>
                        </UiSectionCard>
                    </div>
                </div>

                <MetaVerificationStepper v-if="isWorkspaceOwner" :meta-verification-request="metaVerificationRequest" />

                <div class="dashboard-trio-grid">
                    <div class="dashboard-panel dashboard-panel--status">
                        <UiSectionCard :title="$t('Workspace status')">
                            <div class="flex h-full flex-col gap-3">
                                <div v-if="!subscriptionPanelIsHealthy" class="dashboard-status-card" :style="subscriptionPanelStyle">
                                    <div class="min-w-0">
                                        <h3 class="dashboard-status-card__title">{{ subscriptionPanelTitle }}</h3>
                                        <p v-if="subscriptionPanelSupportText" class="dashboard-status-card__desc">{{ subscriptionPanelSupportText }}</p>
                                        <Link
                                            v-if="subscriptionPanelActionHref"
                                            :href="subscriptionPanelActionHref"
                                            class="dashboard-inline-action mt-3"
                                            :class="subscriptionPanelActionClass"
                                        >
                                            <span>{{ subscriptionPanelActionLabel }}</span>
                                        </Link>
                                    </div>
                                    <span class="dashboard-status-card__icon">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20"><path fill="currentColor" d="M10 2a4 4 0 0 1 4 4v1.17a3 3 0 0 0 .879 2.122l.293.293A1 1 0 0 1 14.464 11H5.536a1 1 0 0 1-.708-1.707l.293-.293A3 3 0 0 0 6 7.17V6a4 4 0 0 1 4-4Zm0 16a3 3 0 0 0 2.995-2.824L13 15H7a3 3 0 0 0 3 3Z"/></svg>
                                    </span>
                                </div>

                                <div v-if="showRenewalCountdownCard" class="dashboard-status-card dashboard-status-card--renewal" :style="renewalCountdownStyle">
                                    <div class="min-w-0">
                                        <h3 class="dashboard-status-card__title">{{ $t('Time until renewal') }}</h3>
                                        <p class="dashboard-status-card__desc">{{ renewalCountdownDesc }}</p>
                                        <Link
                                            v-if="ownerCanManageBilling"
                                            href="/subscription"
                                            class="dashboard-inline-action dashboard-inline-action--primary mt-3"
                                        >
                                            <span>{{ $t('Manage subscription') }}</span>
                                        </Link>
                                    </div>
                                    <div class="dashboard-status-card__days">
                                        <span class="dashboard-status-card__days-value">{{ subscriptionDaysRemaining }}</span>
                                        <span class="dashboard-status-card__days-label">{{ $t('days') }}</span>
                                    </div>
                                </div>

                                <div v-if="showWhatsappSetupCard" data-tour="whatsapp-setup" class="dashboard-status-card dashboard-status-card--success">
                                    <div class="min-w-0">
                                        <h3 class="dashboard-status-card__title">{{ $t('Setup whatsapp') }}</h3>
                                        <p class="dashboard-status-card__desc">{{ $t('Finish setup from WhatsApp settings.') }}</p>
                                        <Link href="/settings/whatsapp" class="dashboard-inline-action dashboard-inline-action--primary mt-3">
                                            <span>{{ $t('Settings') }}</span>
                                        </Link>
                                    </div>
                                    <span class="dashboard-status-card__icon">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" fill-rule="evenodd" d="M2.184 21.331a.4.4 0 0 0 .487.494l4.607-1.204a10 10 0 0 0 4.76 1.207h.004c5.486 0 9.958-4.447 9.958-9.912a9.828 9.828 0 0 0-2.914-7.011A9.917 9.917 0 0 0 12.042 2c-5.486 0-9.958 4.446-9.958 9.911c0 1.739.458 3.447 1.33 4.954l-1.23 4.466Z" clip-rule="evenodd"/></svg>
                                    </span>
                                </div>

                                <div v-if="teamPromptVisible" class="dashboard-status-card dashboard-status-card--warning">
                                    <div class="min-w-0">
                                        <h3 class="dashboard-status-card__title">{{ teamSetupTitle }}</h3>
                                        <p class="dashboard-status-card__desc">{{ teamSetupDescription }}</p>
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
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20"><path fill="currentColor" d="M10 2a4 4 0 0 1 4 4v1.17a3 3 0 0 0 .879 2.122l.293.293A1 1 0 0 1 14.464 11H5.536a1 1 0 0 1-.708-1.707l.293-.293A3 3 0 0 0 6 7.17V6a4 4 0 0 1 4-4Zm0 16a3 3 0 0 0 2.995-2.824L13 15H7a3 3 0 0 0 3 3Z"/></svg>
                                    </span>
                                </div>

                                <div v-if="showWorkspaceReadyState" class="dashboard-status-card dashboard-status-card--success">
                                    <div class="flex items-start gap-3">
                                        <span class="dashboard-status-card__icon">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="m5 13l4 4L19 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                        </span>
                                        <div>
                                            <h3 class="dashboard-status-card__title">{{ $t('Ready to work') }}</h3>
                                            <p class="dashboard-status-card__desc">{{ $t('Everything is ready.') }}</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="dashboard-metric-row">
                                    <div class="dashboard-metric-tile">
                                        <p class="dashboard-metric-tile__value">{{ props.messageEngagement.deliveryRate }}%</p>
                                        <p class="dashboard-metric-tile__label">{{ $t('Delivery rate') }}</p>
                                    </div>
                                </div>
                            </div>
                        </UiSectionCard>
                    </div>

                    <div class="dashboard-panel dashboard-panel--team">
                        <UiSectionCard :title="$t('Company team')" :subtitle="$t('Manage members and access from one place.')">
                            <div class="dashboard-team-card">
                                <div class="dashboard-team-metrics">
                                    <div class="dashboard-team-metric">
                                        <span class="dashboard-team-metric__value">{{ props.pendingInvitesCount }}</span>
                                        <span class="dashboard-team-metric__label">{{ $t('Pending invites') }}</span>
                                    </div>
                                    <div class="dashboard-team-metric">
                                        <span class="dashboard-team-metric__value">{{ teamMembersCount }}</span>
                                        <span class="dashboard-team-metric__label">{{ $t('Team members') }}</span>
                                    </div>
                                </div>

                                <div v-if="props.teamMembers.length" class="dashboard-avatar-stack">
                                    <div
                                        v-for="member in props.teamMembers.slice(0, 4)"
                                        :key="member.full_name"
                                        class="dashboard-avatar dashboard-avatar--stacked"
                                        :class="avatarToneClass(member.full_name)"
                                    >
                                        <img v-if="member.avatar" :src="member.avatar" :alt="member.full_name" class="h-full w-full object-cover" />
                                        <span v-else>{{ avatarInitial(member.full_name) }}</span>
                                    </div>
                                    <span v-if="teamMembersCount > 4" class="dashboard-avatar dashboard-avatar--stacked dashboard-avatar--more">
                                        +{{ teamMembersCount - 4 }}
                                    </span>
                                </div>

                                <Link :href="teamSetupHref" class="dashboard-action dashboard-action--primary dashboard-action--block">
                                    <span>{{ $t('Manage team') }}</span>
                                </Link>
                            </div>
                        </UiSectionCard>
                    </div>

                    <div class="dashboard-panel dashboard-panel--campaigns">
                        <UiSectionCard :title="$t('Campaign queue')" :subtitle="$t('Pending and scheduled.')">
                            <div v-if="props.campaigns.length === 0" class="dashboard-empty-state">
                                <p>{{ $t('Queue is clear.') }}</p>
                                <Link
                                    v-if="canCreateCampaign"
                                    href="/campaigns/create"
                                    class="dashboard-inline-action dashboard-inline-action--primary"
                                >
                                    <span>{{ $t('Create campaign') }}</span>
                                </Link>
                            </div>

                            <div v-else class="dashboard-queue-list">
                                <div v-for="item in props.campaigns" :key="item.uuid || item.id" class="dashboard-queue-row">
                                    <span :class="campaignStatusChipClass(item.status)">{{ $t(item.status) }}</span>
                                    <div class="min-w-0 flex-1">
                                        <h3 class="dashboard-queue-row__name">{{ item.name }}</h3>
                                        <p class="dashboard-queue-row__recipients">{{ item.recipients_count }} {{ $t('Recipients') }}</p>
                                    </div>
                                </div>

                                <Link
                                    v-if="canCreateCampaign"
                                    href="/campaigns/create"
                                    class="dashboard-action dashboard-action--primary dashboard-action--block"
                                >
                                    <span>{{ $t('Create campaign') }}</span>
                                </Link>
                            </div>
                        </UiSectionCard>
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
import { Link, router } from "@inertiajs/vue3";
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useWorkspaceAccess } from '@/Composables/useWorkspaceAccess';
import { useTheme } from '@/Composables/useTheme';
import MetaVerificationStepper from '@/Components/MetaVerificationStepper.vue';

const { t, locale } = useI18n();
const { isWorkspaceOwner, isBranchWorkspace, hasInheritedParentAuthority, canViewBilling, hasPermission } = useWorkspaceAccess();
const { theme } = useTheme();

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
    teamMembers: {
        type: Array,
        default: () => [],
    },
    pendingInvitesCount: {
        type: Number,
        default: 0,
    },
    setupWhatsapp: Boolean,
    organization: Object,
    metaVerificationRequest: {
        type: Object,
        default: null,
    },
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
    messageEngagement: {
        type: Object,
        default: () => ({
            readMessagesCount: 0,
            openRate: 0,
            deliveryRate: 0,
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

const scheduledCampaignCount = computed(() => Number(props.campaignSummary?.scheduled ?? 0));
const teamMembersCount = computed(() => Number(props.teamMemberCount ?? 0));

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

const subscriptionDisplayState = computed(() => props.subscriptionDisplayState ?? {});
const subscriptionStateVariant = computed(() => subscriptionDisplayState.value.variant ?? 'billing_pending');
const subscriptionManagedByParent = computed(() => subscriptionDisplayState.value.managed_by_parent === true);
const subscriptionPanelIsHealthy = computed(() => subscriptionManagedByParent.value || props.subscriptionIsActive);
const ownerCanManageBilling = computed(() => canViewBilling.value && !subscriptionManagedByParent.value);
const showSubscribeButton = computed(() => ownerCanManageBilling.value && ['billing_pending', 'inactive', 'trial_active', 'trial_expired'].includes(subscriptionStateVariant.value));
const showRenewPlanButton = computed(() => ownerCanManageBilling.value && subscriptionStateVariant.value === 'payment_required');

const subscriptionValidUntilDate = computed(() => {
    const raw = props.subscription?.valid_until;
    if (!raw) {
        return null;
    }

    const parsed = new Date(String(raw).replace(' ', 'T'));
    return Number.isNaN(parsed.getTime()) ? null : parsed;
});
const subscriptionDaysRemaining = computed(() => {
    if (!subscriptionValidUntilDate.value) {
        return null;
    }

    const diffMs = subscriptionValidUntilDate.value.getTime() - Date.now();
    return Math.ceil(diffMs / 86400000);
});
const showRenewalCountdownCard = computed(() => (
    subscriptionPanelIsHealthy.value
    && !subscriptionManagedByParent.value
    && subscriptionDaysRemaining.value !== null
    && subscriptionDaysRemaining.value >= 0
));
const renewalIsUrgent = computed(() => subscriptionDaysRemaining.value !== null && subscriptionDaysRemaining.value <= 7);
const renewalCountdownStyle = computed(() => ({
    '--dashboard-status-tone': renewalIsUrgent.value ? 'var(--ui-warning)' : 'var(--ui-success)',
}));
const renewalCountdownDesc = computed(() => (
    subscriptionStateVariant.value === 'trial_active'
        ? t('Free trial')
        : (props.subscription?.plan?.name ?? t('Current plan'))
));

const hasAnyWorkspaceAlert = computed(() => (
    !subscriptionPanelIsHealthy.value || showWhatsappSetupCard.value || teamPromptVisible.value
));
const showWorkspaceReadyState = computed(() => !hasAnyWorkspaceAlert.value && !showRenewalCountdownCard.value);

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
    if (subscriptionStateVariant.value === 'payment_required') {
        return { tone: 'var(--ui-danger)' };
    }

    if (subscriptionStateVariant.value === 'trial_expired') {
        return { tone: 'var(--ui-warning)' };
    }

    return { tone: 'var(--ui-warning)' };
});

const subscriptionPanelStyle = computed(() => ({
    '--dashboard-status-tone': subscriptionPanelTone.value.tone,
}));

const subscriptionPanelActionClass = computed(() => (
    showRenewPlanButton.value ? 'dashboard-inline-action--danger' : 'dashboard-inline-action--primary'
));

const subscriptionPanelTitle = computed(() => {
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

    return t('Billing and subscription');
});

const subscriptionPanelSupportText = computed(() => {
    if (!ownerCanManageBilling.value) {
        return null;
    }

    if (subscriptionStateVariant.value === 'billing_pending') {
        return t('Finish billing setup');
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

const avatarTones = ['a', 'b', 'c', 'd', 'e', 'f'];
const avatarToneClass = (name) => {
    const value = String(name ?? '');
    let hash = 0;

    for (let i = 0; i < value.length; i += 1) {
        hash = (hash + value.charCodeAt(i)) % avatarTones.length;
    }

    return `dashboard-avatar--${avatarTones[hash]}`;
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

const chartPrimaryColor = computed(() => { theme.value; return '#155dfc'; });
const chartSecondaryColor = computed(() => { theme.value; return getThemeColor('--ui-secondary', '#25d366'); });
const chartMutedColor = computed(() => { theme.value; return getThemeColor('--ui-muted', '#475569'); });
const chartBorderColor = computed(() => { theme.value; return getThemeColor('--ui-border', 'rgba(15, 23, 42, 0.14)'); });

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
        foreColor: chartMutedColor.value,
        toolbar: {
            show: false
        },
        zoom: {
            enabled: false
        },
    },
    colors: [chartPrimaryColor.value, chartSecondaryColor.value],
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.16,
            opacityTo: 0.02,
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
        borderColor: chartBorderColor.value,
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
                colors: chartMutedColor.value,
                fontSize: '11px',
            },
        },
    },
    yaxis: {
        labels: {
            style: {
                colors: chartMutedColor.value,
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
.dashboard-header {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
}

.dashboard-header-copy .ui-page-title {
    margin: 0;
}

.dashboard-header-copy .ui-page-subtitle {
    margin-top: 0.3rem;
}

.dashboard-action-cluster {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.6rem;
}

.dashboard-action {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    border-radius: 0.7rem;
    padding: 0.65rem 1.1rem;
    font-size: 0.94rem;
    font-weight: 600;
    white-space: nowrap;
    border: 1px solid transparent;
    transition: transform 160ms ease, box-shadow 160ms ease, filter 160ms ease, background-color 160ms ease;
}

.dashboard-action--primary {
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: 0 14px 28px -16px color-mix(in srgb, var(--ui-secondary) 65%, transparent);
}

.dashboard-action--primary:hover {
    transform: translateY(-1px) scale(1.02);
    filter: saturate(1.08);
}

.dashboard-action--outline {
    background: var(--ui-surface);
    color: var(--ui-text);
    border-color: var(--ui-border);
}

.dashboard-action--outline:hover {
    border-color: color-mix(in srgb, var(--ui-primary) 30%, var(--ui-border));
    background: color-mix(in srgb, var(--ui-primary) 6%, var(--ui-surface));
}

.dashboard-action--disabled {
    background: var(--ui-surface);
    color: var(--ui-muted);
    border-color: var(--ui-border);
    opacity: 0.6;
    cursor: not-allowed;
}

.dashboard-action--block {
    width: 100%;
    justify-content: center;
    margin-top: auto;
}

.dashboard-overview-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}

.dashboard-overview-title {
    font-size: 1.28rem;
    font-weight: 700;
}

.dashboard-period-chip {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    color: var(--ui-muted);
    padding: 0.35rem 0.85rem;
    font-size: 0.88rem;
    font-weight: 600;
}

.dashboard-kpi-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.85rem;
}

@media (min-width: 768px) {
    .dashboard-kpi-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (min-width: 1200px) {
    .dashboard-kpi-grid {
        grid-template-columns: repeat(6, 1fr);
    }
}

.dashboard-kpi-card :deep(.ui-kpi) {
    min-height: 7.5rem;
}

.dashboard-kpi-card :deep(.ui-kpi-icon) {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 0.85rem;
}

.dashboard-kpi-card :deep(.ui-kpi-icon svg) {
    width: 1.1rem;
    height: 1.1rem;
}

.dashboard-kpi-card :deep(.ui-kpi-title) {
    font-size: 0.86rem;
    font-weight: 500;
}

.dashboard-kpi-card :deep(.ui-kpi-value) {
    font-size: 1.75rem;
    font-weight: 800;
    letter-spacing: -0.015em;
}

/* Categorical accents matching the Figma KPI row exactly (pink/violet/amber/indigo/blue/emerald) */
.dashboard-kpi-card--a :deep(.ui-kpi-icon) {
    background: rgba(244, 63, 94, 0.12);
    border-color: rgba(244, 63, 94, 0.22);
}
.dashboard-kpi-card--a :deep(.ui-kpi-icon svg) { color: #e11d48 !important; }

.dashboard-kpi-card--b :deep(.ui-kpi-icon) {
    background: rgba(139, 92, 246, 0.12);
    border-color: rgba(139, 92, 246, 0.22);
}
.dashboard-kpi-card--b :deep(.ui-kpi-icon svg) { color: #7c3aed !important; }

.dashboard-kpi-card--c :deep(.ui-kpi-icon) {
    background: rgba(245, 158, 11, 0.14);
    border-color: rgba(245, 158, 11, 0.24);
}
.dashboard-kpi-card--c :deep(.ui-kpi-icon svg) { color: #f59e0b !important; }

.dashboard-kpi-card--d :deep(.ui-kpi-icon) {
    background: rgba(99, 102, 241, 0.12);
    border-color: rgba(99, 102, 241, 0.22);
}
.dashboard-kpi-card--d :deep(.ui-kpi-icon svg) { color: #6366f1 !important; }

.dashboard-kpi-card--e :deep(.ui-kpi-icon) {
    background: rgba(21, 93, 252, 0.12);
    border-color: rgba(21, 93, 252, 0.22);
}
.dashboard-kpi-card--e :deep(.ui-kpi-icon svg) { color: #155dfc !important; }

.dashboard-kpi-card--f :deep(.ui-kpi-icon) {
    background: color-mix(in srgb, var(--ui-secondary) 16%, transparent);
    border-color: color-mix(in srgb, var(--ui-secondary) 30%, transparent);
}
.dashboard-kpi-card--f :deep(.ui-kpi-icon svg) { color: var(--ui-secondary) !important; }

.dashboard-split-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
}

@media (min-width: 1024px) {
    .dashboard-split-grid {
        grid-template-columns: 1fr 1fr;
    }
}

.dashboard-trio-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
}

@media (min-width: 1024px) {
    .dashboard-trio-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

.dashboard-panel :deep(.ui-card) {
    height: 100%;
    display: flex;
    flex-direction: column;
}

.dashboard-panel :deep(.ui-section-body) {
    flex: 1;
    display: flex;
    flex-direction: column;
}


.dashboard-viewall-link {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--ui-primary);
    border-radius: 0.5rem;
    padding: 0.2rem 0.4rem;
    margin: -0.2rem -0.4rem;
    transition: background-color 160ms ease, gap 160ms ease;
}

.dashboard-viewall-link:hover {
    background: color-mix(in srgb, var(--ui-primary) 10%, transparent);
    gap: 0.45rem;
}

.dashboard-period-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border-radius: 999px;
    border: 1px solid var(--ui-border);
    padding: 0.3rem 0.75rem;
    font-size: 0.86rem;
    color: var(--ui-muted);
}

.dashboard-conversation-list {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.dashboard-conversation-row {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.7rem;
    padding: 0.6rem 0.5rem;
    border-radius: 0.75rem;
    transition: background-color 150ms ease;
}

.dashboard-conversation-row:hover {
    background: color-mix(in srgb, var(--ui-primary) 5%, transparent);
}

.dashboard-avatar {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    flex-shrink: 0;
    border-radius: 999px;
    overflow: hidden;
    font-size: 0.92rem;
    font-weight: 700;
    color: #fff;
    background: var(--ui-primary);
}

.dashboard-avatar--a { background: color-mix(in srgb, var(--ui-primary) 85%, black 0%); }
.dashboard-avatar--b { background: var(--ui-secondary); }
.dashboard-avatar--c { background: color-mix(in srgb, var(--ui-accent) 90%, black 6%); }
.dashboard-avatar--d { background: color-mix(in srgb, var(--ui-primary) 60%, var(--ui-secondary) 40%); }
.dashboard-avatar--e { background: color-mix(in srgb, var(--ui-success) 90%, black 6%); }
.dashboard-avatar--f { background: color-mix(in srgb, var(--ui-muted) 90%, black 6%); }

.dashboard-unread-dot {
    position: absolute;
    inset-inline-start: 2.1rem;
    top: 0.35rem;
    min-width: 1.05rem;
    height: 1.05rem;
    padding: 0 0.25rem;
    border-radius: 999px;
    background: var(--ui-danger);
    color: #fff;
    font-size: 0.62rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    z-index: 1;
}

.dashboard-conversation-name {
    font-size: 0.94rem;
    font-weight: 600;
    color: var(--ui-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.dashboard-conversation-time {
    flex-shrink: 0;
    font-size: 0.78rem;
    color: var(--ui-muted);
}

.dashboard-conversation-preview {
    margin-top: 0.1rem;
    font-size: 0.88rem;
    color: var(--ui-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.dashboard-chart-frame {
    margin-top: 0.5rem;
}

.dashboard-empty-state {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    padding: 2rem 1rem;
    text-align: center;
    color: var(--ui-muted);
    font-size: 0.96rem;
}

.dashboard-status-card {
    --dashboard-status-tone: var(--ui-warning);
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.9rem 1rem;
    border-radius: 0.85rem;
    border-inline-start: 3px solid var(--dashboard-status-tone);
    background: color-mix(in srgb, var(--dashboard-status-tone) 6%, var(--ui-surface-soft));
}

.dashboard-status-card--success {
    --dashboard-status-tone: var(--ui-success);
}

.dashboard-status-card--warning {
    --dashboard-status-tone: var(--ui-warning);
}

.dashboard-status-card__title {
    font-size: 0.94rem;
    font-weight: 700;
    color: var(--ui-text);
}

.dashboard-status-card__desc {
    margin-top: 0.2rem;
    font-size: 0.86rem;
    color: var(--ui-muted);
}

.dashboard-status-card__days {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    min-width: 3.4rem;
    padding: 0.35rem 0.5rem;
    border-radius: 0.65rem;
    background: color-mix(in srgb, var(--dashboard-status-tone) 14%, transparent);
}

.dashboard-status-card__days-value {
    font-size: 1.2rem;
    font-weight: 800;
    line-height: 1.1;
    color: var(--dashboard-status-tone);
}

.dashboard-status-card__days-label {
    margin-top: 0.1rem;
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--dashboard-status-tone);
}

.dashboard-status-card__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.9rem;
    height: 1.9rem;
    flex-shrink: 0;
    border-radius: 0.6rem;
    color: var(--dashboard-status-tone);
    background: color-mix(in srgb, var(--dashboard-status-tone) 20%, transparent);
}

.dashboard-metric-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(7rem, 1fr));
    margin-top: auto;
    padding-top: 0.9rem;
    border-top: 1px solid var(--ui-border);
}

.dashboard-metric-tile {
    text-align: center;
    padding: 0.25rem 0.5rem;
}

.dashboard-metric-tile:not(:last-child) {
    border-inline-end: 1px solid var(--ui-border);
}

.dashboard-metric-tile__value {
    font-size: 1.4rem;
    font-weight: 800;
    letter-spacing: -0.01em;
    color: var(--ui-text);
}

.dashboard-metric-tile__label {
    margin-top: 0.2rem;
    font-size: 0.78rem;
    color: var(--ui-muted);
}

.dashboard-team-card {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    flex: 1;
}

.dashboard-team-metrics {
    display: grid;
    grid-template-columns: 1fr 1fr;
}

.dashboard-team-metric {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.2rem;
    padding: 0.5rem;
}

.dashboard-team-metric:first-child {
    border-inline-end: 1px solid var(--ui-border);
}

.dashboard-team-metric__value {
    font-size: 1.7rem;
    font-weight: 800;
    letter-spacing: -0.01em;
    color: var(--ui-text);
}

.dashboard-team-metric__label {
    font-size: 0.8rem;
    color: var(--ui-muted);
}

.dashboard-avatar-stack {
    display: flex;
    align-items: center;
    justify-content: center;
}

.dashboard-avatar--stacked {
    border: 2px solid var(--ui-surface);
    margin-inline-start: -0.6rem;
}

.dashboard-avatar--stacked:first-child {
    margin-inline-start: 0;
}

.dashboard-avatar--more {
    background: var(--ui-muted);
    font-size: 0.72rem;
}

.dashboard-queue-list {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    flex: 1;
}

.dashboard-queue-row {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    padding: 0.65rem 0.4rem;
    border-radius: 0.6rem;
    border-bottom: 1px solid var(--ui-border);
    transition: background-color 160ms ease;
}

.dashboard-queue-row:last-child {
    border-bottom: none;
}

.dashboard-queue-row:hover {
    background: var(--ui-surface-soft);
}

.dashboard-queue-row__name {
    font-size: 0.92rem;
    font-weight: 600;
    color: var(--ui-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.dashboard-queue-row__recipients {
    margin-top: 0.1rem;
    font-size: 0.82rem;
    color: var(--ui-muted);
}

.dashboard-soft-badge {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.28rem 0.65rem;
    font-size: 0.78rem;
    font-weight: 700;
}

.dashboard-soft-badge--warning {
    background: color-mix(in srgb, var(--ui-warning) 18%, transparent);
    color: color-mix(in srgb, var(--ui-warning) 80%, var(--ui-text) 20%);
}

.dashboard-soft-badge--secondary {
    background: color-mix(in srgb, var(--ui-secondary) 18%, transparent);
    color: color-mix(in srgb, var(--ui-secondary) 70%, var(--ui-text) 30%);
}

.dashboard-soft-badge--neutral {
    background: color-mix(in srgb, var(--ui-muted) 18%, transparent);
    color: var(--ui-muted);
}

.dashboard-inline-action {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    border-radius: 0.6rem;
    padding: 0.48rem 0.9rem;
    font-size: 0.86rem;
    font-weight: 600;
    transition: transform 160ms ease, filter 160ms ease, box-shadow 160ms ease;
}

.dashboard-inline-action:hover {
    transform: translateY(-1px);
    filter: saturate(1.1) brightness(1.03);
}

.dashboard-inline-action--primary {
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: 0 10px 20px -14px color-mix(in srgb, var(--ui-secondary) 70%, transparent);
}

.dashboard-inline-action--danger {
    background: var(--ui-danger);
    color: #fff;
    box-shadow: 0 10px 20px -14px color-mix(in srgb, var(--ui-danger) 70%, transparent);
}

.dashboard-text-action {
    font-size: 0.84rem;
    font-weight: 600;
    color: var(--ui-muted);
    text-decoration: underline;
    text-underline-offset: 2px;
    transition: color 160ms ease;
}

.dashboard-text-action:hover {
    color: var(--ui-primary);
}
</style>
