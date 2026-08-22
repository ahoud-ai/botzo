<template>
    <SettingLayout :modules="props.modules">
        <div class="min-h-0">
            <div class="mb-8 flex justify-center">
                <form @submit.prevent="submitForm2()" class="w-full max-w-5xl">
                    <div
                        v-if="companyProfileManagedByParent"
                        class="mb-4 rounded-lg border border-sky-200 dark:border-sky-500/20 bg-sky-50 dark:bg-sky-500/10 px-3 py-2 text-sm text-sky-900 dark:text-sky-300"
                    >
                        {{ $t('This branch is managed from the parent organization.') }}
                        <span v-if="props.organizationProfileContext?.billingOwner?.name">
                            {{ $t('Parent organization') }}: {{ props.organizationProfileContext.billingOwner.name }}
                        </span>
                    </div>
                    <div
                        v-else-if="!canManageWorkspaceIdentity"
                        class="mb-4 rounded-xl border border-amber-200 dark:border-amber-500/20 bg-amber-50 dark:bg-amber-500/10 px-4 py-3 text-sm text-amber-950 dark:text-amber-200"
                    >
                        <div class="font-semibold">{{ $t('Workspace identity is owner-managed') }}</div>
                        <p class="mt-1">{{ $t('Only workspace owners can rename this workspace or update its postal address.') }}</p>
                        <p class="mt-1 text-amber-800 dark:text-amber-300">{{ $t('Operational settings such as timezone and retry rules can still be managed here if your role allows it.') }}</p>
                    </div>

                    <UiSectionCard :title="organizationDetailsHeading" :subtitle="organizationDetailsHelperText" class="mb-4">
                        <div class="settings-row">
                            <div class="settings-row-label">
                                <span class="settings-field-label">{{ organizationNameLabel }}</span>
                                <div class="settings-field-hint">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                                    <span>{{ organizationNameHint }}</span>
                                </div>
                            </div>
                            <div class="settings-row-control">
                                <FormInput v-model="form2.organization_name" :error="form2.errors.organization_name" :name="''" :type="'text'" :class="'col-span-4'" :disabled="!canManageWorkspaceIdentity"/>
                            </div>
                        </div>
                        <div class="settings-row">
                            <div class="settings-row-label">
                                <span class="settings-field-label">{{ addressDetailsLabel }}</span>
                                <div class="settings-field-hint">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                                    <span>{{ addressDetailsHint }}</span>
                                </div>
                            </div>
                            <div class="settings-row-control">
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                                    <FormInput v-model="form2.address" :error="form2.errors.address" :name="$t('Physical address')" :type="'text'" :class="'col-span-4'" :disabled="!canManageWorkspaceIdentity"/>
                                    <FormInput v-model="form2.city" :error="form2.errors.city" :name="$t('City')" :type="'text'" :class="'col-span-1 md:col-span-2'" :disabled="!canManageWorkspaceIdentity"/>
                                    <FormInput v-model="form2.state" :error="form2.errors.state" :name="$t('State')" :type="'text'" :class="'col-span-1 md:col-span-2'" :disabled="!canManageWorkspaceIdentity"/>
                                    <FormInput v-model="form2.zip" :error="form2.errors.zip" :name="$t('Zip code')" :type="'text'" :class="'col-span-1 md:col-span-2'" :disabled="!canManageWorkspaceIdentity"/>
                                    <FormSelect v-model="form2.country" :name="$t('Country')" :type="'text'" :optionClassName="'h-40'" :options="localizedCountries" :error="form2.errors.country" :class="'col-span-1 md:col-span-2'" :disabled="!canManageWorkspaceIdentity"/>
                                </div>
                            </div>
                        </div>
                    </UiSectionCard>

                    <UiSectionCard :title="$t('Timezone')" class="mb-4">
                        <div class="settings-row settings-row--align-center">
                            <div class="settings-row-label">
                                <span class="settings-field-hint">{{ $t('All your data will be processed according to this timezone') }}</span>
                            </div>
                            <div class="settings-row-control settings-row-control--narrow">
                                <FormSelect v-model="form2.timezone" :name="''" :type="'text'"  :options="localizedTimezones" :error="form2.errors.timezone" :class="'col-span-2'"/>
                            </div>
                        </div>
                    </UiSectionCard>

                    <UiSectionCard :title="$t('Failed Message Retry Settings (Campaigns)')" :subtitle="$t('Configure the intervals at which failed campaign messages will be retried.')" class="mb-4">
                        <div class="settings-row settings-row--align-center">
                            <div class="settings-row-label settings-row-label--wide">
                                <span class="settings-field-label">{{ $t('Enable campaign resend feature') }}</span>
                                <div class="settings-field-hint">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                                    <span>{{ $t('If active, failed messages in campaigns will be retried according to your set intervals') }}</span>
                                </div>
                            </div>
                            <div class="settings-row-control settings-row-control--end">
                                <FormToggleSwitch v-model="form2.enable_campaign_resend" />
                            </div>
                        </div>
                        <div v-if="form2.enable_campaign_resend === true" class="settings-row settings-row--align-center">
                            <div class="settings-row-label">
                                <span class="settings-field-label">{{ $t('Retry Intervals (in hours)') }}</span>
                                <div class="settings-field-hint">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                                    <span>{{ $t('Set retry intervals in hours') }}</span>
                                </div>
                            </div>
                            <div class="settings-row-control">
                                <div class="flex flex-wrap gap-2">
                                    <FormInput v-for="(interval, index) in form2.resend_intervals" v-model.number="form2.resend_intervals[index]" :error="''" :name="''" :type="'number'" :min="'1'" :class="'w-24'"/>
                                </div>
                            </div>
                        </div>
                        <div v-if="form2.enable_campaign_resend === true" class="settings-row settings-row--align-center">
                            <div class="settings-row-label settings-row-label--wide">
                                <span class="settings-field-label">{{ $t('Move failed contacts to a group') }}</span>
                                <div class="settings-field-hint">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                                    <span>{{ $t('If enabled, failed contacts will be moved to a selected group after all retry attempts.') }}</span>
                                </div>
                            </div>
                            <div class="settings-row-control settings-row-control--end">
                                <FormToggleSwitch v-model="form2.move_failed_contacts_to_group" />
                            </div>
                        </div>
                        <div v-if="form2.enable_campaign_resend === true && form2.move_failed_contacts_to_group === true" class="settings-row settings-row--align-center">
                            <div class="settings-row-label">
                                <span class="settings-field-label">{{ $t('Failed contacts groups') }}</span>
                                <div class="settings-field-hint">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                                    <span>{{ $t('After the three retry attempts, contacts will be moved to this group.') }}</span>
                                </div>
                            </div>
                            <div class="settings-row-control settings-row-control--narrow">
                                <FormSelect v-model="form2.failed_campaign_group" :name="''" :error="form2.errors.failed_campaign_group" :options="contactGroupOptions()" :type="'text'" :class="'sm:col-span-6'"/>
                            </div>
                        </div>
                    </UiSectionCard>

                    <div class="mb-20 flex justify-end">
                        <button type="submit" class="settings-save-btn" :disabled="form2.processing">
                            <svg v-if="form2.processing" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                            <span v-else>{{ $t('Save') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </SettingLayout>
</template>
<script setup>
    import SettingLayout from "./Layout.vue";
    import { computed, ref } from 'vue';
    import EmbeddedSignupBtn from '@/Components/EmbeddedSignupBtn.vue';
    import FormModal from '@/Components/FormModal.vue';
    import FormInput from '@/Components/FormInput.vue';
    import FormSelect from '@/Components/FormSelect.vue';
    import FormTextArea from '@/Components/FormTextArea.vue';
    import FormToggleSwitch from '@/Components/FormToggleSwitch.vue';
    import Modal from '@/Components/Modal.vue';
    import UiSectionCard from '@/Components/UI/UiSectionCard.vue';
    import { useWorkspaceAccess } from '@/Composables/useWorkspaceAccess';
    import { localizeCountryOptions, localizeTimezoneOptions } from '@/Utils/optionLocalizers';
    import { useI18n } from 'vue-i18n';
    import { router, useForm } from "@inertiajs/vue3";
    const { locale, t } = useI18n();
    const { isWorkspaceOwner, companyProfileManagedByParent } = useWorkspaceAccess();

    const props = defineProps(['contactGroups', 'settings', 'timezones', 'modules', 'organization', 'countries', 'organizationProfileContext']);
    const statusView = ref(false);
    const config = ref(props.settings.metadata);
    const settings = ref(config.value ? JSON.parse(config.value) : null);
    const localizedCountries = computed(() => localizeCountryOptions(props.countries ?? [], locale.value));
    const localizedTimezones = computed(() => localizeTimezoneOptions(props.timezones ?? [], locale.value));
    const canManageWorkspaceIdentity = computed(() => isWorkspaceOwner.value && !companyProfileManagedByParent.value);
    const organizationDetailsHeading = computed(() => companyProfileManagedByParent.value ? t('Company details') : t('Organization details'));
    const organizationDetailsHelperText = computed(() => companyProfileManagedByParent.value
        ? t('This branch inherits the company profile from the parent organization.')
        : t('Update your organization settings'));
    const organizationNameLabel = computed(() => companyProfileManagedByParent.value ? t('Company name') : t('Organization name'));
    const organizationNameHint = computed(() => companyProfileManagedByParent.value
        ? t('Inherited legal company name from the parent organization')
        : t('Specify the name of your business/organization'));
    const addressDetailsLabel = computed(() => companyProfileManagedByParent.value ? t('Company address') : t('Address details'));
    const addressDetailsHint = computed(() => companyProfileManagedByParent.value
        ? t('Inherited legal mailing address from the parent organization')
        : t('Specify your physical business address'));

    const contactGroupOptions = () => {
        return props.contactGroups.map((option) => ({
            value: option.uuid,
            label: option.name,
        }));
    };

    const normalizeAddress = (value) => {
        if (!value) {
            return {};
        }

        if (typeof value === 'string') {
            try {
                const address = JSON.parse(value);
                return typeof address === 'object' && address !== null ? address : {};
            } catch (error) {
                return {};
            }
        }

        return typeof value === 'object' && value !== null ? value : {};
    };

    const allowedTimezoneValues = (props.timezones ?? []).map((option) => option.value);
    const fallbackTimezone = allowedTimezoneValues[0] ?? 'Asia/Riyadh';
    const savedTimezone = settings.value && settings.value.timezone ? settings.value.timezone : null;
    const initialTimezone = savedTimezone && allowedTimezoneValues.includes(savedTimezone) ? savedTimezone : fallbackTimezone;
    const companyIdentityProfile = companyProfileManagedByParent.value
        ? (props.organizationProfileContext?.companyProfile ?? null)
        : {
            name: props.settings?.name ?? null,
            address: normalizeAddress(props.settings?.address),
        };
    const initialCompanyAddress = normalizeAddress(companyIdentityProfile?.address);

    const form2 = useForm({
        organization_name: companyIdentityProfile?.name ?? props.settings?.name ?? null,
        address: initialCompanyAddress?.street ?? null,
        city: initialCompanyAddress?.city ?? null,
        state: initialCompanyAddress?.state ?? null,
        zip: initialCompanyAddress?.zip ?? null,
        country: initialCompanyAddress?.country ?? null,
        timezone: initialTimezone,
        enable_campaign_resend: settings.value && settings.value.campaigns?.enable_resend ? settings.value.campaigns?.enable_resend : false,
        move_failed_contacts_to_group: settings.value && settings.value?.campaigns?.move_failed_contacts_to_group ? settings.value?.campaigns?.move_failed_contacts_to_group : false,
        resend_intervals: settings?.value?.campaigns?.resend_intervals ?? [1, 3, 6],
        failed_campaign_group: settings.value && settings.value?.campaigns?.failed_campaign_group ? settings.value.campaigns?.failed_campaign_group : null,
    });

    const capitalizeString = (string) => {
        return string.charAt(0).toUpperCase() + string.slice(1).toLowerCase();
    };

    const submitForm = () => {
        form.post('/settings/whatsapp', {
            preserveScroll: true,
            preserveState: false,
        })
    }

    const submitForm2 = () => {
        form2.put('./profile/organization', {
            preserveScroll: true
        });
    }
</script>

<style scoped>
.settings-row {
    display: flex;
    flex-direction: column;
    gap: 1.1rem;
    padding: 1.4rem;
    border-top: 1px solid var(--ui-border);
}

.settings-row:first-child {
    border-top: none;
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

.settings-row-label {
    min-width: 0;
}

.settings-row-control--end {
    display: flex;
    justify-content: flex-start;
}

.settings-save-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.75rem;
    padding: 0.65rem 1.5rem;
    font-size: 0.9rem;
    font-weight: 700;
    color: #fff;
    background: var(--ui-secondary);
    transition: filter 160ms ease;
}

.settings-save-btn:hover:not(:disabled) {
    filter: brightness(1.05);
}

.settings-save-btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

@media (min-width: 768px) {
    .settings-row {
        flex-direction: row;
    }

    .settings-row--align-center {
        align-items: center;
    }

    .settings-row-label {
        width: 40%;
        flex-shrink: 0;
    }

    .settings-row-label--wide {
        width: 70%;
    }

    .settings-row-control {
        width: 60%;
        margin-inline-start: auto;
    }

    .settings-row-control--narrow {
        width: 40%;
    }

    .settings-row-control--end {
        width: 30%;
        justify-content: flex-end;
    }
}
</style>
