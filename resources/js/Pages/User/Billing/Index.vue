<template>
    <div v-if="isPaymentLoading" class="billing-loading-overlay">
        <div class="billing-loading-card">
            <div class="flex justify-center mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" width="90" height="90" viewBox="0 0 24 24"><path fill="currentColor" d="M12,1A11,11,0,1,0,23,12,11,11,0,0,0,12,1Zm0,20a9,9,0,1,1,9-9A9,9,0,0,1,12,21Z" transform="matrix(0 0 0 0 12 12)"><animateTransform id="svgSpinnersPulseRingsMultiple0" attributeName="transform" begin="0;svgSpinnersPulseRingsMultiple2.end" calcMode="spline" dur="1.2s" keySplines=".52,.6,.25,.99" type="translate" values="12 12;0 0"/><animateTransform additive="sum" attributeName="transform" begin="0;svgSpinnersPulseRingsMultiple2.end" calcMode="spline" dur="1.2s" keySplines=".52,.6,.25,.99" type="scale" values="0;1"/><animate attributeName="opacity" begin="0;svgSpinnersPulseRingsMultiple2.end" calcMode="spline" dur="1.2s" keySplines=".52,.6,.25,.99" values="1;0"/></path><path fill="currentColor" d="M12,1A11,11,0,1,0,23,12,11,11,0,0,0,12,1Zm0,20a9,9,0,1,1,9-9A9,9,0,0,1,12,21Z" transform="matrix(0 0 0 0 12 12)"><animateTransform id="svgSpinnersPulseRingsMultiple1" attributeName="transform" begin="svgSpinnersPulseRingsMultiple0.begin+0.2s" calcMode="spline" dur="1.2s" keySplines=".52,.6,.25,.99" type="translate" values="12 12;0 0"/><animateTransform additive="sum" attributeName="transform" begin="svgSpinnersPulseRingsMultiple0.begin+0.2s" calcMode="spline" dur="1.2s" keySplines=".52,.6,.25,.99" type="scale" values="0;1"/><animate attributeName="opacity" begin="svgSpinnersPulseRingsMultiple0.begin+0.2s" calcMode="spline" dur="1.2s" keySplines=".52,.6,.25,.99" values="1;0"/></path><path fill="currentColor" d="M12,1A11,11,0,1,0,23,12,11,11,0,0,0,12,1Zm0,20a9,9,0,1,1,9-9A9,9,0,0,1,12,21Z" transform="matrix(0 0 0 0 12 12)"><animateTransform id="svgSpinnersPulseRingsMultiple2" attributeName="transform" begin="svgSpinnersPulseRingsMultiple0.begin+0.4s" calcMode="spline" dur="1.2s" keySplines=".52,.6,.25,.99" type="translate" values="12 12;0 0"/><animateTransform additive="sum" attributeName="transform" begin="svgSpinnersPulseRingsMultiple0.begin+0.4s" calcMode="spline" dur="1.2s" keySplines=".52,.6,.25,.99" type="scale" values="0;1"/><animate attributeName="opacity" begin="svgSpinnersPulseRingsMultiple0.begin+0.4s" calcMode="spline" dur="1.2s" keySplines=".52,.6,.25,.99" values="1;0"/></path></svg>
            </div>
            <p class="text-[var(--ui-text)]">{{ $t('Please wait as the payment is being processed!') }}</p>
            <Link href="/billing" class="billing-loading-link">{{ $t('Go to billing') }}</Link>
        </div>
    </div>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame text-[var(--ui-text)] min-h-full">
            <UiPageHeader :title="$t('Billing and subscription')" :subtitle="$t('View and manage your billing history')" />

            <div v-if="subscriptionManagedByParent" class="billing-banner billing-banner--info">
                <h3 class="font-semibold">{{ $t('Inherited subscription') }}</h3>
                <p class="mt-1">
                    {{ $t('This branch uses the subscription managed by the parent organization.') }}
                    <span v-if="props.billingOwner?.name">
                        {{ $t('Billing owner') }}: {{ props.billingOwner.name }}.
                    </span>
                </p>
                <p class="mt-1">{{ $t('Upgrade, payment, and coupon management are available from the parent organization only.') }}</p>
            </div>

            <div v-else-if="scheduledPlanChange" class="billing-banner billing-banner--warning">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h3 class="font-semibold">{{ $t('Scheduled plan change') }}</h3>
                        <p class="mt-1">{{ $t('Selected plan will start automatically on the next renewal date.') }}</p>
                        <p class="mt-1">
                            {{ scheduledPlanChange.plan_name }}
                            <span v-if="scheduledPlanChange.effective_at">
                                - {{ $t('will start on') }} {{ scheduledPlanChange.effective_at }}
                            </span>
                        </p>
                    </div>
                    <button type="button" class="billing-btn billing-btn--ghost" @click="cancelScheduledChange">
                        {{ $t('Cancel scheduled change') }}
                    </button>
                </div>
            </div>

            <div v-if="!subscriptionIsActive" class="billing-banner" :class="`billing-banner--${subscriptionAlertVariant}`">
                <div class="flex items-start gap-3">
                    <div class="billing-alert-icon" :class="`billing-alert-icon--${subscriptionAlertVariant}`">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 56 56"><path fill="currentColor" d="M9.965 50.207h36.07c3.985 0 6.469-2.86 6.469-6.469c0-1.078-.281-2.18-.867-3.187L33.567 9.074c-1.22-2.133-3.352-3.281-5.555-3.281c-2.18 0-4.336 1.148-5.579 3.281l-18.07 31.5a6.253 6.253 0 0 0-.867 3.164c0 3.61 2.508 6.469 6.469 6.469m.047-3.68c-1.641 0-2.72-1.336-2.72-2.789c0-.422.071-.914.306-1.406l18.046-31.477c.516-.89 1.454-1.312 2.368-1.312c.914 0 1.804.398 2.32 1.312l18.047 31.5c.234.47.351.961.351 1.383c0 1.453-1.125 2.79-2.742 2.79Zm18-12.117c1.125 0 1.78-.656 1.804-1.875l.328-12.351c.024-1.196-.914-2.086-2.156-2.086c-1.265 0-2.156.867-2.133 2.062l.305 12.375c.023 1.196.68 1.875 1.852 1.875m0 7.617c1.359 0 2.53-1.078 2.53-2.437c0-1.383-1.148-2.438-2.53-2.438c-1.383 0-2.532 1.078-2.532 2.438c0 1.336 1.172 2.437 2.532 2.437"/></svg>
                    </div>
                    <div>
                        <h2 class="font-semibold text-[var(--ui-text)]">{{ subscriptionHeroTitle }}</h2>
                        <p class="mt-1 text-sm leading-6">{{ subscriptionHeroBody }}</p>
                    </div>
                </div>
            </div>

            <div class="billing-hero-grid">
                <div class="billing-plan-card">
                    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-medium text-[var(--ui-muted)]">{{ $t('Plan details') }}</h2>
                            <h2 v-if="trialState" class="mt-2 text-[1.65rem] font-bold leading-tight text-[var(--ui-text)]">{{ $t('Trial period') }}</h2>
                            <h2 v-else class="mt-2 text-[1.65rem] font-bold leading-tight text-[var(--ui-text)]">{{ activePlanName }}</h2>
                            <p v-if="['billing_pending', 'inactive'].includes(subscriptionStateVariant) && !subscriptionManagedByParent" class="mt-3 max-w-xl text-sm leading-6 text-[var(--ui-muted)]">{{ subscriptionStateVariant === 'billing_pending' ? $t('No plan is linked yet. Choose one when you are ready to start billing.') : $t('Select a plan to activate billing and start using paid features.') }}</p>
                            <p v-if="subscriptionManagedByParent && props.billingOwner?.name" class="mt-3 text-sm leading-6 text-[var(--ui-muted)]">{{ $t('Billing owner') }}: {{ props.billingOwner.name }}</p>
                        </div>
                        <span class="billing-eyebrow">{{ planDetailsEyebrow }}</span>
                    </div>
                    <div v-if="!trialState" class="billing-detail-grid md:grid-cols-3">
                        <div>
                            <h2 class="billing-detail-label">{{ $t('Start date') }}</h2>
                            <span class="billing-detail-value">{{ props.subscription?.start_date || '--' }}</span>
                        </div>
                        <div>
                            <h2 class="billing-detail-label">{{ $t('Next billing date') }}</h2>
                            <span class="billing-detail-value">{{ props.subscription?.valid_until || '--' }}</span>
                        </div>
                        <div>
                            <h2 class="billing-detail-label">{{ $t('Account balance') }}</h2>
                            <span class="billing-detail-value">{{ props.subscriptionDetails?.accountBalance ?? '0.00' }}</span>
                        </div>
                    </div>
                    <div v-if="trialState" class="billing-detail-grid md:grid-cols-2">
                        <div>
                            <h2 class="billing-detail-label">{{ $t('Trial expires on') }}</h2>
                            <span class="billing-detail-value">{{ props.subscription?.valid_until || '--' }}</span>
                        </div>
                    </div>
                </div>

                <div v-if="!subscriptionManagedByParent" class="billing-action-card">
                    <div>
                        <span class="billing-eyebrow">{{ $t('Billing and subscription') }}</span>
                        <h3 class="mt-4 text-lg font-bold text-[var(--ui-text)]">
                            {{ subscriptionStateVariant === 'payment_required' ? $t('Complete your billing') : subscriptionStateVariant === 'billing_pending' ? $t('Finish billing setup') : $t('Choose your next step') }}
                        </h3>
                        <p class="mt-3 text-sm leading-6 text-[var(--ui-muted)]">
                            {{ subscriptionStateVariant === 'payment_required'
                                ? renewalPaymentMessage
                                : subscriptionStateVariant === 'billing_pending'
                                    ? $t('This workspace is ready, but billing has not started yet.')
                                    : $t('Pick the plan that fits this workspace and unlock the paid features when you are ready.') }}
                        </p>
                    </div>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <Link href="/subscription" v-if="showRenewPlanButton" class="billing-btn billing-btn--solid">
                            {{ $t('Renew plan') }}
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="m13.172 12l-4.95-4.95l1.414-1.414L16 12l-6.364 6.364l-1.414-1.414z"/></svg>
                        </Link>
                        <button v-if="showAddPaymentButton" @click="openModal()" type="button" class="billing-btn billing-btn--ghost">
                            {{ $t('Add account credit') }}
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M12 3a1 1 0 0 1 1 1v7h7a1 1 0 1 1 0 2h-7v7a1 1 0 1 1-2 0v-7H4a1 1 0 1 1 0-2h7V4a1 1 0 0 1 1-1"/></svg>
                        </button>
                        <Link href="/subscription" v-if="showSubscribeButton" class="billing-btn billing-btn--solid">
                            {{ $t('Subscribe to plan') }}
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="m13.172 12l-4.95-4.95l1.414-1.414L16 12l-6.364 6.364l-1.414-1.414z"/></svg>
                        </Link>
                        <Link href="/subscription" v-if="showManagePlansButton" class="billing-btn billing-btn--solid">
                            {{ $t('Manage plans') }}
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="m13.172 12l-4.95-4.95l1.414-1.414L16 12l-6.364 6.364l-1.414-1.414z"/></svg>
                        </Link>
                        <Link href="/billing/usage" class="billing-btn billing-btn--ghost">
                            {{ $t('Usage and limits') }}
                        </Link>
                    </div>
                </div>
                <div v-else class="billing-action-card billing-action-card--dashed">
                    {{ subscriptionManagedByParent ? $t('Upgrade, payment, and coupon management are available from the parent organization only.') : $t('No payment methods are available right now. Please contact support.') }}
                </div>
            </div>

            <UiSectionCard :title="$t('Invoices')" :subtitle="$t('You can view your invoices and past billing details here')" class="mt-8">
                <BillingInvoiceTable
                    :rows="props.invoiceRows"
                    :view-base-path="'/billing/invoices'"
                    :print-base-path="'/billing/invoices'"
                    :download-base-path="'/billing/invoices'"
                />
            </UiSectionCard>

            <UiSectionCard :title="$t('Billing activity')" :subtitle="$t('Manual credits, debits, and payment activity linked to the billing owner.')" class="mt-6">
                <BillingTable :rows="props.billingActivity" :filters="props.filters"/>
            </UiSectionCard>
        </div>

        <Modal :label="label" :isOpen="isOpenModal" @close="isOpenModal = false">
            <form @submit.prevent="submitForm()">
                <h2 class="text-xl font-bold mb-2 text-[var(--ui-text)]">{{ $t('Add account credit') }}</h2>
                <p class="text-sm text-[var(--ui-muted)]">{{ $t('Add your own custom amount') }}</p>
                <div class="mt-4">
                    <FormInput v-model="form.amount" :error="form.errors.amount" :name="''" :type="'number'" :className="'w-full'"/>
                </div>
                <h2 class="text-sm font-semibold mt-4 mb-2 text-[var(--ui-text)]">{{ $t('Pay via') }}</h2>
                <div v-if="!hasPaymentMethods" class="billing-banner billing-banner--warning">
                    {{ $t('No payment methods are available right now. Please contact support.') }}
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div v-for="(item, index) in props.methods" :key="index" class="billing-method-option" @click="selectPayment(item.name)">
                        <span class="billing-method-radio" :class="{ 'billing-method-radio--checked': form.method === item.name }">
                            <svg v-if="form.method === item.name" xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 20 20" fill="none"><path d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" fill="currentColor"/></svg>
                        </span>
                        <span class="text-sm text-[var(--ui-text)]">{{ item.name }}</span>
                    </div>
                </div>
                <div class="ui-form-error mt-2">{{ form.errors.method }}</div>
                <div class="mt-6 flex gap-3">
                    <button type="button" @click="onClose" class="billing-btn billing-btn--ghost">{{ $t('Cancel') }}</button>
                    <button type="submit" class="billing-btn billing-btn--solid" :disabled="isLoading || !hasPaymentMethods">
                        <span v-if="isLoading" class="flex items-center gap-1">
                            <svg class="animate-spin" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"/></svg>
                            <span>{{ $t('Processing') }}</span>
                        </span>
                        <span v-else class="flex items-center gap-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M12 13a1.49 1.49 0 0 0-1 2.61V17a1 1 0 0 0 2 0v-1.39A1.49 1.49 0 0 0 12 13m5-4V7A5 5 0 0 0 7 7v2a3 3 0 0 0-3 3v7a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-7a3 3 0 0 0-3-3M9 7a3 3 0 0 1 6 0v2H9Zm9 12a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1Z"/></svg>
                            <span>{{ $t('Continue') }}</span>
                        </span>
                    </button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
<script setup>
    import AppLayout from "./../Layout/App.vue";
    import BillingTable from '@/Components/Tables/BillingTable.vue';
    import BillingInvoiceTable from '@/Components/Tables/BillingInvoiceTable.vue';
    import { Link, useForm, router } from "@inertiajs/vue3";
    import Modal from '@/Components/Modal.vue';
    import FormInput from '@/Components/FormInput.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import UiSectionCard from '@/Components/UI/UiSectionCard.vue';
    import { ref, onMounted, computed } from 'vue';
    import Echo from 'laravel-echo';
    import Pusher from 'pusher-js';
    import { useI18n } from 'vue-i18n';

    const props = defineProps([
        'subscription',
        'invoiceRows',
        'billingActivity',
        'filters',
        'subscriptionIsActive',
        'subscriptionDetails',
        'methods',
        'isPaymentLoading',
        'pusherSettings',
        'organizationId',
        'setting',
        'billingOwner',
        'subscriptionManagedByParent',
        'scheduledPlanChange',
        'subscriptionDisplayState',
    ]);

    const label = ref(null);
    const isOpenModal = ref(false);
    const isLoading = ref(false);
    const subscriptionManagedByParent = ref(Boolean(props.subscriptionManagedByParent));
    const scheduledPlanChange = computed(() => props.scheduledPlanChange ?? null);
    const hasPaymentMethods = computed(() => Array.isArray(props.methods) && props.methods.length > 0);
    const subscriptionDisplayState = computed(() => props.subscriptionDisplayState ?? {});
    const subscriptionStateVariant = computed(() => subscriptionDisplayState.value.variant ?? 'billing_pending');
    const trialState = computed(() => ['trial_active', 'trial_expired'].includes(subscriptionStateVariant.value));
    const showSubscribeButton = computed(() => !subscriptionManagedByParent.value && ['billing_pending', 'inactive', 'trial_active', 'trial_expired'].includes(subscriptionStateVariant.value));
    const showRenewPlanButton = computed(() => !subscriptionManagedByParent.value && subscriptionStateVariant.value === 'payment_required');
    const showAddPaymentButton = computed(() => !subscriptionManagedByParent.value && subscriptionStateVariant.value === 'payment_required' && props.setting['enable_custom_payment'] == 1 && hasPaymentMethods.value);
    const showManagePlansButton = computed(() => !subscriptionManagedByParent.value && subscriptionStateVariant.value === 'active');
    const { locale, t } = useI18n();
    const parseMoney = (value) => {
        const normalized = String(value ?? '0').replace(/,/g, '');
        const parsed = parseFloat(normalized);

        return Number.isNaN(parsed) ? 0 : parsed;
    };
    const renewalAmountDue = computed(() => Math.max(0, parseMoney(props.subscriptionDetails?.amountDue)).toFixed(2));
    const renewalRequiresPayment = computed(() => parseMoney(props.subscriptionDetails?.amountDue) > 0);
    const renewalPaymentMessage = computed(() => {
        if (renewalRequiresPayment.value) {
            return `${t('Renew your current plan to continue using the app. Amount due')} ${renewalAmountDue.value}.`;
        }

        return t('Your renewal is covered by available credit. Renew now to reactivate the subscription.');
    });
    const activePlanName = computed(() => {
        const plan = props.subscription?.plan;
        if (!plan) {
            return t('Not selected yet');
        }

        if (plan.display_name) {
            return plan.display_name;
        }

        const isArabic = String(locale.value ?? '').startsWith('ar');

        if (isArabic) {
            return plan.name_ar || plan.name || plan.name_en || '';
        }

        return plan.name_en || plan.name || plan.name_ar || '';
    });
    const planDetailsEyebrow = computed(() => {
        if (subscriptionStateVariant.value === 'payment_required') {
            return t('Renew plan');
        }

        if (subscriptionStateVariant.value === 'billing_pending') {
            return t('Billing setup required');
        }

        if (trialState.value) {
            return t('Trial period');
        }

        return t('Billing and subscription');
    });
    const subscriptionHeroTitle = computed(() => {
        if (subscriptionStateVariant.value === 'billing_pending') {
            return t('Billing has not started yet');
        }

        if (subscriptionStateVariant.value === 'inactive') {
            return t('This workspace does not have an active subscription yet');
        }

        if (subscriptionStateVariant.value === 'trial_expired') {
            return t('Your trial has ended');
        }

        if (subscriptionStateVariant.value === 'payment_required') {
            return t('Subscription renewal required');
        }

        return t('Billing and subscription');
    });
    const subscriptionHeroBody = computed(() => {
        if (subscriptionStateVariant.value === 'billing_pending') {
            return t('Choose the plan that matches this workspace and start billing when you are ready.');
        }

        if (subscriptionStateVariant.value === 'inactive') {
            return t('Select the appropriate plan to activate billing and unlock paid features for this workspace.');
        }

        if (subscriptionStateVariant.value === 'trial_expired') {
            return t('Choose a paid plan to keep this workspace active without interruption.');
        }

        if (subscriptionStateVariant.value === 'payment_required') {
            return renewalPaymentMessage.value;
        }

        return t('Select a plan to activate billing and start using paid features.');
    });
    const subscriptionAlertVariant = computed(() => {
        if (subscriptionStateVariant.value === 'payment_required') {
            return 'danger';
        }

        if (subscriptionStateVariant.value === 'trial_expired') {
            return 'warning';
        }

        return 'info';
    });

    const form = useForm({
        'amount' : null,
        'method' : null
    });

    const resetPaymentForm = () => {
        form.reset();
        form.clearErrors();
        isLoading.value = false;
    };

    const selectPayment = (method) => {
        form.method = method;
    };

    function openModal(){
        if (subscriptionManagedByParent.value) {
            return;
        }

        resetPaymentForm();
        isOpenModal.value = true;
    }

    function onClose() {
        resetPaymentForm();
        isOpenModal.value = false
    }

    const cancelScheduledChange = () => {
        router.post('/subscription/scheduled-change/cancel', {}, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const submitForm = async () => {
        if (subscriptionManagedByParent.value) {
            return;
        }

        isLoading.value = true;
        form.post('/pay', {
            preserveScroll: true,
            onFinish: () => {
                isLoading.value = false
            },
        });
    };

    onMounted(() => {
        //Pusher.logToConsole = true;
        const pusherKey = (props.pusherSettings['pusher_app_key'] || '').toString().trim();
        const pusherCluster = (props.pusherSettings['pusher_app_cluster'] || '').toString().trim();

        if(pusherKey && pusherCluster){
            window.Pusher = Pusher;

            window.Echo = new Echo({
                broadcaster: 'pusher',
                key: pusherKey,
                cluster: pusherCluster,
                encrypted: true,
            });

            window.Echo.channel('payments.ch' + props.organizationId).listen('NewPaymentEvent', (event) => {
                router.visit('/billing', {});
            });
        }
    });
</script>

<style scoped>
.billing-loading-overlay {
    position: fixed;
    inset: 0;
    z-index: var(--ui-layer-modal, 60);
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.5);
}

.billing-loading-card {
    background: var(--ui-surface);
    border: 1px solid var(--ui-border);
    border-radius: var(--ui-radius-lg);
    padding: 1.75rem;
    text-align: center;
    font-size: 0.9rem;
}

.billing-loading-link {
    color: var(--ui-secondary);
    text-decoration: underline;
}

.billing-banner {
    margin-bottom: 1rem;
    padding: 1rem 1.15rem;
    border-radius: var(--ui-radius-md);
    font-size: 0.9rem;
    line-height: 1.5;
}

.billing-banner--info {
    background: color-mix(in srgb, var(--ui-secondary) 8%, var(--ui-surface));
    border: 1px solid color-mix(in srgb, var(--ui-secondary) 20%, var(--ui-border));
    color: var(--ui-text);
}

.billing-banner--warning {
    background: color-mix(in srgb, var(--ui-warning) 12%, var(--ui-surface));
    border: 1px solid color-mix(in srgb, var(--ui-warning) 30%, var(--ui-border));
    color: color-mix(in srgb, var(--ui-warning) 75%, var(--ui-text));
}

.billing-banner--danger {
    background: color-mix(in srgb, var(--ui-danger) 10%, var(--ui-surface));
    border: 1px solid color-mix(in srgb, var(--ui-danger) 28%, var(--ui-border));
    color: color-mix(in srgb, var(--ui-danger) 75%, var(--ui-text));
}

.billing-alert-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 999px;
    background: var(--ui-surface);
    border: 1px solid var(--ui-border);
}

.billing-alert-icon--danger { color: var(--ui-danger); }
.billing-alert-icon--warning { color: var(--ui-warning); }
.billing-alert-icon--info { color: var(--ui-secondary); }

.billing-hero-grid {
    display: grid;
    gap: 1.25rem;
    margin-top: 0.5rem;
}

@media (min-width: 768px) {
    .billing-hero-grid {
        grid-template-columns: minmax(0, 1.3fr) minmax(0, 0.9fr);
    }
}

.billing-plan-card,
.billing-action-card {
    border-radius: var(--ui-radius-lg);
    background: var(--ui-surface);
    border: 1px solid var(--ui-border);
    padding: 1.5rem;
}

.billing-action-card {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.billing-action-card--dashed {
    display: flex;
    align-items: center;
    border-style: dashed;
    color: var(--ui-muted);
    font-size: 0.9rem;
}

.billing-eyebrow {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.3rem 0.8rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--ui-muted);
    background: var(--ui-surface-soft);
    border: 1px solid var(--ui-border);
}

.billing-detail-grid {
    display: grid;
    gap: 1.25rem;
    margin-top: 1.25rem;
    padding: 1.1rem;
    border-radius: var(--ui-radius-md);
    background: var(--ui-surface-soft);
}

.billing-detail-label {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--ui-muted);
}

.billing-detail-value {
    display: block;
    margin-top: 0.4rem;
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--ui-text);
}

.billing-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    border-radius: 0.7rem;
    padding: 0.65rem 1.2rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: background-color 160ms ease, filter 160ms ease;
}

.billing-btn--solid {
    color: #fff;
    background: var(--ui-secondary);
}

.billing-btn--solid:hover:not(:disabled) {
    filter: brightness(1.05);
}

.billing-btn--solid:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.billing-btn--ghost {
    color: var(--ui-text);
    background: var(--ui-surface-soft);
    border: 1px solid var(--ui-border);
}

.billing-btn--ghost:hover {
    background: var(--ui-border);
}

.billing-method-option {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.65rem 0.85rem;
    border-radius: 0.65rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    cursor: pointer;
    transition: border-color 160ms ease;
}

.billing-method-option:hover {
    border-color: var(--ui-secondary);
}

.billing-method-radio {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 1.2rem;
    height: 1.2rem;
    border-radius: 0.35rem;
    border: 1.5px solid var(--ui-border-strong);
    color: #fff;
}

.billing-method-radio--checked {
    background: var(--ui-secondary);
    border-color: var(--ui-secondary);
}
</style>
