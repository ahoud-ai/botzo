<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full">
            <div class="flex justify-between">
                <div>
                    <h1 v-if="props.testimonial === null" class="text-xl mb-1">{{ $t('Create review') }}</h1>
                    <h1 v-else class="text-xl mb-1">{{ $t('Update review') }}</h1>
                    <p class="mb-6 flex items-center text-sm leading-6 text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                        <span v-if="props.testimonial === null" class="ms-1 mt-1">{{ $t('Create review') }}</span>
                        <span v-else class="ms-1 mt-1">{{ $t('Update review') }}</span>
                    </p>
                </div>
                <div>
                    <Link href="/admin/testimonials" class="rounded-md bg-indigo-600 px-3 py-2 text-sm text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">{{ $t('Back') }}</Link>
                </div>
            </div>

            <form @submit.prevent="submitForm()" class="bg-white border py-5 px-5 rounded-[0.5rem]" enctype="multipart/form-data">
                <div class="sm:flex border-b py-5">
                    <div class="hidden sm:block sm:w-[40%] mb-1">
                        <h1 class="text-sm text-gray-500 tracking-[0px]">{{ $t('Reviewer image') }}</h1>
                    </div>
                    <div class="sm:w-[60%] sm:flex gap-x-6">
                        <div class="sm:w-[80%] grid gap-x-6 gap-y-4 sm:grid-cols-6">
                            <FormImage
                                v-model="form.image"
                                :name="'Image'"
                                :error="form.errors.image"
                                :label="$t('Upload image')"
                                :imageUrl="previewImageUrl"
                                :fallbackImageUrl="defaultAvatar"
                                :class="'sm:col-span-6'"
                            />
                        </div>
                    </div>
                </div>

                <div class="sm:flex border-b py-5">
                    <div class="hidden sm:block sm:w-[40%] mb-1">
                        <h1 class="text-sm text-gray-500 tracking-[0px]">{{ $t('Name') }}</h1>
                    </div>
                    <div class="sm:w-[60%] sm:flex gap-x-6">
                        <div class="sm:w-[80%] grid gap-x-6 gap-y-4 sm:grid-cols-6">
                            <FormInput v-model="form.name_ar" :name="$t('Name (Arabic)')" :error="form.errors.name_ar" :type="'text'" :class="'sm:col-span-3'"/>
                            <FormInput v-model="form.name_en" :name="$t('Name (English)')" :error="form.errors.name_en" :type="'text'" :class="'sm:col-span-3'"/>
                        </div>
                    </div>
                </div>

                <div class="sm:flex border-b py-5">
                    <div class="hidden sm:block sm:w-[40%] mb-1">
                        <h1 class="text-sm text-gray-500 tracking-[0px]">{{ $t('Position') }}</h1>
                    </div>
                    <div class="sm:w-[60%] sm:flex gap-x-6">
                        <div class="sm:w-[80%] grid gap-x-6 gap-y-4 sm:grid-cols-6">
                            <FormInput v-model="form.position_ar" :name="$t('Position (Arabic)')" :error="form.errors.position_ar" :type="'text'" :class="'sm:col-span-3'"/>
                            <FormInput v-model="form.position_en" :name="$t('Position (English)')" :error="form.errors.position_en" :type="'text'" :class="'sm:col-span-3'"/>
                        </div>
                    </div>
                </div>

                <div class="sm:flex border-b py-5">
                    <div class="hidden sm:block sm:w-[40%] mb-1">
                        <h1 class="text-sm text-gray-500 tracking-[0px]">{{ $t('Review') }}</h1>
                    </div>
                    <div class="sm:w-[60%] sm:flex gap-x-6">
                        <div class="sm:w-[80%] grid gap-x-6 gap-y-4 sm:grid-cols-6">
                            <FormTextArea v-model="form.review_ar" :name="$t('Review (Arabic)')" :error="form.errors.review_ar" :type="'text'" :textAreaRows="4" :class="'sm:col-span-3'"/>
                            <FormTextArea v-model="form.review_en" :name="$t('Review (English)')" :error="form.errors.review_en" :type="'text'" :textAreaRows="4" :class="'sm:col-span-3'"/>
                        </div>
                    </div>
                </div>

                <div class="sm:flex border-b py-5">
                    <div class="hidden sm:block w-[40%] mb-1">
                        <h1 class="text-sm text-gray-500 tracking-[0px]">{{ $t('Rating') }}</h1>
                    </div>
                    <div class="sm:w-[60%] sm:flex gap-x-6">
                        <div class="sm:w-[80%] grid gap-x-6 gap-y-4 sm:grid-cols-6">
                            <FormSelect v-model="form.rating" :options="ratingOptions" :error="form.errors.rating" :name="''" :class="'sm:col-span-3'" :placeholder="$t('Select rating')"/>
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
    import { computed, ref } from 'vue';
    import { Link, useForm } from "@inertiajs/vue3";
    import { useI18n } from 'vue-i18n';
    import FormInput from '@/Components/FormInput.vue';
    import FormImage from '@/Components/FormImage.vue';
    import FormTextArea from '@/Components/FormTextArea.vue';
    import FormSelect from '@/Components/FormSelect.vue';

    const { t } = useI18n();

    const props = defineProps({ title: String, testimonial: Object });
    const defaultAvatar = '/images/defaults/review-avatar.svg';

    const toPreviewImageUrl = (imagePath) => {
        if (!imagePath) {
            return defaultAvatar;
        }

        if (typeof imagePath === 'string' && (
            imagePath.startsWith('http://') ||
            imagePath.startsWith('https://') ||
            imagePath.startsWith('data:') ||
            imagePath.startsWith('/')
        )) {
            return imagePath;
        }

        return `/storage/${imagePath}`;
    };

    const previewImageUrl = computed(() => toPreviewImageUrl(props.testimonial?.image));

    const form = useForm({
        name_ar: props.testimonial?.name_ar ?? props.testimonial?.name ?? null,
        name_en: props.testimonial?.name_en ?? props.testimonial?.name ?? null,
        position_ar: props.testimonial?.position_ar ?? props.testimonial?.position ?? null,
        position_en: props.testimonial?.position_en ?? props.testimonial?.position ?? null,
        review_ar: props.testimonial?.review_ar ?? props.testimonial?.review ?? null,
        review_en: props.testimonial?.review_en ?? props.testimonial?.review ?? null,
        rating: props.testimonial?.rating ?? null,
        status: props.testimonial?.status ?? 1,
        image: null,
        _method: null,
    });

    const statusOptions = ref([
        { value: 0, label: t('Hide') },
        { value: 1, label: t('Display') },
    ]);

    const ratingOptions = ref([
        { value: 1, label: '1' },
        { value: 2, label: '2' },
        { value: 3, label: '3' },
        { value: 4, label: '4' },
        { value: 5, label: '5' },
    ]);

    const submitForm = async () => {
        const isUpdate = props.testimonial !== null;
        const url = isUpdate ? window.location.pathname : '/admin/testimonials';

        form._method = isUpdate ? 'PUT' : null;

        form.post(url, {
            preserveScroll: true,
            forceFormData: true,
        });
    };
</script>
