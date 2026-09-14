<template>
    <AppLayout>
        <UiPageHeader :title="$t('Content pages') + ' | ' + $t('Edit')" :subtitle="$t('Edit bilingual public pages using the advanced editor')">
            <template #actions>
                <Link href="/admin/settings/pages" class="pgs-btn pgs-btn--ghost">{{ $t('Back') }}</Link>
                <button type="button" @click="openAlert()" class="pgs-btn pgs-btn--danger">{{ $t('Delete') }}</button>
                <button type="button" @click="submitForm()" class="pgs-btn pgs-btn--solid" :disabled="form.processing">{{ $t('Save') }}</button>
            </template>
        </UiPageHeader>

        <form @submit.prevent="submitForm()" class="mt-6 space-y-6">
            <UiSectionCard :title="$t('Page name')">
                <div class="grid gap-5 sm:grid-cols-2">
                    <FormInput v-model="form.name_ar" :name="$t('Name (Arabic)')" :error="form.errors.name_ar" :type="'text'"/>
                    <FormInput v-model="form.name_en" :name="$t('Name (English)')" :error="form.errors.name_en" :type="'text'"/>
                </div>

                <div class="pgs-slug-row">
                    <span class="pgs-slug-chip">{{ `${$t('Slug')} (AR): /pages/${formattedNameAr || '-'}` }}</span>
                    <span class="pgs-slug-chip">{{ `${$t('Slug')} (EN): /pages/${formattedNameEn || '-'}` }}</span>
                    <span class="pgs-slug-chip pgs-slug-chip--accent">{{ `${$t('Canonical slug')}: /pages/${canonicalSlug || '-'}` }}</span>
                </div>
            </UiSectionCard>

            <div class="pgs-layout">
                <div class="pgs-editor-col">
                    <UiSectionCard :title="$t('Content')">
                        <div class="pgs-tabs" role="tablist">
                            <button
                                v-for="locale in localeTabs"
                                :key="locale.id"
                                type="button"
                                class="pgs-tab"
                                :class="{ 'pgs-tab--active': activeLocale === locale.id }"
                                @click="activeLocale = locale.id"
                            >
                                {{ locale.label }}
                            </button>
                        </div>

                        <div class="pgs-tabs pgs-tabs--secondary" role="tablist">
                            <button
                                v-for="tab in editorTabs"
                                :key="tab.id"
                                type="button"
                                class="pgs-tab pgs-tab--sm"
                                :class="{ 'pgs-tab--active': activeTab === tab.id }"
                                @click="activeTab = tab.id"
                            >
                                {{ tab.label }}
                            </button>
                        </div>

                        <div v-if="activeTab === 'visual'" class="pgs-editor-shell">
                            <QuillEditor
                                v-model:content="selectedContent"
                                toolbar="essential"
                                contentType="html"
                                theme="snow"
                            />
                        </div>

                        <div v-if="activeTab === 'html'" class="pgs-editor-shell">
                            <textarea
                                v-model="selectedContent"
                                class="pgs-html-textarea"
                                rows="18"
                                spellcheck="false"
                                :aria-label="$t('HTML Source')"
                            />
                        </div>

                        <p v-if="currentContentError" class="pgs-notice pgs-notice--danger">{{ currentContentError }}</p>
                        <p v-if="form.errors.content" class="pgs-notice pgs-notice--danger">{{ form.errors.content }}</p>
                    </UiSectionCard>
                </div>

                <div class="pgs-preview-col">
                    <UiSectionCard :title="$t('Live preview')" :subtitle="$t('Updates as you type')" class="pgs-preview-card">
                        <p class="pgs-notice pgs-notice--warning">{{ $t('HTML preview may differ after sanitization') }}</p>
                        <div class="pgs-preview-frame prose prose-sm max-w-none" v-html="previewContent" />
                    </UiSectionCard>
                </div>
            </div>

            <div class="pgs-save-bar">
                <span class="pgs-save-hint">{{ form.isDirty ? $t('You have unsaved changes') : $t('All changes saved') }}</span>
                <div class="flex items-center gap-x-3">
                    <Link href="/admin/settings/pages" class="pgs-btn pgs-btn--ghost">{{ $t('Back') }}</Link>
                    <button type="submit" class="pgs-btn pgs-btn--solid" :disabled="form.processing">
                        <span v-if="form.processing">{{ $t('Saving...') }}</span>
                        <span v-else>{{ $t('Save') }}</span>
                    </button>
                </div>
            </div>
        </form>

        <!-- Alert Modal Component-->
        <AlertModal
            v-model="isOpenAlert"
            @confirm="() => confirmAlert(deletePage)"
            :label="$t('Delete row')"
            :description="$t('Are you sure you want to delete this page? This action can not be undone')"
        />
    </AppLayout>
</template>
<script setup>
    import AppLayout from "../Layout/App.vue";
    import { computed, ref } from 'vue';
    import { Link, useForm } from "@inertiajs/vue3";
    import FormInput from '@/Components/FormInput.vue';
    import AlertModal from '@/Components/AlertModal.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import UiSectionCard from '@/Components/UI/UiSectionCard.vue';
    import { useAlertModal } from '@/Composables/useAlertModal';
    import { useUnsavedChangesGuard } from '@/Composables/useUnsavedChangesGuard';
    import { useI18n } from 'vue-i18n';
    import { QuillEditor } from '@vueup/vue-quill';
    import '@vueup/vue-quill/dist/vue-quill.snow.css';

    const { t } = useI18n();
    const props = defineProps({ page: Object });
    const activeLocale = ref('ar');
    const activeTab = ref('visual');
    const { isOpenAlert, openAlert, confirmAlert } = useAlertModal();

    const localeTabs = [
        { id: 'ar', label: t('Arabic') },
        { id: 'en', label: t('English') },
    ];

    const editorTabs = [
        { id: 'visual', label: t('Visual Editor') },
        { id: 'html', label: t('HTML Source') },
    ];

    const form = useForm({
        _method: 'put',
        name: props.page.name ?? null,
        name_ar: props.page.name_ar ?? props.page.name ?? null,
        name_en: props.page.name_en ?? props.page.name ?? null,
        content: props.page.content ?? null,
        content_ar: props.page.content_ar ?? props.page.content ?? null,
        content_en: props.page.content_en ?? props.page.content ?? null,
    });

    const { markSubmitting } = useUnsavedChangesGuard(() => form.isDirty, t('You have unsaved changes. Leave this page?'));

    const firstFilled = (...values) => {
        for (const value of values) {
            if (typeof value === 'string' && value.trim() !== '') {
                return value;
            }
        }

        return null;
    };

    const toSlug = (value) => String(value || '').trim().toLowerCase().replace(/\s+/g, '-');

    const selectedContent = computed({
        get() {
            return activeLocale.value === 'ar' ? form.content_ar : form.content_en;
        },
        set(value) {
            if (activeLocale.value === 'ar') {
                form.content_ar = value;
                return;
            }

            form.content_en = value;
        },
    });

    const previewContent = computed(() => String(selectedContent.value || ''));
    const currentContentError = computed(() => activeLocale.value === 'ar' ? form.errors.content_ar : form.errors.content_en);

    const formattedNameAr = computed(() => toSlug(form.name_ar));
    const formattedNameEn = computed(() => toSlug(form.name_en));
    const canonicalSlug = computed(() => toSlug(firstFilled(form.name_en, form.name_ar, form.name)));

    const submitForm = async () => {
        form.name = firstFilled(form.name_en, form.name_ar, form.name);
        form.content = firstFilled(form.content_en, form.content_ar, form.content);

        const url = window.location.pathname;

        markSubmitting();
        form.put(url, {
            preserveScroll: true,
        });
    };

    const deletePage = async () => {
        const url = window.location.pathname;

        markSubmitting();
        form.delete(url, {
            preserveScroll: true,
        });
    };
</script>

<style scoped>
.pgs-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.85rem;
    padding: 0.6rem 1.2rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: filter 160ms ease, background-color 160ms ease, border-color 160ms ease, opacity 160ms ease;
}

.pgs-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.pgs-btn--solid:hover:not(:disabled) {
    filter: brightness(1.05);
}

.pgs-btn--solid:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

.pgs-btn--ghost {
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    color: var(--ui-text);
}

.pgs-btn--ghost:hover {
    background: var(--ui-surface-soft);
    border-color: var(--ui-border-strong);
}

.pgs-btn--danger {
    border: 1px solid color-mix(in srgb, var(--ui-danger) 45%, transparent);
    background: var(--ui-surface);
    color: var(--ui-danger);
}

.pgs-btn--danger:hover {
    background: color-mix(in srgb, var(--ui-danger) 10%, transparent);
}

.pgs-slug-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 1.1rem;
    border-top: 1px solid var(--ui-border);
    padding-top: 1.1rem;
}

.pgs-slug-chip {
    border-radius: 0.6rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    padding: 0.3rem 0.65rem;
    font-size: 0.75rem;
    color: var(--ui-muted);
}

.pgs-slug-chip--accent {
    border-color: color-mix(in srgb, var(--ui-secondary) 40%, transparent);
    color: var(--ui-secondary);
    font-weight: 600;
}

.pgs-layout {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
    align-items: start;
}

@media (min-width: 1100px) {
    .pgs-layout {
        grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr);
    }

    .pgs-preview-card {
        position: sticky;
        top: 1.5rem;
    }
}

.pgs-tabs {
    display: inline-flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-bottom: 0.85rem;
}

.pgs-tabs--secondary {
    margin-bottom: 1rem;
    border-bottom: 1px solid var(--ui-border);
    padding-bottom: 0.85rem;
}

.pgs-tab {
    border-radius: 0.65rem;
    padding: 0.5rem 1rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--ui-muted);
    background: var(--ui-surface-soft);
    transition: background-color 160ms ease, color 160ms ease;
}

.pgs-tab--sm {
    padding: 0.4rem 0.85rem;
    font-size: 0.8rem;
}

.pgs-tab:hover {
    background: color-mix(in srgb, var(--ui-secondary) 8%, var(--ui-surface-soft));
    color: var(--ui-text);
}

.pgs-tab--active {
    background: var(--ui-secondary);
    color: #fff;
}

.pgs-editor-shell {
    border-radius: 0.9rem;
    overflow: hidden;
}

.pgs-html-textarea {
    width: 100%;
    border-radius: 0.9rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    padding: 0.85rem;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.83rem;
    color: var(--ui-text);
}

.pgs-html-textarea:focus {
    outline: none;
    border-color: var(--ui-secondary);
}

.pgs-notice {
    margin-top: 0.85rem;
    font-size: 0.8rem;
}

.pgs-notice--warning {
    color: var(--ui-warning);
}

.pgs-notice--danger {
    color: var(--ui-danger);
}

.pgs-preview-frame {
    max-height: 32rem;
    overflow: auto;
    border-radius: 0.9rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    padding: 1rem;
    font-size: 0.85rem;
    color: var(--ui-text);
}

.pgs-save-bar {
    position: sticky;
    bottom: 0.75rem;
    z-index: 10;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
    border-radius: 1rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    padding: 1rem 1.25rem;
    box-shadow: var(--ui-shadow-2);
}

.pgs-save-hint {
    font-size: 0.8rem;
    color: var(--ui-muted);
}
</style>
