<script setup>
import SectionBadge from "@/Components/SectionBadge.vue";

const terms = [
    ["Refund Policy", "Fees are non-refundable once the service has started, due to the effort involved."],
    ["Meta's Decision", "The acceptance or rejection decision belongs solely to Meta and cannot be guaranteed"],
    ["Start of Execution", "Work begins within one day of completing payment and providing documents."],
    ["Out of Service Scope", "Creating accounts, managing ads, or any other services outside the scope of this service."],
];
</script>

<template>
    <section v-reveal class="px-5 md:px-10 lg:px-20 2xl:px-60 py-12 md:py-16 lg:py-20">
        <div class="flex flex-col items-center gap-8 lg:gap-[72px]">
            <div class="flex w-full flex-col items-center gap-4 text-center lg:w-[1120px]">
                <SectionBadge :label="$t('Botzo Terms')" />
                <h2 class="w-full text-[22px] font-semibold leading-[29.9px] text-black dark:text-white lg:text-[46px] lg:leading-[56px]">
                    {{ $t('Terms and Conditions') }}
                </h2>
            </div>

            <div class="grid w-full grid-cols-1 gap-4 sm:grid-cols-2 lg:w-[1120px] lg:grid-cols-4">
                <div
                    v-for="[headingKey, descriptionKey] in terms"
                    :key="headingKey"
                    class="flex min-h-[197px] flex-col items-end justify-center gap-2 rounded-3xl bg-[rgba(37,211,102,0.12)] p-4 text-right"
                >
                    <p dir="auto" class="w-full text-lg font-semibold leading-[29.9px] text-black dark:text-white">{{ $t(headingKey) }}</p>
                    <p dir="auto" class="w-full text-sm leading-6 text-[#445566] dark:text-[#94a3b8]">{{ $t(descriptionKey) }}</p>
                </div>
            </div>
        </div>
    </section>
</template>
