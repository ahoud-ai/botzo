<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full">
            <UiPageHeader
                :title="props.testimonial === null ? $t('Create review') : $t('Update review')"
                :subtitle="props.testimonial === null ? $t('Create review') : $t('Update review')"
            >
                <template #actions>
                    <Link href="/admin/testimonials" class="tst-btn tst-btn--solid">{{ $t('Back') }}</Link>
                </template>
            </UiPageHeader>

            <form @submit.prevent="submitForm()" class="mt-6 space-y-6" enctype="multipart/form-data">
                <UiSectionCard :title="$t('Reviewer image')">
                    <FormImage
                        v-model="form.image"
                        :name="'Image'"
                        :error="form.errors.image"
                        :label="$t('Upload image')"
                        :imageUrl="previewImageUrl"
                        :fallbackImageUrl="defaultAvatar"
                    />
                </UiSectionCard>

                <UiSectionCard :title="$t('Name')">
                    <div class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                        <FormInput v-model="form.name_ar" :name="$t('Name (Arabic)')" :error="form.errors.name_ar" :type="'text'"/>
                        <FormInput v-model="form.name_en" :name="$t('Name (English)')" :error="form.errors.name_en" :type="'text'"/>
                    </div>
                </UiSectionCard>

                <UiSectionCard :title="$t('Position')">
                    <div class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                        <FormInput v-model="form.position_ar" :name="$t('Position (Arabic)')" :error="form.errors.position_ar" :type="'text'"/>
                        <FormInput v-model="form.position_en" :name="$t('Position (English)')" :error="form.errors.position_en" :type="'text'"/>
                    </div>
                </UiSectionCard>

                <UiSectionCard :title="$t('Review')">
                    <div class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                        <FormTextArea v-model="form.review_ar" :name="$t('Review (Arabic)')" :error="form.errors.review_ar" :type="'text'" :textAreaRows="4"/>
                        <FormTextArea v-model="form.review_en" :name="$t('Review (English)')" :error="form.errors.review_en" :type="'text'" :textAreaRows="4"/>
                    </div>
                </UiSectionCard>

                <UiSectionCard :title="$t('Rating')">
                    <div class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                        <div>
                            <label class="ui-form-label">{{ $t('Rating') }}</label>
                            <div class="tst-star-picker">
                                <button
                                    v-for="star in [1, 2, 3, 4, 5]"
                                    :key="star"
                                    type="button"
                                    class="tst-star-btn"
                                    :class="{ 'tst-star-btn--on': star <= (form.rating ?? 0) }"
                                    @click="form.rating = star"
                                >
                                    <svg width="26" height="26" viewBox="0 0 24 24">
                                        <path fill="currentColor" d="m12 17.27l4.15 2.51c.76.46 1.69-.22 1.49-1.08l-1.1-4.72l3.67-3.18c.67-.58.31-1.68-.57-1.75l-4.83-.41l-1.89-4.46c-.34-.81-1.5-.81-1.84 0L9.19 8.63l-4.83.41c-.88.07-1.24 1.17-.57 1.75l3.67 3.18l-1.1 4.72c-.2.86.73 1.54 1.49 1.08z"/>
                                    </svg>
                                </button>
                                <span class="tst-star-value">{{ form.rating ? `${form.rating} / 5` : $t('Select rating') }}</span>
                            </div>
                            <div v-if="form.errors.rating" class="ui-form-error mt-2">{{ form.errors.rating }}</div>
                        </div>
                        <FormSelect v-model="form.status" :options="statusOptions" :error="form.errors.status" :name="$t('Status')" :placeholder="$t('Select status')"/>
                    </div>
                </UiSectionCard>

                <div class="flex justify-end">
                    <button type="submit" class="tst-btn tst-btn--solid">
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
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import UiSectionCard from '@/Components/UI/UiSectionCard.vue';

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

<style scoped>
.tst-btn {
    display: inline-flex;
    align-items: center;
    border-radius: 0.85rem;
    padding: 0.6rem 1.1rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: filter 160ms ease;
}

.tst-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.tst-btn--solid:hover {
    filter: brightness(1.05);
}

.tst-star-picker {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin-top: 0.4rem;
}

.tst-star-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--ui-border-strong);
    transition: color 140ms ease, transform 140ms ease;
}

.tst-star-btn:hover {
    transform: scale(1.12);
}

.tst-star-btn--on {
    color: var(--ui-warning);
}

.tst-star-value {
    margin-inline-start: 0.5rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--ui-muted);
}
</style>
