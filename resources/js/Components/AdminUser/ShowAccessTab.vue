<template>
    <div class="space-y-6">
        <UiSectionCard :title="$t('Company memberships')">
            <div v-if="user.companies?.length" class="space-y-4">
                <article v-for="company in user.companies" :key="company.uuid" class="rounded-[1rem] border border-slate-200 bg-slate-50 p-4">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-base font-semibold text-slate-900">{{ company.company_name || $t('Not set') }}</h3>
                                <span class="rounded-full px-3 py-1 text-xs font-medium" :class="company.status === 'active' ? 'bg-emerald-100 text-emerald-700' : company.status === 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-slate-200 text-slate-700'">
                                    {{ localizeStatus(company.status_label) }}
                                </span>
                                <span class="rounded-full px-3 py-1 text-xs font-medium" :class="subscriptionTone(company.subscription?.status_key)">
                                    {{ company.subscription?.status_label || $t('No active subscription') }}
                                </span>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2 text-xs text-slate-600">
                                <span class="rounded-full border border-slate-200 bg-white px-3 py-1">
                                    {{ $t('Assignments') }}: <strong class="text-slate-900">{{ company.assignments_count }}</strong>
                                </span>
                                <span class="rounded-full border border-slate-200 bg-white px-3 py-1">
                                    {{ company.subscription?.plan_name || $t('No active subscription') }}
                                </span>
                                <span v-if="companyTimeline(company)" class="rounded-full border border-slate-200 bg-white px-3 py-1">
                                    {{ companyTimeline(company).label }}: <strong class="text-slate-900">{{ companyTimeline(company).value }}</strong>
                                </span>
                                <span v-if="company.subscription?.managed_from_parent" class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-amber-700">
                                    {{ $t('Managed from parent subscription') }}
                                </span>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600 lg:min-w-[12rem]">
                            <p class="text-xs font-medium text-slate-500">{{ $t('Access summary') }}</p>
                            <p class="mt-1 font-semibold text-slate-900">{{ company.assignments_count }} {{ $t('Workspaces') }}</p>
                            <p v-if="company.subscription?.valid_until" class="mt-1 text-xs text-slate-500">{{ company.subscription.valid_until }}</p>
                        </div>
                    </div>

                    <div v-if="company.assignments?.length" class="mt-4 grid gap-3 lg:grid-cols-2">
                        <div v-for="assignment in company.assignments" :key="assignment.uuid" class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                            <div class="flex items-center justify-between gap-2">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">{{ assignment.workspace_name || $t('Not set') }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ assignment.workspace_type === 'branch' ? $t('Branch workspace') : $t('Main workspace') }}</p>
                                </div>
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-700">{{ localizeKnownValue(assignment.role_name) }}</span>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2 text-xs text-slate-600">
                                <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1">
                                    {{ localizeStatus(assignment.status_label) }}
                                </span>
                                <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1">
                                    {{ assignment.role_permissions?.label || $t('Not set') }}
                                </span>
                                <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1">
                                    {{ assignment.subscription?.plan_name || $t('No active subscription') }}
                                </span>
                                <span v-if="assignment.subscription?.managed_from_parent" class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-amber-700">
                                    {{ $t('Managed from parent subscription') }}
                                </span>
                            </div>
                            <p v-if="assignment.assigned_at" class="mt-3 text-xs text-slate-500">
                                {{ $t('Assigned at') }}: {{ assignment.assigned_at }}
                            </p>
                        </div>
                    </div>
                </article>
            </div>
                <div v-else class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                    {{ $t('No company access yet') }}
                </div>
        </UiSectionCard>

        <div class="grid gap-6 xl:grid-cols-2">
            <UiSectionCard :title="$t('Workspace memberships')">
                <div v-if="user.direct_workspaces?.length" class="space-y-3">
                    <article v-for="workspace in user.direct_workspaces" :key="workspace.id" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900">{{ workspace.workspace_name || $t('Not set') }}</h3>
                                <p class="mt-1 text-xs text-slate-500">{{ workspace.workspace_type === 'branch' ? $t('Branch workspace') : $t('Main workspace') }}</p>
                            </div>
                            <span class="rounded-full bg-white px-3 py-1 text-xs text-slate-700">{{ localizeKnownValue(workspace.role_name) }}</span>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2 text-xs text-slate-600">
                            <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-slate-600">
                                {{ workspace.access_source_label }}
                            </span>
                            <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-slate-600">
                                {{ workspace.subscription?.plan_name || $t('No active subscription') }}
                            </span>
                            <span class="rounded-full px-3 py-1 font-medium" :class="subscriptionTone(workspace.subscription?.status_key)">
                                {{ workspace.subscription?.status_label || $t('No active subscription') }}
                            </span>
                            <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-slate-600">
                                {{ workspace.role_permissions?.label || $t('Not set') }}
                            </span>
                            <span v-if="workspace.subscription?.managed_from_parent" class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-amber-700">
                                {{ $t('Managed from parent subscription') }}
                            </span>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-xs text-slate-500">
                            <span>{{ localizeStatus(workspace.status_label) }}</span>
                            <span>{{ workspace.created_at || $t('Not set') }}</span>
                        </div>
                    </article>
                </div>
                <div v-else class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                    {{ $t('No direct workspaces') }}
                </div>
            </UiSectionCard>

            <UiSectionCard :title="$t('Workspace invitations')">
                <div v-if="user.workspace_invites?.length" class="space-y-3">
                    <article v-for="invite in user.workspace_invites" :key="`${invite.organization_name}-${invite.expire_at}`" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ invite.organization_name || $t('Not set') }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ localizeKnownValue(invite.role_name) }}</p>
                            </div>
                            <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs text-slate-600">
                                {{ invite.expire_at || $t('Not set') }}
                            </span>
                        </div>
                    </article>
                </div>
                <div v-else class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
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

function subscriptionTone(statusKey) {
    if (statusKey === 'active' || statusKey === 'trial') {
        return 'bg-emerald-100 text-emerald-700';
    }

    if (statusKey === 'billing_pending') {
        return 'bg-sky-100 text-sky-700';
    }

    if (statusKey === 'expired' || statusKey === 'inactive' || statusKey === 'none') {
        return 'bg-amber-100 text-amber-700';
    }

    return 'bg-slate-100 text-slate-700';
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
