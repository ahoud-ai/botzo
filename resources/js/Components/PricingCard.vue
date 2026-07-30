<script setup>
import { computed } from "vue";

const props = defineProps({
    topLabel: { type: String, required: true },
    price: { type: [String, Number], required: true },
    periodLabel: { type: String, required: true },
    description: { type: String, required: true },
    features: { type: Array, default: () => [] },
    buttonLabel: { type: String, required: true },
    featured: { type: Boolean, default: false },
});

// Accepts either plain strings (always "available") or { label, available }
// objects, so the same card can render the short home-page teaser list and
// the full /pricing page's detailed list (which also shows unavailable items).
const normalizedFeatures = computed(() => props.features.map((feature) => (
    typeof feature === "string" ? { label: feature, available: true } : feature
)));
</script>

<template>
    <div
        class="group relative flex w-full max-w-[359px] shrink-0 flex-col rounded-3xl border bg-white p-[25px] transition-all duration-300 dark:bg-[#0a0f17] sm:p-[33px] lg:w-[354px]"
        :class="featured
            ? 'border-[#25d366] shadow-[0_0_0_1px_rgba(37,211,102,0.2),0_18px_30px_rgba(37,211,102,0.12)] lg:w-[359px] lg:py-[52px]'
            : 'border-[#cfd8e3] hover:border-[#25d366] hover:shadow-[0_0_0_1px_rgba(37,211,102,0.2),0_18px_30px_rgba(37,211,102,0.12)] dark:border-[#1e2a3a]'"
    >
        <span
            v-if="featured"
            dir="auto"
            class="pointer-events-none absolute -top-3 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-[#25d366] px-4 py-1 text-xs font-semibold text-[#04130a] shadow-[0_8px_16px_rgba(37,211,102,0.28)]"
        >{{ $t('Most Popular') }}</span>

        <div class="flex w-full flex-col items-end gap-6" dir="ltr">
            <div class="flex w-full flex-col items-end gap-2">
                <p dir="auto" class="w-full text-right text-sm font-semibold leading-5 text-black dark:text-white">
                    {{ topLabel }}
                </p>

                <div class="flex h-[43px] w-full items-center justify-end gap-2">
                    <div class="flex items-center justify-end" dir="ltr">
                        <p dir="auto" class="whitespace-nowrap text-right text-sm leading-6 text-[#445566] dark:text-[#94a3b8]">
                            {{ periodLabel }}
                        </p>
                        <img src="/images/pricing/sar-icon.svg" class="h-6 w-6 p-2" alt="SAR" aria-hidden="true" />
                    </div>
                    <p
                        dir="auto"
                        class="whitespace-nowrap bg-clip-text text-right text-[32px] font-bold leading-[normal] text-transparent sm:text-[46px] sm:leading-[76px]"
                        style="background-image: linear-gradient(104deg, #25d366 0%, #1db954 100%)"
                    >
                        {{ price }}
                    </p>
                </div>

                <p dir="auto" class="w-full text-right text-sm leading-6 text-[#445566] dark:text-[#94a3b8]">
                    {{ description }}
                </p>
            </div>

            <div class="flex w-full flex-col items-end gap-3">
                <div v-for="(feature, index) in normalizedFeatures" :key="index" class="flex w-full items-start justify-end gap-2">
                    <p
                        dir="auto"
                        class="text-right text-sm leading-6"
                        :class="feature.available ? 'text-[#445566] dark:text-[#94a3b8]' : 'text-[#94a3b8]/60 dark:text-[#456]/60'"
                    >
                        {{ feature.label }}
                    </p>
                    <span
                        class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-[6px]"
                        :class="feature.available ? 'bg-[rgba(37,211,102,0.12)]' : 'bg-black/5 dark:bg-white/5'"
                    >
                        <span
                            class="text-[11px] leading-[16.5px]"
                            :class="feature.available ? 'text-[#0e6b35] dark:text-[#00c950]' : 'text-[#94a3b8]'"
                        >{{ feature.available ? '✓' : '✕' }}</span>
                    </span>
                </div>
            </div>

            <a
                href="/signup"
                class="flex h-[54px] w-full items-center justify-center rounded-2xl px-8 text-base font-semibold transition-transform duration-200 group-hover:scale-[1.02]"
                :class="featured
                    ? 'bg-[#25d366] text-[#04130a]'
                    : 'border border-[#1a2332] text-black dark:border-white dark:text-white'"
            >
                <span dir="auto">{{ buttonLabel }}</span>
            </a>
        </div>
    </div>
</template>
