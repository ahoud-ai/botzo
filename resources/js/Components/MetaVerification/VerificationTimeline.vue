<script setup>
const steps = [
    { key: "Payment confirmation", highlight: false },
    { key: "Data review (1-3 business days)", highlight: true },
    { key: "Preparing and submitting the request (2-5 business days)", highlight: false },
    { key: "Following up on Meta's decision (3-10 days)", highlight: false },
];
</script>

<template>
    <section v-reveal class="px-5 md:px-10 lg:px-20 2xl:px-60 py-12 md:py-16 lg:py-20">
        <div class="flex flex-col items-center gap-8 lg:gap-[72px]">
            <h2 class="w-full text-center text-[28px] font-semibold leading-[1.4] text-black dark:text-white lg:text-[52px] lg:leading-[62.4px]">
                {{ $t('Verification happens through') }}
            </h2>

            <div class="flex w-full flex-col items-end gap-4 text-right lg:w-[423px] lg:gap-6">
                <p
                    v-for="step in steps"
                    :key="step.key"
                    dir="auto"
                    class="w-full text-xl leading-[1.5] lg:text-[32px]"
                    :class="step.highlight ? 'text-[#25d366]' : 'text-[#8899aa]'"
                >
                    {{ $t(step.key) }}
                </p>
            </div>
        </div>
    </section>
</template>
