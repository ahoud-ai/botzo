<script setup>
import { nextTick, ref, watch } from "vue";
import { TransitionRoot, TransitionChild, Dialog, DialogPanel } from "@headlessui/vue";
import { useForm } from "@inertiajs/vue3";

const props = defineProps({
    isOpen: { type: Boolean, default: false },
});

const emit = defineEmits(["close"]);

const form = useForm({
    full_name: "",
    business_name: "",
    commercial_register_number: "",
    phone: "",
    email: "",
    notes: "",
});

const submitted = ref(false);
const firstFieldRef = ref(null);

const closeModal = () => {
    emit("close");
};

const submit = () => {
    form.post("/meta-verification-requests", {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            submitted.value = true;
        },
    });
};

// Reset form/validation/success state each time the modal is (re)opened, and
// focus the first field so keyboard users can start typing immediately.
watch(() => props.isOpen, (open) => {
    if (!open) return;
    form.clearErrors();
    submitted.value = false;
    nextTick(() => firstFieldRef.value?.focus());
});
</script>

<template>
    <TransitionRoot appear :show="props.isOpen" as="template">
        <Dialog as="div" class="relative ui-layer-modal" @close="closeModal">
            <TransitionChild
                as="template"
                enter="duration-300 ease-out"
                enter-from="opacity-0"
                enter-to="opacity-100"
                leave="duration-200 ease-in"
                leave-from="opacity-100"
                leave-to="opacity-0"
            >
                <div class="fixed inset-0 ui-layer-modal-backdrop backdrop-blur-sm" />
            </TransitionChild>

            <div class="fixed inset-0 overflow-y-auto ui-layer-modal">
                <div class="flex min-h-full items-center justify-center p-4">
                    <TransitionChild
                        as="template"
                        enter="duration-400 ease-[cubic-bezier(0.16,1,0.3,1)]"
                        enter-from="opacity-0 scale-90 translate-y-6"
                        enter-to="opacity-100 scale-100 translate-y-0"
                        leave="duration-200 ease-in"
                        leave-from="opacity-100 scale-100 translate-y-0"
                        leave-to="opacity-0 scale-95 translate-y-2"
                    >
                        <DialogPanel class="w-full max-w-[760px] rounded-3xl border border-[#cfd8e3] bg-white p-8 text-right shadow-2xl transition-all dark:border-[#1e2a3a] dark:bg-[#0a0f17] md:p-10">
                            <!-- Success state -->
                            <div v-if="submitted" class="flex flex-col items-center gap-5 py-6 text-center">
                                <span class="flex h-16 w-16 items-center justify-center rounded-full bg-[rgba(37,211,102,0.12)]">
                                    <svg class="h-8 w-8 text-[#25d366]" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </span>
                                <div class="flex flex-col items-center gap-2">
                                    <h2 dir="auto" class="text-lg font-bold text-black dark:text-white">{{ $t('Request received!') }}</h2>
                                    <p dir="auto" class="max-w-[380px] text-sm leading-6 text-[#8899aa]">
                                        {{ $t("Thanks — our team will reach out to you shortly.") }}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="flex h-12 w-full max-w-[220px] items-center justify-center rounded-2xl text-base font-semibold text-[#04130a] transition-transform duration-200 hover:scale-[1.02]"
                                    style="background-image: linear-gradient(135deg, #25d366 0%, #1db954 100%)"
                                    @click="closeModal"
                                >
                                    {{ $t('Close') }}
                                </button>
                            </div>

                            <!-- Form state -->
                            <form v-else @submit.prevent="submit" class="flex flex-col items-end gap-7">
                                <div class="flex w-full items-start justify-between">
                                    <button
                                        type="button"
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-[#8899aa] transition-colors hover:bg-black/5 hover:text-black dark:hover:bg-white/5 dark:hover:text-white"
                                        @click="closeModal"
                                    >
                                        <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M14 4L4 14M4 4L14 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </button>
                                    <div class="flex flex-1 flex-col items-end gap-1">
                                        <h2 dir="auto" class="w-full text-xl font-bold text-black dark:text-white">{{ $t('Request the Meta verification service') }}</h2>
                                        <p dir="auto" class="w-full text-sm leading-6 text-[#8899aa]">
                                            {{ $t("Enter your business details and we'll get in touch with you as soon as possible.") }}
                                        </p>
                                    </div>
                                </div>

                                <div class="grid w-full grid-cols-1 gap-5 sm:grid-cols-2">
                                    <div class="flex flex-col items-end gap-2">
                                        <label dir="auto" class="w-full text-right text-sm font-medium text-black dark:text-white">{{ $t('Full name') }}</label>
                                        <input
                                            ref="firstFieldRef"
                                            v-model="form.full_name"
                                            type="text"
                                            dir="auto"
                                            :disabled="form.processing"
                                            :placeholder="$t('e.g. Mariam Radwan')"
                                            class="h-12 w-full rounded-xl border border-[#cfd8e3] bg-white px-3.5 text-sm text-black placeholder:text-[#8899aa] focus:border-[#25d366] focus:outline-none focus:ring-2 focus:ring-[#25d366]/20 disabled:opacity-60 dark:border-[#1e2a3a] dark:bg-[#0d1117] dark:text-white"
                                        >
                                        <p v-if="form.errors.full_name" class="w-full text-xs text-red-500">{{ form.errors.full_name }}</p>
                                    </div>

                                    <div class="flex flex-col items-end gap-2">
                                        <label dir="auto" class="w-full text-right text-sm font-medium text-black dark:text-white">{{ $t('Business name') }}</label>
                                        <input
                                            v-model="form.business_name"
                                            type="text"
                                            dir="auto"
                                            :disabled="form.processing"
                                            :placeholder="$t('e.g. Fashion store')"
                                            class="h-12 w-full rounded-xl border border-[#cfd8e3] bg-white px-3.5 text-sm text-black placeholder:text-[#8899aa] focus:border-[#25d366] focus:outline-none focus:ring-2 focus:ring-[#25d366]/20 disabled:opacity-60 dark:border-[#1e2a3a] dark:bg-[#0d1117] dark:text-white"
                                        >
                                        <p v-if="form.errors.business_name" class="w-full text-xs text-red-500">{{ form.errors.business_name }}</p>
                                    </div>

                                    <div class="flex flex-col items-end gap-2">
                                        <label dir="auto" class="w-full text-right text-sm font-medium text-black dark:text-white">{{ $t('Phone number') }}</label>
                                        <input
                                            v-model="form.phone"
                                            type="tel"
                                            dir="ltr"
                                            :disabled="form.processing"
                                            placeholder="0112345678"
                                            class="h-12 w-full rounded-xl border border-[#cfd8e3] bg-white px-3.5 text-right text-sm text-black placeholder:text-[#8899aa] focus:border-[#25d366] focus:outline-none focus:ring-2 focus:ring-[#25d366]/20 disabled:opacity-60 dark:border-[#1e2a3a] dark:bg-[#0d1117] dark:text-white"
                                        >
                                        <p v-if="form.errors.phone" class="w-full text-xs text-red-500">{{ form.errors.phone }}</p>
                                    </div>

                                    <div class="flex flex-col items-end gap-2">
                                        <label dir="auto" class="w-full text-right text-sm font-medium text-black dark:text-white">{{ $t('Email address') }}</label>
                                        <input
                                            v-model="form.email"
                                            type="email"
                                            dir="ltr"
                                            :disabled="form.processing"
                                            placeholder="x@gmail.com"
                                            class="h-12 w-full rounded-xl border border-[#cfd8e3] bg-white px-3.5 text-right text-sm text-black placeholder:text-[#8899aa] focus:border-[#25d366] focus:outline-none focus:ring-2 focus:ring-[#25d366]/20 disabled:opacity-60 dark:border-[#1e2a3a] dark:bg-[#0d1117] dark:text-white"
                                        >
                                        <p v-if="form.errors.email" class="w-full text-xs text-red-500">{{ form.errors.email }}</p>
                                    </div>

                                    <div class="col-span-1 flex flex-col items-end gap-2 sm:col-span-2">
                                        <label dir="auto" class="w-full text-right text-sm font-medium text-black dark:text-white">{{ $t('Commercial register number (optional)') }}</label>
                                        <input
                                            v-model="form.commercial_register_number"
                                            type="text"
                                            dir="ltr"
                                            :disabled="form.processing"
                                            placeholder="1010XXXXXX"
                                            class="h-12 w-full rounded-xl border border-[#cfd8e3] bg-white px-3.5 text-right text-sm text-black placeholder:text-[#8899aa] focus:border-[#25d366] focus:outline-none focus:ring-2 focus:ring-[#25d366]/20 disabled:opacity-60 dark:border-[#1e2a3a] dark:bg-[#0d1117] dark:text-white"
                                        >
                                        <p v-if="form.errors.commercial_register_number" class="w-full text-xs text-red-500">{{ form.errors.commercial_register_number }}</p>
                                    </div>

                                    <div class="col-span-1 flex flex-col items-end gap-2 sm:col-span-2">
                                        <label dir="auto" class="w-full text-right text-sm font-medium text-black dark:text-white">{{ $t('Notes') }}</label>
                                        <textarea
                                            v-model="form.notes"
                                            dir="auto"
                                            rows="4"
                                            :disabled="form.processing"
                                            :placeholder="$t('Write here...')"
                                            class="w-full resize-none rounded-xl border border-[#cfd8e3] bg-white px-3.5 py-3 text-sm text-black placeholder:text-[#8899aa] focus:border-[#25d366] focus:outline-none focus:ring-2 focus:ring-[#25d366]/20 disabled:opacity-60 dark:border-[#1e2a3a] dark:bg-[#0d1117] dark:text-white"
                                        ></textarea>
                                        <p v-if="form.errors.notes" class="w-full text-xs text-red-500">{{ form.errors.notes }}</p>
                                    </div>
                                </div>

                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="flex h-[56px] w-full items-center justify-center gap-2 rounded-2xl text-base font-semibold text-[#04130a] transition-transform duration-200 hover:scale-[1.01] disabled:cursor-not-allowed disabled:opacity-70"
                                    style="background-image: linear-gradient(135deg, #25d366 0%, #1db954 100%)"
                                >
                                    <svg v-if="form.processing" class="h-4 w-4 animate-spin text-[#04130a]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                                    {{ form.processing ? $t('Sending...') : $t('Send') }}
                                </button>
                            </form>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>
