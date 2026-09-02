<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full">
            <UiPageHeader
                :title="props.plan === null ? $t('Create plan') : $t('Update plan')"
                :subtitle="$t('Set pricing, limits, and feature access for this subscription tier.')"
            >
                <template #actions>
                    <Link href="/admin/plans" class="pl-btn pl-btn--solid">{{ $t('Back') }}</Link>
                </template>
            </UiPageHeader>

            <form @submit.prevent="submitForm()" class="mt-6 space-y-6">
                <UiSectionCard :title="$t('Name')">
                    <div class="grid gap-x-6 gap-y-4 sm:grid-cols-6">
                        <FormInput v-model="form.name_ar" :name="$t('Name (Arabic)')" :error="form.errors.name_ar" :type="'text'" :class="'sm:col-span-3'"/>
                        <FormInput v-model="form.name_en" :name="$t('Name (English)')" :error="form.errors.name_en" :type="'text'" :class="'sm:col-span-3'"/>
                        <p class="sm:col-span-6 pl-hint">
                            {{ $t('If one language is empty, the other plan name will be used automatically.') }}
                        </p>
                        <div v-if="form.errors.name" class="sm:col-span-6 ui-form-error">{{ form.errors.name }}</div>
                    </div>
                </UiSectionCard>

                <UiSectionCard :title="$t('Status')">
                    <div class="grid gap-x-6 gap-y-4 sm:grid-cols-6">
                        <FormSelect v-model="form.status" :options="statusOptions" :error="form.errors.status" :name="$t('Status')" :class="'sm:col-span-3'" :placeholder="$t('Select status')"/>
                    </div>
                </UiSectionCard>

                <UiSectionCard :title="$t('Pricing details')">
                    <div class="grid gap-x-6 gap-y-4 sm:grid-cols-6">
                        <FormInput v-model="form.price" :name="$t('Price')" :error="form.errors.price" :type="'number'" :class="'sm:col-span-2'"/>
                        <FormSelect v-model="form.period" :options="periodOptions" :error="form.errors.period" :name="$t('Period')" :class="'sm:col-span-2'" :placeholder="$t('Select period')"/>
                        <FormInput v-model="form.tier_rank" :name="$t('Plan tier rank')" :error="form.errors.tier_rank" :type="'number'" :class="'sm:col-span-2'"/>
                        <p class="sm:col-span-6 pl-hint">
                            {{ $t('Lower ranks are treated as lower plans when deciding upgrades and scheduled downgrades.') }}
                        </p>
                    </div>
                </UiSectionCard>

                <UiSectionCard :title="$t('Plan limit')">
                    <div class="pl-banner pl-banner--warning mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                        <p>{{ $t('For unlimited usage, set -1 as the value') }}</p>
                    </div>
                    <div class="grid gap-x-6 gap-y-4 sm:grid-cols-6">
                        <FormInput v-model="form.campaign_limit" :name="$t('Campaign limit')" :error="form.errors.campaign_limit" :type="'number'" :class="'sm:col-span-3'"/>
                        <FormInput v-model="form.message_limit" :name="$t('Message limit')" :error="form.errors.message_limit" :type="'number'" :class="'sm:col-span-3'"/>
                        <FormInput v-model="form.contacts_limit" :name="$t('Contacts limit')" :error="form.errors.contacts_limit" :type="'number'" :class="'sm:col-span-3'"/>
                        <FormInput v-model="form.canned_replies_limit" :name="$t('Canned/Automated replies limit')" :error="form.errors.canned_replies_limit" :type="'number'" :class="'sm:col-span-3'"/>
                        <FormInput v-model="form.team_limit" :name="$t('User limit')" :error="form.errors.team_limit" :type="'number'" :class="'sm:col-span-3'"/>
                        <p class="sm:col-span-6 pl-hint">
                            {{ $t('Canned replies and automated replies refer to the same feature.') }}
                        </p>
                    </div>
                </UiSectionCard>

                <UiSectionCard :title="$t('Branches limit')" :subtitle="$t('Maximum number of branch organizations allowed under a main organization.')">
                    <div class="grid gap-x-6 gap-y-4 sm:grid-cols-6">
                        <FormInput v-model="form.branches_limit" :name="$t('Branches limit')" :error="form.errors.branches_limit" :type="'number'" :class="'sm:col-span-3'"/>
                    </div>
                </UiSectionCard>

                <UiSectionCard :title="$t('Features access')" :subtitle="$t('Select features that are available in this plan')">
                    <div class="grid gap-x-4 gap-y-4 sm:grid-cols-2">
                        <div v-for="addon in addons" :key="addon" class="pl-tile">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="pl-tile-title">{{ $t(resolveAddonLabelKey(addon)) }}</div>
                                    <p class="pl-hint mt-1">{{ $t('Turn on the feature to reveal and save its plan controls below.') }}</p>
                                </div>
                                <FormToggleSwitch v-model="form.addons[addon]"/>
                            </div>
                        </div>
                    </div>
                </UiSectionCard>

                <UiSectionCard v-if="isAddonEnabled('AI Assistant')" :title="$t('AI Assistant controls')" :subtitle="$t('Set AI usage limits and choose whether organizations can use their own key under this plan.')">
                    <div class="pl-banner pl-banner--warning mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                        <p>{{ $t('For unlimited usage, set -1 as the value') }}</p>
                    </div>
                    <div class="grid gap-x-6 gap-y-4 sm:grid-cols-6">
                        <FormInput v-model="form.ai_text_response_limit" :name="$t('AI Text Response Limit')" :error="form.errors.ai_text_response_limit" :type="'number'" :class="'sm:col-span-3'"/>
                        <FormInput v-model="form.ai_audio_response_limit" :name="$t('AI Audio Response Limit')" :error="form.errors.ai_audio_response_limit" :type="'number'" :class="'sm:col-span-3'"/>
                        <FormInput v-model="form.ai_system_key_monthly_quota" :name="$t('AI system key monthly quota')" :error="form.errors.ai_system_key_monthly_quota" :type="'number'" :class="'sm:col-span-3'"/>
                        <div class="sm:col-span-3 pl-tile">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="pl-tile-title">{{ $t('Allow organization key') }}</div>
                                    <p class="pl-hint mt-1">{{ $t('Allow each organization on this plan to use its own AI API key.') }}</p>
                                </div>
                                <FormToggleSwitch v-model="form.ai_organization_key_enabled"/>
                            </div>
                            <div v-if="form.errors.ai_organization_key_enabled" class="ui-form-error mt-2">{{ form.errors.ai_organization_key_enabled }}</div>
                        </div>
                    </div>
                    <div v-if="props.enable_ai_billing != 1" class="pl-banner pl-banner--warning mt-4">
                        <p>{{ $t('AI response quotas are enforced only when AI billing is enabled globally.') }}</p>
                    </div>
                </UiSectionCard>

                <UiSectionCard v-if="isAddonEnabled('Flow builder')" :title="$t('Flow Builder limits')">
                    <div class="pl-banner pl-banner--warning mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                        <p>{{ $t('For unlimited usage, set -1 as the value') }}</p>
                    </div>
                    <div class="grid gap-x-6 gap-y-4 sm:grid-cols-6">
                        <FormInput v-model="form.flow_builder_active_flows_limit" :name="$t('Active flows limit')" :error="form.errors.flow_builder_active_flows_limit" :type="'number'" :class="'sm:col-span-3'"/>
                        <FormInput v-model="form.flow_builder_nodes_per_flow_limit" :name="$t('Nodes per flow limit')" :error="form.errors.flow_builder_nodes_per_flow_limit" :type="'number'" :class="'sm:col-span-3'"/>
                        <FormInput v-model="form.flow_builder_monthly_runs_limit" :name="$t('Monthly flow runs limit')" :error="form.errors.flow_builder_monthly_runs_limit" :type="'number'" :class="'sm:col-span-3'"/>
                        <div class="sm:col-span-3">
                            <div class="pl-tile-title mb-2">{{ $t('Enable advanced Flow Builder nodes') }}</div>
                            <FormToggleSwitch v-model="form.flow_builder_advanced_enabled"/>
                            <div v-if="form.errors.flow_builder_advanced_enabled" class="ui-form-error mt-1">{{ form.errors.flow_builder_advanced_enabled }}</div>
                        </div>
                    </div>
                </UiSectionCard>

                <UiSectionCard :title="$t('Custom features')" :subtitle="$t('Add custom plan features outside the built-in system limits.')">
                    <div class="space-y-3">
                        <div
                            v-for="(feature, featureIndex) in form.custom_features"
                            :key="`custom-feature-${featureIndex}`"
                            class="pl-tile grid gap-x-6 gap-y-3 sm:grid-cols-6"
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
                                <button type="button" class="pl-remove-btn" @click="removeCustomFeature(featureIndex)">
                                    {{ $t('Remove feature') }}
                                </button>
                            </div>
                        </div>
                        <button type="button" class="pl-btn pl-btn--ghost" @click="addCustomFeature">
                            {{ $t('Add custom feature') }}
                        </button>
                    </div>
                </UiSectionCard>

                <UiSectionCard :title="$t('Enable message reception after plan expiration')" :subtitle="$t('Toggle this setting to allow or block inbound messages when a user\'s subscription plan has ended.')">
                    <FormToggleSwitch v-model="form.receive_messages_after_expiration"/>
                </UiSectionCard>

                <div class="flex justify-end">
                    <button type="submit" class="pl-btn pl-btn--solid">
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
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import UiSectionCard from '@/Components/UI/UiSectionCard.vue';
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

<style scoped>
.pl-btn {
    display: inline-flex;
    align-items: center;
    border-radius: 0.85rem;
    padding: 0.6rem 1.1rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: background-color 160ms ease, border-color 160ms ease, filter 160ms ease;
}

.pl-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.pl-btn--solid:hover {
    filter: brightness(1.05);
}

.pl-btn--ghost {
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    color: var(--ui-text);
}

.pl-btn--ghost:hover {
    background: var(--ui-surface-soft);
    border-color: var(--ui-border-strong);
}

.pl-hint {
    font-size: 0.78rem;
    color: var(--ui-muted);
}

.pl-banner {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: 0.8rem;
    border: 1px solid color-mix(in srgb, var(--ui-warning) 35%, var(--ui-border));
    background: color-mix(in srgb, var(--ui-warning) 10%, var(--ui-surface));
    padding: 0.6rem 0.85rem;
    font-size: 0.85rem;
    color: var(--ui-text);
}

.pl-tile {
    border-radius: 0.9rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    padding: 0.9rem 1rem;
}

.pl-tile-title {
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--ui-text);
}

.pl-remove-btn {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--ui-danger);
}

.pl-remove-btn:hover {
    text-decoration: underline;
}
</style>

