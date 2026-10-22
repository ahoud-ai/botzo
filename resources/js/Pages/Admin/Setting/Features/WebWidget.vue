<template>
    <AppLayout>
        <UiPageHeader :title="$t('Website widget')" :subtitle="$t('Defaults for every customer, voice call limits, and the widget on the Botzo website.')" />

        <div class="mt-6 space-y-8">
            <!-- Defaults every organization starts from -->
            <form @submit.prevent="saveDefaults" class="space-y-4">
                <UiSectionCard :title="$t('Defaults for new widgets')" :subtitle="$t('Customers start from these values and can change them from their dashboard.')">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <fieldset class="sm:col-span-2">
                            <legend class="ui-form-label">{{ $t('Widget style') }}</legend>
                            <div class="flex flex-wrap gap-2">
                                <label v-for="theme in themes" :key="theme" class="ww-choice" :class="defaultsForm.theme === theme ? 'ww-choice--active' : ''">
                                    <input v-model="defaultsForm.theme" type="radio" :value="theme" class="sr-only" />
                                    {{ themeLabel(theme) }}
                                </label>
                            </div>
                        </fieldset>
                        <div>
                            <label for="ww-accent" class="ui-form-label">{{ $t('Headset and buttons color') }}</label>
                            <div class="flex items-center gap-2">
                                <input id="ww-accent" v-model="defaultsForm.accent" type="color" class="ww-swatch" />
                                <input v-model="defaultsForm.accent" type="text" dir="ltr" maxlength="7" class="ui-input sm:text-sm sm:leading-6" :aria-label="$t('Headset and buttons color')" />
                            </div>
                            <p v-if="defaultsForm.errors.accent" class="ww-error">{{ defaultsForm.errors.accent }}</p>
                        </div>
                        <div>
                            <label for="ww-accent2" class="ui-form-label">{{ $t('Second headset color') }}</label>
                            <div class="flex items-center gap-2">
                                <input id="ww-accent2" v-model="defaultsForm.accent_secondary" type="color" class="ww-swatch" />
                                <input v-model="defaultsForm.accent_secondary" type="text" dir="ltr" maxlength="7" class="ui-input sm:text-sm sm:leading-6" :aria-label="$t('Second headset color')" />
                            </div>
                            <p v-if="defaultsForm.errors.accent_secondary" class="ww-error">{{ defaultsForm.errors.accent_secondary }}</p>
                        </div>
                        <fieldset>
                            <legend class="ui-form-label">{{ $t('Button position') }}</legend>
                            <div class="flex gap-2">
                                <label class="ww-choice" :class="defaultsForm.position === 'right' ? 'ww-choice--active' : ''">
                                    <input v-model="defaultsForm.position" type="radio" value="right" class="sr-only" />
                                    {{ $t('Right') }}
                                </label>
                                <label class="ww-choice" :class="defaultsForm.position === 'left' ? 'ww-choice--active' : ''">
                                    <input v-model="defaultsForm.position" type="radio" value="left" class="sr-only" />
                                    {{ $t('Left') }}
                                </label>
                            </div>
                        </fieldset>
                        <div>
                            <label for="ww-name" class="ui-form-label">{{ $t('Assistant name') }}</label>
                            <input id="ww-name" v-model="defaultsForm.assistant_name" type="text" maxlength="60" class="ui-input sm:text-sm sm:leading-6" />
                        </div>
                        <div class="sm:col-span-2">
                            <label for="ww-welcome" class="ui-form-label">{{ $t('Welcome message') }}</label>
                            <textarea id="ww-welcome" v-model="defaultsForm.welcome_text" rows="2" :maxlength="limits.welcome_length" class="ui-input sm:text-sm sm:leading-6"></textarea>
                        </div>
                        <fieldset class="sm:col-span-2">
                            <legend class="ui-form-label">{{ $t('Quick questions') }}</legend>
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <div v-for="index in limits.suggestions" :key="`d-suggestion-${index}`">
                                    <label :for="`ww-suggestion-${index}`" class="sr-only">{{ $t('Quick question') }} {{ index }}</label>
                                    <input :id="`ww-suggestion-${index}`" v-model="defaultsForm.suggestions[index - 1]" type="text" :maxlength="limits.suggestion_length" class="ui-input sm:text-sm sm:leading-6" />
                                </div>
                            </div>
                        </fieldset>
                    </div>
                </UiSectionCard>

                <UiSectionCard :title="$t('Voice call limits')" :subtitle="$t('Shared by every customer. Monthly minutes per customer are set on each subscription plan.')">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label for="ww-voice" class="ui-form-label">{{ $t('Default voice') }}</label>
                            <select id="ww-voice" v-model="defaultsForm.voice" class="ui-input sm:text-sm sm:leading-6" dir="ltr">
                                <option v-for="voice in voices" :key="voice" :value="voice">{{ voice }}</option>
                            </select>
                        </div>
                        <div>
                            <label for="ww-call-max" class="ui-form-label">{{ $t('Maximum minutes per call') }}</label>
                            <input id="ww-call-max" v-model.number="defaultsForm.call_max_minutes" type="number" min="1" max="30" class="ui-input sm:text-sm sm:leading-6" />
                            <p v-if="defaultsForm.errors.call_max_minutes" class="ww-error">{{ defaultsForm.errors.call_max_minutes }}</p>
                        </div>
                        <div>
                            <label for="ww-daily-cap" class="ui-form-label">{{ $t('Daily voice minutes for the whole platform') }}</label>
                            <input id="ww-daily-cap" v-model.number="defaultsForm.daily_minutes_cap" type="number" min="0" class="ui-input sm:text-sm sm:leading-6" />
                            <p class="ww-hint">{{ $t('When reached, every widget continues by text until tomorrow.') }}</p>
                        </div>
                    </div>
                    <Link href="/admin/plans" class="ww-btn-ghost mt-4">{{ $t('Open subscription plans') }}</Link>
                </UiSectionCard>

                <div class="flex justify-end">
                    <button type="submit" class="ww-btn-primary" :disabled="defaultsForm.processing">{{ $t('Save defaults') }}</button>
                </div>
            </form>

            <!-- The widget on Botzo's own website -->
            <section>
                <h2 class="text-lg font-semibold text-[var(--ui-text)]">{{ $t('Botzo website widget') }}</h2>
                <p class="mt-1 text-sm text-[var(--ui-muted)]">
                    <template v-if="site">{{ $t('Answers visitors of botzo.net from the organization "{name}". When it is on, the public pages load it automatically.', { name: site.organization.name }) }}</template>
                    <template v-else>{{ $t('Not set up yet. Run on the server: php artisan botzo:setup-site-widget EMAIL') }}</template>
                </p>

                <div v-if="site" class="mt-4 flex flex-wrap items-start gap-6">
                    <form @submit.prevent="saveSite" class="min-w-0 flex-[999_1_480px] space-y-4">
                        <WidgetSettingsFields
                            :form="siteForm"
                            :limits="limits"
                            :themes="themes"
                            :voices="voices"
                            :organization-name="site.organization.name"
                            :can-remove-branding="true"
                            :voice-minutes="-1"
                            id-prefix="site"
                        />
                        <div class="flex justify-end">
                            <button type="submit" class="ww-btn-primary" :disabled="siteForm.processing">{{ $t('Save Botzo widget') }}</button>
                        </div>
                    </form>
                    <aside class="min-w-0 flex-[1_1_320px] lg:sticky lg:top-4">
                        <WidgetPreview :form="siteForm" :organization-name="site.organization.name" :show-branding="!siteForm.hide_branding" />
                    </aside>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<script setup>
    import { Link, useForm } from '@inertiajs/vue3';
    import { useI18n } from 'vue-i18n';
    import AppLayout from '../Layout/App.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import UiSectionCard from '@/Components/UI/UiSectionCard.vue';
    import WidgetSettingsFields, { widgetFormFields } from '@/Components/WebWidget/WidgetSettingsFields.vue';
    import WidgetPreview from '@/Components/WebWidget/WidgetPreview.vue';

    const { t } = useI18n();

    const props = defineProps({
        defaults: { type: Object, required: true },
        voiceLimits: { type: Object, required: true },
        themes: { type: Array, default: () => ['mochi', 'botzo', 'classic'] },
        voices: { type: Array, default: () => ['alloy'] },
        site: { type: Object, default: null },
        limits: { type: Object, required: true },
    });

    const defaultsForm = useForm({
        theme: props.defaults.theme,
        accent: props.defaults.accent,
        accent_secondary: props.defaults.accent_secondary,
        position: props.defaults.position,
        assistant_name: props.defaults.assistant_name ?? '',
        welcome_text: props.defaults.welcome_text ?? '',
        suggestions: Array.from({ length: props.limits.suggestions }, (_, index) => props.defaults.suggestions?.[index] ?? ''),
        voice: props.defaults.voice,
        call_max_minutes: props.voiceLimits.call_max_minutes,
        daily_minutes_cap: props.voiceLimits.daily_minutes_cap,
    });

    const siteForm = useForm(widgetFormFields(props.site));

    const themeLabel = (theme) => ({
        mochi: t('Mochi (animated mascot)'),
        botzo: t('Dark, Botzo style'),
        classic: t('Light, in your color'),
    }[theme] ?? theme);

    const saveDefaults = () => defaultsForm.post('/admin/settings/features/web-widget/defaults', { preserveScroll: true });
    const saveSite = () => siteForm.post('/admin/settings/features/web-widget/site', { preserveScroll: true });
</script>

<style scoped>
.ww-swatch {
    height: 2.5rem;
    width: 3rem;
    flex-shrink: 0;
    cursor: pointer;
    border-radius: 0.5rem;
    border: 1px solid var(--ui-border);
    background: transparent;
    padding: 0.25rem;
}

.ww-choice {
    display: inline-flex;
    align-items: center;
    min-height: 2.5rem;
    padding: 0 1rem;
    border-radius: 0.75rem;
    border: 1px solid var(--ui-border);
    font-size: 0.85rem;
    cursor: pointer;
}

.ww-choice--active {
    border-color: var(--ui-secondary);
    color: var(--ui-secondary);
    font-weight: 600;
}

.ww-choice:focus-within {
    outline: 2px solid var(--ui-secondary);
    outline-offset: 2px;
}

.ww-error {
    margin-top: 0.25rem;
    font-size: 0.75rem;
    color: #dc2626;
}

.ww-hint {
    margin-top: 0.25rem;
    font-size: 0.75rem;
    color: var(--ui-muted);
}

.ww-btn-primary {
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

.ww-btn-primary:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

.ww-btn-ghost {
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
</style>
