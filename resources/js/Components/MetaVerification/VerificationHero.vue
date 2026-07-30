<script setup>
import { Link } from "@inertiajs/vue3";
import SectionBadge from "@/Components/SectionBadge.vue";
import VerificationBreadcrumb from "@/Components/MetaVerification/VerificationBreadcrumb.vue";

defineProps({
    requestServiceHref: { type: String, default: "/contact" },
});
</script>

<template>
    <div class="flex w-full flex-col items-center">
        <div class="flex w-full max-w-[1120px] px-5 lg:px-0">
            <VerificationBreadcrumb />
        </div>

        <div class="flex w-full flex-col items-center gap-6 px-5 py-12 md:py-16 lg:py-20">
            <div class="flex w-full flex-col items-center gap-4 lg:w-[1120px]">
                <SectionBadge :label="$t('Independent Service')" />

                <h1 class="w-full text-center text-[32px] font-semibold leading-[1.3] text-black dark:text-white lg:text-[64px] lg:leading-[1.4]">
                    <i18n-t keypath="WhatsApp business verification service via {meta}" tag="span" dir="auto">
                        <template #meta>
                            <span class="text-[#25d366]">Meta</span>
                        </template>
                    </i18n-t>
                </h1>

                <p dir="auto" class="w-full text-center text-base leading-6 text-[#8899aa] lg:text-[24px] lg:leading-9">
                    {{ $t('We prepare and follow up your Meta business verification request professionally, from data review to the final decision') }}
                </p>
            </div>

            <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:gap-3" dir="ltr">
                <Link
                    href="/contact"
                    class="flex h-[54px] flex-1 items-center justify-center rounded-2xl border border-[#1a2332] px-8 transition-colors hover:border-[#25d366] dark:border-white sm:w-[235px] sm:flex-none"
                >
                    <span dir="auto" class="whitespace-nowrap text-base font-semibold leading-5 text-black dark:text-white">{{ $t('Contact Us') }}</span>
                </Link>
                <a
                    :href="requestServiceHref"
                    class="flex h-[54px] flex-1 items-center justify-center gap-2 rounded-2xl px-8 transition-transform duration-200 hover:scale-[1.02] sm:w-[235px] sm:flex-none"
                    style="background-image: linear-gradient(138deg, #25d366 0%, #1db954 100%)"
                >
                    <img src="/images/hero/arrow.svg" class="h-4 w-4 shrink-0" alt="" aria-hidden="true" />
                    <span dir="auto" class="whitespace-nowrap text-base font-semibold leading-5 text-[#04130a]">{{ $t('Request the service now') }}</span>
                </a>
            </div>
        </div>
    </div>
</template>
