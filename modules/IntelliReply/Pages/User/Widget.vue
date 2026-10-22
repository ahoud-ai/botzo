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
                    <WidgetSettingsFields
                        :form="form"
                        :limits="limits"
                        :voices="voices"
                        :organization-name="organizationName"
                        :can-remove-branding="entitlements.remove_branding"
                        :voice-minutes="entitlements.voice_minutes_monthly"
                        id-prefix="wg"
                    />

                    <div class="flex justify-end">
                        <button type="submit" class="wg-btn-primary" :disabled="form.processing">{{ form.processing ? $t('Saving...') : $t('Save') }}</button>
                    </div>
                </form>

                <aside class="min-w-0 flex-[1_1_320px] space-y-4 lg:sticky lg:top-4">
                    <section class="wg-card">
                        <h2 class="wg-card-title">{{ $t('Preview') }}</h2>
                        <WidgetPreview class="mt-4" :form="form" :organization-name="organizationName" :show-branding="!(entitlements.remove_branding && form.hide_branding)" />
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
    import { ref } from 'vue';
    import { router, useForm } from "@inertiajs/vue3";
    import { trans } from 'laravel-vue-i18n';
    import WidgetSettingsFields, { widgetFormFields } from '@/Components/WebWidget/WidgetSettingsFields.vue';
    import WidgetPreview from '@/Components/WebWidget/WidgetPreview.vue';

    const props = defineProps({
        aimodule: Boolean,
        organizationName: String,
        aiActive: Boolean,
        widget: Object,
        embedCode: String,
        entitlements: { type: Object, default: () => ({ remove_branding: false, voice_minutes_monthly: 0 }) },
        voices: { type: Array, default: () => ['alloy'] },
        limits: Object,
    });

    const form = useForm(widgetFormFields(props.widget));
    const copied = ref(false);
    const copyMessage = ref('');

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
</style>
