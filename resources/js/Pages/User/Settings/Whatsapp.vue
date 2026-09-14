<template>
    <SettingLayout :modules="props.modules" :title="$t('Whatsapp settings')" :subtitle="$t('Manage your WhatsApp connection and webhook configuration')">
        <div class="min-h-0">
            <div class="flex justify-center items-center mb-8">
                <div class="w-full md:w-[60em]">
                    <UiSectionCard :title="$t('Embedded Signup')" :subtitle="$t('Enable/disable embedded signup for your organization.')" class="mb-4">
                        <template #icon>
                            <div class="flex items-center gap-x-3">
                                <FormToggleSwitch
                                    v-model="embeddedSignupForm.enabled"
                                    :disabled="!embeddedSignupGlobalEnabled || !embeddedSignupPlanEnabled || embeddedSignupForm.processing"
                                />
                                <button
                                    type="button"
                                    @click="submitEmbeddedSignupToggle"
                                    class="settings-save-btn"
                                    :disabled="embeddedSignupForm.processing || !embeddedSignupGlobalEnabled || !embeddedSignupPlanEnabled"
                                >
                                    <span>{{ $t('Save') }}</span>
                                </button>
                            </div>
                        </template>
                        <div v-if="!embeddedSignupPlanEnabled || !embeddedSignupGlobalEnabled" class="settings-notice settings-notice--danger">
                            <p v-if="!embeddedSignupPlanEnabled">{{ $t('Embedded signup is not available for your current plan.') }}</p>
                            <p v-if="!embeddedSignupGlobalEnabled">{{ $t('Embedded signup is currently disabled by the administrator.') }}</p>
                        </div>
                    </UiSectionCard>

                    <UiSectionCard v-if="!settings?.whatsapp" :title="$t('Setup Whatsapp Account')" :subtitle="$t('Setup your integration to be able to receive and send messages via Whatsapp.')" class="mb-4">
                        <template #icon>
                            <EmbeddedSignupBtn
                                v-if="embeddedSetupAvailable && !manualFallbackActive"
                                :appId="props.appId"
                                :configId="props.configId"
                                :graphAPIVersion="props.graphAPIVersion"
                            />
                            <button
                                v-else-if="manualFallbackActive"
                                type="button"
                                @click="openModal()"
                                class="settings-save-btn"
                            >
                                {{ $t('Setup whatsapp') }}
                            </button>
                            <button
                                v-else
                                type="button"
                                disabled
                                class="settings-save-btn settings-save-btn--disabled"
                            >
                                {{ $t('Setup whatsapp') }}
                            </button>
                        </template>
                        <div
                            v-if="manualFallbackActive && props.manualFallbackReason === 'global_disabled'"
                            class="settings-notice settings-notice--danger"
                        >
                            {{ $t('Embedded signup is currently disabled by the administrator. Manual setup is available.') }}
                        </div>
                        <div
                            v-else-if="manualFallbackActive && props.manualFallbackReason === 'plan_disabled'"
                            class="settings-notice settings-notice--warning"
                        >
                            {{ $t('Embedded signup is not available for your current plan. Manual setup is available.') }}
                        </div>
                        <div
                            v-else-if="manualFallbackActive && props.manualFallbackReason === 'org_disabled'"
                            class="settings-notice settings-notice--warning"
                        >
                            {{ $t('Embedded signup is disabled for your organization. Manual setup is available.') }}
                        </div>
                        <div
                            v-else-if="embeddedSignupActive && !embeddedSetupAvailable"
                            class="settings-notice settings-notice--warning"
                        >
                            {{ $t('Embedded signup is not configured correctly. Contact the administrator.') }}
                        </div>
                    </UiSectionCard>

                    <UiSectionCard v-if="settings?.whatsapp" :title="$t('Account overview')" class="mb-4">
                        <template #icon>
                            <button type="button" v-if="refreshLoading === false" @click="refreshData()" class="settings-refresh-btn">
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"><path fill="currentColor" d="M12.079 2.25c-4.794 0-8.734 3.663-9.118 8.333H2a.75.75 0 0 0-.528 1.283l1.68 1.666a.75.75 0 0 0 1.056 0l1.68-1.666a.75.75 0 0 0-.528-1.283h-.893c.38-3.831 3.638-6.833 7.612-6.833a7.658 7.658 0 0 1 6.537 3.643a.75.75 0 1 0 1.277-.786A9.158 9.158 0 0 0 12.08 2.25m8.761 8.217a.75.75 0 0 0-1.054 0L18.1 12.133a.75.75 0 0 0 .527 1.284h.899c-.382 3.83-3.651 6.833-7.644 6.833a7.697 7.697 0 0 1-6.565-3.644a.75.75 0 1 0-1.277.788a9.197 9.197 0 0 0 7.842 4.356c4.808 0 8.765-3.66 9.15-8.333H22a.75.75 0 0 0 .527-1.284z"/></svg>
                                <span>{{ $t('Refresh') }}</span>
                            </button>
                            <span v-else class="settings-refresh-btn">
                                <svg class="animate-spin" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".3"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"/></svg>
                            </span>
                        </template>
                        <div class="grid grid-cols-1 gap-x-6 gap-y-5 px-5 py-5 sm:grid-cols-2 lg:grid-cols-4">
                            <div class="settings-stat">
                                <div class="settings-stat-label">{{ $t('Display name') }}</div>
                                <div class="settings-stat-value">{{ settings.whatsapp?.verified_name ?? '-' }}</div>
                            </div>
                            <div class="settings-stat">
                                <div class="settings-stat-label">{{ $t('Connected number') }}</div>
                                <div class="settings-stat-value">{{ settings.whatsapp?.display_phone_number ?? '-' }}</div>
                            </div>
                            <div class="settings-stat">
                                <div class="settings-stat-label">{{ $t('Message limits') }}</div>
                                <div class="settings-stat-value">{{ settings.whatsapp?.messaging_limit_tier ? settings.whatsapp?.messaging_limit_tier : 'N/A' }}</div>
                            </div>
                            <div class="settings-stat">
                                <div class="settings-stat-label">{{ $t('Number status') }}</div>
                                <span class="settings-status-chip" :class="statusChipClass(settings.whatsapp?.number_status)">{{ formatWhatsappStatus(settings.whatsapp?.number_status) }}</span>
                            </div>
                            <div class="settings-stat">
                                <div class="settings-stat-label">{{ $t('Whatsapp business ac ID') }}</div>
                                <div class="settings-stat-value settings-stat-value--mono">{{ settings.whatsapp?.waba_id ?? '-' }}</div>
                            </div>
                            <div v-if="settings.whatsapp?.is_embedded_signup == 1" class="settings-stat">
                                <div class="settings-stat-label">{{ $t('Phone verification status') }}</div>
                                <span class="settings-status-chip" :class="statusChipClass(settings.whatsapp?.code_verification_status)">{{ formatWhatsappStatus(settings.whatsapp?.code_verification_status) }}</span>
                            </div>
                            <div class="settings-stat">
                                <div class="settings-stat-label">{{ $t('Quality rating') }}</div>
                                <span class="settings-status-chip" :class="statusChipClass(settings.whatsapp?.quality_rating)">{{ formatWhatsappStatus(settings.whatsapp?.quality_rating) }}</span>
                            </div>
                            <div class="settings-stat">
                                <div class="settings-stat-label">{{ $t('Account status') }}</div>
                                <span class="settings-status-chip" :class="statusChipClass(settings.whatsapp?.account_review_status)">{{ formatWhatsappStatus(settings.whatsapp?.account_review_status) }}</span>
                            </div>
                        </div>
                    </UiSectionCard>

                    <UiSectionCard v-if="settings?.whatsapp" :title="$t('WhatsApp webhook settings')" :subtitle="props.whatsappWebhookReadiness?.message" class="mb-4">
                        <div class="grid grid-cols-1 gap-4 px-5 pb-5 md:grid-cols-3">
                            <div class="settings-mini-card">
                                <div class="settings-stat-label">{{ $t('Mode') }}</div>
                                <div class="settings-stat-value mt-1">
                                    {{ props.whatsappWebhookReadiness?.mode === 'embedded_signup' ? $t('Embedded signup') : $t('Manual') }}
                                </div>
                            </div>
                            <div class="settings-mini-card">
                                <div class="settings-stat-label">{{ $t('Signature status') }}</div>
                                <div class="settings-stat-value mt-1">
                                    {{ props.whatsappWebhookReadiness?.secretConfigured ? $t('Configured') : $t('Missing secret') }}
                                </div>
                            </div>
                            <div class="settings-mini-card">
                                <div class="settings-stat-label">{{ $t('Inbound delivery') }}</div>
                                <div class="settings-stat-value mt-1">
                                    {{ props.whatsappWebhookReadiness?.canReceiveInbound ? $t('Ready') : $t('Blocked') }}
                                </div>
                            </div>
                        </div>
                        <div v-if="showManualSettingsUi" class="settings-row">
                            <div class="settings-row-label">
                                <span class="settings-field-label">{{ $t('Add these webhook settings to your facebook developer account') }}</span>
                            </div>
                            <div class="settings-row-control">
                                <div class="settings-webhook-line">
                                    <span class="settings-field-hint">{{ $t('Webhook url') }}</span>
                                    <span class="settings-stat-value settings-stat-value--mono">{{ currentURL + '/webhook/whatsapp/' + props.settings.identifier }}</span>
                                </div>
                                <div class="settings-webhook-line">
                                    <span class="settings-field-hint">{{ $t('Verify token') }}</span>
                                    <span class="settings-stat-value settings-stat-value--mono">{{ props.settings.identifier }}</span>
                                </div>
                            </div>
                        </div>
                    </UiSectionCard>

                    <form @submit.prevent="submitForm2()" v-if="settings?.whatsapp">
                    <UiSectionCard :title="$t('Business profile settings')" :subtitle="$t('Setup the Whatsapp business profile for your number')" class="mb-4">
                        <template #icon>
                            <button v-if="showManualSettingsUi" type="button" @click="openModal2()" class="settings-save-btn settings-save-btn--ghost">
                                {{ $t('Update token') }}
                            </button>
                        </template>
                        <div class="settings-row">
                            <div class="settings-row-label">
                                <span class="settings-field-label">{{ $t('Whatsapp profile picture') }}</span>
                                <div class="settings-field-hint">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                                    <span>{{ $t('Add/update your profile picture') }}</span>
                                </div>
                            </div>
                            <div class="settings-row-control">
                                <FormImageLogo v-model="form2.profile_picture_url" :name="''" :error="form2.errors.profile_picture_url" :label="$t('Upload logo')" :imageUrl="form2.profile_picture_url" :class="'col-span-4'"/>
                                <div class="settings-field-hint mt-2">{{ $t('Accepted formats: JPG/PNG') }}</div>
                                <div class="settings-field-hint">{{ $t('Minimum dimensions: 192x192 pixels.') }}</div>
                            </div>
                        </div>
                        <div class="settings-row">
                            <div class="settings-row-label">
                                <span class="settings-field-label">{{ $t('Business address') }}</span>
                                <div class="settings-field-hint">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                                    <span>{{ $t('Specify your physical business address') }}</span>
                                </div>
                            </div>
                            <div class="settings-row-control">
                                <FormInput v-model="form2.address" :error="form2.errors.address" :name="''" :type="'text'" :class="'col-span-4'"/>
                            </div>
                        </div>
                        <div class="settings-row">
                            <div class="settings-row-label">
                                <span class="settings-field-label">{{ $t('Business email') }}</span>
                                <div class="settings-field-hint">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                                    <span>{{ $t('Add your business email address') }}</span>
                                </div>
                            </div>
                            <div class="settings-row-control">
                                <FormInput v-model="form2.email" :error="form2.errors.email" :name="''" :type="'email'" :class="'col-span-4'"/>
                            </div>
                        </div>
                        <div class="settings-row">
                            <div class="settings-row-label">
                                <span class="settings-field-label">{{ $t('Business description') }}</span>
                                <div class="settings-field-hint">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                                    <span>{{ $t('Edit your whatsapp business account description') }}</span>
                                </div>
                            </div>
                            <div class="settings-row-control">
                                <FormTextArea v-model="form2.description" :error="form2.errors.description" :name="''" :type="'text'" :class="'col-span-4'"/>
                            </div>
                        </div>
                        <div class="settings-row">
                            <div class="settings-row-label">
                                <span class="settings-field-label">{{ $t('Business industry') }}</span>
                                <div class="settings-field-hint">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                                    <span>{{ $t('Specify your business vertical') }}</span>
                                </div>
                            </div>
                            <div class="settings-row-control">
                                <FormSelect v-model="form2.industry" :name="''" :type="'text'"  :options="options" :error="form2.errors.industry" :class="'col-span-4'"/>
                            </div>
                        </div>
                        <div class="flex justify-end px-5 pb-5 pt-2">
                            <button type="submit" class="settings-save-btn" :disabled="form2.processing">
                                <svg v-if="form2.processing" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                                <span v-else>{{ $t('Save') }}</span>
                            </button>
                        </div>
                    </UiSectionCard>
                    </form>

                    <UiSectionCard v-if="settings?.whatsapp" :title="$t('Remove Whatsapp account')" :subtitle="$t('This will completely delete your whatsapp integration. Your contacts & messages will be unaffected.')" class="mb-20 settings-danger-card">
                        <template #icon>
                            <button type="button" @click="deleteIntegration()" class="settings-danger-btn">{{ $t('Delete integration')}}</button>
                        </template>
                    </UiSectionCard>
                </div>
            </div>
        </div>

        <Modal :label="$t('Whatsapp API config')" :isOpen="isOpenFormModal" @close="isOpenFormModal = false">
            <div class="mt-5 grid grid-cols-1 gap-x-6 gap-y-4">
                <form @submit.prevent="submitForm()" class="grid gap-x-6 gap-y-4 sm:grid-cols-6">

                    <FormInput v-model="form.app_id" :error="form.errors.app_id" :name="$t('App ID')" :type="'text'" :class="'sm:col-span-6'"/>
                    <FormInput v-model="form.access_token" :error="form.errors.access_token" :name="$t('Access token')" :type="'text'" :class="'sm:col-span-6'"/>
                    <FormInput v-model="form.app_secret" :error="form.errors.app_secret" :name="$t('App secret')" :type="'password'" :class="'sm:col-span-6'"/>
                    <div v-if="props.hasEncryptedWhatsappAppSecret" class="sm:col-span-6 settings-field-hint">
                        {{ $t('A saved app secret already exists. Leave this field as-is only if you are replacing the current secret.') }}
                    </div>
                    <FormInput v-model="form.phone_number_id" :error="form.errors.phone_number_id" :name="$t('Phone number ID')" :type="'text'" :class="'sm:col-span-6'"/>
                    <FormInput v-model="form.waba_id" :error="form.errors.waba_id" :name="$t('Whatsapp business account ID')" :type="'text'" :class="'sm:col-span-6'"/>

                    <div class="mt-4 flex">
                        <button type="button" @click="isOpenFormModal = false" class="settings-modal-btn settings-modal-btn--ghost me-3">{{ $t('Cancel') }}</button>
                        <button type="submit"
                            class="settings-modal-btn settings-modal-btn--solid"
                            :disabled="form.processing"
                        >
                            <svg v-if="form.processing" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                            <span v-else>{{ $t('Save') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal v-if="settings?.whatsapp && showManualSettingsUi" :label="$t('Whatsapp API config')" :isOpen="isOpenForm2Modal" @close="isOpenForm2Modal = false">
            <div class="mt-5 grid grid-cols-1 gap-x-6 gap-y-4">
                <form @submit.prevent="submitForm3()" class="grid gap-x-6 gap-y-4 sm:grid-cols-6">
                    <FormInput v-model="form3.access_token" :error="form3.errors.access_token" :name="$t('Access token')" :type="'text'" :class="'sm:col-span-6'"/>
                    <FormInput v-model="form3.app_secret" :error="form3.errors.app_secret" :name="$t('App secret')" :type="'password'" :class="'sm:col-span-6'"/>
                    <div v-if="props.hasEncryptedWhatsappAppSecret" class="sm:col-span-6 settings-field-hint">
                        {{ $t('Leave the app secret empty to keep the current saved value.') }}
                    </div>

                    <div class="mt-4 flex">
                        <button type="button" @click="isOpenForm2Modal = false" class="settings-modal-btn settings-modal-btn--ghost me-3">{{ $t('Cancel') }}</button>
                        <button type="submit"
                            class="settings-modal-btn settings-modal-btn--solid"
                            :disabled="form3.processing"
                        >
                            <svg v-if="form3.processing" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                            <span v-else>{{ $t('Save') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </SettingLayout>
</template>
<script setup>
    import SettingLayout from "./Layout.vue";
    import { computed, onMounted, ref } from 'vue';
    import EmbeddedSignupBtn from '@/Components/EmbeddedSignupBtn.vue';
    import FormModal from '@/Components/FormModal.vue';
    import FormImageLogo from '@/Components/FormImageLogo.vue';
    import FormInput from '@/Components/FormInput.vue';
    import FormSelect from '@/Components/FormSelect.vue';
    import FormTextArea from '@/Components/FormTextArea.vue';
    import FormToggleSwitch from '@/Components/FormToggleSwitch.vue';
    import Modal from '@/Components/Modal.vue';
    import UiSectionCard from '@/Components/UI/UiSectionCard.vue';
    import { useI18n } from 'vue-i18n';
    import { buildI18nLookupCandidates } from '@/Utils/i18nLookup';
    import { router, useForm } from "@inertiajs/vue3";
    const { t } = useI18n();

    const props = defineProps([
        'settings',
        'embeddedSignupActive',
        'embeddedSignupOrgEnabled',
        'embeddedSignupGlobalEnabled',
        'embeddedSignupPlanEnabled',
        'manualFallbackActive',
        'manualFallbackReason',
        'autoOpenManualSetup',
        'hasEncryptedWhatsappToken',
        'hasEncryptedWhatsappAppSecret',
        'whatsappWebhookReadiness',
        'appId',
        'configId',
        'graphAPIVersion',
        'modules'
    ]);
    const statusView = ref(false);
    const config = ref(props.settings.metadata);
    const currentURL = ref(window.location.origin);
    const isOpenFormModal = ref(false);
    const isOpenForm2Modal = ref(false);
    const settings = ref(config.value ? JSON.parse(config.value) : null);
    const refreshLoading = ref(false);
    const embeddedSignupGlobalEnabled = ref(Boolean(props.embeddedSignupGlobalEnabled));
    const embeddedSignupPlanEnabled = ref(Boolean(props.embeddedSignupPlanEnabled));
    const manualFallbackActive = ref(Boolean(props.manualFallbackActive));
    const embeddedSetupAvailable = computed(() => Boolean(props.appId && props.configId));
    const showManualSettingsUi = computed(() =>
        Boolean(manualFallbackActive.value || settings.value?.whatsapp?.is_embedded_signup === 0)
    );
    const form = useForm({
        app_id: settings.value && settings.value.whatsapp ? settings.value.whatsapp.app_id : null,
        access_token: !props.hasEncryptedWhatsappToken && settings.value && settings.value.whatsapp ? settings.value.whatsapp.access_token : null,
        app_secret: !props.hasEncryptedWhatsappAppSecret && settings.value && settings.value.whatsapp ? settings.value.whatsapp.app_secret : null,
        phone_number_id: settings.value && settings.value.whatsapp ? settings.value.whatsapp.phone_number_id : null,
        waba_id: settings.value && settings.value.whatsapp ? settings.value.whatsapp.waba_id : null,
    });

    const form2 = useForm({
        profile_picture_url: settings.value && settings.value.whatsapp ? settings.value?.whatsapp?.business_profile?.profile_picture_url : null,
        description: settings.value && settings.value.whatsapp ? settings.value?.whatsapp?.business_profile?.description : null,
        address: settings.value && settings.value.whatsapp ? settings.value?.whatsapp?.business_profile?.address : null,
        email: settings.value && settings.value.whatsapp ? settings.value?.whatsapp?.business_profile?.email : null,
        industry: settings.value && settings.value.whatsapp ? settings.value?.whatsapp?.business_profile?.industry : null,
        websites: []
    });

    const form3 = useForm({
        access_token: !props.hasEncryptedWhatsappToken && settings.value && settings.value.whatsapp ? settings.value.whatsapp.access_token : null,
        app_secret: null,
    });

    const embeddedSignupForm = useForm({
        enabled: Boolean(props.embeddedSignupOrgEnabled),
    });

    const options = [
        { label: t('Automotive'), value: 'AUTO' },
        { label: t('Beauty, spa and salon'), value: 'BEAUTY' },
        { label: t('Clothing'), value: 'APPAREL' },
        { label: t('Education'), value: 'EDU' },
        { label: t('Entertainment'), value: 'ENTERTAIN' },
        { label: t('Event planning and service'), value: 'EVENT_PLAN' },
        { label: t('Finance and banking'), value: 'FINANCE' },
        { label: t('Food and groceries'), value: 'GROCERY' },
        { label: t('Public service'), value: 'GOVT' },
        { label: t('Hotel and lodging'), value: 'HOTEL' },
        { label: t('Medical and health'), value: 'HEALTH' },
        { label: t('Charity'), value: 'NONPROFIT' },
        { label: t('Professional services'), value: 'PROF_SERVICES' },
        { label: t('Shopping and retail'), value: 'RETAIL' },
        { label: t('Travel and transportation'), value: 'TRAVEL' },
        { label: t('Restaurant'), value: 'RESTAURANT' },
        { label: t('Not a business'), value: 'NOT_A_BIZ' },
        { label: t('Undefined'), value: 'UNDEFINED' },
        { label: t('Other'), value: 'OTHER' },
    ]

    function openModal() {
        isOpenFormModal.value = true;
    }

    function openModal2() {
        isOpenForm2Modal.value = true;
    }

    onMounted(() => {
        if (props.autoOpenManualSetup) {
            openModal();
        }
    });

    const formatWhatsappStatus = (value) => {
        if (!value) {
            return '-';
        }

        const raw = String(value).trim();
        if (raw === '') {
            return '-';
        }

        const normalized = raw.replace(/_/g, ' ').replace(/\s+/g, ' ').trim();
        const candidates = Array.from(new Set([
            ...buildI18nLookupCandidates(raw),
            ...buildI18nLookupCandidates(normalized),
        ]));

        for (const candidate of candidates) {
            const translated = t(candidate);
            if (translated !== candidate) {
                return translated;
            }
        }

        return normalized.toLowerCase();
    };

    const statusChipClass = (value) => {
        const raw = String(value ?? '').toUpperCase();

        if (!raw) {
            return 'ui-chip-neutral';
        }

        if (['CONNECTED', 'GREEN', 'APPROVED', 'VERIFIED', 'ACTIVE', 'AVAILABLE'].some((keyword) => raw.includes(keyword))) {
            return 'ui-chip-success';
        }

        if (['RED', 'FLAGGED', 'REJECTED', 'RESTRICTED', 'DISCONNECTED', 'BANNED', 'DISABLED'].some((keyword) => raw.includes(keyword))) {
            return 'ui-chip-danger';
        }

        if (['YELLOW', 'PENDING', 'PROCESSING', 'REVIEW', 'WARN'].some((keyword) => raw.includes(keyword))) {
            return 'ui-chip-warning';
        }

        return 'ui-chip-neutral';
    };

    const toggleStatusView = () => {
        statusView.value = !statusView.value;
    }

    const submitForm = () => {
        form.post('/settings/whatsapp', {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                isOpenFormModal.value = false
            }
        })
    }

    const submitForm2 = () => {
        form2.post('/settings/whatsapp/business-profile', {
            preserveScroll: true,
        })
    }

    const submitForm3 = () => {
        form3.post('/settings/whatsapp/token', {
            preserveScroll: true,
            onSuccess: () => {
                isOpenForm2Modal.value = false
            }
        })
    }

    const submitEmbeddedSignupToggle = () => {
        embeddedSignupForm
            .transform((data) => ({
                enabled: !!data.enabled,
            }))
            .post('/settings/features/embedded-signup/toggle', {
                preserveScroll: true,
                onSuccess: () => {
                    router.visit('/settings/whatsapp', {
                        preserveState: false,
                    });
                },
            });
    }

    const refreshData = () => {
        refreshLoading.value = true;

        router.post('/settings/whatsapp/refresh', {}, {
            preserveState: true,
            onFinish: () => {
                refreshLoading.value = false;
            },
            onSuccess: () => {
                router.visit('/settings/whatsapp', {
                    preserveState: false,
                });
            },
        });
    }

    const deleteIntegration = () => {
        router.delete(`/settings/whatsapp/business-profile`, {
            onBefore: () => confirm(t('Are you sure you want to delete your integration?')),
            preserveState: true,
            onSuccess: () => {
                router.visit('/settings/whatsapp', {
                    preserveState: false,
                });
            },
        })
    }
</script>

<style scoped>
.settings-row {
    padding: 1.4rem;
    border-top: 1px solid var(--ui-border);
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.settings-field-label {
    display: block;
    font-size: 0.98rem;
    font-weight: 600;
    color: var(--ui-text);
}

.settings-field-hint {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin-top: 0.3rem;
    font-size: 0.85rem;
    color: var(--ui-muted);
}

.settings-notice {
    margin: 0 1.25rem 1.25rem;
    border-radius: 0.75rem;
    padding: 0.7rem 1rem;
    font-size: 0.85rem;
}

.settings-notice--danger {
    background: color-mix(in srgb, var(--ui-danger) 10%, var(--ui-surface));
    color: color-mix(in srgb, var(--ui-danger) 80%, var(--ui-text));
}

.settings-notice--warning {
    background: color-mix(in srgb, var(--ui-warning) 12%, var(--ui-surface));
    color: color-mix(in srgb, var(--ui-warning) 75%, var(--ui-text));
}

.settings-stat-label {
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--ui-muted);
}

.settings-stat-value {
    margin-top: 0.35rem;
    font-size: 0.98rem;
    font-weight: 600;
    color: var(--ui-text);
    word-break: break-word;
}

.settings-stat-value--mono {
    font-family: ui-monospace, monospace;
    font-size: 0.85rem;
    font-weight: 500;
}

.settings-status-chip {
    display: inline-flex;
    align-items: center;
    margin-top: 0.4rem;
    border-radius: 999px;
    padding: 0.25rem 0.7rem;
    font-size: 0.78rem;
    font-weight: 700;
}

.settings-mini-card {
    border-radius: 0.85rem;
    background: var(--ui-surface-soft);
    border: 1px solid var(--ui-border);
    padding: 0.9rem 1rem;
}

.settings-webhook-line {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.settings-webhook-line + .settings-webhook-line {
    margin-top: 0.9rem;
}

.settings-save-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    justify-content: center;
    border-radius: 0.7rem;
    padding: 0.55rem 1.1rem;
    font-size: 0.85rem;
    font-weight: 700;
    color: #fff;
    background: var(--ui-secondary);
    transition: filter 160ms ease;
}

.settings-save-btn:hover:not(:disabled) {
    filter: brightness(1.05);
}

.settings-save-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.settings-save-btn--disabled {
    background: var(--ui-border-strong);
    color: var(--ui-muted);
    cursor: not-allowed;
}

.settings-save-btn--ghost {
    background: var(--ui-surface-soft);
    color: var(--ui-text);
    border: 1px solid var(--ui-border);
}

.settings-save-btn--ghost:hover {
    background: var(--ui-border);
    filter: none;
}

.settings-refresh-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border-radius: 999px;
    padding: 0.4rem 0.85rem;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--ui-muted);
    background: var(--ui-surface-soft);
    border: 1px solid var(--ui-border);
    transition: background-color 160ms ease, color 160ms ease;
}

.settings-refresh-btn:hover {
    background: var(--ui-border);
    color: var(--ui-text);
}

.settings-danger-card {
    border-color: color-mix(in srgb, var(--ui-danger) 30%, var(--ui-border)) !important;
}

.settings-danger-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.7rem;
    padding: 0.55rem 1.1rem;
    font-size: 0.85rem;
    font-weight: 700;
    color: #fff;
    background: var(--ui-danger);
    transition: filter 160ms ease;
}

.settings-danger-btn:hover {
    filter: brightness(1.08);
}

.settings-modal-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.7rem;
    padding: 0.55rem 1.1rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: background-color 160ms ease, filter 160ms ease;
}

.settings-modal-btn--ghost {
    color: var(--ui-text);
    background: var(--ui-surface-soft);
    border: 1px solid var(--ui-border);
}

.settings-modal-btn--ghost:hover {
    background: var(--ui-border);
}

.settings-modal-btn--solid {
    color: #fff;
    background: var(--ui-secondary);
}

.settings-modal-btn--solid:hover {
    filter: brightness(1.05);
}

.settings-modal-btn--solid:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

@media (min-width: 768px) {
    .settings-row {
        flex-direction: row;
    }

    .settings-row-label {
        width: 40%;
        flex-shrink: 0;
    }

    .settings-row-control {
        width: 60%;
        margin-inline-start: auto;
    }
}
</style>
