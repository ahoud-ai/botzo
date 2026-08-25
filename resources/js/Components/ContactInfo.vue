<script setup>
    import axios from 'axios';
    import { ref, watchEffect, computed } from 'vue';
    import { Link, router, useForm, usePage } from '@inertiajs/vue3';
    import Dropdown from '@/Components/Dropdown.vue';
    import DropdownItemGroup from '@/Components/DropdownItemGroup.vue';
    import DropdownItem from '@/Components/DropdownItem.vue';
    import { useI18n } from 'vue-i18n';
    const { t } = useI18n();
    
    const props = defineProps(['contact', 'fields', 'locationSettings']);
    const contact = ref(props.contact);
    const metadata = (props.contact.metadata !== null && props.contact.metadata !== '') ? JSON.parse(props.contact.metadata) : {};

    // Helper function to check permissions
    const hasPermission = (permission) => {
        const pageProps = usePage().props;
        const permissions = pageProps?.permissions;
        if (!permissions || !Array.isArray(permissions)) {
            return false;
        }
        return permissions.includes('*') || permissions.includes(permission);
    };

    const canEdit = computed(() => hasPermission('contacts.edit'));
    const canDelete = computed(() => hasPermission('contacts.delete'));
    const replyWindowOpen = computed(() => contact.value?.reply_context?.reply_window_open === true);

    watchEffect(() => {
        contact.value = props.contact;
    });

    const favorite = async() => {
        contact.value.is_favorite = !contact.value.is_favorite;

        router.put('/contacts/favorite/' + contact.value.uuid, { favorite: contact.value.is_favorite });
    }

    const form = useForm({'test': null});

    const deleteRow = async() => {
        if (!canDelete.value) {
            return;
        }
        router.visit('/contacts', {
            method: 'delete',
            data: { 'uuids': [ contact.value.uuid ]},
            preserveState: true
        })
    }

    const getAddressDetail = (value, key) => {
        const address = JSON.parse(value);
        return address?.[key] && address?.[key] != 'Not Set' ? address?.[key] : t('not set');
    }
</script>
<template>
    <div class="px-6 md:px-20 overflow-y-auto h-full min-h-0">
        <div class="flex justify-center gap-x-8 items-center pb-8 pe-20 border-[var(--ui-border)] border-b">
            <div>
                <div class="rounded-full p-1 ring-4 ring-[var(--ui-surface-soft)]">
                    <img v-if="contact.avatar" class="rounded-full w-36 h-36 object-cover" :src="contact.avatar" alt="">
                    <div v-else class="rounded-full w-36 h-36 flex items-center justify-center bg-secondary/10 text-secondary text-4xl font-semibold">
                        {{ contact.first_name?.substring(0, 1) }}
                    </div>
                </div>
            </div>
            <div>
                <h1 class="text-3xl font-bold text-[var(--ui-text)]">{{ contact.full_name }}</h1>
                <div class="text-slate-500 dark:text-slate-400 truncate flex items-center mt-1">
                    <span class="text-sm" dir="ltr" style="unicode-bidi: isolate;">{{ contact.formatted_phone_number }}</span>
                </div>
                <div class="flex flex-wrap gap-2 mt-3">
                    <span class="contact-wa-badge" :class="{ 'contact-wa-badge--open': replyWindowOpen }">
                        <span class="dot"></span>
                        {{ replyWindowOpen ? $t('Reply window open') : $t('Reply window closed') }}
                    </span>
                    <span
                        v-for="group in contact.contact_groups"
                        :key="group.uuid"
                        class="contact-wa-badge contact-wa-badge--group"
                    >{{ group.name }}</span>
                </div>
                <div class="flex gap-x-2 mt-4">
                    <Link v-if="canEdit" :href="'/contacts/' + contact.uuid + '?edit=true'" class="contact-action-btn contact-action-btn--wide">{{ $t('Edit') }}</Link>
                    <Link :href="'/chats/' + contact.uuid" class="contact-action-btn" :title="$t('Chats')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 256 256"><path fill="currentColor" d="M216 80h-32V48a16 16 0 0 0-16-16H40a16 16 0 0 0-16 16v128a8 8 0 0 0 13 6.22L72 154v30a16 16 0 0 0 16 16h93.59L219 230.22a8 8 0 0 0 5 1.78a8 8 0 0 0 8-8V96a16 16 0 0 0-16-16M66.55 137.78L40 159.25V48h128v88H71.58a8 8 0 0 0-5.03 1.78M216 207.25l-26.55-21.47a8 8 0 0 0-5-1.78H88v-32h80a16 16 0 0 0 16-16V96h32Z"></path></svg>
                    </Link>
                    <button type="button" @click="favorite()" class="contact-action-btn" :class="{ 'contact-action-btn--favorite': contact.is_favorite }">
                        <svg v-if="contact.is_favorite === 0 || contact.is_favorite === false" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="1.5" d="M9.153 5.408C10.42 3.136 11.053 2 12 2c.947 0 1.58 1.136 2.847 3.408l.328.588c.36.646.54.969.82 1.182c.28.213.63.292 1.33.45l.636.144c2.46.557 3.689.835 3.982 1.776c.292.94-.546 1.921-2.223 3.882l-.434.507c-.476.557-.715.836-.822 1.18c-.107.345-.071.717.001 1.46l.066.677c.253 2.617.38 3.925-.386 4.506c-.766.582-1.918.051-4.22-1.009l-.597-.274c-.654-.302-.981-.452-1.328-.452c-.347 0-.674.15-1.329.452l-.595.274c-2.303 1.06-3.455 1.59-4.22 1.01c-.767-.582-.64-1.89-.387-4.507l.066-.676c.072-.744.108-1.116 0-1.46c-.106-.345-.345-.624-.821-1.18l-.434-.508c-1.677-1.96-2.515-2.941-2.223-3.882c.293-.941 1.523-1.22 3.983-1.776l.636-.144c.699-.158 1.048-.237 1.329-.45c.28-.213.46-.536.82-1.182l.328-.588Z"/></svg>
                        <svg v-else xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="#f5b400" stroke="#f5b400" stroke-width="1.5" d="M9.153 5.408C10.42 3.136 11.053 2 12 2c.947 0 1.58 1.136 2.847 3.408l.328.588c.36.646.54.969.82 1.182c.28.213.63.292 1.33.45l.636.144c2.46.557 3.689.835 3.982 1.776c.292.94-.546 1.921-2.223 3.882l-.434.507c-.476.557-.715.836-.822 1.18c-.107.345-.071.717.001 1.46l.066.677c.253 2.617.38 3.925-.386 4.506c-.766.582-1.918.051-4.22-1.009l-.597-.274c-.654-.302-.981-.452-1.328-.452c-.347 0-.674.15-1.329.452l-.595.274c-2.303 1.06-3.455 1.59-4.22 1.01c-.767-.582-.64-1.89-.387-4.507l.066-.676c.072-.744.108-1.116 0-1.46c-.106-.345-.345-.624-.821-1.18l-.434-.508c-1.677-1.96-2.515-2.941-2.223-3.882c.293-.941 1.523-1.22 3.983-1.776l.636-.144c.699-.158 1.048-.237 1.329-.45c.28-.213.46-.536.82-1.182l.328-.588Z"/></svg>
                    </button>
                    <Dropdown v-if="canDelete" :align="'right'">
                        <button type="submit" class="contact-action-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 16 16"><path fill="currentColor" d="M4 8a2 2 0 1 1-4 0a2 2 0 0 1 4 0Zm6 0a2 2 0 1 1-4 0a2 2 0 0 1 4 0Zm4 2a2 2 0 1 0 0-4a2 2 0 0 0 0 4Z"/></svg>
                        </button>
                        <template #items>
                            <DropdownItemGroup>
                                <!--<DropdownItem as="button" @click="openModal('edit')">Edit Contact</DropdownItem>-->
                                <DropdownItem as="button" @click="deleteRow()">{{ $t('Delete') }}</DropdownItem>
                            </DropdownItemGroup>
                        </template>
                    </Dropdown>
                </div>
            </div>
        </div>
        <div class="pe-20 py-6">
            <p class="contact-section-title">{{ $t('Contact') }}</p>
            <div class="contact-info-card">
                <div class="contact-info-row">
                    <span class="contact-info-label">{{ $t('Full name') }}</span>
                    <span class="contact-info-value">{{ contact.full_name }}</span>
                </div>
                <div class="contact-info-row">
                    <span class="contact-info-label">{{ $t('Group') }}</span>
                    <span class="contact-info-value">
                        {{ contact.contact_groups?.length ? contact.contact_groups.map(group => group.name).join(', ') : $t('not set') }}
                    </span>
                </div>
                <div class="contact-info-row">
                    <span class="contact-info-label">{{ $t('Email') }}</span>
                    <span class="contact-info-value">{{ contact.email ?? $t('not set') }}</span>
                </div>
                <div class="contact-info-row">
                    <span class="contact-info-label">{{ $t('Contact') }}</span>
                    <span class="contact-info-value">{{ contact.formatted_phone_number }}</span>
                </div>
            </div>
        </div>
        <div v-if="locationSettings === 'before' && fields.length > 0" class="pe-20 pb-6">
            <p class="contact-section-title">{{ $t('Additional details') }}</p>
            <div class="contact-info-card">
                <div v-for="(input, index) in props.fields" :key="index" class="contact-info-row">
                    <span class="contact-info-label">{{ $t(input.name) }}</span>
                    <span v-if="metadata && metadata[input.name] != null" class="contact-info-value">{{ metadata[input.name] }}</span>
                    <span v-else class="contact-info-empty">{{ $t('not set') }}</span>
                </div>
            </div>
        </div>
        <div class="pe-20 pb-6">
            <p class="contact-section-title">{{ $t('Address') }}</p>
            <div class="contact-info-card">
                <div class="contact-info-row">
                    <span class="contact-info-label">{{ $t('Street') }}</span>
                    <span class="contact-info-value">{{ getAddressDetail(contact.address, 'street') }}</span>
                </div>
                <div class="contact-info-row">
                    <span class="contact-info-label">{{ $t('City') }}</span>
                    <span class="contact-info-value">{{ getAddressDetail(contact.address, 'city') }}</span>
                </div>
                <div class="contact-info-row">
                    <span class="contact-info-label">{{ $t('State') }}</span>
                    <span class="contact-info-value">{{ getAddressDetail(contact.address, 'state') }}</span>
                </div>
                <div class="contact-info-row">
                    <span class="contact-info-label">{{ $t('Zip code') }}</span>
                    <span class="contact-info-value">{{ getAddressDetail(contact.address, 'zip') }}</span>
                </div>
                <div class="contact-info-row">
                    <span class="contact-info-label">{{ $t('Country') }}</span>
                    <span class="contact-info-value">{{ getAddressDetail(contact.address, 'country') }}</span>
                </div>
            </div>
        </div>
        <div v-if="locationSettings === 'after' && fields.length > 0" class="pe-20 pb-6">
            <p class="contact-section-title">{{ $t('Additional details') }}</p>
            <div class="contact-info-card">
                <div v-for="(input, index) in props.fields" :key="index" class="contact-info-row">
                    <span class="contact-info-label">{{ $t(input.name) }}</span>
                    <span v-if="metadata && metadata[input.name] != null" class="contact-info-value">{{ metadata[input.name] }}</span>
                    <span v-else class="contact-info-empty">{{ $t('not set') }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.contact-action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 2.35rem;
    min-width: 2.35rem;
    padding: 0 0.85rem;
    border-radius: 0.65rem;
    color: var(--ui-text);
    background: var(--ui-surface-soft);
    border: 1px solid var(--ui-border);
    font-size: 0.85rem;
    font-weight: 500;
    transition: background-color 160ms ease, border-color 160ms ease;
}

.contact-action-btn:hover {
    background: var(--ui-border);
}

.contact-action-btn--wide {
    padding: 0 1.1rem;
}

.contact-action-btn--favorite {
    background: color-mix(in srgb, #f5b400 14%, var(--ui-surface));
    border-color: color-mix(in srgb, #f5b400 40%, var(--ui-border));
}

.contact-wa-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border-radius: 999px;
    padding: 0.3rem 0.75rem;
    font-size: 0.76rem;
    font-weight: 700;
    background: var(--ui-surface-soft);
    color: var(--ui-muted);
}

.contact-wa-badge .dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}

.contact-wa-badge--open {
    background: color-mix(in srgb, var(--ui-success) 14%, transparent);
    color: var(--ui-success);
}

.contact-wa-badge--group {
    background: color-mix(in srgb, var(--ui-secondary) 12%, transparent);
    color: var(--ui-secondary);
}

.contact-section-title {
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: var(--ui-muted);
    margin-bottom: 0.6rem;
}

.contact-info-card {
    border: 1px solid var(--ui-border);
    border-radius: var(--ui-radius-lg, 1rem);
    background: var(--ui-surface);
    overflow: hidden;
}

.contact-info-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.75rem 1.1rem;
    font-size: 0.88rem;
}

.contact-info-row + .contact-info-row {
    border-top: 1px solid var(--ui-border);
}

.contact-info-label {
    color: var(--ui-muted);
    flex-shrink: 0;
}

.contact-info-value {
    color: var(--ui-text);
    font-weight: 500;
    text-align: end;
}

.contact-info-empty {
    padding: 0.15rem 0.55rem;
    border-radius: 999px;
    background: var(--ui-surface-soft);
    color: var(--ui-muted);
    font-size: 0.75rem;
}
</style>
