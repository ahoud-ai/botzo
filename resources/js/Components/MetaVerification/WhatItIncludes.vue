<script setup>
import SectionBadge from "@/Components/SectionBadge.vue";

const items = [
    "Reviewing business activity data before submission",
    "Preparing the verification request and following up",
    "Notifying the client of any feedback from Meta",
    "Guiding the client to address feedback within the same request",
];
</script>

<template>
    <section v-reveal class="px-5 md:px-10 lg:px-20 2xl:px-60 py-12 md:py-16 lg:py-20">
        <div class="flex flex-col items-center gap-8 lg:gap-[72px]">
            <div class="flex w-full flex-col items-center gap-4 text-center lg:w-[1120px]">
                <SectionBadge :label="$t('What Botzo Provides')" />
                <h2 class="w-full text-[22px] font-semibold leading-[29.9px] text-black dark:text-white lg:text-[46px] lg:leading-[56px]">
                    {{ $t('What does the service include?') }}
                </h2>
            </div>

            <div class="grid w-full grid-cols-1 gap-4 sm:grid-cols-2 lg:w-[988px]">
                <div
                    v-for="item in items"
                    :key="item"
                    class="flex min-h-[91px] items-center justify-center rounded-xl bg-[rgba(37,211,102,0.12)] p-4 text-center sm:justify-end sm:text-right"
                >
                    <p dir="auto" class="text-lg leading-[29.9px] text-black dark:text-white">{{ $t(item) }}</p>
                </div>
            </div>
        </div>
    </section>
</template>
