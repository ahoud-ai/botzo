<template>
    <div class="space-y-4">
        <section class="wsf-card">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <h2 class="wsf-title">{{ $t('Show the widget on my website') }}</h2>
                    <p class="wsf-desc">{{ $t('Visitors see the chat button only on the domains you add below.') }}</p>
                </div>
                <FormToggleSwitch v-model="form.is_enabled" />
            </div>
        </section>

        <section class="wsf-card">
            <h2 class="wsf-title">{{ $t('Allowed domains') }}</h2>
            <p class="wsf-desc">{{ $t('The widget works only on these websites. Example: mystore.com') }}</p>
            <div class="mt-4 space-y-2">
                <div v-for="(domain, index) in form.allowed_domains" :key="`domain-${index}`" class="flex items-start gap-2">
                    <div class="min-w-0 flex-1">
                        <label :for="`${idPrefix}-domain-${index}`" class="sr-only">{{ $t('Domain') }} {{ index + 1 }}</label>
                        <input :id="`${idPrefix}-domain-${index}`" v-model="form.allowed_domains[index]" type="text" dir="ltr" class="ui-input sm:text-sm sm:leading-6" :class="form.errors[`allowed_domains.${index}`] ? 'ui-input-error' : ''" placeholder="mystore.com" maxlength="255" autocomplete="off" />
                        <p v-if="form.errors[`allowed_domains.${index}`]" class="wsf-error">{{ form.errors[`allowed_domains.${index}`] }}</p>
                    </div>
                    <button type="button" class="wsf-icon-btn" @click="removeRow(form.allowed_domains, index, '')" :aria-label="$t('Remove domain')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </div>
                <p v-if="form.errors.allowed_domains" class="wsf-error">{{ form.errors.allowed_domains }}</p>
                <button v-if="form.allowed_domains.length < limits.domains" type="button" class="wsf-btn-ghost" @click="form.allowed_domains.push('')">{{ $t('Add domain') }}</button>
            </div>
        </section>

        <section class="wsf-card">
            <h2 class="wsf-title">{{ $t('Look') }}</h2>
            <fieldset class="mt-4">
                <legend class="ui-form-label">{{ $t('Widget style') }}</legend>
                <div class="flex flex-wrap gap-2">
                    <label v-for="theme in themes" :key="theme" class="wsf-choice" :class="form.theme === theme ? 'wsf-choice--active' : ''">
                        <input v-model="form.theme" type="radio" :value="theme" class="sr-only" />
                        {{ themeLabel(theme) }}
                    </label>
                </div>
            </fieldset>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label :for="`${idPrefix}-accent`" class="ui-form-label">{{ form.theme === 'mochi' ? $t('Headset and buttons color') : $t('Main color') }}</label>
                    <div class="flex items-center gap-2">
                        <input :id="`${idPrefix}-accent`" v-model="form.accent" type="color" class="wsf-swatch" />
                        <input v-model="form.accent" type="text" dir="ltr" maxlength="7" class="ui-input sm:text-sm sm:leading-6" :aria-label="$t('Main color')" />
                    </div>
                    <p v-if="form.errors.accent" class="wsf-error">{{ form.errors.accent }}</p>
                </div>
                <div v-if="form.theme === 'mochi'">
                    <label :for="`${idPrefix}-accent2`" class="ui-form-label">{{ $t('Second headset color') }}</label>
                    <div class="flex items-center gap-2">
                        <input :id="`${idPrefix}-accent2`" v-model="form.accent_secondary" type="color" class="wsf-swatch" />
                        <input v-model="form.accent_secondary" type="text" dir="ltr" maxlength="7" class="ui-input sm:text-sm sm:leading-6" :aria-label="$t('Second headset color')" />
                    </div>
                    <p v-if="form.errors.accent_secondary" class="wsf-error">{{ form.errors.accent_secondary }}</p>
                </div>
                <p v-else-if="form.theme === 'botzo'" class="wsf-desc sm:col-span-1">{{ $t('The color is used by the light style only.') }}</p>
                <fieldset>
                    <legend class="ui-form-label">{{ $t('Button position') }}</legend>
                    <div class="flex gap-2">
                        <label class="wsf-choice" :class="form.position === 'right' ? 'wsf-choice--active' : ''">
                            <input v-model="form.position" type="radio" value="right" class="sr-only" />
                            {{ $t('Right') }}
                        </label>
                        <label class="wsf-choice" :class="form.position === 'left' ? 'wsf-choice--active' : ''">
                            <input v-model="form.position" type="radio" value="left" class="sr-only" />
                            {{ $t('Left') }}
                        </label>
                    </div>
                </fieldset>
            </div>
        </section>

        <section class="wsf-card">
            <h2 class="wsf-title">{{ $t('Messages') }}</h2>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label :for="`${idPrefix}-name`" class="ui-form-label">{{ $t('Assistant name') }}</label>
                    <input :id="`${idPrefix}-name`" v-model="form.assistant_name" type="text" maxlength="60" class="ui-input sm:text-sm sm:leading-6" :placeholder="$t('Mochi')" />
                </div>
                <div>
                    <label :for="`${idPrefix}-business`" class="ui-form-label">{{ $t('Business name in the widget') }}</label>
                    <input :id="`${idPrefix}-business`" v-model="form.business_label" type="text" maxlength="60" class="ui-input sm:text-sm sm:leading-6" :placeholder="organizationName" />
                    <p class="wsf-hint">{{ $t('Shown as "Mochi — {name} assistant". Leave empty to use your business name.', { name: form.business_label || organizationName }) }}</p>
                </div>
                <div class="sm:col-span-2">
                    <label :for="`${idPrefix}-welcome`" class="ui-form-label">{{ $t('Welcome message') }}</label>
                    <textarea :id="`${idPrefix}-welcome`" v-model="form.welcome_text" rows="3" :maxlength="limits.welcome_length" class="ui-input sm:text-sm sm:leading-6" :placeholder="$t('Hi! How can I help you today?')"></textarea>
                    <p v-if="form.errors.welcome_text" class="wsf-error">{{ form.errors.welcome_text }}</p>
                </div>
                <fieldset class="sm:col-span-2">
                    <legend class="ui-form-label">{{ $t('Quick questions') }}</legend>
                    <p class="wsf-hint mb-2">{{ $t('Up to {count} buttons visitors can tap instead of typing.', { count: limits.suggestions }) }}</p>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <div v-for="index in limits.suggestions" :key="`suggestion-${index}`">
                            <label :for="`${idPrefix}-suggestion-${index}`" class="sr-only">{{ $t('Quick question') }} {{ index }}</label>
                            <input :id="`${idPrefix}-suggestion-${index}`" v-model="form.suggestions[index - 1]" type="text" :maxlength="limits.suggestion_length" class="ui-input sm:text-sm sm:leading-6" />
                        </div>
                    </div>
                </fieldset>
            </div>
        </section>

        <section class="wsf-card">
            <h2 class="wsf-title">{{ $t('Pages the assistant can open') }}</h2>
            <p class="wsf-desc">{{ $t('The assistant can take visitors to these pages of your website only. Use a path like /pricing, a section like #features, or a full link on your domain.') }}</p>
            <div class="mt-4 space-y-2">
                <div v-for="(page, index) in form.pages" :key="`page-${index}`" class="grid grid-cols-[1fr_1.4fr_auto] items-start gap-2">
                    <div>
                        <label :for="`${idPrefix}-page-label-${index}`" class="sr-only">{{ $t('Page name') }}</label>
                        <input :id="`${idPrefix}-page-label-${index}`" v-model="page.label" type="text" :maxlength="limits.page_label_length" class="ui-input sm:text-sm sm:leading-6" :class="form.errors[`pages.${index}.label`] ? 'ui-input-error' : ''" :placeholder="$t('Pricing')" />
                        <p v-if="form.errors[`pages.${index}.label`]" class="wsf-error">{{ form.errors[`pages.${index}.label`] }}</p>
                    </div>
                    <div>
                        <label :for="`${idPrefix}-page-url-${index}`" class="sr-only">{{ $t('Page link') }}</label>
                        <input :id="`${idPrefix}-page-url-${index}`" v-model="page.url" type="text" dir="ltr" maxlength="500" class="ui-input sm:text-sm sm:leading-6" :class="form.errors[`pages.${index}.url`] ? 'ui-input-error' : ''" placeholder="/pricing" />
                        <p v-if="form.errors[`pages.${index}.url`]" class="wsf-error">{{ form.errors[`pages.${index}.url`] }}</p>
                    </div>
                    <button type="button" class="wsf-icon-btn" @click="removeRow(form.pages, index)" :aria-label="$t('Remove page')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </div>
                <button v-if="form.pages.length < limits.pages" type="button" class="wsf-btn-ghost" @click="form.pages.push({ label: '', url: '' })">{{ $t('Add page') }}</button>
            </div>
        </section>

        <section class="wsf-card">
            <h2 class="wsf-title">{{ $t('Voice calls') }}</h2>
            <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
                <p class="wsf-desc min-w-0 flex-1">
                    <template v-if="voiceMinutes === 0">{{ $t('Your plan does not include voice calls. Visitors can still chat by typing.') }}</template>
                    <template v-else>{{ $t('Visitors can press the mic and talk with the assistant. When the monthly minutes run out it continues by text.') }}</template>
                </p>
                <FormToggleSwitch v-model="form.voice_enabled" :disabled="voiceMinutes === 0" />
            </div>
            <div v-if="form.voice_enabled && voiceMinutes !== 0" class="mt-4 max-w-xs">
                <label :for="`${idPrefix}-voice`" class="ui-form-label">{{ $t('Voice') }}</label>
                <select :id="`${idPrefix}-voice`" v-model="form.voice" class="ui-input sm:text-sm sm:leading-6" dir="ltr">
                    <option v-for="voice in voices" :key="voice" :value="voice">{{ voice }}</option>
                </select>
            </div>
        </section>

        <section class="wsf-card">
            <h2 class="wsf-title">{{ $t('Protection') }}</h2>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label :for="`${idPrefix}-limit`" class="ui-form-label">{{ $t('AI replies per day') }}</label>
                    <input :id="`${idPrefix}-limit`" v-model.number="form.daily_reply_limit" type="number" min="0" max="5000" class="ui-input sm:text-sm sm:leading-6" />
                    <p class="wsf-hint">{{ $t('After this, visitor messages wait for your team until tomorrow.') }}</p>
                    <p v-if="form.errors.daily_reply_limit" class="wsf-error">{{ form.errors.daily_reply_limit }}</p>
                </div>
                <div v-if="canRemoveBranding" class="flex items-start justify-between gap-3">
                    <div>
                        <div class="ui-form-label">{{ $t('Hide "Powered by botzo"') }}</div>
                        <p class="wsf-hint">{{ $t('Your plan lets you remove the Botzo credit from the widget.') }}</p>
                    </div>
                    <FormToggleSwitch v-model="form.hide_branding" />
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
    import { useI18n } from 'vue-i18n';
    import FormToggleSwitch from '@/Components/FormToggleSwitch.vue';

    const { t } = useI18n();

    defineProps({
        form: { type: Object, required: true },
        limits: { type: Object, required: true },
        themes: { type: Array, default: () => ['mochi', 'botzo', 'classic'] },
        voices: { type: Array, default: () => ['alloy'] },
        organizationName: { type: String, default: '' },
        canRemoveBranding: { type: Boolean, default: false },
        // -1 = unlimited, 0 = no voice calls on the plan.
        voiceMinutes: { type: Number, default: -1 },
        idPrefix: { type: String, default: 'wsf' },
    });

    const themeLabel = (theme) => ({
        mochi: t('Mochi (animated mascot)'),
        botzo: t('Dark, Botzo style'),
        classic: t('Light, in your color'),
    }[theme] ?? theme);

    const removeRow = (rows, index, emptyValue = null) => {
        rows.splice(index, 1);
        if (emptyValue !== null && rows.length === 0) {
            rows.push(emptyValue);
        }
    };
</script>

<script>
    /**
     * The form fields WidgetSettingsFields edits, from a widget's saved settings.
     */
    export const widgetFormFields = (widget) => {
        const settings = widget?.settings ?? {};
        const suggestions = Array.from({ length: 4 }, (_, index) => settings.suggestions?.[index] ?? '');

        return {
            is_enabled: Boolean(widget?.is_enabled),
            allowed_domains: widget?.allowed_domains?.length ? [...widget.allowed_domains] : [''],
            theme: settings.theme || 'mochi',
            accent: settings.accent || '#22D366',
            accent_secondary: settings.accent_secondary || '#2F7BFF',
            position: settings.position || 'left',
            assistant_name: settings.assistant_name || '',
            business_label: settings.business_label || '',
            welcome_text: settings.welcome_text || '',
            suggestions,
            pages: (settings.pages ?? []).map((page) => ({ label: page.label ?? '', url: page.url ?? '' })),
            hide_branding: Boolean(settings.hide_branding),
            voice_enabled: Boolean(settings.voice_enabled),
            voice: settings.voice || 'alloy',
            daily_reply_limit: widget?.daily_reply_limit ?? 300,
        };
    };
</script>

<style scoped>
.wsf-card {
    border-radius: 22px;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    box-shadow: var(--ui-shadow-1);
    padding: 1.25rem 1.5rem;
}

.wsf-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--ui-text);
}

.wsf-desc {
    margin-top: 0.25rem;
    font-size: 0.85rem;
    line-height: 1.5;
    color: var(--ui-muted);
}

.wsf-hint {
    margin-top: 0.25rem;
    font-size: 0.75rem;
    color: var(--ui-muted);
}

.wsf-error {
    margin-top: 0.25rem;
    font-size: 0.75rem;
    color: #dc2626;
}

.wsf-swatch {
    height: 2.5rem;
    width: 3rem;
    flex-shrink: 0;
    cursor: pointer;
    border-radius: 0.5rem;
    border: 1px solid var(--ui-border);
    background: transparent;
    padding: 0.25rem;
}

.wsf-icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 2.5rem;
    width: 2.5rem;
    flex-shrink: 0;
    border-radius: 999px;
    color: var(--ui-muted);
}

.wsf-icon-btn:hover {
    background: var(--ui-surface-soft);
    color: var(--ui-text);
}

.wsf-choice {
    display: inline-flex;
    align-items: center;
    min-height: 2.5rem;
    padding: 0 1rem;
    border-radius: 0.75rem;
    border: 1px solid var(--ui-border);
    font-size: 0.85rem;
    cursor: pointer;
}

.wsf-choice--active {
    border-color: var(--ui-secondary);
    color: var(--ui-secondary);
    font-weight: 600;
}

.wsf-choice:focus-within {
    outline: 2px solid var(--ui-secondary);
    outline-offset: 2px;
}

.wsf-btn-ghost {
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

.wsf-btn-ghost:hover {
    background: var(--ui-surface-soft);
}
</style>
