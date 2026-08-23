<script setup>
    import { ref } from 'vue';
    import { Link, useForm } from "@inertiajs/vue3";
    import FormCheckbox from '@/Components/FormCheckbox.vue';
    import FormInput from '@/Components/FormInput.vue';
    import FormPhoneInput from '@/Components/FormPhoneInput.vue';
    import FormSelect from '@/Components/FormSelect.vue';
    import FormTextArea from '@/Components/FormTextArea.vue';
    import UiFormSection from '@/Components/UI/UiFormSection.vue';
    import { useI18n } from 'vue-i18n';
    const { t } = useI18n();

    const props = defineProps(['contactGroups', 'contact', 'fields', 'locationSettings']);
    const fileUrl = props.contact?.avatar ? ref(props.contact?.avatar) : ref(null);
    const results = ref();
    const inputFields = props.fields;

    const defaultFields = inputFields.reduce((acc, field) => {
        acc[field.name] = "";
        return acc;
    }, {});

    const getAddressDetail = (value, key) => {
        const address = JSON.parse(value);
        return address?.[key] ?? '';
    }

    const form = useForm({
        first_name: props.contact?.first_name ?? null,
        last_name: props.contact?.last_name ?? null,
        phone: props.contact?.phone ?? null,
        email: props.contact?.email ?? null,
        group: props.contact?.contact_groups ? props.contact.contact_groups.map(group => group.uuid || group) : [],
        file: null,
        street: props.contact?.address ? getAddressDetail(props.contact?.address, 'street') : null,
        city: props.contact?.address ? getAddressDetail(props.contact?.address, 'city') : null,
        state: props.contact?.address ? getAddressDetail(props.contact?.address, 'state') : null,
        zip: props.contact?.address ? getAddressDetail(props.contact?.address, 'zip') : null,
        country: props.contact?.address ? getAddressDetail(props.contact?.address, 'country') : null,
        metadata: props.contact?.metadata ? JSON.parse(props.contact?.metadata) : defaultFields,
    });

    const contactGroupOptions = () => {
        return props.contactGroups.map((option) => ({
            value: option.uuid,
            label: option.name,
        }));
    };

    const handleFileUpload = (event) => {
        const fileSizeLimit = 5 * 1024 * 1024; ///5MB
        const file = event.target.files[0];

        if (file && file.size > fileSizeLimit) {
            // Handle file size exceeding the limit
            alert(t('file size exceeds the limit. Max allowed size:') + ' ' + fileSizeLimit + 'b');
            // Clear the file input
            event.target.value = null;
        } else {
            const reader = new FileReader();

            reader.onload = (e) => {
                fileUrl.value = e.target.result;
            };

            form.file = file;

            // Start reading the file
            reader.readAsDataURL(file);
        }
    }

    const submitForm = () => {
        if(!props.contact){
            form.post('/contacts');
        } else {
            form.post('/contacts/' + props.contact.uuid);
        }
    }

    const transformOptions = (optionsString) => {
        return optionsString.split(", ").map(option => ({ label: option, value: option }));
    };
</script>
<template>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-3xl border border-slate-200 dark:border-white/10 bg-white/90 dark:bg-white/5 px-5 py-4 shadow-sm md:mb-8 md:px-8">
        <div>
            <h1 v-if="!props.contact" class="text-xl font-semibold text-slate-900 dark:text-white">{{ $t('Add contact') }}</h1>
            <h1 v-else class="text-xl font-semibold text-slate-900 dark:text-white">{{ $t('Edit contact') }}</h1>
        </div>
        <div class="flex flex-wrap gap-3">
            <Link v-if="!props.contact" href="/contacts" class="contact-form-btn contact-form-btn--ghost">{{ $t('Cancel') }}</Link>
            <Link v-else :href="'/contacts/' + props.contact.uuid" class="contact-form-btn contact-form-btn--ghost">{{ $t('Back') }}</Link>
        </div>
    </div>
    <div class="flex min-h-0 justify-center px-4 pb-8 md:px-8">
        <form @submit.prevent="submitForm()" class="w-full max-w-4xl">
            <div class="flex flex-col items-center gap-3 mb-6">
                <div class="rounded-full w-32 h-32 ring-4 ring-[var(--ui-surface-soft)]">
                    <svg v-if="fileUrl === null" class="text-gray-400 dark:text-slate-500" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M18.685 19.097A9.723 9.723 0 0021.75 12c0-5.385-4.365-9.75-9.75-9.75S2.25 6.615 2.25 12a9.723 9.723 0 003.065 7.097A9.716 9.716 0 0012 21.75a9.716 9.716 0 006.685-2.653zm-12.54-1.285A7.486 7.486 0 0112 15a7.486 7.486 0 015.855 2.812A8.224 8.224 0 0112 20.25a8.224 8.224 0 01-5.855-2.438zM15.75 9a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" clip-rule="evenodd" />
                    </svg>
                    <img v-else class="w-32 h-32 rounded-full object-cover" :src="fileUrl" :alt="$t('Contact Image')">
                </div>
                <input type="file" class="sr-only" :accept="'.jpg, .png'" id="file-upload" @change="handleFileUpload">
                <label for="file-upload" class="contact-form-btn contact-form-btn--ghost cursor-pointer">{{ $t('Upload image') }}</label>
            </div>

            <UiFormSection :title="$t('Basic information')" class="mb-4">
                <div class="grid gap-x-6 gap-y-4 sm:grid-cols-6">
                    <FormInput v-model="form.first_name" :name="$t('First name')" :error="form.errors.first_name" :type="'text'" :class="'sm:col-span-3'"/>
                    <FormInput v-model="form.last_name" :name="$t('Last name')" :error="form.errors.last_name" :type="'text'" :class="'sm:col-span-3'"/>
                    <FormInput v-model="form.email" :name="$t('Email')" :error="form.errors.email" :type="'text'" :class="'sm:col-span-6'"/>
                </div>
            </UiFormSection>

            <UiFormSection :title="$t('WhatsApp number')" class="mb-4">
                <FormPhoneInput v-model="form.phone" :name="$t('Phone')" :error="form.errors.phone" :type="'text'" :class="'w-full'"/>
            </UiFormSection>

            <UiFormSection :title="$t('Groups')" class="mb-4">
                <FormSelect v-model="form.group" :name="$t('Group')" :error="form.errors.group" :options="contactGroupOptions()" :type="'text'" :class="'w-full'" :multiple="true"/>
            </UiFormSection>

            <UiFormSection v-if="locationSettings === 'before' && props.fields.length > 0" :title="$t('Additional details')" class="mb-4">
                <div class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                    <div v-for="(input, index) in props.fields" :key="index" :class="input.type != 'input' ? 'sm:col-span-2' : 'sm:col-span-1'">
                        <FormInput v-if="input.type === 'input'" v-model="form.metadata[input.name]" :name="input.name" :label="$t(input.name)" :type="input.value" :class="'sm:col-span-2'" :required="input.required === 1 ? true : false"/>
                        <FormTextArea v-if="input.type === 'textarea'" v-model="form.metadata[input.name]" :name="input.name" :label="$t(input.name)" :class="'sm:col-span-2'" :required="input.required === 1 ? true : false"/>
                        <FormSelect v-if="input.type === 'select'" v-model="form.metadata[input.name]" :name="input.name" :options="transformOptions(input.value)" :class="'sm:col-span-2'" :required="input.required === 1 ? true : false"/>
                        <FormCheckbox v-if="input.type === 'checkbox'" v-model="form.metadata[input.name]" :name="input.name" :label="input.name" :class="'sm:col-span-2'" :required="input.required === 1 ? true : false"/>
                    </div>
                </div>
            </UiFormSection>

            <UiFormSection :title="$t('Address')" class="mb-4">
                <div class="grid gap-x-6 gap-y-4 sm:grid-cols-6">
                    <FormInput v-model="form.street" :name="$t('Street')" :error="form.errors.street" :type="'text'" :class="'sm:col-span-6'"/>
                    <FormInput v-model="form.city" :name="$t('City')" :error="form.errors.city" :type="'text'" :class="'sm:col-span-3'"/>
                    <FormInput v-model="form.state" :name="$t('State')" :error="form.errors.state" :type="'text'" :class="'sm:col-span-3'"/>
                    <FormInput v-model="form.zip" :name="$t('Zip code')" :error="form.errors.zip" :type="'text'" :class="'sm:col-span-3'"/>
                    <FormInput v-model="form.country" :name="$t('Country')" :error="form.errors.country" :type="'text'" :class="'sm:col-span-3'"/>
                </div>
            </UiFormSection>

            <UiFormSection v-if="locationSettings === 'after' && props.fields.length > 0" :title="$t('Additional details')" class="mb-4">
                <div class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                    <div v-for="(input, index) in props.fields" :key="index" :class="input.type != 'input' ? 'sm:col-span-2' : 'sm:col-span-1'">
                        <FormInput v-if="input.type === 'input'" v-model="form.metadata[input.name]" :name="input.name" :label="$t(input.name)" :type="input.value" :class="'sm:col-span-2'" :required="input.required === 1 ? true : false"/>
                        <FormTextArea v-if="input.type === 'textarea'" v-model="form.metadata[input.name]" :name="input.name" :label="$t(input.name)" :class="'sm:col-span-2'" :required="input.required === 1 ? true : false"/>
                        <FormSelect v-if="input.type === 'select'" v-model="form.metadata[input.name]" :name="input.name" :options="transformOptions(input.value)" :class="'sm:col-span-2'" :required="input.required === 1 ? true : false"/>
                        <FormCheckbox v-if="input.type === 'checkbox'" v-model="form.metadata[input.name]" :name="input.name" :label="input.name" :class="'sm:col-span-2'" :required="input.required === 1 ? true : false"/>
                    </div>
                </div>
            </UiFormSection>

            <div class="mt-2 mb-10 flex">
                <Link href="/contacts" class="contact-form-btn contact-form-btn--ghost me-4">{{ $t('Cancel') }}</Link>
                <button type="submit" class="contact-form-btn contact-form-btn--solid">
                    <span>{{ $t('Save') }}</span>
                </button>
            </div>
        </form>
    </div>
</template>

<style scoped>
.contact-form-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.7rem;
    padding: 0.55rem 1.1rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: background-color 160ms ease, filter 160ms ease;
}

.contact-form-btn--ghost {
    color: var(--ui-text);
    background: var(--ui-surface-soft);
    border: 1px solid var(--ui-border);
}

.contact-form-btn--ghost:hover {
    background: var(--ui-border);
}

.contact-form-btn--solid {
    color: #fff;
    background: var(--ui-secondary);
    border: 1px solid var(--ui-secondary);
}

.contact-form-btn--solid:hover {
    filter: brightness(1.05);
}
</style>

