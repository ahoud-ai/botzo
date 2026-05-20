<template>
    <SettingLayout :modules="props.modules">
        <div class="min-h-0">
            <div class="flex justify-center items-center mb-8">
                <div class="md:w-[60em]">
                    <div class="ui-card py-4 text-sm mb-4">
                        <div class="flex items-center justify-between px-4">
                            <div class="w-[70%]">
                                <h2 class="text-[17px]">{{ $t('Embedded Signup') }}</h2>
                                <span class="flex items-center mt-1">
                                    {{ $t('Enable/disable embedded signup for your organization.') }}
                                </span>
                                <div v-if="!embeddedSignupPlanEnabled" class="text-xs text-red-600 mt-2">
                                    {{ $t('Embedded signup is not available for your current plan.') }}
                                </div>
                                <div v-if="!embeddedSignupGlobalEnabled" class="text-xs text-red-600 mt-1">
                                    {{ $t('Embedded signup is currently disabled by the administrator.') }}
                                </div>
                            </div>
                            <div class="ms-auto flex items-center gap-x-3">
                                <FormToggleSwitch
                                    v-model="embeddedSignupForm.enabled"
                                    :disabled="!embeddedSignupGlobalEnabled || !embeddedSignupPlanEnabled || embeddedSignupForm.processing"
                                />
                                <button
                                    type="button"
                                    @click="submitEmbeddedSignupToggle"
                                    class="rounded-md bg-primary px-3 py-2 text-sm text-white shadow-sm hover:shadow-md"
                                    :disabled="embeddedSignupForm.processing || !embeddedSignupGlobalEnabled || !embeddedSignupPlanEnabled"
                                >
                                    <span>{{ $t('Save') }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="!settings?.whatsapp" class="ui-card py-2 text-sm mb-4">
                        <div class="flex items-center px-4 pt-2 pb-4">
                            <div class="w-[70%]">
                                <h2 class="text-[17px]">{{ $t('Setup Whatsapp Account') }}</h2>
                                <span class="flex items-center mt-1">
                                    {{ $t('Setup your integration to be able to receive and send messages via Whatsapp.') }}
                                </span>
                            </div>
                            <div class="ms-auto">
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
                                    class="bg-primary text-white p-2 rounded-lg text-sm mt-5 flex px-3 w-fit"
                                >
                                    {{ $t('Setup whatsapp') }}
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><g fill="currentColor" fill-rule="evenodd" clip-rule="evenodd"><g opacity=".2"><path d="M12.206 5.848a1.5 1.5 0 0 1 2.113.192l3.333 4a1.5 1.5 0 1 1-2.304 1.92l-3.334-4a1.5 1.5 0 0 1 .192-2.112Z"/><path d="M12.206 16.152a1.5 1.5 0 0 1-.192-2.112l3.334-4a1.5 1.5 0 0 1 2.304 1.92l-3.333 4a1.5 1.5 0 0 1-2.113.192Z"/><path d="M16 11a1.5 1.5 0 0 1-1.5 1.5h-8a1.5 1.5 0 0 1 0-3h8A1.5 1.5 0 0 1 16 11Z"/></g><path d="M11.347 5.616a.5.5 0 0 1 .704.064l3.333 4a.5.5 0 0 1-.768.64l-3.333-4a.5.5 0 0 1 .064-.704Z"/><path d="M11.347 14.384a.5.5 0 0 1-.064-.704l3.333-4a.5.5 0 0 1 .768.64l-3.333 4a.5.5 0 0 1-.704.064Z"/><path d="M15.5 10a.5.5 0 0 1-.5.5H5a.5.5 0 0 1 0-1h20a.5.5 0 0 1 .5.5Z"/></g></svg>
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    disabled
                                    class="bg-slate-300 text-white p-2 rounded-lg text-sm mt-5 flex px-3 w-fit cursor-not-allowed"
                                >
                                    {{ $t('Setup whatsapp') }}
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><g fill="currentColor" fill-rule="evenodd" clip-rule="evenodd"><g opacity=".2"><path d="M12.206 5.848a1.5 1.5 0 0 1 2.113.192l3.333 4a1.5 1.5 0 1 1-2.304 1.92l-3.334-4a1.5 1.5 0 0 1 .192-2.112Z"/><path d="M12.206 16.152a1.5 1.5 0 0 1-.192-2.112l3.334-4a1.5 1.5 0 0 1 2.304 1.92l-3.333 4a1.5 1.5 0 0 1-2.113.192Z"/><path d="M16 11a1.5 1.5 0 0 1-1.5 1.5h-8a1.5 1.5 0 0 1 0-3h8A1.5 1.5 0 0 1 16 11Z"/></g><path d="M11.347 5.616a.5.5 0 0 1 .704.064l3.333 4a.5.5 0 0 1-.768.64l-3.333-4a.5.5 0 0 1 .064-.704Z"/><path d="M11.347 14.384a.5.5 0 0 1-.064-.704l3.333-4a.5.5 0 0 1 .768.64l-3.333 4a.5.5 0 0 1-.704.064Z"/><path d="M15.5 10a.5.5 0 0 1-.5.5H5a.5.5 0 0 1 0-1h20a.5.5 0 0 1 .5.5Z"/></g></svg>
                                </button>
                            </div>
                        </div>
                        <div
                            v-if="manualFallbackActive && props.manualFallbackReason === 'global_disabled'"
                            class="px-4 pb-2 text-xs text-red-600"
                        >
                            {{ $t('Embedded signup is currently disabled by the administrator. Manual setup is available.') }}
                        </div>
                        <div
                            v-else-if="manualFallbackActive && props.manualFallbackReason === 'plan_disabled'"
                            class="px-4 pb-2 text-xs text-amber-700"
                        >
                            {{ $t('Embedded signup is not available for your current plan. Manual setup is available.') }}
                        </div>
                        <div
                            v-else-if="manualFallbackActive && props.manualFallbackReason === 'org_disabled'"
                            class="px-4 pb-2 text-xs text-amber-700"
                        >
                            {{ $t('Embedded signup is disabled for your organization. Manual setup is available.') }}
                        </div>
                        <div
                            v-else-if="embeddedSignupActive && !embeddedSetupAvailable"
                            class="px-4 pb-3 text-xs text-amber-700"
                        >
                            {{ $t('Embedded signup is not configured correctly. Contact the administrator.') }}
                        </div>
                    </div>

                    <div v-if="settings?.whatsapp" class="ui-card py-2 text-sm mb-4">
                        <div class="grid grid-cols-4 items-center px-4 gap-x-4 py-2 border-b relative">
                            <div class="ui-border-end ui-border-end-1 border-slate-200 pe-3">
                                <div>{{ $t('Display name') }}</div>
                                <div>{{ settings.whatsapp?.verified_name }}</div>
                            </div>
                            <div class="ui-border-end ui-border-end-1 border-slate-200 pe-3">
                                <div>{{ $t('Connected number') }}</div>
                                <div>{{ settings.whatsapp?.display_phone_number }}</div>
                            </div>
                            <div class="ui-border-end ui-border-end-1 border-slate-200 pe-3">
                                <div>{{ $t('Message limits') }}</div>
                                <div>{{ settings.whatsapp?.messaging_limit_tier ? settings.whatsapp?.messaging_limit_tier : 'N/A' }}</div>
                            </div>
                            <div>
                                <div>{{ $t('Number status') }}</div>
                                <div class="bg-slate-50 py-1 px-2 rounded-md w-[fit-content] text-xs">{{ formatWhatsappStatus(settings.whatsapp?.number_status) }}</div>
                            </div>
                            <button type="button" v-if="refreshLoading === false" @click="refreshData()" class="flex items-center absolute end-0 top-0 text-xs me-1 gap-x-2 p-1 px-2 bg-slate-50 rounded-xl">
                                <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="currentColor" d="M12.079 2.25c-4.794 0-8.734 3.663-9.118 8.333H2a.75.75 0 0 0-.528 1.283l1.68 1.666a.75.75 0 0 0 1.056 0l1.68-1.666a.75.75 0 0 0-.528-1.283h-.893c.38-3.831 3.638-6.833 7.612-6.833a7.658 7.658 0 0 1 6.537 3.643a.75.75 0 1 0 1.277-.786A9.158 9.158 0 0 0 12.08 2.25m8.761 8.217a.75.75 0 0 0-1.054 0L18.1 12.133a.75.75 0 0 0 .527 1.284h.899c-.382 3.83-3.651 6.833-7.644 6.833a7.697 7.697 0 0 1-6.565-3.644a.75.75 0 1 0-1.277.788a9.197 9.197 0 0 0 7.842 4.356c4.808 0 8.765-3.66 9.15-8.333H22a.75.75 0 0 0 .527-1.284z"/></svg>
                                <span>{{ $t('Refresh') }}</span>
                            </button>
                            <button type="submit" v-else class="flex items-center absolute end-0 top-0 text-xs me-1 gap-x-2 p-1 px-2 bg-slate-50 rounded-xl">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                            </button>
                        </div>
                        <div class="grid grid-cols-4 items-center px-4 gap-x-4 py-2">
                            <div class="ui-border-end ui-border-end-1 border-slate-200 pe-3">
                                <div>{{ $t('Whatsapp business ac ID') }}</div>
                                <div>{{ settings.whatsapp?.waba_id }}</div>
                            </div>
                            <div v-if="settings.whatsapp?.is_embedded_signup == 1" class="ui-border-end ui-border-end-1 border-slate-200 pe-3">
                                <div>{{ $t('Phone verification status') }}</div>
                                <div class="bg-slate-50 py-1 px-2 rounded-md w-[fit-content] text-xs">{{ formatWhatsappStatus(settings.whatsapp?.code_verification_status) }}</div>
                            </div>
                            <div class="ui-border-end ui-border-end-1 border-slate-200 pe-3">
                                <div>{{ $t('Quality rating') }}</div>
                                <div class="bg-slate-50 py-1 px-2 rounded-md w-[fit-content] text-xs">{{ formatWhatsappStatus(settings.whatsapp?.quality_rating) }}</div>
                            </div>
                            <div>
                                <div>{{ $t('Account status') }}</div>
                                <div class="bg-slate-50 py-1 px-2 rounded-md w-[fit-content] text-xs">{{ formatWhatsappStatus(settings.whatsapp?.account_review_status) }}</div>
                            </div>
                        </div>
                    </div>

                    <div v-if="settings?.whatsapp" class="ui-card py-2 text-sm mb-4">
                        <div class="px-4 pt-2 pb-4">
                            <h2 class="text-[17px]">{{ $t('Inbound webhook readiness') }}</h2>
                            <span class="flex items-center mt-1">
                                {{ props.whatsappWebhookReadiness?.message }}
                            </span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 px-4 pb-4">
                            <div class="bg-slate-50 rounded-md p-3">
                                <div class="text-xs uppercase tracking-wide text-slate-500">{{ $t('Mode') }}</div>
                                <div class="mt-1 font-medium">
                                    {{ props.whatsappWebhookReadiness?.mode === 'embedded_signup' ? $t('Embedded signup') : $t('Manual') }}
                                </div>
                            </div>
                            <div class="bg-slate-50 rounded-md p-3">
                                <div class="text-xs uppercase tracking-wide text-slate-500">{{ $t('Signature status') }}</div>
                                <div class="mt-1 font-medium">
                                    {{ props.whatsappWebhookReadiness?.secretConfigured ? $t('Configured') : $t('Missing secret') }}
                                </div>
                            </div>
                            <div class="bg-slate-50 rounded-md p-3">
                                <div class="text-xs uppercase tracking-wide text-slate-500">{{ $t('Inbound delivery') }}</div>
                                <div class="mt-1 font-medium">
                                    {{ props.whatsappWebhookReadiness?.canReceiveInbound ? $t('Ready') : $t('Blocked') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <form @submit.prevent="submitForm2()" v-if="settings?.whatsapp" class="ui-card py-2 text-sm mb-4 pb-4">
                        <div class="flex items-center justify-between px-4 pt-2 pb-4">
                            <div>
                                <h2 class="text-[17px]">{{ $t('Business profile settings') }}</h2>
                                <span class="flex items-center mt-1">
                                    <svg class="me-2" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                                    {{ $t('Setup the Whatsapp business profile for your number') }}
                                </span>
                            </div>
                            <div>
                                <button v-if="showManualSettingsUi" type="button" @click="openModal2()" class="bg-primary text-white p-2 rounded-lg text-sm mt-5 flex px-3 w-fit">
                                    {{ $t('Update token') }}
                                </button>
                            </div>
                        </div>
                        <div class="flex gap-x-10 border-b w-full px-4 py-6">
                            <div class="w-[40%]">
                                <span class="text-slate-600">{{ $t('Whatsapp profile picture') }}</span>
                                <div class="text-xs text-slate-700 flex items-center">
                                    <svg class="me-1" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                                    <span>{{ $t('Add/update your profile picture') }}</span>
                                </div>
                            </div>
                            <div class="w-[60%]">
                                <FormImageLogo v-model="form2.profile_picture_url" :name="''" :error="form2.errors.profile_picture_url" :label="$t('Upload logo')" :imageUrl="form2.profile_picture_url" :class="'col-span-4 ms-6'"/>
                                <div class="ms-6">{{ $t('Accepted formats: JPG/PNG') }}</div>
                                <div class="ms-6">{{ $t('Minimum dimensions: 192x192 pixels.') }}</div>
                            </div>
                        </div>
                        <div class="flex gap-x-10 border-b w-full px-4 py-6">
                            <div class="w-[40%]">
                                <span class="text-slate-600">{{ $t('Business address') }}</span>
                                <div class="text-xs text-slate-700 flex items-center">
                                    <svg class="me-1" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                                    <span>{{ $t('Specify your physical business address') }}</span>
                                </div>
                            </div>
                            <div class="w-[60%]">
                                <FormInput v-model="form2.address" :error="form2.errors.address" :name="''" :type="'text'" :class="'col-span-4'"/>
                            </div>
                        </div>
                        <div class="flex gap-x-10 border-b w-full px-4 py-6">
                            <div class="w-[40%]">
                                <span class="text-slate-600">{{ $t('Business email') }}</span>
                                <div class="text-xs text-slate-700 flex items-center">
                                    <svg class="me-1" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                                    <span>{{ $t('Add your business email address') }}</span>
                                </div>
                            </div>
                            <div class="w-[60%]">
                                <FormInput v-model="form2.email" :error="form2.errors.email" :name="''" :type="'email'" :class="'col-span-4'"/>
                            </div>
                        </div>
                        <div class="flex gap-x-10 border-b w-full px-4 py-6">
                            <div class="w-[40%]">
                                <span class="text-slate-600">{{ $t('Business description') }}</span>
                                <div class="text-xs text-slate-700 flex items-center">
                                    <svg class="me-1" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                                    <span>{{ $t('Edit your whatsapp business account description') }}</span>
                                </div>
                            </div>
                            <div class="w-[60%]">
                                <FormTextArea v-model="form2.description" :error="form2.errors.description" :name="''" :type="'text'" :class="'col-span-4'"/>
                            </div>
                        </div>
                        <div class="flex gap-x-10 w-full px-4 py-6">
                            <div class="w-[40%]">
                                <span class="text-slate-600">{{ $t('Business industry') }}</span>
                                <div class="text-xs text-slate-700 flex items-center">
                                    <svg class="me-1" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                                    <span>{{ $t('Specify your business vertical') }}</span>
                                </div>
                            </div>
                            <div class="w-[60%]">
                                <FormSelect v-model="form2.industry" :name="''" :type="'text'"  :options="options" :error="form2.errors.industry" :class="'col-span-4'"/>
                            </div>
                        </div>
                        <div class="flex px-4 pt-1 pb-2">
                            <div class="ms-auto">
                                <button type="submit" class="rounded-md bg-primary px-3 py-2 text-sm text-white shadow-sm hover:shadow-md hover:bg-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600" :disabled="form2.processing">
                                    <svg v-if="form2.processing" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                                    <span v-else>{{ $t('Save') }}</span>
                                </button>
                            </div>
                        </div>
                    </form>

                    <div v-if="settings?.whatsapp && showManualSettingsUi" class="ui-card py-2 text-sm mb-4">
                        <div class="px-4 pt-2 pb-4">
                            <h2 class="text-[17px]">{{ $t('WhatsApp webhook settings') }}</h2>
                            <span class="flex items-center mt-1">
                                <svg class="me-2" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                                {{ $t('Add these webhook settings to your facebook developer account') }}
                            </span>
                        </div>
                        <div class="flex gap-x-10 border-b w-full px-4 py-4">
                            <div class="w-[40%]">
                                <span class="text-slate-600">{{ $t('Webhook url') }}</span>
                            </div>
                            <div class="text-start w-[60%]">
                                <span class="w-48 break-all">{{ currentURL + '/webhook/whatsapp/' +props.settings.identifier }}</span>
                            </div>
                        </div>
                        <div class="flex gap-x-10 w-full px-4 py-4">
                            <div class="w-[40%]">
                                <span class="text-slate-600">{{ $t('Verify token') }}</span>
                            </div>
                            <div class="text-start w-[60%]">
                                <div class="text-start">{{ props.settings.identifier }}</div>
                            </div>
                        </div>
                    </div>

                    <div v-if="settings?.whatsapp" class="ui-card py-2 text-sm mb-20">
                        <div class="flex items-center px-4 pt-2 pb-4">
                            <div class="w-[60%]">
                                <h2 class="text-[17px]">{{ $t('Remove Whatsapp account') }}</h2>
                                <span class="flex items-center mt-1">
                                    {{ $t('This will completely delete your whatsapp integration. Your contacts & messages will be unaffected.') }}
                                </span>
                            </div>
                            <div class="w-[40%] ms-auto">
                                <button type="button" @click="deleteIntegration()" class="rounded-md bg-red-700 px-3 py-2 text-sm text-white shadow-sm hover:bg-red-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">{{ $t('Delete integration')}}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Modal :label="$t('Whatsapp API config')" :isOpen="isOpenFormModal" @close="isOpenFormModal = false">
            <div class="mt-5 grid grid-cols-1 gap-x-6 gap-y-4">
                <form @submit.prevent="submitForm()" class="grid gap-x-6 gap-y-4 sm:grid-cols-6">

                    <FormInput v-model="form.app_id" :error="form.errors.app_id" :name="$t('App ID')" :type="'text'" :class="'sm:col-span-6'"/>
                    <FormInput v-model="form.access_token" :error="form.errors.access_token" :name="$t('Access token')" :type="'text'" :class="'sm:col-span-6'"/>
                    <FormInput v-model="form.app_secret" :error="form.errors.app_secret" :name="$t('App secret')" :type="'password'" :class="'sm:col-span-6'"/>
                    <div v-if="props.hasEncryptedWhatsappAppSecret" class="sm:col-span-6 text-xs text-slate-500">
                        {{ $t('A saved app secret already exists. Leave this field as-is only if you are replacing the current secret.') }}
                    </div>
                    <FormInput v-model="form.phone_number_id" :error="form.errors.phone_number_id" :name="$t('Phone number ID')" :type="'text'" :class="'sm:col-span-6'"/>
                    <FormInput v-model="form.waba_id" :error="form.errors.waba_id" :name="$t('Whatsapp business account ID')" :type="'text'" :class="'sm:col-span-6'"/>

                    <div class="mt-4 flex">
                        <button type="button" @click="isOpenFormModal = false" class="inline-flex justify-center rounded-md border border-transparent bg-slate-50 px-4 py-2 text-sm text-slate-500 hover:bg-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 me-4">{{ $t('Cancel') }}</button>
                        <button type="submit" 
                            :class="['inline-flex justify-center rounded-md border border-transparent bg-primary px-4 py-2 text-sm text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2', { 'opacity-50': form.processing }]"
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
                    <div v-if="props.hasEncryptedWhatsappAppSecret" class="sm:col-span-6 text-xs text-slate-500">
                        {{ $t('Leave the app secret empty to keep the current saved value.') }}
                    </div>

                    <div class="mt-4 flex">
                        <button type="button" @click="isOpenForm2Modal = false" class="inline-flex justify-center rounded-md border border-transparent bg-slate-50 px-4 py-2 text-sm text-slate-500 hover:bg-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 me-4">{{ $t('Cancel') }}</button>
                        <button type="submit" 
                            :class="['inline-flex justify-center rounded-md border border-transparent bg-primary px-4 py-2 text-sm text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2', { 'opacity-50': form.processing }]"
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




