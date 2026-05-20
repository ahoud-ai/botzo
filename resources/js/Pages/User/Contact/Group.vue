<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-workspace-page--fixed capitalize min-h-full">
            <div class="ui-workspace-split ui-workspace-split--contacts h-full">
                <div class="ui-workspace-pane ui-workspace-sidebar" :class="group ? 'hidden' : ''">
                    <div class="px-4 pt-4">
                        <div class="flex justify-between mt-2">
                            <div class="flex gap-x-1 text-xl">
                                <h2>{{ $t('Groups') }}</h2>
                                <span class="text-slate-500">{{ props.rowCount }}</span>
                            </div>
                            <div class="flex gap-x-2 items-center">
                                <span @click="openModal()" class="cursor-pointer" :title="$t('Add Contact')">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><g fill="currentColor" fill-rule="evenodd" clip-rule="evenodd"><path d="M2 12C2 6.477 6.477 2 12 2s10 4.477 10 10s-4.477 10-10 10S2 17.523 2 12Zm10-8a8 8 0 1 0 0 16a8 8 0 0 0 0-16Z"/><path d="M13 7a1 1 0 1 0-2 0v4H7a1 1 0 1 0 0 2h4v4a1 1 0 1 0 2 0v-4h4a1 1 0 1 0 0-2h-4V7Z"/></g></svg>
                                </span>
                            </div>
                        </div>
                    </div>
                    <ContactTable :rows="props.rows" :filters="props.filters" :type="'group'" @callback="handleGroup"/>
                </div>
                <div class="ui-workspace-pane ui-workspace-main bg-cover flex h-full min-h-0 flex-col">
                    <div v-if="group" class="flex-1 overflow-y-auto">
                        <ContactGroupInfo :group="group" />
                    </div>
                    <div v-else class="hidden flex-1 md:flex">
                        <div class="ui-workspace-empty-state">
                            <div class="ui-workspace-empty">
                                <span class="ui-workspace-empty-kicker">{{ $t('Groups') }}</span>
                                <h2 class="ui-workspace-empty-title">{{ $t('Select group') }}</h2>
                                <p class="ui-workspace-empty-description">{{ $t('Choose a group from the list to review members, manage assignments, or create a new segment for your contacts.') }}</p>
                                <div class="ui-workspace-empty-actions">
                                    <button type="button" @click="openModal()" class="ui-workspace-empty-primary capitalize">{{ $t('Add group') }}</button>
                                    <button type="button" @click="isOpenModal = true" class="ui-workspace-empty-secondary">{{ $t('Bulk upload') }}</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
    <!-- Form Modal Component-->
    <FormModal 
        v-model="isOpenFormModal" 
        :label="$t('Add group')" 
        :url="formUrl" 
        :form="form" 
        :formInputs="formInputs"
        @callback="handleCallback"
    />

    <ContactGroupImportModal :type="'group'" v-model:modelValue="isOpenModal"/>
</template>
<script setup>
    import AppLayout from "./../Layout/App.vue";
    import { ref } from 'vue';
    import ContactGroupInfo from '@/Components/ContactGroupInfo.vue';
    import ContactGroupImportModal from '@/Components/ContactImportModal.vue';
    import ContactTable from '@/Components/Tables/ContactTable.vue';
    import Dropdown from '@/Components/Dropdown.vue';
    import DropdownItemGroup from '@/Components/DropdownItemGroup.vue';
    import DropdownItem from '@/Components/DropdownItem.vue';
    import FormModal from '@/Components/FormModal.vue';
    import { router } from '@inertiajs/vue3';
    import { useI18n } from 'vue-i18n';
    const { t } = useI18n();

    const props = defineProps({ rows: Object, filters: Object, rowCount: Number, group: Object });
    const isOpenModal = ref(false);
    const currentUrl = window.location.href;
    const isOpenFormModal = ref(false);
    const formUrl = ref(currentUrl);
    const form = ref({
        name: '',
    });

    const initialFormInputs = [
        {
            inputType: 'FormInput',
            name: 'name',
            label: t('Group name'),
            type: 'text',
            className: 'sm:col-span-6',
        },
    ];

    const formInputs = initialFormInputs;

    const openModal = () => {
        isOpenFormModal.value = true;
        form.value.name = '';
    }

    const handleGroup = (value) => {
        router.visit('/contact-groups', {
            method: 'get',
            data: value,
        })
    }

    const handleCallback = (res) => {
        form.value.name = res.data.name;
        handleGroup(res.data.uuid);
    }
</script>
