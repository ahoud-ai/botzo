<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full">
            <UiPageHeader :title="$t('Create user')" :subtitle="$t('Create a standalone account, a new organization owner, or an employee with workspace access.')">
                <template #actions>
                    <Link href="/admin/users" class="usr-btn usr-btn--ghost">
                        {{ $t('Back') }}
                    </Link>
                </template>
            </UiPageHeader>

            <form @submit.prevent="submitForm" class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div class="space-y-6">
                    <UiSectionCard>
                        <div class="flex flex-wrap gap-3">
                            <button
                                v-for="mode in creationModes"
                                :key="mode.value"
                                type="button"
                                class="usr-choice-card"
                                :class="{ 'usr-choice-card--active': form.creation_mode === mode.value }"
                                @click="setCreationMode(mode.value)"
                            >
                                <div class="usr-choice-card-title">{{ mode.label }}</div>
                                <div class="usr-choice-card-desc">{{ mode.description }}</div>
                            </button>
                        </div>
                    </UiSectionCard>

                    <UiSectionCard :title="$t('Account')" :subtitle="$t('The user will receive a platform account and can sign in right away.')">
                        <div class="grid gap-4 md:grid-cols-2">
                            <FormInput v-model="form.first_name" :name="$t('First name')" :error="form.errors.first_name" type="text" />
                            <FormInput v-model="form.last_name" :name="$t('Last name')" :error="form.errors.last_name" type="text" />
                            <FormInput v-model="form.email" :name="$t('Email')" :error="form.errors.email" type="email" />
                            <FormPhoneInput v-model="form.phone" :allowed-countries="allowedPhoneCountries" :name="$t('Phone')" :error="form.errors.phone" type="text" />
                            <FormInput v-model="form.password" :name="$t('Password')" :error="form.errors.password" type="password" />
                            <FormInput v-model="form.password_confirmation" :name="$t('Confirm password')" :error="form.errors.password_confirmation" type="password" />
                        </div>

                        <label class="usr-checkbox-row mt-4">
                            <input v-model="form.send_registration_email" type="checkbox" class="ui-checkbox-input">
                            <span>{{ $t('Send a registration email after saving') }}</span>
                        </label>
                    </UiSectionCard>

                    <UiSectionCard v-if="form.creation_mode === 'owner_new_org'" :title="$t('Owner workspace')" :subtitle="$t('Create a main organization and assign this account as the owner.')">
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

                        <div class="usr-banner usr-banner--info mt-4">
                            {{ form.organization_billing_mode === 'later'
                                ? $t('The organization will be created without a plan, and the owner will complete billing after the first sign-in.')
                                : $t('The organization will start with the selected plan immediately after creation.') }}
                        </div>
                    </UiSectionCard>

                    <UiSectionCard v-if="form.creation_mode === 'employee_existing_org'" :title="$t('Employee access')" :subtitle="$t('Choose an active company, then assign one or more workspaces and roles.')">
                        <FormSelect
                            :model-value="form.company_uuid"
                            @update:modelValue="onCompanyChange"
                            :options="companyOptions"
                            :name="$t('Company')"
                            :error="form.errors.company_uuid"
                            :placeholder="$t('Select a company')"
                        />

                        <div v-if="selectedCompanyBlockingMessage" class="usr-banner usr-banner--warning mt-4">
                            {{ selectedCompanyBlockingMessage }}
                        </div>

                        <div class="mt-5">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <div>
                                    <div class="usr-choice-card-title">{{ $t('Assignments') }}</div>
                                    <div class="usr-choice-card-desc mt-1">{{ $t('Each workspace can only be assigned once.') }}</div>
                                </div>

                                <button
                                    type="button"
                                    class="usr-btn usr-btn--ghost usr-btn--sm"
                                    :disabled="!canAddAssignment"
                                    @click="addAssignment"
                                >
                                    {{ $t('Add assignment') }}
                                </button>
                            </div>

                            <div class="space-y-3">
                                <div v-for="(assignment, index) in form.assignments" :key="assignment.key" class="usr-tile">
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
                                                class="usr-btn usr-btn--ghost w-full justify-center"
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
                    </UiSectionCard>

                    <div class="flex justify-end gap-3">
                        <Link href="/admin/users" class="usr-btn usr-btn--ghost">
                            {{ $t('Cancel') }}
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing || employeeModeBlocked"
                            class="usr-btn usr-btn--solid"
                        >
                            {{ form.processing ? $t('Saving...') : $t('Create user') }}
                        </button>
                    </div>
                </div>

                <aside class="space-y-4">
                    <UiSectionCard :title="$t('Summary')">
                        <div class="space-y-3 text-sm">
                            <div class="usr-tile">
                                <p class="usr-tile-label">{{ $t('Flow') }}</p>
                                <p class="usr-tile-value">{{ activeModeLabel }}</p>
                            </div>
                            <div class="usr-tile">
                                <p class="usr-tile-label">{{ $t('Account email') }}</p>
                                <p class="usr-tile-value break-all">{{ form.email || $t('Not set') }}</p>
                            </div>
                            <div v-if="form.creation_mode === 'owner_new_org'" class="usr-tile">
                                <p class="usr-tile-label">{{ $t('Organization') }}</p>
                                <p class="usr-tile-value">{{ form.organization_name || $t('Not set') }}</p>
                                <p class="usr-tile-sub">{{ form.organization_billing_mode === 'later' ? $t('Billing later') : $t('Plan selected now') }}</p>
                            </div>
                            <div v-if="form.creation_mode === 'employee_existing_org'" class="usr-tile">
                                <p class="usr-tile-label">{{ $t('Company') }}</p>
                                <p class="usr-tile-value">{{ selectedCompanyName || $t('Not set') }}</p>
                                <p class="usr-tile-sub">{{ $t('Assignments') }}: {{ completedAssignmentsCount }}</p>
                            </div>
                        </div>
                    </UiSectionCard>
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
import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
import UiSectionCard from '@/Components/UI/UiSectionCard.vue';

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

<style scoped>
.usr-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    border-radius: 0.85rem;
    padding: 0.6rem 1.1rem;
    font-size: 0.88rem;
    font-weight: 600;
    transition: background-color 160ms ease, border-color 160ms ease, filter 160ms ease, opacity 160ms ease;
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

.usr-btn--ghost:hover:not(:disabled) {
    background: var(--ui-surface-soft);
    border-color: var(--ui-border-strong);
}

.usr-btn--ghost:disabled {
    cursor: not-allowed;
    opacity: 0.55;
}

.usr-btn--solid {
    border: 1px solid transparent;
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.usr-btn--solid:hover:not(:disabled) {
    filter: brightness(1.05);
}

.usr-btn--solid:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

.usr-choice-card {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    border-radius: 1rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    padding: 0.9rem 1.05rem;
    text-align: start;
    transition: border-color 160ms ease, background-color 160ms ease, box-shadow 160ms ease;
}

.usr-choice-card:hover {
    border-color: var(--ui-border-strong);
}

.usr-choice-card--active {
    border-color: color-mix(in srgb, var(--ui-secondary) 55%, var(--ui-border));
    background: color-mix(in srgb, var(--ui-secondary) 10%, var(--ui-surface));
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--ui-secondary) 12%, transparent);
}

.usr-choice-card-title {
    font-size: 0.92rem;
    font-weight: 700;
    color: var(--ui-text);
}

.usr-choice-card-desc {
    font-size: 0.8rem;
    line-height: 1.4;
    color: var(--ui-muted);
}

.usr-tile {
    border-radius: 0.9rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    padding: 0.85rem 1rem;
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

.usr-tile-sub {
    margin-top: 0.3rem;
    font-size: 0.78rem;
    color: var(--ui-muted);
}

.usr-checkbox-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    border-radius: 1rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    padding: 0.85rem 1.05rem;
    font-size: 0.88rem;
    color: var(--ui-text);
}

.usr-banner {
    border: 1px solid;
    border-radius: 1rem;
    padding: 0.9rem 1.05rem;
    font-size: 0.88rem;
    line-height: 1.5;
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
</style>
