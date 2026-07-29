<script setup>
const cardBase =
    "w-full overflow-hidden rounded-3xl bg-[rgba(37,211,102,0.12)] p-4";
</script>

<template>
    <div class="flex w-full flex-col items-center gap-4">
        <!-- Step 1: Connect WhatsApp Business (illustration only, matches provided mobile reference) -->
        <div v-reveal :class="cardBase">
            <div class="flex w-full flex-col items-start gap-3 rounded-2xl bg-white p-4 dark:bg-black">
                <div class="w-full">
                    <p class="text-right text-xs leading-[18px] text-[#8899aa]">{{ $t('Mobile number') }}</p>
                    <div class="mt-2 w-full rounded-xl bg-[#f7f9fb] px-3.5 py-[11px] dark:bg-[#0d1117]">
                        <p class="text-right text-sm leading-[21px] tracking-[0.3px] text-black dark:text-white" dir="ltr">
                            +966 5X XXX XXXX
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    class="w-full rounded-xl bg-[#25d366] px-4 py-3 text-center text-sm font-semibold leading-[21px] text-black"
                >
                    {{ $t('Connect now') }}
                </button>
                <div class="flex w-full items-center justify-end gap-[10px] rounded-xl border-[1.5px] border-[#25d366] px-[15.5px] py-[13.5px]">
                    <div class="flex flex-col items-end">
                        <p class="text-sm font-bold leading-[21px] text-[#25d366]">{{ $t('Connected') }}</p>
                        <p class="text-[11px] leading-[16.5px] text-[#8899aa]" dir="ltr">Botzo AI مرتبط بواتساب</p>
                    </div>
                    <div class="flex h-[26px] w-[26px] shrink-0 items-center justify-center rounded-full bg-[#25d366]">
                        <svg width="13" height="13" viewBox="0 0 13 13" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M10.8346 3.25L4.8763 9.20833L2.16797 6.5" stroke="white" stroke-width="1.625" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 2: Design smart replies without code -->
        <div v-reveal="{ delay: 80 }" :class="cardBase">
            <div class="flex w-full flex-col items-end gap-4">
                <div class="w-full text-right">
                    <h3 dir="auto" class="text-xl font-semibold leading-[25px] text-black dark:text-white">
                        2-{{ $t('Design smart replies without code') }}
                    </h3>
                    <p class="mt-1.5 text-sm leading-6 text-[#8899aa]">
                        {{ $t('Build flows with drag-and-drop, without writing a single line of code.') }}
                    </p>
                </div>
                <div class="howitworks-mobile-scale mx-auto">
                    <div class="howitworks-mobile-scale__box howitworks-mobile-scale__box--flowchart overflow-hidden rounded-[20px] bg-white dark:bg-[#0a0f17]">
                        <img
                            src="/images/how-it-works/flowchart-lines.svg"
                            class="pointer-events-none absolute inset-0 h-[290px] w-[428px]"
                            alt=""
                            aria-hidden="true"
                        />
                        <div
                            class="absolute flex items-center gap-[7px] rounded-xl border-2 border-[#1e2a3a] bg-[#131a28] px-4 py-[10px] drop-shadow-[0px_2px_4px_rgba(0,0,0,0.35)]"
                            style="left: 190px; top: 41.31px"
                        >
                            <span class="text-[13px] leading-[19.5px] text-[#f0f4f8]">👋</span>
                            <span class="whitespace-nowrap text-xs leading-[18px] text-[#f0f4f8]">{{ $t('Welcome message') }}</span>
                        </div>
                        <div
                            class="absolute flex items-center gap-[7px] rounded-xl border-2 border-[#1e2a3a] bg-[#131a28] px-4 py-[10px] drop-shadow-[0px_2px_4px_rgba(0,0,0,0.35)]"
                            style="left: 190px; top: 127.72px"
                        >
                            <span class="text-[13px] leading-[19.5px] text-[#f0f4f8]">❓</span>
                            <span class="whitespace-nowrap text-xs leading-[18px] text-[#f0f4f8]">{{ $t('Customer question?') }}</span>
                        </div>
                        <div
                            class="absolute flex items-center gap-[7px] rounded-xl border-2 border-[#25d366] bg-[#131a28] px-4 py-[10px] drop-shadow-[0_0_0_rgba(37,211,102,0.12),0_4px_8px_rgba(37,211,102,0.22)]"
                            style="left: 95.5px; top: 219.88px"
                        >
                            <span class="text-[13px] font-bold leading-[19.5px] text-[#25d366]">✓</span>
                            <span class="whitespace-nowrap text-xs font-bold leading-[18px] text-[#25d366]">{{ $t('Auto-reply') }}</span>
                            <span class="h-[7px] w-[7px] shrink-0 rounded-full bg-[#25d366]"></span>
                        </div>
                        <div
                            class="absolute flex items-center gap-[7px] rounded-xl border-2 border-[#1e2a3a] bg-[#131a28] px-4 py-[10px] drop-shadow-[0px_2px_4px_rgba(0,0,0,0.35)]"
                            style="left: 284.5px; top: 219.88px"
                        >
                            <span class="text-[13px] leading-[19.5px] text-[#f0f4f8]">👤</span>
                            <span class="whitespace-nowrap text-xs leading-[18px] text-[#f0f4f8]">{{ $t('Transfer to agent') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 3: Monitor and improve -->
        <div v-reveal="{ delay: 160 }" :class="cardBase">
            <div class="flex w-full flex-col items-end gap-4">
                <div class="w-full text-right">
                    <h3 dir="auto" class="text-xl font-semibold leading-[25px] text-black dark:text-white">
                        3-{{ $t('Monitor and improve') }}
                    </h3>
                    <p class="mt-1.5 text-sm leading-6 text-[#8899aa]">
                        {{ $t("A real-time analytics dashboard reveals what's working and what needs improvement.") }}
                    </p>
                </div>
                <div class="howitworks-mobile-scale mx-auto">
                    <div class="howitworks-mobile-scale__box howitworks-mobile-scale__box--analytics flex flex-col items-end rounded-[20px] bg-white p-[22px] dark:bg-black">
                        <p class="w-full text-right text-sm leading-[21px] text-black dark:text-white">{{ $t('Overview') }}</p>
                        <div class="mt-4 flex w-full items-start gap-[10px]">
                            <div class="flex w-[152px] flex-col items-end rounded-[14px] border border-[#25d366] bg-[#25d366] px-[13px] py-[15px]">
                                <p class="w-full text-right text-[11px] leading-[16.5px] text-black">{{ $t('Reply rate') }}</p>
                                <p class="w-full pt-1.5 text-right text-[26px] font-bold leading-[28.6px] text-black">{{ $t('94%') }}</p>
                                <div class="flex items-center justify-end gap-[3px] pt-[5px]">
                                    <svg width="10" height="10" viewBox="0 0 10 10" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M9.16536 2.91602L5.6237 6.45768L3.54036 4.37435L0.832031 7.08268" stroke="black" stroke-width="0.833333" stroke-linecap="round" stroke-linejoin="round" />
                                        <path d="M6.66797 2.91602H9.16797V5.41602" stroke="black" stroke-width="0.833333" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <span class="whitespace-nowrap text-[10px] leading-[15px] text-black">{{ $t('↑ 2% this week') }}</span>
                                </div>
                            </div>
                            <div class="flex w-[152px] flex-col items-end rounded-[14px] border border-[#25d366] bg-[#25d366] px-[13px] py-[15px]">
                                <p class="w-full text-right text-[11px] leading-[16.5px] text-black">{{ $t('Conversions') }}</p>
                                <p class="w-full pt-1.5 text-right text-[26px] font-bold leading-[28.6px] text-black">{{ $t('↑31%') }}</p>
                                <div class="flex items-center justify-end gap-[3px] pt-[5px]">
                                    <svg width="10" height="10" viewBox="0 0 10 10" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M2.91797 2.91602H7.08464V7.08268" stroke="black" stroke-width="0.833333" stroke-linecap="round" stroke-linejoin="round" />
                                        <path d="M2.91797 7.08268L7.08464 2.91602" stroke="black" stroke-width="0.833333" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <span class="whitespace-nowrap text-[10px] leading-[15px] text-black">{{ $t('from last month') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="mt-[18px] w-full">
                            <p class="w-full text-right text-[11px] leading-[16.5px] text-black dark:text-white">{{ $t('Daily conversations') }}</p>
                            <div class="mt-2.5 flex w-full items-end gap-1.5" style="height: 82px">
                                <div class="min-w-px flex-[39.711] rounded-t-[4px] rounded-b-[2px] bg-[#25d366]" style="height: 44.633px"></div>
                                <div class="min-w-px flex-[39.719] rounded-t-[4px] rounded-b-[2px] bg-[#25d366]" style="height: 54px"></div>
                                <div class="min-w-px flex-[39.711] rounded-t-[4px] rounded-b-[2px] bg-[#25d366]" style="height: 41.758px"></div>
                                <div class="min-w-px flex-[39.719] rounded-t-[4px] rounded-b-[2px] bg-[#25d366]" style="height: 63.359px"></div>
                                <div class="min-w-px flex-[39.711] rounded-t-[4px] rounded-b-[2px] bg-[#25d366]" style="height: 59.039px"></div>
                                <div class="min-w-px flex-[39.719] rounded-t-[4px] rounded-b-[2px] bg-[#25d366]" style="height: 67.68px"></div>
                                <div class="min-w-px flex-[39.711] rounded-t-[4px] rounded-b-[2px] bg-[#25d366]" style="height: 56.875px"></div>
                            </div>
                            <div class="mt-1.5 flex w-full gap-1.5">
                                <span class="min-w-px flex-[39.711] text-center text-[9px] leading-[13.5px] text-[#445566] dark:text-[#94a3b8]">{{ $t('Sun') }}</span>
                                <span class="min-w-px flex-[39.719] text-center text-[9px] leading-[13.5px] text-[#445566] dark:text-[#94a3b8]">{{ $t('Mon') }}</span>
                                <span class="min-w-px flex-[39.711] text-center text-[9px] leading-[13.5px] text-[#445566] dark:text-[#94a3b8]">{{ $t('Tue') }}</span>
                                <span class="min-w-px flex-[39.719] text-center text-[9px] leading-[13.5px] text-[#445566] dark:text-[#94a3b8]">{{ $t('Wed') }}</span>
                                <span class="min-w-px flex-[39.711] text-center text-[9px] leading-[13.5px] text-[#445566] dark:text-[#94a3b8]">{{ $t('Thu') }}</span>
                                <span class="min-w-px flex-[39.719] text-center text-[9px] font-bold leading-[13.5px] text-[#25d366]">{{ $t('Fri') }}</span>
                                <span class="min-w-px flex-[39.711] text-center text-[9px] leading-[13.5px] text-[#445566] dark:text-[#94a3b8]">{{ $t('Sat') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Reuses the exact desktop illustration markup at native pixel size, then
   scales the whole block down to fit the mobile card width fluidly - this
   avoids re-deriving every absolute px value for a second, narrower layout. */
.howitworks-mobile-scale {
    position: relative;
    overflow: hidden;
    width: calc(100vw - 40px - 32px);
}

@media (min-width: 768px) {
    .howitworks-mobile-scale {
        width: calc(100vw - 80px - 32px);
    }
}

.howitworks-mobile-scale__box {
    position: absolute;
    top: 0;
    left: 0;
    transform-origin: top left;
}

.howitworks-mobile-scale__box--flowchart {
    width: 427px;
    height: 307px;
    transform: scale(calc(min(100vw - 72px, 640px) / 427px));
}

.howitworks-mobile-scale__box--analytics {
    width: 360px;
    height: 330.094px;
    transform: scale(calc(min(100vw - 72px, 640px) / 360px));
}

@media (min-width: 768px) {
    .howitworks-mobile-scale__box--flowchart {
        transform: scale(calc(min(100vw - 112px, 640px) / 427px));
    }

    .howitworks-mobile-scale__box--analytics {
        transform: scale(calc(min(100vw - 112px, 640px) / 360px));
    }
}

/* Reserve the scaled footprint's actual height so no blank space is left below. */
.howitworks-mobile-scale:has(.howitworks-mobile-scale__box--flowchart) {
    height: calc(min(100vw - 72px, 640px) * 307 / 427);
}

.howitworks-mobile-scale:has(.howitworks-mobile-scale__box--analytics) {
    height: calc(min(100vw - 72px, 640px) * 330.094 / 360);
}

@media (min-width: 768px) {
    .howitworks-mobile-scale:has(.howitworks-mobile-scale__box--flowchart) {
        height: calc(min(100vw - 112px, 640px) * 307 / 427);
    }

    .howitworks-mobile-scale:has(.howitworks-mobile-scale__box--analytics) {
        height: calc(min(100vw - 112px, 640px) * 330.094 / 360);
    }
}
</style>
