<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full">
            <UiPageHeader
                :title="props.user === null ? $t('Create user') : $t('Update user')"
                :subtitle="props.user === null ? $t('Create administrative user and assign role') : $t('Update administrative user and assign role')"
            >
                <template #actions>
                    <Link href="/admin/team/users" class="tm-btn tm-btn--solid">{{ $t('Back') }}</Link>
                </template>
            </UiPageHeader>

            <form @submit.prevent="submitForm()" class="mt-6 space-y-6">
                <UiSectionCard :title="$t('Personally identifiable information')">
                    <div class="grid gap-x-6 gap-y-4 sm:grid-cols-6">
                        <FormImage v-model="form.avatar" :name="'Avatar'" :error="form.errors.avatar" :label="$t('Upload image')" :imageUrl="props.user?.avatar ? '/media/' + props.user?.avatar : null" :class="'sm:col-span-6'"/>
                        <FormInput v-model="form.first_name" :name="$t('First name')" :error="form.errors.first_name" :type="'text'" :class="'sm:col-span-3'"/>
                        <FormInput v-model="form.last_name" :name="$t('Last name')" :error="form.errors.last_name" :type="'text'" :class="'sm:col-span-3'"/>
                        <FormInput v-model="form.email" :name="$t('Email')" :error="form.errors.email" :type="'text'" :class="'sm:col-span-3'"/>
                        <FormPhoneInput
                            v-model="form.phone"
                            :allowed-countries="allowedPhoneCountries"
                            :name="$t('Phone')"
                            :error="form.errors.phone"
                            :type="'text'"
                            :class="'sm:col-span-3'"
                        />
                        <div v-if="isSystemOwner" class="sm:col-span-6 tm-banner">
                            {{ $t('This account is protected as the primary system owner.') }}
                        </div>
                        <FormSelect v-model="form.role" :disabled="isSystemOwner" :name="$t('Role')" :error="form.errors.role" :options="roleOptions()" :type="'text'" :class="'sm:col-span-6'"/>
                        <FormInput v-model="form.password" :name="$t('Password')" :error="form.errors.password" :type="'password'" :class="'sm:col-span-3'"/>
                        <FormInput v-model="form.password_confirmation" :name="$t('Confirm password')" :error="form.errors.password_confirmation" :type="'password'" :class="'sm:col-span-3'"/>
                    </div>
                </UiSectionCard>

                <UiSectionCard :title="$t('Address details')">
                    <div class="grid gap-x-6 gap-y-4 sm:grid-cols-6">
                        <FormInput v-model="form.street" :name="$t('Street')" :error="form.errors.street" :type="'text'" :class="'sm:col-span-6'"/>
                        <FormInput v-model="form.city" :name="$t('City')" :error="form.errors.city" :type="'text'" :class="'sm:col-span-3'"/>
                        <FormInput v-model="form.state" :name="$t('State')" :error="form.errors.state" :type="'text'" :class="'sm:col-span-3'"/>
                        <FormInput v-model="form.zip" :name="$t('Zip code')" :error="form.errors.zip" :type="'text'" :class="'sm:col-span-3'"/>
                        <FormInput v-model="form.country" :name="$t('Country')" :error="form.errors.country" :type="'text'" :class="'sm:col-span-3'"/>
                    </div>
                </UiSectionCard>

                <div class="flex justify-end">
                    <button type="submit" class="tm-btn tm-btn--solid">
                        {{ $t('Save') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
<script setup>
    import AppLayout from "./../Layout/App.vue";
    import{ computed, ref } from 'vue';
    import { Link, useForm, usePage } from "@inertiajs/vue3";
    import FormImage from '@/Components/FormImage.vue';
    import FormInput from '@/Components/FormInput.vue';
    import FormPhoneInput from '@/Components/FormPhoneInput.vue';
    import FormSelect from '@/Components/FormSelect.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import UiSectionCard from '@/Components/UI/UiSectionCard.vue';

    const props = defineProps({ title: String, user: Object, roles: Object });
    const allowedPhoneCountries = Array.isArray(usePage().props.phoneCountries) ? usePage().props.phoneCountries : [];
    const fileUrl = ref(null);
    const isSystemOwner = computed(() => Boolean(props.user?.is_system_owner));

    const getAddressDetail = (value, key) => {
        if(value){
            const address = JSON.parse(value);
            return address?.[key] ?? null;
        } else {
            return null;
        }
    }

    const form = useForm({
        first_name: props.user?.first_name,
        last_name: props.user?.last_name,
        email: props.user?.email,
        phone: props.user?.phone,
        role: props.user?.role?.uuid,
        password: null,
        password_confirmation: null,
        avatar: null,
        street: getAddressDetail(props.user?.address, 'street'),
        city: getAddressDetail(props.user?.address, 'city'),
        state: getAddressDetail(props.user?.address, 'state'),
        zip: getAddressDetail(props.user?.address, 'zip'),
        country: getAddressDetail(props.user?.address, 'country')
    })

    const roleOptions = () => {
        return props.roles.map((option) => ({
            value: option.uuid,
            label: option.name,
        }));
    };

    const submitForm = async () => {
        const url = props.user ? window.location.pathname : '/admin/team/users';

        form[props.user ? 'put' : 'post'](url, {
            preserveScroll: true,
        });
    };
</script>

<style scoped>
.tm-btn {
    display: inline-flex;
    align-items: center;
    border-radius: 0.85rem;
    padding: 0.6rem 1.1rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: filter 160ms ease;
}

.tm-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.tm-btn--solid:hover {
    filter: brightness(1.05);
}

.tm-banner {
    border-radius: 0.9rem;
    border: 1px solid color-mix(in srgb, var(--ui-secondary) 30%, var(--ui-border));
    background: color-mix(in srgb, var(--ui-secondary) 8%, var(--ui-surface));
    padding: 0.85rem 1.05rem;
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--ui-text);
}
</style>
