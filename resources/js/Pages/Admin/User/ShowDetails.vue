<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full">
            <section class="usr-hero">
                <div class="usr-hero-wash"></div>

                <div class="relative flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between">
                    <div class="flex min-w-0 items-start gap-4">
                        <span v-if="!user.avatar" class="usr-hero-avatar">
                            <UserRound class="h-9 w-9" />
                        </span>
                        <img v-else class="usr-hero-avatar-img" :src="`/media/${user.avatar}`" :alt="user.full_name">

                        <div class="min-w-0 space-y-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <h1 class="usr-hero-title truncate">{{ user.full_name }}</h1>
                                <span class="usr-chip" :class="user.status === 'active' ? 'ui-chip-success' : 'ui-chip-danger'">
                                    <ShieldCheck v-if="user.status === 'active'" class="h-3.5 w-3.5" />
                                    <CircleOff v-else class="h-3.5 w-3.5" />
                                    {{ user.status_label }}
                                </span>
                                <span class="usr-chip" :class="user.email_verified ? 'ui-chip-info' : 'ui-chip-warning'">
                                    <MailCheck v-if="user.email_verified" class="h-3.5 w-3.5" />
                                    <MailQuestion v-else class="h-3.5 w-3.5" />
                                    {{ user.email_verified ? $t('Verified email') : $t('Unverified email') }}
                                </span>
                            </div>

                            <div class="space-y-1">
                                <p class="usr-hero-role">{{ user.type?.organization_role_label || user.type?.primary_label }}</p>
                                <p class="usr-hero-subtitle">{{ user.access_summary || $t('No access') }}</p>
                            </div>

                            <div class="flex flex-wrap gap-2.5 text-xs">
                                <span class="usr-pill">
                                    <Mail class="h-3.5 w-3.5" />
                                    {{ user.email }}
                                </span>
                                <span class="usr-pill">
                                    <Phone class="h-3.5 w-3.5" />
                                    {{ user.phone || $t('Not set') }}
                                </span>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-3">
                                <div v-for="item in headerStats" :key="item.label" class="usr-tile">
                                    <div class="usr-tile-head">
                                        <component :is="item.icon" class="h-3.5 w-3.5" />
                                        <span class="usr-tile-label">{{ item.label }}</span>
                                    </div>
                                    <p class="usr-tile-value">{{ item.value }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <Link href="/admin/users" class="usr-btn usr-btn--ghost">
                            <ArrowLeft class="h-4 w-4" />
                            {{ $t('Back') }}
                        </Link>
                        <button v-if="canEditAccount" type="button" class="usr-btn usr-btn--ghost" @click="activeTab = 'edit'">
                            <PencilLine class="h-4 w-4" />
                            {{ $t('Edit') }}
                        </button>
                        <button v-if="canToggleAccountState" type="button" class="usr-btn" :class="user.actions?.can_restore_account ? 'usr-btn--info' : 'usr-btn--warning'" @click="isStateModalOpen = true">
                            <ShieldCheck v-if="user.actions?.can_restore_account" class="h-4 w-4" />
                            <Ban v-else class="h-4 w-4" />
                            {{ user.actions?.can_restore_account ? $t('Restore') : $t('Suspend') }}
                        </button>
                        <button v-if="canDeleteAccount" type="button" class="usr-btn usr-btn--danger-ghost" @click="isDeleteModalOpen = true">
                            <Trash2 class="h-4 w-4" />
                            {{ $t('Delete') }}
                        </button>
                    </div>
                </div>

                <div v-if="primaryBusinessRule" class="usr-banner relative mt-5" :class="primaryBusinessRule.level === 'warning' ? 'usr-banner--warning' : 'usr-banner--info'">
                    <div class="flex items-start gap-3">
                        <span class="usr-banner-icon" :class="primaryBusinessRule.level === 'warning' ? 'usr-banner-icon--warning' : 'usr-banner-icon--info'">
                            <AlertTriangle v-if="primaryBusinessRule.level === 'warning'" class="h-4 w-4" />
                            <InfoIcon v-else class="h-4 w-4" />
                        </span>
                        <p class="leading-6">{{ primaryBusinessRule.message }}</p>
                    </div>
                </div>
            </section>

            <section class="usr-panel mt-6">
                <div class="usr-tabbar">
                    <div class="usr-filter-group">
                        <button v-for="tab in tabs" :key="tab.key" type="button" class="usr-filter-pill" :class="{ 'usr-filter-pill--active': activeTab === tab.key }" @click="activeTab = tab.key">
                            <component :is="tab.icon" class="h-4 w-4" />
                            {{ tab.label }}
                            <span v-if="tab.count !== null" class="usr-tab-count" :class="{ 'usr-tab-count--active': activeTab === tab.key }">{{ tab.count }}</span>
                        </button>
                    </div>
                </div>

                <div class="p-4 md:p-6">
                    <ShowOverviewTab v-show="activeTab === 'overview'" :user="user" />
                    <ShowAccessTab v-show="activeTab === 'access'" :user="user" />
                    <ShowEditTab
                        v-show="activeTab === 'edit'"
                        :user="user"
                        :form="form"
                        :allowed-phone-countries="allowedPhoneCountries"
                        :can-toggle-account-state="canToggleAccountState"
                        :can-delete-account="canDeleteAccount"
                        @submit="submitForm"
                        @open-state-modal="isStateModalOpen = true"
                        @open-delete-modal="isDeleteModalOpen = true"
                    />
                </div>
            </section>

            <AlertModal v-model="isStateModalOpen" :label="$t(stateModalLabel)" :description="$t(stateModalDescription)" :confirm-button-text="$t(stateModalConfirmText)" :confirm-button-class="stateModalConfirmClass" @confirm="toggleAccountState" />
            <AlertModal v-model="isDeleteModalOpen" :label="$t('Delete user?')" :description="$t('Deletes the platform account and linked access. This action cannot be undone.')" :confirm-button-text="$t('Delete')" :confirm-button-class="'bg-rose-600 hover:bg-rose-500'" @confirm="deleteUser" />
        </div>
    </AppLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { AlertTriangle, ArrowLeft, Ban, Building2, CircleOff, Clock3, Info as InfoIcon, Mail, MailCheck, MailQuestion, Network, PencilLine, Phone, ShieldCheck, Trash2, UserRound, Users2 } from 'lucide-vue-next';
import AppLayout from './../Layout/App.vue';
import AlertModal from '@/Components/AlertModal.vue';
import ShowAccessTab from '@/Components/AdminUser/ShowAccessTab.vue';
import ShowEditTab from '@/Components/AdminUser/ShowEditTab.vue';
import ShowOverviewTab from '@/Components/AdminUser/ShowOverviewTab.vue';
import { useAdminPermission } from '@/Composables/useAdminPermission';

const props = defineProps({
    title: String,
    initialTab: { type: String, default: 'overview' },
    user: { type: Object, required: true },
});
const { adminCan } = useAdminPermission();
const { t } = useI18n();
const allowedPhoneCountries = Array.isArray(usePage().props.phoneCountries) ? usePage().props.phoneCountries : [];
const allowedTabs = ['overview', 'access', 'edit'];
const activeTab = ref(allowedTabs.includes(props.initialTab) ? props.initialTab : 'overview');
const isDeleteModalOpen = ref(false);
const isStateModalOpen = ref(false);
const user = computed(() => props.user);
const form = useForm({
    _method: 'put',
    first_name: user.value.first_name,
    last_name: user.value.last_name,
    email: user.value.email,
    phone: user.value.phone,
    avatar: undefined,
    street: user.value.address?.street ?? null,
    city: user.value.address?.city ?? null,
    state: user.value.address?.state ?? null,
    zip: user.value.address?.zip ?? null,
    country: user.value.address?.country ?? null,
    password: null,
    password_confirmation: null,
});
const canEditAccount = computed(() => adminCan('customers', 'edit'));
const canDeleteAccount = computed(() => adminCan('customers', 'delete'));
const canToggleAccountState = computed(() => adminCan('customers', 'edit') && (user.value.actions?.can_suspend_account || user.value.actions?.can_restore_account));
const pendingInviteCount = computed(() => (user.value.counts?.pending_company_invites ?? 0) + (user.value.counts?.workspace_invites ?? 0));
const businessRules = computed(() => Array.isArray(user.value.business_rules) ? user.value.business_rules : []);
const primaryBusinessRule = computed(() => businessRules.value[0] ?? null);
const headerStats = computed(() => [
    { label: t('Companies'), value: user.value.counts?.companies ?? 0, icon: Building2 },
    { label: t('Workspaces'), value: user.value.counts?.workspaces ?? 0, icon: Network },
    { label: t('Pending invites'), value: pendingInviteCount.value, icon: Clock3 },
]);
const tabs = computed(() => [
    { key: 'overview', label: t('Overview'), icon: UserRound, count: null },
    { key: 'access', label: t('Access'), icon: Users2, count: (user.value.counts?.companies ?? 0) + (user.value.counts?.workspaces ?? 0) },
    { key: 'edit', label: t('Edit account'), icon: PencilLine, count: null },
]);
const stateModalLabel = computed(() => user.value.actions?.can_restore_account ? 'Restore this account?' : 'Suspend this account?');
const stateModalDescription = computed(() => user.value.actions?.can_restore_account ? 'Sign-in returns. Access stays the same.' : 'Only sign-in is blocked. Access stays the same.');
const stateModalConfirmText = computed(() => user.value.actions?.can_restore_account ? 'Restore' : 'Suspend');
const stateModalConfirmClass = computed(() => user.value.actions?.can_restore_account ? 'bg-sky-600 hover:bg-sky-500' : 'bg-amber-600 hover:bg-amber-500');

function submitForm() {
    form.post(`/admin/users/${user.value.id}?tab=edit`, {
        preserveScroll: true,
        preserveState: true,
        forceFormData: true,
    });
}

function deleteUser() {
    router.delete(`/admin/users/${user.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            isDeleteModalOpen.value = false;
        },
    });
}

function toggleAccountState() {
    const path = user.value.actions?.can_restore_account ? `/admin/users/${user.value.id}/restore` : `/admin/users/${user.value.id}/suspend`;

    router.patch(path, {}, {
        preserveScroll: true,
        onFinish: () => {
            isStateModalOpen.value = false;
        },
    });
}
</script>

<style scoped>
.usr-hero {
    position: relative;
    overflow: hidden;
    border: 1px solid var(--ui-border);
    border-radius: var(--ui-radius-xl, 1.5rem);
    background: var(--ui-surface);
    box-shadow: var(--ui-shadow-1);
    padding: 1.5rem;
}

@media (min-width: 768px) {
    .usr-hero {
        padding: 2rem;
    }
}

.usr-hero-wash {
    position: absolute;
    inset-inline: 0;
    top: 0;
    height: 8rem;
    background: linear-gradient(135deg, color-mix(in srgb, var(--ui-secondary) 12%, transparent), color-mix(in srgb, var(--ui-primary) 8%, transparent), transparent);
    pointer-events: none;
}

.usr-hero-avatar,
.usr-hero-avatar-img {
    position: relative;
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    width: 4.5rem;
    height: 4.5rem;
    border-radius: 1.4rem;
}

.usr-hero-avatar {
    background: linear-gradient(135deg, var(--ui-secondary), color-mix(in srgb, var(--ui-secondary) 70%, var(--ui-primary)));
    color: #fff;
    box-shadow: 0 10px 22px -12px color-mix(in srgb, var(--ui-secondary) 70%, transparent);
}

.usr-hero-avatar-img {
    object-fit: cover;
    border: 1px solid var(--ui-border);
}

.usr-hero-title {
    font-size: 1.5rem;
    font-weight: 800;
    letter-spacing: -0.01em;
    color: var(--ui-text);
}

.usr-hero-role {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--ui-text);
}

.usr-hero-subtitle {
    font-size: 0.85rem;
    color: var(--ui-muted);
}

.usr-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border-radius: 999px;
    padding: 0.3rem 0.75rem;
    font-size: 0.78rem;
    font-weight: 700;
    white-space: nowrap;
}

.usr-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border-radius: 999px;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    color: var(--ui-muted);
    padding: 0.4rem 0.85rem;
}

.usr-tile {
    border-radius: 0.9rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    padding: 0.8rem 0.95rem;
}

.usr-tile-head {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    color: var(--ui-muted);
}

.usr-tile-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    color: var(--ui-muted);
}

.usr-tile-value {
    margin-top: 0.25rem;
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--ui-text);
}

.usr-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    border-radius: 0.85rem;
    padding: 0.6rem 1.1rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: background-color 160ms ease, border-color 160ms ease, filter 160ms ease;
}

.usr-btn--ghost {
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    color: var(--ui-text);
}

.usr-btn--ghost:hover {
    background: var(--ui-surface-soft);
    border-color: var(--ui-border-strong);
}

.usr-btn--info {
    border: 1px solid transparent;
    background: var(--ui-primary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.usr-btn--info:hover {
    filter: brightness(1.1);
}

.usr-btn--warning {
    border: 1px solid transparent;
    background: var(--ui-warning);
    color: #1f2937;
    box-shadow: var(--ui-shadow-1);
}

.usr-btn--warning:hover {
    filter: brightness(1.05);
}

.usr-btn--danger-ghost {
    border: 1px solid color-mix(in srgb, var(--ui-danger) 40%, var(--ui-border));
    background: var(--ui-surface);
    color: var(--ui-danger);
}

.usr-btn--danger-ghost:hover {
    background: color-mix(in srgb, var(--ui-danger) 8%, var(--ui-surface));
}

.usr-banner {
    border: 1px solid;
    border-radius: 1rem;
    padding: 1rem 1.1rem;
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

.usr-panel {
    overflow: hidden;
    border: 1px solid var(--ui-border);
    border-radius: var(--ui-radius-xl, 1.5rem);
    background: var(--ui-surface);
    box-shadow: var(--ui-shadow-1);
}

.usr-tabbar {
    border-bottom: 1px solid var(--ui-border);
    padding: 1rem 1.25rem;
}

.usr-filter-group {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    padding: 0.3rem;
    border-radius: 999px;
    background: var(--ui-surface-soft);
    border: 1px solid var(--ui-border);
}

.usr-filter-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border-radius: 999px;
    padding: 0.55rem 1rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--ui-muted);
    background: transparent;
    border: 1px solid transparent;
    transition: background-color 160ms ease, color 160ms ease;
}

.usr-filter-pill:hover {
    color: var(--ui-text);
    background: var(--ui-border);
}

.usr-filter-pill--active {
    color: #fff;
    background: linear-gradient(135deg, var(--ui-secondary), color-mix(in srgb, var(--ui-secondary) 78%, var(--ui-primary)));
    box-shadow: 0 10px 20px -12px color-mix(in srgb, var(--ui-secondary) 60%, rgba(15, 23, 42, 0.5));
}

.usr-tab-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.3rem;
    border-radius: 999px;
    padding: 0.05rem 0.4rem;
    font-size: 0.68rem;
    font-weight: 700;
    background: var(--ui-surface);
    color: var(--ui-muted);
}

.usr-tab-count--active {
    background: rgba(255, 255, 255, 0.2);
    color: #fff;
}
</style>
