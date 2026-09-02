<script setup>
    import { computed, defineProps } from "vue";
    import UiSectionCard from "@/Components/UI/UiSectionCard.vue";
    import { FileText, PenLine, Wallet, Search, Send, Eye, ShieldCheck } from "lucide-vue-next";

    const props = defineProps({
        metaVerificationRequest: {
            type: Object,
            default: null,
        },
    });

    const VERIFICATION_STAGE_META = [
        { key: 'requested', labelKey: 'Requested', icon: FileText },
        { key: 'awaiting_agreement', labelKey: 'Awaiting agreement', icon: PenLine },
        { key: 'awaiting_payment', labelKey: 'Awaiting payment', icon: Wallet },
        { key: 'under_review', labelKey: 'Under review', icon: Search },
        { key: 'submitted_to_meta', labelKey: 'Submitted to Meta', icon: Send },
        { key: 'meta_reviewing', labelKey: 'Meta reviewing', icon: Eye },
        { key: 'approved', labelKey: 'Approved', icon: ShieldCheck },
    ];

    const verificationStages = computed(() => {
        const status = props.metaVerificationRequest?.status;
        const currentIndex = Math.max(0, VERIFICATION_STAGE_META.findIndex((stage) => stage.key === status));

        return VERIFICATION_STAGE_META.map((stage, index) => {
            const isDone = index < currentIndex;
            const isActive = index === currentIndex;

            let stateClass = 'mvw-step-circle--upcoming';
            if (isDone) stateClass = 'mvw-step-circle--done';
            else if (isActive) stateClass = 'mvw-step-circle--active';

            return {
                ...stage,
                isDone,
                labelOn: isDone || isActive,
                stateClass,
                trackClass: isDone ? 'mvw-step-track-fill--full' : '',
            };
        });
    });
</script>

<template>
    <div v-if="metaVerificationRequest" class="dashboard-panel dashboard-panel--verification">
        <UiSectionCard :title="$t('Meta verification status')" :subtitle="$t('Track your WhatsApp verification progress.')">
            <template #icon>
                <span class="mvw-head-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 4 6v6c0 5 3.4 8.7 8 10 4.6-1.3 8-5 8-10V6z"/><path d="m9 12 2 2 4-4"/></svg>
                </span>
            </template>

            <div v-if="metaVerificationRequest.status === 'approved'" class="mvw-callout mvw-callout--success">
                <span class="mvw-callout-icon">
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 4 6v6c0 5 3.4 8.7 8 10 4.6-1.3 8-5 8-10V6z"/><path d="m9 12 2 2 4-4"/></svg>
                </span>
                <div>
                    <p class="mvw-callout-title">{{ $t('Verification approved') }}</p>
                    <p class="mvw-callout-text">{{ $t('Your WhatsApp Business account is officially verified by Meta.') }}</p>
                </div>
            </div>

            <div v-else-if="metaVerificationRequest.status === 'rejected'" class="mvw-callout mvw-callout--danger">
                <span class="mvw-callout-icon">
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/><path d="M12 9v4M12 17h.01"/></svg>
                </span>
                <div>
                    <p class="mvw-callout-title">{{ $t('Verification rejected') }}</p>
                    <p class="mvw-callout-text">{{ metaVerificationRequest.rejection_reason }}</p>
                </div>
            </div>

            <div v-else class="mvw-stepper">
                <template v-for="(stage, index) in verificationStages" :key="stage.key">
                    <div class="mvw-step">
                        <div class="mvw-step-circle" :class="stage.stateClass">
                            <component :is="stage.icon" width="16" height="16" />
                            <span v-if="stage.isDone" class="mvw-step-badge">
                                <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 13 4 4L19 7"/></svg>
                            </span>
                        </div>
                        <span class="mvw-step-label" :class="{ 'mvw-step-label--on': stage.labelOn }">{{ $t(stage.labelKey) }}</span>
                    </div>
                    <div v-if="index < verificationStages.length - 1" class="mvw-step-track">
                        <div class="mvw-step-track-fill" :class="stage.trackClass"></div>
                    </div>
                </template>
            </div>
        </UiSectionCard>
    </div>
</template>

<style scoped>
.dashboard-panel--verification :deep(.ui-card) {
    height: auto;
}

.mvw-head-icon {
    width: 2.2rem;
    height: 2.2rem;
    border-radius: 0.7rem;
    background: color-mix(in srgb, var(--ui-secondary) 16%, transparent);
    color: var(--ui-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.mvw-stepper {
    display: flex;
    align-items: flex-start;
    overflow-x: auto;
    padding: 0.25rem 0.1rem 0.1rem;
}

.mvw-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.45rem;
}

.mvw-step-circle {
    position: relative;
    width: 38px;
    height: 38px;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border: 2px solid var(--ui-border-strong);
    color: var(--ui-muted);
    background: var(--ui-surface-soft);
    transition: background 350ms ease, border-color 350ms ease, box-shadow 350ms ease, color 350ms ease, transform 350ms ease;
}

.mvw-step-circle--done {
    border-color: color-mix(in srgb, var(--ui-success) 55%, transparent);
    color: var(--ui-success);
    background: color-mix(in srgb, var(--ui-success) 13%, var(--ui-surface));
}

.mvw-step-circle--active {
    border-color: var(--ui-secondary);
    background: linear-gradient(135deg, var(--ui-secondary), color-mix(in srgb, var(--ui-secondary) 72%, var(--ui-primary)));
    color: #fff;
    transform: scale(1.1);
    box-shadow: 0 0 0 6px color-mix(in srgb, var(--ui-secondary) 15%, transparent);
    animation: mvwPulse 2.4s ease-in-out infinite;
}

@keyframes mvwPulse {
    0%, 100% { box-shadow: 0 0 0 0 color-mix(in srgb, var(--ui-secondary) 38%, transparent); }
    50% { box-shadow: 0 0 0 8px color-mix(in srgb, var(--ui-secondary) 0%, transparent); }
}

@media (prefers-reduced-motion: reduce) {
    .mvw-step-circle--active { animation: none; }
}

.mvw-step-badge {
    position: absolute;
    inset-inline-end: -2px;
    bottom: -2px;
    width: 14px;
    height: 14px;
    border-radius: 999px;
    background: var(--ui-success);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid var(--ui-surface);
}

.mvw-step-label {
    font-size: 0.66rem;
    font-weight: 600;
    color: var(--ui-muted);
    text-align: center;
    max-width: 4.6rem;
    line-height: 1.3;
    transition: color 300ms ease, font-weight 300ms ease;
}

.mvw-step-label--on {
    color: var(--ui-text);
    font-weight: 800;
}

.mvw-step-track {
    flex: 1;
    min-width: 0.75rem;
    height: 3px;
    background: var(--ui-border);
    margin-top: 18px;
    border-radius: 999px;
    overflow: hidden;
}

.mvw-step-track-fill {
    height: 100%;
    width: 0%;
    border-radius: 999px;
    background: var(--ui-success);
    transition: width 500ms ease;
}

.mvw-step-track-fill--full {
    width: 100%;
}

.mvw-callout {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.9rem 1rem;
    border-radius: 0.9rem;
}

.mvw-callout--success {
    background: color-mix(in srgb, var(--ui-success) 7%, var(--ui-surface));
    border: 1px solid color-mix(in srgb, var(--ui-success) 22%, var(--ui-border));
}

.mvw-callout--danger {
    background: color-mix(in srgb, var(--ui-danger) 7%, var(--ui-surface));
    border: 1px solid color-mix(in srgb, var(--ui-danger) 22%, var(--ui-border));
}

.mvw-callout-icon {
    width: 2.2rem;
    height: 2.2rem;
    border-radius: 0.7rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.mvw-callout--success .mvw-callout-icon {
    background: color-mix(in srgb, var(--ui-success) 16%, transparent);
    color: var(--ui-success);
}

.mvw-callout--danger .mvw-callout-icon {
    background: color-mix(in srgb, var(--ui-danger) 16%, transparent);
    color: var(--ui-danger);
}

.mvw-callout-title {
    font-weight: 800;
    font-size: 0.88rem;
    margin: 0;
}

.mvw-callout--success .mvw-callout-title {
    color: var(--ui-success);
}

.mvw-callout--danger .mvw-callout-title {
    color: var(--ui-danger);
}

.mvw-callout-text {
    font-size: 0.8rem;
    color: var(--ui-muted);
    margin: 0.2rem 0 0;
    line-height: 1.6;
}
</style>
