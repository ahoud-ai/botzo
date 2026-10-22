<script setup>
import { computed, ref, toRefs, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { useI18n } from 'vue-i18n';
import AlertModal from '@/Components/AlertModal.vue';
import { useAlertModal } from '@/Composables/useAlertModal';
import { useClipboard } from '@/Composables/useClipboard';

const props = defineProps({
    webhooks: {
        type: Object,
        required: true,
    },
});

const { webhooks } = toRefs(props);
const { t } = useI18n();
const page = usePage();
const { copy, isCopied } = useClipboard();
const { isOpenAlert: isOpenDeleteAlert, openAlert: openDeleteAlert, confirmAlert: confirmDeleteAlert } = useAlertModal();
const { isOpenAlert: isOpenRotateAlert, openAlert: openRotateAlert, confirmAlert: confirmRotateAlert } = useAlertModal();

const form = useForm({ url: '', events: ['message.received', 'message.status', 'conversation.handoff', 'conversation.reply'] });
const busyUuid = ref(null);
const inspectorRequests = ref(webhooks.value.inspector_requests || []);
const inspectorLoading = ref(false);
// A test send or any other action reloads the page data; show the receiver's latest requests then too.
watch(() => webhooks.value.inspector_requests, (requests) => {
    inspectorRequests.value = requests || [];
});
const openRequest = ref(null);

const permissions = computed(() => (Array.isArray(page.props.permissions) ? page.props.permissions : []));
const can = (permission) => permissions.value.includes('*') || permissions.value.includes(permission);
const generatedSecret = computed(() => page.props.flash?.generated_webhook_secret || null);
const canAddMore = computed(() => webhooks.value.endpoints.length < webhooks.value.max);

const eventLabels = computed(() => ({
    'message.received': { title: t('Message received'), help: t('A customer sent you a WhatsApp message.') },
    'message.status': { title: t('Message status'), help: t('A message you sent was sent, delivered, read or failed.') },
    'conversation.handoff': { title: t('Handed to a human'), help: t('The customer asked for a person, or the assistant handed over.') },
    'conversation.reply': { title: t('Assistant or team reply'), help: t('Mochi or your team answered in a website or app conversation.') },
}));

const deliveryStatus = (status) => ({
    succeeded: t('Delivered'),
    pending: t('Retrying'),
    failed: t('Failed'),
}[status] || status);

const formatTime = (value) => (value ? new Date(value).toLocaleString() : '');

const addWebhook = () => {
    form.post('/developer-tools/event-hooks', {
        preserveScroll: true,
        onSuccess: () => form.reset('url'),
    });
};

const useInspector = () => {
    form.url = webhooks.value.inspector_url;
};

const action = (uuid, method, path, data = {}) => {
    busyUuid.value = uuid;
    router[method](`/developer-tools/event-hooks/${uuid}${path}`, data, {
        preserveScroll: true,
        onFinish: () => {
            busyUuid.value = null;
        },
    });
};

const toggleEvent = (endpoint, event) => {
    const events = endpoint.events.includes(event)
        ? endpoint.events.filter((item) => item !== event)
        : [...endpoint.events, event];
    if (events.length === 0) {
        return;
    }
    action(endpoint.uuid, 'put', '', { events, is_active: endpoint.is_active });
};

const setActive = (endpoint, active) => action(endpoint.uuid, 'put', '', { events: endpoint.events, is_active: active });
const sendTest = (endpoint) => action(endpoint.uuid, 'post', '/test');
const rotate = (uuid) => action(uuid, 'post', '/rotate');
const remove = (uuid) => action(uuid, 'delete', '');

const refreshInspector = async () => {
    inspectorLoading.value = true;
    try {
        const { data } = await axios.get('/developer-tools/webhook-inspector');
        inspectorRequests.value = data.requests || [];
    } finally {
        inspectorLoading.value = false;
    }
};

const prettyBody = (body) => {
    try {
        return JSON.stringify(JSON.parse(body), null, 2);
    } catch (error) {
        return body;
    }
};
</script>

<template>
    <div class="wh">
        <div class="wh-head">
            <div class="space-y-2">
                <p class="wh-eyebrow">{{ $t('Webhooks') }}</p>
                <h2 class="wh-title">{{ $t('Get notified on your server') }}</h2>
                <p class="wh-text">{{ $t('Botzo sends a signed POST request to your URL as soon as something happens, so your app does not have to keep asking.') }}</p>
            </div>
            <div class="wh-count">{{ webhooks.endpoints.length }} / {{ webhooks.max }}</div>
        </div>

        <section v-if="generatedSecret" class="wh-secret" aria-live="polite">
            <div class="wh-secret-body">
                <h3 class="wh-secret-title">{{ $t('Signing secret') }}</h3>
                <p class="wh-text">{{ $t('Use it on your server to check that requests really come from Botzo. It will not be shown again.') }}</p>
                <code class="wh-code">{{ generatedSecret }}</code>
            </div>
            <button type="button" class="wh-btn wh-btn--primary" @click="copy(generatedSecret, 'webhook-secret')">
                {{ isCopied('webhook-secret') ? $t('Copied') : $t('Copy secret') }}
            </button>
        </section>

        <form v-if="can('developer_tools.add') && canAddMore" class="wh-card wh-form" @submit.prevent="addWebhook">
            <div>
                <label for="wh-url" class="wh-label">{{ $t('Your server URL') }}</label>
                <div class="wh-url-row">
                    <input
                        id="wh-url"
                        v-model="form.url"
                        type="url"
                        dir="ltr"
                        class="ui-input"
                        placeholder="https://example.com/botzo/webhook"
                        required
                    >
                    <button type="button" class="wh-btn" @click="useInspector">{{ $t('Use the test receiver') }}</button>
                </div>
                <p v-if="form.errors.url" class="wh-error">{{ form.errors.url }}</p>
            </div>

            <fieldset>
                <legend class="wh-label">{{ $t('Events to send') }}</legend>
                <div class="wh-events">
                    <label v-for="event in webhooks.events" :key="event" class="wh-event">
                        <input v-model="form.events" type="checkbox" :value="event">
                        <span>
                            <span class="wh-event-title">{{ eventLabels[event]?.title }}</span>
                            <code class="wh-event-code">{{ event }}</code>
                            <span class="wh-event-help">{{ eventLabels[event]?.help }}</span>
                        </span>
                    </label>
                </div>
                <p v-if="form.errors.events" class="wh-error">{{ form.errors.events }}</p>
            </fieldset>

            <div>
                <button type="submit" class="wh-btn wh-btn--primary" :disabled="form.processing || form.events.length === 0">
                    {{ $t('Add webhook') }}
                </button>
            </div>
        </form>

        <div v-if="webhooks.endpoints.length" class="space-y-3">
            <article v-for="endpoint in webhooks.endpoints" :key="endpoint.uuid" class="wh-card">
                <div class="wh-endpoint-head">
                    <code class="wh-url" dir="ltr">{{ endpoint.url }}</code>
                    <span class="wh-chip" :class="endpoint.is_active ? 'wh-chip--on' : 'wh-chip--off'">
                        {{ endpoint.is_active ? $t('Active') : (endpoint.disabled_at ? $t('Stopped after repeated failures') : $t('Paused')) }}
                    </span>
                </div>

                <div class="wh-events wh-events--compact">
                    <label v-for="event in webhooks.events" :key="event" class="wh-event wh-event--compact">
                        <input
                            type="checkbox"
                            :checked="endpoint.events.includes(event)"
                            :disabled="!can('developer_tools.edit') || busyUuid === endpoint.uuid"
                            @change="toggleEvent(endpoint, event)"
                        >
                        <span class="wh-event-title">{{ eventLabels[event]?.title }}</span>
                    </label>
                </div>

                <div class="wh-actions">
                    <button v-if="can('developer_tools.edit')" type="button" class="wh-btn" :disabled="busyUuid === endpoint.uuid" @click="sendTest(endpoint)">
                        {{ $t('Send test event') }}
                    </button>
                    <button v-if="can('developer_tools.edit')" type="button" class="wh-btn" :disabled="busyUuid === endpoint.uuid" @click="setActive(endpoint, !endpoint.is_active)">
                        {{ endpoint.is_active ? $t('Pause') : $t('Turn on') }}
                    </button>
                    <button v-if="can('developer_tools.edit')" type="button" class="wh-btn" :disabled="busyUuid === endpoint.uuid" @click="openRotateAlert(endpoint.uuid)">
                        {{ $t('New secret') }}
                    </button>
                    <button v-if="can('developer_tools.delete')" type="button" class="wh-btn wh-btn--danger" :disabled="busyUuid === endpoint.uuid" @click="openDeleteAlert(endpoint.uuid)">
                        {{ $t('Delete') }}
                    </button>
                </div>
            </article>
        </div>

        <section class="wh-card">
            <div class="wh-section-head">
                <div>
                    <h3 class="wh-subtitle">{{ $t('Test receiver') }}</h3>
                    <p class="wh-text">{{ $t('A private URL inside Botzo that shows the requests it receives. Add it as a webhook to see real events before you write any code.') }}</p>
                </div>
                <button type="button" class="wh-btn" :disabled="inspectorLoading" @click="refreshInspector">
                    {{ inspectorLoading ? $t('Refreshing...') : $t('Refresh') }}
                </button>
            </div>
            <div class="wh-url-row">
                <code class="wh-url wh-url--box" dir="ltr">{{ webhooks.inspector_url }}</code>
                <button type="button" class="wh-btn" @click="copy(webhooks.inspector_url, 'inspector-url')">
                    {{ isCopied('inspector-url') ? $t('Copied') : $t('Copy') }}
                </button>
            </div>

            <p v-if="!inspectorRequests.length" class="wh-empty">{{ $t('Nothing received yet.') }}</p>
            <ul v-else class="wh-list">
                <li v-for="request in inspectorRequests" :key="request.id" class="wh-list-item">
                    <button type="button" class="wh-row" :aria-expanded="openRequest === request.id" @click="openRequest = openRequest === request.id ? null : request.id">
                        <code class="wh-event-code">{{ request.event || $t('Unknown event') }}</code>
                        <span class="wh-chip" :class="request.signature_valid ? 'wh-chip--on' : 'wh-chip--off'">
                            {{ request.signature_valid === null ? $t('Not signed') : (request.signature_valid ? $t('Signature valid') : $t('Signature invalid')) }}
                        </span>
                        <span class="wh-muted">{{ formatTime(request.received_at) }}</span>
                    </button>
                    <pre v-if="openRequest === request.id" class="wh-pre" dir="ltr">{{ prettyBody(request.body) }}</pre>
                </li>
            </ul>
        </section>

        <section class="wh-card">
            <h3 class="wh-subtitle">{{ $t('Recent deliveries') }}</h3>
            <p v-if="!webhooks.deliveries.length" class="wh-empty">{{ $t('No events sent yet.') }}</p>
            <div v-else class="wh-table-wrap">
                <table class="wh-table">
                    <thead>
                        <tr>
                            <th>{{ $t('Event') }}</th>
                            <th>{{ $t('Result') }}</th>
                            <th>{{ $t('Attempts') }}</th>
                            <th>{{ $t('Time') }}</th>
                            <th>{{ $t('Sent at') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="delivery in webhooks.deliveries" :key="delivery.event_id">
                            <td><code class="wh-event-code">{{ delivery.event }}</code></td>
                            <td>
                                <span class="wh-chip" :class="delivery.status === 'succeeded' ? 'wh-chip--on' : 'wh-chip--off'">{{ deliveryStatus(delivery.status) }}</span>
                                <span v-if="delivery.error" class="wh-muted" dir="ltr"> {{ delivery.error }}</span>
                            </td>
                            <td>{{ delivery.attempts }}</td>
                            <td dir="ltr">{{ delivery.response_ms !== null ? delivery.response_ms + ' ms' : '' }}</td>
                            <td class="wh-muted">{{ formatTime(delivery.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <AlertModal
            v-model="isOpenDeleteAlert"
            :label="$t('Delete this webhook?')"
            :description="$t('Botzo stops sending events to this URL right away.')"
            :confirm-button-text="$t('Delete')"
            @confirm="() => confirmDeleteAlert(remove)"
        />
        <AlertModal
            v-model="isOpenRotateAlert"
            :label="$t('Create a new signing secret?')"
            :description="$t('The old secret stops working at once. Update your server with the new one.')"
            :confirm-button-text="$t('Create')"
            @confirm="() => confirmRotateAlert(rotate)"
        />
    </div>
</template>

<style scoped>
.wh { display: flex; flex-direction: column; gap: 1rem; }
.wh-head { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 1rem; }
.wh-eyebrow { font-size: 0.72rem; font-weight: 700; letter-spacing: 0.2em; text-transform: uppercase; color: var(--ui-muted); }
.wh-title { font-size: 1.35rem; font-weight: 700; color: var(--ui-text); }
.wh-subtitle { font-size: 1rem; font-weight: 700; color: var(--ui-text); }
.wh-text { max-width: 46rem; font-size: 0.88rem; line-height: 1.6; color: var(--ui-muted); }
.wh-count { border-radius: 999px; border: 1px solid var(--ui-border); background: var(--ui-surface-soft); padding: 0.35rem 0.9rem; font-size: 0.85rem; font-weight: 600; color: var(--ui-text); }
.wh-card { border-radius: 1.25rem; border: 1px solid var(--ui-border); background: var(--ui-surface-soft); padding: 1rem; }
.wh-form { display: flex; flex-direction: column; gap: 1rem; }
.wh-label { display: block; margin-bottom: 0.45rem; font-size: 0.82rem; font-weight: 600; color: var(--ui-text); }
.wh-url-row { display: flex; flex-wrap: wrap; gap: 0.6rem; align-items: center; }
.wh-url-row > .ui-input, .wh-url--box { flex: 1 1 18rem; min-width: 0; }
.wh-events { display: grid; gap: 0.6rem; }
@media (min-width: 768px) { .wh-events { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (min-width: 1280px) { .wh-events { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
.wh-event { display: flex; gap: 0.6rem; align-items: flex-start; border-radius: 0.9rem; border: 1px solid var(--ui-border); background: var(--ui-surface); padding: 0.75rem; cursor: pointer; }
.wh-event input { margin-top: 0.2rem; width: 1rem; height: 1rem; accent-color: var(--ui-primary); }
.wh-event--compact { padding: 0.5rem 0.7rem; align-items: center; }
.wh-event--compact input { margin-top: 0; }
.wh-events--compact { margin: 0.75rem 0; }
.wh-event-title { display: block; font-size: 0.85rem; font-weight: 600; color: var(--ui-text); }
.wh-event-code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.75rem; color: var(--ui-muted); direction: ltr; unicode-bidi: isolate; }
.wh-event-help { display: block; margin-top: 0.2rem; font-size: 0.78rem; line-height: 1.5; color: var(--ui-muted); }
.wh-endpoint-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.6rem; }
.wh-url { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.82rem; color: var(--ui-text); overflow-wrap: anywhere; text-align: left; }
.wh-url--box { border-radius: 0.8rem; border: 1px solid var(--ui-border); background: var(--ui-surface); padding: 0.6rem 0.8rem; }
.wh-chip { display: inline-flex; align-items: center; border-radius: 999px; padding: 0.15rem 0.65rem; font-size: 0.72rem; font-weight: 600; white-space: nowrap; }
.wh-chip--on { background: color-mix(in srgb, var(--ui-primary) 16%, transparent); color: var(--ui-text); }
.wh-chip--off { background: color-mix(in srgb, #ef4444 16%, transparent); color: var(--ui-text); }
.wh-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.wh-btn { display: inline-flex; min-height: 2.5rem; align-items: center; justify-content: center; border-radius: 0.8rem; border: 1px solid var(--ui-border); background: var(--ui-surface); padding: 0.5rem 0.95rem; font-size: 0.84rem; font-weight: 600; color: var(--ui-text); cursor: pointer; transition: filter 160ms ease, border-color 160ms ease; }
.wh-btn:hover:not(:disabled) { border-color: color-mix(in srgb, var(--ui-primary) 40%, var(--ui-border)); }
.wh-btn:focus-visible { outline: 2px solid var(--ui-primary); outline-offset: 2px; }
.wh-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.wh-btn--primary { border-color: transparent; background: var(--ui-secondary); color: #fff; }
.wh-btn--primary:hover:not(:disabled) { filter: brightness(1.08); }
.wh-btn--danger { color: #dc2626; }
.wh-secret { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; border-radius: 1.25rem; border: 1px solid color-mix(in srgb, var(--ui-primary) 35%, var(--ui-border)); background: color-mix(in srgb, var(--ui-primary) 8%, var(--ui-surface)); padding: 1rem; }
.wh-secret-body { min-width: 0; flex: 1 1 16rem; }
.wh-secret-title { font-size: 0.95rem; font-weight: 700; color: var(--ui-text); }
.wh-code { display: block; margin-top: 0.5rem; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.85rem; color: var(--ui-text); overflow-wrap: anywhere; direction: ltr; text-align: left; }
.wh-error { margin-top: 0.4rem; font-size: 0.8rem; color: #dc2626; }
.wh-section-head { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 0.75rem; margin-bottom: 0.75rem; }
.wh-empty { margin-top: 0.75rem; font-size: 0.85rem; color: var(--ui-muted); }
.wh-list { margin-top: 0.75rem; display: flex; flex-direction: column; gap: 0.5rem; }
.wh-list-item { border-radius: 0.9rem; border: 1px solid var(--ui-border); background: var(--ui-surface); }
.wh-row { display: flex; width: 100%; flex-wrap: wrap; align-items: center; gap: 0.6rem; padding: 0.65rem 0.8rem; text-align: start; cursor: pointer; }
.wh-row:focus-visible { outline: 2px solid var(--ui-primary); outline-offset: -2px; border-radius: 0.9rem; }
.wh-muted { font-size: 0.78rem; color: var(--ui-muted); }
.wh-pre { margin: 0; max-height: 22rem; overflow: auto; border-top: 1px solid var(--ui-border); padding: 0.8rem; font-size: 0.75rem; line-height: 1.5; color: var(--ui-text); white-space: pre-wrap; overflow-wrap: anywhere; text-align: left; }
.wh-table-wrap { margin-top: 0.75rem; overflow-x: auto; }
.wh-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; color: var(--ui-text); }
.wh-table th { padding: 0.5rem; text-align: start; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--ui-muted); border-bottom: 1px solid var(--ui-border); white-space: nowrap; }
.wh-table td { padding: 0.55rem 0.5rem; border-bottom: 1px solid var(--ui-border); vertical-align: middle; }
</style>
