<script setup>
defineProps({
    reviews: {
        type: Array,
        default: () => [],
    },
});

const initial = (name) => (name || "").trim().charAt(0) || "؟";

// Repeated 4x (not just once) so the "first half" / "second half" the
// translateX(-50%) loop scrolls between are each two full sets wide — wide
// enough that the same reviewer's card can never appear twice within one
// viewport at once, even on very large monitors. Spacing between cards is
// done with a trailing margin on every card (including the last one of each
// set) instead of flex `gap`, so "one set" is a self-contained,
// exactly-repeatable unit — no JS measurement of pixel widths needed, and no
// seam/jump at the reset point on any viewport.
</script>

<template>
    <div v-if="reviews.length" class="reviews-marquee relative w-full overflow-hidden">
        <div class="reviews-marquee__track" dir="ltr">
            <div
                v-for="(item, index) in [...reviews, ...reviews, ...reviews, ...reviews]"
                :key="index"
                :aria-hidden="index >= reviews.length ? 'true' : null"
                class="reviews-marquee__card flex w-[280px] shrink-0 flex-col items-end gap-4 overflow-hidden rounded-3xl border-[0.7px] border-[#e4ece7] bg-[var(--surface-1)] px-5 py-6 dark:border-[#1e2a3a] dark:bg-[#0a0f17] sm:w-[284px] sm:px-6 sm:py-7"
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
    width: max-content;
    animation: reviews-marquee-scroll 80s linear infinite;
    will-change: transform;
}

.reviews-marquee__card {
    margin-inline-end: 16px;
}

@keyframes reviews-marquee-scroll {
    from {
        transform: translateX(0);
    }
    to {
        transform: translateX(-50%);
    }
}

@media (prefers-reduced-motion: reduce) {
    .reviews-marquee__track {
        animation: none;
    }
}
</style>
