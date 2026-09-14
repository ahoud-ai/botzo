<script setup>
    defineProps({
        label: {
            type: String,
            required: true,
        },
    });
</script>

<template>
    <div class="inline-flex items-center gap-2 rounded-xl border border-white/50 bg-white/60 px-4 py-3 shadow-[0_4px_24px_rgba(0,0,0,0.06)] backdrop-blur-[45px] dark:border-white/10 dark:bg-white/[0.06] dark:shadow-[0_4px_24px_rgba(0,0,0,0.25)] lg:rounded-2xl">
        <span
            class="whitespace-nowrap bg-clip-text text-base leading-6 text-transparent"
            style="background-image: linear-gradient(103deg, #25d366 0%, #3b82f6 100%)"
        >
            {{ label }}
        </span>
        <img src="/images/icons/section-badge-mobile.svg" class="h-[13px] w-[13px] lg:hidden" alt="" aria-hidden="true" />
        <img src="/images/icons/section-badge.svg" class="hidden h-[18px] w-[18px] lg:block" alt="" aria-hidden="true" />
    </div>
</template>
