<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full">
            <UiPageHeader :title="`${$t('Ticket ref')}: ${props.ticket.reference}`" :subtitle="props.ticket.subject">
                <template #actions>
                    <Link href="/admin/support" class="tkt-btn tkt-btn--ghost">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16"><path fill="currentColor" fill-rule="evenodd" d="M5.841 5.28a.75.75 0 0 0-1.06-1.06L1.53 7.47L1 8l.53.53l3.25 3.25a.75.75 0 0 0 1.061-1.06l-1.97-1.97H14.25a.75.75 0 0 0 0-1.5H3.871l1.97-1.97Z" clip-rule="evenodd"/></svg>
                        {{ $t('Back') }}
                    </Link>
                </template>
            </UiPageHeader>

            <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
                <div class="space-y-4">
                    <UiSectionCard :title="$t('Subject') + ': ' + props.ticket.subject">
                        <div class="tkt-message-box">{{ props.ticket.message }}</div>
                    </UiSectionCard>

                    <UiSectionCard v-if="props.ticket.status === 'open' || props.ticket.status === 'pending'" :title="$t('Add comment')">
                        <form @submit.prevent="submitForm()">
                            <FormTextArea v-model="form.message" :name="$t('Comment')" :type="'text'" :showLabel="true" :error="form.errors.message" :textAreaRows="3" :class="'mb-4'"/>
                            <button type="submit" class="tkt-btn tkt-btn--solid">{{ $t('Add comment') }}</button>
                        </form>
                    </UiSectionCard>

                    <article v-for="(item, index) in props.ticket.comments_with_user" :key="index" class="tkt-comment-card">
                        <div class="flex gap-x-3.5 text-sm">
                            <span class="tkt-avatar">
                                {{ getInitials(item.user.first_name, item.user.last_name) }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-baseline gap-x-3">
                                    <span class="tkt-comment-name">{{ item.user.first_name + ' ' + item.user.last_name }}</span>
                                    <span class="tkt-comment-date">{{ formatDateTime(item.created_at) }}</span>
                                </div>
                                <div class="tkt-comment-message mt-1">{{ item.message }}</div>
                            </div>
                        </div>
                    </article>
                </div>

                <div>
                    <UiSectionCard :title="$t('Ticket details')">
                        <div class="space-y-3">
                            <div class="tkt-tile">
                                <span class="tkt-tile-label">{{ $t('Category') }}</span>
                                <span class="tkt-chip ui-chip-neutral capitalize">{{ localizeCategory(props.ticket.category.name) }}</span>
                            </div>
                            <div class="tkt-tile">
                                <span class="tkt-tile-label">{{ $t('Status') }}</span>
                                <span class="tkt-chip capitalize" :class="statusChipClass(props.ticket.status)">{{ localizeStatus(props.ticket.status) }}</span>
                            </div>
                            <div class="tkt-tile">
                                <span class="tkt-tile-label">{{ $t('Priority') }}</span>
                                <FormSelect v-if="props.ticket.status === 'open' || props.ticket.status === 'pending'" v-model="priority" @update:modelValue="updatePriority" :options="priorityOptions" :class="'w-full mt-2'" :placeholder="$t('Not Set')"/>
                                <span v-else class="tkt-chip capitalize" :class="priorityChipClass(props.ticket.priority)">{{ localizePriority(props.ticket.priority) }}</span>
                            </div>
                            <div class="tkt-tile">
                                <span class="tkt-tile-label">{{ $t('Assigned to') }}</span>
                                <FormSelect v-if="props.ticket.status === 'open' || props.ticket.status === 'pending'" v-model="user" @update:modelValue="updateUser" :options="userOptions" :class="'w-full mt-2'" :placeholder="$t('Select User')"/>
                                <span v-else class="tkt-tile-value">{{ props.ticket?.agent ? props.ticket?.agent?.first_name + ' ' + props.ticket?.agent?.last_name : $t('Not set') }}</span>
                            </div>
                            <div class="tkt-tile">
                                <span class="tkt-tile-label">{{ $t('Date created') }}</span>
                                <span class="tkt-tile-value">{{ formatDateTime(props.ticket.created_at) }}</span>
                            </div>
                        </div>

                        <div v-if="props.ticket.status === 'open' || props.ticket.status === 'pending'" class="mt-4 grid grid-cols-2 gap-2">
                            <button type="button" @click="changeTicketStatus('closed')" class="tkt-btn tkt-btn--ghost justify-center">{{ $t('Close ticket') }}</button>
                            <button type="button" @click="changeTicketStatus('resolved')" class="tkt-btn tkt-btn--solid justify-center">{{ $t('Mark as resolved') }}</button>
                        </div>
                    </UiSectionCard>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
<script setup>
    import AppLayout from './../Layout/App.vue';
    import axios from 'axios';
    import { Link, router, useForm, usePage } from "@inertiajs/vue3";
    import { ref, onMounted } from 'vue';
    import { useI18n } from 'vue-i18n';
    import FormSelect from '@/Components/FormSelect.vue';
    import FormTextArea from '@/Components/FormTextArea.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import UiSectionCard from '@/Components/UI/UiSectionCard.vue';
    const { t, te } = useI18n();
    const page = usePage();

    const props = defineProps(['title', 'ticket', 'users']);
    const priority = ref(props.ticket.priority);
    const user = ref(props.ticket.assigned_to);
    const userOptions = ref([]);
    const form = useForm({
        'message' : null,
    });
    const priorityOptions = ref([
        { value: 'critical', label: t('Critical') },
        { value: 'high', label: t('High') },
        { value: 'medium', label: t('Medium') },
        { value: 'low', label: t('Low') },
    ])

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

    const priorityChipClass = (priorityValue) => {
        switch (String(priorityValue ?? '').toLowerCase()) {
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
      const firstInitial = firstName.charAt(0).toUpperCase();
      const lastInitial = lastName.charAt(0).toUpperCase();

      return `${firstInitial}${lastInitial}`;
    }

    const submitForm = () => {
        form.post('/admin/support/' + props.ticket.uuid + '/comment', {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    const changeTicketStatus = (status) => {
        router.post('/admin/support/' + props.ticket.uuid + '/status', { status: status });
    }

    const transformUsers = (users) => {
        return users.map((user) => ({
            value: user.id,
            label: user.first_name + ' ' + user.last_name,
        }));
    };

    const updateUser = async(value) => {
        try {
            await axios.post('/admin/support/' + props.ticket.uuid + '/assign', { user: value });
        } catch {
            // Ticket assignment changes are best-effort in this previous admin panel.
        }
    }

    const updatePriority = async(value) => {
        try {
            await axios.post('/admin/support/' + props.ticket.uuid + '/priority', { priority: value });
        } catch {
            // Ticket priority changes are best-effort in this previous admin panel.
        }
    }

    onMounted(() => {
        userOptions.value = transformUsers(props.users);
    });
</script>

<style scoped>
.tkt-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: 0.85rem;
    padding: 0.6rem 1.1rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: background-color 160ms ease, border-color 160ms ease, filter 160ms ease;
}

.tkt-btn--ghost {
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    color: var(--ui-text);
}

.tkt-btn--ghost:hover {
    background: var(--ui-surface-soft);
    border-color: var(--ui-border-strong);
}

.tkt-btn--solid {
    border: 1px solid transparent;
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.tkt-btn--solid:hover {
    filter: brightness(1.05);
}

.tkt-message-box {
    border: 1px dashed var(--ui-border-strong);
    border-radius: 0.9rem;
    background: var(--ui-surface-soft);
    padding: 0.9rem 1.05rem;
    font-size: 0.9rem;
    line-height: 1.6;
    color: var(--ui-text);
    white-space: pre-wrap;
}

.tkt-comment-card {
    border-radius: 1.1rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    padding: 1.1rem;
    box-shadow: var(--ui-shadow-1);
}

.tkt-avatar {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 999px;
    background: linear-gradient(135deg, var(--ui-secondary), color-mix(in srgb, var(--ui-secondary) 70%, var(--ui-primary)));
    color: #fff;
    font-size: 0.85rem;
    font-weight: 800;
}

.tkt-comment-name {
    font-weight: 700;
    color: var(--ui-text);
}

.tkt-comment-date {
    font-size: 0.75rem;
    color: var(--ui-muted);
}

.tkt-comment-message {
    color: var(--ui-text);
    line-height: 1.6;
}

.tkt-tile {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    border-radius: 0.9rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    padding: 0.75rem 0.9rem;
}

.tkt-tile-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    color: var(--ui-muted);
}

.tkt-tile-value {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--ui-text);
}

.tkt-chip {
    display: inline-flex;
    align-self: start;
    align-items: center;
    border-radius: 999px;
    padding: 0.3rem 0.75rem;
    font-size: 0.78rem;
    font-weight: 700;
}
</style>
