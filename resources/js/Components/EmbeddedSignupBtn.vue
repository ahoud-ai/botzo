<script setup>
    import { ref, onUnmounted } from 'vue';
    import { router } from "@inertiajs/vue3";
    import { useI18n } from 'vue-i18n';
    import axios from 'axios';
    const { t } = useI18n();

    const props = defineProps(['appId', 'configId', 'graphAPIVersion'])

    const isSetupLoading = ref(false);
    const isMessageListenerAttached = ref(false);
    const ambiguousCandidates = ref([]);
    const isSelectingCandidate = ref(false);
    const embeddedSignupData = ref({
        waba_id: null,
        phone_number_id: null,
    });

    let popupWindow = null;
    let popupPollTimer = null;
    let settledByCode = false;

    const sessionInfoListener = (event) => {
        const allowedOrigins = [
            "https://www.facebook.com",
            "https://web.facebook.com",
            "https://business.facebook.com",
        ];
        if (!allowedOrigins.includes(event.origin)) {
            return;
        }

        try {
            const data = JSON.parse(event.data);
            if (data.type === 'WA_EMBEDDED_SIGNUP') {
                // if user finishes the Embedded Signup flow
                if (data.event === 'FINISH') {
                    const {phone_number_id, waba_id, code} = data.data;
                    embeddedSignupData.value.phone_number_id = phone_number_id ?? null;
                    embeddedSignupData.value.waba_id = waba_id ?? null;
                    if (code) {
                        settledByCode = true;
                        completeSignup(code);
                    }
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

    function stopWatchingPopup() {
        if (popupPollTimer) {
            window.clearInterval(popupPollTimer);
            popupPollTimer = null;
        }
    }

    function detachMessageListener() {
        if (isMessageListenerAttached.value) {
            window.removeEventListener("message", sessionInfoListener);
            isMessageListenerAttached.value = false;
        }
    }

    onUnmounted(() => {
        stopWatchingPopup();
        detachMessageListener();
    });

    function completeSignup(code) {
        isSetupLoading.value = true;

        router.post(`/whatsapp/exchange-code`, {
            token: code,
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
                detachMessageListener();
                isSetupLoading.value = false;
            },
        });
    }

    const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

    // Neither the WA_EMBEDDED_SIGNUP postMessage nor the redirect_uri code hand-off
    // actually fires when the popup is opened via window.open() to Meta's hosted
    // onboarding URL (confirmed by live testing — Meta's own success screen shows,
    // but our opener never hears about it). This asks our backend to check Meta
    // directly instead: any WhatsApp account shared with our Business Manager but
    // not yet linked to an organization is a candidate. Meta's sharing can lag a
    // couple seconds behind the popup closing, so this retries a few times before
    // giving up (status stays 'pending' — see reconcile()).
    async function reconcileAfterPopupClosed() {
        for (let attempt = 0; attempt < 4; attempt++) {
            if (attempt > 0) {
                await sleep(2500);
            }

            try {
                const { data } = await axios.post('/whatsapp/embedded-signup/reconcile');

                if (data?.status === 'connected') {
                    router.visit('/settings/whatsapp', { preserveState: false });
                    return;
                }

                if (data?.status === 'ambiguous') {
                    ambiguousCandidates.value = data.candidates ?? [];
                    return;
                }
                // status === 'pending' — nothing new found yet, retry.
            } catch {
                // Network/server hiccup — still worth retrying rather than giving up immediately.
            }
        }
    }

    async function selectCandidate(wabaId) {
        isSelectingCandidate.value = true;

        try {
            const { data } = await axios.post('/whatsapp/embedded-signup/select', { waba_id: wabaId });

            if (data?.status === 'connected') {
                router.visit('/settings/whatsapp', { preserveState: false });
                return;
            }

            alert(data?.message || t('Something went wrong. Refresh the page and try again'));
        } catch (error) {
            alert(error?.response?.data?.message || t('Something went wrong. Refresh the page and try again'));
        } finally {
            isSelectingCandidate.value = false;
            ambiguousCandidates.value = [];
        }
    }

    function launchWhatsAppSignup() {
        if (window.location.protocol !== 'https:') {
            alert(t('Embedded signup requires HTTPS. Please use a secure URL.'));
            return;
        }

        if (!props.appId || !props.configId) {
            alert(t('Embedded signup is not configured correctly. Contact the administrator.'));
            return;
        }

        settledByCode = false;
        // Clear any picker left over from a previous attempt — without this,
        // starting a fresh popup while an earlier ambiguous-candidates picker
        // was still showing left both on screen at once (confirmed live).
        ambiguousCandidates.value = [];
        window.addEventListener("message", sessionInfoListener);
        isMessageListenerAttached.value = true;

        // Conversion tracking code
        if (typeof fbq !== 'undefined') {
            fbq('trackCustom', 'WhatsAppOnboardingStart', {
                appId: props.appId,
                feature: 'whatsapp_embedded_signup'
            });
        }

        // Open Meta's own hosted Embedded Signup page directly in a popup instead
        // of calling window.FB.login(). Chrome/browser FedCM interception was
        // silently rewriting the FB.login() popup request into an unrelated
        // generic OAuth request (stripping config_id, forcing scope=openid),
        // which Meta then rejected as an unsupported permission — regardless of
        // what parameters this component passed. Meta's own hosted onboarding
        // link (confirmed working directly against this app/config) bypasses
        // that entirely, since it isn't mediated through FB.login()'s popup.
        const redirectUri = `${window.location.origin}/`;
        const params = new URLSearchParams({
            app_id: props.appId,
            config_id: props.configId,
            redirect_uri: redirectUri,
            extras: JSON.stringify({ sessionInfoVersion: '3', version: 'v4' }),
        });

        popupWindow = window.open(
            `https://business.facebook.com/messaging/whatsapp/onboard/?${params.toString()}`,
            'whatsapp-embedded-signup',
            'width=600,height=800'
        );

        if (!popupWindow) {
            alert(t('Please allow pop-ups for this site to continue.'));
            detachMessageListener();
            return;
        }

        // Meta redirects the popup back to our own redirect_uri with ?code=...
        // once the flow completes. Poll for that instead of relying on an
        // FB.login() callback, since we're not using FB.login() here.
        popupPollTimer = window.setInterval(() => {
            if (popupWindow.closed) {
                stopWatchingPopup();
                detachMessageListener();

                if (!settledByCode) {
                    isSetupLoading.value = true;
                    reconcileAfterPopupClosed().finally(() => {
                        isSetupLoading.value = false;
                    });
                }
                return;
            }

            let popupUrl = null;
            try {
                popupUrl = popupWindow.location.href;
            } catch {
                // Still on facebook.com/business.facebook.com (cross-origin) — not back yet.
                return;
            }

            if (popupUrl.indexOf(redirectUri) === 0) {
                stopWatchingPopup();

                const code = new URL(popupUrl).searchParams.get('code');
                popupWindow.close();

                if (code) {
                    settledByCode = true;
                    completeSignup(code);
                } else {
                    detachMessageListener();
                }
            }
        }, 500);
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

    <div v-if="ambiguousCandidates.length" class="fixed inset-0 ui-layer-modal bg-black bg-opacity-40 flex items-center justify-center">
        <div class="bg-white p-6 rounded-lg shadow-lg text-sm w-full max-w-sm">
            <p class="mb-4 font-medium">{{ $t('More than one WhatsApp account was found. Which one is yours?') }}</p>
            <div class="flex flex-col gap-2">
                <button
                    v-for="candidate in ambiguousCandidates"
                    :key="candidate.waba_id"
                    type="button"
                    :disabled="isSelectingCandidate"
                    @click="selectCandidate(candidate.waba_id)"
                    class="border rounded-lg p-2 text-start hover:bg-gray-50 disabled:opacity-50"
                >
                    <span class="block font-medium">{{ candidate.name }}</span>
                    <span v-if="candidate.phone" class="block text-xs text-gray-500" dir="ltr">{{ candidate.phone }}</span>
                </button>
            </div>
        </div>
    </div>

    <button type="button" @click="launchWhatsAppSignup" class="bg-primary text-white p-2 rounded-lg text-sm mt-5 flex px-3 w-fit">
        {{ $t('Setup whatsapp') }}
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><g fill="currentColor" fill-rule="evenodd" clip-rule="evenodd"><g opacity=".2"><path d="M12.206 5.848a1.5 1.5 0 0 1 2.113.192l3.333 4a1.5 1.5 0 1 1-2.304 1.92l-3.334-4a1.5 1.5 0 0 1 .192-2.112Z"/><path d="M12.206 16.152a1.5 1.5 0 0 1-.192-2.112l3.334-4a1.5 1.5 0 0 1 2.304 1.92l-3.333 4a1.5 1.5 0 0 1-2.113.192Z"/><path d="M16 11a1.5 1.5 0 0 1-1.5 1.5h-8a1.5 1.5 0 0 1 0-3h8A1.5 1.5 0 0 1 16 11Z"/></g><path d="M11.347 5.616a.5.5 0 0 1 .704.064l3.333 4a.5.5 0 0 1-.768.64l-3.333-4a.5.5 0 0 1 .064-.704Z"/><path d="M11.347 14.384a.5.5 0 0 1-.064-.704l3.333-4a.5.5 0 0 1 .768.64l-3.333 4a.5.5 0 0 1-.704.064Z"/><path d="M15.5 10a.5.5 0 0 1-.5.5H5a.5.5 0 0 1 0-1h20a.5.5 0 0 1 .5.5Z"/></g></svg>
    </button>
</template>
