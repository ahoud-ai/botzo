<template>
    <AppLayout>
        <div>
            <h2 class="text-xl mb-1">{{ $t('General settings') }}</h2>
            <p class="mb-6 flex items-center text-sm leading-6">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                <span class="ms-1 mt-1">{{ $t('Configure general account information') }}</span>
            </p>
        </div>
        <form @submit.prevent="submitForm()" enctype="multipart/form-data">
            <div class="space-y-12">
                <div class="pb-12">
                    <div class="grid gap-6 grid-cols-2 pb-10 border-b md:w-2/3">
                        <FormInput v-model="form.company_name" :name="$t('Organization/company name')" :error="form.errors.company_name" :type="'text'" :class="'col-span-2'"/>
                        <FormInput v-model="form.email" :name="$t('Email')" :error="form.errors.email" :type="'text'" :class="'col-span-2'"/>
                        <FormInput v-model="form.book_a_demo_link" :name="$t('Book a demo link')" :error="form.errors.book_a_demo_link" :type="'url'" :placeholder="'https://example.com'" :class="'col-span-2'"/>
                    </div>


                    <div class="pt-5">
                        <h2 class="text-base text-gray-900">{{ $t('Social media accounts') }}</h2>
                        <p class="text-sm leading-6 mb-5">{{ $t('Add your social media account links') }}</p>

                        <div class="grid gap-6 grid-cols-2 pb-10 border-b md:w-2/3">
                            <FormInput v-model="form.socials.facebook" :name="'Facebook'" :type="'text'" :class="'col-span-1'"/>
                            <FormInput v-model="form.socials.twitter" :name="'Twitter'" :type="'text'" :class="'col-span-1'"/>
                            <FormInput v-model="form.socials.tiktok" :name="'TikTok'" :type="'text'" :class="'col-span-1'"/>
                            <FormInput v-model="form.socials.snapchat" :name="'Snapchat'" :type="'text'" :class="'col-span-1'"/>
                            <FormInput v-model="form.socials.slack" :name="'Slack'" :type="'text'" :class="'col-span-1'"/>
                            <FormInput v-model="form.socials.linkedin" :name="'LinkedIn'" :type="'text'" :class="'col-span-1'"/>
                        </div>
                    </div>

                    <!--<div class="pt-5">
                        <h2 class="text-base text-gray-900">{{ $t('App environment') }}</h2>
                        <p class="text-sm leading-6 mb-5">{{ $t('Set your app environment') }}</p>

                        <div class="grid gap-6 grid-cols-2 pb-10 border-b md:w-2/3">
                            <FormSelect v-model="form.app_environment" :name="$t('App environment')" :type="'text'"  :options="options" :error="form.errors.app_environment" :class="'col-span-2'"/>
                        </div>
                    </div>-->

                    <div class="pt-5">
                        <h2 class="text-base text-gray-900">{{ $t('Frontend Settings') }}</h2>
                        <p class="text-sm leading-6 mb-5">{{ $t('Show/hide the main frontend page') }}</p>

                        <div class="flex gap-x-10 md:w-2/3">
                            <div class="w-[80%]">
                                <span>{{ $t('Use the current default frontend') }}</span>
                                <div class="text-xs text-slate-700 flex items-center">
                                    <svg class="me-1" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                                    <span>{{ $t('Toggle on/off to show/hide the main frontend page') }}</span>
                                </div>
                            </div>
                            <div class="w-[20%] flex justify-end">
                                <FormToggleSwitch v-model="form.display_frontend"/>
                            </div>
                        </div>

                        <div class="grid gap-6 grid-cols-2 pb-10 border-b md:w-2/3 pt-6">
                            <FormSelect
                                v-model="form.frontend_variant"
                                :name="$t('Frontend Variant')"
                                :options="frontendVariantOptions"
                                :placeholder="$t('Select option')"
                                :error="form.errors.frontend_variant"
                                :class-name="'col-span-2'"
                            />
                            <p class="text-xs text-slate-700 col-span-2 -mt-3">
                                {{ $t('Choose which public-site experience to serve visitors') }}
                            </p>
                        </div>
                    </div>

                    <div class="pt-5">
                        <h2 class="text-base text-gray-900">{{ $t('Site logo and favicon') }}</h2>
                        <p class="text-sm leading-6 mb-5">{{ $t('Add your logo and favicon') }}</p>

                        <div class="grid gap-6 grid-cols-2 pb-10 border-b md:w-2/3">
                            <FormImageLogo v-model="form.logo" :name="$t('Site logo')" :error="form.errors.logo" :label="$t('Upload logo')" :imageUrl="getImageUrl('logo')" :class="'col-span-2'"/>
                            <FormImageFavicon v-model="form.favicon" :name="$t('Favicon')" :error="form.errors.favicon" :label="$t('Upload logo')" :imageUrl="getImageUrl('favicon')" :class="'col-span-2'"/>
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-x-6 md:w-2/3">
                        <button type="submit"
                            :class="['inline-flex justify-center rounded-md border border-transparent bg-primary px-4 py-2 text-sm text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2', { 'opacity-50': isLoading }]"
                            :disabled="isLoading"
                        >
                            <svg v-if="isLoading" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                            <span v-else>{{ $t('Save') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </AppLayout>
</template>
<script setup>
    import AppLayout from "./Layout/App.vue";
    import { defineProps, ref } from 'vue';
    import { useForm } from "@inertiajs/vue3";
    import { useI18n } from 'vue-i18n';
    import FormImageFavicon from '@/Components/FormImageFavicon.vue';
    import FormImageLogo from '@/Components/FormImageLogo.vue';
    import FormInput from '@/Components/FormInput.vue';
    import FormSelect from '@/Components/FormSelect.vue';
    import FormToggleSwitch from '@/Components/FormToggleSwitch.vue';
    const { t } = useI18n();

    const props = defineProps({
        config: {
            type: Array,
            required: true
        }
    });

    const getValueByKey = (key) => {
        const found = props.config.find(item => item.key === key);
        return found ? found.value : '';
    };

    const getImageUrl = (value) => {
        const file = getValueByKey(value);
        return file != '' && file != null ? '/media/' + file : null;
    }

    const frontendVariantOptions = [
        { label: t('Classic Frontend'), value: 'classic' },
        { label: t('Premium Frontend'), value: 'premium' },
    ];

    const normalizedFrontendVariant = (value) => ['classic', 'premium'].includes(value) ? value : 'premium';

    const socials = JSON.parse(getValueByKey('socials') || '{}');
    const isLoading = ref(false);

    const form = useForm({
        _method: 'put',
        company_name: getValueByKey('company_name'),
        email: getValueByKey('email'),
        book_a_demo_link: getValueByKey('book_a_demo_link'),
        app_environment: getValueByKey('app_environment'),
        frontend_variant: normalizedFrontendVariant(getValueByKey('frontend_variant') || 'premium'),
        display_frontend:  getValueByKey('display_frontend') == 1 || getValueByKey('display_frontend') == '' ? true : false,
        logo: null,
        favicon: null,
        socials: {
            facebook: socials?.facebook,
            twitter: socials?.twitter,
            tiktok: socials?.tiktok,
            snapchat: socials?.snapchat,
            slack: socials?.slack,
            linkedin: socials?.linkedin
        }
    })

    const submitForm = async () => {
        form.post('/admin/settings?type=general', {
            preserveScroll: true,
        })
    };
</script>

