<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full">
            <UiPageHeader :title="campaign.name">
                <template #title>
                    <div class="flex items-center gap-x-3 flex-wrap">
                        <span>{{ campaign.name }}</span>
                        <span class="view-status-badge" :style="{ '--status-color': statusColorVar(campaign.status) }">
                            <span class="dot"></span>
                            {{ translateStatus(campaign.status) }}
                        </span>
                    </div>
                </template>
                <template #actions>
                    <Link href="/campaigns" class="view-btn view-btn--ghost">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16"><path fill="currentColor" fill-rule="evenodd" d="M5.841 5.28a.75.75 0 0 0-1.06-1.06L1.53 7.47L1 8l.53.53l3.25 3.25a.75.75 0 0 0 1.061-1.06l-1.97-1.97H24.25a.75.75 0 0 0 0-1.5H3.871l1.97-1.97Z" clip-rule="evenodd"/></svg>
                        {{ $t('Back') }}
                    </Link>
                    <a :href="'/campaigns/export/' + campaign.uuid" class="view-btn view-btn--ghost">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
                        {{ $t('Export as CSV') }}
                    </a>
                    <button v-if="canDelete" type="button" class="view-btn view-btn--danger" @click="openAlert(campaign.uuid)">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M10 11v6M14 11v6M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2l1-12M9 7V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3"/></svg>
                        {{ $t('Delete campaign') }}
                    </button>
                </template>
            </UiPageHeader>
            <p class="view-ref" dir="ltr">{{ campaign.uuid }}</p>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 mt-4">
                <UiStatCard class="view-kpi view-kpi--total" :title="$t('Recipients')" :value="campaign.total_message_count">
                    <template #icon>
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm12 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </template>
                </UiStatCard>

                <div class="view-rate-card" style="--rate-color:#155dfc;">
                    <div class="view-rate-head">
                        <span class="view-rate-icon"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="m5 13 4 4L19 7"/></svg></span>
                        <span class="view-rate-title">{{ $t('Delivery rate') }}</span>
                    </div>
                    <div class="view-rate-value-row">
                        <span class="view-rate-value">{{ deliveryPercent }}%</span>
                        <span class="view-rate-frac" dir="ltr">{{ campaign.total_delivered_count }}/{{ campaign.total_message_count }}</span>
                    </div>
                    <div class="view-rate-meter"><div class="view-rate-meter-fill" :style="{ width: deliveryPercent + '%' }"></div></div>
                </div>

                <div class="view-rate-card" style="--rate-color:var(--ui-secondary);">
                    <div class="view-rate-head">
                        <span class="view-rate-icon"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg></span>
                        <span class="view-rate-title">{{ $t('Read rate') }}</span>
                    </div>
                    <div class="view-rate-value-row">
                        <span class="view-rate-value">{{ readPercent }}%</span>
                        <span class="view-rate-frac" dir="ltr">{{ campaign.total_read_count }}/{{ campaign.total_message_count }}</span>
                    </div>
                    <div class="view-rate-meter"><div class="view-rate-meter-fill" :style="{ width: readPercent + '%' }"></div></div>
                </div>

                <UiStatCard class="view-kpi view-kpi--failed" :title="$t('Failed')" :value="campaign.total_failed_count">
                    <template #icon>
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v4m0 4h.01M10.29 3.86l-8.18 14A2 2 0 0 0 3.82 21h16.36a2 2 0 0 0 1.71-3.14l-8.18-14a2 2 0 0 0-3.42 0Z"/></svg>
                    </template>
                </UiStatCard>
            </div>

            <div class="view-layout">
                <div>
                    <div class="view-card">
                        <h2 class="view-card-title">{{ $t('Recipients') }}</h2>
                        <CampaignLogTable :rows="props.rows" :filters="props.filters" :uuid="campaign.uuid"/>
                    </div>
                </div>

                <div>
                    <div class="view-card">
                        <h2 class="view-card-title">{{ $t('Campaign details') }}</h2>
                        <dl class="view-meta-list">
                            <div class="view-meta-row">
                                <dt>{{ $t('Template') }}</dt>
                                <dd>{{ campaign.template?.name ?? '-' }}</dd>
                            </div>
                            <div class="view-meta-row">
                                <dt>{{ $t('Recipients') }}</dt>
                                <dd>{{ recipientLabel }}</dd>
                            </div>
                            <div class="view-meta-row">
                                <dt>{{ $t('Created') }}</dt>
                                <dd>{{ campaign.created_at }}</dd>
                            </div>
                            <div v-if="campaign.status === 'scheduled'" class="view-meta-row">
                                <dt>{{ $t('Scheduled for') }}</dt>
                                <dd>{{ campaign.scheduled_at }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="view-card">
                        <h2 class="view-card-title">{{ $t('Live preview') }}</h2>
                        <div class="view-preview-frame">
                            <div class="ui-workspace-main--chat view-preview-body">
                                <WhatsappTemplate :parameters="previewParameters" :placeholder="false" :visible="true"/>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alert Modal Component-->
        <AlertModal
            v-model="isOpenAlert"
            @confirm="() => confirmAlert(deleteCampaign)"
            :label="$t('Delete campaign')"
            :description="$t('Are you sure you want to delete this campaign? This action will only delete the campaign; sent messages will not be deleted from the chat history.')"
        />
    </AppLayout>
</template>
<script setup>
    import AppLayout from "./../Layout/App.vue";
    import CampaignLogTable from '@/Components/Tables/CampaignLogTable.vue';
    import WhatsappTemplate from '@/Components/WhatsappTemplate.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import UiStatCard from '@/Components/UI/UiStatCard.vue';
    import AlertModal from '@/Components/AlertModal.vue';
    import { useAlertModal } from '@/Composables/useAlertModal';
    import { Link, useForm, usePage } from "@inertiajs/vue3";
    import { computed } from 'vue';
    import { useI18n } from 'vue-i18n';

    const { t } = useI18n();
    const props = defineProps(['campaign', 'rows', 'filters']);
    const campaign = props.campaign;

    const { isOpenAlert, openAlert, confirmAlert } = useAlertModal();

    const hasPermission = (permission) => {
        const permissions = usePage().props?.permissions;
        if (!permissions || !Array.isArray(permissions)) {
            return false;
        }
        return permissions.includes('*') || permissions.includes(permission);
    };
    const canDelete = computed(() => hasPermission('campaigns.delete'));

    const form = useForm({});
    const deleteCampaign = (uuid) => {
        if (!canDelete.value) {
            return;
        }
        form.delete('/campaigns/' + uuid);
    };

    const statusColorVar = (status) => {
        switch (status) {
            case 'ongoing': return '#155dfc';
            case 'scheduled': return 'var(--ui-warning)';
            case 'completed': return 'var(--ui-success)';
            default: return 'var(--ui-muted)';
        }
    };
    const translateStatus = (status) => t(String(status ?? '').toLowerCase());

    const deliveryPercent = computed(() => {
        const total = campaign.total_message_count || 0;
        if (!total) return 0;
        return Math.max(0, Math.min(100, Math.round((campaign.total_delivered_count / total) * 100)));
    });
    const readPercent = computed(() => {
        const total = campaign.total_message_count || 0;
        if (!total) return 0;
        return Math.max(0, Math.min(100, Math.round((campaign.total_read_count / total) * 100)));
    });

    const recipientLabel = computed(() => {
        return Number(campaign.contact_group_id) === 0
            ? t('All contacts')
            : (campaign.contact_group?.name ?? '-');
    });

    const previewParameters = computed(() => {
        try {
            return JSON.parse(campaign.metadata);
        } catch {
            return { header: { format: null, text: null, parameters: [] }, body: { text: '' }, footer: { text: null }, buttons: [] };
        }
    });
</script>

<style scoped>
.view-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border-radius: 999px;
    padding: 0.3rem 0.75rem;
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: capitalize;
    background: color-mix(in srgb, var(--status-color) 16%, transparent);
    color: var(--status-color);
}
.view-status-badge .dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

.view-ref {
    margin: -0.5rem 0 0;
    font-size: 0.78rem;
    color: var(--ui-muted);
    unicode-bidi: isolate;
}

.view-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border-radius: 0.7rem;
    padding: 0.55rem 1rem;
    font-size: 0.82rem;
    font-weight: 600;
}
.view-btn--ghost {
    background: var(--ui-surface-soft);
    color: var(--ui-text);
    border: 1px solid var(--ui-border);
}
.view-btn--ghost:hover { background: var(--ui-border); }
.view-btn--danger {
    background: color-mix(in srgb, var(--ui-danger) 12%, transparent);
    color: var(--ui-danger);
    border: 1px solid color-mix(in srgb, var(--ui-danger) 35%, transparent);
}
.view-btn--danger:hover { background: color-mix(in srgb, var(--ui-danger) 18%, transparent); }

.view-kpi :deep(.ui-kpi-icon) { width: 2.5rem; height: 2.5rem; border-radius: 0.85rem; }
.view-kpi :deep(.ui-kpi-icon svg) { width: 1.1rem; height: 1.1rem; }
.view-kpi--total :deep(.ui-kpi-icon) { background: color-mix(in srgb, var(--ui-secondary) 16%, transparent); border-color: color-mix(in srgb, var(--ui-secondary) 30%, transparent); }
.view-kpi--total :deep(.ui-kpi-icon svg) { color: var(--ui-secondary) !important; }
.view-kpi--failed :deep(.ui-kpi-icon) { background: color-mix(in srgb, var(--ui-danger) 16%, transparent); border-color: color-mix(in srgb, var(--ui-danger) 30%, transparent); }
.view-kpi--failed :deep(.ui-kpi-icon svg) { color: var(--ui-danger) !important; }

.view-rate-card {
    background: var(--ui-surface);
    border: 1px solid var(--ui-border);
    border-radius: 18px;
    padding: 1.1rem 1.25rem;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
}
.view-rate-head { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.7rem; }
.view-rate-icon {
    display: inline-flex; align-items: center; justify-content: center;
    width: 1.9rem; height: 1.9rem; border-radius: 0.6rem;
    background: color-mix(in srgb, var(--rate-color) 16%, transparent); color: var(--rate-color);
}
.view-rate-title { font-size: 0.78rem; font-weight: 700; color: var(--ui-muted); }
.view-rate-value-row { display: flex; align-items: baseline; gap: 0.5rem; margin-bottom: 0.5rem; }
.view-rate-value { font-size: 1.5rem; font-weight: 800; font-variant-numeric: tabular-nums; }
.view-rate-frac { font-size: 0.75rem; color: var(--ui-muted); font-variant-numeric: tabular-nums; unicode-bidi: isolate; }
.view-rate-meter { height: 6px; border-radius: 999px; background: var(--ui-surface-soft); overflow: hidden; }
.view-rate-meter-fill { height: 100%; border-radius: 999px; background: var(--rate-color); transition: width 500ms cubic-bezier(.2,.8,.2,1); }

.view-layout { display: grid; grid-template-columns: minmax(0, 1.6fr) minmax(20rem, 23rem); gap: 1.5rem; align-items: start; margin-top: 1.5rem; }

.view-card {
    background: var(--ui-surface);
    border: 1px solid var(--ui-border);
    border-radius: 1.1rem;
    padding: 1.5rem;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}
.view-card + .view-card { margin-top: 1.25rem; }
.view-card-title { font-size: 1rem; font-weight: 800; margin: 0 0 1rem; }

.view-meta-list { display: flex; flex-direction: column; gap: 0.7rem; }
.view-meta-row { display: flex; justify-content: space-between; gap: 1rem; font-size: 0.85rem; padding-bottom: 0.7rem; border-bottom: 1px dashed var(--ui-border); }
.view-meta-row:last-child { border-bottom: none; padding-bottom: 0; }
.view-meta-row dt { color: var(--ui-muted); font-weight: 600; }
.view-meta-row dd { margin: 0; font-weight: 700; text-align: end; }

.view-preview-frame { border: 1px solid var(--ui-border); border-radius: 1rem; overflow: hidden; }
.view-preview-body { padding: 1.6rem 1rem; min-height: 14rem; display: flex; align-items: flex-end; }

@media (max-width: 980px) {
    .view-layout { grid-template-columns: 1fr; }
}
</style>
