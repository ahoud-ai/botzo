<template>
    <SettingLayout :aimodule="aimodule">
        <div class="mx-auto w-full max-w-5xl pb-16">
            <div class="mb-5">
                <h1 class="text-xl font-semibold text-[var(--ui-text)]">{{ $t('Website widget') }}</h1>
                <p class="mt-1 text-sm text-[var(--ui-muted)]">{{ $t('Add a chat window to your website. Your AI assistant answers visitors with the same information it uses on WhatsApp.') }}</p>
            </div>

            <div v-if="!aiActive" class="wg-warning mb-4" role="status">
                {{ $t('The AI assistant is off, so visitor messages will wait for your team in the inbox.') }}
            </div>

            <div class="flex flex-wrap items-start gap-6">
                <form @submit.prevent="save" class="min-w-0 flex-[999_1_480px] space-y-4">
                    <section class="wg-card">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="min-w-0">
                                <h2 class="wg-card-title">{{ $t('Show the widget on my website') }}</h2>
                                <p class="wg-card-desc">{{ $t('Visitors see the chat button only on the domains you add below.') }}</p>
                            </div>
                            <FormToggleSwitch v-model="form.is_enabled" />
                        </div>
                    </section>

                    <section class="wg-card">
                        <h2 class="wg-card-title">{{ $t('Allowed domains') }}</h2>
                        <p class="wg-card-desc">{{ $t('The widget works only on these websites. Example: mystore.com') }}</p>
                        <div class="mt-4 space-y-2">
                            <div v-for="(domain, index) in form.allowed_domains" :key="index" class="flex items-start gap-2">
                                <div class="min-w-0 flex-1">
                                    <label :for="`wg-domain-${index}`" class="sr-only">{{ $t('Domain') }} {{ index + 1 }}</label>
                                    <input :id="`wg-domain-${index}`" v-model="form.allowed_domains[index]" type="text" dir="ltr" class="ui-input sm:text-sm sm:leading-6" :class="domainError(index) ? 'ui-input-error' : ''" placeholder="mystore.com" maxlength="255" autocomplete="off" />
                                    <p v-if="domainError(index)" class="mt-1 text-xs text-red-600">{{ domainError(index) }}</p>
                                </div>
                                <button type="button" class="wg-icon-btn" @click="removeDomain(index)" :aria-label="$t('Remove domain')">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M6 6l12 12M18 6L6 18"/></svg>
                                </button>
                            </div>
                            <p v-if="form.errors.allowed_domains" class="text-xs text-red-600">{{ form.errors.allowed_domains }}</p>
                            <button v-if="form.allowed_domains.length < limits.domains" type="button" class="wg-btn-ghost" @click="addDomain">{{ $t('Add domain') }}</button>
                        </div>
                    </section>

                    <section class="wg-card">
                        <h2 class="wg-card-title">{{ $t('Look') }}</h2>
                        <fieldset class="mt-4">
                            <legend class="ui-form-label">{{ $t('Widget style') }}</legend>
                            <div class="flex flex-wrap gap-2">
                                <label class="wg-choice" :class="form.theme === 'botzo' ? 'wg-choice--active' : ''">
                                    <input v-model="form.theme" type="radio" value="botzo" class="sr-only" />
                                    {{ $t('Dark, Botzo style') }}
                                </label>
                                <label class="wg-choice" :class="form.theme === 'classic' ? 'wg-choice--active' : ''">
                                    <input v-model="form.theme" type="radio" value="classic" class="sr-only" />
                                    {{ $t('Light, in your color') }}
                                </label>
                            </div>
                        </fieldset>
                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="wg-accent" class="ui-form-label">{{ $t('Main color') }}</label>
                                <div class="flex items-center gap-2">
                                    <input id="wg-accent" v-model="form.accent" type="color" class="h-10 w-12 cursor-pointer rounded-lg border border-[var(--ui-border)] bg-transparent p-1" />
                                    <input v-model="form.accent" type="text" dir="ltr" maxlength="7" class="ui-input sm:text-sm sm:leading-6" :aria-label="$t('Main color')" />
                                </div>
                                <p v-if="form.theme === 'botzo'" class="mt-1 text-xs text-[var(--ui-muted)]">{{ $t('The color is used by the light style only.') }}</p>
                                <p v-if="form.errors.accent" class="mt-1 text-xs text-red-600">{{ form.errors.accent }}</p>
                            </div>
                            <fieldset>
                                <legend class="ui-form-label">{{ $t('Button position') }}</legend>
                                <div class="flex gap-2">
                                    <label class="wg-choice" :class="form.position === 'right' ? 'wg-choice--active' : ''">
                                        <input v-model="form.position" type="radio" value="right" class="sr-only" />
                                        {{ $t('Right') }}
                                    </label>
                                    <label class="wg-choice" :class="form.position === 'left' ? 'wg-choice--active' : ''">
                                        <input v-model="form.position" type="radio" value="left" class="sr-only" />
                                        {{ $t('Left') }}
                                    </label>
                                </div>
                            </fieldset>
                        </div>
                    </section>

                    <section class="wg-card">
                        <h2 class="wg-card-title">{{ $t('Messages') }}</h2>
                        <div class="mt-4 space-y-4">
                            <div>
                                <label for="wg-name" class="ui-form-label">{{ $t('Assistant name') }}</label>
                                <input id="wg-name" v-model="form.assistant_name" type="text" maxlength="60" class="ui-input sm:text-sm sm:leading-6" :placeholder="organizationName" />
                                <p class="mt-1 text-xs text-[var(--ui-muted)]">{{ $t('Leave empty to use your business name.') }}</p>
                            </div>
                            <div>
                                <label for="wg-welcome" class="ui-form-label">{{ $t('Welcome message') }}</label>
                                <textarea id="wg-welcome" v-model="form.welcome_text" rows="3" :maxlength="limits.welcome_length" class="ui-input sm:text-sm sm:leading-6" :placeholder="$t('Hi! How can I help you today?')"></textarea>
                                <p v-if="form.errors.welcome_text" class="mt-1 text-xs text-red-600">{{ form.errors.welcome_text }}</p>
                            </div>
                            <fieldset>
                                <legend class="ui-form-label">{{ $t('Quick questions') }}</legend>
                                <p class="mb-2 text-xs text-[var(--ui-muted)]">{{ $t('Up to {count} buttons visitors can tap instead of typing.', { count: limits.suggestions }) }}</p>
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    <div v-for="index in limits.suggestions" :key="index">
                                        <label :for="`wg-suggestion-${index}`" class="sr-only">{{ $t('Quick question') }} {{ index }}</label>
                                        <input :id="`wg-suggestion-${index}`" v-model="form.suggestions[index - 1]" type="text" :maxlength="limits.suggestion_length" class="ui-input sm:text-sm sm:leading-6" />
                                    </div>
                                </div>
                            </fieldset>
                        </div>
                    </section>

                    <section class="wg-card">
                        <h2 class="wg-card-title">{{ $t('Protection') }}</h2>
                        <div class="mt-4 max-w-xs">
                            <label for="wg-limit" class="ui-form-label">{{ $t('AI replies per day') }}</label>
                            <input id="wg-limit" v-model.number="form.daily_reply_limit" type="number" min="0" max="5000" class="ui-input sm:text-sm sm:leading-6" />
                            <p class="mt-1 text-xs text-[var(--ui-muted)]">{{ $t('After this, visitor messages wait for your team until tomorrow.') }}</p>
                            <p v-if="form.errors.daily_reply_limit" class="mt-1 text-xs text-red-600">{{ form.errors.daily_reply_limit }}</p>
                        </div>
                    </section>

                    <div class="flex justify-end">
                        <button type="submit" class="wg-btn-primary" :disabled="form.processing">{{ form.processing ? $t('Saving...') : $t('Save') }}</button>
                    </div>
                </form>

                <aside class="min-w-0 flex-[1_1_320px] space-y-4">
                    <section class="wg-card">
                        <h2 class="wg-card-title">{{ $t('Preview') }}</h2>
                        <div class="wg-preview mt-4" :class="[form.position === 'left' ? 'items-start' : 'items-end', isBotzo ? 'wg-preview--botzo' : '']">
                            <div class="wg-preview-panel">
                                <div class="wg-preview-header" :style="{ background: isBotzo ? 'linear-gradient(135deg, #22D366, #2F7BFF)' : previewAccent }">
                                    <div v-if="isBotzo" class="wg-preview-avatar wg-preview-avatar--icon" aria-hidden="true" v-html="BOTZO_HEADER_ICON"></div>
                                    <div v-else class="wg-preview-avatar" aria-hidden="true">{{ previewInitial }}</div>
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-semibold">{{ previewName }}</div>
                                        <div class="text-xs opacity-90">{{ $t('Online · replies instantly') }}</div>
                                    </div>
                                </div>
                                <div class="wg-preview-body">
                                    <div class="wg-preview-bubble">{{ form.welcome_text || $t('Hi! How can I help you today?') }}</div>
                                    <div class="flex flex-wrap gap-1.5">
                                        <span v-for="(item, index) in previewSuggestions" :key="index" class="wg-preview-chip" :style="{ color: chipColor, borderColor: chipColor }">{{ item }}</span>
                                    </div>
                                </div>
                                <div class="wg-preview-input">{{ $t('Type your question...') }}</div>
                                <div class="wg-preview-footer">{{ $t('An AI assistant replies to you') }} · Powered by <strong>Botzo</strong></div>
                            </div>
                            <div v-if="isBotzo" class="wg-preview-launcher wg-preview-launcher--icon" aria-hidden="true" v-html="BOTZO_LAUNCHER_ICON"></div>
                            <div v-else class="wg-preview-launcher" :style="{ background: previewAccent }" aria-hidden="true">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/></svg>
                            </div>
                        </div>
                    </section>

                    <section class="wg-card">
                        <h2 class="wg-card-title">{{ $t('Embed code') }}</h2>
                        <template v-if="embedCode">
                            <p class="wg-card-desc">{{ $t('Paste this line at the end of every page of your website, or in the custom code box of your store platform.') }}</p>
                            <label for="wg-code" class="sr-only">{{ $t('Embed code') }}</label>
                            <textarea id="wg-code" :value="embedCode" readonly rows="3" dir="ltr" class="ui-input mt-3 font-mono text-xs" @focus="$event.target.select()"></textarea>
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <button type="button" class="wg-btn-primary" @click="copyCode">{{ copied ? $t('Copied') : $t('Copy code') }}</button>
                                <button type="button" class="wg-btn-ghost" @click="regenerate">{{ $t('Create a new code') }}</button>
                            </div>
                            <p class="mt-2 text-xs text-[var(--ui-muted)]" aria-live="polite">{{ copyMessage }}</p>
                        </template>
                        <p v-else class="wg-card-desc">{{ $t('Save the settings once to get your embed code.') }}</p>
                    </section>
                </aside>
            </div>
        </div>
    </SettingLayout>
</template>

<script setup>
    import SettingLayout from "./../../../../resources/js/Pages/User/Automation/Layout.vue";
    import { computed, ref } from 'vue';
    import { router, useForm } from "@inertiajs/vue3";
    import { trans } from 'laravel-vue-i18n';
    import FormToggleSwitch from '@/Components/FormToggleSwitch.vue';

    const props = defineProps({
        aimodule: Boolean,
        organizationName: String,
        aiActive: Boolean,
        widget: Object,
        embedCode: String,
        limits: Object,
    });

    const initialSuggestions = Array.from({ length: props.limits.suggestions }, (_, index) => props.widget.settings.suggestions?.[index] ?? '');

    // The same fixed brand icons the widget script shows in the dark Botzo style.
    const BOTZO_HEADER_ICON = '<svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 44 44"><circle cx="22" cy="22" r="22" fill="#0A0E16"/><g transform="translate(6 6) scale(0.2667)"><path d="M18 62a42 42 0 0 1 84 0" fill="none" stroke="#F2F5F9" stroke-width="8" stroke-linecap="round"/><rect x="24" y="30" width="72" height="66" rx="26" fill="#22D366"/><rect x="8" y="54" width="18" height="30" rx="9" fill="#F2F5F9"/><rect x="94" y="54" width="18" height="30" rx="9" fill="#F2F5F9"/><circle cx="48" cy="60" r="8" fill="#0A0E16"/><circle cx="72" cy="60" r="8" fill="#0A0E16"/><path d="M50 76q10 6 20 0" stroke="#0A0E16" stroke-width="6" fill="none" stroke-linecap="round"/></g><circle cx="7" cy="37" r="5" fill="#22D366" stroke="#0A0E16" stroke-width="2"/></svg>';
    const BOTZO_LAUNCHER_ICON = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64"><circle cx="32" cy="32" r="32" fill="#22D366"/><g transform="translate(11 11) scale(0.35)"><path d="M18 62a42 42 0 0 1 84 0" fill="none" stroke="#0A0E16" stroke-width="8" stroke-linecap="round"/><rect x="24" y="30" width="72" height="66" rx="26" fill="#0A0E16"/><rect x="8" y="54" width="18" height="30" rx="9" fill="#0A0E16"/><rect x="94" y="54" width="18" height="30" rx="9" fill="#0A0E16"/><circle cx="48" cy="60" r="8" fill="#22D366"/><circle cx="72" cy="60" r="8" fill="#22D366"/><path d="M50 76q10 6 20 0" stroke="#22D366" stroke-width="6" fill="none" stroke-linecap="round"/></g></svg>';

    const form = useForm({
        theme: props.widget.settings.theme || 'botzo',
        is_enabled: props.widget.is_enabled,
        allowed_domains: props.widget.allowed_domains.length ? [...props.widget.allowed_domains] : [''],
        accent: props.widget.settings.accent || '#034737',
        position: props.widget.settings.position || 'right',
        assistant_name: props.widget.settings.assistant_name || '',
        welcome_text: props.widget.settings.welcome_text || '',
        suggestions: initialSuggestions,
        daily_reply_limit: props.widget.daily_reply_limit,
    });

    const copied = ref(false);
    const copyMessage = ref('');

    const isBotzo = computed(() => form.theme === 'botzo');
    const previewAccent = computed(() => (/^#[0-9a-fA-F]{6}$/.test(form.accent) ? form.accent : '#034737'));
    const chipColor = computed(() => (isBotzo.value ? '#22D366' : previewAccent.value));
    const previewName = computed(() => form.assistant_name.trim() || props.organizationName);
    const previewInitial = computed(() => (previewName.value || '?').trim().substring(0, 1).toUpperCase());
    const previewSuggestions = computed(() => form.suggestions.map((item) => (item || '').trim()).filter(Boolean));

    const domainError = (index) => form.errors[`allowed_domains.${index}`];
    const addDomain = () => form.allowed_domains.push('');
    const removeDomain = (index) => {
        form.allowed_domains.splice(index, 1);
        if (!form.allowed_domains.length) {
            form.allowed_domains.push('');
        }
    };

    const save = () => {
        form.post('/automation/widget', { preserveScroll: true });
    };

    const regenerate = () => {
        if (!window.confirm(trans('The current code will stop working. Continue?'))) {
            return;
        }

        router.post('/automation/widget/regenerate-key', {}, { preserveScroll: true });
    };

    const copyCode = async () => {
        try {
            await navigator.clipboard.writeText(props.embedCode);
            copied.value = true;
            copyMessage.value = trans('The code is copied. Paste it into your website.');
            setTimeout(() => { copied.value = false; }, 2500);
        } catch (error) {
            copyMessage.value = trans('Copy failed. Select the code and copy it manually.');
        }
    };
</script>

<style scoped>
.wg-card {
    border-radius: 22px;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    box-shadow: var(--ui-shadow-1);
    padding: 1.25rem 1.5rem;
}

.wg-card-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--ui-text);
}

.wg-card-desc {
    margin-top: 0.25rem;
    font-size: 0.85rem;
    line-height: 1.5;
    color: var(--ui-muted);
}

.wg-warning {
    border-radius: 0.85rem;
    border: 1px solid color-mix(in srgb, var(--ui-warning) 35%, var(--ui-border));
    background: color-mix(in srgb, var(--ui-warning) 12%, var(--ui-surface));
    padding: 0.65rem 0.9rem;
    font-size: 0.85rem;
    color: var(--ui-text);
}

.wg-icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 2.5rem;
    width: 2.5rem;
    flex-shrink: 0;
    border-radius: 999px;
    color: var(--ui-muted);
}

.wg-icon-btn:hover {
    background: var(--ui-surface-soft);
    color: var(--ui-text);
}

.wg-choice {
    display: inline-flex;
    align-items: center;
    min-height: 2.5rem;
    padding: 0 1rem;
    border-radius: 0.75rem;
    border: 1px solid var(--ui-border);
    font-size: 0.85rem;
    cursor: pointer;
}

.wg-choice--active {
    border-color: var(--ui-secondary);
    color: var(--ui-secondary);
    font-weight: 600;
}

.wg-choice:focus-within {
    outline: 2px solid var(--ui-secondary);
    outline-offset: 2px;
}

.wg-btn-primary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 2.5rem;
    border-radius: 0.75rem;
    background: var(--ui-primary);
    color: #fff;
    padding: 0.5rem 1.1rem;
    font-size: 0.85rem;
    font-weight: 600;
}

.wg-btn-primary:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

.wg-btn-ghost {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 2.5rem;
    border-radius: 0.75rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    color: var(--ui-text);
    padding: 0.5rem 1.1rem;
    font-size: 0.85rem;
    font-weight: 600;
}

.wg-btn-ghost:hover {
    background: var(--ui-surface-soft);
}

.wg-preview {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    border-radius: 1rem;
    background: var(--ui-surface-soft);
    padding: 1rem;
}

.wg-preview-panel {
    width: 100%;
    max-width: 300px;
    overflow: hidden;
    border-radius: 18px;
    background: #fff;
    color: #1f2933;
    box-shadow: 0 8px 24px rgba(16, 24, 40, 0.14);
}

.wg-preview-header {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.75rem 0.9rem;
    color: #fff;
}

.wg-preview-avatar {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.1rem;
    height: 2.1rem;
    flex-shrink: 0;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.2);
    font-weight: 700;
}

.wg-preview-body {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    padding: 0.8rem;
    background: #f7f8f7;
}

.wg-preview-bubble {
    align-self: flex-start;
    max-width: 90%;
    border-radius: 14px;
    border: 1px solid #e3e7e5;
    background: #fff;
    padding: 0.5rem 0.75rem;
    font-size: 0.8rem;
    line-height: 1.6;
    white-space: pre-line;
}

.wg-preview-chip {
    border-radius: 999px;
    border: 1px solid;
    background: #fff;
    padding: 0.2rem 0.6rem;
    font-size: 0.72rem;
    font-weight: 500;
}

.wg-preview-input {
    margin: 0.6rem;
    border-radius: 999px;
    border: 1px solid #cbd2cf;
    padding: 0.45rem 0.8rem;
    font-size: 0.75rem;
    color: #5b6670;
}

.wg-preview-footer {
    padding: 0 0.6rem 0.6rem;
    text-align: center;
    font-size: 0.68rem;
    color: #5b6670;
}

.wg-preview-avatar--icon,
.wg-preview-launcher--icon {
    padding: 0;
    background: transparent;
}

.wg-preview-avatar--icon :deep(svg),
.wg-preview-launcher--icon :deep(svg) {
    display: block;
    width: 100%;
    height: 100%;
}

.wg-preview--botzo .wg-preview-panel {
    background: #0f1520;
    color: #f2f5f9;
    border: 1px solid #1e2838;
}

.wg-preview--botzo .wg-preview-header {
    color: #0a0e16;
}

.wg-preview--botzo .wg-preview-body {
    background: #0f1520;
}

.wg-preview--botzo .wg-preview-bubble {
    background: #1a2333;
    border-color: #1a2333;
    color: #f2f5f9;
}

.wg-preview--botzo .wg-preview-chip {
    background: transparent;
}

.wg-preview--botzo .wg-preview-input {
    background: #161e2c;
    border-color: #263044;
    color: #7c8799;
}

.wg-preview--botzo .wg-preview-footer {
    color: #7c8799;
}

.wg-preview--botzo .wg-preview-footer strong {
    color: #22d366;
}

.wg-preview-launcher {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 3rem;
    height: 3rem;
    border-radius: 999px;
    color: #fff;
}
</style>
