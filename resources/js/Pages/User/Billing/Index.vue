<template>
    <div v-if="isPaymentLoading" class="fixed inset-0 ui-layer-modal bg-black bg-opacity-40 flex items-center justify-center">
        <div class="bg-white p-6 rounded-lg shadow-lg text-center text-sm">
            <div class="flex justify-center mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 24 24"><path fill="black" d="M12,1A11,11,0,1,0,23,12,11,11,0,0,0,12,1Zm0,20a9,9,0,1,1,9-9A9,9,0,0,1,12,21Z" transform="matrix(0 0 0 0 12 12)"><animateTransform id="svgSpinnersPulseRingsMultiple0" attributeName="transform" begin="0;svgSpinnersPulseRingsMultiple2.end" calcMode="spline" dur="1.2s" keySplines=".52,.6,.25,.99" type="translate" values="12 12;0 0"/><animateTransform additive="sum" attributeName="transform" begin="0;svgSpinnersPulseRingsMultiple2.end" calcMode="spline" dur="1.2s" keySplines=".52,.6,.25,.99" type="scale" values="0;1"/><animate attributeName="opacity" begin="0;svgSpinnersPulseRingsMultiple2.end" calcMode="spline" dur="1.2s" keySplines=".52,.6,.25,.99" values="1;0"/></path><path fill="black" d="M12,1A11,11,0,1,0,23,12,11,11,0,0,0,12,1Zm0,20a9,9,0,1,1,9-9A9,9,0,0,1,12,21Z" transform="matrix(0 0 0 0 12 12)"><animateTransform id="svgSpinnersPulseRingsMultiple1" attributeName="transform" begin="svgSpinnersPulseRingsMultiple0.begin+0.2s" calcMode="spline" dur="1.2s" keySplines=".52,.6,.25,.99" type="translate" values="12 12;0 0"/><animateTransform additive="sum" attributeName="transform" begin="svgSpinnersPulseRingsMultiple0.begin+0.2s" calcMode="spline" dur="1.2s" keySplines=".52,.6,.25,.99" type="scale" values="0;1"/><animate attributeName="opacity" begin="svgSpinnersPulseRingsMultiple0.begin+0.2s" calcMode="spline" dur="1.2s" keySplines=".52,.6,.25,.99" values="1;0"/></path><path fill="black" d="M12,1A11,11,0,1,0,23,12,11,11,0,0,0,12,1Zm0,20a9,9,0,1,1,9-9A9,9,0,0,1,12,21Z" transform="matrix(0 0 0 0 12 12)"><animateTransform id="svgSpinnersPulseRingsMultiple2" attributeName="transform" begin="svgSpinnersPulseRingsMultiple0.begin+0.4s" calcMode="spline" dur="1.2s" keySplines=".52,.6,.25,.99" type="translate" values="12 12;0 0"/><animateTransform additive="sum" attributeName="transform" begin="svgSpinnersPulseRingsMultiple0.begin+0.4s" calcMode="spline" dur="1.2s" keySplines=".52,.6,.25,.99" type="scale" values="0;1"/><animate attributeName="opacity" begin="svgSpinnersPulseRingsMultiple0.begin+0.4s" calcMode="spline" dur="1.2s" keySplines=".52,.6,.25,.99" values="1;0"/></path></svg>
            </div>
            <p>{{ $t('Please wait as the payment is being processed!') }}</p>
            <Link href="/billing" class="underline">{{ $t('Go to billing') }}</Link>
        </div>
    </div>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame text-[var(--ui-text)] min-h-full">
            <div class="flex justify-between mt-8 md:mt-0">
                <div>
                    <h2 class="text-xl mb-1">{{ $t('Billing and subscription') }}</h2>
                    <p class="mb-6 flex items-center text-sm leading-6 text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                        <span class="ms-1 mt-1">{{ $t('View and manage your billing history') }}</span>
                    </p>
                </div>
            </div>
            <div v-if="subscriptionManagedByParent" class="mb-4 rounded-[0.75rem] border border-sky-100 bg-sky-50 px-4 py-4 text-sm text-sky-900">
                <h3 class="font-medium">{{ $t('Inherited subscription') }}</h3>
                <p class="mt-1">
                    {{ $t('This branch uses the subscription managed by the parent organization.') }}
                    <span v-if="props.billingOwner?.name">
                        {{ $t('Billing owner') }}: {{ props.billingOwner.name }}.
                    </span>
                </p>
                <p class="mt-1 text-sky-800">{{ $t('Upgrade, payment, and coupon management are available from the parent organization only.') }}</p>
            </div>
            <div
                v-else-if="scheduledPlanChange"
                class="mb-4 rounded-[0.85rem] border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-950"
            >
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h3 class="font-medium">{{ $t('Scheduled plan change') }}</h3>
                        <p class="mt-1">{{ $t('Selected plan will start automatically on the next renewal date.') }}</p>
                        <p class="mt-1">
                            {{ scheduledPlanChange.plan_name }}
                            <span v-if="scheduledPlanChange.effective_at">
                                - {{ $t('will start on') }} {{ scheduledPlanChange.effective_at }}
                            </span>
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-md border border-amber-300 bg-white px-3 py-2 text-sm text-amber-900 transition hover:bg-amber-100"
                        @click="cancelScheduledChange"
                    >
                        {{ $t('Cancel scheduled change') }}
                    </button>
                </div>
            </div>
            <div
                v-if="!subscriptionIsActive"
                class="mb-4 flex flex-col gap-4 rounded-[1rem] border px-4 py-4 md:flex-row md:items-center md:justify-between"
                :class="subscriptionAlertClasses"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-full border"
                        :class="subscriptionAlertIconClasses"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 56 56"><path fill="currentColor" d="M9.965 50.207h36.07c3.985 0 6.469-2.86 6.469-6.469c0-1.078-.281-2.18-.867-3.187L33.567 9.074c-1.22-2.133-3.352-3.281-5.555-3.281c-2.18 0-4.336 1.148-5.579 3.281l-18.07 31.5a6.253 6.253 0 0 0-.867 3.164c0 3.61 2.508 6.469 6.469 6.469m.047-3.68c-1.641 0-2.72-1.336-2.72-2.789c0-.422.071-.914.306-1.406l18.046-31.477c.516-.89 1.454-1.312 2.368-1.312c.914 0 1.804.398 2.32 1.312l18.047 31.5c.234.47.351.961.351 1.383c0 1.453-1.125 2.79-2.742 2.79Zm18-12.117c1.125 0 1.78-.656 1.804-1.875l.328-12.351c.024-1.196-.914-2.086-2.156-2.086c-1.265 0-2.156.867-2.133 2.062l.305 12.375c.023 1.196.68 1.875 1.852 1.875m0 7.617c1.359 0 2.53-1.078 2.53-2.437c0-1.383-1.148-2.438-2.53-2.438c-1.383 0-2.532 1.078-2.532 2.438c0 1.336 1.172 2.437 2.532 2.437"/></svg>
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold md:text-base">{{ subscriptionHeroTitle }}</h2>
                        <p class="mt-1 text-sm leading-6">{{ subscriptionHeroBody }}</p>
                    </div>
                </div>
                <div v-if="!subscriptionManagedByParent" class="flex flex-wrap gap-2">
                    <Link
                        href="/subscription"
                        v-if="showSubscribeButton"
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                    >
                        <span>{{ $t('Subscribe to a plan') }}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="m13.172 12l-4.95-4.95l1.414-1.414L16 12l-6.364 6.364l-1.414-1.414z"/></svg>
                    </Link>
                    <Link
                        href="/subscription"
                        v-if="showRenewPlanButton"
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                    >
                        <span>{{ $t('Renew plan') }}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="m13.172 12l-4.95-4.95l1.414-1.414L16 12l-6.364 6.364l-1.414-1.414z"/></svg>
                    </Link>
                    <button
                        @click="openModal()"
                        v-if="showAddPaymentButton"
                        type="button"
                        class="rounded-md border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                    >
                        {{ $t('Add account credit') }}
                    </button>
                </div>
            </div>
            <div class="grid gap-5 rounded-[1.5rem] border border-white/70 bg-white/80 p-5 shadow-[0_24px_80px_-40px_rgba(15,23,42,0.45)] backdrop-blur-xl md:grid-cols-[minmax(0,1.3fr)_minmax(0,0.9fr)] md:p-6">
                <div class="rounded-[1.25rem] border border-slate-200/70 bg-gradient-to-br from-white via-slate-50 to-slate-100/90 p-5 shadow-sm">
                    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-medium tracking-[0px] text-slate-500">{{ $t('Plan details') }}</h2>
                            <h2 v-if="trialState" class="mt-2 text-[1.65rem] font-semibold leading-tight text-slate-950">{{ $t('Trial period') }}</h2>
                            <h2 v-else class="mt-2 text-[1.65rem] font-semibold leading-tight text-slate-950">{{ activePlanName }}</h2>
                            <p v-if="['billing_pending', 'inactive'].includes(subscriptionStateVariant) && !subscriptionManagedByParent" class="mt-3 max-w-xl text-sm leading-6 text-slate-600">{{ subscriptionStateVariant === 'billing_pending' ? $t('No plan is linked yet. Choose one when you are ready to start billing.') : $t('Select a plan to activate billing and start using paid features.') }}</p>
                            <p v-if="subscriptionManagedByParent && props.billingOwner?.name" class="mt-3 text-sm leading-6 text-slate-600">{{ $t('Billing owner') }}: {{ props.billingOwner.name }}</p>
                        </div>
                        <span class="inline-flex rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-600 shadow-sm">
                            {{ planDetailsEyebrow }}
                        </span>
                    </div>
                    <div v-if="!trialState" class="grid gap-4 rounded-[1rem] bg-white/80 p-4 shadow-inner shadow-slate-200/60 md:grid-cols-3">
                        <div>
                            <h2 class="text-xs font-medium uppercase tracking-[0.12em] text-slate-400">{{ $t('Start date') }}</h2>
                            <span class="mt-2 block text-sm font-medium text-slate-800">{{ props.subscription?.start_date || '--' }}</span>
                        </div>
                        <div>
                            <h2 class="text-xs font-medium uppercase tracking-[0.12em] text-slate-400">{{ $t('Next billing date') }}</h2>
                            <span class="mt-2 block text-sm font-medium text-slate-800">{{ props.subscription?.valid_until || '--' }}</span>
                        </div>
                        <div>
                            <h2 class="text-xs font-medium uppercase tracking-[0.12em] text-slate-400">{{ $t('Account balance') }}</h2>
                            <span class="mt-2 block text-sm font-medium text-slate-800">{{ props.subscriptionDetails?.accountBalance ?? '0.00' }}</span>
                        </div>
                    </div>
                    <div v-if="trialState" class="grid gap-4 rounded-[1rem] bg-white/80 p-4 shadow-inner shadow-slate-200/60 md:grid-cols-2">
                        <div>
                            <h2 class="text-xs font-medium uppercase tracking-[0.12em] text-slate-400">{{ $t('Trial expires on') }}</h2>
                            <span class="mt-2 block text-sm font-medium text-slate-800">{{ props.subscription?.valid_until || '--' }}</span>
                        </div>
                    </div>
                </div>
                <div
                    v-if="!subscriptionManagedByParent"
                    class="rounded-[1.25rem] border border-slate-200 bg-slate-50/80 p-5 shadow-sm"
                >
                    <div class="flex h-full flex-col justify-between">
                        <div>
                            <span class="inline-flex rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-600 shadow-sm">
                                {{ $t('Billing and subscription') }}
                            </span>
                            <h3 class="mt-4 text-lg font-semibold text-slate-900">
                                {{ subscriptionStateVariant === 'payment_required' ? $t('Complete your billing') : subscriptionStateVariant === 'billing_pending' ? $t('Finish billing setup') : $t('Choose your next step') }}
                            </h3>
                            <p class="mt-3 text-sm leading-6 text-slate-600">
                                {{ subscriptionStateVariant === 'payment_required'
                                    ? renewalPaymentMessage
                                    : subscriptionStateVariant === 'billing_pending'
                                        ? $t('This workspace is ready, but billing has not started yet.')
                                        : $t('Pick the plan that fits this workspace and unlock the paid features when you are ready.') }}
                            </p>
                        </div>
                        <div class="mt-6 flex flex-wrap gap-3">
                            <Link
                                href="/subscription"
                                v-if="showRenewPlanButton"
                                class="inline-flex items-center justify-center gap-2 rounded-md bg-primary px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                            >
                                {{ $t('Renew plan') }}
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="m13.172 12l-4.95-4.95l1.414-1.414L16 12l-6.364 6.364l-1.414-1.414z"/></svg>
                            </Link>
                            <button
                                v-if="showAddPaymentButton"
                                @click="openModal()"
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                            >
                                {{ $t('Add account credit') }}
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M12 3a1 1 0 0 1 1 1v7h7a1 1 0 1 1 0 2h-7v7a1 1 0 1 1-2 0v-7H4a1 1 0 1 1 0-2h7V4a1 1 0 0 1 1-1"/></svg>
                            </button>
                            <Link
                                href="/subscription"
                                v-if="showSubscribeButton"
                                class="inline-flex items-center justify-center gap-2 rounded-md bg-primary px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                            >
                                {{ $t('Subscribe to plan') }}
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="m13.172 12l-4.95-4.95l1.414-1.414L16 12l-6.364 6.364l-1.414-1.414z"/></svg>
                            </Link>
                            <Link
                                href="/subscription"
                                v-if="showManagePlansButton"
                                class="inline-flex items-center justify-center gap-2 rounded-md bg-primary px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                            >
                                {{ $t('Manage plans') }}
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="m13.172 12l-4.95-4.95l1.414-1.414L16 12l-6.364 6.364l-1.414-1.414z"/></svg>
                            </Link>
                            <Link href="/billing/usage" class="inline-flex items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
                                {{ $t('Usage and limits') }}
                            </Link>
                        </div>
                    </div>
                </div>
                <div v-else class="rounded-[1.25rem] border border-dashed border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-600 md:ms-auto md:max-w-sm">
                    {{ subscriptionManagedByParent ? $t('Upgrade, payment, and coupon management are available from the parent organization only.') : $t('No payment methods are available right now. Please contact support.') }}
                </div>
            </div>
            <div class=" bg-slate-100 md:bg-gray-50 p-5 md:px-0 rounded-[0.5rem] mt-10 w-full">
                <div class="w-full">
                    <div class="mb-5">
                        <h2 class="text-xl tracking-[0px]">{{ $t('Invoices') }}</h2>
                        <p class="mb-6 flex items-center text-sm leading-6 text-gray-600">
                            <span class="ms-1 mt-1">{{ $t('You can view your invoices and past billing details here') }}</span>
                        </p>
                    </div>
                    <div class="w-100">
                        <BillingInvoiceTable
                            :rows="props.invoiceRows"
                            :view-base-path="'/billing/invoices'"
                            :print-base-path="'/billing/invoices'"
                            :download-base-path="'/billing/invoices'"
                        />
                    </div>
                </div>
            </div>
            <div class=" bg-slate-100 md:bg-gray-50 p-5 md:px-0 rounded-[0.5rem] mt-6 w-full">
                <div class="w-full">
                    <div class="mb-5">
                        <h2 class="text-xl tracking-[0px]">{{ $t('Billing activity') }}</h2>
                        <p class="mb-6 flex items-center text-sm leading-6 text-gray-600">
                            <span class="ms-1 mt-1">{{ $t('Manual credits, debits, and payment activity linked to the billing owner.') }}</span>
                        </p>
                    </div>
                    <div class="w-100">
                        <BillingTable :rows="props.billingActivity" :filters="props.filters"/>
                    </div>
                </div>
            </div>
        </div>
        <Modal :label="label" :isOpen="isOpenModal" @close="isOpenModal = false">
            <form @submit.prevent="submitForm()" class="gap-y-4">
                <h2 class="text-2xl mb-4">{{ $t('Add account credit') }}</h2>
                <p class="text-sm">{{ $t('Add your own custom amount') }}</p>
                <FormInput v-model="form.amount" :error="form.errors.amount" :name="''" :type="'number'" :class="'w-100'"/>
                <h2 class="text-sm mt-4 mb-2">{{ $t('Pay via') }}</h2>
                <div v-if="!hasPaymentMethods" class="mb-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    {{ $t('No payment methods are available right now. Please contact support.') }}
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div v-for="(item, index) in props.methods" :key="index" class="">
                        <div class="flex items-center">
                            <label @click="selectPayment(item.name)" for="myCheckbox" class="cursor-pointer">
                                <div class="w-5 h-5 border border-gray-400 rounded-md flex items-center justify-center" :class="form.method === item.name ? 'bg-[color:var(--ui-text)]' : ''">
                                    <svg v-if="form.method === item.name" class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </div>
                            </label>
                            <span @click="selectPayment(item.name)" class="ms-2 text-sm cursor-pointer">{{ item.name }}</span>
                        </div>
                    </div>
                </div>
                <div class="form-error ui-danger-text text-xs mt-2">{{ form.errors.method }}</div>
                <div class="mt-6 flex">
                    <button type="button" @click="onClose" class="inline-flex justify-center rounded-md border border-transparent bg-slate-50 px-4 py-2 text-sm text-slate-500 hover:bg-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 me-4">{{ $t('Cancel') }}</button>
                    <button type="submit" 
                        :class="['inline-flex justify-center rounded-md border border-transparent bg-primary px-4 py-2 text-sm text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2', { 'opacity-50': isLoading }]"
                        :disabled="isLoading || !hasPaymentMethods"
                    >
                        <span v-if="isLoading" class="flex gap-x-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                            <span>{{ $t('Processing') }}</span>
                        </span>
                        <span v-else class="flex gap-x-1">
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
    const subscriptionAlertClasses = computed(() => {
        if (subscriptionStateVariant.value === 'payment_required') {
            return 'border-rose-200 bg-rose-50 text-rose-900';
        }

        if (subscriptionStateVariant.value === 'trial_expired') {
            return 'border-amber-200 bg-amber-50 text-amber-900';
        }

        if (subscriptionStateVariant.value === 'billing_pending') {
            return 'border-sky-200 bg-sky-50 text-sky-900';
        }

        return 'border-sky-200 bg-sky-50 text-sky-900';
    });
    const subscriptionAlertIconClasses = computed(() => {
        if (subscriptionStateVariant.value === 'payment_required') {
            return 'border-rose-200 bg-white text-rose-500';
        }

        if (subscriptionStateVariant.value === 'trial_expired') {
            return 'border-amber-200 bg-white text-amber-500';
        }

        if (subscriptionStateVariant.value === 'billing_pending') {
            return 'border-sky-200 bg-white text-sky-500';
        }

        return 'border-sky-200 bg-white text-sky-500';
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




