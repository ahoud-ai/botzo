<template>
    <AppLayout>
        <UiPageHeader :title="$t('Flow builder')" :subtitle="$t('Build guided automation flows for conversations and follow-ups.')">
            <template #actions>
                <span class="fb-chip" :class="featureActive ? 'ui-chip-success' : 'ui-chip-neutral'">
                    {{ featureActive ? $t('Active') : $t('Inactive') }}
                </span>
            </template>
        </UiPageHeader>

        <form @submit.prevent="submitForm()" class="mt-6 space-y-6">
            <UiSectionCard :title="$t('Feature status')" :subtitle="$t('Use this switch to allow or block Flow Builder globally.')">
                <template #icon>
                    <FormToggleSwitch v-model="form.is_active"/>
                </template>
            </UiSectionCard>

            <UiSectionCard :title="$t('Plan controls')">
                <p class="fb-hint">
                    {{ $t('Plan-level limits remain inside subscription plan management so each customer tier can keep its own automation capacity.') }}
                </p>
                <Link href="/admin/plans" class="fb-btn fb-btn--ghost mt-4">
                    {{ $t('Open subscription plans') }}
                </Link>
            </UiSectionCard>

            <div class="flex justify-end">
                <button
                    type="submit"
                    class="fb-btn fb-btn--solid"
                    :disabled="form.processing"
                >
                    <svg v-if="form.processing" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                    <span v-else>{{ $t('Save') }}</span>
                </button>
            </div>
        </form>
    </AppLayout>
</template>

<script setup>
    import { computed } from 'vue';
    import { Link, useForm } from '@inertiajs/vue3';
    import AppLayout from '../Layout/App.vue';
    import FormToggleSwitch from '@/Components/FormToggleSwitch.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import UiSectionCard from '@/Components/UI/UiSectionCard.vue';

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

<style scoped>
.fb-chip {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.3rem 0.75rem;
    font-size: 0.78rem;
    font-weight: 700;
    white-space: nowrap;
}

.fb-hint {
    font-size: 0.86rem;
    line-height: 1.6;
    color: var(--ui-muted);
}

.fb-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.85rem;
    padding: 0.6rem 1.3rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: filter 160ms ease, background-color 160ms ease, border-color 160ms ease, opacity 160ms ease;
}

.fb-btn--ghost {
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    color: var(--ui-text);
}

.fb-btn--ghost:hover {
    background: var(--ui-surface-soft);
    border-color: var(--ui-border-strong);
}

.fb-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.fb-btn--solid:hover:not(:disabled) {
    filter: brightness(1.05);
}

.fb-btn--solid:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}
</style>
