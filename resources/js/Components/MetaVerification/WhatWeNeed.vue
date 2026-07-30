<script setup>
import SectionBadge from "@/Components/SectionBadge.vue";

const requirements = [
    ["/images/meta-verification/icon-document-dark.svg", "/images/meta-verification/icon-document-light.svg", "Commercial Registration"],
    ["/images/meta-verification/icon-phone-dark.svg", "/images/meta-verification/icon-phone-light.svg", "Official contact information"],
    ["/images/meta-verification/icon-business-dark.svg", "/images/meta-verification/icon-business-light.svg", "Meta Business account permissions"],
    ["/images/meta-verification/icon-link-dark.svg", "/images/meta-verification/icon-link-light.svg", "Website link (if available)"],
];
</script>

<template>
    <section v-reveal class="px-5 md:px-10 lg:px-20 2xl:px-60 py-12 md:py-16 lg:py-20">
        <div class="flex flex-col items-center gap-8 lg:gap-[72px]">
            <div class="flex w-full flex-col items-center gap-4 text-center lg:w-[1120px]">
                <SectionBadge :label="$t('What Botzo Needs')" />
                <h2 class="w-full text-[22px] font-semibold leading-[29.9px] text-black dark:text-white lg:text-[46px] lg:leading-[56px]">
                    {{ $t('What do we need from you?') }}
                </h2>
            </div>

            <div class="flex w-full flex-col gap-6 lg:w-[1120px]" dir="ltr">
                <div
                    v-for="(row, rowIndex) in [requirements.slice(0, 2), requirements.slice(2, 4)]"
                    :key="rowIndex"
                    class="flex flex-col items-stretch gap-6 sm:flex-row sm:items-stretch sm:gap-0"
                >
                    <div
                        v-for="([iconDark, iconLight, labelKey], colIndex) in row"
                        :key="labelKey"
                        class="flex flex-1 items-center gap-4 py-4 sm:flex-col sm:items-end sm:gap-2"
                        :class="colIndex === 0 ? 'sm:border-r sm:border-[#5a6b7e] sm:pr-[30px] dark:sm:border-[#94a3b8]' : 'sm:pl-[30px]'"
                    >
                        <img :src="iconDark" class="hidden h-6 w-6 dark:block" alt="" aria-hidden="true" />
                        <img :src="iconLight" class="h-6 w-6 dark:hidden" alt="" aria-hidden="true" />
                        <p dir="auto" class="w-full text-right text-lg font-semibold leading-[30px] text-black dark:text-white sm:text-xl">
                            {{ $t(labelKey) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
