<template>
    <SettingLayout :modules="props.modules" :title="$t('Company team')" :subtitle="$t('Invite employees and assign them to one or more workspaces')">
        <template #actions>
            <button
                type="button"
                class="team-invite-btn"
                :class="{ 'team-invite-btn--disabled': !hasWorkspaceOptions }"
                :disabled="!hasWorkspaceOptions"
                @click="openInviteModal"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m-7-7h14"/></svg>
                {{ $t('Invite employee') }}
            </button>
        </template>

        <div class="team-info-banner">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"><circle cx="12" cy="12" r="9"/><path d="M12 11v5m0-8v.01"/></g></svg>
            <span>{{ $t('Employees added here are managed from the parent organization and can be assigned to more than one workspace.') }}</span>
        </div>

        <div class="grid gap-4 mb-6 sm:grid-cols-2 xl:grid-cols-5">
            <UiStatCard class="ui-kpi--compact team-kpi--total" :title="$t('Employees')" :value="props.summary?.employees ?? 0">
                <template #icon>
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20c0-1.657-2.239-3-5-3s-5 1.343-5 3m14-3c0-1.23-1.234-2.287-3-2.75M3 17c0-1.23 1.234-2.287 3-2.75m12-4.014a3 3 0 1 0-4-4.472m-8 4.472a3 3 0 0 1 4-4.472M12 14a3 3 0 1 1 0-6a3 3 0 0 1 0 6Z"/></svg>
                </template>
            </UiStatCard>
            <UiStatCard class="ui-kpi--compact team-kpi--active" :title="$t('Active')" :value="props.summary?.active ?? 0">
                <template #icon>
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m5 13l4 4L19 7"/></svg>
                </template>
            </UiStatCard>
            <UiStatCard class="ui-kpi--compact team-kpi--pending" :title="$t('Pending')" :value="props.summary?.pending ?? 0">
                <template #icon>
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></g></svg>
                </template>
            </UiStatCard>
            <UiStatCard class="ui-kpi--compact team-kpi--suspended" :title="$t('Suspended')" :value="props.summary?.suspended ?? 0">
                <template #icon>
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.5l5 5m0-5l-5 5"/></g></svg>
                </template>
            </UiStatCard>
            <UiStatCard class="ui-kpi--compact team-kpi--workspaces" :title="$t('Workspaces')" :value="props.summary?.workspaces ?? 0">
                <template #icon>
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 21h18M5 21V7l7-4l7 4v14M9 21v-6h6v6M9 11h.01M15 11h.01M9 7h.01M15 7h.01"/></svg>
                </template>
            </UiStatCard>
        </div>

        <div class="team-toolbar">
            <div class="ui-table-search">
                <span class="ui-table-search-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 15l6 6m-11-4a7 7 0 1 1 0-14a7 7 0 0 1 0 14Z"/></svg>
                </span>
                <input @input="search" v-model="params.search" type="text" class="ui-table-search-input" :placeholder="$t('Search team')">
                <button v-if="isSearching === false && params.search" @click="clearSearch" type="button" class="ui-table-search-action">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10s10-4.5 10-10S17.5 2 12 2zm3.7 12.3c.4.4.4 1 0 1.4c-.4.4-1 .4-1.4 0L12 13.4l-2.3 2.3c-.4.4-1 .4-1.4 0c-.4-.4-.4-1 0-1.4l2.3-2.3l-2.3-2.3c-.4-.4-.4-1 0-1.4c.4-.4 1-.4 1.4 0l2.3 2.3l2.3-2.3c.4-.4 1-.4 1.4 0c.4.4.4 1 0 1.4L13.4 12l2.3 2.3z"/></svg>
                </button>
                <span v-if="isSearching" class="ui-table-search-action">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="0 12 12;90 12 12;180 12 12;270 12 12"/><animate attributeName="opacity" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" begin="0.2s" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="30 12 12;120 12 12;210 12 12;300 12 12"/><animate attributeName="opacity" begin="0.2s" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" begin="0.4s" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="60 12 12;150 12 12;240 12 12;330 12 12"/><animate attributeName="opacity" begin="0.4s" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle></svg>
                </span>
            </div>

            <div class="team-filter-group">
                <button
                    v-for="option in statusOptions"
                    :key="option.value"
                    type="button"
                    class="team-filter-pill"
                    :class="{ 'team-filter-pill--active': params.status === option.value }"
                    @click="applyStatus(option.value)"
                >
                    {{ option.label }}
                </button>
            </div>
        </div>

        <Table :rows="normalizedRows">
            <TableHeader>
                <TableHeaderRow>
                    <TableHeaderRowItem :position="'first'">{{ $t('Name') }}</TableHeaderRowItem>
                    <TableHeaderRowItem class="hidden md:table-cell">{{ $t('Email') }}</TableHeaderRowItem>
                    <TableHeaderRowItem>{{ $t('Status') }}</TableHeaderRowItem>
                    <TableHeaderRowItem class="hidden lg:table-cell">{{ $t('Assignments') }}</TableHeaderRowItem>
                    <TableHeaderRowItem :position="'last'">{{ $t('Actions') }}</TableHeaderRowItem>
                </TableHeaderRow>
            </TableHeader>
            <TableBody>
                <TableBodyRow v-for="row in rowsData" :key="row.uuid">
                    <TableBodyRowItem :position="'first'">
                        <div class="flex items-center gap-3">
                            <span class="team-avatar">{{ initials(row) }}</span>
                            <div class="min-w-0">
                                <div class="font-bold text-[15px] text-[var(--ui-text)] truncate">{{ row.full_name || $t('Pending') }}</div>
                                <div class="md:hidden text-[13px] text-[var(--ui-muted)] truncate">{{ row.email }}</div>
                            </div>
                        </div>
                    </TableBodyRowItem>
                    <TableBodyRowItem class="hidden md:table-cell">
                        <span class="text-[var(--ui-muted)]">{{ row.email }}</span>
                    </TableBodyRowItem>
                    <TableBodyRowItem>
                        <div class="flex items-center gap-2.5">
                            <FormToggleSwitch
                                v-if="canToggleStatus(row)"
                                :modelValue="row.status === 'active'"
                                :disabled="isActionProcessing(row.uuid, 'suspend') || isActionProcessing(row.uuid, 'restore')"
                                :title="row.status === 'active' ? $t('Suspend') : $t('Restore')"
                                @update:modelValue="() => toggleStatus(row)"
                            />
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[13px] font-bold" :class="statusChipClass(row.status)">
                                <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                                {{ $t(statusLabel(row.status)) }}
                            </span>
                        </div>
                    </TableBodyRowItem>
                    <TableBodyRowItem class="hidden lg:table-cell">
                        <div v-if="row.assignments_count > 0" class="flex flex-wrap gap-1.5">
                            <span
                                v-for="assignment in row.assignments"
                                :key="`${row.uuid}-${assignment.organization_uuid}-${assignment.role_name}`"
                                class="team-assignment-chip"
                            >
                                {{ assignment.organization_name }} · {{ localizeKnownValue(assignment.role_name) }}
                            </span>
                        </div>
                        <span v-else class="text-[var(--ui-muted)]">{{ $t('No assignments yet') }}</span>
                    </TableBodyRowItem>
                    <TableBodyRowItem :position="'last'">
                        <Dropdown :align="'right'">
                            <button type="submit" class="team-toolbar-btn">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24">
                                    <path fill="currentColor" d="M12 16a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2m0-6a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2m0-6a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2Z"/>
                                </svg>
                            </button>
                            <template #items>
                                <DropdownItemGroup>
                                    <DropdownItem as="button" @click="openEditModal(row)">{{ $t('Edit') }}</DropdownItem>
                                    <DropdownItem v-if="row.can_resend_invite" as="button" :disabled="isActionProcessing(row.uuid, 'resend')" @click="resendInvite(row)">
                                        {{ isActionProcessing(row.uuid, 'resend') ? $t('Sending...') : $t('Resend invite') }}
                                    </DropdownItem>
                                    <DropdownItem v-if="row.can_delete" as="button" :disabled="isActionProcessing(row.uuid, 'delete')" @click="deleteEmployee(row)">
                                        {{ isActionProcessing(row.uuid, 'delete') ? $t('Deleting...') : $t('Delete') }}
                                    </DropdownItem>
                                </DropdownItemGroup>
                            </template>
                        </Dropdown>
                    </TableBodyRowItem>
                </TableBodyRow>
            </TableBody>
        </Table>

        <UiEmptyState v-if="rowsData.length === 0" :title="$t('No team members found.')">
            <template #icon>
                <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20c0-1.657-2.239-3-5-3s-5 1.343-5 3m14-3c0-1.23-1.234-2.287-3-2.75M3 17c0-1.23 1.234-2.287 3-2.75m12-4.014a3 3 0 1 0-4-4.472m-8 4.472a3 3 0 0 1 4-4.472M12 14a3 3 0 1 1 0-6a3 3 0 0 1 0 6Z"/></svg>
            </template>
        </UiEmptyState>

        <CompanyTeamEmployeeModal
            :is-open="isOpenFormModal"
            :mode="editingEmployee ? 'edit' : 'invite'"
            :employee="editingEmployee"
            :workspaces="props.workspaces"
            :workspace-role-catalog="props.workspaceRoleCatalog"
            @close="closeModal"
        />

        <AlertModal
            v-model="confirmationModal.isOpen"
            :label="confirmationModal.label"
            :description="confirmationModal.description"
            :confirm-button-text="confirmationModal.confirmButtonText"
            :confirm-button-class="confirmationModal.confirmButtonClass"
            @confirm="confirmRowAction"
        />
    </SettingLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import debounce from 'lodash/debounce';
import { buildI18nLookupCandidates } from '@/Utils/i18nLookup';
import AlertModal from '@/Components/AlertModal.vue';
import CompanyTeamEmployeeModal from '@/Components/CompanyTeamEmployeeModal.vue';
import FormToggleSwitch from '@/Components/FormToggleSwitch.vue';
import SettingLayout from './../Settings/Layout.vue';
import UiStatCard from '@/Components/UI/UiStatCard.vue';
import UiEmptyState from '@/Components/UI/UiEmptyState.vue';
import Table from '@/Components/Table.vue';
import TableHeader from '@/Components/TableHeader.vue';
import TableHeaderRow from '@/Components/TableHeaderRow.vue';
import TableHeaderRowItem from '@/Components/TableHeaderRowItem.vue';
import TableBody from '@/Components/TableBody.vue';
import TableBodyRow from '@/Components/TableBodyRow.vue';
import TableBodyRowItem from '@/Components/TableBodyRowItem.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownItemGroup from '@/Components/DropdownItemGroup.vue';
import DropdownItem from '@/Components/DropdownItem.vue';

const { t, te } = useI18n();

const props = defineProps({
    rows: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    modules: { type: Array, default: () => [] },
    workspaces: { type: Array, default: () => [] },
    workspaceRoleCatalog: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    company: { type: Object, default: () => ({}) },
});

const isOpenFormModal = ref(false);
const editingEmployee = ref(null);
const rowAction = ref({ uuid: null, action: null });
const confirmationModal = ref({
    isOpen: false,
    label: null,
    description: null,
    confirmButtonText: null,
    confirmButtonClass: 'bg-red-600 hover:bg-red-500',
    callback: null,
});

const rowsData = computed(() => Array.isArray(props.rows?.data) ? props.rows.data : []);
const paginationMeta = computed(() => props.rows?.meta ? props.rows.meta : (props.rows?.last_page ? props.rows : null));
const normalizedRows = computed(() => ({ data: rowsData.value, meta: paginationMeta.value }));
const hasWorkspaceOptions = computed(() => props.workspaces.length > 0);

const params = ref({
    search: props.filters?.search ?? null,
    status: props.filters?.status ?? 'all',
});

const statusOptions = computed(() => ([
    { value: 'all', label: t('All statuses') },
    { value: 'active', label: t('Active') },
    { value: 'pending', label: t('Pending') },
    { value: 'suspended', label: t('Suspended') },
]));

const isSearching = ref(false);

const clearSearch = () => {
    params.value.search = null;
    runSearch();
};

const search = debounce(() => {
    isSearching.value = true;
    runSearch();
}, 600);

const runSearch = () => {
    const filteredParams = Object.fromEntries(
        Object.entries(params.value).filter(([, value]) => value !== null && value !== 'all')
    );

    router.visit('/settings/company-team', {
        method: 'get',
        data: filteredParams,
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            isSearching.value = false;
        },
    });
};

const applyStatus = (value) => {
    params.value.status = value;
    runSearch();
};

function initials(row) {
    const name = String(row.full_name || row.email || '').trim();
    if (name === '') return '?';
    return name.charAt(0).toUpperCase();
}

function openInviteModal() {
    editingEmployee.value = null;
    isOpenFormModal.value = true;
}

function openEditModal(row) {
    editingEmployee.value = row;
    isOpenFormModal.value = true;
}

function closeModal() {
    isOpenFormModal.value = false;
    editingEmployee.value = null;
}

function statusLabel(status) {
    if (status === 'active') return 'Active';
    if (status === 'pending') return 'Pending';
    if (status === 'suspended') return 'Suspended';

    return 'Disabled';
}

function canToggleStatus(row) {
    if (row.status === 'active') return Boolean(row.can_suspend);
    if (row.status === 'suspended') return Boolean(row.can_restore);
    return false;
}

function toggleStatus(row) {
    if (row.status === 'active') {
        suspendEmployee(row);
        return;
    }

    if (row.status === 'suspended') {
        restoreEmployee(row);
    }
}

function statusChipClass(status) {
    if (status === 'active') return 'ui-chip-success';
    if (status === 'pending') return 'ui-chip-warning';
    if (status === 'suspended') return 'ui-chip-neutral';

    return 'ui-chip-neutral';
}

function localizeKnownValue(value) {
    const rawValue = String(value ?? '').trim();
    if (rawValue === '') return '-';

    for (const candidate of buildI18nLookupCandidates(rawValue)) {
        if (te(candidate)) return t(candidate);
    }

    return rawValue;
}

function isActionProcessing(uuid, action) {
    return rowAction.value.uuid === uuid && rowAction.value.action === action;
}

function resendInvite(row) {
    rowAction.value = { uuid: row.uuid, action: 'resend' };

    router.post(`/settings/company-team/${row.uuid}/resend-invite`, {}, {
        preserveScroll: true,
        onFinish: () => {
            rowAction.value = { uuid: null, action: null };
        },
    });
}

function suspendEmployee(row) {
    openConfirmation({
        label: t('Suspend employee?'),
        description: t('The employee will lose current access.'),
        confirmButtonText: t('Suspend'),
        confirmButtonClass: 'bg-amber-600 hover:bg-amber-500',
        callback: () => {
            rowAction.value = { uuid: row.uuid, action: 'suspend' };

            router.post(`/settings/company-team/${row.uuid}/suspend`, {}, {
                preserveScroll: true,
                onFinish: () => {
                    rowAction.value = { uuid: null, action: null };
                },
            });
        },
    });
}

function restoreEmployee(row) {
    rowAction.value = { uuid: row.uuid, action: 'restore' };

    router.post(`/settings/company-team/${row.uuid}/restore`, {}, {
        preserveScroll: true,
        onFinish: () => {
            rowAction.value = { uuid: null, action: null };
        },
    });
}

function deleteEmployee(row) {
    openConfirmation({
        label: t('Remove employee?'),
        description: t('This removes the employee and clears their assignments.'),
        confirmButtonText: t('Remove'),
        confirmButtonClass: 'bg-rose-600 hover:bg-rose-500',
        callback: () => {
            rowAction.value = { uuid: row.uuid, action: 'delete' };

            router.delete(`/settings/company-team/${row.uuid}`, {
                preserveScroll: true,
                onFinish: () => {
                    rowAction.value = { uuid: null, action: null };
                },
            });
        },
    });
}

function openConfirmation(config) {
    confirmationModal.value = {
        isOpen: true,
        label: config.label,
        description: config.description,
        confirmButtonText: config.confirmButtonText,
        confirmButtonClass: config.confirmButtonClass,
        callback: config.callback,
    };
}

function confirmRowAction() {
    const callback = confirmationModal.value.callback;

    confirmationModal.value = {
        isOpen: false,
        label: null,
        description: null,
        confirmButtonText: null,
        confirmButtonClass: 'bg-red-600 hover:bg-red-500',
        callback: null,
    };

    if (typeof callback === 'function') {
        callback();
    }
}
</script>

<style scoped>
.team-invite-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border-radius: 0.7rem;
    padding: 0.6rem 1.2rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: #fff;
    background: var(--ui-secondary);
    transition: filter 160ms ease;
}

.team-invite-btn:hover {
    filter: brightness(1.05);
}

.team-invite-btn--disabled {
    background: var(--ui-border-strong);
    color: var(--ui-muted);
    cursor: not-allowed;
}

.team-info-banner {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    margin-bottom: 1.25rem;
    padding: 0.85rem 1.1rem;
    border-radius: var(--ui-radius-md);
    background: color-mix(in srgb, var(--ui-secondary) 8%, var(--ui-surface));
    border: 1px solid color-mix(in srgb, var(--ui-secondary) 20%, var(--ui-border));
    color: var(--ui-text);
    font-size: 0.88rem;
    line-height: 1.5;
}

.team-info-banner svg {
    flex-shrink: 0;
    margin-top: 0.1rem;
    color: var(--ui-secondary);
}

.team-kpi--active :deep(.ui-kpi-icon) {
    background: color-mix(in srgb, var(--ui-success) 16%, transparent);
    border-color: color-mix(in srgb, var(--ui-success) 32%, transparent);
}
.team-kpi--active :deep(.ui-kpi-icon svg) { color: var(--ui-success) !important; }

.team-kpi--pending :deep(.ui-kpi-icon) {
    background: color-mix(in srgb, var(--ui-warning) 16%, transparent);
    border-color: color-mix(in srgb, var(--ui-warning) 32%, transparent);
}
.team-kpi--pending :deep(.ui-kpi-icon svg) { color: var(--ui-warning) !important; }

.team-kpi--suspended :deep(.ui-kpi-icon) {
    background: color-mix(in srgb, var(--ui-danger) 14%, transparent);
    border-color: color-mix(in srgb, var(--ui-danger) 28%, transparent);
}
.team-kpi--suspended :deep(.ui-kpi-icon svg) { color: var(--ui-danger) !important; }

.team-kpi--workspaces :deep(.ui-kpi-icon) {
    background: color-mix(in srgb, var(--ui-secondary) 16%, transparent);
    border-color: color-mix(in srgb, var(--ui-secondary) 32%, transparent);
}
.team-kpi--workspaces :deep(.ui-kpi-icon svg) { color: var(--ui-secondary) !important; }

.team-kpi--total :deep(.ui-kpi-icon) {
    background: color-mix(in srgb, #155dfc 16%, transparent);
    border-color: color-mix(in srgb, #155dfc 32%, transparent);
}
.team-kpi--total :deep(.ui-kpi-icon svg) { color: #155dfc !important; }

.team-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.team-filter-group {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.team-filter-pill {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.4rem 0.9rem;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--ui-muted);
    background: var(--ui-surface-soft);
    border: 1px solid var(--ui-border);
    transition: background-color 160ms ease, color 160ms ease, border-color 160ms ease;
}

.team-filter-pill:hover {
    color: var(--ui-text);
    background: var(--ui-border);
}

.team-filter-pill--active {
    color: #fff;
    background: var(--ui-secondary);
    border-color: var(--ui-secondary);
}

.team-avatar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 2.4rem;
    height: 2.4rem;
    border-radius: 999px;
    background: color-mix(in srgb, var(--ui-secondary) 14%, var(--ui-surface-soft));
    color: var(--ui-secondary);
    font-weight: 700;
    font-size: 0.9rem;
}

.team-assignment-chip {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.25rem 0.7rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--ui-text);
    background: var(--ui-surface-soft);
    border: 1px solid var(--ui-border);
}

.team-toolbar-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.1rem;
    height: 2.1rem;
    border-radius: 999px;
    color: var(--ui-muted);
    background: transparent;
    cursor: pointer;
    transition: background-color 160ms ease, color 160ms ease;
}

.team-toolbar-btn:hover {
    background: var(--ui-surface-soft);
    color: var(--ui-text);
}
</style>
