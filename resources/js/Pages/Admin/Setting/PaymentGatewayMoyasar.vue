<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full">
            <UiPageHeader
                :title="$t('Moyasar configuration')"
                :subtitle="$t('Manage your Moyasar credentials from a dedicated page and choose which environment is active for new checkouts.')"
            >
                <template #actions>
                    <Link
                        href="/admin/payment-gateways"
                        class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50"
                    >
                        {{ $t('Back to payment gateways') }}
                    </Link>
                </template>
            </UiPageHeader>

            <form @submit.prevent="submitForm" class="mt-6 space-y-6">
                <section class="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-sm">
                    <div class="bg-gradient-to-l from-sky-50 via-white to-slate-50 px-6 py-6 sm:px-8">
                        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(360px,0.8fr)] xl:items-start">
                            <div>
                                <div class="inline-flex items-center gap-2 rounded-full border border-sky-200 bg-sky-100/70 px-3 py-1 text-xs font-semibold text-sky-800">
                                    <span class="inline-flex h-2.5 w-2.5 rounded-full" :class="form.active_mode === 'test' ? 'bg-sky-500' : 'bg-emerald-500'"></span>
                                    {{ form.active_mode === 'test' ? $t('Test environment') : $t('Live environment') }}
                                </div>

                                <h2 class="mt-4 text-2xl font-semibold tracking-tight text-slate-900">
                                    {{ $t('Environment in use') }}
                                </h2>

                                <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-600">
                                    {{ $t('Switch between sandbox and production without leaving this page. Stored credentials for both environments stay ready for the next change.') }}
                                </p>

                                <div class="mt-6 grid gap-3 sm:grid-cols-2">
                                    <button
                                        type="button"
                                        class="flex items-start justify-between rounded-2xl border px-4 py-4 text-start transition"
                                        :class="environmentChoiceClasses('test')"
                                        @click="form.active_mode = 'test'"
                                    >
                                        <div>
                                            <p class="text-sm font-semibold">{{ $t('Test environment') }}</p>
                                            <p class="mt-1 text-xs leading-6 text-slate-600">
                                                {{ $t('These credentials are used for sandbox and QA transactions only.') }}
                                            </p>
                                        </div>
                                        <span class="mt-1 inline-flex h-3 w-3 rounded-full" :class="form.active_mode === 'test' ? 'bg-sky-500' : 'bg-slate-300'"></span>
                                    </button>

                                    <button
                                        type="button"
                                        class="flex items-start justify-between rounded-2xl border px-4 py-4 text-start transition"
                                        :class="environmentChoiceClasses('live')"
                                        @click="form.active_mode = 'live'"
                                    >
                                        <div>
                                            <p class="text-sm font-semibold">{{ $t('Live environment') }}</p>
                                            <p class="mt-1 text-xs leading-6 text-slate-600">
                                                {{ $t('These credentials are used for real customer transactions in production.') }}
                                            </p>
                                        </div>
                                        <span class="mt-1 inline-flex h-3 w-3 rounded-full" :class="form.active_mode === 'live' ? 'bg-emerald-500' : 'bg-slate-300'"></span>
                                    </button>
                                </div>

                                <div v-if="form.errors.active_mode" class="ui-form-error mt-4">
                                    {{ form.errors.active_mode }}
                                </div>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                                <div class="rounded-3xl border border-slate-200 bg-white/90 p-5 shadow-sm">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                                                {{ $t('Gateway status') }}
                                            </p>
                                            <h3 class="mt-3 text-base font-semibold text-slate-900">
                                                {{ form.status ? $t('Active') : $t('Inactive') }}
                                            </h3>
                                            <p class="mt-2 text-sm leading-6 text-slate-600">
                                                {{ $t('Enable or disable Moyasar for new subscription and billing payments.') }}
                                            </p>
                                        </div>

                                        <FormToggleSwitch v-model="form.status" />
                                    </div>

                                    <div v-if="form.errors.status" class="ui-form-error mt-3">
                                        {{ form.errors.status }}
                                    </div>
                                </div>

                                <div class="rounded-3xl border border-slate-200 bg-white/90 p-5 shadow-sm">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                                        {{ $t('Saved secrets') }}
                                    </p>
                                    <h3 class="mt-3 text-base font-semibold text-slate-900">
                                        {{ $t('Secret values stay saved after each successful update. You only need to edit a field when you want to replace the existing value.') }}
                                    </h3>
                                    <p class="mt-2 text-sm leading-6 text-slate-600">
                                        {{ $t('Saved credentials remain available here until you replace them. Use Show when you need to review a hidden secret.') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="grid gap-6 xl:grid-cols-2">
                    <section
                        class="rounded-[28px] border bg-white p-6 shadow-sm transition-colors sm:p-7"
                        :class="environmentPanelClasses('test')"
                    >
                        <div class="flex flex-col gap-4 border-b border-slate-100 pb-5 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold"
                                     :class="form.active_mode === 'test' ? 'border-sky-200 bg-sky-50 text-sky-800' : 'border-slate-200 bg-slate-50 text-slate-600'">
                                    <span class="inline-flex h-2.5 w-2.5 rounded-full" :class="form.active_mode === 'test' ? 'bg-sky-500' : 'bg-slate-300'"></span>
                                    {{ form.active_mode === 'test' ? $t('Currently active') : $t('Standby') }}
                                </div>
                                <h3 class="mt-4 text-xl font-semibold text-slate-900">{{ $t('Test credentials') }}</h3>
                                <p class="mt-2 text-sm leading-7 text-slate-600">
                                    {{ $t('These credentials are used for sandbox and QA transactions only.') }}
                                </p>
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                {{ form.active_mode === 'test' ? $t('Selected for new payments') : $t('Standby') }}
                            </div>
                        </div>

                        <div class="mt-6 space-y-5">
                            <FormInput
                                v-model="form.test.publishable_key"
                                :name="$t('Test publishable key')"
                                :type="'text'"
                                :error="form.errors['test.publishable_key']"
                                :placeholder="'pk_test_...'"
                                :class-name="'space-y-2'"
                            />

                            <div class="space-y-2">
                                <label class="ui-form-label">{{ $t('Test secret key') }}</label>
                                <div class="flex flex-col gap-3 sm:flex-row">
                                    <input
                                        v-model="form.test.secret_key"
                                        :type="secretInputType('test', 'secret_key')"
                                        class="ui-input w-full font-mono text-sm ltr:text-left"
                                        :class="form.errors['test.secret_key'] ? 'ui-input-error' : ''"
                                        dir="ltr"
                                        autocomplete="off"
                                    />
                                    <button
                                        type="button"
                                        class="inline-flex h-11 min-w-[96px] items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                                        @click="toggleSecretVisibility('test', 'secret_key')"
                                    >
                                        {{ secretVisibility.test.secret_key ? $t('Hide') : $t('Show') }}
                                    </button>
                                </div>
                                <div v-if="form.errors['test.secret_key']" class="ui-form-error">{{ form.errors['test.secret_key'] }}</div>
                            </div>

                            <div class="space-y-2">
                                <label class="ui-form-label">{{ $t('Test webhook secret') }}</label>
                                <div class="flex flex-col gap-3 sm:flex-row">
                                    <input
                                        v-model="form.test.webhook_secret"
                                        :type="secretInputType('test', 'webhook_secret')"
                                        class="ui-input w-full font-mono text-sm ltr:text-left"
                                        :class="form.errors['test.webhook_secret'] ? 'ui-input-error' : ''"
                                        dir="ltr"
                                        autocomplete="off"
                                    />
                                    <button
                                        type="button"
                                        class="inline-flex h-11 min-w-[96px] items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                                        @click="toggleSecretVisibility('test', 'webhook_secret')"
                                    >
                                        {{ secretVisibility.test.webhook_secret ? $t('Hide') : $t('Show') }}
                                    </button>
                                </div>
                                <div v-if="form.errors['test.webhook_secret']" class="ui-form-error">{{ form.errors['test.webhook_secret'] }}</div>
                            </div>
                        </div>
                    </section>

                    <section
                        class="rounded-[28px] border bg-white p-6 shadow-sm transition-colors sm:p-7"
                        :class="environmentPanelClasses('live')"
                    >
                        <div class="flex flex-col gap-4 border-b border-slate-100 pb-5 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold"
                                     :class="form.active_mode === 'live' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-slate-50 text-slate-600'">
                                    <span class="inline-flex h-2.5 w-2.5 rounded-full" :class="form.active_mode === 'live' ? 'bg-emerald-500' : 'bg-slate-300'"></span>
                                    {{ form.active_mode === 'live' ? $t('Currently active') : $t('Standby') }}
                                </div>
                                <h3 class="mt-4 text-xl font-semibold text-slate-900">{{ $t('Live credentials') }}</h3>
                                <p class="mt-2 text-sm leading-7 text-slate-600">
                                    {{ $t('These credentials are used for real customer transactions in production.') }}
                                </p>
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                {{ form.active_mode === 'live' ? $t('Selected for new payments') : $t('Standby') }}
                            </div>
                        </div>

                        <div class="mt-6 space-y-5">
                            <FormInput
                                v-model="form.live.publishable_key"
                                :name="$t('Live publishable key')"
                                :type="'text'"
                                :error="form.errors['live.publishable_key']"
                                :placeholder="'pk_live_...'"
                                :class-name="'space-y-2'"
                            />

                            <div class="space-y-2">
                                <label class="ui-form-label">{{ $t('Live secret key') }}</label>
                                <div class="flex flex-col gap-3 sm:flex-row">
                                    <input
                                        v-model="form.live.secret_key"
                                        :type="secretInputType('live', 'secret_key')"
                                        class="ui-input w-full font-mono text-sm ltr:text-left"
                                        :class="form.errors['live.secret_key'] ? 'ui-input-error' : ''"
                                        dir="ltr"
                                        autocomplete="off"
                                    />
                                    <button
                                        type="button"
                                        class="inline-flex h-11 min-w-[96px] items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                                        @click="toggleSecretVisibility('live', 'secret_key')"
                                    >
                                        {{ secretVisibility.live.secret_key ? $t('Hide') : $t('Show') }}
                                    </button>
                                </div>
                                <div v-if="form.errors['live.secret_key']" class="ui-form-error">{{ form.errors['live.secret_key'] }}</div>
                            </div>

                            <div class="space-y-2">
                                <label class="ui-form-label">{{ $t('Live webhook secret') }}</label>
                                <div class="flex flex-col gap-3 sm:flex-row">
                                    <input
                                        v-model="form.live.webhook_secret"
                                        :type="secretInputType('live', 'webhook_secret')"
                                        class="ui-input w-full font-mono text-sm ltr:text-left"
                                        :class="form.errors['live.webhook_secret'] ? 'ui-input-error' : ''"
                                        dir="ltr"
                                        autocomplete="off"
                                    />
                                    <button
                                        type="button"
                                        class="inline-flex h-11 min-w-[96px] items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                                        @click="toggleSecretVisibility('live', 'webhook_secret')"
                                    >
                                        {{ secretVisibility.live.webhook_secret ? $t('Hide') : $t('Show') }}
                                    </button>
                                </div>
                                <div v-if="form.errors['live.webhook_secret']" class="ui-form-error">{{ form.errors['live.webhook_secret'] }}</div>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="rounded-[28px] border border-slate-200 bg-white px-6 py-5 shadow-sm">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-base font-semibold text-slate-900">{{ $t('Saved secrets') }}</p>
                            <p class="mt-2 text-sm leading-7 text-slate-600">
                                {{ $t('Secret values stay saved after each successful update. You only need to edit a field when you want to replace the existing value.') }}
                            </p>
                        </div>

                        <button
                            type="submit"
                            class="inline-flex min-w-[170px] items-center justify-center rounded-xl bg-primary px-5 py-3 text-sm font-medium text-white shadow-sm transition hover:brightness-105 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
                            :class="{ 'opacity-50': form.processing }"
                            :disabled="form.processing"
                        >
                            <svg v-if="form.processing" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" class="me-2">
                                <path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/>
                                <path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z">
                                    <animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/>
                                </path>
                            </svg>
                            <span>{{ $t('Save') }}</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup>
import { reactive } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from './Layout/App.vue';
import FormInput from '@/Components/FormInput.vue';
import FormToggleSwitch from '@/Components/FormToggleSwitch.vue';
import UiPageHeader from '@/Components/Ui/UiPageHeader.vue';

const props = defineProps({
    gateway: {
        type: Object,
        required: true,
    },
});

const buildEnvironmentConfig = (config = {}) => ({
    publishable_key: config?.publishable_key ?? '',
    secret_key: config?.secret_key ?? '',
    webhook_secret: config?.webhook_secret ?? '',
});

const form = useForm({
    active_mode: props.gateway?.active_mode ?? 'test',
    status: Number(props.gateway?.is_active ?? 0) === 1,
    test: buildEnvironmentConfig(props.gateway?.test),
    live: buildEnvironmentConfig(props.gateway?.live),
});

const secretVisibility = reactive({
    test: {
        secret_key: false,
        webhook_secret: false,
    },
    live: {
        secret_key: false,
        webhook_secret: false,
    },
});

const environmentChoiceClasses = (environment) => (
    form.active_mode === environment
        ? environment === 'test'
            ? 'border-sky-300 bg-sky-50 text-slate-900 shadow-sm'
            : 'border-emerald-300 bg-emerald-50 text-slate-900 shadow-sm'
        : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50'
);

const environmentPanelClasses = (environment) => ({
    'border-sky-300 bg-sky-50/40': form.active_mode === environment && environment === 'test',
    'border-emerald-300 bg-emerald-50/40': form.active_mode === environment && environment === 'live',
    'border-slate-200 bg-white': form.active_mode !== environment,
});

const secretInputType = (environment, field) => (
    secretVisibility[environment][field] ? 'text' : 'password'
);

const toggleSecretVisibility = (environment, field) => {
    secretVisibility[environment][field] = !secretVisibility[environment][field];
};

const submitForm = () => {
    form
        .transform((data) => ({
            ...data,
            status: data.status ? '1' : '0',
        }))
        .put('/admin/payment-gateways/moyasar', {
            preserveScroll: true,
        });
};
</script>
