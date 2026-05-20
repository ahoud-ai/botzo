<template>
    <SettingLayout :modules="props.modules">
        <div class="min-h-0">
            <div class="flex justify-center items-center">
                <div class="md:w-[72em] mb-20">
                    <div class="bg-white border border-slate-200 rounded-lg py-2 text-sm mb-4 pb-4 px-4">
                        <div class="w-full py-2 mb-2 mt-2">
                            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                <div class="text-md">
                                    <h4 class="text-[16px]">{{ $t('Company team') }}</h4>
                                    <span class="flex items-center mt-1 text-slate-500">
                                        {{ $t('Invite employees and assign them to one or more workspaces') }}
                                    </span>
                                </div>
                                <div class="ms-auto">
                                    <button
                                        type="button"
                                        @click="openInviteModal"
                                        :disabled="!hasWorkspaceOptions"
                                        :class="[
                                            'rounded-md px-3 py-2 text-sm shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600',
                                            hasWorkspaceOptions
                                                ? 'bg-primary text-white hover:bg-indigo-500'
                                                : 'cursor-not-allowed bg-slate-200 text-slate-500',
                                        ]"
                                    >
                                        {{ $t('Invite employee') }}
                                    </button>
                                </div>
                            </div>

                            <div class="mt-4 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                                {{ $t('Employees added here are managed from the parent organization and can be assigned to more than one workspace.') }}
                            </div>

                            <div class="mt-4 grid gap-3 md:grid-cols-5">
                                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                                    <div class="text-xs text-slate-500">{{ $t('Employees') }}</div>
                                    <div class="mt-1 text-2xl font-semibold text-slate-900">{{ props.summary?.employees ?? 0 }}</div>
                                </div>
                                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                                    <div class="text-xs text-emerald-700">{{ $t('Active') }}</div>
                                    <div class="mt-1 text-2xl font-semibold text-emerald-900">{{ props.summary?.active ?? 0 }}</div>
                                </div>
                                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                                    <div class="text-xs text-amber-700">{{ $t('Pending') }}</div>
                                    <div class="mt-1 text-2xl font-semibold text-amber-900">{{ props.summary?.pending ?? 0 }}</div>
                                </div>
                                <div class="rounded-xl border border-slate-300 bg-slate-100 px-4 py-3">
                                    <div class="text-xs text-slate-700">{{ $t('Suspended') }}</div>
                                    <div class="mt-1 text-2xl font-semibold text-slate-900">{{ props.summary?.suspended ?? 0 }}</div>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                                    <div class="text-xs text-slate-500">{{ $t('Workspaces') }}</div>
                                    <div class="mt-1 text-2xl font-semibold text-slate-900">{{ props.summary?.workspaces ?? 0 }}</div>
                                </div>
                            </div>

                            <div class="mt-6 overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-200">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-4 py-3 text-start text-xs font-medium uppercase tracking-wide text-slate-500">{{ $t('Name') }}</th>
                                            <th class="px-4 py-3 text-start text-xs font-medium uppercase tracking-wide text-slate-500">{{ $t('Email') }}</th>
                                            <th class="px-4 py-3 text-start text-xs font-medium uppercase tracking-wide text-slate-500">{{ $t('Status') }}</th>
                                            <th class="px-4 py-3 text-start text-xs font-medium uppercase tracking-wide text-slate-500">{{ $t('Assignments') }}</th>
                                            <th class="px-4 py-3 text-end text-xs font-medium uppercase tracking-wide text-slate-500">{{ $t('Actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200 bg-white">
                                        <tr v-for="row in rowsData" :key="row.uuid">
                                            <td class="px-4 py-4 align-top text-sm text-slate-900">{{ row.full_name || $t('Pending') }}</td>
                                            <td class="px-4 py-4 align-top text-sm text-slate-600">{{ row.email }}</td>
                                            <td class="px-4 py-4 align-top text-sm">
                                                <span :class="statusBadgeClass(row.status)" class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium">
                                                    {{ $t(statusLabel(row.status)) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-4 align-top text-sm text-slate-700">
                                                <div v-if="row.assignments_count > 0" class="flex flex-wrap gap-2">
                                                    <span
                                                        v-for="assignment in row.assignments"
                                                        :key="`${row.uuid}-${assignment.organization_uuid}-${assignment.role_name}`"
                                                        class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs text-slate-700"
                                                    >
                                                        {{ assignment.organization_name }} · {{ localizeKnownValue(assignment.role_name) }}
                                                    </span>
                                                </div>
                                                <span v-else class="text-slate-400">{{ $t('No assignments yet') }}</span>
                                            </td>
                                            <td class="px-4 py-4 align-top">
                                                <div class="flex items-center justify-end gap-2">
                                                    <button
                                                        type="button"
                                                        class="rounded-md border border-slate-200 px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50"
                                                        @click="openEditModal(row)"
                                                    >
                                                        {{ $t('Edit') }}
                                                    </button>
                                                    <button
                                                        v-if="row.can_suspend"
                                                        type="button"
                                                        class="rounded-md border border-amber-200 px-3 py-1.5 text-xs text-amber-700 hover:bg-amber-50 disabled:cursor-not-allowed disabled:opacity-50"
                                                        :disabled="isActionProcessing(row.uuid, 'suspend')"
                                                        @click="suspendEmployee(row)"
                                                    >
                                                        {{ isActionProcessing(row.uuid, 'suspend') ? $t('Suspending...') : $t('Suspend') }}
                                                    </button>
                                                    <button
                                                        v-if="row.can_restore"
                                                        type="button"
                                                        class="rounded-md border border-emerald-200 px-3 py-1.5 text-xs text-emerald-700 hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-50"
                                                        :disabled="isActionProcessing(row.uuid, 'restore')"
                                                        @click="restoreEmployee(row)"
                                                    >
                                                        {{ isActionProcessing(row.uuid, 'restore') ? $t('Restoring...') : $t('Restore') }}
                                                    </button>
                                                    <button
                                                        v-if="row.can_resend_invite"
                                                        type="button"
                                                        class="rounded-md border border-sky-200 px-3 py-1.5 text-xs text-sky-700 hover:bg-sky-50 disabled:cursor-not-allowed disabled:opacity-50"
                                                        :disabled="isActionProcessing(row.uuid, 'resend')"
                                                        @click="resendInvite(row)"
                                                    >
                                                        {{ isActionProcessing(row.uuid, 'resend') ? $t('Sending...') : $t('Resend invite') }}
                                                    </button>
                                                    <button
                                                        v-if="row.can_delete"
                                                        type="button"
                                                        class="rounded-md border border-rose-200 px-3 py-1.5 text-xs text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50"
                                                        :disabled="isActionProcessing(row.uuid, 'delete')"
                                                        @click="deleteEmployee(row)"
                                                    >
                                                        {{ isActionProcessing(row.uuid, 'delete') ? $t('Deleting...') : $t('Delete') }}
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-if="rowsData.length === 0">
                                            <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">{{ $t('No team members found.') }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <Pagination v-if="pagination" class="mt-4" :pagination="pagination" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

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
import { buildI18nLookupCandidates } from '@/Utils/i18nLookup';
import AlertModal from '@/Components/AlertModal.vue';
import Pagination from '@/Components/Pagination.vue';
import CompanyTeamEmployeeModal from '@/Components/CompanyTeamEmployeeModal.vue';
import SettingLayout from './../Settings/Layout.vue';

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
const pagination = computed(() => props.rows?.meta ? props.rows.meta : (props.rows?.last_page ? props.rows : null));
const hasWorkspaceOptions = computed(() => props.workspaces.length > 0);

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

function statusBadgeClass(status) {
    if (status === 'active') return 'border border-emerald-200 bg-emerald-50 text-emerald-800';
    if (status === 'pending') return 'border border-amber-200 bg-amber-50 text-amber-800';
    if (status === 'suspended') return 'border border-slate-300 bg-slate-100 text-slate-700';
    return 'border border-slate-200 bg-slate-50 text-slate-700';
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
