<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full">
            <UiPageHeader :title="$t('Users')" :subtitle="$t('Review account status, ownership, and organization access.')">
                <template #actions>
                    <Link v-if="adminCan('customers', 'create')" href="/admin/users/create" class="usr-btn usr-btn--solid">
                        {{ $t('Add user') }}
                    </Link>
                </template>
            </UiPageHeader>

            <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4 usr-kpi-row">
                <UiStatCard class="ui-kpi--compact usr-kpi usr-kpi--primary" :title="$t('Users')" :value="props.summary?.users ?? 0"><template #icon><Users2 class="h-5 w-5" /></template></UiStatCard>
                <UiStatCard class="ui-kpi--compact usr-kpi usr-kpi--danger" :title="$t('Inactive')" :value="props.summary?.inactive ?? 0"><template #icon><CircleOff class="h-5 w-5" /></template></UiStatCard>
                <UiStatCard class="ui-kpi--compact usr-kpi usr-kpi--success" :title="$t('Main organization owners')" :value="props.summary?.main_owners ?? 0"><template #icon><Crown class="h-5 w-5" /></template></UiStatCard>
                <UiStatCard class="ui-kpi--compact usr-kpi usr-kpi--secondary" :title="$t('Company employees')" :value="props.summary?.company_members ?? 0"><template #icon><Building2 class="h-5 w-5" /></template></UiStatCard>
            </div>

            <div class="usr-toolbar mt-6">
                <div class="usr-toolbar-row">
                    <div class="ui-table-search usr-toolbar-search">
                        <span class="ui-table-search-icon">
                            <Search class="h-4 w-4" />
                        </span>
                        <input v-model="localFilters.search" type="text" class="ui-table-search-input" :placeholder="$t('Search by name, email, phone, or organization')" @keydown.enter.prevent="applyFilters">
                    </div>

                    <select v-model="localFilters.access_scope" class="usr-toolbar-select" @change="applyFilters">
                        <option v-for="option in props.filterOptions?.access_scope ?? []" :key="`scope-${option.value}`" :value="option.value">{{ option.label }}</option>
                    </select>
                </div>

                <div class="usr-toolbar-divider"></div>

                <div class="usr-toolbar-row">
                    <div class="usr-filter-group">
                        <button
                            v-for="option in props.filterOptions?.account_status ?? []"
                            :key="`status-${option.value}`"
                            type="button"
                            class="usr-filter-pill"
                            :class="{ 'usr-filter-pill--active': localFilters.account_status === option.value }"
                            @click="setFilter('account_status', option.value)"
                        >
                            {{ option.label }}
                        </button>
                    </div>

                    <div class="usr-filter-group">
                        <button
                            v-for="option in props.filterOptions?.verification ?? []"
                            :key="`verification-${option.value}`"
                            type="button"
                            class="usr-filter-pill"
                            :class="{ 'usr-filter-pill--active': localFilters.verification === option.value }"
                            @click="setFilter('verification', option.value)"
                        >
                            {{ option.label }}
                        </button>
                    </div>

                    <button type="button" class="usr-toolbar-clear" @click="resetFilters">
                        {{ $t('Reset filters') }}
                    </button>
                </div>
            </div>

            <UiDataTableShell class="mt-6">
                <TableHeader>
                    <TableHeaderRow>
                        <TableHeaderRowItem :position="'first'">{{ $t('User') }}</TableHeaderRowItem>
                        <TableHeaderRowItem>{{ $t('Role') }}</TableHeaderRowItem>
                        <TableHeaderRowItem>{{ $t('Access') }}</TableHeaderRowItem>
                        <TableHeaderRowItem>{{ $t('Status') }}</TableHeaderRowItem>
                        <TableHeaderRowItem>{{ $t('Last updated') }}</TableHeaderRowItem>
                        <TableHeaderRowItem :position="'last'">{{ $t('Actions') }}</TableHeaderRowItem>
                    </TableHeaderRow>
                </TableHeader>
                <TableBody>
                    <TableBodyRow v-for="row in rows" :key="row.id">
                        <TableBodyRowItem :position="'first'">
                            <div class="flex items-start gap-3">
                                <span class="usr-avatar" :class="row.organization_role === 'owner' ? 'usr-avatar--owner' : 'usr-avatar--default'">
                                    {{ userInitial(row) }}
                                </span>
                                <div class="min-w-0 space-y-0.5">
                                    <div class="usr-name">{{ row.full_name }}</div>
                                    <div class="usr-email">{{ row.email }}</div>
                                    <div v-if="row.phone" class="usr-phone">{{ row.phone }}</div>
                                </div>
                            </div>
                        </TableBodyRowItem>
                        <TableBodyRowItem>
                            <div class="space-y-1.5">
                                <span class="usr-chip" :class="roleBadgeClass(row)">{{ compactRoleLabel(row) }}</span>
                                <div v-if="roleSecondaryText(row)" class="usr-subtext">{{ roleSecondaryText(row) }}</div>
                            </div>
                        </TableBodyRowItem>
                        <TableBodyRowItem>
                            <div class="space-y-1.5">
                                <div class="flex flex-wrap gap-1.5">
                                    <span
                                        v-for="badge in accessBadges(row)"
                                        :key="`${row.id}-${badge.label}`"
                                        class="usr-chip"
                                        :class="badge.tone === 'warning' ? 'ui-chip-warning' : 'ui-chip-neutral'"
                                    >
                                        {{ badge.label }}
                                    </span>
                                </div>
                                <div v-if="accessHint(row)" class="usr-subtext">{{ accessHint(row) }}</div>
                            </div>
                        </TableBodyRowItem>
                        <TableBodyRowItem>
                            <div class="flex flex-wrap gap-1.5">
                                <span class="usr-chip" :class="row.account_status === 'active' ? 'ui-chip-success' : 'ui-chip-danger'">
                                    {{ row.account_status_label }}
                                </span>
                                <span class="usr-chip" :class="row.email_verified ? 'ui-chip-info' : 'ui-chip-warning'">
                                    {{ row.email_verified ? $t('verified') : $t('unverified') }}
                                </span>
                            </div>
                        </TableBodyRowItem>
                        <TableBodyRowItem>
                            <div class="usr-updated">
                                <div>{{ formatUpdated(row.updated_at).date }}</div>
                                <div v-if="formatUpdated(row.updated_at).time" class="usr-subtext">{{ formatUpdated(row.updated_at).time }}</div>
                            </div>
                        </TableBodyRowItem>
                        <TableBodyRowItem :position="'last'">
                            <div class="flex justify-end gap-2">
                                <Link :href="`/admin/users/${row.id}`" class="usr-btn usr-btn--ghost usr-btn--sm">
                                    <Eye class="h-4 w-4" />
                                    {{ $t('View') }}
                                </Link>
                                <Dropdown v-if="canManageRow(row)" align="end">
                                    <button type="button" class="usr-kebab-btn">
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
                                                <span class="flex items-center gap-2">
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
                                                <span class="flex items-center gap-2 ui-danger-text">
                                                    <Trash2 class="h-4 w-4" />
                                                    {{ $t('Delete') }}
                                                </span>
                                            </DropdownItem>
                                        </DropdownItemGroup>
                                    </template>
                                </Dropdown>
                            </div>
                        </TableBodyRowItem>
                    </TableBodyRow>
                </TableBody>
            </UiDataTableShell>

            <Pagination v-if="props.rows?.links" class="mt-4" :pagination="props.rows" />

            <UiEmptyState
                v-if="rows.length === 0"
                :title="$t('No users found for the current filters.')"
            >
                <template #icon>
                    <UserRound class="h-7 w-7" />
                </template>
            </UiEmptyState>

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
import TableHeader from '@/Components/TableHeader.vue';
import TableHeaderRow from '@/Components/TableHeaderRow.vue';
import TableHeaderRowItem from '@/Components/TableHeaderRowItem.vue';
import TableBody from '@/Components/TableBody.vue';
import TableBodyRow from '@/Components/TableBodyRow.vue';
import TableBodyRowItem from '@/Components/TableBodyRowItem.vue';
import Pagination from '@/Components/Pagination.vue';
import UiDataTableShell from '@/Components/UI/UiDataTableShell.vue';
import UiEmptyState from '@/Components/UI/UiEmptyState.vue';
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

function userInitial(row) {
    return String(row.full_name ?? row.email ?? '').trim().charAt(0).toUpperCase() || '#';
}

function applyFilters() {
    router.get('/admin/users', { ...localFilters }, { preserveState: true, preserveScroll: true, replace: true });
}

function setFilter(key, value) {
    localFilters[key] = localFilters[key] === value ? '' : value;
    applyFilters();
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
        return 'ui-chip-success';
    }

    if (row.organization_role === 'employee') {
        return 'ui-chip-info';
    }

    return 'ui-chip-neutral';
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

<style scoped>
.usr-kpi-row :deep(.usr-kpi) {
    position: relative;
    overflow: hidden;
    transition: transform 180ms ease, box-shadow 180ms ease;
}

.usr-kpi-row :deep(.usr-kpi)::before {
    content: '';
    position: absolute;
    inset-inline: 0;
    top: 0;
    height: 3px;
    background: var(--usr-kpi-tone);
}

.usr-kpi-row :deep(.usr-kpi:hover) {
    transform: translateY(-2px);
    box-shadow: var(--ui-shadow-1);
}

.usr-kpi-row :deep(.usr-kpi--primary) { --usr-kpi-tone: var(--ui-primary); }
.usr-kpi-row :deep(.usr-kpi--secondary) { --usr-kpi-tone: var(--ui-secondary); }
.usr-kpi-row :deep(.usr-kpi--success) { --usr-kpi-tone: var(--ui-success); }
.usr-kpi-row :deep(.usr-kpi--danger) { --usr-kpi-tone: var(--ui-danger); }

.usr-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border-radius: 0.85rem;
    padding: 0.6rem 1.1rem;
    font-size: 0.88rem;
    font-weight: 600;
    transition: background-color 160ms ease, border-color 160ms ease, filter 160ms ease;
}

.usr-btn--sm {
    padding: 0.5rem 0.85rem;
    font-size: 0.82rem;
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

.usr-btn--solid {
    border: 1px solid transparent;
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.usr-btn--solid:hover {
    filter: brightness(1.05);
}

.usr-kebab-btn {
    display: inline-flex;
    height: 2.5rem;
    width: 2.5rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.75rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    color: var(--ui-muted);
    transition: background-color 160ms ease, color 160ms ease;
}

.usr-kebab-btn:hover {
    background: var(--ui-surface-soft);
    color: var(--ui-text);
}

.usr-toolbar {
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
    padding: 1.1rem 1.25rem;
    border: 1px solid var(--ui-border);
    border-radius: var(--ui-radius-xl, 1.25rem);
    background: linear-gradient(160deg, color-mix(in srgb, var(--ui-secondary) 5%, var(--ui-surface)), var(--ui-surface));
    box-shadow: var(--ui-shadow-1);
}

.usr-toolbar-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}

.usr-toolbar-divider {
    height: 1px;
    background: var(--ui-border);
}

.usr-toolbar-search {
    margin-bottom: 0;
    width: min(100%, 26rem);
    min-height: 2.9rem;
    border-color: var(--ui-border-strong);
}

.usr-toolbar-search :deep(.ui-table-search-input) {
    font-size: 0.92rem;
}

.usr-toolbar-select {
    border: 1px solid var(--ui-border);
    border-radius: 0.8rem;
    background: var(--ui-surface);
    color: var(--ui-text);
    padding: 0.6rem 1rem;
    font-size: 0.85rem;
    outline: none;
    transition: border-color 160ms ease, box-shadow 160ms ease;
}

.usr-toolbar-select:focus {
    border-color: color-mix(in srgb, var(--ui-secondary) 40%, var(--ui-border));
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--ui-secondary) 12%, transparent);
}

.usr-filter-group {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.usr-filter-pill {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.5rem 0.95rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--ui-muted);
    background: var(--ui-surface-soft);
    border: 1px solid var(--ui-border);
    transition: background-color 160ms ease, color 160ms ease, border-color 160ms ease, transform 160ms ease;
}

.usr-filter-pill:hover {
    color: var(--ui-text);
    background: var(--ui-border);
    transform: translateY(-1px);
}

.usr-filter-pill--active {
    color: #fff;
    background: linear-gradient(135deg, var(--ui-secondary), color-mix(in srgb, var(--ui-secondary) 78%, var(--ui-primary)));
    border-color: transparent;
    box-shadow: 0 10px 20px -12px color-mix(in srgb, var(--ui-secondary) 60%, rgba(15, 23, 42, 0.5));
}

.usr-toolbar-clear {
    border: 1px dashed var(--ui-border-strong);
    border-radius: 0.8rem;
    background: transparent;
    color: var(--ui-muted);
    padding: 0.55rem 0.9rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: background-color 160ms ease, border-color 160ms ease, color 160ms ease;
}

.usr-toolbar-clear:hover {
    background: var(--ui-surface-soft);
    border-color: var(--ui-danger);
    color: var(--ui-danger);
}

.usr-avatar {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    width: 2.6rem;
    height: 2.6rem;
    margin-top: 0.1rem;
    border-radius: 0.85rem;
    font-size: 1rem;
    font-weight: 800;
    color: #fff;
}

.usr-avatar--owner {
    background: linear-gradient(135deg, var(--ui-success), color-mix(in srgb, var(--ui-success) 70%, var(--ui-secondary)));
    box-shadow: 0 8px 18px -10px color-mix(in srgb, var(--ui-success) 70%, transparent);
}

.usr-avatar--default {
    background: linear-gradient(135deg, var(--ui-secondary), color-mix(in srgb, var(--ui-secondary) 70%, var(--ui-primary)));
    box-shadow: 0 8px 18px -10px color-mix(in srgb, var(--ui-secondary) 70%, transparent);
}

.usr-name {
    font-size: 0.92rem;
    font-weight: 700;
    color: var(--ui-text);
}

.usr-email {
    font-size: 0.85rem;
    color: var(--ui-muted);
}

.usr-phone {
    font-size: 0.78rem;
    color: var(--ui-muted);
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

.usr-subtext {
    font-size: 0.78rem;
    color: var(--ui-muted);
}

.usr-updated {
    font-size: 0.85rem;
    color: var(--ui-text);
}
</style>
