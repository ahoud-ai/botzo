<script setup>
import { ref, onMounted, onUnmounted } from "vue";
import { ChevronLeft, ChevronRight } from "lucide-vue-next";
import { useRtl } from "@/Composables/useRtl";

const props = defineProps({
    reviews: {
        type: Array,
        default: () => [],
    },
});

const { isRtl } = useRtl();

const initial = (name) => (name || "").trim().charAt(0) || "؟";

// Four fixed slots. Each step replaces the content of exactly ONE slot (in
// round-robin order) — the other three keep their own DOM node (same :key),
// so Vue never touches them: no shift, no re-render, nothing moves except
// the one card whose content actually changed.
const SLOT_COUNT = 4;
const canRotate = props.reviews.length > SLOT_COUNT;

const slotReviewIndex = ref(
    Array.from({ length: Math.min(SLOT_COUNT, props.reviews.length) }, (_, i) => i)
);
let nextReviewIndex = SLOT_COUNT % (props.reviews.length || 1);
let slotCursor = 0;
const history = [];

// Which way the changing card slides: "fwd" enters from the right/exits
// left, "bwd" is the mirror image. Read by the per-slot <Transition> below.
const direction = ref("fwd");

function stepForward() {
    if (!canRotate) return;
    direction.value = "fwd";
    const slot = slotCursor;
    history.push({
        slot,
        prevReviewIndex: slotReviewIndex.value[slot],
        prevNextIndex: nextReviewIndex,
    });
    slotReviewIndex.value[slot] = nextReviewIndex;
    nextReviewIndex = (nextReviewIndex + 1) % props.reviews.length;
    slotCursor = (slotCursor + 1) % SLOT_COUNT;
}

function stepBackward() {
    if (!canRotate) return;
    const last = history.pop();
    if (!last) return;
    direction.value = "bwd";
    slotReviewIndex.value[last.slot] = last.prevReviewIndex;
    nextReviewIndex = last.prevNextIndex;
    slotCursor = last.slot;
}

const prefersReducedMotionQuery = typeof window !== "undefined" ? window.matchMedia("(prefers-reduced-motion: reduce)") : null;
const prefersReducedMotion = () => prefersReducedMotionQuery?.matches ?? false;

const trackEl = ref(null);
let autoplayInterval = null;

function startAutoplay() {
    if (!canRotate || prefersReducedMotion()) return;
    autoplayInterval = setInterval(stepForward, 2800);
}
function stopAutoplay() {
    if (autoplayInterval) clearInterval(autoplayInterval);
    autoplayInterval = null;
}

onMounted(() => {
    startAutoplay();
    trackEl.value?.addEventListener("mouseenter", stopAutoplay);
    trackEl.value?.addEventListener("mouseleave", startAutoplay);
});

onUnmounted(() => {
    stopAutoplay();
    trackEl.value?.removeEventListener("mouseenter", stopAutoplay);
    trackEl.value?.removeEventListener("mouseleave", startAutoplay);
});
</script>

<template>
    <div v-if="reviews.length" class="relative w-full">
        <div
            ref="trackEl"
            class="grid w-full grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
            :dir="isRtl ? 'rtl' : 'ltr'"
        >
            <div v-for="(reviewIndex, slot) in slotReviewIndex" :key="slot" class="relative h-full overflow-hidden rounded-3xl">
                <Transition :name="`review-swap-${direction}`" mode="out-in">
                    <div
                        :key="reviewIndex"
                        class="relative flex h-full w-full flex-col items-end gap-4 overflow-hidden rounded-3xl border-[0.7px] border-[#e4ece7] bg-[var(--surface-1)] px-5 py-6 dark:border-[#1e2a3a] dark:bg-[#0a0f17] sm:px-6 sm:py-7"
                    >
                        <span
                            class="pointer-events-none absolute font-['Georgia',_serif] text-[120px] leading-[120px] text-black opacity-[0.04] dark:text-white"
                            style="left: 22.3px; top: 6.3px"
                            aria-hidden="true"
                        >❝</span>

                        <div class="relative w-full drop-shadow-[0_0_4px_rgba(37,211,102,0.5)]">
                            <p class="w-full text-right text-sm tracking-[2px] text-[#25d366]" dir="ltr">
                                <span v-for="n in 5" :key="n">{{ n <= (reviews[reviewIndex].rating || 0) ? '★' : '☆' }}</span>
                            </p>
                        </div>

                        <p dir="auto" class="w-full text-right text-base leading-6 text-[#445566] dark:text-[#94a3b8]">
                            {{ reviews[reviewIndex].review }}
                        </p>

                        <div class="h-[0.8px] w-full bg-[#cfd8e3] dark:bg-[#1e2a3a]"></div>

                        <div class="flex w-full items-center justify-end gap-4" dir="ltr">
                            <div class="flex min-w-0 flex-1 flex-col items-end gap-1 text-right">
                                <p class="w-full truncate text-base font-semibold text-black dark:text-white" dir="auto">
                                    {{ reviews[reviewIndex].name }}
                                </p>
                                <p class="w-full truncate text-sm text-black dark:text-white" dir="auto">
                                    {{ reviews[reviewIndex].position }}
                                </p>
                            </div>
                            <div
                                class="flex h-[46px] w-[46px] shrink-0 items-center justify-center rounded-full"
                                style="background-image: linear-gradient(97.26deg, #25d366 0%, #3b82f6 100%)"
                                aria-hidden="true"
                            >
                                <span class="text-base text-[#04130a]">{{ initial(reviews[reviewIndex].name) }}</span>
                            </div>
                        </div>
                    </div>
                </Transition>
            </div>
        </div>

        <div v-if="canRotate" class="mt-6 flex w-full items-center justify-center gap-3" dir="ltr">
            <button
                type="button"
                :aria-label="$t('Previous')"
                class="reviews-nav-btn flex h-11 w-11 items-center justify-center rounded-full border border-[#cfd8e3] bg-white text-black transition-colors hover:border-[#25d366] hover:bg-[#25d366]/[0.08] dark:border-[#1e2a3a] dark:bg-[#0b161a] dark:text-white"
                @click="stepBackward"
            >
                <ChevronLeft :size="20" />
            </button>
            <button
                type="button"
                :aria-label="$t('Next')"
                class="reviews-nav-btn flex h-11 w-11 items-center justify-center rounded-full border border-[#cfd8e3] bg-white text-black transition-colors hover:border-[#25d366] hover:bg-[#25d366]/[0.08] dark:border-[#1e2a3a] dark:bg-[#0b161a] dark:text-white"
                @click="stepForward"
            >
                <ChevronRight :size="20" />
            </button>
        </div>
    </div>
</template>

<style scoped>
.review-swap-fwd-enter-active,
.review-swap-fwd-leave-active,
.review-swap-bwd-enter-active,
.review-swap-bwd-leave-active {
    transition: opacity 0.35s ease, transform 0.35s cubic-bezier(0.22, 1, 0.36, 1);
}

/* Forward: new card enters from the right, old card exits to the left. */
.review-swap-fwd-enter-from {
    opacity: 0;
    transform: translateX(32px);
}

.review-swap-fwd-leave-to {
    opacity: 0;
    transform: translateX(-32px);
}

/* Backward: the exact mirror — enters from the left, exits to the right. */
.review-swap-bwd-enter-from {
    opacity: 0;
    transform: translateX(-32px);
}

.review-swap-bwd-leave-to {
    opacity: 0;
    transform: translateX(32px);
}

@media (prefers-reduced-motion: reduce) {
    .review-swap-fwd-enter-active,
    .review-swap-fwd-leave-active,
    .review-swap-bwd-enter-active,
    .review-swap-bwd-leave-active {
        transition: none;
    }
}
</style>
