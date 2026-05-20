<template>
    <Modal :label="modalTitle" :isOpen="isOpen" @close="emit('close')">
        <div class="mt-5">
            <form @submit.prevent="submitForm" class="grid gap-x-6 gap-y-4 sm:grid-cols-6">
                <FormInput
                    v-model="form.first_name"
                    :error="form.errors.first_name"
                    :name="$t('First name')"
                    :type="'text'"
                    :className="'sm:col-span-3'"
                />
                <FormInput
                    v-model="form.last_name"
                    :error="form.errors.last_name"
                    :name="$t('Last name')"
                    :type="'text'"
                    :className="'sm:col-span-3'"
                />
                <FormInput
                    v-model="form.email"
                    :error="form.errors.email"
                    :name="$t('Email')"
                    :type="'email'"
                    :className="'sm:col-span-6'"
                />

                <div v-if="showPasswordFields" class="sm:col-span-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="mb-3 text-xs text-slate-500">
                        {{ $t('Leave the password fields empty to keep the current password.') }}
                    </div>
                    <div class="grid gap-3 sm:grid-cols-6">
                        <FormInput
                            v-model="form.password"
                            :error="form.errors.password"
                            :name="$t('Password')"
                            :type="'password'"
                            :className="'sm:col-span-3'"
                        />
                        <FormInput
                            v-model="form.password_confirmation"
                            :error="form.errors.password_confirmation"
                            :name="$t('Confirm password')"
                            :type="'password'"
                            :className="'sm:col-span-3'"
                        />
                    </div>
                </div>

                <div
                    v-else-if="isEditMode"
                    class="sm:col-span-6 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-500"
                >
                    {{ $t(passwordHelperText) }}
                </div>

                <div class="sm:col-span-6 flex items-center justify-between">
                    <div>
                        <div class="ui-form-label mb-0">{{ $t('Assignments') }}</div>
                        <div class="mt-1 text-xs text-slate-500">{{ $t('Select one or more workspaces and define a role for each one.') }}</div>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 whitespace-nowrap rounded-xl border border-sky-200 bg-sky-50 px-4 py-2.5 text-sm font-semibold text-sky-700 transition hover:bg-sky-100 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-400"
                        @click="addAssignment"
                        :disabled="!canAddAssignment"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                        </svg>
                        {{ $t(addAssignmentLabel) }}
                    </button>
                </div>

                <div
                    v-if="assignmentsLocked"
                    class="sm:col-span-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800"
                >
                    {{ $t('This employee inherits protected owner access and their assignments cannot be changed here.') }}
                </div>

                <div
                    v-else-if="addAssignmentHint"
                    class="sm:col-span-6 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600"
                >
                    {{ $t(addAssignmentHint) }}
                </div>

                <div
                    v-for="(assignment, index) in form.assignments"
                    :key="assignment.key"
                    class="sm:col-span-6 rounded-xl border border-slate-200 bg-slate-50 p-4"
                >
                    <div class="grid gap-3 sm:grid-cols-12">
                        <FormSelect
                            :modelValue="assignment.organization_uuid"
                            @update:modelValue="value => updateAssignmentWorkspace(index, value)"
                            :options="workspaceOptionsFor(index)"
                            :error="assignmentError(index, 'organization_uuid')"
                            :name="$t('Workspace')"
                            :placeholder="$t('Select a workspace')"
                            :className="'sm:col-span-5'"
                            :disabled="assignmentsLocked"
                        />
                        <FormSelect
                            :modelValue="assignment.organization_role_id"
                            @update:modelValue="value => updateAssignmentRole(index, value)"
                            :options="roleOptionsFor(assignment.organization_uuid)"
                            :error="assignmentError(index, 'organization_role_id')"
                            :name="$t('Role')"
                            :placeholder="$t('Select a role')"
                            :className="'sm:col-span-5'"
                            :disabled="assignmentsLocked || roleOptionsFor(assignment.organization_uuid).length === 0"
                        />
                        <div class="sm:col-span-2 flex items-end">
                            <button
                                type="button"
                                class="w-full rounded-md border border-slate-200 px-3 py-2 text-sm text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="assignmentsLocked || form.assignments.length === 1"
                                @click="removeAssignment(index)"
                            >
                                {{ $t('Remove') }}
                            </button>
                        </div>
                    </div>
                    <div
                        v-if="assignment.organization_uuid && roleOptionsFor(assignment.organization_uuid).length === 0"
                        class="mt-2 text-xs text-amber-700"
                    >
                        {{ $t('No roles available for this workspace') }}
                    </div>
                </div>

                <div v-if="form.errors.assignments" class="sm:col-span-6 ui-form-error">
                    {{ form.errors.assignments }}
                </div>

                <div class="mt-4 flex sm:col-span-6">
                    <button
                        type="button"
                        @click="emit('close')"
                        class="inline-flex justify-center rounded-md border border-transparent bg-slate-50 px-4 py-2 text-sm text-slate-500 hover:bg-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 me-4"
                    >
                        {{ $t('Cancel') }}
                    </button>
                    <button
                        type="submit"
                        :class="['inline-flex justify-center rounded-md border border-transparent bg-primary px-4 py-2 text-sm text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2', { 'opacity-50': form.processing }]"
                        :disabled="form.processing"
                    >
                        <svg v-if="form.processing" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                        <span v-else>{{ $t(isEditMode ? 'Update employee' : 'Save') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </Modal>
</template>

<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import FormInput from '@/Components/FormInput.vue';
import FormSelect from '@/Components/FormSelect.vue';

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false,
    },
    mode: {
        type: String,
        default: 'invite',
    },
    employee: {
        type: Object,
        default: null,
    },
    workspaces: {
        type: Array,
        default: () => [],
    },
    workspaceRoleCatalog: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['close']);
let assignmentSeed = 1;

const form = useForm({
    first_name: null,
    last_name: null,
    email: null,
    password: null,
    password_confirmation: null,
    assignments: [newAssignment()],
});

const isEditMode = computed(() => props.mode === 'edit');
const modalTitle = computed(() => isEditMode.value ? 'Edit employee' : 'Invite employee');
const assignmentsLocked = computed(() => Boolean(isEditMode.value && props.employee && !props.employee.can_manage_assignments));
const showPasswordFields = computed(() => Boolean(isEditMode.value && props.employee?.can_manage_account));
const selectedWorkspaceUuids = computed(() => form.assignments
    .map((assignment) => String(assignment.organization_uuid ?? '').trim())
    .filter((value) => value !== ''));
const hasIncompleteAssignments = computed(() => form.assignments.some((assignment) => {
    return String(assignment.organization_uuid ?? '').trim() === ''
        || Number(assignment.organization_role_id ?? 0) <= 0;
}));
const remainingWorkspaceCount = computed(() => {
    const selected = new Set(selectedWorkspaceUuids.value);

    return props.workspaces.filter((workspace) => {
        const value = String(workspace?.value ?? '').trim();

        return value !== '' && !selected.has(value);
    }).length;
});
const canAddAssignment = computed(() => {
    return !assignmentsLocked.value
        && !hasIncompleteAssignments.value
        && remainingWorkspaceCount.value > 0;
});
const addAssignmentLabel = computed(() => {
    return remainingWorkspaceCount.value > 0 ? 'Add assignment' : 'All workspaces assigned';
});
const passwordHelperText = computed(() => {
    if (props.employee?.has_user_account && props.employee?.status === 'pending') {
        return 'Account changes become available after the employee accepts the invite.';
    }

    return 'Password can only be changed for active or restored employees with an account.';
});
const addAssignmentHint = computed(() => {
    if (assignmentsLocked.value) {
        return null;
    }

    if (hasIncompleteAssignments.value) {
        return 'Complete the current assignment before adding another one.';
    }

    if (remainingWorkspaceCount.value <= 0) {
        return 'All available workspaces have already been assigned.';
    }

    return null;
});

watch(
    () => [props.isOpen, props.mode, props.employee],
    ([isOpen]) => {
        if (!isOpen) {
            return;
        }

        initializeForm();
    },
    { immediate: true }
);

function newAssignment(assignment = {}) {
    return {
        key: `assignment-${assignmentSeed++}`,
        organization_uuid: assignment.organization_uuid ?? null,
        organization_role_id: assignment.organization_role_id ?? null,
    };
}

function sanitizeAssignments(assignments = []) {
    const uniqueAssignments = [];
    const seenWorkspaceUuids = new Set();

    for (const assignment of Array.isArray(assignments) ? assignments : []) {
        const organizationUuid = String(assignment?.organization_uuid ?? '').trim();
        const organizationRoleId = assignment?.organization_role_id ?? null;

        if (organizationUuid === '' || seenWorkspaceUuids.has(organizationUuid)) {
            continue;
        }

        seenWorkspaceUuids.add(organizationUuid);
        uniqueAssignments.push(newAssignment({
            organization_uuid: organizationUuid,
            organization_role_id: organizationRoleId,
        }));
    }

    return uniqueAssignments.length > 0 ? uniqueAssignments : [newAssignment()];
}

function initializeForm() {
    assignmentSeed = 1;
    form.clearErrors();
    form.password = null;
    form.password_confirmation = null;

    if (isEditMode.value && props.employee) {
        form.first_name = props.employee.first_name ?? null;
        form.last_name = props.employee.last_name ?? null;
        form.email = props.employee.email ?? null;
        form.assignments = sanitizeAssignments(props.employee.assignments ?? []);

        return;
    }

    form.first_name = null;
    form.last_name = null;
    form.email = null;
    form.assignments = [newAssignment()];
}

function addAssignment() {
    if (canAddAssignment.value) {
        form.assignments.push(newAssignment());
    }
}

function removeAssignment(index) {
    if (!assignmentsLocked.value && form.assignments.length > 1) {
        form.assignments.splice(index, 1);
    }
}

function updateAssignmentWorkspace(index, value) {
    const normalizedValue = String(value ?? '').trim();
    const duplicateWorkspaceSelected = form.assignments.some((assignment, assignmentIndex) => {
        if (assignmentIndex === index) {
            return false;
        }

        return String(assignment.organization_uuid ?? '').trim() === normalizedValue && normalizedValue !== '';
    });

    if (duplicateWorkspaceSelected) {
        form.assignments[index].organization_uuid = null;
        form.assignments[index].organization_role_id = null;
        return;
    }

    form.assignments[index].organization_uuid = value;

    const validRoleIds = roleOptionsFor(value).map((option) => Number(option.value));
    if (!validRoleIds.includes(Number(form.assignments[index].organization_role_id))) {
        form.assignments[index].organization_role_id = null;
    }
}

function updateAssignmentRole(index, value) {
    form.assignments[index].organization_role_id = value;
}

function roleOptionsFor(organizationUuid) {
    return organizationUuid ? (props.workspaceRoleCatalog?.[organizationUuid] ?? []) : [];
}

function workspaceOptionsFor(index) {
    const selectedByOtherRows = new Set(
        form.assignments
            .filter((_, assignmentIndex) => assignmentIndex !== index)
            .map((assignment) => String(assignment.organization_uuid ?? '').trim())
            .filter((value) => value !== '')
    );

    return props.workspaces.filter((workspace) => {
        const value = String(workspace?.value ?? '').trim();
        const currentValue = String(form.assignments[index]?.organization_uuid ?? '').trim();

        return value === currentValue || !selectedByOtherRows.has(value);
    });
}

function assignmentError(index, field) {
    return form.errors[`assignments.${index}.${field}`] ?? null;
}

function submitForm() {
    const options = {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    };

    if (isEditMode.value && props.employee?.uuid) {
        form.put(`/settings/company-team/${props.employee.uuid}`, options);
        return;
    }

    form.post('/settings/company-team/invite', options);
}
</script>
