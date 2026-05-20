<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-950">{{ $t('Create user') }}</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                        {{ $t('Create a standalone account, a new organization owner, or an employee with workspace access.') }}
                    </p>
                </div>

                <Link href="/admin/users" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
                    {{ $t('Back') }}
                </Link>
            </div>

            <form @submit.prevent="submitForm" class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div class="space-y-6">
                    <section class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                        <div class="flex flex-wrap gap-3">
                            <button
                                v-for="mode in creationModes"
                                :key="mode.value"
                                type="button"
                                class="rounded-2xl border px-4 py-3 text-start text-sm transition"
                                :class="form.creation_mode === mode.value ? 'border-indigo-300 bg-indigo-50 text-indigo-900' : 'border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100'"
                                @click="setCreationMode(mode.value)"
                            >
                                <div class="font-semibold">{{ mode.label }}</div>
                                <div class="mt-1 text-xs leading-5 text-slate-500">{{ mode.description }}</div>
                            </button>
                        </div>
                    </section>

                    <section class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                        <div class="mb-4">
                            <h2 class="text-base font-semibold text-slate-950">{{ $t('Account') }}</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ $t('The user will receive a platform account and can sign in right away.') }}</p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <FormInput v-model="form.first_name" :name="$t('First name')" :error="form.errors.first_name" type="text" />
                            <FormInput v-model="form.last_name" :name="$t('Last name')" :error="form.errors.last_name" type="text" />
                            <FormInput v-model="form.email" :name="$t('Email')" :error="form.errors.email" type="email" />
                            <FormPhoneInput v-model="form.phone" :allowed-countries="allowedPhoneCountries" :name="$t('Phone')" :error="form.errors.phone" type="text" />
                            <FormInput v-model="form.password" :name="$t('Password')" :error="form.errors.password" type="password" />
                            <FormInput v-model="form.password_confirmation" :name="$t('Confirm password')" :error="form.errors.password_confirmation" type="password" />
                        </div>

                        <label class="mt-4 flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                            <input v-model="form.send_registration_email" type="checkbox" class="ui-checkbox-input">
                            <span>{{ $t('Send a registration email after saving') }}</span>
                        </label>
                    </section>

                    <section v-if="form.creation_mode === 'owner_new_org'" class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                        <div class="mb-4">
                            <h2 class="text-base font-semibold text-slate-950">{{ $t('Owner workspace') }}</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ $t('Create a main organization and assign this account as the owner.') }}</p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <FormInput v-model="form.organization_name" :name="$t('Organization name')" :error="form.errors.organization_name" type="text" class-name="md:col-span-2" />
                            <FormSelect
                                :model-value="form.organization_billing_mode"
                                @update:modelValue="value => form.organization_billing_mode = value"
                                :options="billingModeOptions"
                                :name="$t('Billing setup')"
                                :error="form.errors.organization_billing_mode"
                                :placeholder="$t('Select option')"
                                class-name="md:col-span-2"
                            />
                            <FormSelect
                                v-if="form.organization_billing_mode === 'now'"
                                :model-value="form.organization_plan_uuid"
                                @update:modelValue="value => form.organization_plan_uuid = value"
                                :options="planOptions"
                                :name="$t('Subscription plan')"
                                :error="form.errors.organization_plan_uuid"
                                :placeholder="$t('Select a plan')"
                                class-name="md:col-span-2"
                            />
                        </div>

                        <div class="mt-4 rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                            {{ form.organization_billing_mode === 'later'
                                ? $t('The organization will be created without a plan, and the owner will complete billing after the first sign-in.')
                                : $t('The organization will start with the selected plan immediately after creation.') }}
                        </div>
                    </section>

                    <section v-if="form.creation_mode === 'employee_existing_org'" class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                        <div class="mb-4">
                            <h2 class="text-base font-semibold text-slate-950">{{ $t('Employee access') }}</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ $t('Choose an active company, then assign one or more workspaces and roles.') }}</p>
                        </div>

                        <FormSelect
                            :model-value="form.company_uuid"
                            @update:modelValue="onCompanyChange"
                            :options="companyOptions"
                            :name="$t('Company')"
                            :error="form.errors.company_uuid"
                            :placeholder="$t('Select a company')"
                        />

                        <div v-if="selectedCompanyBlockingMessage" class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            {{ selectedCompanyBlockingMessage }}
                        </div>

                        <div class="mt-5">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <div>
                                    <div class="text-sm font-semibold text-slate-900">{{ $t('Assignments') }}</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ $t('Each workspace can only be assigned once.') }}</div>
                                </div>

                                <button
                                    type="button"
                                    class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="!canAddAssignment"
                                    @click="addAssignment"
                                >
                                    {{ $t('Add assignment') }}
                                </button>
                            </div>

                            <div class="space-y-3">
                                <div v-for="(assignment, index) in form.assignments" :key="assignment.key" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                    <div class="grid gap-3 md:grid-cols-12">
                                        <FormSelect
                                            :model-value="assignment.organization_uuid"
                                            @update:modelValue="value => updateAssignmentWorkspace(index, value)"
                                            :options="workspaceOptionsFor(index)"
                                            :name="$t('Workspace')"
                                            :error="assignmentError(index, 'organization_uuid')"
                                            :placeholder="$t('Select a workspace')"
                                            class-name="md:col-span-5"
                                        />
                                        <FormSelect
                                            :model-value="assignment.organization_role_id"
                                            @update:modelValue="value => updateAssignmentRole(index, value)"
                                            :options="roleOptionsFor(assignment.organization_uuid)"
                                            :name="$t('Role')"
                                            :error="assignmentError(index, 'organization_role_id')"
                                            :placeholder="$t('Select a role')"
                                            class-name="md:col-span-5"
                                        />
                                        <div class="md:col-span-2 flex items-end">
                                            <button
                                                type="button"
                                                class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                                                :disabled="form.assignments.length === 1"
                                                @click="removeAssignment(index)"
                                            >
                                                {{ $t('Remove') }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="form.errors.assignments" class="ui-form-error mt-3">{{ form.errors.assignments }}</div>
                        </div>
                    </section>

                    <div class="flex justify-end gap-3">
                        <Link href="/admin/users" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                            {{ $t('Cancel') }}
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing || employeeModeBlocked"
                            class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-900 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{ form.processing ? $t('Saving...') : $t('Create user') }}
                        </button>
                    </div>
                </div>

                <aside class="space-y-4">
                    <section class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                        <h2 class="text-base font-semibold text-slate-950">{{ $t('Summary') }}</h2>
                        <div class="mt-4 space-y-3 text-sm">
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <div class="text-xs text-slate-500">{{ $t('Flow') }}</div>
                                <div class="mt-1 font-semibold text-slate-900">{{ activeModeLabel }}</div>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <div class="text-xs text-slate-500">{{ $t('Account email') }}</div>
                                <div class="mt-1 font-semibold text-slate-900 break-all">{{ form.email || $t('Not set') }}</div>
                            </div>
                            <div v-if="form.creation_mode === 'owner_new_org'" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <div class="text-xs text-slate-500">{{ $t('Organization') }}</div>
                                <div class="mt-1 font-semibold text-slate-900">{{ form.organization_name || $t('Not set') }}</div>
                                <div class="mt-2 text-xs text-slate-500">
                                    {{ form.organization_billing_mode === 'later' ? $t('Billing later') : $t('Plan selected now') }}
                                </div>
                            </div>
                            <div v-if="form.creation_mode === 'employee_existing_org'" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <div class="text-xs text-slate-500">{{ $t('Company') }}</div>
                                <div class="mt-1 font-semibold text-slate-900">{{ selectedCompanyName || $t('Not set') }}</div>
                                <div class="mt-2 text-xs text-slate-500">{{ $t('Assignments') }}: {{ completedAssignmentsCount }}</div>
                            </div>
                        </div>
                    </section>
                </aside>
            </form>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed, watch } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from './../Layout/App.vue';
import FormInput from '@/Components/FormInput.vue';
import FormPhoneInput from '@/Components/FormPhoneInput.vue';
import FormSelect from '@/Components/FormSelect.vue';

const props = defineProps({
    title: String,
    plans: { type: Array, default: () => [] },
    companies: { type: Array, default: () => [] },
    companyCatalog: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const allowedPhoneCountries = Array.isArray(usePage().props.phoneCountries) ? usePage().props.phoneCountries : [];
let assignmentSeed = 1;

const creationModes = computed(() => ([
    {
        value: 'user_only',
        label: t('Platform user'),
        description: t('Create an account without organization access yet.'),
    },
    {
        value: 'owner_new_org',
        label: t('Owner for a new organization'),
        description: t('Create the account and provision a new main organization for it.'),
    },
    {
        value: 'employee_existing_org',
        label: t('Employee for an existing company'),
        description: t('Create the account and assign workspace access right away.'),
    },
]));

const billingModeOptions = computed(() => ([
    { value: 'later', label: t('Owner completes billing later') },
    { value: 'now', label: t('Select a plan now') },
]));

const planOptions = computed(() => props.plans.map((plan) => ({
    value: plan.uuid,
    label: `${plan.name} · ${plan.period === 'yearly' ? t('Yearly') : t('Monthly')}`,
    disableTranslation: true,
})));

const companyOptions = computed(() => props.companies.map((company) => ({
    value: company.value,
    label: `${company.label} · ${company.meta?.subscription_status_label ?? t('Not set')}`,
    disableTranslation: true,
})));

function newAssignment(assignment = {}) {
    return {
        key: `assignment-${assignmentSeed++}`,
        organization_uuid: assignment.organization_uuid ?? null,
        organization_role_id: assignment.organization_role_id ?? null,
    };
}

const form = useForm({
    creation_mode: 'user_only',
    first_name: null,
    last_name: null,
    email: null,
    phone: null,
    password: null,
    password_confirmation: null,
    send_registration_email: true,
    organization_name: null,
    organization_billing_mode: 'later',
    organization_plan_uuid: null,
    company_uuid: null,
    assignments: [newAssignment()],
});

const selectedCompanyCatalog = computed(() => props.companyCatalog?.[form.company_uuid] ?? null);
const selectedCompanyName = computed(() => props.companies.find((company) => company.value === form.company_uuid)?.label ?? null);
const selectedCompanyBlockingMessage = computed(() => selectedCompanyCatalog.value?.blockingMessage ?? null);
const employeeModeBlocked = computed(() => form.creation_mode === 'employee_existing_org' && Boolean(selectedCompanyBlockingMessage.value));
const activeModeLabel = computed(() => creationModes.value.find((mode) => mode.value === form.creation_mode)?.label ?? t('Platform user'));
const selectedWorkspaceUuids = computed(() => form.assignments.map((assignment) => String(assignment.organization_uuid ?? '').trim()).filter(Boolean));
const hasIncompleteAssignments = computed(() => form.assignments.some((assignment) => String(assignment.organization_uuid ?? '').trim() === '' || Number(assignment.organization_role_id ?? 0) <= 0));
const remainingWorkspaceCount = computed(() => {
    const selected = new Set(selectedWorkspaceUuids.value);
    const workspaces = selectedCompanyCatalog.value?.workspaces ?? [];

    return workspaces.filter((workspace) => {
        const value = String(workspace?.value ?? '').trim();

        return value !== '' && !selected.has(value);
    }).length;
});
const canAddAssignment = computed(() => form.creation_mode === 'employee_existing_org' && form.company_uuid && !employeeModeBlocked.value && !hasIncompleteAssignments.value && remainingWorkspaceCount.value > 0);
const completedAssignmentsCount = computed(() => form.assignments.filter((assignment) => String(assignment.organization_uuid ?? '').trim() !== '' && Number(assignment.organization_role_id ?? 0) > 0).length);

watch(() => form.creation_mode, (mode) => {
    if (mode !== 'owner_new_org') {
        form.organization_name = null;
        form.organization_billing_mode = 'later';
        form.organization_plan_uuid = null;
    }

    if (mode !== 'employee_existing_org') {
        form.company_uuid = null;
        form.assignments = [newAssignment()];
    }
});

watch(() => form.organization_billing_mode, (mode) => {
    if (mode !== 'now') {
        form.organization_plan_uuid = null;
    }
});

function setCreationMode(mode) {
    form.creation_mode = mode;
}

function onCompanyChange(value) {
    form.company_uuid = value;
    form.assignments = [newAssignment()];
}

function addAssignment() {
    if (canAddAssignment.value) {
        form.assignments.push(newAssignment());
    }
}

function removeAssignment(index) {
    if (form.assignments.length > 1) {
        form.assignments.splice(index, 1);
    }
}

function updateAssignmentWorkspace(index, value) {
    const normalizedValue = String(value ?? '').trim();
    const duplicateSelected = form.assignments.some((assignment, assignmentIndex) => assignmentIndex !== index && String(assignment.organization_uuid ?? '').trim() === normalizedValue && normalizedValue !== '');

    if (duplicateSelected) {
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
    if (!organizationUuid) {
        return [];
    }

    return selectedCompanyCatalog.value?.workspaceRoleCatalog?.[organizationUuid] ?? [];
}

function workspaceOptionsFor(index) {
    const workspaces = selectedCompanyCatalog.value?.workspaces ?? [];
    const selectedByOtherRows = new Set(
        form.assignments
            .filter((_, assignmentIndex) => assignmentIndex !== index)
            .map((assignment) => String(assignment.organization_uuid ?? '').trim())
            .filter(Boolean)
    );

    return workspaces.filter((workspace) => {
        const value = String(workspace?.value ?? '').trim();
        const currentValue = String(form.assignments[index]?.organization_uuid ?? '').trim();

        return value === currentValue || !selectedByOtherRows.has(value);
    });
}

function assignmentError(index, field) {
    return form.errors[`assignments.${index}.${field}`] ?? null;
}

function submitForm() {
    form.post('/admin/users', {
        preserveScroll: true,
    });
}
</script>
