<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full">
            <UiPageHeader :title="$t('Create ticket')" :subtitle="$t('Have an issue? Describe it in detail below')">
                <template #actions>
                    <Link href="/support" class="ticket-form-btn ticket-form-btn--ghost">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16"><path fill="currentColor" fill-rule="evenodd" d="M5.841 5.28a.75.75 0 0 0-1.06-1.06L1.53 7.47L1 8l.53.53l3.25 3.25a.75.75 0 0 0 1.061-1.06l-1.97-1.97H24.25a.75.75 0 0 0 0-1.5H3.871l1.97-1.97Z" clip-rule="evenodd"/></svg>
                        {{ $t('Back') }}
                    </Link>
                </template>
            </UiPageHeader>

            <UiSectionCard :title="$t('Ticket details')" class="ticket-form-card">
                <form @submit.prevent="submitForm()">
                    <div class="grid gap-x-4 gap-y-5 sm:grid-cols-2">
                        <FormInput v-model="form.subject" :name="$t('Subject')" :type="'text'" :error="form.errors.subject" :class="'w-full'" :labelClass="'mb-0'"/>
                        <FormSelect v-model="form.category" :name="$t('Category')" :options="categoryOptions" :error="form.errors.category" :class="'w-full'" :placeholder="$t('Select category')"/>
                    </div>
                    <div class="mt-5">
                        <FormTextArea v-model="form.message" :name="$t('Description')" :type="'text'" :showLabel="true" :error="form.errors.message" :textAreaRows="6" :class="'w-full'"/>
                    </div>
                    <div class="flex justify-end pt-6">
                        <button type="submit" class="ticket-form-btn ticket-form-btn--solid" :disabled="isLoading">
                            <span>{{ $t('Create ticket') }}</span>
                            <svg v-if="isLoading" class="animate-spin" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".3"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"/></svg>
                        </button>
                    </div>
                </form>
            </UiSectionCard>
        </div>
    </AppLayout>
</template>
<script setup>
    import AppLayout from './../Layout/App.vue';
    import { Link, useForm } from "@inertiajs/vue3";
    import { ref, computed, onMounted } from 'vue';
    import FormInput from '@/Components/FormInput.vue';
    import FormSelect from '@/Components/FormSelect.vue';
    import FormTextArea from '@/Components/FormTextArea.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import UiSectionCard from '@/Components/UI/UiSectionCard.vue';
    import { useI18n } from 'vue-i18n';
    const { t, te } = useI18n();

    const props = defineProps(['categories']);
    const form = useForm({
        'subject' : null,
        'category' : null,
        'message' : null,
    });

    const categoryOptions = ref([]);
    const isLoading = computed(() => form.processing);

    const transformCategories = (categories) => {
        return categories.map((category) => ({
            value: category.id,
            label: te(category.name) ? t(category.name) : category.name,
        }));
    };

    const submitForm = () => {
        form.post('/support');
    }

    onMounted(() => {
        categoryOptions.value = transformCategories(props.categories);
    });
</script>

<style scoped>
.ticket-form-card :deep(.ui-section-body) {
    padding-top: 0.25rem;
}

.ticket-form-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    font-weight: 600;
    border-radius: 0.7rem;
    padding: 0.6rem 1.2rem;
}

.ticket-form-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
    transition: filter 160ms ease;
}

.ticket-form-btn--solid:hover:not(:disabled) {
    filter: brightness(1.05);
}

.ticket-form-btn--solid:disabled {
    opacity: 0.75;
    cursor: not-allowed;
}

.ticket-form-btn--ghost {
    background: var(--ui-surface-soft);
    color: var(--ui-text);
    border: 1px solid var(--ui-border);
}

.ticket-form-btn--ghost:hover {
    background: var(--ui-border);
}
</style>
