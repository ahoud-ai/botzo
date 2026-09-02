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
                    <button type="submit" :disabled="form.processing" class="usr-btn usr-btn--solid">
                        <PencilLine class="h-4 w-4" />
                        {{ form.processing ? $t('Saving...') : $t('Save') }}
                    </button>
                </div>
            </UiSectionCard>
        </form>

        <div class="space-y-6">
            <UiSectionCard :title="$t('Account state')" :subtitle="$t('Suspend or restore sign-in without changing company memberships.')">
                <div class="usr-banner" :class="user.status === 'active' ? 'usr-banner--warning' : 'usr-banner--info'">
                    {{ user.status === 'active'
                        ? $t('Suspending this account blocks future sign-ins without deleting company memberships or branch assignments.')
                        : $t('Restoring this account allows sign-in again and keeps previous company memberships as they are.') }}
                </div>

                <div class="mt-4 flex justify-end">
                    <button
                        v-if="canToggleAccountState"
                        type="button"
                        class="usr-btn"
                        :class="user.actions?.can_restore_account ? 'usr-btn--info' : 'usr-btn--warning'"
                        @click="$emit('open-state-modal')"
                    >
                        <ShieldCheck v-if="user.actions?.can_restore_account" class="h-4 w-4" />
                        <Ban v-else class="h-4 w-4" />
                        {{ user.actions?.can_restore_account ? $t('Restore') : $t('Suspend') }}
                    </button>
                </div>
            </UiSectionCard>

            <UiSectionCard :title="$t('Delete platform account')" :subtitle="$t('Use delete only after reviewing ownership and linked memberships.')">
                <div v-if="canDeleteAccount" class="usr-banner usr-banner--danger">
                    {{ $t('Deleting this account removes the platform user and synchronizes related membership records. Use this only for account-level cleanup.') }}
                </div>
                <div v-else class="usr-banner usr-banner--warning">
                    {{ $t('Delete is blocked for main organization owners until ownership is transferred.') }}
                </div>

                <div class="mt-4 flex justify-end">
                    <button v-if="canDeleteAccount" type="button" class="usr-btn usr-btn--danger-ghost" @click="$emit('open-delete-modal')">
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

<style scoped>
.usr-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    border-radius: 0.85rem;
    padding: 0.6rem 1.1rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: background-color 160ms ease, border-color 160ms ease, filter 160ms ease, opacity 160ms ease;
}

.usr-btn--solid {
    border: 1px solid transparent;
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.usr-btn--solid:hover:not(:disabled) {
    filter: brightness(1.05);
}

.usr-btn--solid:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

.usr-btn--info {
    border: 1px solid transparent;
    background: var(--ui-primary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.usr-btn--info:hover {
    filter: brightness(1.1);
}

.usr-btn--warning {
    border: 1px solid transparent;
    background: var(--ui-warning);
    color: #1f2937;
    box-shadow: var(--ui-shadow-1);
}

.usr-btn--warning:hover {
    filter: brightness(1.05);
}

.usr-btn--danger-ghost {
    border: 1px solid color-mix(in srgb, var(--ui-danger) 40%, var(--ui-border));
    background: var(--ui-surface);
    color: var(--ui-danger);
}

.usr-btn--danger-ghost:hover {
    background: color-mix(in srgb, var(--ui-danger) 8%, var(--ui-surface));
}

.usr-banner {
    border: 1px solid;
    border-radius: 1rem;
    padding: 1rem 1.1rem;
    font-size: 0.88rem;
    line-height: 1.6;
}

.usr-banner--info {
    border-color: color-mix(in srgb, var(--ui-primary) 30%, var(--ui-border));
    background: color-mix(in srgb, var(--ui-primary) 8%, var(--ui-surface));
    color: var(--ui-text);
}

.usr-banner--warning {
    border-color: color-mix(in srgb, var(--ui-warning) 35%, var(--ui-border));
    background: color-mix(in srgb, var(--ui-warning) 10%, var(--ui-surface));
    color: var(--ui-text);
}

.usr-banner--danger {
    border-color: color-mix(in srgb, var(--ui-danger) 35%, var(--ui-border));
    background: color-mix(in srgb, var(--ui-danger) 10%, var(--ui-surface));
    color: var(--ui-text);
}
</style>
