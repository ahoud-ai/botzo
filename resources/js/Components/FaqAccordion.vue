<script setup>
import { ref } from "vue";

defineProps({
    items: { type: Array, default: () => [] },
});

const selected = ref(null);
</script>

<template>
    <div class="faq-accordion flex w-full flex-col items-end">
        <div
            v-for="(item, index) in items"
            :key="index"
            class="faq-item w-full overflow-hidden transition-colors"
            :class="selected === index + 1 ? 'faq-item--open' : ''"
        >
            <button
                type="button"
                class="flex w-full items-center justify-between gap-4 p-6 text-right"
                dir="ltr"
                @click="selected = selected === index + 1 ? null : index + 1"
            >
                <span
                    class="flex h-6 w-6 shrink-0 items-center justify-center text-[#25d366] transition-transform duration-300"
                    :class="selected === index + 1 ? 'rotate-45' : ''"
                >
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <span dir="auto" class="w-full flex-1 text-right text-lg font-medium text-black dark:text-white" v-html="item.question"></span>
            </button>
            <div
                class="grid w-full transition-all duration-300 ease-[cubic-bezier(0.16,1,0.3,1)]"
                :class="selected === index + 1 ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'"
            >
                <div class="overflow-hidden">
                    <div dir="auto" class="faq-answer border-t border-[#cfd8e3] px-6 py-5 text-right text-sm leading-6 text-[#445566] dark:border-[#1e2a3a] dark:text-[#94a3b8]" v-html="item.answer"></div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* .faq-accordion / .faq-item / .faq-item--open base + dark-mode rules live in
   app.css (global stylesheet) instead of here — Vue's scoped-CSS handling of
   a selector that's only partially wrapped in :global() (":global(.dark) .foo")
   doesn't reliably apply the dark override in this project's build, so the
   dark-mode background never took effect when those rules lived in this
   scoped block. Un-scoped rules in app.css avoid that entirely. */

.faq-answer :deep(p) {
    margin-top: 0.5rem;
    margin-bottom: 0.5rem;
}

.faq-answer :deep(ul),
.faq-answer :deep(ol) {
    margin-top: 0.5rem;
    margin-bottom: 0.5rem;
    padding-inline-start: 1.25rem;
}

.faq-answer :deep(strong) {
    font-weight: 600;
    /* color (black / dark:white) lives in app.css alongside the other
       .faq-* dark-mode rules — same :global() reliability issue. */
}

.faq-answer :deep(a) {
    color: #25d366;
    text-decoration: underline;
}

.faq-answer :deep(a:hover) {
    color: #1db954;
}
</style>
