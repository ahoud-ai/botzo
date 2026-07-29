<script setup>
import { computed } from "vue";

const props = defineProps({
    reviews: {
        type: Array,
        default: () => [],
    },
});

const initial = (name) => (name || "").trim().charAt(0) || "؟";

// Duplicated so the track can loop seamlessly. The reset distance must be
// an exact pixel value (card width + gap) * count, not translateX(-50%) of
// the doubled track: with a flex `gap`, the gap count doesn't split evenly
// between "half the track" and "one real set", which leaves a half-gap
// (8px) seam at the reset point.
const CARD_WIDTH = 362.667;
const CARD_GAP = 16;
const loopedReviews = computed(() => [...props.reviews, ...props.reviews]);
const loopDistance = computed(() => `${(CARD_WIDTH + CARD_GAP) * props.reviews.length}px`);
</script>

<template>
    <div v-if="reviews.length" class="reviews-marquee relative w-full overflow-hidden">
        <div class="reviews-marquee__fade reviews-marquee__fade--start" aria-hidden="true"></div>
        <div class="reviews-marquee__fade reviews-marquee__fade--end" aria-hidden="true"></div>

        <div class="reviews-marquee__track" dir="ltr" :style="{ '--loop-distance': loopDistance }">
            <div
                v-for="(item, index) in loopedReviews"
                :key="index"
                :aria-hidden="index >= reviews.length ? 'true' : null"
                class="flex w-[362.667px] shrink-0 flex-col items-end gap-4 overflow-hidden rounded-3xl border-[0.7px] border-[#cfd8e3] bg-white px-[24.7px] py-[32.7px] dark:border-[#1e2a3a] dark:bg-[#0a0f17]"
            >
                <span
                    class="pointer-events-none absolute font-['Georgia',_serif] text-[120px] leading-[120px] text-black opacity-[0.04] dark:text-white"
                    style="left: 22.3px; top: 6.3px"
                    aria-hidden="true"
                >❝</span>

                <div class="relative w-full drop-shadow-[0_0_4px_rgba(37,211,102,0.5)]">
                    <p class="w-full text-right text-sm tracking-[2px] text-[#25d366]" dir="ltr">
                        <span v-for="n in 5" :key="n">{{ n <= (item.rating || 0) ? '★' : '☆' }}</span>
                    </p>
                </div>

                <p dir="auto" class="w-full text-right text-base leading-6 text-[#445566] dark:text-[#94a3b8]">
                    {{ item.review }}
                </p>

                <div class="h-[0.8px] w-full bg-[#cfd8e3] dark:bg-[#1e2a3a]"></div>

                <div class="flex w-full items-center justify-end gap-4" dir="ltr">
                    <div class="flex min-w-0 flex-1 flex-col items-end gap-1 text-right">
                        <p class="w-full truncate text-base font-semibold text-black dark:text-white" dir="auto">
                            {{ item.name }}
                        </p>
                        <p class="w-full truncate text-sm text-black dark:text-white" dir="auto">
                            {{ item.position }}
                        </p>
                    </div>
                    <div
                        class="flex h-[46px] w-[46px] shrink-0 items-center justify-center rounded-full"
                        style="background-image: linear-gradient(97.26deg, #25d366 0%, #3b82f6 100%)"
                        aria-hidden="true"
                    >
                        <span class="text-base text-[#04130a]">{{ initial(item.name) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.reviews-marquee__track {
    display: flex;
    align-items: stretch;
    gap: 16px;
    width: max-content;
    animation: reviews-marquee-scroll 90s linear infinite;
}

.reviews-marquee:hover .reviews-marquee__track {
    animation-play-state: paused;
}

@keyframes reviews-marquee-scroll {
    from {
        transform: translateX(0);
    }
    to {
        transform: translateX(calc(-1 * var(--loop-distance)));
    }
}

.reviews-marquee__fade {
    position: absolute;
    top: 0;
    bottom: 0;
    width: clamp(24px, 6vw, 96px);
    z-index: 2;
    pointer-events: none;
}

.reviews-marquee__fade--start {
    left: 0;
    background: linear-gradient(to right, #ffffff, transparent);
}

.reviews-marquee__fade--end {
    right: 0;
    background: linear-gradient(to left, #ffffff, transparent);
}

.dark .reviews-marquee__fade--start {
    background: linear-gradient(to right, #0a0f17, transparent);
}

.dark .reviews-marquee__fade--end {
    background: linear-gradient(to left, #0a0f17, transparent);
}

@media (prefers-reduced-motion: reduce) {
    .reviews-marquee__track {
        animation: none;
    }
}
</style>
