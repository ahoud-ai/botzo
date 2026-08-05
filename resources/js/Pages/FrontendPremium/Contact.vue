<template>
    <FrontendLayout :companyConfig="props.companyConfig" :pages="props.pages">
        <!-- Hero -->
        <section class="px-5 md:px-10 lg:px-20 2xl:px-60 pt-32 pb-8 bg-white dark:bg-[#0a0f17]">
            <div class="flex flex-col items-center gap-4 lg:gap-6">
                <PageBreadcrumb :current-label="$t('Contact Us')" />

                <div class="flex w-full flex-col items-center gap-3 text-center lg:w-[1120px] lg:gap-4">
                    <SectionBadge :label="$t('Contact Us')" />
                    <h1 class="w-full text-[28px] font-semibold leading-[1.3] text-black dark:text-white lg:text-[46px] lg:leading-[56px]">
                        {{ $t('Get in Touch') }}
                    </h1>
                    <p dir="auto" class="w-full text-base leading-6 text-[#8899aa] lg:text-[24px] lg:leading-9">
                        {{ $t("Have a question or need assistance? We're here to help. Reach out to us through any of the options below") }}.
                    </p>
                </div>
            </div>
        </section>

        <!-- Contact options -->
        <section class="px-5 md:px-10 lg:px-20 2xl:px-60 py-12 md:py-16 lg:py-20 bg-white dark:bg-[#0a0f17]">
            <div class="flex flex-col items-center gap-10 lg:w-[1120px] lg:mx-auto lg:gap-[72px]">
                <div class="grid w-full grid-cols-1 gap-6 md:grid-cols-3">
                    <!-- Call Us -->
                    <div class="group flex flex-col items-end gap-4 rounded-3xl border border-[#cfd8e3] bg-white p-6 text-right transition-all duration-300 hover:border-[#25d366] hover:shadow-[0_0_0_1px_rgba(37,211,102,0.2),0_18px_30px_rgba(37,211,102,0.12)] dark:border-[#1e2a3a] dark:bg-[#0a0f17]">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-[rgba(37,211,102,0.12)]">
                            <svg class="h-6 w-6 text-[#25d366]" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24c1.12.37 2.33.57 3.57.57c.55 0 1 .45 1 1V20c0 .55-.45 1-1 1c-9.39 0-17-7.61-17-17c0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1c0 1.25.2 2.45.57 3.57c.11.35.03.74-.25 1.02z"/></svg>
                        </span>
                        <div class="flex w-full flex-col items-end gap-2">
                            <h3 dir="auto" class="w-full text-lg font-semibold text-black dark:text-white">{{ $t('Call Us') }}</h3>
                            <p dir="auto" class="w-full text-sm leading-6 text-[#445566] dark:text-[#94a3b8]">
                                {{ $t('Speak directly with our team during business hours for immediate assistance') }}.
                            </p>
                        </div>
                        <div v-if="phoneNumbers.length" class="flex w-full flex-col gap-2">
                            <a
                                v-for="phoneNumber in phoneNumbers"
                                :key="`contact-call-phone-${phoneNumber}`"
                                :href="'tel:' + phoneNumber"
                                dir="ltr"
                                class="flex h-11 w-full items-center justify-center rounded-2xl text-center text-sm font-semibold text-[#04130a] transition-transform duration-200 hover:scale-[1.02]"
                                style="background-image: linear-gradient(135deg, #25d366 0%, #1db954 100%)"
                            >
                                {{ phoneNumber }}
                            </a>
                        </div>
                        <span v-else class="flex h-11 w-full cursor-not-allowed items-center justify-center rounded-2xl bg-black/5 text-sm font-medium text-[#8899aa] dark:bg-white/5">
                            {{ $t('Not Available') }}
                        </span>
                    </div>

                    <!-- Email Us -->
                    <div class="group flex flex-col items-end gap-4 rounded-3xl border border-[#cfd8e3] bg-white p-6 text-right transition-all duration-300 hover:border-[#25d366] hover:shadow-[0_0_0_1px_rgba(37,211,102,0.2),0_18px_30px_rgba(37,211,102,0.12)] dark:border-[#1e2a3a] dark:bg-[#0a0f17]">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-[rgba(37,211,102,0.12)]">
                            <svg class="h-6 w-6 text-[#25d366]" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2m0 4l-8 5l-8-5V6l8 5l8-5z"/></svg>
                        </span>
                        <div class="flex w-full flex-col items-end gap-2">
                            <h3 dir="auto" class="w-full text-lg font-semibold text-black dark:text-white">{{ $t('Email Us') }}</h3>
                            <p dir="auto" class="w-full text-sm leading-6 text-[#445566] dark:text-[#94a3b8]">
                                {{ $t("Send us an email and we'll respond within 24 hours") }}.
                            </p>
                        </div>
                        <a
                            v-if="props.companyConfig && props.companyConfig.email"
                            :href="'mailto:' + props.companyConfig.email"
                            class="flex h-11 w-full items-center justify-center rounded-2xl px-2 text-center text-sm font-semibold text-[#04130a] transition-transform duration-200 hover:scale-[1.02]"
                            style="background-image: linear-gradient(135deg, #25d366 0%, #1db954 100%)"
                        >
                            <span class="truncate">{{ props.companyConfig.email }}</span>
                        </a>
                        <span v-else class="flex h-11 w-full cursor-not-allowed items-center justify-center rounded-2xl bg-black/5 text-sm font-medium text-[#8899aa] dark:bg-white/5">
                            {{ $t('Not Available') }}
                        </span>
                    </div>

                    <!-- WhatsApp Us -->
                    <div class="group flex flex-col items-end gap-4 rounded-3xl border border-[#cfd8e3] bg-white p-6 text-right transition-all duration-300 hover:border-[#25d366] hover:shadow-[0_0_0_1px_rgba(37,211,102,0.2),0_18px_30px_rgba(37,211,102,0.12)] dark:border-[#1e2a3a] dark:bg-[#0a0f17]">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-[rgba(37,211,102,0.12)]">
                            <svg class="h-6 w-6 text-[#25d366]" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967c-.273-.099-.471-.148-.67.15c-.197.297-.767.966-.94 1.164c-.173.199-.347.223-.644.075c-.297-.15-1.255-.463-2.39-1.475c-.883-.788-1.48-1.761-1.653-2.059c-.173-.297-.018-.458.13-.606c.134-.133.298-.347.446-.52c.149-.174.198-.298.298-.497c.099-.198.05-.371-.025-.52c-.075-.149-.669-1.612-.916-2.207c-.242-.579-.487-.5-.669-.51c-.173-.008-.371-.01-.57-.01c-.198 0-.52.074-.792.372c-.272.297-1.04 1.016-1.04 2.479c0 1.462 1.065 2.875 1.213 3.074c.149.198 2.096 3.2 5.077 4.487c.709.306 1.262.489 1.694.625c.712.227 1.36.195 1.871.118c.571-.085 1.758-.719 2.006-1.413c.248-.694.248-1.289.173-1.413c-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214l-3.741.982l.998-3.648l-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884c2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>
                        </span>
                        <div class="flex w-full flex-col items-end gap-2">
                            <h3 dir="auto" class="w-full text-lg font-semibold text-black dark:text-white">{{ $t('WhatsApp Us') }}</h3>
                            <p dir="auto" class="w-full text-sm leading-6 text-[#445566] dark:text-[#94a3b8]">
                                {{ $t('Chat with us on WhatsApp for instant support and quick responses') }}.
                            </p>
                        </div>
                        <a
                            v-if="whatsappNumber"
                            :href="whatsappUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            dir="ltr"
                            class="flex h-11 w-full items-center justify-center rounded-2xl text-center text-sm font-semibold text-[#04130a] transition-transform duration-200 hover:scale-[1.02]"
                            style="background-image: linear-gradient(135deg, #25d366 0%, #1db954 100%)"
                        >
                            {{ whatsappNumber }}
                        </a>
                        <span v-else class="flex h-11 w-full cursor-not-allowed items-center justify-center rounded-2xl bg-black/5 text-sm font-medium text-[#8899aa] dark:bg-white/5">
                            {{ $t('Not Available') }}
                        </span>
                    </div>
                </div>

                <!-- Business hours / address / social -->
                <div class="grid w-full grid-cols-1 gap-8 rounded-3xl border border-[#cfd8e3] bg-white p-6 dark:border-[#1e2a3a] dark:bg-[#0a0f17] md:grid-cols-3 md:p-10">
                    <div v-if="businessHoursLines.length" class="flex flex-col items-end gap-3 text-right">
                        <div class="flex w-full items-center justify-end gap-2" dir="ltr">
                            <h3 dir="auto" class="text-lg font-semibold text-black dark:text-white">{{ $t('Business Hours') }}</h3>
                            <svg class="h-5 w-5 text-[#25d366]" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2M12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8s8 3.58 8 8s-3.58 8-8 8m.5-13H11v6l5.25 3.15l.75-1.23l-4.5-2.67z"/></svg>
                        </div>
                        <p v-for="(line, index) in businessHoursLines" :key="`contact-business-hours-${index}`" dir="auto" class="w-full text-sm leading-6 text-[#445566] dark:text-[#94a3b8]">
                            {{ line }}
                        </p>
                    </div>

                    <div v-if="addressLines.length" class="flex flex-col items-end gap-3 text-right">
                        <div class="flex w-full items-center justify-end gap-2" dir="ltr">
                            <h3 dir="auto" class="text-lg font-semibold text-black dark:text-white">{{ $t('Address') }}</h3>
                            <svg class="h-5 w-5 text-[#25d366]" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7m0 9.5a2.5 2.5 0 0 1 0-5a2.5 2.5 0 0 1 0 5"/></svg>
                        </div>
                        <p v-for="(addressLine, index) in addressLines" :key="`contact-address-${index}`" dir="auto" class="w-full text-sm leading-6 text-[#445566] dark:text-[#94a3b8]">
                            {{ addressLine }}
                        </p>
                    </div>

                    <div v-if="hasSocialMedia" class="flex flex-col items-end gap-3 text-right">
                        <div class="flex w-full items-center justify-end gap-2" dir="ltr">
                            <h3 dir="auto" class="text-lg font-semibold text-black dark:text-white">{{ $t('Follow Us') }}</h3>
                            <svg class="h-5 w-5 text-[#25d366]" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81c1.66 0 3-1.34 3-3s-1.34-3-3-3s-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65c0 1.61 1.31 2.92 2.92 2.92c1.61 0 2.92-1.31 2.92-2.92s-1.31-2.92-2.92-2.92"/></svg>
                        </div>
                        <div class="flex w-full items-center justify-end gap-3" dir="ltr">
                            <a v-if="whatsappUrl" :href="whatsappUrl" target="_blank" rel="noopener noreferrer" class="flex h-11 w-11 items-center justify-center rounded-xl border border-[#cfd8e3] text-[#445566] transition-colors hover:border-[#25d366] hover:text-[#25d366] dark:border-[#1e2a3a] dark:text-[#94a3b8]">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967c-.273-.099-.471-.148-.67.15c-.197.297-.767.966-.94 1.164c-.173.199-.347.223-.644.075c-.297-.15-1.255-.463-2.39-1.475c-.883-.788-1.48-1.761-1.653-2.059c-.173-.297-.018-.458.13-.606c.134-.133.298-.347.446-.52c.149-.174.198-.298.298-.497c.099-.198.05-.371-.025-.52c-.075-.149-.669-1.612-.916-2.207c-.242-.579-.487-.5-.669-.51c-.173-.008-.371-.01-.57-.01c-.198 0-.52.074-.792.372c-.272.297-1.04 1.016-1.04 2.479c0 1.462 1.065 2.875 1.213 3.074c.149.198 2.096 3.2 5.077 4.487c.709.306 1.262.489 1.694.625c.712.227 1.36.195 1.871.118c.571-.085 1.758-.719 2.006-1.413c.248-.694.248-1.289.173-1.413c-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214l-3.741.982l.998-3.648l-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884c2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>
                            </a>
                            <a v-if="linkedinUrl" :href="linkedinUrl" target="_blank" rel="noopener noreferrer" class="flex h-11 w-11 items-center justify-center rounded-xl border border-[#cfd8e3] text-[#445566] transition-colors hover:border-[#25d366] hover:text-[#25d366] dark:border-[#1e2a3a] dark:text-[#94a3b8]">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 16 16"><path fill="currentColor" d="M0 1.146C0 .513.526 0 1.175 0h13.65C15.474 0 16 .513 16 1.146v13.708c0 .633-.526 1.146-1.175 1.146H1.175C.526 16 0 15.487 0 14.854zm4.943 12.248V6.169H2.542v7.225zm-1.2-8.212c.837 0 1.358-.554 1.358-1.248c-.015-.709-.52-1.248-1.342-1.248S2.4 3.226 2.4 3.934c0 .694.521 1.248 1.327 1.248zm4.908 8.212V9.359c0-.216.016-.432.08-.586c.173-.431.568-.878 1.232-.878c.869 0 1.216.662 1.216 1.634v3.865h2.401V9.25c0-2.22-1.184-3.252-2.764-3.252c-1.274 0-1.845.7-2.165 1.193v.025h-.016l.016-.025V6.169h-2.4c.03.678 0 7.225 0 7.225z"/></svg>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="px-5 md:px-10 lg:px-20 2xl:px-60 py-12 md:py-16 lg:py-20">
            <div class="flex w-full flex-col items-center gap-6 overflow-hidden rounded-3xl bg-[rgba(37,211,102,0.12)] px-6 py-12 text-center md:px-10 lg:px-20 lg:py-20">
                <div class="flex w-full flex-col items-center gap-4 lg:w-[1120px] lg:gap-4">
                    <h2 class="w-full text-[22px] font-semibold leading-[29.9px] text-black dark:text-white lg:text-[46px] lg:leading-[56px]">
                        {{ $t('Ready to Get Started?') }}
                    </h2>
                    <p class="w-full text-base leading-6 text-[#8899aa] lg:text-[24px] lg:leading-9">
                        {{ $t('Join thousands of businesses already using our platform to streamline their communication and grow their customer base.') }}
                    </p>
                </div>
                <div class="flex w-full flex-col items-center gap-3 sm:w-auto sm:flex-row" dir="ltr">
                    <Link
                        href="/signup"
                        class="flex h-[54px] w-full items-center justify-center rounded-2xl px-8 transition-transform duration-200 hover:scale-[1.03] sm:w-[220px]"
                        style="background-image: linear-gradient(135.79deg, #25d366 0%, #1db954 100%)"
                    >
                        <span dir="auto" class="whitespace-nowrap text-base font-semibold leading-5 text-[#04130a]">{{ $t('Subscribe now') }}</span>
                    </Link>
                    <a
                        v-if="props.companyConfig && props.companyConfig.book_a_demo_link"
                        :href="props.companyConfig.book_a_demo_link"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex h-[54px] w-full items-center justify-center rounded-2xl border border-[#1a2332] px-8 transition-colors hover:border-[#25d366] dark:border-white sm:w-[220px]"
                    >
                        <span dir="auto" class="whitespace-nowrap text-base font-semibold leading-5 text-black dark:text-white">{{ $t('Book a Demo') }}</span>
                    </a>
                </div>
            </div>
        </section>
    </FrontendLayout>
</template>

<script setup>
    import { ref, computed, onMounted } from 'vue';
    import { Link, usePage } from '@inertiajs/vue3';
    import FrontendLayout from './FrontendLayout.vue';
    import SectionBadge from '@/Components/SectionBadge.vue';
    import PageBreadcrumb from '@/Components/PageBreadcrumb.vue';
    import { useFrontendContactInfo } from '@/Composables/useFrontendContactInfo';

    const props = defineProps(['companyConfig', 'pages']);
    const page = usePage();

    const linkedinUrl = ref(null);
    const whatsappNumber = ref(null);
    const whatsappUrl = ref(null);
    const currentLanguageCode = computed(() => page.props.currentLanguage || 'en');
    const companyConfigRef = computed(() => props.companyConfig || {});
    const { addressLines, businessHoursLines, phoneNumbers, primaryPhone } = useFrontendContactInfo(companyConfigRef, currentLanguageCode);

    const hasSocialMedia = computed(() => linkedinUrl.value || whatsappUrl.value);

    const parseSocials = () => {
        if (!props.companyConfig || !props.companyConfig.socials) return;
        try {
            const socialsArray = JSON.parse(props.companyConfig.socials || '{}');
            linkedinUrl.value = socialsArray['linkedin'] || null;
            whatsappNumber.value = socialsArray['whatsapp'] || primaryPhone.value || null;

            if (whatsappNumber.value) {
                const cleanNumber = whatsappNumber.value.replace(/[^\d+]/g, '');
                whatsappUrl.value = `https://wa.me/${cleanNumber}`;
            }
        } catch (error) {
            console.error('Error parsing socials:', error);
        }
    };

    onMounted(() => {
        parseSocials();
    });
</script>
