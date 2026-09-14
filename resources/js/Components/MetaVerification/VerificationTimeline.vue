<script setup>
import { computed } from "vue";
import SectionBadge from "@/Components/SectionBadge.vue";

const props = defineProps({
    companyConfig: { type: Object, default: () => ({}) },
    metaVerificationRequest: { type: Object, default: null },
});

defineEmits(["request-service"]);

const steps = [
    {
        title: "Request the service",
        description: "Get in touch with us and let us know you'd like to request the Meta business account verification service.",
        actionType: "request",
    },
    {
        title: "Download the agreement, sign it, and upload it back",
        description: "Download a copy of the agreement, sign it, and upload the signed copy from your request tracking dashboard below.",
        actionType: "agreement",
        statuses: ["awaiting_agreement"],
    },
    {
        title: "Payment and data review",
        description: "Complete payment and upload proof of payment from your request tracking dashboard, then we review your business activity data within 1 to 3 business days.",
        statuses: ["awaiting_payment", "under_review"],
    },
    {
        title: "Preparing and submitting the request",
        description: "We prepare the verification request and submit it to Meta within 2 to 5 business days.",
        statuses: ["submitted_to_meta"],
    },
    {
        title: "Following up on Meta's decision",
        description: "We follow up until a final decision is reached, which can take 3 to 10 days depending on Meta's review.",
        statuses: ["meta_reviewing", "approved"],
    },
];

const currentStepIndex = computed(() => {
    const status = props.metaVerificationRequest?.status;
    if (!status || status === "rejected") return null;
    if (status === "requested") return 0;

    const index = steps.findIndex((step) => step.statuses?.includes(status));
    return index === -1 ? null : index;
});

const stepsWithState = computed(() => steps.map((step, index) => {
    if (currentStepIndex.value === null) {
        return { ...step, state: null };
    }

    if (index < currentStepIndex.value) return { ...step, state: "done" };
    if (index === currentStepIndex.value) return { ...step, state: "current" };
    return { ...step, state: "upcoming" };
}));
</script>

<template>
    <section v-reveal class="px-5 md:px-10 lg:px-20 2xl:px-32 py-12 md:py-16 lg:py-20">
        <div class="flex flex-col items-center gap-8 lg:gap-[72px]">
            <div class="flex w-full flex-col items-center gap-4 text-center lg:w-[1280px]">
                <SectionBadge :label="$t('How It Works')" />
                <h2 class="w-full text-[22px] font-semibold leading-[29.9px] text-black dark:text-white lg:text-[46px] lg:leading-[56px]">
                    {{ $t('How to request the service') }}
                </h2>
            </div>

            <div class="flex w-full flex-col gap-4 lg:w-[860px]">
                <div
                    v-for="(step, index) in stepsWithState"
                    :key="step.title"
                    class="relative flex w-full items-start gap-4 rounded-3xl border p-6 text-right transition-colors duration-300"
                    :class="step.state === 'current'
                        ? 'border-[#25d366] bg-[rgba(37,211,102,0.12)]'
                        : 'border-[#cfd8e3] bg-white dark:border-[#1e2a3a] dark:bg-[#0a0f17]'"
                >
                    <span
                        v-if="step.state === 'current'"
                        class="absolute -top-3 end-6 rounded-full px-3 py-1 text-xs font-bold text-[#04130a]"
                        style="background-image: linear-gradient(135deg, #25d366 0%, #1db954 100%)"
                    >
                        {{ $t("You're here") }}
                    </span>

                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-base font-semibold"
                        :class="step.state === 'done' ? 'text-white' : 'text-[#04130a]'"
                        :style="step.state === 'done'
                            ? 'background-image: linear-gradient(135deg, #34d399 0%, #059669 100%)'
                            : 'background-image: linear-gradient(135deg, #25d366 0%, #1db954 100%)'"
                    >
                        <svg v-if="step.state === 'done'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 13 4 4L19 7"/></svg>
                        <template v-else>{{ index + 1 }}</template>
                    </div>
                    <div class="flex flex-1 flex-col items-end gap-2 pt-1">
                        <h3 dir="auto" class="w-full text-lg font-semibold text-black dark:text-white">
                            {{ $t(step.title) }}
                        </h3>
                        <p dir="auto" class="w-full text-sm leading-6 text-[#445566] dark:text-[#94a3b8]">
                            {{ $t(step.description) }}
                        </p>

                        <div v-if="step.actionType === 'request' && !metaVerificationRequest" class="flex w-full flex-wrap items-center justify-end gap-3 pt-2" dir="ltr">
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
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
