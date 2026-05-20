<script setup>
    import { ref, onMounted, onUnmounted } from 'vue';
    import { router } from "@inertiajs/vue3";
    import { useI18n } from 'vue-i18n';
    const { t } = useI18n();
    
    const props = defineProps(['appId', 'configId', 'graphAPIVersion'])

    const isSetupLoading = ref(false);
    const isSdkReady = ref(false);
    const sdkScriptFailed = ref(false);
    const isMessageListenerAttached = ref(false);
    const embeddedSignupData = ref({
        waba_id: null,
        phone_number_id: null,
    });

    onMounted(() => {
        window.fbAsyncInit = function () {
            // JavaScript SDK configuration and setup
            if (typeof window.FB === 'undefined') {
                return;
            }

            window.FB.init({
                appId: props.appId, // Facebook App ID
                cookie: true, // enable cookies
                xfbml: true, // parse social plugins on this page
                version: props.graphAPIVersion // Graph API version
            });
            isSdkReady.value = true;
        };

        // Load the JavaScript SDK asynchronously
        (function (d, s, id) {
            var js, fjs = d.getElementsByTagName(s)[0];
            if (d.getElementById(id)) {
                if (typeof window.FB !== 'undefined') {
                    isSdkReady.value = true;
                }
                return;
            }
            js = d.createElement(s);
            js.id = id;
            js.src = "https://connect.facebook.net/en_US/sdk.js";
            js.onerror = function () {
                sdkScriptFailed.value = true;
            };
            fjs.parentNode.insertBefore(js, fjs);
        }(document, 'script', 'facebook-jssdk'));
    });

    const sessionInfoListener = (event) => {
        if (event.origin !== "https://www.facebook.com" && event.origin !== "https://web.facebook.com") {
            return;
        }
        
        try {
            const data = JSON.parse(event.data);
            if (data.type === 'WA_EMBEDDED_SIGNUP') {
                // if user finishes the Embedded Signup flow
                if (data.event === 'FINISH') {
                    const {phone_number_id, waba_id} = data.data;
                    embeddedSignupData.value.phone_number_id = phone_number_id ?? null;
                    embeddedSignupData.value.waba_id = waba_id ?? null;
                }
                // if user cancels the Embedded Signup flow
                else {
                    const{current_step} = data.data;
                }
            }
        } catch {
            // Don’t parse info that’s not a JSON
        }
    };

    onUnmounted(() => {
        if (isMessageListenerAttached.value) {
            window.removeEventListener("message", sessionInfoListener);
            isMessageListenerAttached.value = false;
        }
    });

    function launchWhatsAppSignup() {
        if (window.location.protocol !== 'https:') {
            alert(t('Embedded signup requires HTTPS. Please use a secure URL.'));
            return;
        }

        if (!props.appId || !props.configId) {
            alert(t('Embedded signup is not configured correctly. Contact the administrator.'));
            return;
        }

        if (sdkScriptFailed.value) {
            alert(t('Unable to load Facebook SDK.'));
            return;
        }

        if (!isSdkReady.value || typeof window.FB === 'undefined') {
            alert(t('Facebook SDK is still loading. Please try again in a few seconds.'));
            return;
        }

        window.addEventListener("message", sessionInfoListener);
        isMessageListenerAttached.value = true;

        // Conversion tracking code
        if (typeof fbq !== 'undefined') {
            fbq('trackCustom', 'WhatsAppOnboardingStart', {
                appId: props.appId,
                feature: 'whatsapp_embedded_signup'
            });
        }

        // Launch Facebook login
        window.FB.login(function (response) {
            if (response.authResponse) {
                isSetupLoading.value = true;
                router.post(`/whatsapp/exchange-code`, {
                    token: response.authResponse.code,
                    waba_id: embeddedSignupData.value.waba_id,
                    phone_number_id: embeddedSignupData.value.phone_number_id,
                }, {
                    preserveState: true,
                    onSuccess: () => {
                        router.visit('/settings/whatsapp', {
                            preserveState: false,
                        });
                    },
                    onFinish: () => {
                        if (isMessageListenerAttached.value) {
                            window.removeEventListener("message", sessionInfoListener);
                            isMessageListenerAttached.value = false;
                        }
                        isSetupLoading.value = false;
                    },
                })
            } else {
                if (isMessageListenerAttached.value) {
                    window.removeEventListener("message", sessionInfoListener);
                    isMessageListenerAttached.value = false;
                }
            }
        }, {
            config_id: props.configId, // configuration ID goes here
            response_type: 'code', // must be set to 'code' for System User access token
            override_default_response_type: true, // when true, any response types passed in the "response_type" will take precedence over the default types
            extras: {
                sessionInfoVersion: 2,
                setup: {
                    // Prefilled data can go here
                }
            }
        });
    }
</script>
<template>
    <div v-if="isSetupLoading" class="fixed inset-0 ui-layer-modal bg-black bg-opacity-40 flex items-center justify-center">
        <div class="bg-white p-6 rounded-lg shadow-lg text-center text-sm">
            <div class="flex justify-center mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 24 24"><path fill="black" d="M12,1A11,11,0,1,0,23,12,11,11,0,0,0,12,1Zm0,20a9,9,0,1,1,9-9A9,9,0,0,1,12,21Z" transform="matrix(0 0 0 0 12 12)"><animateTransform id="svgSpinnersPulseRingsMultiple0" attributeName="transform" begin="0;svgSpinnersPulseRingsMultiple2.end" calcMode="spline" dur="1.2s" keySplines=".52,.6,.25,.99" type="translate" values="12 12;0 0"/><animateTransform additive="sum" attributeName="transform" begin="0;svgSpinnersPulseRingsMultiple2.end" calcMode="spline" dur="1.2s" keySplines=".52,.6,.25,.99" type="scale" values="0;1"/><animate attributeName="opacity" begin="0;svgSpinnersPulseRingsMultiple2.end" calcMode="spline" dur="1.2s" keySplines=".52,.6,.25,.99" values="1;0"/></path><path fill="black" d="M12,1A11,11,0,1,0,23,12,11,11,0,0,0,12,1Zm0,20a9,9,0,1,1,9-9A9,9,0,0,1,12,21Z" transform="matrix(0 0 0 0 12 12)"><animateTransform id="svgSpinnersPulseRingsMultiple1" attributeName="transform" begin="svgSpinnersPulseRingsMultiple0.begin+0.2s" calcMode="spline" dur="1.2s" keySplines=".52,.6,.25,.99" type="translate" values="12 12;0 0"/><animateTransform additive="sum" attributeName="transform" begin="svgSpinnersPulseRingsMultiple0.begin+0.2s" calcMode="spline" dur="1.2s" keySplines=".52,.6,.25,.99" type="scale" values="0;1"/><animate attributeName="opacity" begin="svgSpinnersPulseRingsMultiple0.begin+0.2s" calcMode="spline" dur="1.2s" keySplines=".52,.6,.25,.99" values="1;0"/></path><path fill="black" d="M12,1A11,11,0,1,0,23,12,11,11,0,0,0,12,1Zm0,20a9,9,0,1,1,9-9A9,9,0,0,1,12,21Z" transform="matrix(0 0 0 0 12 12)"><animateTransform id="svgSpinnersPulseRingsMultiple2" attributeName="transform" begin="svgSpinnersPulseRingsMultiple0.begin+0.4s" calcMode="spline" dur="1.2s" keySplines=".52,.6,.25,.99" type="translate" values="12 12;0 0"/><animateTransform additive="sum" attributeName="transform" begin="svgSpinnersPulseRingsMultiple0.begin+0.4s" calcMode="spline" dur="1.2s" keySplines=".52,.6,.25,.99" type="scale" values="0;1"/><animate attributeName="opacity" begin="svgSpinnersPulseRingsMultiple0.begin+0.4s" calcMode="spline" dur="1.2s" keySplines=".52,.6,.25,.99" values="1;0"/></path></svg>
            </div>
            <p>{{ $t('Please wait for your whatsapp account to be connected!') }}</p>
        </div>
    </div>

    <button type="button" @click="launchWhatsAppSignup" class="bg-primary text-white p-2 rounded-lg text-sm mt-5 flex px-3 w-fit">
        {{ $t('Setup whatsapp') }}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><g fill="currentColor" fill-rule="evenodd" clip-rule="evenodd"><g opacity=".2"><path d="M12.206 5.848a1.5 1.5 0 0 1 2.113.192l3.333 4a1.5 1.5 0 1 1-2.304 1.92l-3.334-4a1.5 1.5 0 0 1 .192-2.112Z"/><path d="M12.206 16.152a1.5 1.5 0 0 1-.192-2.112l3.334-4a1.5 1.5 0 0 1 2.304 1.92l-3.333 4a1.5 1.5 0 0 1-2.113.192Z"/><path d="M16 11a1.5 1.5 0 0 1-1.5 1.5h-8a1.5 1.5 0 0 1 0-3h8A1.5 1.5 0 0 1 16 11Z"/></g><path d="M11.347 5.616a.5.5 0 0 1 .704.064l3.333 4a.5.5 0 0 1-.768.64l-3.333-4a.5.5 0 0 1 .064-.704Z"/><path d="M11.347 14.384a.5.5 0 0 1-.064-.704l3.333-4a.5.5 0 0 1 .768.64l-3.333 4a.5.5 0 0 1-.704.064Z"/><path d="M15.5 10a.5.5 0 0 1-.5.5H5a.5.5 0 0 1 0-1h20a.5.5 0 0 1 .5.5Z"/></g></svg>
    </button>
</template>
