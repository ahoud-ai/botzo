<template>
    <AppLayout>
        <div class="max-w-5xl">
            <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold text-primary">{{ $t('Features') }}</p>
                    <h2 class="mt-1 text-2xl font-semibold text-slate-950">{{ $t('Flow builder') }}</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ $t('Build guided automation flows for conversations and follow-ups.') }}</p>
                </div>
                <span :class="featureActive ? 'bg-green-50 text-green-700' : 'bg-slate-100 text-slate-600'" class="rounded-md px-3 py-2 text-sm font-medium">
                    {{ featureActive ? $t('Active') : $t('Inactive') }}
                </span>
            </div>

            <form @submit.prevent="submitForm()" class="space-y-8">
                <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h3 class="text-base font-semibold text-slate-950">{{ $t('Feature status') }}</h3>
                            <p class="mt-1 text-sm text-slate-500">{{ $t('Use this switch to allow or block Flow Builder globally.') }}</p>
                        </div>
                        <FormToggleSwitch v-model="form.is_active"/>
                    </div>
                </section>

                <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-base font-semibold text-slate-950">{{ $t('Plan controls') }}</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        {{ $t('Plan-level limits remain inside subscription plan management so each customer tier can keep its own automation capacity.') }}
                    </p>
                    <Link href="/admin/plans" class="mt-4 inline-flex rounded-md border border-primary px-3 py-2 text-sm font-medium text-primary hover:bg-primary hover:text-white">
                        {{ $t('Open subscription plans') }}
                    </Link>
                </section>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        :class="['inline-flex justify-center rounded-md border border-transparent bg-primary px-4 py-2 text-sm text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2', { 'opacity-50': form.processing }]"
                        :disabled="form.processing"
                    >
                        <svg v-if="form.processing" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                        <span v-else>{{ $t('Save') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup>
    import { computed } from 'vue';
    import { Link, useForm } from '@inertiajs/vue3';
    import AppLayout from '../Layout/App.vue';
    import FormToggleSwitch from '@/Components/FormToggleSwitch.vue';

    const props = defineProps({
        config: {
            type: Array,
            required: false,
            default: () => [],
        },
        addon: {
            type: Object,
            required: true,
        },
    });

    const form = useForm({
        uuid: props.addon.uuid,
        settings: {},
        is_active: Number(props.addon.is_active) === 1,
    });

    const featureActive = computed(() => form.is_active === true || form.is_active === 1);

    const submitForm = () => {
        form.post('/admin/settings/features/flow-builder', {
            preserveScroll: true,
        });
    };
</script>
