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
                <div v-for="row in overviewRows" :key="row.label" class="usr-tile">
                    <p class="usr-tile-label">{{ row.label }}</p>
                    <p class="usr-tile-value break-words">{{ row.value }}</p>
                </div>
            </div>

            <div class="usr-tile usr-tile--dashed mt-4">
                <p class="usr-tile-label">{{ $t('Address details') }}</p>
                <div v-if="addressLines.length" class="mt-2 space-y-1 text-sm usr-tile-value">
                    <p v-for="line in addressLines" :key="line">{{ line }}</p>
                </div>
                <p v-else class="mt-2 text-sm usr-muted">{{ $t('Not set') }}</p>
            </div>
        </UiSectionCard>

        <UiSectionCard :title="$t('Ownership review')">
            <div class="grid gap-4 lg:grid-cols-2">
                <article class="usr-review-card">
                    <div class="flex items-center gap-3">
                        <span class="usr-review-icon usr-review-icon--success">
                            <Crown class="h-5 w-5" />
                        </span>
                        <div>
                            <h3 class="usr-review-title">{{ $t('Main organization ownership') }}</h3>
                            <p class="usr-muted text-xs">{{ user.counts?.main_owned ?? 0 }} {{ $t('organization(s)') }}</p>
                        </div>
                    </div>

                    <div v-if="user.owned_main_organizations?.length" class="mt-4 flex flex-wrap gap-2">
                        <span
                            v-for="organization in user.owned_main_organizations"
                            :key="organization.uuid"
                            class="usr-chip ui-chip-success"
                        >
                            {{ organization.name }}
                        </span>
                    </div>
                    <p v-else class="mt-4 text-sm usr-muted">{{ $t('No main ownership') }}</p>
                </article>

                <article class="usr-review-card">
                    <div class="flex items-center gap-3">
                        <span class="usr-review-icon usr-review-icon--warning">
                            <ShieldAlert class="h-5 w-5" />
                        </span>
                        <div>
                            <h3 class="usr-review-title">{{ $t('Branch owner anomalies') }}</h3>
                            <p class="usr-muted text-xs">{{ user.counts?.branch_owner_anomalies ?? 0 }} {{ $t('workspace(s)') }}</p>
                        </div>
                    </div>

                    <div v-if="user.branch_owner_anomalies?.length" class="mt-4 flex flex-wrap gap-2">
                        <span
                            v-for="organization in user.branch_owner_anomalies"
                            :key="organization.uuid"
                            class="usr-chip ui-chip-warning"
                        >
                            {{ organization.name }}
                        </span>
                    </div>
                    <p v-else class="mt-4 text-sm usr-muted">{{ $t('No branch owner issues') }}</p>
                </article>
            </div>
        </UiSectionCard>

        <UiSectionCard v-if="businessRules.length" :title="$t('Business rules')">
            <div class="grid gap-3 lg:grid-cols-2">
                <article v-for="rule in businessRules" :key="rule.message" class="usr-banner" :class="rule.level === 'warning' ? 'usr-banner--warning' : 'usr-banner--info'">
                    <div class="flex items-start gap-3">
                        <span class="usr-banner-icon" :class="rule.level === 'warning' ? 'usr-banner-icon--warning' : 'usr-banner-icon--info'">
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

<style scoped>
.usr-tile {
    border-radius: 0.9rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
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
    font-size: 0.92rem;
    font-weight: 700;
    color: var(--ui-text);
}

.usr-muted {
    color: var(--ui-muted);
}

.usr-review-card {
    border-radius: 1rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    padding: 1.1rem;
}

.usr-review-icon {
    display: inline-flex;
    height: 2.5rem;
    width: 2.5rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 0.9rem;
}

.usr-review-icon--success {
    background: color-mix(in srgb, var(--ui-success) 16%, transparent);
    color: color-mix(in srgb, var(--ui-success) 76%, #0f172a);
}

.usr-review-icon--warning {
    background: color-mix(in srgb, var(--ui-warning) 20%, transparent);
    color: color-mix(in srgb, var(--ui-warning) 78%, #0f172a);
}

.usr-review-title {
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--ui-text);
}

.usr-chip {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.3rem 0.75rem;
    font-size: 0.78rem;
    font-weight: 700;
}

.usr-banner {
    border: 1px solid;
    border-radius: 1rem;
    padding: 0.9rem 1.05rem;
    font-size: 0.88rem;
}

.usr-banner--info {
    border-color: color-mix(in srgb, var(--ui-primary) 30%, var(--ui-border));
    background: color-mix(in srgb, var(--ui-primary) 8%, var(--ui-surface));
    color: var(--ui-text);
}

.usr-banner--warning {
    border-color: color-mix(in srgb, var(--ui-warning) 35%, var(--ui-border));
    background: color-mix(in srgb, var(--ui-warning) 10%, var(--ui-surface));
    color: var(--ui-text);
}

.usr-banner-icon {
    display: inline-flex;
    height: 2rem;
    width: 2rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 0.8rem;
    margin-top: 0.1rem;
}

.usr-banner-icon--info {
    background: color-mix(in srgb, var(--ui-primary) 16%, transparent);
    color: var(--ui-primary);
}

.usr-banner-icon--warning {
    background: color-mix(in srgb, var(--ui-warning) 20%, transparent);
    color: color-mix(in srgb, var(--ui-warning) 80%, #0f172a);
}
</style>
