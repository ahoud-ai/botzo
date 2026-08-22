<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full">
            <UiPageHeader :title="$t('Ticket ref') + ': ' + props.ticket.reference">
                <template #actions>
                    <Link href="/support" class="ticket-view-btn ticket-view-btn--ghost">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16"><path fill="currentColor" fill-rule="evenodd" d="M5.841 5.28a.75.75 0 0 0-1.06-1.06L1.53 7.47L1 8l.53.53l3.25 3.25a.75.75 0 0 0 1.061-1.06l-1.97-1.97H24.25a.75.75 0 0 0 0-1.5H3.871l1.97-1.97Z" clip-rule="evenodd"/></svg>
                        {{ $t('Back') }}
                    </Link>
                </template>
            </UiPageHeader>

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2 space-y-4">
                    <UiSectionCard :title="props.ticket.subject">
                        <div class="ticket-message-box">{{ props.ticket.message }}</div>
                    </UiSectionCard>

                    <UiSectionCard :title="$t('Conversation')">
                        <div v-if="orderedComments.length" class="space-y-4">
                            <div v-for="(item, index) in orderedComments" :key="index" class="ticket-comment">
                                <div class="ticket-comment-avatar">
                                    {{ getInitials(item.user?.first_name, item.user?.last_name) }}
                                </div>
                                <div class="ticket-comment-body">
                                    <div class="ticket-comment-meta">
                                        <span class="ticket-comment-author">{{ item.user?.first_name }} {{ item.user?.last_name }}</span>
                                        <span class="ticket-comment-date">{{ formatDateTime(item.created_at) }}</span>
                                    </div>
                                    <p class="ticket-comment-text">{{ item.message }}</p>
                                </div>
                            </div>
                        </div>
                        <UiEmptyState v-else :title="$t('No comments yet')">
                            <template #icon>
                                <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 11.5a8.38 8.38 0 0 1-.9 3.8a8.5 8.5 0 0 1-7.6 4.7a8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8a8.5 8.5 0 0 1 4.7-7.6a8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5Z"/></svg>
                            </template>
                        </UiEmptyState>

                        <form v-if="isTicketOpenForReplies" @submit.prevent="submitForm()" class="ticket-comment-form">
                            <FormTextArea v-model="form.message" :name="$t('Comment')" :type="'text'" :showLabel="true" :error="form.errors.message" :textAreaRows="3" :class="'w-full'"/>
                            <div class="flex justify-end mt-3">
                                <button type="submit" class="ticket-view-btn ticket-view-btn--solid" :disabled="form.processing">{{ $t('Add comment') }}</button>
                            </div>
                        </form>
                    </UiSectionCard>
                </div>

                <div class="space-y-4">
                    <UiSectionCard :title="$t('Ticket details')">
                        <dl class="ticket-meta-list">
                            <div class="ticket-meta-row">
                                <dt>{{ $t('Category') }}</dt>
                                <dd>{{ localizeCategory(props.ticket.category?.name) }}</dd>
                            </div>
                            <div class="ticket-meta-row">
                                <dt>{{ $t('Status') }}</dt>
                                <dd>
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[12px] font-bold capitalize" :class="statusChipClass(props.ticket.status)">
                                        <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                                        {{ localizeStatus(props.ticket.status) }}
                                    </span>
                                </dd>
                            </div>
                            <div class="ticket-meta-row">
                                <dt>{{ $t('Priority') }}</dt>
                                <dd>
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[12px] font-bold capitalize" :class="priorityChipClass(props.ticket.priority)">
                                        {{ localizePriority(props.ticket.priority) }}
                                    </span>
                                </dd>
                            </div>
                            <div class="ticket-meta-row">
                                <dt>{{ $t('Date created') }}</dt>
                                <dd>{{ formatDateTime(props.ticket.created_at) }}</dd>
                            </div>
                        </dl>

                        <div v-if="isTicketOpenForReplies" class="ticket-status-actions">
                            <button type="button" @click="changeTicketStatus('resolved')" class="ticket-view-btn ticket-view-btn--success w-full">{{ $t('Mark as resolved') }}</button>
                            <button type="button" @click="changeTicketStatus('closed')" class="ticket-view-btn ticket-view-btn--ghost w-full">{{ $t('Close ticket') }}</button>
                        </div>
                    </UiSectionCard>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
<script setup>
    import AppLayout from './../Layout/App.vue';
    import { Link, router, useForm, usePage } from "@inertiajs/vue3";
    import { computed } from 'vue';
    import { useI18n } from 'vue-i18n';
    import FormTextArea from '@/Components/FormTextArea.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import UiSectionCard from '@/Components/UI/UiSectionCard.vue';
    import UiEmptyState from '@/Components/UI/UiEmptyState.vue';

    const { t, te } = useI18n();
    const page = usePage();
    const props = defineProps(['ticket']);
    const form = useForm({
        'message' : null,
    });

    const isTicketOpenForReplies = computed(() => props.ticket.status === 'open' || props.ticket.status === 'pending');

    const orderedComments = computed(() => {
        return [...(props.ticket.comments_with_user ?? [])].reverse();
    });

    const formatDateTime = (value) => {
        if (!value) {
            return '-';
        }

        const options = { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' };
        const locale = page.props?.currentLanguage === 'ar' ? 'ar-EG' : 'en-US';
        return new Intl.DateTimeFormat(locale, options).format(new Date(value));
    };

    const localizeStatus = (value) => {
        if (!value) {
            return '-';
        }

        return t(String(value).toLowerCase());
    };

    const localizePriority = (value) => {
        if (!value) {
            return t('Not set');
        }

        return t(String(value).toLowerCase());
    };

    const localizeCategory = (value) => {
        const rawValue = String(value ?? '').trim();

        if (rawValue === '') {
            return '-';
        }

        return te(rawValue) ? t(rawValue) : rawValue;
    };

    const statusChipClass = (status) => {
        switch (String(status ?? '').toLowerCase()) {
            case 'open':
                return 'ui-chip-info';
            case 'pending':
                return 'ui-chip-warning';
            case 'resolved':
                return 'ui-chip-success';
            case 'closed':
                return 'ui-chip-neutral';
            default:
                return 'ui-chip-neutral';
        }
    };

    const priorityChipClass = (priority) => {
        switch (String(priority ?? '').toLowerCase()) {
            case 'critical':
            case 'high':
                return 'ui-chip-danger';
            case 'medium':
                return 'ui-chip-warning';
            case 'low':
                return 'ui-chip-neutral';
            default:
                return 'ui-chip-neutral';
        }
    };

    const getInitials = (firstName, lastName) => {
      const firstInitial = firstName ? firstName.charAt(0).toUpperCase() : '';
      const lastInitial = lastName ? lastName.charAt(0).toUpperCase() : '';

      return `${firstInitial}${lastInitial}` || '?';
    }

    const submitForm = () => {
        form.post('/support/' + props.ticket.uuid + '/comment', {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    const changeTicketStatus = (status) => {
        router.post('/support/' + props.ticket.uuid + '/status', { status: status });
    }
</script>

<style scoped>
.ticket-message-box {
    padding: 0.85rem 1rem;
    border-radius: 0.7rem;
    background: var(--ui-surface-soft);
    border: 1px dashed var(--ui-border);
    font-size: 0.9rem;
    line-height: 1.6;
    color: var(--ui-text);
    white-space: pre-wrap;
}

.ticket-comment {
    display: flex;
    gap: 0.85rem;
}

.ticket-comment-avatar {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 999px;
    background: var(--ui-surface-soft);
    border: 1px solid var(--ui-border);
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--ui-secondary);
}

.ticket-comment-body {
    flex: 1;
    min-width: 0;
    padding: 0.75rem 1rem;
    border-radius: 0.7rem;
    background: var(--ui-surface-soft);
    border: 1px solid var(--ui-border);
}

.ticket-comment-meta {
    display: flex;
    align-items: baseline;
    gap: 0.6rem;
    margin-bottom: 0.35rem;
}

.ticket-comment-author {
    font-weight: 700;
    font-size: 0.85rem;
    color: var(--ui-text);
}

.ticket-comment-date {
    font-size: 0.75rem;
    color: var(--ui-muted);
}

.ticket-comment-text {
    font-size: 0.875rem;
    line-height: 1.6;
    color: var(--ui-text);
    white-space: pre-wrap;
}

.ticket-comment-form {
    margin-top: 1.25rem;
    padding-top: 1.25rem;
    border-top: 1px solid var(--ui-border);
}

.ticket-meta-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.ticket-meta-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--ui-border);
}

.ticket-meta-row:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.ticket-meta-row dt {
    font-size: 0.8rem;
    color: var(--ui-muted);
    font-weight: 600;
}

.ticket-meta-row dd {
    font-size: 0.85rem;
    color: var(--ui-text);
    font-weight: 600;
}

.ticket-status-actions {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    margin-top: 1.25rem;
    padding-top: 1.25rem;
    border-top: 1px solid var(--ui-border);
}

.ticket-view-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    font-weight: 600;
    border-radius: 0.7rem;
    padding: 0.6rem 1.1rem;
}

.ticket-view-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
    transition: filter 160ms ease;
}

.ticket-view-btn--solid:hover:not(:disabled) {
    filter: brightness(1.05);
}

.ticket-view-btn--solid:disabled {
    opacity: 0.75;
    cursor: not-allowed;
}

.ticket-view-btn--success {
    background: color-mix(in srgb, var(--ui-success) 16%, transparent);
    color: var(--ui-success);
    border: 1px solid color-mix(in srgb, var(--ui-success) 30%, transparent);
}

.ticket-view-btn--success:hover {
    background: color-mix(in srgb, var(--ui-success) 24%, transparent);
}

.ticket-view-btn--ghost {
    background: var(--ui-surface-soft);
    color: var(--ui-text);
    border: 1px solid var(--ui-border);
}

.ticket-view-btn--ghost:hover {
    background: var(--ui-border);
}
</style>
