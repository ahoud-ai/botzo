<template>
    <div class="space-y-6">
        <UiSectionCard :title="$t('Company memberships')">
            <div v-if="user.companies?.length" class="space-y-4">
                <article v-for="company in user.companies" :key="company.uuid" class="usr-card">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="usr-card-name">{{ company.company_name || $t('Not set') }}</h3>
                                <span class="usr-chip" :class="companyStatusChip(company.status)">
                                    {{ localizeStatus(company.status_label) }}
                                </span>
                                <span class="usr-chip" :class="subscriptionTone(company.subscription?.status_key)">
                                    {{ company.subscription?.status_label || $t('No active subscription') }}
                                </span>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                <span class="usr-pill">
                                    {{ $t('Assignments') }}: <strong>{{ company.assignments_count }}</strong>
                                </span>
                                <span class="usr-pill">
                                    {{ company.subscription?.plan_name || $t('No active subscription') }}
                                </span>
                                <span v-if="companyTimeline(company)" class="usr-pill">
                                    {{ companyTimeline(company).label }}: <strong>{{ companyTimeline(company).value }}</strong>
                                </span>
                                <span v-if="company.subscription?.managed_from_parent" class="usr-chip ui-chip-warning">
                                    {{ $t('Managed from parent subscription') }}
                                </span>
                            </div>
                        </div>

                        <div class="usr-tile lg:min-w-[12rem]">
                            <p class="usr-tile-label">{{ $t('Access summary') }}</p>
                            <p class="usr-tile-value">{{ company.assignments_count }} {{ $t('Workspaces') }}</p>
                            <p v-if="company.subscription?.valid_until" class="usr-tile-sub">{{ company.subscription.valid_until }}</p>
                        </div>
                    </div>

                    <div v-if="company.assignments?.length" class="mt-4 grid gap-3 lg:grid-cols-2">
                        <div v-for="assignment in company.assignments" :key="assignment.uuid" class="usr-tile">
                            <div class="flex items-center justify-between gap-2">
                                <div>
                                    <p class="usr-tile-value">{{ assignment.workspace_name || $t('Not set') }}</p>
                                    <p class="usr-tile-sub mt-1">{{ assignment.workspace_type === 'branch' ? $t('Branch workspace') : $t('Main workspace') }}</p>
                                </div>
                                <span class="usr-chip ui-chip-neutral">{{ localizeKnownValue(assignment.role_name) }}</span>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                <span class="usr-pill">
                                    {{ localizeStatus(assignment.status_label) }}
                                </span>
                                <span class="usr-pill">
                                    {{ assignment.role_permissions?.label || $t('Not set') }}
                                </span>
                                <span class="usr-pill">
                                    {{ assignment.subscription?.plan_name || $t('No active subscription') }}
                                </span>
                                <span v-if="assignment.subscription?.managed_from_parent" class="usr-chip ui-chip-warning">
                                    {{ $t('Managed from parent subscription') }}
                                </span>
                            </div>
                            <p v-if="assignment.assigned_at" class="usr-tile-sub mt-3">
                                {{ $t('Assigned at') }}: {{ assignment.assigned_at }}
                            </p>
                        </div>
                    </div>
                </article>
            </div>
            <div v-else class="usr-tile usr-tile--dashed usr-empty">
                {{ $t('No company access yet') }}
            </div>
        </UiSectionCard>

        <div class="grid gap-6 xl:grid-cols-2">
            <UiSectionCard :title="$t('Workspace memberships')">
                <div v-if="user.direct_workspaces?.length" class="space-y-3">
                    <article v-for="workspace in user.direct_workspaces" :key="workspace.id" class="usr-tile">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3 class="usr-tile-value">{{ workspace.workspace_name || $t('Not set') }}</h3>
                                <p class="usr-tile-sub mt-1">{{ workspace.workspace_type === 'branch' ? $t('Branch workspace') : $t('Main workspace') }}</p>
                            </div>
                            <span class="usr-chip ui-chip-neutral">{{ localizeKnownValue(workspace.role_name) }}</span>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2 text-xs">
                            <span class="usr-pill">
                                {{ workspace.access_source_label }}
                            </span>
                            <span class="usr-pill">
                                {{ workspace.subscription?.plan_name || $t('No active subscription') }}
                            </span>
                            <span class="usr-chip" :class="subscriptionTone(workspace.subscription?.status_key)">
                                {{ workspace.subscription?.status_label || $t('No active subscription') }}
                            </span>
                            <span class="usr-pill">
                                {{ workspace.role_permissions?.label || $t('Not set') }}
                            </span>
                            <span v-if="workspace.subscription?.managed_from_parent" class="usr-chip ui-chip-warning">
                                {{ $t('Managed from parent subscription') }}
                            </span>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-xs usr-muted">
                            <span>{{ localizeStatus(workspace.status_label) }}</span>
                            <span>{{ workspace.created_at || $t('Not set') }}</span>
                        </div>
                    </article>
                </div>
                <div v-else class="usr-tile usr-tile--dashed usr-empty">
                    {{ $t('No direct workspaces') }}
                </div>
            </UiSectionCard>

            <UiSectionCard :title="$t('Workspace invitations')">
                <div v-if="user.workspace_invites?.length" class="space-y-3">
                    <article v-for="invite in user.workspace_invites" :key="`${invite.organization_name}-${invite.expire_at}`" class="usr-tile">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="usr-tile-value">{{ invite.organization_name || $t('Not set') }}</p>
                                <p class="usr-tile-sub mt-1">{{ localizeKnownValue(invite.role_name) }}</p>
                            </div>
                            <span class="usr-pill">
                                {{ invite.expire_at || $t('Not set') }}
                            </span>
                        </div>
                    </article>
                </div>
                <div v-else class="usr-tile usr-tile--dashed usr-empty">
                    {{ $t('No pending workspace invites') }}
                </div>
            </UiSectionCard>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import UiSectionCard from '@/Components/UI/UiSectionCard.vue';
import { buildI18nLookupCandidates } from '@/Utils/i18nLookup';

const { t, te } = useI18n();

const props = defineProps({
    user: { type: Object, required: true },
});

const user = computed(() => props.user);

function localizeKnownValue(value) {
    const rawValue = String(value ?? '').trim();
    if (rawValue === '') {
        return t('Not set');
    }

    for (const candidate of buildI18nLookupCandidates(rawValue)) {
        if (te(candidate)) {
            return t(candidate);
        }
    }

    return rawValue;
}

function localizeStatus(value) {
    const rawValue = String(value ?? '').trim();
    if (rawValue === '') {
        return t('Not set');
    }

    return t(rawValue);
}

function companyStatusChip(status) {
    if (status === 'active') {
        return 'ui-chip-success';
    }

    if (status === 'pending') {
        return 'ui-chip-warning';
    }

    return 'ui-chip-neutral';
}

function subscriptionTone(statusKey) {
    if (statusKey === 'active') {
        return 'ui-chip-success';
    }

    if (statusKey === 'trial') {
        return 'ui-chip-warning';
    }

    if (statusKey === 'expired' || statusKey === 'inactive') {
        return 'ui-chip-danger';
    }

    return 'ui-chip-neutral';
}

function companyTimeline(company) {
    if (company.accepted_at) {
        return { label: t('Accepted at'), value: company.accepted_at };
    }

    if (company.invite_expires_at) {
        return { label: t('Invite expires'), value: company.invite_expires_at };
    }

    if (company.invited_at) {
        return { label: t('Invited at'), value: company.invited_at };
    }

    return null;
}
</script>

<style scoped>
.usr-card {
    border-radius: 1.1rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    padding: 1.1rem;
    transition: border-color 160ms ease;
}

.usr-card:hover {
    border-color: var(--ui-border-strong);
}

.usr-card-name {
    font-size: 0.98rem;
    font-weight: 800;
    color: var(--ui-text);
}

.usr-tile {
    border-radius: 0.9rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    padding: 0.85rem 1rem;
}

.usr-tile--dashed {
    border-style: dashed;
}

.usr-tile-label {
    font-size: 0.76rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    color: var(--ui-muted);
}

.usr-tile-value {
    margin-top: 0.3rem;
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--ui-text);
}

.usr-tile-sub {
    font-size: 0.78rem;
    color: var(--ui-muted);
}

.usr-empty {
    text-align: center;
    padding: 2rem 1rem;
    color: var(--ui-muted);
}

.usr-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    border-radius: 999px;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    color: var(--ui-muted);
    padding: 0.35rem 0.8rem;
}

.usr-pill strong {
    color: var(--ui-text);
    font-weight: 700;
}

.usr-chip {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.3rem 0.75rem;
    font-size: 0.78rem;
    font-weight: 700;
    white-space: nowrap;
}

.usr-muted {
    color: var(--ui-muted);
}
</style>
