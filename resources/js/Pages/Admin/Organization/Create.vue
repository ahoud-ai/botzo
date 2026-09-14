<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full">
            <UiPageHeader :title="$t('Create organization')" :subtitle="$t('Create a new main organization or branch, assign its owner, and decide whether billing starts now or later.')">
                <template #actions>
                    <Link href="/admin/organizations" class="org-btn org-btn--ghost">
                        {{ $t('Back') }}
                    </Link>
                </template>
            </UiPageHeader>

            <form @submit.prevent="submitForm" class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div class="space-y-6">
                    <UiSectionCard :title="$t('Organization type')" :subtitle="$t('Main organizations own billing, while branches inherit plan limits from the parent organization.')">
                        <div class="grid gap-3 md:grid-cols-2">
                            <button
                                v-for="option in organizationTypeOptions"
                                :key="option.value"
                                type="button"
                                class="org-choice-card"
                                :class="{ 'org-choice-card--active': form.organization_type === option.value }"
                                @click="form.organization_type = option.value"
                            >
                                <div class="org-choice-card-title">{{ option.label }}</div>
                                <div class="org-choice-card-desc">{{ option.description }}</div>
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
                    </UiSectionCard>

                    <UiSectionCard v-if="form.organization_type === 'main'" :title="$t('Billing')" :subtitle="$t('You can create the organization without a plan, then let the owner complete billing after the first sign-in.')">
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
                    </UiSectionCard>

                    <UiSectionCard :title="$t('Owner')" :subtitle="$t('Every new organization is created with a single owner account.')">
                        <div class="flex flex-wrap gap-3">
                            <button
                                v-for="option in ownerModeOptions"
                                :key="option.value"
                                type="button"
                                class="org-choice-card"
                                :class="{ 'org-choice-card--active': form.create_user === option.value }"
                                @click="setOwnerMode(option.value)"
                            >
                                <div class="org-choice-card-title">{{ option.label }}</div>
                                <div class="org-choice-card-desc">{{ option.description }}</div>
                            </button>
                        </div>

                        <div v-if="form.create_user === 1" class="mt-5 grid gap-4 md:grid-cols-2">
                            <FormInput v-model="form.first_name" :name="$t('First name')" :error="form.errors.first_name" type="text" />
                            <FormInput v-model="form.last_name" :name="$t('Last name')" :error="form.errors.last_name" type="text" />
                            <FormInput v-model="form.email" :name="$t('Email')" :error="form.errors.email" type="email" />
                            <FormPhoneInput v-model="form.phone" :allowed-countries="allowedPhoneCountries" :name="$t('Phone')" :error="form.errors.phone" type="text" />
                            <FormInput v-model="form.password" :name="$t('Password')" :error="form.errors.password" type="password" />
                            <FormInput v-model="form.password_confirmation" :name="$t('Confirm password')" :error="form.errors.password_confirmation" type="password" />

                            <label class="org-checkbox-row md:col-span-2">
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

                            <div v-if="ownerSearchLoading" class="org-tile">
                                {{ $t('Searching...') }}
                            </div>

                            <div v-else-if="ownerSearchResults.length" class="space-y-2">
                                <button
                                    v-for="user in ownerSearchResults"
                                    :key="user.id"
                                    type="button"
                                    class="org-choice-card org-choice-card--wide"
                                    :class="{ 'org-choice-card--active': form.owner_user_id === user.id }"
                                    @click="selectExistingOwner(user)"
                                >
                                    <div>
                                        <div class="org-choice-card-title">{{ user.full_name || `${user.first_name ?? ''} ${user.last_name ?? ''}`.trim() || $t('Not set') }}</div>
                                        <div class="org-choice-card-desc">{{ user.email }}</div>
                                    </div>
                                    <div v-if="user.phone" class="org-choice-card-meta">{{ user.phone }}</div>
                                </button>
                            </div>

                            <div v-else-if="ownerSearch.length >= 2" class="org-tile org-tile--dashed">
                                {{ $t('No matching users found.') }}
                            </div>

                            <div v-if="selectedExistingOwner" class="org-tile">
                                <p class="org-tile-label">{{ $t('Selected owner') }}</p>
                                <p class="org-tile-value">{{ selectedExistingOwner.full_name || `${selectedExistingOwner.first_name ?? ''} ${selectedExistingOwner.last_name ?? ''}`.trim() || $t('Not set') }}</p>
                                <p class="org-tile-sub">{{ selectedExistingOwner.email }}</p>
                            </div>
                        </div>
                    </UiSectionCard>

                    <UiSectionCard :title="$t('Address')" :subtitle="$t('Optional profile details for the new organization.')">
                        <div class="grid gap-4 md:grid-cols-2">
                            <FormInput v-model="form.street" :name="$t('Street')" :error="form.errors.street" type="text" class-name="md:col-span-2" />
                            <FormInput v-model="form.city" :name="$t('City')" :error="form.errors.city" type="text" />
                            <FormInput v-model="form.state" :name="$t('State')" :error="form.errors.state" type="text" />
                            <FormInput v-model="form.zip" :name="$t('Zip code')" :error="form.errors.zip" type="text" />
                            <FormInput v-model="form.country" :name="$t('Country')" :error="form.errors.country" type="text" />
                        </div>
                    </UiSectionCard>

                    <div class="flex justify-end gap-3">
                        <Link href="/admin/organizations" class="org-btn org-btn--ghost">
                            {{ $t('Cancel') }}
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing || ownerSelectionBlocked"
                            class="org-btn org-btn--solid"
                        >
                            {{ form.processing ? $t('Saving...') : $t('Create organization') }}
                        </button>
                    </div>
                </div>

                <aside class="space-y-4">
                    <UiSectionCard :title="$t('Summary')">
                        <div class="space-y-3 text-sm">
                            <div class="org-tile">
                                <p class="org-tile-label">{{ $t('Type') }}</p>
                                <p class="org-tile-value">{{ form.organization_type === 'branch' ? $t('Branch') : $t('Main organization') }}</p>
                            </div>
                            <div class="org-tile">
                                <p class="org-tile-label">{{ $t('Name') }}</p>
                                <p class="org-tile-value">{{ form.name || $t('Not set') }}</p>
                            </div>
                            <div class="org-tile">
                                <p class="org-tile-label">{{ $t('Owner flow') }}</p>
                                <p class="org-tile-value">{{ form.create_user === 1 ? $t('Create a new owner') : $t('Use an existing owner') }}</p>
                            </div>
                            <div class="org-tile">
                                <p class="org-tile-label">{{ $t('Billing') }}</p>
                                <p class="org-tile-value">
                                    {{ form.organization_type === 'branch'
                                        ? $t('Inherited from the parent organization')
                                        : (form.billing_setup_mode === 'later' ? $t('Owner completes billing later') : $t('Plan selected now')) }}
                                </p>
                            </div>
                        </div>
                    </UiSectionCard>
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
import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
import UiSectionCard from '@/Components/UI/UiSectionCard.vue';

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

<style scoped>
.org-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    border-radius: 0.85rem;
    padding: 0.6rem 1.1rem;
    font-size: 0.88rem;
    font-weight: 600;
    transition: background-color 160ms ease, border-color 160ms ease, filter 160ms ease, opacity 160ms ease;
}

.org-btn--ghost {
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    color: var(--ui-text);
}

.org-btn--ghost:hover {
    background: var(--ui-surface-soft);
    border-color: var(--ui-border-strong);
}

.org-btn--solid {
    border: 1px solid transparent;
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.org-btn--solid:hover:not(:disabled) {
    filter: brightness(1.05);
}

.org-btn--solid:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

.org-choice-card {
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

.org-choice-card:hover {
    border-color: var(--ui-border-strong);
}

.org-choice-card--active {
    border-color: color-mix(in srgb, var(--ui-secondary) 55%, var(--ui-border));
    background: color-mix(in srgb, var(--ui-secondary) 10%, var(--ui-surface));
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--ui-secondary) 12%, transparent);
}

.org-choice-card--wide {
    display: flex;
    flex-direction: row;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
    width: 100%;
}

.org-choice-card-title {
    font-size: 0.92rem;
    font-weight: 700;
    color: var(--ui-text);
}

.org-choice-card-desc {
    font-size: 0.8rem;
    line-height: 1.4;
    color: var(--ui-muted);
}

.org-choice-card-meta {
    font-size: 0.78rem;
    color: var(--ui-muted);
    white-space: nowrap;
}

.org-tile {
    border-radius: 0.9rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    padding: 0.85rem 1rem;
}

.org-tile--dashed {
    border-style: dashed;
    text-align: center;
    color: var(--ui-muted);
}

.org-tile-label {
    font-size: 0.76rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    color: var(--ui-muted);
}

.org-tile-value {
    margin-top: 0.3rem;
    font-size: 0.92rem;
    font-weight: 700;
    color: var(--ui-text);
}

.org-tile-sub {
    margin-top: 0.2rem;
    font-size: 0.82rem;
    color: var(--ui-muted);
}

.org-checkbox-row {
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
</style>
