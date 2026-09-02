<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full">
            <UiPageHeader
                :title="props.faq === null ? $t('Create FAQ') : $t('Update FAQ')"
                :subtitle="props.faq === null ? $t('Create FAQ') : $t('Update FAQ')"
            >
                <template #actions>
                    <Link href="/admin/faqs" class="fq-btn fq-btn--solid">{{ $t('Back') }}</Link>
                </template>
            </UiPageHeader>

            <form @submit.prevent="submitForm()" class="mt-6 space-y-6">
                <UiSectionCard :title="$t('Question')">
                    <div class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                        <FormTextArea v-model="form.question_ar" :name="$t('Question (Arabic)')" :error="form.errors.question_ar" :type="'text'" :textAreaRows="4"/>
                        <FormTextArea v-model="form.question_en" :name="$t('Question (English)')" :error="form.errors.question_en" :type="'text'" :textAreaRows="4"/>
                    </div>
                </UiSectionCard>

                <UiSectionCard :title="$t('Answer')">
                    <div class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                        <FormTextArea v-model="form.answer_ar" :name="$t('Answer (Arabic)')" :error="form.errors.answer_ar" :type="'text'" :textAreaRows="4"/>
                        <FormTextArea v-model="form.answer_en" :name="$t('Answer (English)')" :error="form.errors.answer_en" :type="'text'" :textAreaRows="4"/>
                    </div>
                </UiSectionCard>

                <UiSectionCard :title="$t('Status')">
                    <div class="max-w-xs">
                        <FormSelect v-model="form.status" :options="statusOptions" :error="form.errors.status" :name="''" :placeholder="$t('Select status')"/>
                    </div>
                </UiSectionCard>

                <div class="flex justify-end">
                    <button type="submit" class="fq-btn fq-btn--solid">
                        {{ $t('Save') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
<script setup>
    import AppLayout from "./../Layout/App.vue";
    import { ref } from 'vue';
    import { Link, useForm } from "@inertiajs/vue3";
    import { useI18n } from 'vue-i18n';
    import FormTextArea from '@/Components/FormTextArea.vue';
    import FormSelect from '@/Components/FormSelect.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import UiSectionCard from '@/Components/UI/UiSectionCard.vue';
    const { t } = useI18n();

    const props = defineProps({ title: String, faq: Object });

    const form = useForm({
        question_ar: props.faq?.question_ar ?? props.faq?.question ?? null,
        question_en: props.faq?.question_en ?? props.faq?.question ?? null,
        answer_ar: props.faq?.answer_ar ?? props.faq?.answer ?? null,
        answer_en: props.faq?.answer_en ?? props.faq?.answer ?? null,
        status: props.faq?.status,
    })

    const statusOptions = ref([
        { value: 0, label: t('Hide') },
        { value: 1, label: t('Display') }
    ]);

    const submitForm = async () => {
        const url = props.faq ? window.location.pathname : '/admin/faqs';

        form[props.faq ? 'put' : 'post'](url, {
            preserveScroll: true,
        });
    };
</script>

<style scoped>
.fq-btn {
    display: inline-flex;
    align-items: center;
    border-radius: 0.85rem;
    padding: 0.6rem 1.1rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: filter 160ms ease;
}

.fq-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.fq-btn--solid:hover {
    filter: brightness(1.05);
}
</style>
