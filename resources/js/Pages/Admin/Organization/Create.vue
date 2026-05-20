<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-950">{{ $t('Create organization') }}</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                        {{ $t('Create a new main organization or branch, assign its owner, and decide whether billing starts now or later.') }}
                    </p>
                </div>

                <Link href="/admin/organizations" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
                    {{ $t('Back') }}
                </Link>
            </div>

            <form @submit.prevent="submitForm" class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div class="space-y-6">
                    <section class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                        <div class="mb-4">
                            <h2 class="text-base font-semibold text-slate-950">{{ $t('Organization type') }}</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ $t('Main organizations own billing, while branches inherit plan limits from the parent organization.') }}</p>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            <button
                                v-for="option in organizationTypeOptions"
                                :key="option.value"
                                type="button"
                                class="rounded-2xl border px-4 py-3 text-start text-sm transition"
                                :class="form.organization_type === option.value ? 'border-indigo-300 bg-indigo-50 text-indigo-900' : 'border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100'"
                                @click="form.organization_type = option.value"
                            >
                                <div class="font-semibold">{{ option.label }}</div>
                                <div class="mt-1 text-xs leading-5 text-slate-500">{{ option.description }}</div>
                            </button>
                        </div>

                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <FormInput v-model="form.name" :name="$t('Organization name')" :error="form.errors.name" type="text" class-name="md:col-span-2" />

                            <FormSelect
                                v-if="form.organization_type === 'branch'"
                                :model-value="form.parent_organization_uuid"
                                @update:modelValue="value => form.parent_organization_uuid = value"
                                :options="parentOrganizationOptions"
                                :name="$t('Parent organization')"
                                :error="form.errors.parent_organization_uuid"
                                :placeholder="$t('Select a parent organization')"
                                class-name="md:col-span-2"
                            />
                        </div>
                    </section>

                    <section v-if="form.organization_type === 'main'" class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                        <div class="mb-4">
                            <h2 class="text-base font-semibold text-slate-950">{{ $t('Billing') }}</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ $t('You can create the organization without a plan, then let the owner complete billing after the first sign-in.') }}</p>
                        </div>

                        <FormSelect
                            :model-value="form.billing_setup_mode"
                            @update:modelValue="value => form.billing_setup_mode = value"
                            :options="billingModeOptions"
                            :name="$t('Billing setup')"
                            :error="form.errors.billing_setup_mode"
                            :placeholder="$t('Select option')"
                        />

                        <FormSelect
                            v-if="form.billing_setup_mode === 'now'"
                            :model-value="form.plan"
                            @update:modelValue="value => form.plan = value"
                            :options="planOptions"
                            :name="$t('Subscription plan')"
                            :error="form.errors.plan"
                            :placeholder="$t('Select a plan')"
                            class-name="mt-4"
                        />
                    </section>

                    <section class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                        <div class="mb-4">
                            <h2 class="text-base font-semibold text-slate-950">{{ $t('Owner') }}</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ $t('Every new organization is created with a single owner account.') }}</p>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <button
                                v-for="option in ownerModeOptions"
                                :key="option.value"
                                type="button"
                                class="rounded-2xl border px-4 py-3 text-start text-sm transition"
                                :class="form.create_user === option.value ? 'border-indigo-300 bg-indigo-50 text-indigo-900' : 'border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100'"
                                @click="setOwnerMode(option.value)"
                            >
                                <div class="font-semibold">{{ option.label }}</div>
                                <div class="mt-1 text-xs leading-5 text-slate-500">{{ option.description }}</div>
                            </button>
                        </div>

                        <div v-if="form.create_user === 1" class="mt-5 grid gap-4 md:grid-cols-2">
                            <FormInput v-model="form.first_name" :name="$t('First name')" :error="form.errors.first_name" type="text" />
                            <FormInput v-model="form.last_name" :name="$t('Last name')" :error="form.errors.last_name" type="text" />
                            <FormInput v-model="form.email" :name="$t('Email')" :error="form.errors.email" type="email" />
                            <FormPhoneInput v-model="form.phone" :allowed-countries="allowedPhoneCountries" :name="$t('Phone')" :error="form.errors.phone" type="text" />
                            <FormInput v-model="form.password" :name="$t('Password')" :error="form.errors.password" type="password" />
                            <FormInput v-model="form.password_confirmation" :name="$t('Confirm password')" :error="form.errors.password_confirmation" type="password" />

                            <label class="md:col-span-2 flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                                <input v-model="form.send_registration_email" type="checkbox" class="ui-checkbox-input">
                                <span>{{ $t('Send a registration email after saving') }}</span>
                            </label>
                        </div>

                        <div v-else class="mt-5 space-y-4">
                            <FormInput
                                v-model="ownerSearch"
                                :name="$t('Search for an existing user')"
                                :placeholder="$t('Search by name or email')"
                                :error="form.errors.owner_user_id || form.errors.email"
                                type="text"
                            />

                            <div v-if="ownerSearchLoading" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500">
                                {{ $t('Searching...') }}
                            </div>

                            <div v-else-if="ownerSearchResults.length" class="space-y-2">
                                <button
                                    v-for="user in ownerSearchResults"
                                    :key="user.id"
                                    type="button"
                                    class="flex w-full items-start justify-between rounded-2xl border px-4 py-3 text-start transition"
                                    :class="form.owner_user_id === user.id ? 'border-indigo-300 bg-indigo-50' : 'border-slate-200 bg-slate-50 hover:bg-slate-100'"
                                    @click="selectExistingOwner(user)"
                                >
                                    <div>
                                        <div class="font-semibold text-slate-900">{{ user.full_name || `${user.first_name ?? ''} ${user.last_name ?? ''}`.trim() || $t('Not set') }}</div>
                                        <div class="mt-1 text-sm text-slate-500">{{ user.email }}</div>
                                    </div>
                                    <div v-if="user.phone" class="text-xs text-slate-500">{{ user.phone }}</div>
                                </button>
                            </div>

                            <div v-else-if="ownerSearch.length >= 2" class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                                {{ $t('No matching users found.') }}
                            </div>

                            <div v-if="selectedExistingOwner" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm">
                                <div class="text-xs text-slate-500">{{ $t('Selected owner') }}</div>
                                <div class="mt-1 font-semibold text-slate-900">{{ selectedExistingOwner.full_name || `${selectedExistingOwner.first_name ?? ''} ${selectedExistingOwner.last_name ?? ''}`.trim() || $t('Not set') }}</div>
                                <div class="mt-1 text-slate-500">{{ selectedExistingOwner.email }}</div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                        <div class="mb-4">
                            <h2 class="text-base font-semibold text-slate-950">{{ $t('Address') }}</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ $t('Optional profile details for the new organization.') }}</p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <FormInput v-model="form.street" :name="$t('Street')" :error="form.errors.street" type="text" class-name="md:col-span-2" />
                            <FormInput v-model="form.city" :name="$t('City')" :error="form.errors.city" type="text" />
                            <FormInput v-model="form.state" :name="$t('State')" :error="form.errors.state" type="text" />
                            <FormInput v-model="form.zip" :name="$t('Zip code')" :error="form.errors.zip" type="text" />
                            <FormInput v-model="form.country" :name="$t('Country')" :error="form.errors.country" type="text" />
                        </div>
                    </section>

                    <div class="flex justify-end gap-3">
                        <Link href="/admin/organizations" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                            {{ $t('Cancel') }}
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing || ownerSelectionBlocked"
                            class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-900 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{ form.processing ? $t('Saving...') : $t('Create organization') }}
                        </button>
                    </div>
                </div>

                <aside class="space-y-4">
                    <section class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60">
                        <h2 class="text-base font-semibold text-slate-950">{{ $t('Summary') }}</h2>
                        <div class="mt-4 space-y-3 text-sm">
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <div class="text-xs text-slate-500">{{ $t('Type') }}</div>
                                <div class="mt-1 font-semibold text-slate-900">{{ form.organization_type === 'branch' ? $t('Branch') : $t('Main organization') }}</div>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <div class="text-xs text-slate-500">{{ $t('Name') }}</div>
                                <div class="mt-1 font-semibold text-slate-900">{{ form.name || $t('Not set') }}</div>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <div class="text-xs text-slate-500">{{ $t('Owner flow') }}</div>
                                <div class="mt-1 font-semibold text-slate-900">{{ form.create_user === 1 ? $t('Create a new owner') : $t('Use an existing owner') }}</div>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <div class="text-xs text-slate-500">{{ $t('Billing') }}</div>
                                <div class="mt-1 font-semibold text-slate-900">
                                    {{ form.organization_type === 'branch'
                                        ? $t('Inherited from the parent organization')
                                        : (form.billing_setup_mode === 'later' ? $t('Owner completes billing later') : $t('Plan selected now')) }}
                                </div>
                            </div>
                        </div>
                    </section>
                </aside>
            </form>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from './../Layout/App.vue';
import FormInput from '@/Components/FormInput.vue';
import FormPhoneInput from '@/Components/FormPhoneInput.vue';
import FormSelect from '@/Components/FormSelect.vue';

const props = defineProps({
    title: String,
    plans: { type: Array, default: () => [] },
    parentOrganizations: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const allowedPhoneCountries = Array.isArray(usePage().props.phoneCountries) ? usePage().props.phoneCountries : [];
const ownerSearch = ref('');
const ownerSearchResults = ref([]);
const ownerSearchLoading = ref(false);
const selectedExistingOwner = ref(null);
let ownerSearchDebounce = null;

const organizationTypeOptions = computed(() => ([
    {
        value: 'main',
        label: t('Main organization'),
        description: t('Owns billing and can create branches later.'),
    },
    {
        value: 'branch',
        label: t('Branch'),
        description: t('Uses the parent organization subscription and limits.'),
    },
]));

const ownerModeOptions = computed(() => ([
    {
        value: 1,
        label: t('Create a new owner'),
        description: t('Create the user account now and assign ownership immediately.'),
    },
    {
        value: 0,
        label: t('Use an existing user'),
        description: t('Find an existing platform user and assign ownership to them.'),
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

const parentOrganizationOptions = computed(() => props.parentOrganizations.map((organization) => ({
    value: organization.uuid,
    label: organization.name,
    disableTranslation: true,
})));

const form = useForm({
    name: null,
    organization_type: 'main',
    billing_setup_mode: 'later',
    plan: null,
    parent_organization_uuid: null,
    create_user: 1,
    owner_user_id: null,
    first_name: null,
    last_name: null,
    email: null,
    phone: null,
    password: null,
    password_confirmation: null,
    send_registration_email: true,
    street: null,
    city: null,
    state: null,
    zip: null,
    country: null,
});

const ownerSelectionBlocked = computed(() => form.create_user === 0 && !form.owner_user_id);

watch(() => form.organization_type, (type) => {
    if (type !== 'branch') {
        form.parent_organization_uuid = null;
        return;
    }

    form.billing_setup_mode = 'later';
    form.plan = null;
});

watch(() => form.billing_setup_mode, (mode) => {
    if (mode !== 'now') {
        form.plan = null;
    }
});

watch(() => form.create_user, (value) => {
    if (value === 1) {
        form.owner_user_id = null;
        selectedExistingOwner.value = null;
        ownerSearch.value = '';
        ownerSearchResults.value = [];
        return;
    }

    form.first_name = null;
    form.last_name = null;
    form.email = null;
    form.phone = null;
    form.password = null;
    form.password_confirmation = null;
});

watch(ownerSearch, (value) => {
    if (form.create_user !== 0) {
        return;
    }

    if (ownerSearchDebounce) {
        clearTimeout(ownerSearchDebounce);
    }

    const normalized = String(value ?? '').trim();

    if (normalized.length < 2) {
        ownerSearchResults.value = [];
        ownerSearchLoading.value = false;
        return;
    }

    ownerSearchDebounce = setTimeout(() => {
        searchExistingUsers(normalized);
    }, 250);
});

function setOwnerMode(value) {
    form.create_user = value;
}

async function searchExistingUsers(query) {
    ownerSearchLoading.value = true;

    try {
        const response = await window.fetch(`/admin/users?search=${encodeURIComponent(query)}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            ownerSearchResults.value = [];
            return;
        }

        const payload = await response.json();
        ownerSearchResults.value = Array.isArray(payload?.rows) ? payload.rows : [];
    } catch (error) {
        ownerSearchResults.value = [];
    } finally {
        ownerSearchLoading.value = false;
    }
}

function selectExistingOwner(user) {
    selectedExistingOwner.value = user;
    form.owner_user_id = user.id;
    form.email = user.email;
    ownerSearch.value = user.full_name || `${user.first_name ?? ''} ${user.last_name ?? ''}`.trim() || user.email;
    ownerSearchResults.value = [];
}

function submitForm() {
    form.post('/admin/organizations', {
        preserveScroll: true,
    });
}
</script>
