<script setup>
const cardBase =
    "group absolute w-[1080px] overflow-hidden rounded-3xl bg-[rgba(37,211,102,0.12)] transition-transform duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] hover:-translate-y-1.5";
</script>

<template>
    <div class="relative w-[1080px]" style="height: 1649.59px">
        <!-- Connector: Step1 -> Step2 (180deg rotation keeps the same bounding box, no dimension swap) -->
        <div class="absolute flex items-center justify-center" style="left: 392px; top: 445px; width: 294.058px; height: 136.218px">
            <div class="shrink-0 rotate-180" style="width: 298.736px; height: 141.032px">
                <img
                    src="/images/how-it-works/connector-a.svg"
                    class="block h-full w-full"
                    alt=""
                    aria-hidden="true"
                />
            </div>
        </div>

        <!-- Connector: Step2 -> Step3 (90deg rotation swaps width/height, so the image must keep
             its own natural landscape size and only the wrapper gets rotated - otherwise the SVG's
             preserveAspectRatio="none" stretches the dashed path non-uniformly and the dashes warp) -->
        <div class="absolute flex items-center justify-center" style="left: 493px; top: 837.02px; width: 136.219px; height: 294.057px">
            <div class="shrink-0 rotate-90" style="width: 297.333px; height: 139.279px">
                <img
                    src="/images/how-it-works/connector-b.svg"
                    class="block h-full w-full"
                    alt=""
                    aria-hidden="true"
                />
            </div>
        </div>

        <!-- Step 1: Connect WhatsApp Business -->
        <div v-reveal :class="cardBase" style="left: 0; top: 0">
            <div class="flex w-full items-center justify-between px-10 py-20" dir="ltr">
                <div class="flex w-[319px] shrink-0 flex-col items-start gap-[18px] rounded-[20px] bg-white p-4 dark:bg-black">
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
                        class="w-full rounded-xl bg-[#25d366] px-4 py-3 text-center text-sm font-semibold leading-[21px] text-black transition-transform duration-200 hover:scale-[1.02]"
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
                <div class="flex min-w-0 flex-1 flex-col items-end gap-4 text-right">
                    <h3 dir="auto" class="w-full text-[32px] font-semibold leading-[29.9px] text-black dark:text-white">
                        1-{{ $t('Connect WhatsApp Business') }}
                    </h3>
                    <p dir="auto" class="w-full text-lg leading-9 text-[#8899aa]">
                        {{ $t('Connect your number via official WhatsApp in minutes, with zero technical hassle.') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Step 2: Design smart replies without code -->
        <div v-reveal="{ delay: 80 }" :class="cardBase" style="left: 0; top: 603px">
            <div class="flex w-full items-center justify-between px-10 py-20" dir="ltr">
                <div class="relative h-[307px] w-[427px] shrink-0 overflow-hidden rounded-[20px] bg-white dark:bg-[#0a0f17]">
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
                <div class="flex min-w-0 flex-1 flex-col items-end gap-4 text-right">
                    <h3 dir="auto" class="w-full text-[32px] font-semibold leading-[29.9px] text-black dark:text-white">
                        2-{{ $t('Design smart replies without code') }}
                    </h3>
                    <p dir="auto" class="w-full text-lg leading-9 text-[#8899aa]">
                        {{ $t('Build flows with drag-and-drop, without writing a single line of code.') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Step 3: Monitor and improve -->
        <div v-reveal="{ delay: 160 }" :class="cardBase" style="left: 0; top: 1171px">
            <div class="flex w-full items-center justify-between px-10 py-20" dir="ltr">
                <div class="flex w-[360px] max-w-[360px] shrink-0 flex-col items-end rounded-[20px] bg-white p-[22px] dark:bg-black">
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
                <div class="flex min-w-0 flex-1 flex-col items-end gap-4 text-right">
                    <h3 dir="auto" class="w-full text-[32px] font-semibold leading-[29.9px] text-black dark:text-white">
                        3-{{ $t('Monitor and improve') }}
                    </h3>
                    <p dir="auto" class="w-full text-lg leading-9 text-[#8899aa]">
                        {{ $t("A real-time analytics dashboard reveals what's working and what needs improvement.") }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
