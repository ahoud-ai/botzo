<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-workspace-page--fixed capitalize min-h-full">
            <div class="ui-workspace-split ui-workspace-split--contacts h-full">
                <div class="ui-workspace-pane ui-workspace-sidebar" :class="$page.url === '/contacts/add' || contact ? 'hidden' : 'md:flex'">
                    <div class="px-4 pt-4">
                        <div class="flex justify-between mt-2">
                            <div class="flex gap-x-1 text-xl">
                                <h2>{{ $t('Contacts') }}</h2>
                                <span class="text-slate-500">{{ props.rowCount }}</span>
                            </div>
                            <div class="flex gap-x-2 items-center">
                                <Link v-if="canCreate" href="/contacts/add" :title="$t('Add Contact')">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><g fill="currentColor" fill-rule="evenodd" clip-rule="evenodd"><path d="M2 12C2 6.477 6.477 2 12 2s10 4.477 10 10s-4.477 10-10 10S2 17.523 2 12Zm10-8a8 8 0 1 0 0 16a8 8 0 0 0 0-16Z"/><path d="M13 7a1 1 0 1 0-2 0v4H7a1 1 0 1 0 0 2h4v4a1 1 0 1 0 2 0v-4h4a1 1 0 1 0 0-2h-4V7Z"/></g></svg>
                                </Link>
                            </div>
                        </div>
                    </div>
                    <ContactTable :rows="props.rows" :filters="props.filters" :type="'contact'" :contactGroups="props.contactGroups" @callback="handleContact"/>
                </div>
                <div class="ui-workspace-pane ui-workspace-main bg-cover flex h-full min-h-0 flex-col">
                    <div v-if="contact" class="flex-1 overflow-y-auto">
                        <ContactInfo v-if="!editContact" class="pt-20" :contact="contact" :fields="props.fields" :locationSettings="locationSettings"/>
                        <ContactForm v-else :contactGroups="props.contactGroups" :contact="props.contact" :fields="props.fields" :locationSettings="locationSettings" />
                    </div>
                    <div v-else-if="$page.url === '/contacts/add'" class="flex-1 overflow-y-auto">
                        <ContactForm :contactGroups="props.contactGroups" :contact="props.contact" :fields="props.fields" :locationSettings="locationSettings" />
                    </div>
                    <div v-else class="hidden flex-1 md:flex">
                        <div class="ui-workspace-empty-state">
                            <div class="ui-workspace-empty">
                                <span class="ui-workspace-empty-kicker">{{ $t('Contacts') }}</span>
                                <h2 class="ui-workspace-empty-title">{{ $t('Select contact') }}</h2>
                                <p class="ui-workspace-empty-description">{{ $t('Choose a contact from the list to review details, update information, or start a conversation without leaving this page.') }}</p>
                                <div class="ui-workspace-empty-actions">
                                    <Link v-if="canCreate" href="/contacts/add" class="ui-workspace-empty-primary">{{ $t('Add contact') }}</Link>
                                    <button type="submit" v-else disabled class="ui-workspace-empty-primary cursor-not-allowed">{{ $t('Add contact') }}</button>
                                    <button type="button" v-if="canImport" @click="isOpenModal = true" class="ui-workspace-empty-secondary">{{ $t('Bulk upload') }}</button>
                                    <button type="submit" v-else disabled class="ui-workspace-empty-secondary cursor-not-allowed">{{ $t('Bulk upload') }}</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>

    <ContactImportModal :type="'contact'" v-model:modelValue="isOpenModal"/>
</template>
<script setup>
    import AppLayout from "./../Layout/App.vue";
    import { ref, computed } from 'vue';
    import { Link, usePage } from "@inertiajs/vue3";
    import ContactForm from '@/Components/ContactComponents/CreateForm.vue';
    import ContactImportModal from '@/Components/ContactImportModal.vue';
    import ContactInfo from '@/Components/ContactInfo.vue';
    import ContactTable from '@/Components/Tables/ContactTable.vue';
    import { router } from '@inertiajs/vue3';

    const props = defineProps({ rows: Object, filters: Object, rowCount: Number, contactGroups: Object, contact: Object, editContact: Boolean, fields: Object, locationSettings: String, flash: Object });
    const isOpenModal = ref(false);

    // Helper function to check permissions
    const hasPermission = (permission) => {
        const pageProps = usePage().props;
        const permissions = pageProps?.permissions;
        if (!permissions || !Array.isArray(permissions)) {
            return false;
        }
        return permissions.includes('*') || permissions.includes(permission);
    };

    const canCreate = computed(() => hasPermission('contacts.create'));
    const canImport = computed(() => hasPermission('contacts.import'));

    const handleContact = (value) => {
        router.visit('/contacts', {
            method: 'get',
            data: value,
        })
    }
</script>
