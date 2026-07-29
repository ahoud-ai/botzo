<script setup>
    defineProps({
        label: {
            type: String,
            required: true,
        },
    });
</script>

<template>
    <div class="inline-flex items-center gap-2 rounded-xl px-4 py-3 lg:rounded-2xl">
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
