<script setup>
import { nextTick, reactive, ref, watch } from "vue";
import { TransitionRoot, TransitionChild, Dialog, DialogPanel } from "@headlessui/vue";
import { useForm } from "@inertiajs/vue3";

const props = defineProps({
    isOpen: { type: Boolean, default: false },
});

const emit = defineEmits(["close"]);

const DOCUMENT_FIELDS = [
    { key: "commercial_register_document", label: "Commercial register document" },
    { key: "id_proof_document", label: "Owner's ID proof" },
    { key: "ownership_proof_document", label: "Proof of business ownership" },
];

const form = useForm({
    full_name: "",
    business_name: "",
    legal_company_name: "",
    commercial_register_number: "",
    phone: "",
    whatsapp_number: "",
    email: "",
    website_url: "",
    notes: "",
    commercial_register_document: null,
    id_proof_document: null,
    ownership_proof_document: null,
});

const submitted = ref(false);
const firstFieldRef = ref(null);
const documentFileNames = reactive({
    commercial_register_document: null,
    id_proof_document: null,
    ownership_proof_document: null,
});

const closeModal = () => {
    emit("close");
};

const onDocumentChange = (key, event) => {
    const file = event.target.files[0] ?? null;
    form[key] = file;
    documentFileNames[key] = file?.name ?? null;
};

const onDocumentDrop = (key, event) => {
    event.preventDefault();
    const file = event.dataTransfer.files[0] ?? null;
    if (!file) return;
    form[key] = file;
    documentFileNames[key] = file.name;
};

const removeDocument = (key) => {
    form[key] = null;
    documentFileNames[key] = null;
};

const submit = () => {
    form.post("/meta-verification-requests", {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            DOCUMENT_FIELDS.forEach((field) => { documentFileNames[field.key] = null; });
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
                        <DialogPanel class="max-h-[90vh] w-full max-w-[860px] overflow-y-auto rounded-3xl border border-[#cfd8e3] bg-white p-8 text-right shadow-2xl transition-all dark:border-[#1e2a3a] dark:bg-[#0a0f17] md:p-10">
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
                                        <label dir="auto" class="w-full text-right text-sm font-medium text-black dark:text-white">{{ $t('Legal company name') }}</label>
                                        <input
                                            v-model="form.legal_company_name"
                                            type="text"
                                            dir="auto"
                                            :disabled="form.processing"
                                            :placeholder="$t('As registered with the Ministry of Commerce')"
                                            class="h-12 w-full rounded-xl border border-[#cfd8e3] bg-white px-3.5 text-sm text-black placeholder:text-[#8899aa] focus:border-[#25d366] focus:outline-none focus:ring-2 focus:ring-[#25d366]/20 disabled:opacity-60 dark:border-[#1e2a3a] dark:bg-[#0d1117] dark:text-white"
                                        >
                                        <p v-if="form.errors.legal_company_name" class="w-full text-xs text-red-500">{{ form.errors.legal_company_name }}</p>
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
                                        <label dir="auto" class="w-full text-right text-sm font-medium text-black dark:text-white">{{ $t('WhatsApp Business number') }}</label>
                                        <input
                                            v-model="form.whatsapp_number"
                                            type="tel"
                                            dir="ltr"
                                            :disabled="form.processing"
                                            placeholder="0112345678"
                                            class="h-12 w-full rounded-xl border border-[#cfd8e3] bg-white px-3.5 text-right text-sm text-black placeholder:text-[#8899aa] focus:border-[#25d366] focus:outline-none focus:ring-2 focus:ring-[#25d366]/20 disabled:opacity-60 dark:border-[#1e2a3a] dark:bg-[#0d1117] dark:text-white"
                                        >
                                        <p v-if="form.errors.whatsapp_number" class="w-full text-xs text-red-500">{{ form.errors.whatsapp_number }}</p>
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

                                    <div class="flex flex-col items-end gap-2">
                                        <label dir="auto" class="w-full text-right text-sm font-medium text-black dark:text-white">{{ $t('Website (optional)') }}</label>
                                        <input
                                            v-model="form.website_url"
                                            type="url"
                                            dir="ltr"
                                            :disabled="form.processing"
                                            placeholder="https://example.com"
                                            class="h-12 w-full rounded-xl border border-[#cfd8e3] bg-white px-3.5 text-right text-sm text-black placeholder:text-[#8899aa] focus:border-[#25d366] focus:outline-none focus:ring-2 focus:ring-[#25d366]/20 disabled:opacity-60 dark:border-[#1e2a3a] dark:bg-[#0d1117] dark:text-white"
                                        >
                                        <p v-if="form.errors.website_url" class="w-full text-xs text-red-500">{{ form.errors.website_url }}</p>
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

                                    <div
                                        v-for="field in DOCUMENT_FIELDS"
                                        :key="field.key"
                                        class="col-span-1 flex flex-col items-end gap-2 sm:col-span-2"
                                    >
                                        <label dir="auto" :for="`meta-verification-${field.key}`" class="w-full text-right text-sm font-medium text-black dark:text-white">{{ $t(field.label) }}</label>
                                        <input
                                            type="file"
                                            class="sr-only"
                                            accept=".pdf,.jpg,.jpeg,.png"
                                            :id="`meta-verification-${field.key}`"
                                            :disabled="form.processing"
                                            @change="onDocumentChange(field.key, $event)"
                                        >
                                        <label
                                            :for="`meta-verification-${field.key}`"
                                            @dragover.prevent
                                            @drop="onDocumentDrop(field.key, $event)"
                                            class="flex w-full cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border border-dashed border-[#cfd8e3] bg-white px-3.5 py-5 text-center text-sm text-[#8899aa] transition-colors hover:border-[#25d366] dark:border-[#1e2a3a] dark:bg-[#0d1117]"
                                        >
                                            <template v-if="documentFileNames[field.key]">
                                                <span class="font-medium text-black dark:text-white" dir="ltr">{{ documentFileNames[field.key] }}</span>
                                                <button type="button" class="text-xs font-semibold text-red-500 hover:underline" @click.prevent="removeDocument(field.key)">{{ $t('Remove') }}</button>
                                            </template>
                                            <template v-else>
                                                <span>{{ $t('Click to upload or drag and drop') }}</span>
                                                <span class="text-xs">{{ $t('PDF, JPG or PNG · up to 10MB') }}</span>
                                            </template>
                                        </label>
                                        <p v-if="form.errors[field.key]" class="w-full text-xs text-red-500">{{ form.errors[field.key] }}</p>
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
