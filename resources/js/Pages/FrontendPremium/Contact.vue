<template>
    <FrontendLayout :companyConfig="props.companyConfig" :pages="props.pages">
        
        <!-- Hero Section -->
        <section class="premium-page-section premium-page-hero px-5 md:px-10 lg:px-20 pt-32 pb-10 2xl:px-60">
            <div class="premium-page-hero-grid"></div>
            <div class="premium-page-content max-w-4xl mx-auto text-center">
                <div class="inline-flex mb-5 premium-page-badge">{{ $t('Contact') }}</div>
                <h1 class="text-3xl md:text-6xl leading-[1.3] font-semibold mb-6 text-gray-900">
                    {{ $t('Get in Touch') }}
                </h1>
                <p class="text-xl text-gray-600 mb-8 max-w-2xl mx-auto">
                    {{ $t('Have a question or need assistance? We\'re here to help. Reach out to us through any of the options below') }}.
                </p>
            </div>
        </section>

        <!-- Contact Options Section -->
        <section class="premium-page-section px-5 md:px-10 lg:px-20 py-20 2xl:px-60">
            <div class="premium-page-content max-w-6xl mx-auto">
                <!-- Contact Cards Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-16">
                    <!-- Call Us Card -->
                    <div class="premium-page-panel premium-page-interactive p-8 flex flex-col">
                        <div class="mb-6">
                            <svg class="w-8 h-8 text-secondary" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24c1.12.37 2.33.57 3.57.57c.55 0 1 .45 1 1V20c0 .55-.45 1-1 1c-9.39 0-17-7.61-17-17c0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1c0 1.25.2 2.45.57 3.57c.11.35.03.74-.25 1.02z"/></svg>
                        </div>
                        <h3 class="text-xl font-medium text-gray-900 mb-3">{{ $t('Call Us') }}</h3>
                        <p class="text-gray-500 mb-6 flex-grow">
                            {{ $t('Speak directly with our team during business hours for immediate assistance') }}.
                        </p>
                        <div v-if="phoneNumbers.length" class="w-full space-y-2">
                            <a
                                v-for="phoneNumber in phoneNumbers"
                                :key="`contact-call-phone-${phoneNumber}`"
                                :href="'tel:' + phoneNumber"
                                class="w-full bg-primary hover:bg-secondary text-white font-normal py-3 px-6 rounded-lg transition-colors text-center inline-block"
                            >
                                {{ phoneNumber }}
                            </a>
                        </div>
                        <button type="submit" 
                            v-else
                            disabled
                            class="w-full bg-gray-300 text-gray-500 font-medium py-3 px-6 rounded-lg cursor-not-allowed"
                        >
                            {{ $t('Not Available') }}
                        </button>
                    </div>

                    <!-- Email Us Card -->
                    <div class="premium-page-panel premium-page-interactive p-8 flex flex-col">
                        <div class="mb-6">
                            <svg class="w-8 h-8 text-secondary" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2m0 4l-8 5l-8-5V6l8 5l8-5z"/></svg>
                        </div>
                        <h3 class="text-xl font-medium text-gray-900 mb-3">{{ $t('Email Us') }}</h3>
                        <p class="text-gray-500 mb-6 flex-grow">
                            {{ $t('Send us an email and we\'ll respond within 24 hours') }}.
                        </p>
                        <a 
                            v-if="props.companyConfig && props.companyConfig.email" 
                            :href="'mailto:' + props.companyConfig.email"
                            class="w-full bg-primary hover:bg-secondary text-white font-normal py-3 px-6 rounded-lg transition-colors text-center inline-block break-words"
                        >
                            {{ props.companyConfig.email }}
                        </a>
                        <button type="submit" 
                            v-else
                            disabled
                            class="w-full bg-gray-300 text-gray-500 font-medium py-3 px-6 rounded-lg cursor-not-allowed"
                        >
                            {{ $t('Not Available') }}
                        </button>
                    </div>

                    <!-- WhatsApp Us Card -->
                    <div class="premium-page-panel premium-page-interactive p-8 flex flex-col">
                        <div class="mb-6">
                            <svg class="w-8 h-8 text-secondary" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967c-.273-.099-.471-.148-.67.15c-.197.297-.767.966-.94 1.164c-.173.199-.347.223-.644.075c-.297-.15-1.255-.463-2.39-1.475c-.883-.788-1.48-1.761-1.653-2.059c-.173-.297-.018-.458.13-.606c.134-.133.298-.347.446-.52c.149-.174.198-.298.298-.497c.099-.198.05-.371-.025-.52c-.075-.149-.669-1.612-.916-2.207c-.242-.579-.487-.5-.669-.51c-.173-.008-.371-.01-.57-.01c-.198 0-.52.074-.792.372c-.272.297-1.04 1.016-1.04 2.479c0 1.462 1.065 2.875 1.213 3.074c.149.198 2.096 3.2 5.077 4.487c.709.306 1.262.489 1.694.625c.712.227 1.36.195 1.871.118c.571-.085 1.758-.719 2.006-1.413c.248-.694.248-1.289.173-1.413c-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214l-3.741.982l.998-3.648l-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884c2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>
                        </div>
                        <h3 class="text-xl font-medium text-gray-900 mb-3">{{ $t('WhatsApp Us') }}</h3>
                        <p class="text-gray-500 mb-6 flex-grow">
                            {{ $t('Chat with us on WhatsApp for instant support and quick responses') }}.
                        </p>
                        <a 
                            v-if="whatsappNumber" 
                            :href="whatsappUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="w-full bg-primary hover:bg-secondary text-white font-normal py-3 px-6 rounded-lg transition-colors text-center inline-block"
                        >
                            {{ whatsappNumber }}
                        </a>
                        <button type="submit" 
                            v-else
                            disabled
                            class="w-full bg-gray-300 text-gray-500 font-medium py-3 px-6 rounded-lg cursor-not-allowed"
                        >
                            {{ $t('Not Available') }}
                        </button>
                    </div>
                </div>

                <!-- Additional Information Section -->
                <div class="max-w-6xl mx-auto">
                    <div class="premium-page-panel p-8 md:p-12">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                            <!-- Business Hours -->
                            <div v-if="businessHoursLines.length">
                                <div class="flex items-center gap-3 mb-4">
                                    <div class="">
                                        <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2M12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8s8 3.58 8 8s-3.58 8-8 8m.5-13H11v6l5.25 3.15l.75-1.23l-4.5-2.67z"/></svg>
                                    </div>
                                    <h3 class="text-xl font-semibold text-gray-900">{{ $t('Business Hours') }}</h3>
                                </div>
                                <div class="space-y-2">
                                    <p v-for="(line, index) in businessHoursLines" :key="`contact-business-hours-${index}`" class="text-gray-700">
                                        {{ line }}
                                    </p>
                                </div>
                            </div>

                            <!-- Address -->
                            <div v-if="addressLines.length">
                                <div class="flex items-center gap-3 mb-4">
                                    <div class="">
                                        <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7m0 9.5a2.5 2.5 0 0 1 0-5a2.5 2.5 0 0 1 0 5"/></svg>
                                    </div>
                                    <h3 class="text-xl font-semibold text-gray-900">{{ $t('Address') }}</h3>
                                </div>
                                <div class="space-y-1">
                                    <p v-for="(addressLine, index) in addressLines" :key="`contact-address-${index}`" class="text-gray-700">
                                        {{ addressLine }}
                                    </p>
                                </div>
                            </div>

                            <!-- Social Media -->
                            <div v-if="hasSocialMedia">
                                <div class="flex items-center gap-3 mb-4">
                                    <div class="">
                                        <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81c1.66 0 3-1.34 3-3s-1.34-3-3-3s-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65c0 1.61 1.31 2.92 2.92 2.92c1.61 0 2.92-1.31 2.92-2.92s-1.31-2.92-2.92-2.92"/></svg>
                                    </div>
                                    <h3 class="text-xl font-semibold text-gray-900">{{ $t('Follow Us') }}</h3>
                                </div>
                                <div class="flex flex-wrap gap-4">
                                    <a v-if="facebookUrl" :href="facebookUrl" target="_blank" rel="noopener noreferrer" class="group w-12 h-12 rounded-xl bg-white border border-gray-200 hover:bg-primary hover:border-primary hover:text-white flex items-center justify-center transition-all duration-200 text-gray-600 shadow-sm hover:shadow-md">
                                        <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95c5.05-.5 9-4.76 9-9.95"/></svg>
                                    </a>
                                    <a v-if="twitterUrl" :href="twitterUrl" target="_blank" rel="noopener noreferrer" class="group w-12 h-12 rounded-xl bg-white border border-gray-200 hover:bg-primary hover:border-primary hover:text-white flex items-center justify-center transition-all duration-200 text-gray-600 shadow-sm hover:shadow-md">
                                        <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M22 5.8a8.49 8.49 0 0 1-2.36.64a4.13 4.13 0 0 0 1.81-2.27a8.21 8.21 0 0 1-2.61 1a4.1 4.1 0 0 0-7 3.74a11.64 11.64 0 0 1-8.45-4.29a4.16 4.16 0 0 0-.55 2.07a4.09 4.09 0 0 0 1.82 3.41a4.05 4.05 0 0 1-1.86-.51v.05a4.1 4.1 0 0 0 3.3 4a3.93 3.93 0 0 1-1.1.17a4.9 4.9 0 0 1-.77-.07a4.11 4.11 0 0 0 3.83 2.84A8.22 8.22 0 0 1 3 18.34a7.93 7.93 0 0 1-1-.06a11.57 11.57 0 0 0 6.29 1.85A11.59 11.59 0 0 0 20 8.45v-.53a8.43 8.43 0 0 0 2-2.12"/></svg>
                                    </a>
                                    <a v-if="linkedinUrl" :href="linkedinUrl" target="_blank" rel="noopener noreferrer" class="group w-12 h-12 rounded-xl bg-white border border-gray-200 hover:bg-primary hover:border-primary hover:text-white flex items-center justify-center transition-all duration-200 text-gray-600 shadow-sm hover:shadow-md">
                                        <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 16 16"><path fill="currentColor" d="M0 1.146C0 .513.526 0 1.175 0h13.65C15.474 0 16 .513 16 1.146v13.708c0 .633-.526 1.146-1.175 1.146H1.175C.526 16 0 15.487 0 14.854zm4.943 12.248V6.169H2.542v7.225zm-1.2-8.212c.837 0 1.358-.554 1.358-1.248c-.015-.709-.52-1.248-1.342-1.248S2.4 3.226 2.4 3.934c0 .694.521 1.248 1.327 1.248zm4.908 8.212V9.359c0-.216.016-.432.08-.586c.173-.431.568-.878 1.232-.878c.869 0 1.216.662 1.216 1.634v3.865h2.401V9.25c0-2.22-1.184-3.252-2.764-3.252c-1.274 0-1.845.7-2.165 1.193v.025h-.016l.016-.025V6.169h-2.4c.03.678 0 7.225 0 7.225z"/></svg>
                                    </a>
                                    <a v-if="tiktokUrl" :href="tiktokUrl" target="_blank" rel="noopener noreferrer" class="group w-12 h-12 rounded-xl bg-white border border-gray-200 hover:bg-primary hover:border-primary hover:text-white flex items-center justify-center transition-all duration-200 text-gray-600 shadow-sm hover:shadow-md">
                                        <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.4v13.57a2.89 2.89 0 0 1-2.89 2.88a2.89 2.89 0 0 1 0-5.77c.18 0 .35.02.52.05V9.27a6.29 6.29 0 0 0-.52-.02a6.3 6.3 0 0 0 0 12.6a6.3 6.3 0 0 0 6.3-6.3V8.66a8.18 8.18 0 0 0 4.77 1.53V6.82a4.87 4.87 0 0 1-1.01-.13"/></svg>
                                    </a>
                                    <a v-if="snapchatUrl" :href="snapchatUrl" target="_blank" rel="noopener noreferrer" class="group w-12 h-12 rounded-xl bg-white border border-gray-200 hover:bg-primary hover:border-primary hover:text-white flex items-center justify-center transition-all duration-200 text-gray-600 shadow-sm hover:shadow-md">
                                        <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2a7 7 0 0 0-7 7v11l2-2l2 2l3-3l3 3l2-2l2 2V9a7 7 0 0 0-7-7m-3 8a1 1 0 1 1 .01 0zm6 0a1 1 0 1 1 .01 0z"/></svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Call to Action Section -->
        <section class="premium-page-section premium-page-cta px-5 md:px-10 lg:px-20 py-20 2xl:px-60">
            <div class="premium-page-content max-w-4xl mx-auto text-center">
                <h2 class="text-3xl md:text-5xl font-semibold text-gray-900 mb-6">
                    {{ $t('Ready to Get Started?') }}
                </h2>
                <p class="text-xl text-gray-600 mb-10 max-w-2xl mx-auto">
                    {{ $t('Join thousands of businesses already using our platform to streamline their communication and grow their customer base.') }}
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <Link href="/signup" class="px-6 py-3 bg-primary hover:bg-secondary text-white rounded-xl text-base font-semibold transition-all duration-300 shadow-md hover:shadow-lg transform hover:scale-105">
                        {{ $t('Subscribe now') }}
                    </Link>
                    <a v-if="props.companyConfig && props.companyConfig.book_a_demo_link" :href="props.companyConfig.book_a_demo_link" target="_blank" rel="noopener noreferrer" class="px-6 py-3 border-2 border-gray-300 hover:border-primary text-gray-700 hover:text-primary rounded-xl text-base font-semibold transition-all duration-300 bg-white">
                        {{ $t('Book a Demo') }}
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
    import { useFrontendContactInfo } from '@/Composables/useFrontendContactInfo';

    const props = defineProps(['companyConfig', 'pages']);
    const page = usePage();

    const facebookUrl = ref(null);
    const twitterUrl = ref(null);
    const tiktokUrl = ref(null);
    const snapchatUrl = ref(null);
    const linkedinUrl = ref(null);
    const whatsappNumber = ref(null);
    const whatsappUrl = ref(null);
    const currentLanguageCode = computed(() => page.props.currentLanguage || 'en');
    const companyConfigRef = computed(() => props.companyConfig || {});
    const { addressLines, businessHoursLines, phoneNumbers, primaryPhone } = useFrontendContactInfo(companyConfigRef, currentLanguageCode);

    const hasSocialMedia = computed(() => {
        return facebookUrl.value || twitterUrl.value || linkedinUrl.value || tiktokUrl.value || snapchatUrl.value;
    });

    const parseSocials = () => {
        if (!props.companyConfig || !props.companyConfig.socials) return;
        try {
            const socialsArray = JSON.parse(props.companyConfig.socials || '{}');
            facebookUrl.value = socialsArray['facebook'] || null;
            twitterUrl.value = socialsArray['twitter'] || null;
            tiktokUrl.value = socialsArray['tiktok'] || null;
            snapchatUrl.value = socialsArray['snapchat'] || null;
            linkedinUrl.value = socialsArray['linkedin'] || null;
            whatsappNumber.value = socialsArray['whatsapp'] || primaryPhone.value || null;
            
            // Generate WhatsApp URL if phone number is available
            if (whatsappNumber.value) {
                // Remove any non-numeric characters except + for international format
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
