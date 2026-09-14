<template>
    <AppLayout>
        <UiPageHeader :title="$t('Mailer settings')" :subtitle="$t('Configure email accounts')" />

        <form @submit.prevent="submitForm()" class="mt-6 space-y-6">
            <UiSectionCard :title="$t('Provider')" :subtitle="$t('Choose how outgoing emails are sent')">
                <div class="grid gap-6 sm:grid-cols-2">
                    <FormSelect v-model="form.mail_config.driver" :name="$t('Method')" :type="'text'" :options="methods" :error="form.errors['mail_config.driver']" :class="'sm:col-span-2'"/>

                    <template v-if="form.mail_config.driver === 'mailgun'">
                        <FormInput v-model="form.mail_config.mg_domain" :error="form.errors['mail_config.mg_domain']" :name="$t('Mailgun domain')" :type="'text'"/>
                        <FormInput v-model="form.mail_config.mg_secret" :error="form.errors['mail_config.mg_secret']" :name="$t('Mailgun secret')" :type="'password'"/>
                    </template>

                    <template v-else-if="form.mail_config.driver === 'ses'">
                        <FormInput v-model="form.mail_config.ses_key" :error="form.errors['mail_config.ses_key']" :name="$t('AWS access key id')" :type="'text'"/>
                        <FormInput v-model="form.mail_config.ses_secret" :error="form.errors['mail_config.ses_secret']" :name="$t('AWS secret access key')" :type="'password'"/>
                        <FormInput v-model="form.mail_config.ses_region" :error="form.errors['mail_config.ses_region']" :name="$t('AWS default region')" :type="'text'" :class="'sm:col-span-2'"/>
                    </template>

                    <!-- Resend: API Key + from/reply only; SMTP .env keys are ignored when Resend is selected -->
                    <template v-else-if="form.mail_config.driver === 'resend'">
                        <FormInput v-model="form.mail_config.resend_api_key" :error="form.errors['mail_config.resend_api_key']" :name="$t('Resend API Key')" :type="'password'" :class="'sm:col-span-2'"/>
                    </template>

                    <!-- SMTP: when used, Resend API Key in .env is commented out/ignored -->
                    <template v-else-if="form.mail_config.driver === 'smtp'">
                        <FormInput v-model="form.mail_config.host" :error="form.errors['mail_config.host']" :name="$t('Host')" :type="'text'"/>
                        <FormInput v-model="form.mail_config.port" :error="form.errors['mail_config.port']" :name="$t('Port')" :type="'text'"/>
                        <FormInput v-model="form.mail_config.username" :error="form.errors['mail_config.username']" :name="$t('Username')" :type="'text'"/>
                        <FormInput v-model="form.mail_config.password" :error="form.errors['mail_config.password']" :name="$t('Password')" :type="'password'"/>
                    </template>
                </div>
            </UiSectionCard>

            <UiSectionCard :title="$t('Sender identity')" :subtitle="$t('Shown to recipients on every outgoing email')">
                <div class="grid gap-6 sm:grid-cols-2">
                    <FormInput v-model="form.mail_config.from_name" :name="$t('Mail from name')" :error="form.errors['mail_config.from_name']" :type="'text'"/>
                    <FormInput v-model="form.mail_config.from_address" :name="$t('Mail from address')" :error="form.errors['mail_config.from_address']" :type="'email'"/>
                    <FormInput v-model="form.mail_config.reply_to_name" :name="$t('Reply to name')" :error="form.errors['mail_config.reply_to_name']" :type="'text'"/>
                    <FormInput v-model="form.mail_config.reply_to_address" :name="$t('Reply to address')" :error="form.errors['mail_config.reply_to_address']" :type="'email'"/>

                    <div class="eml-toggle-row sm:col-span-2">
                        <span class="eml-toggle-label">{{ $t('Activate email') }}</span>
                        <FormToggleSwitch v-model="form.smtp_email_active"/>
                    </div>
                    <div class="eml-toggle-row sm:col-span-2">
                        <span class="eml-toggle-label">{{ $t('Require email verification for new accounts') }}</span>
                        <FormToggleSwitch v-model="form.verify_email"/>
                    </div>
                </div>
            </UiSectionCard>

            <div class="flex items-center justify-end">
                <button type="submit" class="eml-btn eml-btn--solid" :disabled="isLoading">
                    <svg v-if="isLoading" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                    <span v-else>{{ $t('Save') }}</span>
                </button>
            </div>
        </form>
    </AppLayout>
</template>
<script setup>
    import AppLayout from "./Layout/App.vue";
    import { ref } from 'vue';
    import { useForm } from "@inertiajs/vue3";
    import { useI18n } from 'vue-i18n';
    import FormInput from '@/Components/FormInput.vue';
    import FormSelect from '@/Components/FormSelect.vue';
    import FormToggleSwitch from '@/Components/FormToggleSwitch.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import UiSectionCard from '@/Components/UI/UiSectionCard.vue';
    const { t } = useI18n();

    const props = defineProps({
        config: {
            type: Object,
            required: true
        }
    });

    const getValueByKey = (key) => {
        const found = props.config.find(item => item.key === key);
        return found ? found.value : '';
    };

    const getMailboxSettings = (key) => {
        const mailConfig = JSON.parse(getValueByKey('mail_config'));

        // Check if mailConfig is not null, is an object, and not an array
        if (mailConfig !== null && typeof mailConfig === 'object' && !Array.isArray(mailConfig)) {
            return mailConfig[key] ?? null;
        }

        return null;
    }

    const isLoading = ref(false);
    const form = useForm({
        mail_config: {
            driver: getMailboxSettings('driver') ?? undefined,
            from_address: getMailboxSettings('from_address') ?? undefined,
            from_name: getMailboxSettings('from_name') ?? undefined,
            reply_to_name: getMailboxSettings('reply_to_name') ?? undefined,
            reply_to_address: getMailboxSettings('reply_to_address') ?? undefined,
            mg_domain: getMailboxSettings('mg_domain') ?? undefined,
            mg_secret: getMailboxSettings('mg_secret') ?? undefined,
            ses_key: getMailboxSettings('ses_key') ?? undefined,
            ses_secret: getMailboxSettings('ses_secret') ?? undefined,
            ses_region: getMailboxSettings('ses_region') ?? undefined,
            port: getMailboxSettings('port') ?? undefined,
            host: getMailboxSettings('host') ?? undefined,
            username: getMailboxSettings('username') ?? undefined,
            password: getMailboxSettings('password') ?? undefined,
            resend_api_key: getMailboxSettings('resend_api_key') ?? undefined,
        },
        smtp_email_active: getValueByKey('smtp_email_active') === '1' ? true : false,
        verify_email: getValueByKey('verify_email') === '1' ? true : false,
    })

    const methods = [
        { label: 'SMTP', value: 'smtp' },
        { label: 'Resend', value: 'resend' },
        { label: 'Mailgun', value: 'mailgun' },
        { label: t('Amazon SES'), value: 'ses' },
    ]

    const submitForm = async () => {
        form.put('/admin/settings?type=email', {
            preserveScroll: true,
        })
    };
</script>

<style scoped>
.eml-toggle-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    border-radius: 0.9rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    padding: 0.85rem 1.05rem;
}

.eml-toggle-label {
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--ui-text);
}

.eml-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.85rem;
    padding: 0.6rem 1.3rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: filter 160ms ease, opacity 160ms ease;
}

.eml-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.eml-btn--solid:hover:not(:disabled) {
    filter: brightness(1.05);
}

.eml-btn--solid:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}
</style>
