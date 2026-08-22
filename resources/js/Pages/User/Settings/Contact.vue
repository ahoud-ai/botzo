<template>
    <SettingLayout :modules="props.modules" :title="$t('Contact fields')" :subtitle="$t('Customize the extra fields captured for each contact')">
        <div class="min-h-0">
            <div class="flex justify-center items-center">
                <div class="w-full md:w-[60em]">
                    <UiSectionCard :title="$t('Contact fields location')" :subtitle="$t('Place custom fields before or after the address section')" class="mb-4">
                        <div class="settings-row settings-row--align-center">
                            <div class="settings-row-control settings-row-control--narrow settings-row-control--start">
                                <FormSelect v-model="location" :options="locationOptions" :name="''" :error="form2.errors.location" :class="'col-span-6'" :placeholder="$t('Select Here')"/>
                            </div>
                        </div>
                    </UiSectionCard>

                    <UiSectionCard :title="$t('Custom contact fields')" :subtitle="$t('Create custom input fields for the contacts section')" class="mb-20">
                        <template #icon>
                            <button type="button" @click="openModal()" class="settings-save-btn">{{ $t('Add fields') }}</button>
                        </template>
                        <ContactFieldTable :rows="props.rows" @edit="openModal" @delete="openAlert"/>
                    </UiSectionCard>
                </div>
            </div>
        </div>

        <Modal :label="label" :isOpen="isOpenFormModal" @close="isOpenFormModal = false">
            <div class="mt-5 grid grid-cols-1 gap-x-6 gap-y-4">
                <form @submit.prevent="submitForm()" class="gap-y-4">
                    <div class="grid grid-cols gap-y-4">
                        <FormInput v-model="form.name" :name="$t('Label')" :error="form.errors.name" :type="'text'" :class="'col-span-6'"/>
                        <FormSelect v-model="form.component" :options="componentOptions" :name="$t('Form component')" :error="form.errors.component" :class="'col-span-6'" :optionClassName="'h-[8em]'" :placeholder="$t('Select Here')"/>
                        <FormSelect v-if="form.component === 'input'" v-model="form.type" :options="inputTypeOptions" :name="$t('Input type')" :error="form.errors.type" :class="'col-span-6'" :optionClassName="'h-[8em]'" :placeholder="$t('Select Here')"/>
                        <div v-if="form.component === 'select'" :class="'col-span-6 mt-2'">
                            <div class="flex pb-2 items-center">
                                <span class="settings-field-label">{{ $t('Select options') }}</span>
                                <div class="col-1 ms-auto">
                                    <button type="button" class="settings-modal-chip-btn" @click="add">{{ $t('Add option') }}</button>
                                </div>
                            </div>
                            <div class="rounded-lg p-2" style="background: var(--ui-surface-soft);">
                                <draggable tag="div" :list="form.options" class="mt-2 w-full" handle=".handle" item-key="id">
                                    <template #item="{ element, index }">
                                        <div class="flex items-center w-full gap-x-4">
                                            <span class="handle text-[var(--ui-muted)]">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M9 19.23q-.508 0-.87-.36q-.36-.362-.36-.87t.36-.87t.87-.36t.87.36q.36.362.36.87t-.36.87t-.87.36m6 0q-.508 0-.87-.36q-.36-.362-.36-.87t.36-.87t.87-.36t.87.36q.36.362.36.87t-.36.87t-.87.36m-6-6q-.508 0-.87-.36q-.36-.362-.36-.87t.36-.87t.87-.36t.87.36q.36.362.36.87t-.36.87t-.87.36m6 0q-.508 0-.87-.36q-.36-.362-.36-.87t.36-.87t.87-.36t.87.36q.36.362.36.87t-.36.87t-.87.36m-6-6q-.508 0-.87-.36q-.36-.362-.36-.87t.36-.87t.87-.36t.87.36q.36.362.36.87t-.36.87t-.87.36m6 0q-.508 0-.87-.36q-.36-.362-.36-.87t.36-.87t.87-.36t.87.36q.36.362.36.87t-.36.87t-.87.36"/></svg>
                                            </span>

                                            <FormInput v-model="element.value" :name="''" :type="'text'" :class="'w-full py-2'" :required="true"/>

                                            <span v-if="index != 0" @click="removeAt(index)" class="settings-icon-action settings-icon-action--danger">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m20 9l-1.995 11.346A2 2 0 0 1 16.035 22h-8.07a2 2 0 0 1-1.97-1.654L4 9m17-3h-5.625M3 6h5.625m0 0V4a2 2 0 0 1 2-2h2.75a2 2 0 0 1 2 2v2m-6.75 0h6.75"/></svg>
                                            </span>
                                            <span v-else @click="add" class="settings-icon-action">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 17h7m5-1h3m0 0h3m-3 0v3m0-3v-3M3 12h11M3 7h11"/></svg>
                                            </span>
                                        </div>
                                    </template>
                                </draggable>
                            </div>
                        </div>
                        <div class="flex items-center gap-x-4 py-3" :class="'col-span-6'">
                            <span class="settings-field-label">{{ $t('Is required') }}</span>
                            <FormToggleSwitch :modelValue="form.required != 0" @update:modelValue="toggleRequiredInput" />
                        </div>
                    </div>

                    <div class="mt-4 flex">
                        <button type="button" @click="isOpenFormModal = false" class="settings-modal-btn settings-modal-btn--ghost me-3">{{ $t('Cancel') }}</button>
                        <button type="submit"
                            class="settings-modal-btn settings-modal-btn--solid"
                            :disabled="isLoading"
                        >
                            <svg v-if="isLoading" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                            <span v-else>{{ $t('Save') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </SettingLayout>
</template>
<script setup>
    import SettingLayout from "./Layout.vue";
    import axios from "axios";
    import { computed, ref, watch } from 'vue';
    import { useForm } from "@inertiajs/vue3";
    import { useI18n } from 'vue-i18n';
    import draggable from 'vuedraggable';
    import Modal from '@/Components/Modal.vue';
    import FormInput from '@/Components/FormInput.vue';
    import FormSelect from '@/Components/FormSelect.vue';
    import FormToggleSwitch from '@/Components/FormToggleSwitch.vue';
    import ContactFieldTable from '@/Components/Tables/ContactFieldTable.vue';
    import UiSectionCard from '@/Components/UI/UiSectionCard.vue';
    const { t } = useI18n();

    const props = defineProps(['rows', 'filters', 'settings', 'modules']);
    const isOpenFormModal = ref(false);
    const label = ref(t('Add contact field'));
    const formUrl = ref('/contact-fields');
    const formMethod = ref('post');
    const config = ref(props.settings.metadata);
    const settings = ref(config.value ? JSON.parse(config.value) : null);
    const isLoading = ref(false);
    const location = ref(settings?.value?.contacts?.location ? settings?.value?.contacts?.location : null);
    let id = 0;

    const form = useForm({
        name: null,
        component: null,
        type: null,
        required: 0,
        options: [
            { value: "", id: 0 },
        ],
    });

    const form2 = useForm({
        location: null,
    });

    const openModal = (key, formData = {}) => {
        label.value = t('Add contact field');
        formUrl.value = '/contact-fields';
        formMethod.value = 'post';

        if (key != null) {
            label.value = t('Edit contact field');
            formUrl.value = '/contact-fields/' + key;
            formMethod.value = 'put';
            getRow();
        } else {
            id = 0;
            form.name = null;
            form.type = null;
            form.options = [
                { value: "", id: 0 },
            ];
            isOpenFormModal.value = true;
        }
    }

    function getRow() {
        axios.get(formUrl.value).then((response) => {
            const { data } = response;

            if(data.item.type === 'select'){
                form['name'] = data.item.name,
                form['component'] = data.item.type;
                form['required'] = data.item.required;

                const inputString = data.item.value;
                const transformedArray = inputString.split(', ').map((value, index) => ({
                    id: index,
                    value: value
                }));
                id = transformedArray.length - 1;
                form['options'] = transformedArray;
            } else if(data.item.type === 'input'){
                form['name'] = data.item.name,
                form['component'] = data.item.type;
                form['type'] = data.item.value;
                form['required'] = data.item.required;
            } else {
                form['name'] = data.item.name,
                form['component'] = data.item.type;
                form['required'] = data.item.required;
            }

            isOpenFormModal.value = true;
        })
        .catch(() => {
            // Keep existing custom-field form state when the row cannot be loaded.
        });
    }

    const formInputs = [
        {
            inputType: 'FormInput',
            name: 'name',
            label: t('Name'),
            type: 'text',
            className: 'sm:col-span-6',
        },
        {
            inputType: 'FormSelect',
            name: 'type',
            label: t('Type'),
            options: [
                { value: 'text', label: t('Text') },
                { value: 'number', label: t('Number') },
                { value: 'email', label: t('Email') },
                { value: 'url', label: t('URL') },
                { value: 'date', label: t('Date') },
                { value: 'time', label: t('Time') },
                { value: 'datetime-local', label: t('Date & Time Local') },
            ],
            className: 'sm:col-span-6',
        },
    ];

    const inputTypeOptions = computed(() => ([
        { label: t('Text'), value: 'text' },
        { label: t('Number'), value: 'number' },
        { label: t('Email'), value: 'email' },
        { label: t('URL'), value: 'url' },
        { label: t('Date'), value: 'date' },
        { label: t('Time'), value: 'time' },
        { label: t('Date & time'), value: 'datetime-local' },
    ]));

    const componentOptions = computed(() => ([
        { label: t('Input'), value: 'input' },
        { label: t('Select box'), value: 'select' },
        { label: t('Text area'), value: 'textarea' },
        { label: t('Checkbox'), value: 'checkbox' },
    ]));

    const locationOptions = computed(() => ([
        { label: t('Before address'), value: 'before' },
        { label: t('After address'), value: 'after' },
    ]));

    const toggleRequiredInput = (value) => {
        form.required = value ? 1 : 0;
    };

    const dragging = ref(false);

    const removeAt = (idx) => {
        form.options.splice(idx, 1);
    };

    const add = () => {
        id++;
        form.options.push({ id, value: "" });
    };

    const draggingInfo = () => {
        return dragging.value ? "under drag" : "";
    };

    const submitForm = async () => {
        isLoading.value = true;

        if(formMethod.value == 'post'){
            form.post(formUrl.value, {
                preserveScroll: true,
                onFinish: () => {
                    isLoading.value = false;
                },
                onSuccess: () => {
                    isOpenFormModal.value = false;
                }
            })
        } else {
            form.put(formUrl.value, {
                preserveScroll: true,
                onFinish: () => {
                    isLoading.value = false;
                },
                onSuccess: () => {
                    isOpenFormModal.value = false;
                }
            })
        }
    };

    watch(location, (newValue, oldValue) => {
        if (newValue !== oldValue) {
            form2.location = location;

            submitForm2();
        }
    });

    const submitForm2 = async () => {
        form2.post('/settings/contacts', {
            preserveScroll: true,
        })
    };
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

.settings-row-control--start {
    margin-inline-end: auto;
}

.settings-field-label {
    display: block;
    font-size: 0.98rem;
    font-weight: 600;
    color: var(--ui-text);
}

.settings-save-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.7rem;
    padding: 0.55rem 1.1rem;
    font-size: 0.85rem;
    font-weight: 700;
    color: #fff;
    background: var(--ui-secondary);
    transition: filter 160ms ease;
}

.settings-save-btn:hover {
    filter: brightness(1.05);
}

.settings-modal-chip-btn {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.3rem 0.8rem;
    font-size: 0.78rem;
    font-weight: 700;
    color: #fff;
    background: var(--ui-secondary);
}

.settings-icon-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.1rem;
    height: 2.1rem;
    border-radius: 999px;
    color: var(--ui-muted);
    cursor: pointer;
    transition: background-color 160ms ease, color 160ms ease;
}

.settings-icon-action:hover {
    background: color-mix(in srgb, #fff 86%, var(--ui-secondary) 14%);
    color: var(--ui-secondary);
}

.dark .settings-icon-action:hover {
    background: color-mix(in srgb, var(--ui-bg) 80%, var(--ui-secondary) 20%);
}

.settings-icon-action--danger:hover {
    background: color-mix(in srgb, #fff 86%, var(--ui-danger) 14%) !important;
    color: var(--ui-danger) !important;
}

.dark .settings-icon-action--danger:hover {
    background: color-mix(in srgb, var(--ui-bg) 80%, var(--ui-danger) 20%) !important;
}

.settings-modal-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.7rem;
    padding: 0.55rem 1.1rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: background-color 160ms ease, filter 160ms ease;
}

.settings-modal-btn--ghost {
    color: var(--ui-text);
    background: var(--ui-surface-soft);
    border: 1px solid var(--ui-border);
}

.settings-modal-btn--ghost:hover {
    background: var(--ui-border);
}

.settings-modal-btn--solid {
    color: #fff;
    background: var(--ui-secondary);
}

.settings-modal-btn--solid:hover {
    filter: brightness(1.05);
}

.settings-modal-btn--solid:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

@media (min-width: 768px) {
    .settings-row {
        flex-direction: row;
        align-items: center;
    }

    .settings-row-control--narrow {
        width: 40%;
    }
}
</style>
