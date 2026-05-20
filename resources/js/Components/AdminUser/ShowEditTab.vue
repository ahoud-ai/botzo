<template>
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.3fr)_minmax(320px,0.9fr)]">
        <form @submit.prevent="$emit('submit')">
            <UiSectionCard :title="$t('Edit account details')" :subtitle="$t('These changes affect the platform account only.')">
                <div class="grid gap-x-4 gap-y-4 sm:grid-cols-2">
                    <FormImage
                        v-model="form.avatar"
                        :name="$t('Avatar')"
                        :error="form.errors.avatar"
                        :label="$t('Upload image')"
                        :imageUrl="user?.avatar ? `/media/${user.avatar}` : null"
                        :class="'sm:col-span-2'"
                    />
                    <FormInput v-model="form.first_name" :name="$t('First name')" :error="form.errors.first_name" :type="'text'" />
                    <FormInput v-model="form.last_name" :name="$t('Last name')" :error="form.errors.last_name" :type="'text'" />
                    <FormInput v-model="form.email" :name="$t('Email')" :error="form.errors.email" :type="'email'" :class="'sm:col-span-2'" />
                    <FormPhoneInput
                        v-model="form.phone"
                        :allowed-countries="allowedPhoneCountries"
                        :name="$t('Phone')"
                        :error="form.errors.phone"
                        :type="'text'"
                        :class="'sm:col-span-2'"
                    />
                    <FormInput v-model="form.password" :name="$t('Password')" :error="form.errors.password" :type="'password'" />
                    <FormInput v-model="form.password_confirmation" :name="$t('Confirm password')" :error="form.errors.password_confirmation" :type="'password'" />
                    <FormInput v-model="form.street" :name="$t('Street')" :error="form.errors.street" :type="'text'" :class="'sm:col-span-2'" />
                    <FormInput v-model="form.city" :name="$t('City')" :error="form.errors.city" :type="'text'" />
                    <FormInput v-model="form.state" :name="$t('State')" :error="form.errors.state" :type="'text'" />
                    <FormInput v-model="form.zip" :name="$t('Zip code')" :error="form.errors.zip" :type="'text'" />
                    <FormInput v-model="form.country" :name="$t('Country')" :error="form.errors.country" :type="'text'" />
                </div>

                <div class="mt-5 flex justify-end">
                    <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60">
                        <PencilLine class="h-4 w-4" />
                        {{ form.processing ? $t('Saving...') : $t('Save') }}
                    </button>
                </div>
            </UiSectionCard>
        </form>

        <div class="space-y-6">
            <UiSectionCard :title="$t('Account state')" :subtitle="$t('Suspend or restore sign-in without changing company memberships.')">
                <div class="rounded-2xl border px-4 py-4 text-sm leading-7" :class="user.status === 'active' ? 'border-amber-200 bg-amber-50 text-amber-950' : 'border-sky-200 bg-sky-50 text-sky-900'">
                    {{ user.status === 'active'
                        ? $t('Suspending this account blocks future sign-ins without deleting company memberships or branch assignments.')
                        : $t('Restoring this account allows sign-in again and keeps previous company memberships as they are.') }}
                </div>

                <div class="mt-4 flex justify-end">
                    <button
                        v-if="canToggleAccountState"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition"
                        :class="user.actions?.can_restore_account ? 'bg-sky-600 hover:bg-sky-500' : 'bg-amber-600 hover:bg-amber-500'"
                        @click="$emit('open-state-modal')"
                    >
                        <ShieldCheck v-if="user.actions?.can_restore_account" class="h-4 w-4" />
                        <Ban v-else class="h-4 w-4" />
                        {{ user.actions?.can_restore_account ? $t('Restore') : $t('Suspend') }}
                    </button>
                </div>
            </UiSectionCard>

            <UiSectionCard :title="$t('Delete platform account')" :subtitle="$t('Use delete only after reviewing ownership and linked memberships.')">
                <div v-if="canDeleteAccount" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-4 text-sm leading-7 text-rose-900">
                    {{ $t('Deleting this account removes the platform user and synchronizes related membership records. Use this only for account-level cleanup.') }}
                </div>
                <div v-else class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm leading-7 text-amber-950">
                    {{ $t('Delete is blocked for main organization owners until ownership is transferred.') }}
                </div>

                <div class="mt-4 flex justify-end">
                    <button v-if="canDeleteAccount" type="button" class="inline-flex items-center gap-2 rounded-xl border border-rose-200 bg-white px-4 py-2.5 text-sm font-medium text-rose-700 transition hover:bg-rose-50" @click="$emit('open-delete-modal')">
                        <Trash2 class="h-4 w-4" />
                        {{ $t('Delete') }}
                    </button>
                </div>
            </UiSectionCard>
        </div>
    </div>
</template>

<script setup>
import { Ban, PencilLine, ShieldCheck, Trash2 } from 'lucide-vue-next';
import FormImage from '@/Components/FormImage.vue';
import FormInput from '@/Components/FormInput.vue';
import FormPhoneInput from '@/Components/FormPhoneInput.vue';
import UiSectionCard from '@/Components/UI/UiSectionCard.vue';

defineProps({
    user: { type: Object, required: true },
    form: { type: Object, required: true },
    allowedPhoneCountries: { type: Array, default: () => [] },
    canToggleAccountState: { type: Boolean, default: false },
    canDeleteAccount: { type: Boolean, default: false },
});

defineEmits(['submit', 'open-state-modal', 'open-delete-modal']);
</script>
