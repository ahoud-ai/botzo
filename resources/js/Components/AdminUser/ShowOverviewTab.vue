<template>
    <div class="space-y-6">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <UiStatCard class="ui-kpi--compact" :title="$t('Companies')" :value="user.counts?.companies ?? 0">
                <template #icon><Building2 class="h-5 w-5" /></template>
            </UiStatCard>
            <UiStatCard class="ui-kpi--compact" :title="$t('Workspaces')" :value="user.counts?.workspaces ?? 0">
                <template #icon><Network class="h-5 w-5" /></template>
            </UiStatCard>
            <UiStatCard class="ui-kpi--compact" :title="$t('Main organization owners')" :value="user.counts?.main_owned ?? 0">
                <template #icon><Crown class="h-5 w-5" /></template>
            </UiStatCard>
            <UiStatCard class="ui-kpi--compact" :title="$t('Pending invites')" :value="pendingInviteCount">
                <template #icon><Clock3 class="h-5 w-5" /></template>
            </UiStatCard>
        </div>

        <UiSectionCard :title="$t('Account summary')">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div v-for="row in overviewRows" :key="row.label" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="text-xs font-medium text-slate-500">{{ row.label }}</p>
                    <p class="mt-1 break-words text-sm font-semibold text-slate-900">{{ row.value }}</p>
                </div>
            </div>

            <div class="mt-4 rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-4">
                <p class="text-xs font-medium text-slate-500">{{ $t('Address details') }}</p>
                <div v-if="addressLines.length" class="mt-2 space-y-1 text-sm text-slate-700">
                    <p v-for="line in addressLines" :key="line">{{ line }}</p>
                </div>
                <p v-else class="mt-2 text-sm text-slate-500">{{ $t('Not set') }}</p>
            </div>
        </UiSectionCard>

        <UiSectionCard :title="$t('Ownership review')">
            <div class="grid gap-4 lg:grid-cols-2">
                <article class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-indigo-100 text-indigo-700">
                            <Crown class="h-5 w-5" />
                        </span>
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900">{{ $t('Main organization ownership') }}</h3>
                            <p class="text-xs text-slate-500">{{ user.counts?.main_owned ?? 0 }} {{ $t('organization(s)') }}</p>
                        </div>
                    </div>

                    <div v-if="user.owned_main_organizations?.length" class="mt-4 flex flex-wrap gap-2">
                        <span
                            v-for="organization in user.owned_main_organizations"
                            :key="organization.uuid"
                            class="rounded-full bg-indigo-100 px-3 py-1 text-xs font-medium text-indigo-700"
                        >
                            {{ organization.name }}
                        </span>
                    </div>
                    <p v-else class="mt-4 text-sm text-slate-500">{{ $t('No main ownership') }}</p>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-amber-100 text-amber-700">
                            <ShieldAlert class="h-5 w-5" />
                        </span>
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900">{{ $t('Branch owner anomalies') }}</h3>
                            <p class="text-xs text-slate-500">{{ user.counts?.branch_owner_anomalies ?? 0 }} {{ $t('workspace(s)') }}</p>
                        </div>
                    </div>

                    <div v-if="user.branch_owner_anomalies?.length" class="mt-4 flex flex-wrap gap-2">
                        <span
                            v-for="organization in user.branch_owner_anomalies"
                            :key="organization.uuid"
                            class="rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700"
                        >
                            {{ organization.name }}
                        </span>
                    </div>
                    <p v-else class="mt-4 text-sm text-slate-500">{{ $t('No branch owner issues') }}</p>
                </article>
            </div>
        </UiSectionCard>

        <UiSectionCard v-if="businessRules.length" :title="$t('Business rules')">
            <div class="grid gap-3 lg:grid-cols-2">
                <article v-for="rule in businessRules" :key="rule.message" class="rounded-2xl border px-4 py-3 text-sm" :class="rule.level === 'warning' ? 'border-amber-200 bg-amber-50 text-amber-950' : 'border-sky-200 bg-sky-50 text-sky-900'">
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 inline-flex h-8 w-8 items-center justify-center rounded-2xl" :class="rule.level === 'warning' ? 'bg-amber-100 text-amber-700' : 'bg-sky-100 text-sky-700'">
                            <AlertTriangle v-if="rule.level === 'warning'" class="h-4 w-4" />
                            <InfoIcon v-else class="h-4 w-4" />
                        </span>
                        <p class="leading-6">{{ rule.message }}</p>
                    </div>
                </article>
            </div>
        </UiSectionCard>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { AlertTriangle, Building2, Clock3, Crown, Info as InfoIcon, Network, ShieldAlert } from 'lucide-vue-next';
import UiSectionCard from '@/Components/UI/UiSectionCard.vue';
import UiStatCard from '@/Components/UI/UiStatCard.vue';

const { t } = useI18n();

const props = defineProps({
    user: { type: Object, required: true },
});

const user = computed(() => props.user);
const businessRules = computed(() => Array.isArray(user.value.business_rules) ? user.value.business_rules : []);
const pendingInviteCount = computed(() => (user.value.counts?.pending_company_invites ?? 0) + (user.value.counts?.workspace_invites ?? 0));

const overviewRows = computed(() => [
    { label: t('Organization role'), value: user.value.type?.organization_role_label || t('Not set') },
    { label: t('Access summary'), value: user.value.access_summary || t('No access') },
    { label: t('Verified at'), value: user.value.email_verified_at || t('Not set') },
    { label: t('Last updated'), value: user.value.updated_at || t('Not set') },
]);

const addressLines = computed(() => {
    const values = [
        user.value.address?.street,
        user.value.address?.city,
        user.value.address?.state,
        user.value.address?.zip,
        user.value.address?.country,
    ];

    return values.filter((value) => String(value ?? '').trim() !== '');
});
</script>
