<script setup>
import { computed } from "vue";
import SectionBadge from "@/Components/SectionBadge.vue";

const props = defineProps({
    companyConfig: { type: Object, default: () => ({}) },
});

defineEmits(["request-service"]);

const primaryPhone = computed(() => {
    const config = props.companyConfig || {};
    return config.frontend_contact_phone_primary || config.phone || null;
});

const buildWhatsappHref = (message) => {
    if (!primaryPhone.value) return null;
    const cleanNumber = String(primaryPhone.value).replace(/[^\d+]/g, "");
    return `https://wa.me/${cleanNumber}?text=${encodeURIComponent(message)}`;
};

const buildEmailHref = (subject) => {
    const email = props.companyConfig?.email;
    if (!email) return null;
    return `mailto:${email}?subject=${encodeURIComponent(subject)}`;
};

const agreementWhatsappHref = computed(() => buildWhatsappHref("مرحبًا، مرفق اتفاقية خدمة التحقق من الحساب التجاري عبر Meta موقّعة."));
const agreementEmailHref = computed(() => buildEmailHref("اتفاقية خدمة التحقق من الحساب التجاري عبر Meta - موقّعة"));

const paymentWhatsappHref = computed(() => buildWhatsappHref("مرحبًا، مرفق إثبات سداد قيمة خدمة التحقق من الحساب التجاري عبر Meta."));
const paymentEmailHref = computed(() => buildEmailHref("إثبات سداد - خدمة التحقق من الحساب التجاري عبر Meta"));

const steps = [
    {
        title: "Request the service",
        description: "Get in touch with us and let us know you'd like to request the Meta business account verification service.",
        actionType: "request",
    },
    {
        title: "Download the agreement, sign it, and send it back",
        description: "Download a copy of the agreement, sign it, and send it back to us via WhatsApp or email so we can begin.",
        highlight: true,
        actionType: "agreement",
    },
    {
        title: "Payment confirmation and data review",
        description: "Once payment is confirmed, we review your business activity data within 1 to 3 business days.",
        actionType: "payment",
    },
    {
        title: "Preparing and submitting the request",
        description: "We prepare the verification request and submit it to Meta within 2 to 5 business days.",
    },
    {
        title: "Following up on Meta's decision",
        description: "We follow up until a final decision is reached, which can take 3 to 10 days depending on Meta's review.",
    },
];
</script>

<template>
    <section v-reveal class="px-5 md:px-10 lg:px-20 2xl:px-60 py-12 md:py-16 lg:py-20">
        <div class="flex flex-col items-center gap-8 lg:gap-[72px]">
            <div class="flex w-full flex-col items-center gap-4 text-center lg:w-[1120px]">
                <SectionBadge :label="$t('How It Works')" />
                <h2 class="w-full text-[22px] font-semibold leading-[29.9px] text-black dark:text-white lg:text-[46px] lg:leading-[56px]">
                    {{ $t('How to request the service') }}
                </h2>
            </div>

            <div class="flex w-full flex-col gap-4 lg:w-[860px]">
                <div
                    v-for="(step, index) in steps"
                    :key="step.title"
                    class="flex w-full items-start gap-4 rounded-3xl border p-6 text-right transition-colors duration-300"
                    :class="step.highlight
                        ? 'border-[#25d366] bg-[rgba(37,211,102,0.12)]'
                        : 'border-[#cfd8e3] bg-white dark:border-[#1e2a3a] dark:bg-[#0a0f17]'"
                >
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-base font-semibold text-[#04130a]"
                        style="background-image: linear-gradient(135deg, #25d366 0%, #1db954 100%)"
                    >
                        {{ index + 1 }}
                    </div>
                    <div class="flex flex-1 flex-col items-end gap-2 pt-1">
                        <h3 dir="auto" class="w-full text-lg font-semibold text-black dark:text-white">
                            {{ $t(step.title) }}
                        </h3>
                        <p dir="auto" class="w-full text-sm leading-6 text-[#445566] dark:text-[#94a3b8]">
                            {{ $t(step.description) }}
                        </p>

                        <div v-if="step.actionType === 'request'" class="flex w-full flex-wrap items-center justify-end gap-3 pt-2" dir="ltr">
                            <button
                                type="button"
                                @click="$emit('request-service')"
                                class="flex h-11 items-center justify-center rounded-xl px-5 transition-transform duration-200 hover:scale-[1.03]"
                                style="background-image: linear-gradient(135deg, #25d366 0%, #1db954 100%)"
                            >
                                <span dir="auto" class="whitespace-nowrap text-sm font-semibold text-[#04130a]">{{ $t('Request the service now') }}</span>
                            </button>
                        </div>

                        <div v-else-if="step.actionType === 'agreement'" class="flex w-full flex-wrap items-center justify-end gap-3 pt-2" dir="ltr">
                            <a
                                href="/meta-verification/agreement/pdf"
                                class="flex h-11 items-center justify-center rounded-xl px-5 transition-transform duration-200 hover:scale-[1.03]"
                                style="background-image: linear-gradient(135deg, #25d366 0%, #1db954 100%)"
                            >
                                <span dir="auto" class="whitespace-nowrap text-sm font-semibold text-[#04130a]">{{ $t('Download the agreement') }}</span>
                            </a>
                            <a
                                v-if="agreementWhatsappHref"
                                :href="agreementWhatsappHref"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="flex h-11 items-center justify-center rounded-xl border border-[#cfd8e3] px-5 transition-colors hover:border-[#25d366] dark:border-[#1e2a3a]"
                            >
                                <span dir="auto" class="whitespace-nowrap text-sm font-semibold text-black dark:text-white">{{ $t('Send via WhatsApp') }}</span>
                            </a>
                            <a
                                v-if="agreementEmailHref"
                                :href="agreementEmailHref"
                                class="flex h-11 items-center justify-center rounded-xl border border-[#cfd8e3] px-5 transition-colors hover:border-[#25d366] dark:border-[#1e2a3a]"
                            >
                                <span dir="auto" class="whitespace-nowrap text-sm font-semibold text-black dark:text-white">{{ $t('Send via Email') }}</span>
                            </a>
                        </div>

                        <div v-else-if="step.actionType === 'payment'" class="flex w-full flex-wrap items-center justify-end gap-3 pt-2" dir="ltr">
                            <a
                                v-if="paymentWhatsappHref"
                                :href="paymentWhatsappHref"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="flex h-11 items-center justify-center rounded-xl border border-[#cfd8e3] px-5 transition-colors hover:border-[#25d366] dark:border-[#1e2a3a]"
                            >
                                <span dir="auto" class="whitespace-nowrap text-sm font-semibold text-black dark:text-white">{{ $t('Send payment confirmation via WhatsApp') }}</span>
                            </a>
                            <a
                                v-if="paymentEmailHref"
                                :href="paymentEmailHref"
                                class="flex h-11 items-center justify-center rounded-xl border border-[#cfd8e3] px-5 transition-colors hover:border-[#25d366] dark:border-[#1e2a3a]"
                            >
                                <span dir="auto" class="whitespace-nowrap text-sm font-semibold text-black dark:text-white">{{ $t('Send payment confirmation via Email') }}</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
