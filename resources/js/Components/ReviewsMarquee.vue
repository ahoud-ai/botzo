<script setup>
import { ref } from "vue";
import { Swiper, SwiperSlide } from "swiper/vue";
import { Navigation, Autoplay, A11y } from "swiper/modules";
import { ChevronLeft, ChevronRight } from "lucide-vue-next";
import { useRtl } from "@/Composables/useRtl";
import "swiper/css";
import "swiper/css/navigation";

defineProps({
    reviews: {
        type: Array,
        default: () => [],
    },
});

const { isRtl } = useRtl();

const initial = (name) => (name || "").trim().charAt(0) || "؟";

const prevButton = ref(null);
const nextButton = ref(null);

const breakpoints = {
    0: { slidesPerView: 1.15, spaceBetween: 16 },
    640: { slidesPerView: 2.1, spaceBetween: 16 },
    1024: { slidesPerView: 3, spaceBetween: 20 },
    1280: { slidesPerView: 4, spaceBetween: 20 },
};
</script>

<template>
    <div v-if="reviews.length" class="relative w-full">
        <Swiper
            :dir="isRtl ? 'rtl' : 'ltr'"
            :modules="[Navigation, Autoplay, A11y]"
            :loop="reviews.length > 4"
            :breakpoints="breakpoints"
            :autoplay="{ delay: 3500, disableOnInteraction: false, pauseOnMouseEnter: true }"
            :navigation="{ prevEl: prevButton, nextEl: nextButton }"
            :speed="500"
            class="reviews-swiper !overflow-visible"
        >
            <SwiperSlide v-for="(item, index) in reviews" :key="index" class="!h-auto">
                <div
                    class="relative flex h-full w-full flex-col items-end gap-4 overflow-hidden rounded-3xl border-[0.7px] border-[#e4ece7] bg-[var(--surface-1)] px-5 py-6 dark:border-[#1e2a3a] dark:bg-[#0a0f17] sm:px-6 sm:py-7"
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
            </SwiperSlide>
        </Swiper>

        <div v-if="reviews.length > 1" class="mt-6 flex w-full items-center justify-center gap-3" dir="ltr">
            <button
                ref="prevButton"
                type="button"
                :aria-label="$t('Previous')"
                class="reviews-nav-btn flex h-11 w-11 items-center justify-center rounded-full border border-[#cfd8e3] bg-white text-black transition-colors hover:border-[#25d366] hover:bg-[#25d366]/[0.08] dark:border-[#1e2a3a] dark:bg-[#0b161a] dark:text-white"
            >
                <ChevronLeft :size="20" />
            </button>
            <button
                ref="nextButton"
                type="button"
                :aria-label="$t('Next')"
                class="reviews-nav-btn flex h-11 w-11 items-center justify-center rounded-full border border-[#cfd8e3] bg-white text-black transition-colors hover:border-[#25d366] hover:bg-[#25d366]/[0.08] dark:border-[#1e2a3a] dark:bg-[#0b161a] dark:text-white"
            >
                <ChevronRight :size="20" />
            </button>
        </div>
    </div>
</template>

<style scoped>
.reviews-swiper {
    padding-block: 4px;
}

.reviews-nav-btn:disabled,
.reviews-nav-btn.swiper-button-disabled {
    opacity: 0.35;
    cursor: default;
    pointer-events: none;
}
</style>
