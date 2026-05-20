<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full">
            <UiPageHeader :title="$t('Users')" :subtitle="$t('Review account status, ownership, and organization access.')">
                <template #actions>
                    <Link v-if="adminCan('customers', 'create')" href="/admin/users/create" class="rounded-xl bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-500">
                        {{ $t('Add user') }}
                    </Link>
                </template>
            </UiPageHeader>

            <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <UiStatCard class="ui-kpi--compact" :title="$t('Users')" :value="props.summary?.users ?? 0"><template #icon><Users2 class="h-5 w-5" /></template></UiStatCard>
                <UiStatCard class="ui-kpi--compact" :title="$t('Inactive')" :value="props.summary?.inactive ?? 0"><template #icon><CircleOff class="h-5 w-5" /></template></UiStatCard>
                <UiStatCard class="ui-kpi--compact" :title="$t('Main organization owners')" :value="props.summary?.main_owners ?? 0"><template #icon><Crown class="h-5 w-5" /></template></UiStatCard>
                <UiStatCard class="ui-kpi--compact" :title="$t('Company employees')" :value="props.summary?.company_members ?? 0"><template #icon><Building2 class="h-5 w-5" /></template></UiStatCard>
            </div>

            <section class="mt-6 rounded-[1.25rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_220px_220px_220px_auto]">
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 start-3 flex items-center text-slate-400">
                            <Search class="h-4 w-4" />
                        </span>
                        <input v-model="localFilters.search" type="text" class="w-full rounded-xl border border-slate-200 bg-white py-3 pe-4 ps-10 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" :placeholder="$t('Search by name, email, phone, or organization')" @keydown.enter.prevent="applyFilters">
                    </div>

                    <select v-model="localFilters.account_status" class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" @change="applyFilters">
                        <option v-for="option in props.filterOptions?.account_status ?? []" :key="`status-${option.value}`" :value="option.value">{{ option.label }}</option>
                    </select>

                    <select v-model="localFilters.verification" class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" @change="applyFilters">
                        <option v-for="option in props.filterOptions?.verification ?? []" :key="`verification-${option.value}`" :value="option.value">{{ option.label }}</option>
                    </select>

                    <select v-model="localFilters.access_scope" class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-indigo-300 focus:ring-2 focus:ring-indigo-100" @change="applyFilters">
                        <option v-for="option in props.filterOptions?.access_scope ?? []" :key="`scope-${option.value}`" :value="option.value">{{ option.label }}</option>
                    </select>

                    <button type="button" class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-100" @click="resetFilters">
                        {{ $t('Reset filters') }}
                    </button>
                </div>
            </section>

            <section class="mt-6 overflow-hidden rounded-[1.25rem] border border-slate-200 bg-white shadow-sm shadow-slate-200/60">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50/90">
                            <tr>
                                <th class="px-4 py-3 text-start text-[11px] font-semibold text-slate-500">{{ $t('User') }}</th>
                                <th class="px-4 py-3 text-start text-[11px] font-semibold text-slate-500">{{ $t('Role') }}</th>
                                <th class="px-4 py-3 text-start text-[11px] font-semibold text-slate-500">{{ $t('Access') }}</th>
                                <th class="px-4 py-3 text-start text-[11px] font-semibold text-slate-500">{{ $t('Status') }}</th>
                                <th class="px-4 py-3 text-start text-[11px] font-semibold text-slate-500">{{ $t('Last updated') }}</th>
                                <th class="px-4 py-3 text-end text-[11px] font-semibold text-slate-500">{{ $t('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            <tr v-for="row in rows" :key="row.id" class="align-top transition hover:bg-slate-50/70">
                                <td class="px-4 py-4">
                                    <div class="flex items-start gap-3.5">
                                        <span class="mt-0.5 inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 text-slate-600">
                                            <UserRound class="h-5 w-5" />
                                        </span>
                                        <div class="min-w-0 space-y-1">
                                            <div class="truncate text-sm font-semibold text-slate-900">{{ row.full_name }}</div>
                                            <div class="truncate text-sm text-slate-600">{{ row.email }}</div>
                                            <div v-if="row.phone" class="text-xs text-slate-400">{{ row.phone }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="space-y-2">
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold" :class="roleBadgeClass(row)">
                                            {{ compactRoleLabel(row) }}
                                        </span>
                                        <div v-if="roleSecondaryText(row)" class="text-xs text-slate-500">{{ roleSecondaryText(row) }}</div>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="space-y-2">
                                        <div class="flex flex-wrap gap-2">
                                            <span
                                                v-for="badge in accessBadges(row)"
                                                :key="`${row.id}-${badge.label}`"
                                                class="inline-flex rounded-full px-3 py-1 text-xs font-medium"
                                                :class="badge.tone === 'warning' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-700'"
                                            >
                                                {{ badge.label }}
                                            </span>
                                        </div>
                                        <div v-if="accessHint(row)" class="text-xs text-slate-500">{{ accessHint(row) }}</div>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium" :class="row.account_status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-700'">
                                            {{ row.account_status_label }}
                                        </span>
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium" :class="row.email_verified ? 'bg-sky-50 text-sky-700' : 'bg-amber-50 text-amber-700'">
                                            {{ row.email_verified ? $t('verified') : $t('unverified') }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="space-y-1 text-sm text-slate-600">
                                        <div>{{ formatUpdated(row.updated_at).date }}</div>
                                        <div v-if="formatUpdated(row.updated_at).time" class="text-xs text-slate-400">{{ formatUpdated(row.updated_at).time }}</div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-end">
                                    <div class="flex justify-end gap-2">
                                        <Link :href="`/admin/users/${row.id}`" class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                                            <Eye class="h-4 w-4" />
                                            {{ $t('View') }}
                                        </Link>
                                        <Dropdown v-if="canManageRow(row)" align="end">
                                            <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-slate-700">
                                                <MoreHorizontal class="h-4 w-4" />
                                                <span class="sr-only">{{ $t('Actions') }}</span>
                                            </button>

                                            <template #items>
                                                <DropdownItemGroup>
                                                    <DropdownItem
                                                        v-if="adminCan('customers', 'edit')"
                                                        as="button"
                                                        @click="openActionModal(row, row.account_status === 'active' ? 'suspend' : 'restore')"
                                                    >
                                                        <span class="flex items-center gap-2" :class="row.account_status === 'active' ? 'text-amber-700' : 'text-sky-700'">
                                                            <Ban v-if="row.account_status === 'active'" class="h-4 w-4" />
                                                            <ShieldCheck v-else class="h-4 w-4" />
                                                            {{ row.account_status === 'active' ? $t('Suspend') : $t('Restore') }}
                                                        </span>
                                                    </DropdownItem>
                                                    <DropdownItem
                                                        v-if="adminCan('customers', 'delete') && row.can_delete_account"
                                                        as="button"
                                                        @click="openActionModal(row, 'delete')"
                                                    >
                                                        <span class="flex items-center gap-2 text-rose-700">
                                                            <Trash2 class="h-4 w-4" />
                                                            {{ $t('Delete') }}
                                                        </span>
                                                    </DropdownItem>
                                                </DropdownItemGroup>
                                            </template>
                                        </Dropdown>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="rows.length === 0">
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">{{ $t('No users found for the current filters.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination v-if="props.rows?.links" class="border-t border-slate-200 p-4" :pagination="props.rows" />
            </section>

            <AlertModal v-model="isActionModalOpen" :label="actionModal.label" :description="actionModal.description" :confirm-button-text="actionModal.confirmText" :confirm-button-class="actionModal.confirmClass" @confirm="confirmAction" />
        </div>
    </AppLayout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Ban, Building2, CircleOff, Crown, Eye, MoreHorizontal, Search, ShieldCheck, Trash2, UserRound, Users2 } from 'lucide-vue-next';
import AppLayout from './../Layout/App.vue';
import AlertModal from '@/Components/AlertModal.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownItem from '@/Components/DropdownItem.vue';
import DropdownItemGroup from '@/Components/DropdownItemGroup.vue';
import Pagination from '@/Components/Pagination.vue';
import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
import UiStatCard from '@/Components/UI/UiStatCard.vue';
import { useAdminPermission } from '@/Composables/useAdminPermission';

const props = defineProps({
    title: String,
    allowCreate: Boolean,
    rows: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    filterOptions: { type: Object, default: () => ({}) },
});

const { adminCan } = useAdminPermission();
const { t } = useI18n();
const rows = computed(() => Array.isArray(props.rows?.data) ? props.rows.data : []);
const isActionModalOpen = ref(false);
const targetRow = ref(null);
const pendingAction = ref(null);

const localFilters = reactive({
    search: props.filters?.search ?? '',
    account_status: props.filters?.account_status ?? '',
    verification: props.filters?.verification ?? '',
    access_scope: props.filters?.access_scope ?? '',
});

const actionModal = computed(() => {
    if (pendingAction.value === 'delete') {
        return {
            label: t('Delete user?'),
            description: t('This removes the platform account and linked access records. This action cannot be undone.'),
            confirmText: t('Delete'),
            confirmClass: 'bg-rose-600 hover:bg-rose-500',
        };
    }

    if (pendingAction.value === 'restore') {
        return {
            label: t('Restore this account?'),
            description: t('Sign-in will be available again. Company memberships stay unchanged.'),
            confirmText: t('Restore'),
            confirmClass: 'bg-sky-600 hover:bg-sky-500',
        };
    }

    return {
        label: t('Suspend this account?'),
        description: t('This blocks sign-in only. Company memberships stay unchanged.'),
        confirmText: t('Suspend'),
        confirmClass: 'bg-amber-600 hover:bg-amber-500',
    };
});

function applyFilters() {
    router.get('/admin/users', { ...localFilters }, { preserveState: true, preserveScroll: true, replace: true });
}

function resetFilters() {
    localFilters.search = '';
    localFilters.account_status = '';
    localFilters.verification = '';
    localFilters.access_scope = '';
    applyFilters();
}

function openActionModal(row, action) {
    targetRow.value = row;
    pendingAction.value = action;
    isActionModalOpen.value = true;
}

function roleBadgeClass(row) {
    if (row.organization_role === 'owner') {
        return 'bg-indigo-50 text-indigo-700';
    }

    if (row.organization_role === 'employee') {
        return 'bg-sky-50 text-sky-700';
    }

    return 'bg-slate-100 text-slate-600';
}

function compactRoleLabel(row) {
    if (row.organization_role === 'owner') {
        return t('Owner');
    }

    if (row.organization_role === 'employee') {
        return t('Employee');
    }

    return t('No access');
}

function roleSecondaryText(row) {
    if (row.organization_role === 'unassigned') {
        return null;
    }

    if (row.companies_count === 1 && row.primary_company_name) {
        return row.primary_company_name;
    }

    if (row.user_type === 'branch_member') {
        return t('Branch workspace member');
    }

    if (row.user_type === 'workspace_member') {
        return t('Workspace member');
    }

    return null;
}

function countLabel(count, singularKey, pluralKey) {
    if (count <= 0) {
        return null;
    }

    return `${count} ${count === 1 ? t(singularKey) : t(pluralKey)}`;
}

function accessBadges(row) {
    const badges = [];
    const companyLabel = countLabel(row.companies_count ?? 0, 'Company', 'Companies');
    const workspaceLabel = countLabel(row.workspaces_count ?? 0, 'Workspace', 'Workspaces');

    if (companyLabel) {
        badges.push({ label: companyLabel, tone: 'default' });
    }

    if (workspaceLabel) {
        badges.push({ label: workspaceLabel, tone: 'default' });
    }

    if ((row.subscription_issue_count ?? 0) > 0) {
        badges.push({ label: t('Billing review'), tone: 'warning' });
    }

    if (badges.length === 0) {
        badges.push({ label: t('No access'), tone: 'default' });
    }

    return badges;
}

function accessHint(row) {
    if ((row.subscription_issue_count ?? 0) > 0) {
        return t('Subscription attention needed');
    }

    if ((row.companies_count ?? 0) > 1 && row.primary_company_name) {
        return row.primary_company_name;
    }

    return null;
}

function formatUpdated(value) {
    const normalized = String(value ?? '').trim();

    if (normalized === '') {
        return { date: t('Not set'), time: null };
    }

    const parts = normalized.split(' ');

    if (parts.length >= 3) {
        return {
            date: parts.slice(0, 1).join(' '),
            time: parts.slice(1).join(' '),
        };
    }

    return { date: normalized, time: null };
}

function canManageRow(row) {
    return adminCan('customers', 'edit') || (adminCan('customers', 'delete') && row.can_delete_account);
}

function confirmAction() {
    if (!targetRow.value || !pendingAction.value) {
        return;
    }

    if (pendingAction.value === 'delete') {
        router.delete(`/admin/users/${targetRow.value.id}`, { preserveScroll: true, onFinish: closeActionModal });
        return;
    }

    const endpoint = pendingAction.value === 'restore'
        ? `/admin/users/${targetRow.value.id}/restore`
        : `/admin/users/${targetRow.value.id}/suspend`;

    router.patch(endpoint, {}, { preserveScroll: true, onFinish: closeActionModal });
}

function closeActionModal() {
    isActionModalOpen.value = false;
    targetRow.value = null;
    pendingAction.value = null;
}
</script>
