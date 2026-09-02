<template>
    <AppLayout>
        <div class="mvr-detail ui-page ui-fade-up text-[var(--ui-text)]">
            <div class="mvr-detail__inner">
                <Link href="/admin/meta-verifications" class="mvr-back">
                    <span>→</span>
                    <span>{{ $t('Back to all requests') }}</span>
                </Link>

                <div class="mvr-grid">
                    <div class="mvr-main">
                        <UiFormSection :title="$t('Request details')">
                            <div class="mvr-field-grid">
                                <div><p class="mvr-field-label">{{ $t('Full name') }}</p><p class="mvr-field-value">{{ record.full_name }}</p></div>
                                <div><p class="mvr-field-label">{{ $t('Business name') }}</p><p class="mvr-field-value">{{ record.business_name }}</p></div>
                                <div><p class="mvr-field-label">{{ $t('Commercial register number') }}</p><p class="mvr-field-value">{{ record.commercial_register_number ?? '—' }}</p></div>
                                <div><p class="mvr-field-label">{{ $t('Phone') }}</p><p class="mvr-field-value">{{ record.phone }}</p></div>
                                <div><p class="mvr-field-label">{{ $t('Email') }}</p><p class="mvr-field-value">{{ record.email }}</p></div>
                                <div><p class="mvr-field-label">{{ $t('Date created') }}</p><p class="mvr-field-value">{{ record.created_at }}</p></div>
                            </div>
                            <div v-if="record.notes" class="mvr-notes">
                                <p class="mvr-field-label">{{ $t('Notes') }}</p>
                                <p class="mvr-notes-text">{{ record.notes }}</p>
                            </div>
                        </UiFormSection>

                        <UiFormSection :title="$t('Verification stages')">
                            <div class="mvr-stepper">
                                <template v-for="(stage, index) in stages" :key="stage.key">
                                    <div class="mvr-step">
                                        <div class="mvr-step-circle" :class="stage.stateClass">
                                            <component :is="stage.icon" width="16" height="16" />
                                            <span v-if="stage.isDone" class="mvr-step-badge">
                                                <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 13 4 4L19 7"/></svg>
                                            </span>
                                        </div>
                                        <span class="mvr-step-label" :class="{ 'mvr-step-label--on': stage.labelOn }">{{ $t(stage.labelKey) }}</span>
                                    </div>
                                    <div v-if="index < stages.length - 1" class="mvr-step-track">
                                        <div class="mvr-step-track-fill" :class="stage.trackClass"></div>
                                    </div>
                                </template>
                            </div>
                        </UiFormSection>
                    </div>

                    <div class="mvr-side">
                        <div v-if="record.status === 'rejected'" class="mvr-status-hero" style="--tone: var(--ui-danger)">
                            <span class="mvr-status-hero-icon"><XOctagon width="24" height="24" /></span>
                            <div>
                                <p class="mvr-status-hero-eyebrow">{{ $t('Status') }}</p>
                                <p class="mvr-status-hero-title">{{ $t('rejected') }}</p>
                            </div>
                        </div>
                        <div v-else class="mvr-status-hero" :style="{ '--tone': currentStageMeta.toneVar }">
                            <span class="mvr-status-hero-icon"><component :is="currentStageMeta.icon" width="24" height="24" /></span>
                            <div>
                                <p class="mvr-status-hero-eyebrow">{{ $t('Status') }}</p>
                                <p class="mvr-status-hero-title">{{ $t(record.status) }}</p>
                            </div>
                        </div>

                        <div v-if="record.status === 'rejected'" class="mvr-reject-callout">
                            <p class="mvr-reject-title">{{ $t('Verification rejected') }}</p>
                            <p class="mvr-reject-text">{{ record.rejection_reason }}</p>
                        </div>

                        <UiFormSection :title="$t('Organization')">
                            <p v-if="record.organization" class="mvr-field-value">
                                {{ record.organization.name }}
                                <Link :href="`/admin/organizations/${record.organization.id}`" class="mvr-org-link">({{ $t('View organization') }})</Link>
                            </p>
                            <p v-else class="mvr-field-value" style="color: var(--ui-muted); font-weight: 600;">{{ $t('Guest (not registered)') }}</p>
                        </UiFormSection>

                        <div v-if="!isTerminal" class="mvr-actions">
                            <button type="button" class="mvr-btn mvr-btn--primary" @click="advance">
                                {{ $t('Advance to: :stage', { stage: $t(nextStageLabelKey) }) }} ←
                            </button>
                            <button type="button" class="mvr-btn mvr-btn--danger" @click="showRejectModal = true">
                                {{ $t('Reject') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Modal :is-open="showRejectModal" label="Reject request" close-btn @close="showRejectModal = false">
            <UiFormSection :title="$t('Reject request')" :description="$t('Reason for rejection')">
                <textarea v-model="rejectReason" class="mvr-textarea" rows="4" :placeholder="$t('Reason for rejection')"></textarea>
                <div class="mvr-modal-actions">
                    <button type="button" class="mvr-btn mvr-btn--danger" :disabled="!rejectReason" @click="submitReject">{{ $t('Reject') }}</button>
                </div>
            </UiFormSection>
        </Modal>
    </AppLayout>
</template>

<script setup>
    import AppLayout from "../Layout/App.vue";
    import { Link, router } from "@inertiajs/vue3";
    import { computed, defineProps, ref } from "vue";
    import { useI18n } from "vue-i18n";
    import UiFormSection from "@/Components/UI/UiFormSection.vue";
    import Modal from "@/Components/Modal.vue";
    import { FileText, PenLine, Wallet, Search, Send, Eye, ShieldCheck, XOctagon } from "lucide-vue-next";

    const { t } = useI18n();

    const props = defineProps({
        title: { type: String },
        record: { type: Object, required: true },
    });

    const STAGE_META = [
        { key: 'requested', labelKey: 'Requested', icon: FileText, toneVar: 'var(--ui-muted)' },
        { key: 'awaiting_agreement', labelKey: 'Awaiting agreement', icon: PenLine, toneVar: 'var(--ui-accent)' },
        { key: 'awaiting_payment', labelKey: 'Awaiting payment', icon: Wallet, toneVar: 'var(--ui-warning)' },
        { key: 'under_review', labelKey: 'Under review', icon: Search, toneVar: 'var(--ui-primary)' },
        { key: 'submitted_to_meta', labelKey: 'Submitted to Meta', icon: Send, toneVar: 'var(--ui-secondary)' },
        { key: 'meta_reviewing', labelKey: 'Meta reviewing', icon: Eye, toneVar: 'color-mix(in srgb, var(--ui-primary) 50%, var(--ui-secondary) 50%)' },
        { key: 'approved', labelKey: 'Approved', icon: ShieldCheck, toneVar: 'var(--ui-success)' },
    ];

    const isTerminal = computed(() => props.record.status === 'approved' || props.record.status === 'rejected');

    const currentIndex = computed(() => {
        const index = STAGE_META.findIndex((stage) => stage.key === props.record.status);
        return index === -1 ? 0 : index;
    });

    const currentStageMeta = computed(() => STAGE_META[currentIndex.value] ?? STAGE_META[0]);

    const nextStageLabelKey = computed(() => {
        const next = STAGE_META[currentIndex.value + 1];
        return next ? next.labelKey : '';
    });

    const stages = computed(() => STAGE_META.map((stage, index) => {
        const isRejected = props.record.status === 'rejected';
        const isDone = isRejected ? index < currentIndex.value : index < currentIndex.value;
        const isActive = !isRejected && index === currentIndex.value;

        let stateClass = 'mvr-step-circle--upcoming';
        if (isDone) stateClass = 'mvr-step-circle--done';
        else if (isActive) stateClass = 'mvr-step-circle--active';

        return {
            ...stage,
            isDone,
            labelOn: isDone || isActive,
            stateClass,
            trackClass: isDone ? 'mvr-step-track-fill--full' : '',
        };
    }));

    const advance = () => {
        router.post(`/admin/meta-verifications/${props.record.id}/advance`);
    };

    const showRejectModal = ref(false);
    const rejectReason = ref('');

    const submitReject = () => {
        router.post(`/admin/meta-verifications/${props.record.id}/reject`, { reason: rejectReason.value }, {
            onSuccess: () => {
                showRejectModal.value = false;
                rejectReason.value = '';
            },
        });
    };
</script>

<style scoped>
.mvr-detail {
    min-height: 100%;
    padding: 1.5rem 2rem;
}

.mvr-detail__inner {
    width: 100%;
    max-width: 1680px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.mvr-back {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    width: fit-content;
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--ui-muted);
    transition: color 150ms ease, transform 150ms ease;
}

.mvr-back:hover {
    color: var(--ui-primary);
    transform: translateX(3px);
}

:global(.direction-ltr) .mvr-back:hover {
    transform: translateX(-3px);
}

.mvr-grid {
    display: grid;
    grid-template-columns: 1.65fr 1fr;
    gap: 1.4rem;
    align-items: start;
}

.mvr-main {
    display: flex;
    flex-direction: column;
    gap: 1.4rem;
}

.mvr-side {
    display: flex;
    flex-direction: column;
    gap: 1.1rem;
}

.mvr-field-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.25rem 1.75rem;
}

.mvr-field-label {
    font-size: 0.68rem;
    font-weight: 800;
    color: var(--ui-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin: 0;
}

.mvr-field-value {
    font-size: 0.92rem;
    font-weight: 700;
    margin: 0.3rem 0 0;
}

.mvr-notes {
    margin-top: 1.25rem;
    padding-top: 1.1rem;
    border-top: 1px solid var(--ui-border);
}

.mvr-notes-text {
    font-weight: 500;
    color: var(--ui-muted);
    margin-top: 0.4rem;
    line-height: 1.7;
}

.mvr-org-link {
    font-weight: 700;
    font-size: 0.78rem;
}

/* Stepper */
.mvr-stepper {
    display: flex;
    align-items: flex-start;
}

.mvr-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
}

.mvr-step-circle {
    position: relative;
    width: 44px;
    height: 44px;
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

.mvr-step-circle--done {
    border-color: color-mix(in srgb, var(--ui-success) 55%, transparent);
    color: var(--ui-success);
    background: color-mix(in srgb, var(--ui-success) 13%, var(--ui-surface));
}

.mvr-step-circle--active {
    border-color: var(--ui-secondary);
    background: linear-gradient(135deg, var(--ui-secondary), color-mix(in srgb, var(--ui-secondary) 72%, var(--ui-primary)));
    color: #fff;
    transform: scale(1.1);
    box-shadow: 0 0 0 7px color-mix(in srgb, var(--ui-secondary) 15%, transparent);
    animation: mvrPulse 2.4s ease-in-out infinite;
}

@keyframes mvrPulse {
    0%, 100% { box-shadow: 0 0 0 0 color-mix(in srgb, var(--ui-secondary) 38%, transparent); }
    50% { box-shadow: 0 0 0 9px color-mix(in srgb, var(--ui-secondary) 0%, transparent); }
}

@media (prefers-reduced-motion: reduce) {
    .mvr-step-circle--active { animation: none; }
}

.mvr-step-badge {
    position: absolute;
    inset-inline-end: -3px;
    bottom: -3px;
    width: 16px;
    height: 16px;
    border-radius: 999px;
    background: var(--ui-success);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid var(--ui-surface);
}

.mvr-step-label {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--ui-muted);
    text-align: center;
    max-width: 5.25rem;
    line-height: 1.35;
    transition: color 300ms ease, font-weight 300ms ease;
}

.mvr-step-label--on {
    color: var(--ui-text);
    font-weight: 800;
}

.mvr-step-track {
    flex: 1;
    height: 4px;
    background: var(--ui-border);
    margin-top: 21px;
    border-radius: 999px;
    overflow: hidden;
}

.mvr-step-track-fill {
    height: 100%;
    width: 0%;
    border-radius: 999px;
    background: var(--ui-success);
    transition: width 500ms ease;
}

.mvr-step-track-fill--full {
    width: 100%;
}

/* Status hero */
.mvr-status-hero {
    display: flex;
    align-items: center;
    gap: 0.9rem;
    padding: 1.1rem;
    border-radius: 1rem;
    background: linear-gradient(160deg, var(--ui-surface), color-mix(in srgb, var(--ui-surface) 90%, var(--tone) 10%));
    border: 1px solid color-mix(in srgb, var(--tone) 24%, var(--ui-border));
}

.mvr-status-hero-icon {
    width: 52px;
    height: 52px;
    border-radius: 15px;
    background: linear-gradient(135deg, var(--tone), color-mix(in srgb, var(--tone) 70%, var(--ui-primary)));
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 14px 26px -16px color-mix(in srgb, var(--tone) 70%, rgba(15, 23, 42, 0.6));
}

.mvr-status-hero-eyebrow {
    font-size: 0.68rem;
    font-weight: 800;
    color: color-mix(in srgb, var(--tone) 75%, var(--ui-text));
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin: 0;
}

.mvr-status-hero-title {
    font-size: 1.18rem;
    font-weight: 900;
    margin: 0.2rem 0 0;
    letter-spacing: -0.01em;
}

.mvr-reject-callout {
    padding: 1rem 1.1rem;
    border-radius: 1rem;
    background: color-mix(in srgb, var(--ui-danger) 7%, var(--ui-surface));
    border: 1px solid color-mix(in srgb, var(--ui-danger) 22%, var(--ui-border));
}

.mvr-reject-title {
    font-weight: 800;
    color: var(--ui-danger);
    margin: 0;
    font-size: 0.88rem;
}

.mvr-reject-text {
    font-size: 0.82rem;
    color: var(--ui-muted);
    margin: 0.35rem 0 0;
    line-height: 1.6;
}

/* Actions */
.mvr-actions {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.mvr-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    width: 100%;
    padding: 0.8rem;
    border-radius: 0.9rem;
    font-size: 0.84rem;
    font-weight: 800;
    border: 1px solid transparent;
    cursor: pointer;
    transition: transform 180ms ease, box-shadow 180ms ease, background 180ms ease, border-color 180ms ease;
}

.mvr-btn--primary {
    color: #fff;
    background: linear-gradient(135deg, var(--ui-secondary), color-mix(in srgb, var(--ui-secondary) 76%, var(--ui-primary)));
    box-shadow: 0 16px 28px -18px color-mix(in srgb, var(--ui-secondary) 65%, rgba(15, 23, 42, 0.55));
}

.mvr-btn--primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 20px 34px -18px color-mix(in srgb, var(--ui-secondary) 70%, rgba(15, 23, 42, 0.6));
}

.mvr-btn--danger {
    color: var(--ui-danger);
    border-color: color-mix(in srgb, var(--ui-danger) 30%, var(--ui-border));
    background: var(--ui-surface);
}

.mvr-btn--danger:hover {
    transform: translateY(-2px);
    background: color-mix(in srgb, var(--ui-danger) 6%, var(--ui-surface));
    border-color: color-mix(in srgb, var(--ui-danger) 46%, var(--ui-border));
}

.mvr-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

.mvr-textarea {
    width: 100%;
    border: 1px solid var(--ui-border);
    border-radius: 0.75rem;
    background: var(--ui-surface);
    color: var(--ui-text);
    padding: 0.7rem 0.85rem;
    font-size: 0.85rem;
    font-family: inherit;
    resize: vertical;
}

.mvr-modal-actions {
    margin-top: 0.9rem;
}

@media (max-width: 1024px) {
    .mvr-grid {
        grid-template-columns: 1fr;
    }
}
</style>
