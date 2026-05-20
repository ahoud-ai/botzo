<script setup>
import { ref } from 'vue';
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue';
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import { useI18n } from 'vue-i18n';

const { t, te } = useI18n();

const props = defineProps({
    modelValue: Boolean,
    label: String,
    description: String,
    confirmButtonText: {
        type: String,
        default: 'Confirm',
    },
    confirmButtonClass: {
        type: String,
        default: 'bg-red-600 hover:bg-red-500',
    },
    icon: {
        type: String,
        default: 'warning',
    },
});

const isLoading = ref(false);
const emit = defineEmits(['update:modelValue', 'confirm']);

function confirm() {
    if (isLoading.value) {
        return;
    }

    isLoading.value = true;
    emit('confirm');
    isLoading.value = false;
}

function onClose() {
    emit('update:modelValue', false);
}

function resolveText(value) {
    if (!value) {
        return '';
    }

    return te(value) ? t(value) : value;
}
</script>

<template>
    <TransitionRoot as="template" :show="modelValue">
        <Dialog as="div" class="relative ui-layer-modal" @close="onClose">
            <TransitionChild
                as="template"
                enter="ease-out duration-200"
                enter-from="opacity-0"
                enter-to="opacity-100"
                leave="ease-in duration-150"
                leave-from="opacity-100"
                leave-to="opacity-0"
            >
                <div class="fixed inset-0 bg-slate-950/40 backdrop-blur-sm transition-opacity" />
            </TransitionChild>

            <div class="fixed inset-0 ui-layer-modal w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-6">
                    <TransitionChild
                        as="template"
                        enter="ease-out duration-200"
                        enter-from="translate-y-4 opacity-0 sm:translate-y-0 sm:scale-95"
                        enter-to="translate-y-0 opacity-100 sm:scale-100"
                        leave="ease-in duration-150"
                        leave-from="translate-y-0 opacity-100 sm:scale-100"
                        leave-to="translate-y-4 opacity-0 sm:translate-y-0 sm:scale-95"
                    >
                        <DialogPanel class="relative w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white text-start shadow-2xl">
                            <div class="h-1.5 bg-gradient-to-r from-sky-500 via-indigo-500 to-rose-500" />

                            <div class="px-5 pb-5 pt-5 sm:px-6">
                                <div class="flex items-start gap-4">
                                    <div
                                        :class="[
                                            'mt-0.5 flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl border',
                                            icon === 'warning'
                                                ? 'border-rose-200 bg-rose-50 text-rose-600'
                                                : 'border-sky-200 bg-sky-50 text-sky-600',
                                        ]"
                                    >
                                        <ExclamationTriangleIcon class="h-5 w-5" aria-hidden="true" />
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <DialogTitle as="h3" class="text-base font-semibold text-slate-900">
                                            {{ resolveText(props.label) }}
                                        </DialogTitle>

                                        <p v-if="props.description" class="mt-2 text-sm leading-6 text-slate-600">
                                            {{ resolveText(props.description) }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col-reverse gap-3 border-t border-slate-100 bg-slate-50 px-5 py-4 sm:flex-row sm:justify-end sm:px-6">
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100"
                                    @click="onClose"
                                >
                                    {{ $t('Cancel') }}
                                </button>

                                <button
                                    type="button"
                                    :disabled="isLoading"
                                    :class="[
                                        'inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition disabled:cursor-not-allowed disabled:opacity-70',
                                        confirmButtonClass,
                                    ]"
                                    @click="confirm"
                                >
                                    <svg
                                        v-if="isLoading"
                                        xmlns="http://www.w3.org/2000/svg"
                                        width="18"
                                        height="18"
                                        viewBox="0 0 24 24"
                                        class="animate-spin"
                                    >
                                        <path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".3" />
                                        <path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z" />
                                    </svg>
                                    <span v-else>{{ resolveText(confirmButtonText) }}</span>
                                </button>
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>
  
