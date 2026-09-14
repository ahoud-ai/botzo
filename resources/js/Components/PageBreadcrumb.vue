<script setup>
import { Link } from "@inertiajs/vue3";

defineProps({
    currentLabel: { type: String, required: true },
});
</script>

<template>
    <div class="flex h-6 w-full items-center justify-end gap-2" dir="ltr">
        <span dir="auto" class="whitespace-nowrap text-sm font-medium leading-5 text-[#2bd46a]">{{ currentLabel }}</span>
        <span class="text-base leading-6 text-[#8899aa]">/</span>
        <Link href="/" class="flex items-center gap-1">
            <span dir="auto" class="whitespace-nowrap text-sm leading-6 text-[#8899aa]">{{ $t('Home Page') }}</span>
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M1.5 8L7.46933 2.03C7.76267 1.73733 8.23733 1.73733 8.53 2.03L14.5 8M3 6.5V13.25C3 13.664 3.336 14 3.75 14H6.5V10.75C6.5 10.336 6.836 10 7.25 10H8.75C9.164 10 9.5 10.336 9.5 10.75V14H12.25C12.664 14 13 13.664 13 13.25V6.5M5.5 14H11" stroke="#8899AA" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </Link>
    </div>
</template>
