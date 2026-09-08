<script setup>
import { onMounted, onUnmounted, ref } from "vue";

const statBoxBase =
    "flex flex-col items-center justify-center gap-0 rounded-2xl bg-[#0a0f17] p-[17px] text-center dark:bg-[#0a0f17]";

const HOURS_TARGET = 200;
const SALES_TARGET = 3;
const REPLY_TARGET = 94;
const beforeReplyRate = 13;
const afterReplyRate = 94;
const COUNT_DURATION = 1600;

// Stats + before/after bars all start at 0 and count/fill up together once
// the card scrolls into view, instead of just appearing pre-filled.
const hoursDisplay = ref(0);
const salesDisplay = ref(0);
const replyDisplay = ref(0);
const barBeforeDisplay = ref(0);
const barAfterDisplay = ref(0);

const prefersReducedMotionQuery = typeof window !== "undefined" ? window.matchMedia("(prefers-reduced-motion: reduce)") : null;
const prefersReducedMotion = () => prefersReducedMotionQuery?.matches ?? false;

const rootEl = ref(null);
let observer = null;
const rafIds = [];

function animateValue(setter, target, duration) {
    const start = performance.now();
    const tick = (now) => {
        const progress = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        setter(Math.round(eased * target));
        if (progress < 1) {
            rafIds.push(requestAnimationFrame(tick));
        }
    };
    rafIds.push(requestAnimationFrame(tick));
}

function playAnimation() {
    if (prefersReducedMotion()) {
        hoursDisplay.value = HOURS_TARGET;
        salesDisplay.value = SALES_TARGET;
        replyDisplay.value = REPLY_TARGET;
        barBeforeDisplay.value = beforeReplyRate;
        barAfterDisplay.value = afterReplyRate;
        return;
    }

    animateValue((v) => (hoursDisplay.value = v), HOURS_TARGET, COUNT_DURATION);
    animateValue((v) => (salesDisplay.value = v), SALES_TARGET, COUNT_DURATION);
    animateValue((v) => (replyDisplay.value = v), REPLY_TARGET, COUNT_DURATION);
    animateValue((v) => (barBeforeDisplay.value = v), beforeReplyRate, COUNT_DURATION);
    animateValue((v) => (barAfterDisplay.value = v), afterReplyRate, COUNT_DURATION);
}

onMounted(() => {
    observer = new IntersectionObserver(
        ([entry]) => {
            if (entry.isIntersecting) {
                playAnimation();
                observer.disconnect();
            }
        },
        { threshold: 0.3 },
    );
    if (rootEl.value) observer.observe(rootEl.value);
});

onUnmounted(() => {
    observer?.disconnect();
    rafIds.forEach((id) => cancelAnimationFrame(id));
});
</script>

<template>
    <div
        ref="rootEl"
        class="relative flex w-full flex-col items-stretch gap-8 overflow-hidden rounded-3xl border border-[#1e2a3a] bg-[#0d1117] p-6 dark:border-[#1e2a3a] dark:bg-[#0d1117] md:p-10 lg:w-[1280px] lg:flex-row lg:items-start lg:justify-between lg:gap-0 lg:p-[56px]"
        dir="ltr"
    >
        <!-- Evidence: stat grid + before/after comparison -->
        <div class="flex w-full flex-col items-stretch gap-6 lg:w-[528px]">
            <div class="grid w-full grid-cols-3 gap-3.5 text-center lg:h-[108.8px]" dir="ltr">
                <div class="col-start-1 border border-[#1e2a3a]" :class="statBoxBase">
                    <p class="text-2xl font-semibold leading-[1.4] text-[#25d366] tabular-nums lg:text-[40px]">{{ hoursDisplay }}+</p>
                    <p class="text-sm leading-6 text-[#9fb0c4]">{{ $t('Hours saved/month') }}</p>
                </div>
                <div class="col-start-2 border border-[#1a2332]" :class="statBoxBase">
                    <p class="text-2xl font-semibold leading-[1.4] text-[#25d366] tabular-nums lg:text-[40px]">{{ salesDisplay }}×</p>
                    <p class="text-sm leading-6 text-[#9fb0c4]">{{ $t('Sales increase') }}</p>
                </div>
                <div class="col-start-3 border border-[#1a2332]" :class="statBoxBase">
                    <p class="text-2xl font-semibold leading-[1.4] text-[#25d366] tabular-nums lg:text-[40px]">{{ replyDisplay }}%</p>
                    <p class="text-sm leading-6 text-[#9fb0c4]">{{ $t('Reply rate') }}</p>
                </div>
            </div>

            <div class="flex w-full flex-col gap-4 rounded-2xl border border-[#1e2a3a] bg-[#0a0f17] p-[21px]">
                <div class="flex w-full items-center gap-3" dir="ltr">
                    <span class="w-[38px] shrink-0 text-sm leading-6 text-[#9fb0c4] tabular-nums">{{ barBeforeDisplay }}%</span>
                    <span class="relative h-3 flex-1 overflow-hidden rounded-lg bg-[#1a2332]">
                        <span class="absolute inset-y-0 right-0 rounded-lg bg-[#25d366]" :style="{ left: (100 - barBeforeDisplay) + '%' }"></span>
                    </span>
                    <span class="w-12 shrink-0 text-right text-sm leading-6 text-[#9fb0c4]">{{ $t('Before') }}</span>
                </div>
                <div class="flex w-full items-center gap-3" dir="ltr">
                    <span class="w-[38px] shrink-0 text-sm leading-6 text-[#9fb0c4] tabular-nums">{{ barAfterDisplay }}%</span>
                    <span class="relative h-3 flex-1 overflow-hidden rounded-lg bg-[#1a2332]">
                        <span
                            class="absolute inset-y-0 right-0 rounded-lg bg-[#25d366] shadow-[0px_0px_16px_0px_rgba(37,211,102,0.4)]"
                            :style="{ left: (100 - barAfterDisplay) + '%' }"
                        ></span>
                    </span>
                    <span class="w-12 shrink-0 text-right text-sm leading-6 text-[#9fb0c4]">{{ $t('After') }}</span>
                </div>
            </div>
        </div>

        <!-- Case study details -->
        <div class="flex w-full flex-col items-end gap-8 text-right lg:w-[616px]">
            <div class="flex w-full flex-col items-end gap-6">
                <h3 dir="auto" class="w-full text-2xl font-semibold leading-[1.3] text-[#e9eef5] dark:text-white lg:text-[32px] lg:leading-[40.8px]">
                    {{ $t('From delayed replies to round-the-clock sales') }}
                </h3>
                <div class="flex w-full flex-col items-end gap-4">
                    <div class="flex w-full flex-col items-end gap-2 pt-1">
                        <p dir="auto" class="text-lg font-medium uppercase leading-[1.4] text-[#25d366]">{{ $t('Context') }}</p>
                        <p dir="auto" class="w-full text-base leading-6 text-[#9fb0c4]">
                            {{ $t('A Saudi fashion e-commerce store receiving thousands of daily WhatsApp inquiries, with a limited team.') }}
                        </p>
                    </div>
                    <div class="flex w-full flex-col items-end gap-2">
                        <p dir="auto" class="text-lg font-medium uppercase leading-[1.4] text-[#25d366]">{{ $t('Challenge') }}</p>
                        <p dir="auto" class="w-full text-base leading-6 text-[#9fb0c4]">
                            {{ $t('Leads lost overnight, replies delayed for hours, and customers leaving before purchase.') }}
                        </p>
                    </div>
                    <div class="flex w-full flex-col items-end gap-2">
                        <p dir="auto" class="text-lg font-medium uppercase leading-[1.4] text-[#25d366]">{{ $t('Solution') }}</p>
                        <p dir="auto" class="w-full text-base leading-6 text-[#9fb0c4]">
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
