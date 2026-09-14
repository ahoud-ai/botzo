<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full">
            <UiPageHeader
                :title="$t('Moyasar configuration')"
                :subtitle="$t('Manage your Moyasar credentials from a dedicated page and choose which environment is active for new checkouts.')"
            >
                <template #actions>
                    <Link href="/admin/payment-gateways" class="pg-btn pg-btn--ghost">
                        {{ $t('Back to payment gateways') }}
                    </Link>
                </template>
            </UiPageHeader>

            <form @submit.prevent="submitForm" class="mt-6 space-y-6">
                <section class="pg-hero">
                    <div class="pg-hero-wash"></div>
                    <div class="relative grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(360px,0.8fr)] xl:items-start">
                        <div>
                            <div class="pg-status-pill" :style="environmentTone(form.active_mode)">
                                <span class="pg-status-dot"></span>
                                {{ form.active_mode === 'test' ? $t('Test environment') : $t('Live environment') }}
                            </div>

                            <h2 class="pg-hero-title">
                                {{ $t('Environment in use') }}
                            </h2>

                            <p class="pg-hero-copy">
                                {{ $t('Switch between sandbox and production without leaving this page. Stored credentials for both environments stay ready for the next change.') }}
                            </p>

                            <div class="mt-6 grid gap-3 sm:grid-cols-2">
                                <button
                                    type="button"
                                    class="pg-env-choice"
                                    :class="{ 'pg-env-choice--active': form.active_mode === 'test' }"
                                    :style="environmentTone('test')"
                                    @click="form.active_mode = 'test'"
                                >
                                    <div>
                                        <p class="pg-env-choice-title">{{ $t('Test environment') }}</p>
                                        <p class="pg-env-choice-desc">
                                            {{ $t('These credentials are used for sandbox and QA transactions only.') }}
                                        </p>
                                    </div>
                                    <span class="pg-env-choice-dot" :class="{ 'pg-env-choice-dot--on': form.active_mode === 'test' }"></span>
                                </button>

                                <button
                                    type="button"
                                    class="pg-env-choice"
                                    :class="{ 'pg-env-choice--active': form.active_mode === 'live' }"
                                    :style="environmentTone('live')"
                                    @click="form.active_mode = 'live'"
                                >
                                    <div>
                                        <p class="pg-env-choice-title">{{ $t('Live environment') }}</p>
                                        <p class="pg-env-choice-desc">
                                            {{ $t('These credentials are used for real customer transactions in production.') }}
                                        </p>
                                    </div>
                                    <span class="pg-env-choice-dot" :class="{ 'pg-env-choice-dot--on': form.active_mode === 'live' }"></span>
                                </button>
                            </div>

                            <div v-if="form.errors.active_mode" class="ui-form-error mt-4">
                                {{ form.errors.active_mode }}
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                            <div class="pg-tile-card">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="pg-eyebrow">
                                            {{ $t('Gateway status') }}
                                        </p>
                                        <h3 class="pg-tile-card-title">
                                            {{ form.status ? $t('Active') : $t('Inactive') }}
                                        </h3>
                                        <p class="pg-tile-card-copy">
                                            {{ $t('Enable or disable Moyasar for new subscription and billing payments.') }}
                                        </p>
                                    </div>

                                    <FormToggleSwitch v-model="form.status" />
                                </div>

                                <div v-if="form.errors.status" class="ui-form-error mt-3">
                                    {{ form.errors.status }}
                                </div>
                            </div>

                            <div class="pg-tile-card">
                                <p class="pg-eyebrow">
                                    {{ $t('Saved secrets') }}
                                </p>
                                <h3 class="pg-tile-card-title">
                                    {{ $t('Secret values stay saved after each successful update. You only need to edit a field when you want to replace the existing value.') }}
                                </h3>
                                <p class="pg-tile-card-copy">
                                    {{ $t('Saved credentials remain available here until you replace them. Use Show when you need to review a hidden secret.') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="grid gap-6 xl:grid-cols-2">
                    <section class="pg-env-panel" :class="{ 'pg-env-panel--active': form.active_mode === 'test' }" :style="environmentTone('test')">
                        <div class="pg-env-panel-head">
                            <div>
                                <div class="pg-status-pill pg-status-pill--sm" :class="{ 'pg-status-pill--muted': form.active_mode !== 'test' }" :style="environmentTone('test')">
                                    <span class="pg-status-dot"></span>
                                    {{ form.active_mode === 'test' ? $t('Currently active') : $t('Standby') }}
                                </div>
                                <h3 class="pg-env-panel-title">{{ $t('Test credentials') }}</h3>
                                <p class="pg-env-panel-copy">
                                    {{ $t('These credentials are used for sandbox and QA transactions only.') }}
                                </p>
                            </div>

                            <div class="pg-tile">
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
                                    <button type="button" class="pg-btn pg-btn--ghost pg-btn--sm" @click="toggleSecretVisibility('test', 'secret_key')">
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
                                    <button type="button" class="pg-btn pg-btn--ghost pg-btn--sm" @click="toggleSecretVisibility('test', 'webhook_secret')">
                                        {{ secretVisibility.test.webhook_secret ? $t('Hide') : $t('Show') }}
                                    </button>
                                </div>
                                <div v-if="form.errors['test.webhook_secret']" class="ui-form-error">{{ form.errors['test.webhook_secret'] }}</div>
                            </div>
                        </div>
                    </section>

                    <section class="pg-env-panel" :class="{ 'pg-env-panel--active': form.active_mode === 'live' }" :style="environmentTone('live')">
                        <div class="pg-env-panel-head">
                            <div>
                                <div class="pg-status-pill pg-status-pill--sm" :class="{ 'pg-status-pill--muted': form.active_mode !== 'live' }" :style="environmentTone('live')">
                                    <span class="pg-status-dot"></span>
                                    {{ form.active_mode === 'live' ? $t('Currently active') : $t('Standby') }}
                                </div>
                                <h3 class="pg-env-panel-title">{{ $t('Live credentials') }}</h3>
                                <p class="pg-env-panel-copy">
                                    {{ $t('These credentials are used for real customer transactions in production.') }}
                                </p>
                            </div>

                            <div class="pg-tile">
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
                                    <button type="button" class="pg-btn pg-btn--ghost pg-btn--sm" @click="toggleSecretVisibility('live', 'secret_key')">
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
                                    <button type="button" class="pg-btn pg-btn--ghost pg-btn--sm" @click="toggleSecretVisibility('live', 'webhook_secret')">
                                        {{ secretVisibility.live.webhook_secret ? $t('Hide') : $t('Show') }}
                                    </button>
                                </div>
                                <div v-if="form.errors['live.webhook_secret']" class="ui-form-error">{{ form.errors['live.webhook_secret'] }}</div>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="pg-save-bar">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="pg-tile-card-title">{{ $t('Saved secrets') }}</p>
                            <p class="pg-tile-card-copy mt-2">
                                {{ $t('Secret values stay saved after each successful update. You only need to edit a field when you want to replace the existing value.') }}
                            </p>
                        </div>

                        <button
                            type="submit"
                            class="pg-btn pg-btn--solid"
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
import UiPageHeader from '@/Components/UI/UiPageHeader.vue';

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

const environmentTone = (environment) => ({
    '--tone': environment === 'live' ? 'var(--ui-success)' : 'var(--ui-primary)',
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

<style scoped>
.pg-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.85rem;
    padding: 0.6rem 1.2rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: filter 160ms ease, background-color 160ms ease, border-color 160ms ease, opacity 160ms ease;
}

.pg-btn--sm {
    min-width: 6rem;
    padding: 0.55rem 0.9rem;
}

.pg-btn--ghost {
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    color: var(--ui-text);
}

.pg-btn--ghost:hover {
    background: var(--ui-surface-soft);
    border-color: var(--ui-border-strong);
}

.pg-btn--solid {
    min-width: 10rem;
    border: 1px solid transparent;
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.pg-btn--solid:hover:not(:disabled) {
    filter: brightness(1.05);
}

.pg-btn--solid:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

.pg-hero {
    position: relative;
    overflow: hidden;
    border: 1px solid var(--ui-border);
    border-radius: var(--ui-radius-xl, 1.75rem);
    background: var(--ui-surface);
    box-shadow: var(--ui-shadow-1);
    padding: 1.5rem;
}

@media (min-width: 768px) {
    .pg-hero {
        padding: 2rem;
    }
}

.pg-hero-wash {
    position: absolute;
    inset-inline: 0;
    top: 0;
    height: 10rem;
    background: linear-gradient(135deg, color-mix(in srgb, var(--ui-secondary) 10%, transparent), color-mix(in srgb, var(--ui-primary) 6%, transparent), transparent);
    pointer-events: none;
}

.pg-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: 999px;
    border: 1px solid color-mix(in srgb, var(--tone) 30%, var(--ui-border));
    background: color-mix(in srgb, var(--tone) 12%, var(--ui-surface));
    color: color-mix(in srgb, var(--tone) 80%, var(--ui-text));
    padding: 0.4rem 0.9rem;
    font-size: 0.78rem;
    font-weight: 700;
}

.pg-status-pill--sm {
    font-size: 0.74rem;
    padding: 0.3rem 0.75rem;
}

.pg-status-pill--muted {
    border-color: var(--ui-border);
    background: var(--ui-surface-soft);
    color: var(--ui-muted);
}

.pg-status-dot {
    width: 0.55rem;
    height: 0.55rem;
    border-radius: 999px;
    background: var(--tone);
}

.pg-status-pill--muted .pg-status-dot {
    background: var(--ui-border-strong);
}

.pg-hero-title {
    margin-top: 1rem;
    font-size: 1.5rem;
    font-weight: 800;
    letter-spacing: -0.01em;
    color: var(--ui-text);
}

.pg-hero-copy {
    margin-top: 0.75rem;
    max-width: 42rem;
    font-size: 0.88rem;
    line-height: 1.7;
    color: var(--ui-muted);
}

.pg-env-choice {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
    border-radius: 1.1rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    padding: 1rem;
    text-align: start;
    transition: border-color 160ms ease, background-color 160ms ease, box-shadow 160ms ease;
}

.pg-env-choice:hover {
    border-color: var(--ui-border-strong);
    background: var(--ui-surface-soft);
}

.pg-env-choice--active {
    border-color: color-mix(in srgb, var(--tone) 45%, var(--ui-border));
    background: color-mix(in srgb, var(--tone) 8%, var(--ui-surface));
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--tone) 12%, transparent);
}

.pg-env-choice-title {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--ui-text);
}

.pg-env-choice-desc {
    margin-top: 0.25rem;
    font-size: 0.76rem;
    line-height: 1.5;
    color: var(--ui-muted);
}

.pg-env-choice-dot {
    margin-top: 0.15rem;
    width: 0.75rem;
    height: 0.75rem;
    flex-shrink: 0;
    border-radius: 999px;
    background: var(--ui-border-strong);
}

.pg-env-choice-dot--on {
    background: var(--tone);
}

.pg-eyebrow {
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--ui-muted);
}

.pg-tile-card {
    border-radius: 1.25rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    padding: 1.1rem 1.25rem;
    box-shadow: var(--ui-shadow-1);
}

.pg-tile-card-title {
    margin-top: 0.6rem;
    font-size: 0.92rem;
    font-weight: 700;
    color: var(--ui-text);
}

.pg-tile-card-copy {
    margin-top: 0.4rem;
    font-size: 0.82rem;
    line-height: 1.6;
    color: var(--ui-muted);
}

.pg-tile {
    border-radius: 0.9rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    padding: 0.65rem 1rem;
    font-size: 0.82rem;
    color: var(--ui-muted);
}

.pg-env-panel {
    border-radius: var(--ui-radius-xl, 1.75rem);
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    padding: 1.5rem;
    box-shadow: var(--ui-shadow-1);
    transition: border-color 200ms ease, background-color 200ms ease;
}

@media (min-width: 640px) {
    .pg-env-panel {
        padding: 1.75rem;
    }
}

.pg-env-panel--active {
    border-color: color-mix(in srgb, var(--tone) 35%, var(--ui-border));
    background: color-mix(in srgb, var(--tone) 5%, var(--ui-surface));
}

.pg-env-panel-head {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    border-bottom: 1px solid var(--ui-border);
    padding-bottom: 1.25rem;
}

@media (min-width: 640px) {
    .pg-env-panel-head {
        flex-direction: row;
        align-items: flex-start;
        justify-content: space-between;
    }
}

.pg-env-panel-title {
    margin-top: 1rem;
    font-size: 1.2rem;
    font-weight: 800;
    color: var(--ui-text);
}

.pg-env-panel-copy {
    margin-top: 0.5rem;
    font-size: 0.85rem;
    line-height: 1.7;
    color: var(--ui-muted);
}

.pg-save-bar {
    border-radius: var(--ui-radius-xl, 1.75rem);
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    padding: 1.25rem 1.5rem;
    box-shadow: var(--ui-shadow-1);
}
</style>
