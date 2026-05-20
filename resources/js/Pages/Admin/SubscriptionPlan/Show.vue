<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full">
            <div class="flex justify-between">
                <div>
                    <h1 v-if="props.plan === null" class="text-xl mb-1">{{ $t('Create plan') }}</h1>
                    <h1 v-else class="text-xl mb-1">{{ $t('Update plan') }}</h1>
                    <p class="mb-6 flex items-center text-sm leading-6 text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                        <span v-if="props.plan === null" class="ms-1 mt-1">{{ $t('Create plan') }}</span>
                        <span v-else class="ms-1 mt-1">{{ $t('Update plan') }}</span>
                    </p>
                </div>
                <div>
                    <Link href="/admin/plans" class="rounded-md bg-indigo-600 px-3 py-2 text-sm text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">{{ $t('Back') }}</Link>
                </div>
            </div>
            <form @submit.prevent="submitForm()" class="bg-white border py-5 px-5 rounded-[0.5rem]">
                <div class="sm:flex border-b py-5">
                    <div class="hidden sm:block sm:w-[40%] mb-1">
                        <h1 class="text-sm text-gray-500 tracking-[0px]">{{ $t('Name') }}</h1>
                    </div>
                    <div class="sm:w-[60%] sm:flex gap-x-6">
                        <div class="sm:w-[80%] grid gap-x-6 gap-y-4 sm:grid-cols-6">
                            <FormInput v-model="form.name_ar" :name="$t('Name (Arabic)')" :error="form.errors.name_ar" :type="'text'" :class="'sm:col-span-3'"/>
                            <FormInput v-model="form.name_en" :name="$t('Name (English)')" :error="form.errors.name_en" :type="'text'" :class="'sm:col-span-3'"/>
                            <p class="sm:col-span-6 text-xs text-slate-700">
                                {{ $t('If one language is empty, the other plan name will be used automatically.') }}
                            </p>
                            <div v-if="form.errors.name" class="sm:col-span-6 ui-form-error">{{ form.errors.name }}</div>
                        </div>
                    </div>
                </div>
                <div class="sm:flex border-b py-5">
                    <div class="hidden sm:block sm:w-[40%] mb-1">
                        <h1 class="text-sm text-gray-500 tracking-[0px]">{{ $t('Status') }}</h1>
                    </div>
                    <div class="sm:w-[60%] sm:flex gap-x-6">
                        <div class="sm:w-[80%] grid gap-x-6 gap-y-4 sm:grid-cols-6">
                            <FormSelect v-model="form.status" :options="statusOptions" :error="form.errors.status" :name="$t('Status')" :class="'sm:col-span-6'" :placeholder="$t('Select status')"/>
                        </div>
                    </div>
                </div>
                <div class="sm:flex border-b py-5">
                    <div class="hidden sm:block w-[40%] mb-1">
                        <h1 class="text-sm text-gray-500 tracking-[0px]">{{ $t('Pricing details') }}</h1>
                    </div>
                    <div class="sm:w-[60%] sm:flex gap-x-6">
                        <div class="sm:w-[80%] grid gap-x-6 gap-y-4 sm:grid-cols-6">
                            <FormInput v-model="form.price" :name="$t('Price')" :error="form.errors.price" :type="'number'" :class="'sm:col-span-3'"/>
                            <FormSelect v-model="form.period" :options="periodOptions" :error="form.errors.period" :name="$t('Period')" :class="'sm:col-span-3'" :placeholder="$t('Select period')"/>
                            <FormInput v-model="form.tier_rank" :name="$t('Plan tier rank')" :error="form.errors.tier_rank" :type="'number'" :class="'sm:col-span-3'"/>
                            <p class="sm:col-span-6 text-xs text-slate-700">
                                {{ $t('Lower ranks are treated as lower plans when deciding upgrades and scheduled downgrades.') }}
                            </p>
                        </div>
                    </div>
                </div>
                <div class="sm:flex py-5 border-b">
                    <div class="hidden sm:block w-[40%] mb-1">
                        <h1 class="text-sm text-gray-500 tracking-[0px]">{{ $t('Plan limit') }}</h1>
                    </div>
                    <div class="sm:w-[60%]">
                        <div class="bg-orange-100 p-2 rounded-md shadow-sm sm:w-[80%] mb-4 flex items-center gap-x-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                            <p class="text-sm leading-6">{{ $t('For unlimited usage, set -1 as the value') }}</p>
                        </div>
                        <div class="sm:w-[80%] grid gap-x-6 gap-y-4 sm:grid-cols-6">
                            <FormInput v-model="form.campaign_limit" :name="$t('Campaign limit')" :error="form.errors.campaign_limit" :type="'number'" :class="'sm:col-span-3'"/>
                            <FormInput v-model="form.message_limit" :name="$t('Message limit')" :error="form.errors.message_limit" :type="'number'" :class="'sm:col-span-3'"/>
                            <FormInput v-model="form.contacts_limit" :name="$t('Contacts limit')" :error="form.errors.contacts_limit" :type="'number'" :class="'sm:col-span-3'"/>
                            <FormInput v-model="form.canned_replies_limit" :name="$t('Canned/Automated replies limit')" :error="form.errors.canned_replies_limit" :type="'number'" :class="'sm:col-span-3'"/>
                            <FormInput v-model="form.team_limit" :name="$t('User limit')" :error="form.errors.team_limit" :type="'number'" :class="'sm:col-span-3'"/>
                            <p class="sm:col-span-6 text-xs text-slate-700">
                                {{ $t('Canned replies and automated replies refer to the same feature.') }}
                            </p>
                        </div>
                    </div>
                </div>
                <div class="sm:flex py-5 border-b">
                    <div class="hidden sm:block w-[40%] mb-1">
                        <h1 class="text-sm text-gray-500 tracking-[0px]">{{ $t('Branches limit') }}</h1>
                        <p class="text-xs text-slate-700">{{ $t('Maximum number of branch organizations allowed under a main organization.') }}</p>
                    </div>
                    <div class="sm:w-[60%]">
                        <div class="sm:w-[80%] grid gap-x-6 gap-y-4 sm:grid-cols-6">
                            <FormInput v-model="form.branches_limit" :name="$t('Branches limit')" :error="form.errors.branches_limit" :type="'number'" :class="'sm:col-span-3'"/>
                        </div>
                    </div>
                </div>
                <div class="sm:flex py-5 border-b">
                    <div class="hidden sm:block w-[40%] mb-1">
                        <h1 class="text-sm text-gray-500 tracking-[0px]">{{ $t('Features access') }}</h1>
                        <p class="text-xs text-slate-700">{{ $t('Select features that are available in this plan') }}</p>
                    </div>
                    <div class="sm:w-[60%]">
                        <div class="sm:w-[80%] grid gap-x-6 gap-y-4 sm:grid-cols-6">
                            <div v-for="addon in addons" :key="addon" class="sm:col-span-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-medium text-slate-900">{{ $t(resolveAddonLabelKey(addon)) }}</div>
                                        <p class="mt-1 text-xs text-slate-600">{{ $t('Turn on the feature to reveal and save its plan controls below.') }}</p>
                                    </div>
                                    <FormToggleSwitch v-model="form.addons[addon]"/>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-if="isAddonEnabled('AI Assistant')" class="sm:flex py-5 border-b">
                    <div class="hidden sm:block w-[40%] mb-1">
                        <h1 class="text-sm text-gray-500 tracking-[0px]">{{ $t('AI Assistant controls') }}</h1>
                        <p class="text-xs text-slate-700">{{ $t('Set AI usage limits and choose whether organizations can use their own key under this plan.') }}</p>
                    </div>
                    <div class="sm:w-[60%]">
                        <div class="bg-orange-100 p-2 rounded-md shadow-sm sm:w-[80%] mb-4 flex items-center gap-x-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                            <p class="text-sm leading-6">{{ $t('For unlimited usage, set -1 as the value') }}</p>
                        </div>
                        <div class="sm:w-[80%] grid gap-x-6 gap-y-4 sm:grid-cols-6">
                            <FormInput v-model="form.ai_text_response_limit" :name="$t('AI Text Response Limit')" :error="form.errors.ai_text_response_limit" :type="'number'" :class="'sm:col-span-3'"/>
                            <FormInput v-model="form.ai_audio_response_limit" :name="$t('AI Audio Response Limit')" :error="form.errors.ai_audio_response_limit" :type="'number'" :class="'sm:col-span-3'"/>
                            <FormInput v-model="form.ai_system_key_monthly_quota" :name="$t('AI system key monthly quota')" :error="form.errors.ai_system_key_monthly_quota" :type="'number'" :class="'sm:col-span-3'"/>
                            <div class="sm:col-span-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-medium text-slate-900">{{ $t('Allow organization key') }}</div>
                                        <p class="mt-1 text-xs text-slate-600">{{ $t('Allow each organization on this plan to use its own AI API key.') }}</p>
                                    </div>
                                    <FormToggleSwitch v-model="form.ai_organization_key_enabled"/>
                                </div>
                                <div v-if="form.errors.ai_organization_key_enabled" class="ui-form-error mt-2">{{ form.errors.ai_organization_key_enabled }}</div>
                            </div>
                        </div>
                        <div v-if="props.enable_ai_billing != 1" class="sm:w-[80%] mt-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                            {{ $t('AI response quotas are enforced only when AI billing is enabled globally.') }}
                        </div>
                    </div>
                </div>
                <div v-if="isAddonEnabled('Flow builder')" class="sm:flex py-5 border-b">
                    <div class="hidden sm:block w-[40%] mb-1">
                        <h1 class="text-sm text-gray-500 tracking-[0px]">{{ $t('Flow Builder limits') }}</h1>
                    </div>
                    <div class="sm:w-[60%]">
                        <div class="bg-orange-100 p-2 rounded-md shadow-sm sm:w-[80%] mb-4 flex items-center gap-x-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                            <p class="text-sm leading-6">{{ $t('For unlimited usage, set -1 as the value') }}</p>
                        </div>
                        <div class="sm:w-[80%] grid gap-x-6 gap-y-4 sm:grid-cols-6">
                            <FormInput v-model="form.flow_builder_active_flows_limit" :name="$t('Active flows limit')" :error="form.errors.flow_builder_active_flows_limit" :type="'number'" :class="'sm:col-span-3'"/>
                            <FormInput v-model="form.flow_builder_nodes_per_flow_limit" :name="$t('Nodes per flow limit')" :error="form.errors.flow_builder_nodes_per_flow_limit" :type="'number'" :class="'sm:col-span-3'"/>
                            <FormInput v-model="form.flow_builder_monthly_runs_limit" :name="$t('Monthly flow runs limit')" :error="form.errors.flow_builder_monthly_runs_limit" :type="'number'" :class="'sm:col-span-3'"/>
                            <div class="sm:col-span-3">
                                <div class="text-sm mb-2">{{ $t('Enable advanced Flow Builder nodes') }}</div>
                                <FormToggleSwitch v-model="form.flow_builder_advanced_enabled"/>
                                <div v-if="form.errors.flow_builder_advanced_enabled" class="ui-form-error mt-1">{{ form.errors.flow_builder_advanced_enabled }}</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="sm:flex py-5 border-b">
                    <div class="hidden sm:block w-[40%] mb-1">
                        <h1 class="text-sm text-gray-500 tracking-[0px]">{{ $t('Custom features') }}</h1>
                        <p class="text-xs text-slate-700">{{ $t('Add custom plan features outside the built-in system limits.') }}</p>
                    </div>
                    <div class="sm:w-[60%]">
                        <div class="sm:w-[80%] space-y-3">
                            <div
                                v-for="(feature, featureIndex) in form.custom_features"
                                :key="`custom-feature-${featureIndex}`"
                                class="grid gap-x-6 gap-y-3 sm:grid-cols-6 border border-gray-200 rounded-lg p-3"
                            >
                                <FormInput
                                    v-model="feature.text_ar"
                                    :name="$t('Feature text (Arabic)')"
                                    :error="form.errors[`custom_features.${featureIndex}.text_ar`]"
                                    :type="'text'"
                                    :class="'sm:col-span-3'"
                                />
                                <FormInput
                                    v-model="feature.text_en"
                                    :name="$t('Feature text (English)')"
                                    :error="form.errors[`custom_features.${featureIndex}.text_en`]"
                                    :type="'text'"
                                    :class="'sm:col-span-3'"
                                />
                                <div class="sm:col-span-6 flex justify-end">
                                    <button type="button" class="text-xs text-red-600 hover:text-red-700" @click="removeCustomFeature(featureIndex)">
                                        {{ $t('Remove feature') }}
                                    </button>
                                </div>
                            </div>
                            <button type="button" class="rounded-md border border-primary text-primary px-3 py-1.5 text-sm hover:bg-primary hover:text-white transition-colors" @click="addCustomFeature">
                                {{ $t('Add custom feature') }}
                            </button>
                        </div>
                    </div>
                </div>
                <div class="py-5 sm:flex">
                    <div class="w-[40%]">
                        <h1 class="text-sm text-gray-500 tracking-[0px]">{{ $t('Enable message reception after plan expiration') }}</h1>
                        <div class="text-xs text-slate-700 flex items-center">
                            <span>{{ $t('Toggle this setting to allow or block inbound messages when a user\'s subscription plan has ended.') }}</span>
                        </div>
                    </div>
                    <div class="sm:w-[20%] flex items-start">
                        <FormToggleSwitch v-model="form.receive_messages_after_expiration"/>
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
    import FormInput from '@/Components/FormInput.vue';
    import FormSelect from '@/Components/FormSelect.vue';
    import FormToggleSwitch from '@/Components/FormToggleSwitch.vue';
    import { useI18n } from 'vue-i18n';
    const { t } = useI18n();

    const props = defineProps(['title', 'plan', 'addons', 'enable_ai_billing']);
    const addonLabelKeyMap = {
        'Flow builder': 'Flow Builder',
    };

    const getDetail = (value, key) => {
        if(value){
            const item = JSON.parse(value);
            return item?.[key] ?? null;
        } else {
            return null;
        }
    }

    const firstFilled = (...values) => {
        for (const value of values) {
            if (typeof value === 'string' && value.trim() !== '') {
                return value.trim();
            }
        }

        return null;
    };

    const resolveAddonLabelKey = (addon) => addonLabelKeyMap[addon] ?? addon;

    const normalizeBoolean = (value, fallback = false) => {
        if (typeof value === 'boolean') {
            return value;
        }

        if (typeof value === 'number') {
            return value === 1;
        }

        if (typeof value === 'string') {
            return ['1', 'true', 'yes', 'on'].includes(value.trim().toLowerCase());
        }

        return fallback;
    };

    const normalizeCustomFeatures = (value) => {
        if (!value) {
            return [{ text_ar: '', text_en: '' }];
        }

        try {
            const item = JSON.parse(value);
            const features = item?.custom_features;

            if (!Array.isArray(features) || features.length === 0) {
                return [{ text_ar: '', text_en: '' }];
            }

            const normalized = features.map((feature) => ({
                text_ar: typeof feature?.text_ar === 'string' ? feature.text_ar : '',
                text_en: typeof feature?.text_en === 'string' ? feature.text_en : '',
            }));

            return normalized.length > 0 ? normalized : [{ text_ar: '', text_en: '' }];
        } catch (error) {
            return [{ text_ar: '', text_en: '' }];
        }
    };

    const getAddons = (value, key) => {
        if(value){
            const item = JSON.parse(value);
            const fallback = props.addons.reduce((acc, addon) => {
                acc[addon] = false;
                return acc;
            }, {});
            const addonValues = item?.[key];

            if (!addonValues || typeof addonValues !== 'object') {
                return fallback;
            }

            return props.addons.reduce((acc, addon) => {
                acc[addon] = normalizeBoolean(addonValues[addon] ?? false);
                return acc;
            }, {});
        }

        // Return the props.addons with all values set to false
        return props.addons.reduce((acc, addon) => {
            acc[addon] = false;
            return acc;
        }, {});
    };

    const form = useForm({
        name: props.plan?.name,
        name_ar: props.plan?.name_ar ?? props.plan?.name ?? null,
        name_en: props.plan?.name_en ?? props.plan?.name ?? null,
        price: props.plan?.price,
        period: props.plan?.period,
        tier_rank: getDetail(props.plan?.metadata, 'tier_rank') ?? props.plan?.tier_rank ?? 1,
        status: props.plan?.status,
        campaign_limit: getDetail(props.plan?.metadata, 'campaign_limit') ?? '-1',
        message_limit: getDetail(props.plan?.metadata, 'message_limit') ?? '-1',
        contacts_limit: getDetail(props.plan?.metadata, 'contacts_limit') ?? '-1',
        canned_replies_limit: getDetail(props.plan?.metadata, 'canned_replies_limit') ?? '-1',
        team_limit: getDetail(props.plan?.metadata, 'team_limit') ?? '-1',
        ai_text_response_limit: getDetail(props.plan?.metadata, 'ai_text_response_limit') ?? '-1',
        ai_audio_response_limit: getDetail(props.plan?.metadata, 'ai_audio_response_limit') ?? '-1',
        ai_organization_key_enabled: getDetail(props.plan?.metadata, 'ai_organization_key_enabled') == 0 ? false : true,
        branches_limit: getDetail(props.plan?.metadata, 'branches_limit') ?? '-1',
        ai_system_key_monthly_quota: getDetail(props.plan?.metadata, 'ai_system_key_monthly_quota') ?? '-1',
        flow_builder_active_flows_limit: getDetail(props.plan?.metadata, 'flow_builder_active_flows_limit') ?? '-1',
        flow_builder_nodes_per_flow_limit: getDetail(props.plan?.metadata, 'flow_builder_nodes_per_flow_limit') ?? '-1',
        flow_builder_monthly_runs_limit: getDetail(props.plan?.metadata, 'flow_builder_monthly_runs_limit') ?? '-1',
        flow_builder_advanced_enabled: getDetail(props.plan?.metadata, 'flow_builder_advanced_enabled') == 0 ? false : true,
        receive_messages_after_expiration: getDetail(props.plan?.metadata, 'receive_messages_after_expiration') == 1 || getDetail(props.plan?.metadata, 'receive_messages_after_expiration') == null ? true : false,
        addons: getAddons(props.plan?.metadata, 'addons'),
        custom_features: normalizeCustomFeatures(props.plan?.metadata),
    })

    const isAddonEnabled = (addon) => normalizeBoolean(form.addons?.[addon] ?? false);

    const statusOptions = ref([
        { value: 'active', label: t('active') },
        { value: 'inactive', label: t('inactive') }
    ]);

    const periodOptions = ref([
        { value: 'monthly', label: t('Monthly') },
        { value: 'yearly', label: t('Yearly') }
    ]);

    const addCustomFeature = () => {
        form.custom_features.push({ text_ar: '', text_en: '' });
    };

    const removeCustomFeature = (index) => {
        if (form.custom_features.length === 1) {
            form.custom_features[0] = { text_ar: '', text_en: '' };
            return;
        }

        form.custom_features.splice(index, 1);
    };

    const prepareFormPayload = () => {
        form.name_ar = typeof form.name_ar === 'string' ? form.name_ar.trim() : form.name_ar;
        form.name_en = typeof form.name_en === 'string' ? form.name_en.trim() : form.name_en;
        form.name = firstFilled(form.name_en, form.name_ar, form.name);
        form.custom_features = form.custom_features
            .map((feature) => ({
                text_ar: typeof feature?.text_ar === 'string' ? feature.text_ar.trim() : '',
                text_en: typeof feature?.text_en === 'string' ? feature.text_en.trim() : '',
            }))
            .filter((feature) => feature.text_ar !== '' || feature.text_en !== '');
    };

    const submitForm = async () => {
        prepareFormPayload();
        const url = props.plan ? window.location.pathname : '/admin/plans';

        form[props.plan ? 'put' : 'post'](url, {
            preserveScroll: true,
        });
    };
</script>

