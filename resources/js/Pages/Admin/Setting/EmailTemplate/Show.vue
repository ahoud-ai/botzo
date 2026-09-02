<template>
    <AppLayout>
        <UiPageHeader :title="$t('Template') + ': ' + props.template.name" :subtitle="$t('Configure email templates')">
            <template #actions>
                <Link href="/admin/settings/email-templates" class="emts-btn emts-btn--ghost">{{ $t('Back') }}</Link>
                <button type="button" @click="submitForm()" class="emts-btn emts-btn--solid" :disabled="form.processing">{{ $t('Save') }}</button>
            </template>
        </UiPageHeader>

        <form @submit.prevent="submitForm()" class="emts-layout mt-6">
            <div class="emts-editor-col">
                <UiSectionCard :title="$t('Subject')" :subtitle="$t('Shown as the email subject line in the recipient inbox')">
                    <FormInput v-model="form.subject" :hideLabel="true" :error="form.errors.subject" :type="'text'" :placeholder="$t('Subject')"/>
                </UiSectionCard>

                <UiSectionCard :title="$t('Content')" class="mt-6">
                    <div class="emts-tabs" role="tablist">
                        <button
                            v-for="tab in tabs"
                            :key="tab.id"
                            type="button"
                            role="tab"
                            :aria-selected="activeTab === tab.id"
                            class="emts-tab"
                            :class="{ 'emts-tab--active': activeTab === tab.id }"
                            @click="activeTab = tab.id"
                        >
                            {{ $t(tab.label) }}
                        </button>
                    </div>

                    <div v-if="activeTab === 'visual'" class="emts-editor-shell">
                        <QuillEditor
                            v-model:content="form.body"
                            toolbar="essential"
                            contentType="html"
                            theme="snow"
                        />
                    </div>

                    <div v-if="activeTab === 'html'" class="emts-editor-shell">
                        <textarea
                            v-model="form.body"
                            class="emts-html-textarea"
                            rows="18"
                            spellcheck="false"
                            :aria-label="$t('HTML Source')"
                        />
                    </div>

                    <p v-if="hasUnbalancedPlaceholderBraces" class="emts-notice emts-notice--warning">
                        {{ $t('Unbalanced placeholder braces detected') }}
                    </p>
                    <p v-if="form.errors.body" class="emts-notice emts-notice--danger">{{ form.errors.body }}</p>
                </UiSectionCard>

                <UiSectionCard :title="$t('Template placeholders')" :subtitle="$t('Click a placeholder to copy it, then paste it into the subject or body')" class="mt-6">
                    <div class="emts-placeholder-list">
                        <button
                            v-for="placeholder in placeholders"
                            :key="placeholder"
                            type="button"
                            class="emts-placeholder-chip"
                            :title="$t('Copy to clipboard')"
                            @click="copyPlaceholder(placeholder)"
                        >
                            <code>{{ placeholder }}</code>
                            <svg v-if="copiedPlaceholder === placeholder" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4" d="m5 13l4 4L19 7"/></svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M8 8h11v11H8zm-3-3h11v3H8v8H5z"/></svg>
                        </button>
                    </div>
                </UiSectionCard>
            </div>

            <div class="emts-preview-col">
                <UiSectionCard :title="$t('Live preview')" :subtitle="$t('Updates as you type')" class="emts-preview-card">
                    <p class="emts-notice emts-notice--warning">{{ $t('HTML preview may differ after sanitization') }}</p>
                    <div class="emts-preview-subject">
                        <span class="emts-preview-label">{{ $t('Subject') }}</span>
                        <span class="emts-preview-subject-value">{{ form.subject || $t('No preview content') }}</span>
                    </div>
                    <div class="emts-preview-frame prose prose-sm max-w-none" v-html="previewContent" />
                </UiSectionCard>
            </div>
        </form>
    </AppLayout>
</template>
<script setup>
    import { computed, ref } from "vue";
    import AppLayout from "../Layout/App.vue";
    import { Link, useForm } from "@inertiajs/vue3";
    import { useI18n } from 'vue-i18n';
    import FormInput from '@/Components/FormInput.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import UiSectionCard from '@/Components/UI/UiSectionCard.vue';
    import { QuillEditor } from '@vueup/vue-quill';
    import '@vueup/vue-quill/dist/vue-quill.snow.css';
    const { t } = useI18n();

    const props = defineProps({
        template: Object,
        placeholders: {
            type: Array,
            default: () => [],
        },
    });
    const activeTab = ref('visual');
    const tabs = [
        { id: 'visual', label: t('Visual Editor') },
        { id: 'html', label: t('HTML Source') },
    ];
    const form = useForm({
        _method: 'put',
        subject: props.template.subject,
        body: props.template.body,
    });

    const placeholders = computed(() => Array.isArray(props.placeholders) ? props.placeholders : []);
    const previewContent = computed(() => String(form.body || ''));
    const hasUnbalancedPlaceholderBraces = computed(() => {
        const body = String(form.body || '');
        const opens = (body.match(/{{/g) || []).length;
        const closes = (body.match(/}}/g) || []).length;

        return opens !== closes;
    });

    const copiedPlaceholder = ref(null);
    const copyPlaceholder = async (placeholder) => {
        try {
            await navigator.clipboard.writeText(placeholder);
            copiedPlaceholder.value = placeholder;
            setTimeout(() => {
                if (copiedPlaceholder.value === placeholder) {
                    copiedPlaceholder.value = null;
                }
            }, 1500);
        } catch (e) {
            // Clipboard access can be blocked by the browser; the chip text is still selectable manually.
        }
    };

    const submitForm = async () => {
        const url = window.location.pathname;

        form.put(url, {
            preserveScroll: true,
        });
    };
</script>

<style scoped>
.emts-layout {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
    align-items: start;
}

@media (min-width: 1100px) {
    .emts-layout {
        grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr);
    }

    .emts-preview-card {
        position: sticky;
        top: 1.5rem;
    }
}

.emts-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.85rem;
    padding: 0.6rem 1.2rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: filter 160ms ease, background-color 160ms ease, border-color 160ms ease, opacity 160ms ease;
}

.emts-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.emts-btn--solid:hover:not(:disabled) {
    filter: brightness(1.05);
}

.emts-btn--solid:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

.emts-btn--ghost {
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    color: var(--ui-text);
}

.emts-btn--ghost:hover {
    background: var(--ui-surface-soft);
    border-color: var(--ui-border-strong);
}

.emts-tabs {
    display: inline-flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-bottom: 1rem;
    border-bottom: 1px solid var(--ui-border);
    padding-bottom: 0.85rem;
}

.emts-tab {
    border-radius: 0.65rem;
    padding: 0.45rem 0.9rem;
    font-size: 0.83rem;
    font-weight: 600;
    color: var(--ui-muted);
    background: transparent;
    transition: background-color 160ms ease, color 160ms ease;
}

.emts-tab:hover {
    background: var(--ui-surface-soft);
    color: var(--ui-text);
}

.emts-tab--active {
    background: var(--ui-secondary);
    color: #fff;
}

.emts-editor-shell {
    border-radius: 0.9rem;
    overflow: hidden;
}

.emts-html-textarea {
    width: 100%;
    border-radius: 0.9rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    padding: 0.85rem;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.83rem;
    color: var(--ui-text);
}

.emts-html-textarea:focus {
    outline: none;
    border-color: var(--ui-secondary);
}

.emts-notice {
    margin-top: 0.85rem;
    font-size: 0.8rem;
}

.emts-notice--warning {
    color: var(--ui-warning);
}

.emts-notice--danger {
    color: var(--ui-danger);
}

.emts-placeholder-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.emts-placeholder-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border-radius: 0.6rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    padding: 0.35rem 0.65rem;
    font-size: 0.78rem;
    color: var(--ui-secondary);
    transition: border-color 160ms ease, background-color 160ms ease;
}

.emts-placeholder-chip:hover {
    border-color: var(--ui-secondary);
    background: color-mix(in srgb, var(--ui-secondary) 10%, transparent);
}

.emts-preview-subject {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    margin-bottom: 0.85rem;
    border-radius: 0.7rem;
    border: 1px dashed var(--ui-border-strong);
    background: var(--ui-surface-soft);
    padding: 0.6rem 0.75rem;
}

.emts-preview-label {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--ui-muted);
}

.emts-preview-subject-value {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--ui-text);
}

.emts-preview-frame {
    max-height: 32rem;
    overflow: auto;
    border-radius: 0.9rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    padding: 1rem;
    font-size: 0.85rem;
    color: var(--ui-text);
}
</style>
