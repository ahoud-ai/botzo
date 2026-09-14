<script setup>
    defineProps({
        variant: {
            type: String,
            default: 'desktop', // 'desktop' | 'mobile'
        },
    });
</script>

<template>
    <div
        class="relative shrink-0"
        :style="variant === 'mobile' ? { width: '44px', height: '40.333px' } : { width: '72px', height: '66px' }"
    >
        <!-- Dark theme mark -->
        <div class="absolute inset-0 hidden dark:block">
            <img
                v-if="variant === 'mobile'"
                src="/images/nav/nav-mobile-icon-dark.svg" alt="" class="absolute max-w-none"
                style="left: 10.54px; top: 0; width: 23.229px; height: 22.658px;"
            >
            <img
                v-else
                src="/images/nav/nav-icon-dark.svg" alt="" class="absolute max-w-none"
                style="left: 17.49px; top: 0; width: 38.54px; height: 37.591px;"
            >
            <img
                :src="variant === 'mobile' ? '/images/nav/nav-mobile-wordmark-dark.svg' : '/images/nav/nav-wordmark-dark.svg'"
                alt="Botzo" class="absolute max-w-none"
                style="left: 0; top: 58.93%; width: 100%; height: 27.78%;"
            >
            <p
                class="absolute whitespace-nowrap leading-none"
                :style="variant === 'mobile'
                    ? { left: '2.48px', top: '36.98px', fontSize: '2.791px', letterSpacing: '0.1116px' }
                    : { left: '4.12px', top: '61.35px', fontSize: '4.63px', letterSpacing: '0.1852px' }"
            >
                <span style="color: #9eed42;">WhatsApp</span><span class="text-white"> &amp; </span><span style="color: #4230f8;">Meta</span><span class="text-white"> Solutions</span>
            </p>
        </div>

        <!-- Light theme mark -->
        <div class="absolute inset-0 block dark:hidden">
            <img
                v-if="variant === 'mobile'"
                src="/images/nav/nav-mobile-icon-light.svg" alt="" class="absolute max-w-none"
                style="left: 10.49px; top: 0; width: 23.114px; height: 22.545px;"
            >
            <img
                v-else
                src="/images/nav/nav-icon-light.svg" alt="" class="absolute max-w-none"
                style="left: 17.17px; top: 0; width: 37.823px; height: 36.892px;"
            >
            <img
                :src="variant === 'mobile' ? '/images/nav/nav-mobile-wordmark-light.svg' : '/images/nav/nav-wordmark-light.svg'"
                alt="Botzo" class="absolute max-w-none"
                style="left: 0; top: 58.93%; width: 100%; height: 27.78%;"
            >
            <p
                class="absolute whitespace-nowrap leading-none"
                :style="variant === 'mobile'
                    ? { left: '2.47px', top: '36.8px', fontSize: '2.777px', letterSpacing: '0.1111px' }
                    : { left: '4.04px', top: '60.21px', fontSize: '4.544px', letterSpacing: '0.1818px' }"
            >
                <span style="color: #25d366;">WhatsApp</span><span style="color: #25d366;"> &amp; </span><span style="color: #3a5af7;">Meta</span><span style="color: #25d366;"> Solutions</span>
            </p>
        </div>
    </div>
</template>
