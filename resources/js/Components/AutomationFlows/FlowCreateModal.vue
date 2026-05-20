<template>
    <Modal :is-open="open" :label="$t('Create Automation')" :close-btn="true" @close="$emit('close')">
        <div class="w-full max-w-lg p-2 sm:p-3">
            <div class="mb-4 flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-xl font-semibold text-slate-950">{{ $t('Create Automation') }}</h3>
                    <p class="mt-1.5 text-sm text-slate-500">{{ $t('Start with a ready-to-edit WhatsApp journey that matches the goal you choose.') }}</p>
                </div>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <FormInput v-model="form.name" :label="$t('Name')" :placeholder="namePlaceholder" :error="errors.name" />

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">{{ $t('Goal') }}</label>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <button
                            v-for="option in presetOptions"
                            :key="option.value"
                            type="button"
                            class="rounded-2xl border px-3.5 py-3 text-start transition"
                            :class="form.goal_preset === option.value
                                ? 'border-emerald-300 bg-emerald-50 text-emerald-900'
                                : 'border-slate-200 bg-slate-50 text-slate-700 hover:border-slate-300 hover:bg-white'"
                            @click="form.goal_preset = option.value"
                        >
                            <div class="font-medium">{{ option.label }}</div>
                            <div class="mt-1 text-xs leading-5 line-clamp-2" :class="form.goal_preset === option.value ? 'text-emerald-700' : 'text-slate-500'">
                                {{ option.description }}
                            </div>
                        </button>
                    </div>
                    <p class="mt-2 text-xs leading-5 text-slate-500">
                        {{ $t('This creates a ready-to-edit starter journey for the selected goal. You can still change every step after creation.') }}
                    </p>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">{{ $t('Description') }}</label>
                    <FlowAutosizeTextarea v-model="form.description" rows="3" class="w-full min-h-[92px] rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm leading-6 outline-none transition focus:border-primary focus:bg-white" :placeholder="descriptionPlaceholder" />
                    <p v-if="errors.description" class="mt-2 text-sm text-rose-600">{{ errors.description }}</p>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <AutomationButton variant="secondary" @click="$emit('close')">
                        {{ $t('Cancel') }}
                    </AutomationButton>
                    <AutomationButton type="submit" variant="primary" :disabled="loading">
                        {{ loading ? $t('Creating...') : $t('Create') }}
                    </AutomationButton>
                </div>
            </form>
        </div>
    </Modal>
</template>

<script setup>
import { computed, reactive, watch } from 'vue';
import axios from 'axios';
import { useI18n } from 'vue-i18n';
import Modal from '@/Components/Modal.vue';
import FormInput from '@/Components/FormInput.vue';
import FlowAutosizeTextarea from '@/Components/AutomationFlows/FlowAutosizeTextarea.vue';
import AutomationButton from '@/Components/AutomationFlows/Ui/AutomationButton.vue';
import { flowGoalPresetLabel, flowGoalPresetOptions, flowGoalPresetPlaceholder } from '@/Components/AutomationFlows/flowBuilderGoalPresets.js';

const { t } = useI18n();

const emit = defineEmits(['close', 'created', 'update:loading']);

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    initialGoalPreset: {
        type: String,
        default: 'sales_qualification',
    },
});

const form = reactive({
    name: '',
    description: '',
    goal_preset: 'sales_qualification',
});

const errors = reactive({});
const presetOptions = computed(() => flowGoalPresetOptions(t));
const namePlaceholder = computed(() => flowGoalPresetLabel(form.goal_preset, t));
const descriptionPlaceholder = computed(() => flowGoalPresetPlaceholder(form.goal_preset, t));

watch(() => props.open, (value) => {
    if (value) {
        form.name = '';
        form.description = '';
        form.goal_preset = props.initialGoalPreset || 'sales_qualification';
        Object.keys(errors).forEach((key) => delete errors[key]);
    }
});

const submit = async () => {
    emit('update:loading', true);
    Object.keys(errors).forEach((key) => delete errors[key]);

    try {
        const response = await axios.post('/automation/flows', form);
        emit('created', response.data);
    } catch (error) {
        const validation = error?.response?.data?.errors || {};
        Object.entries(validation).forEach(([key, value]) => {
            errors[key] = Array.isArray(value) ? value[0] : value;
        });

        if (!Object.keys(validation).length) {
            errors.name = error?.response?.data?.message || t('Unable to create the automation right now.');
        }
    } finally {
        emit('update:loading', false);
    }
};
</script>
