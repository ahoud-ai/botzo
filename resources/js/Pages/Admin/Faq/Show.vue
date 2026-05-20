<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full">
            <div class="flex justify-between">
                <div>
                    <h1 v-if="props.faq === null" class="text-xl mb-1">{{ $t('Create FAQ') }}</h1>
                    <h1 v-else class="text-xl mb-1">{{ $t('Update FAQ') }}</h1>
                    <p class="mb-6 flex items-center text-sm leading-6 text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                        <span v-if="props.faq === null" class="ms-1 mt-1">{{ $t('Create FAQ') }}</span>
                        <span v-else class="ms-1 mt-1">{{ $t('Update FAQ') }}</span>
                    </p>
                </div>
                <div>
                    <Link href="/admin/faqs" class="rounded-md bg-indigo-600 px-3 py-2 text-sm text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">{{ $t('Back') }}</Link>
                </div>
            </div>
            <form @submit.prevent="submitForm()" class="bg-white border py-5 px-5 rounded-[0.5rem]">
                <div class="sm:flex border-b py-5">
                    <div class="hidden sm:block sm:w-[40%] mb-1">
                        <h1 class="text-sm text-gray-500 tracking-[0px]">{{ $t('Question') }}</h1>
                    </div>
                    <div class="sm:w-[60%] sm:flex gap-x-6">
                        <div class="sm:w-[80%] grid gap-x-6 gap-y-4 sm:grid-cols-6">
                            <FormTextArea v-model="form.question_ar" :name="$t('Question (Arabic)')" :error="form.errors.question_ar" :type="'text'" :textAreaRows="4" :class="'sm:col-span-3'"/>
                            <FormTextArea v-model="form.question_en" :name="$t('Question (English)')" :error="form.errors.question_en" :type="'text'" :textAreaRows="4" :class="'sm:col-span-3'"/>
                        </div>
                    </div>
                </div>
                <div class="sm:flex border-b py-5">
                    <div class="hidden sm:block sm:w-[40%] mb-1">
                        <h1 class="text-sm text-gray-500 tracking-[0px]">{{ $t('Answer') }}</h1>
                    </div>
                    <div class="sm:w-[60%] sm:flex gap-x-6">
                        <div class="sm:w-[80%] grid gap-x-6 gap-y-4 sm:grid-cols-6">
                            <FormTextArea v-model="form.answer_ar" :name="$t('Answer (Arabic)')" :error="form.errors.answer_ar" :type="'text'" :textAreaRows="4" :class="'sm:col-span-3'"/>
                            <FormTextArea v-model="form.answer_en" :name="$t('Answer (English)')" :error="form.errors.answer_en" :type="'text'" :textAreaRows="4" :class="'sm:col-span-3'"/>
                        </div>
                    </div>
                </div>
                <div class="sm:flex border-b py-5">
                    <div class="hidden sm:block w-[40%] mb-1">
                        <h1 class="text-sm text-gray-500 tracking-[0px]">{{ $t('Status') }}</h1>
                    </div>
                    <div class="sm:w-[60%] sm:flex gap-x-6">
                        <div class="sm:w-[80%] grid gap-x-6 gap-y-4 sm:grid-cols-1">
                            <FormSelect v-model="form.status" :options="statusOptions" :error="form.errors.status" :name="''" :class="'sm:col-span-3'" :placeholder="$t('Select status')"/>
                        </div>
                    </div>
                </div>
                <div class="py-6 flex justify-end">
                    <button type="submit" class="flex items-center gap-x-4 rounded-md bg-black px-3 py-2 text-sm text-white shadow-sm hover:bg-slate-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
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

