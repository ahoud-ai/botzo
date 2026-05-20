<template>
    <SettingLayout :modules="props.modules">
        <div class="min-h-0">
            <div class="flex justify-center items-center">
                <div class="md:w-[60em] mb-20">
                    <div class="bg-white border border-slate-200 rounded-lg py-2 text-sm mb-4 pb-4 px-4">
                        <div class="w-full py-2 mb-2 mt-2">
                            <div class="flex w-full mb-4">
                                <div class="text-md">
                                    <h4 class="text-[16px]">{{ $t('Team') }}</h4>
                                    <span class="flex items-center mt-1 text-slate-500">
                                        {{ $t('Invite users to this workspace and assign one of its local roles') }}
                                    </span> 
                                </div>
                                <div class="ms-auto">
                                    <button
                                        type="button"
                                        @click="openModal()"
                                        :disabled="!canInviteUsers"
                                        :class="[
                                            'rounded-md px-3 py-2 text-sm shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600',
                                            canInviteUsers
                                                ? 'bg-primary text-white hover:bg-indigo-500'
                                                : 'cursor-not-allowed bg-slate-200 text-slate-500',
                                        ]"
                                    >
                                        {{ $t('Invite user') }}
                                    </button>
                                </div>
                            </div>
                            <div v-if="hasInheritedParentAuthority" class="mb-4 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                                <div class="font-medium">{{ $t('Parent organization authority') }}</div>
                                <p class="mt-1">{{ $t('You are managing this branch through the parent organization owner access. Invitations and role changes here still apply to this branch workspace only.') }}</p>
                            </div>
                            <div class="mb-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                                {{ $t('Only roles created for this workspace appear in the invite form. The Owner role is reserved for automatic workspace ownership and cannot be assigned manually.') }}
                            </div>
                            <div v-if="showEmptyRolesNotice" class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                {{ $t('Create at least one workspace role before inviting another user.') }}
                            </div>
                            <div class="w-5/5">
                                <!-- Table Component-->
                                <TeamTable :rows="props.rows" @edit="openModal"/>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Modal :label="label" :isOpen="isOpenFormModal" @close="isOpenFormModal = false">
            <div class="mt-5 grid grid-cols-1 gap-x-6 gap-y-4">
                <form @submit.prevent="submitForm()" class="grid gap-x-6 gap-y-4 sm:grid-cols-6">
                    <FormInput v-model="form.email" :error="form.errors.email" :name="$t('Email')" :type="'email'" :class="'sm:col-span-6'" :disabled="formMethod === 'put' ? true : false"/>
                    <FormSelect v-model="form.organization_role_id" :error="form.errors.organization_role_id" :options="roleOptions" :name="$t('Role')" :class="'sm:col-span-6'" :placeholder="$t('Select Role')"/>
                    <div class="mt-4 flex">
                        <button type="button" @click="isOpenFormModal = false" class="inline-flex justify-center rounded-md border border-transparent bg-slate-50 px-4 py-2 text-sm text-slate-500 hover:bg-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 me-4">{{ $t('Cancel') }}</button>
                        <button type="submit" :class="['inline-flex justify-center rounded-md border border-transparent bg-primary px-4 py-2 text-sm text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2', { 'opacity-50': form.processing }]" :disabled="form.processing">
                            <svg v-if="form.processing" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                            <span v-else>{{ $t('Save') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </SettingLayout>
</template>
<script setup>
    import SettingLayout from "./../Settings/Layout.vue";
    import { ref, onMounted, computed } from 'vue';
    import { useForm } from "@inertiajs/vue3";
    import TeamTable from '@/Components/Tables/TeamTable.vue';
    import Modal from '@/Components/Modal.vue';
    import FormInput from '@/Components/FormInput.vue';
    import FormSelect from '@/Components/FormSelect.vue';
    import { useI18n } from 'vue-i18n';
    import { useWorkspaceAccess } from '@/Composables/useWorkspaceAccess';
    const { t } = useI18n();
    const { hasInheritedParentAuthority } = useWorkspaceAccess();

    const props = defineProps({ 
        rows: Object, 
        filters: Object,
        modules: {
            type: Array,
            default: () => []
        }
    });
    const isOpenFormModal = ref(false);
    const formUrl = ref('/settings/team/invite');
    const formMethod = ref('post');
    const label = ref(t('Invite user'));

    const form = useForm({
        email: null,
        organization_role_id: null,
    });

    const roleOptions = ref([]);
    const roleOptionsLoaded = ref(false);
    const canInviteUsers = computed(() => roleOptions.value.length > 0);
    const showEmptyRolesNotice = computed(() => roleOptionsLoaded.value && roleOptions.value.length === 0);

    // Load organization roles
    onMounted(async () => {
        try {
            const response = await fetch('/settings/team/roles/select');
            const data = await response.json();
            roleOptions.value = data.roles.map(role => ({
                value: role.id,
                label: role.name,
            }));
        } catch (error) {
            console.error('Error loading roles:', error);
            // Fallback to empty array
            roleOptions.value = [];
        } finally {
            roleOptionsLoaded.value = true;
        }
    });

    const openModal = (key, formData = {}) => {
        if (!key && !canInviteUsers.value) {
            return;
        }

        label.value = t('Invite user');
        formUrl.value = '/settings/team/invite';
        formMethod.value = 'post';

        if (key) {
            label.value = t('Update user');
            formUrl.value = '/settings/team/' + key.id;
            formMethod.value = 'put';
            form.email = key.email;
            form.organization_role_id = key.organization_role_id || key.role_id;
            isOpenFormModal.value = true;
        } else {
            form.email = null;
            form.organization_role_id = null;
            isOpenFormModal.value = true;
        }
    }

    const submitForm = () => {
        if(formMethod.value === 'post'){
            form.post(formUrl.value, {
                onFinish: () => {
                    isOpenFormModal.value = false;
                }
            });
        } else {
            form.put(formUrl.value, {
                onFinish: () => {
                    isOpenFormModal.value = false;
                }
            });
        }
    }
</script>



