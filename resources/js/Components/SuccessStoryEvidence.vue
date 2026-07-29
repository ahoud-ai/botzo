<script setup>
const statBoxBase =
    "flex flex-col items-center justify-center gap-0 rounded-2xl bg-white p-[17px] text-center dark:bg-[#0a0f17]";
</script>

<template>
    <div
        class="relative flex w-full flex-col items-stretch gap-8 overflow-hidden rounded-3xl border border-[#cfd8e3] bg-[#f7f9fb] p-6 dark:border-[#1e2a3a] dark:bg-[#0d1117] md:p-10 lg:w-[1120px] lg:flex-row lg:items-start lg:justify-between lg:gap-0 lg:p-[49px]"
        dir="ltr"
    >
        <!-- Evidence: stat grid + before/after comparison -->
        <div class="flex w-full flex-col items-stretch gap-6 lg:w-[462.16px]">
            <div class="grid w-full grid-cols-3 gap-3.5 text-center lg:h-[108.8px]" dir="ltr">
                <div class="col-start-1 border border-[#cfd8e3] dark:border-[#1e2a3a]" :class="statBoxBase">
                    <p class="text-2xl font-semibold leading-[1.4] text-[#2bd46a] lg:text-[40px]">{{ $t('200+') }}</p>
                    <p class="text-sm leading-6 text-[#8899aa]">{{ $t('Hours saved/month') }}</p>
                </div>
                <div class="col-start-2 border border-[#e6edf3] dark:border-[#1a2332]" :class="statBoxBase">
                    <p class="text-2xl font-semibold leading-[1.4] text-[#2bd46a] lg:text-[40px]">{{ $t('3×') }}</p>
                    <p class="text-sm leading-6 text-[#8899aa]">{{ $t('Sales increase') }}</p>
                </div>
                <div class="col-start-3 border border-[#e6edf3] dark:border-[#1a2332]" :class="statBoxBase">
                    <p class="text-2xl font-semibold leading-[1.4] text-[#2bd46a] lg:text-[40px]">{{ $t('94%') }}</p>
                    <p class="text-sm leading-6 text-[#8899aa]">{{ $t('Reply rate') }}</p>
                </div>
            </div>

            <div class="flex w-full flex-col gap-4 rounded-2xl border border-[#cfd8e3] bg-white p-[21px] dark:border-[#1e2a3a] dark:bg-[#0a0f17]">
                <div class="flex w-full items-center gap-3" dir="ltr">
                    <span class="w-[38px] shrink-0 text-sm leading-6 text-[#8899aa]">{{ $t('13%') }}</span>
                    <span class="relative h-3 flex-1 overflow-hidden rounded-lg bg-[#25d366]">
                        <span class="absolute inset-y-0 right-0 rounded-lg bg-[#2bd46a]" style="left: 69%"></span>
                    </span>
                    <span class="w-12 shrink-0 text-right text-sm leading-6 text-[#8899aa]">{{ $t('Before') }}</span>
                </div>
                <div class="flex w-full items-center gap-3" dir="ltr">
                    <span class="w-[38px] shrink-0 text-sm leading-6 text-[#8899aa]">{{ $t('94%') }}</span>
                    <span class="relative h-3 flex-1 overflow-hidden rounded-lg bg-[#25d366]">
                        <span
                            class="absolute inset-y-0 right-0 rounded-lg bg-[#2bd46a] shadow-[0px_0px_16px_0px_rgba(37,211,102,0.4)]"
                            style="left: 6%"
                        ></span>
                    </span>
                    <span class="w-12 shrink-0 text-right text-sm leading-6 text-[#8899aa]">{{ $t('After') }}</span>
                </div>
            </div>
        </div>

        <!-- Case study details -->
        <div class="flex w-full flex-col items-end gap-8 text-right lg:w-[539px]">
            <div class="flex w-full flex-col items-end gap-6">
                <h3 dir="auto" class="w-full text-2xl font-semibold leading-[1.3] text-black dark:text-white lg:text-[32px] lg:leading-[40.8px]">
                    {{ $t('From delayed replies to round-the-clock sales') }}
                </h3>
                <div class="flex w-full flex-col items-end gap-4">
                    <div class="flex w-full flex-col items-end gap-2 pt-1">
                        <p dir="auto" class="text-lg font-medium uppercase leading-[1.4] text-[#25d366]">{{ $t('Context') }}</p>
                        <p dir="auto" class="w-full text-base leading-6 text-[#8899aa]">
                            {{ $t('A Saudi fashion e-commerce store receiving thousands of daily WhatsApp inquiries, with a limited team.') }}
                        </p>
                    </div>
                    <div class="flex w-full flex-col items-end gap-2">
                        <p dir="auto" class="text-lg font-medium uppercase leading-[1.4] text-[#25d366]">{{ $t('Challenge') }}</p>
                        <p dir="auto" class="w-full text-base leading-6 text-[#8899aa]">
                            {{ $t('Leads lost overnight, replies delayed for hours, and customers leaving before purchase.') }}
                        </p>
                    </div>
                    <div class="flex w-full flex-col items-end gap-2">
                        <p dir="auto" class="text-lg font-medium uppercase leading-[1.4] text-[#25d366]">{{ $t('Solution') }}</p>
                        <p dir="auto" class="w-full text-base leading-6 text-[#8899aa]">
                            {{ $t('Deployed Botzo for instant replies, lead qualification, and automatic cart recovery — within a single week.') }}
                        </p>
                    </div>
                </div>
            </div>

            <button
                type="button"
                class="flex h-[54px] w-[185px] items-center justify-center gap-2 rounded-2xl px-8 transition-transform duration-200 hover:scale-[1.03]"
                style="background-image: linear-gradient(130.84deg, #25d366 0%, #1db954 100%)"
            >
                <img src="/images/success-story/arrow-left.svg" class="h-4 w-4" alt="" aria-hidden="true" />
                <span class="whitespace-nowrap text-base font-semibold leading-5 text-[#04130a]">{{ $t('Learn more') }}</span>
            </button>
        </div>
    </div>
</template>
