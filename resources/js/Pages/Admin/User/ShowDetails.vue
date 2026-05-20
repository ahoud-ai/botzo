<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full">
            <section class="relative overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-200/60 md:p-8">
                <div class="absolute inset-x-0 top-0 h-32 bg-[linear-gradient(135deg,rgba(99,102,241,0.10),rgba(14,165,233,0.08),transparent)]" />

                <div class="relative flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between">
                    <div class="flex min-w-0 items-start gap-4">
                        <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-3 shadow-sm">
                            <img v-if="user.avatar" class="h-20 w-20 rounded-[1.1rem] object-cover" :src="`/media/${user.avatar}`" :alt="user.full_name">
                            <div v-else class="flex h-20 w-20 items-center justify-center rounded-[1.1rem] bg-slate-100 text-slate-700">
                                <UserRound class="h-10 w-10" />
                            </div>
                        </div>

                        <div class="min-w-0 space-y-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <h1 class="truncate text-2xl font-semibold text-slate-950">{{ user.full_name }}</h1>
                                <span class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-medium" :class="user.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-700'">
                                    <ShieldCheck v-if="user.status === 'active'" class="h-3.5 w-3.5" />
                                    <CircleOff v-else class="h-3.5 w-3.5" />
                                    {{ user.status_label }}
                                </span>
                                <span class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-medium" :class="user.email_verified ? 'bg-sky-100 text-sky-700' : 'bg-amber-100 text-amber-700'">
                                    <MailCheck v-if="user.email_verified" class="h-3.5 w-3.5" />
                                    <MailQuestion v-else class="h-3.5 w-3.5" />
                                    {{ user.email_verified ? $t('Verified email') : $t('Unverified email') }}
                                </span>
                            </div>

                            <div class="space-y-1">
                                <p class="text-sm font-medium text-slate-700">{{ user.type?.organization_role_label || user.type?.primary_label }}</p>
                                <p class="text-sm text-slate-500">{{ user.access_summary || $t('No access') }}</p>
                            </div>

                            <div class="flex flex-wrap gap-2.5 text-xs text-slate-600">
                                <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5">
                                    <Mail class="h-3.5 w-3.5 text-slate-400" />
                                    {{ user.email }}
                                </span>
                                <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5">
                                    <Phone class="h-3.5 w-3.5 text-slate-400" />
                                    {{ user.phone || $t('Not set') }}
                                </span>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-3">
                                <div v-for="item in headerStats" :key="item.label" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                    <div class="flex items-center gap-2 text-xs font-medium text-slate-500">
                                        <component :is="item.icon" class="h-3.5 w-3.5 text-slate-400" />
                                        {{ item.label }}
                                    </div>
                                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ item.value }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <Link href="/admin/users" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                            <ArrowLeft class="h-4 w-4" />
                            {{ $t('Back') }}
                        </Link>
                        <button v-if="canEditAccount" type="button" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50" @click="activeTab = 'edit'">
                            <PencilLine class="h-4 w-4" />
                            {{ $t('Edit') }}
                        </button>
                        <button v-if="canToggleAccountState" type="button" class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition" :class="user.actions?.can_restore_account ? 'bg-sky-600 hover:bg-sky-500' : 'bg-amber-600 hover:bg-amber-500'" @click="isStateModalOpen = true">
                            <ShieldCheck v-if="user.actions?.can_restore_account" class="h-4 w-4" />
                            <Ban v-else class="h-4 w-4" />
                            {{ user.actions?.can_restore_account ? $t('Restore') : $t('Suspend') }}
                        </button>
                        <button v-if="canDeleteAccount" type="button" class="inline-flex items-center gap-2 rounded-xl border border-rose-200 bg-white px-4 py-2.5 text-sm font-medium text-rose-700 transition hover:bg-rose-50" @click="isDeleteModalOpen = true">
                            <Trash2 class="h-4 w-4" />
                            {{ $t('Delete') }}
                        </button>
                    </div>
                </div>

                <div v-if="primaryBusinessRule" class="relative mt-5 rounded-2xl border px-4 py-3 text-sm" :class="primaryBusinessRule.level === 'warning' ? 'border-amber-200 bg-amber-50 text-amber-950' : 'border-sky-200 bg-sky-50 text-sky-900'">
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 inline-flex h-8 w-8 items-center justify-center rounded-2xl" :class="primaryBusinessRule.level === 'warning' ? 'bg-amber-100 text-amber-700' : 'bg-sky-100 text-sky-700'">
                            <AlertTriangle v-if="primaryBusinessRule.level === 'warning'" class="h-4 w-4" />
                            <InfoIcon v-else class="h-4 w-4" />
                        </span>
                        <p class="leading-6">{{ primaryBusinessRule.message }}</p>
                    </div>
                </div>
            </section>

            <section class="mt-6 overflow-hidden rounded-[1.6rem] border border-slate-200 bg-white shadow-sm shadow-slate-200/60">
                <div class="border-b border-slate-200 px-4 py-4 md:px-6">
                    <div class="flex flex-wrap gap-2">
                        <button v-for="tab in tabs" :key="tab.key" type="button" class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium transition" :class="activeTab === tab.key ? 'bg-slate-950 text-white shadow-sm' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'" @click="activeTab = tab.key">
                            <component :is="tab.icon" class="h-4 w-4" />
                            {{ tab.label }}
                            <span v-if="tab.count !== null" class="rounded-full px-2 py-0.5 text-[11px]" :class="activeTab === tab.key ? 'bg-white/15 text-white' : 'bg-white text-slate-500'">{{ tab.count }}</span>
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
