<template>
    <div v-if="nodeType === 'send_email'" class="grid gap-4">
        <label class="block">
            <div class="mb-2 text-sm font-semibold text-[var(--ui-text)]">{{ $t('Subject') }}</div>
            <input type="text" class="nodrag w-full rounded-2xl border border-[var(--ui-border)] bg-[var(--ui-surface)] px-4 py-3 text-sm text-[var(--ui-text)] outline-none transition focus:border-[var(--ui-secondary)]" :value="config.subject || ''" @input="setConfigValue('subject', $event.target.value)" />
        </label>

        <label class="block">
            <div class="mb-2 text-sm font-semibold text-[var(--ui-text)]">{{ $t('Body') }}</div>
            <FlowAutosizeTextarea rows="4" class="nodrag w-full min-h-[116px] rounded-2xl border border-[var(--ui-border)] bg-[var(--ui-surface)] px-4 py-3 text-sm leading-7 text-[var(--ui-text)] outline-none transition focus:border-[var(--ui-secondary)]" :value="config.body || ''" @input="setConfigValue('body', $event)" />
        </label>

        <details class="nodrag rounded-2xl border border-[var(--ui-border)] bg-[var(--ui-surface-soft)] p-4">
            <summary class="nodrag cursor-pointer list-none text-sm font-semibold text-[var(--ui-text)]">
                {{ $t('Advanced settings') }}
            </summary>
            <div class="mt-4 grid gap-3">
                <div class="text-sm font-semibold text-[var(--ui-text)]">{{ $t('SMTP Settings') }}</div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <input type="text" class="nodrag w-full rounded-2xl border border-[var(--ui-border)] bg-[var(--ui-surface)] px-4 py-3 text-sm text-[var(--ui-text)] outline-none transition focus:border-[var(--ui-secondary)]" :value="secret.host || ''" :placeholder="$t('SMTP Host')" @input="setSecretValue('host', $event.target.value)" />
                    <input type="text" class="nodrag w-full rounded-2xl border border-[var(--ui-border)] bg-[var(--ui-surface)] px-4 py-3 text-sm text-[var(--ui-text)] outline-none transition focus:border-[var(--ui-secondary)]" :value="secret.port || ''" :placeholder="$t('SMTP Port')" @input="setSecretValue('port', $event.target.value)" />
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <input type="text" class="nodrag w-full rounded-2xl border border-[var(--ui-border)] bg-[var(--ui-surface)] px-4 py-3 text-sm text-[var(--ui-text)] outline-none transition focus:border-[var(--ui-secondary)]" :value="secret.username || ''" :placeholder="$t('Username')" @input="setSecretValue('username', $event.target.value)" />
                    <input type="password" class="nodrag w-full rounded-2xl border border-[var(--ui-border)] bg-[var(--ui-surface)] px-4 py-3 text-sm text-[var(--ui-text)] outline-none transition focus:border-[var(--ui-secondary)]" :placeholder="secret.has_password ? $t('Password saved. Enter a new value only if you want to replace it.') : $t('Password')" @input="setSecretValue('password', $event.target.value)" />
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <select class="nodrag w-full rounded-2xl border border-[var(--ui-border)] bg-[var(--ui-surface)] px-4 py-3 text-sm text-[var(--ui-text)] outline-none transition focus:border-[var(--ui-secondary)]" :value="secret.encryption || 'tls'" @change="setSecretValue('encryption', $event.target.value)">
                        <option value="tls">{{ $t('TLS') }}</option>
                        <option value="ssl">{{ $t('SSL') }}</option>
                        <option value="none">{{ $t('None') }}</option>
                    </select>
                    <input type="text" class="nodrag w-full rounded-2xl border border-[var(--ui-border)] bg-[var(--ui-surface)] px-4 py-3 text-sm text-[var(--ui-text)] outline-none transition focus:border-[var(--ui-secondary)]" :value="secret.from_name || ''" :placeholder="$t('From Name')" @input="setSecretValue('from_name', $event.target.value)" />
                </div>
                <input type="email" class="nodrag w-full rounded-2xl border border-[var(--ui-border)] bg-[var(--ui-surface)] px-4 py-3 text-sm text-[var(--ui-text)] outline-none transition focus:border-[var(--ui-secondary)]" :value="secret.from_email || ''" :placeholder="$t('From Email')" @input="setSecretValue('from_email', $event.target.value)" />
                <label class="flex items-center gap-3 text-sm font-medium text-[var(--ui-text)]">
                    <input type="checkbox" class="nodrag h-4 w-4 rounded border-[var(--ui-border-strong)] text-[var(--ui-success)] focus:ring-[color-mix(in_srgb,var(--ui-success)_35%,transparent)]" :checked="Boolean(config.active ?? true)" @change="setConfigValue('active', $event.target.checked)" />
                    {{ $t('Active') }}
                </label>
            </div>
        </details>
    </div>

    <div v-else-if="nodeType === 'delay'" class="grid gap-4">
        <label class="block">
            <div class="mb-2 text-sm font-semibold text-[var(--ui-text)]">{{ $t('Duration (minutes)') }}</div>
            <input type="number" min="1" class="nodrag w-full rounded-2xl border border-[var(--ui-border)] bg-[var(--ui-surface)] px-4 py-3 text-sm text-[var(--ui-text)] outline-none transition focus:border-[var(--ui-secondary)]" :value="config.minutes || 5" @input="setConfigValue('minutes', normalizeInteger($event.target.value, 5))" />
        </label>

        <details class="nodrag rounded-2xl border border-[var(--ui-border)] bg-[var(--ui-surface-soft)] px-4 py-3">
            <summary class="nodrag cursor-pointer list-none text-sm font-semibold text-[var(--ui-text)]">
                {{ $t('Advanced settings') }}
            </summary>
            <label class="mt-3 flex items-center gap-3 text-sm font-medium text-[var(--ui-text)]">
                <input type="checkbox" class="nodrag h-4 w-4 rounded border-[var(--ui-border-strong)] text-[var(--ui-success)] focus:ring-[color-mix(in_srgb,var(--ui-success)_35%,transparent)]" :checked="Boolean(config.active ?? true)" @change="setConfigValue('active', $event.target.checked)" />
                {{ $t('Active') }}
            </label>
        </details>
    </div>

    <div v-else class="grid gap-4">
        <label class="block">
            <div class="mb-2 text-sm font-semibold text-[var(--ui-text)]">{{ $t('URL') }}</div>
            <input type="url" class="nodrag w-full rounded-2xl border border-[var(--ui-border)] bg-[var(--ui-surface)] px-4 py-3 text-sm text-[var(--ui-text)] outline-none transition focus:border-[var(--ui-secondary)]" :value="config.url || ''" :placeholder="$t('Paste the destination URL')" @input="setConfigValue('url', $event.target.value)" />
        </label>

        <label class="block">
            <div class="mb-2 text-sm font-semibold text-[var(--ui-text)]">{{ $t('Method') }}</div>
            <select class="nodrag w-full rounded-2xl border border-[var(--ui-border)] bg-[var(--ui-surface)] px-4 py-3 text-sm text-[var(--ui-text)] outline-none transition focus:border-[var(--ui-secondary)]" :value="config.method || 'POST'" @change="setConfigValue('method', $event.target.value)">
                <option value="POST">{{ $t('POST') }}</option>
                <option value="GET">{{ $t('GET') }}</option>
                <option value="PUT">{{ $t('PUT') }}</option>
                <option value="PATCH">{{ $t('PATCH') }}</option>
                <option value="DELETE">{{ $t('DELETE') }}</option>
            </select>
        </label>

        <details class="nodrag rounded-2xl border border-[var(--ui-border)] bg-[var(--ui-surface-soft)] px-4 py-3">
            <summary class="nodrag cursor-pointer list-none text-sm font-semibold text-[var(--ui-text)]">
                {{ $t('Advanced settings') }}
            </summary>
            <label class="mt-3 flex items-center gap-3 text-sm font-medium text-[var(--ui-text)]">
                <input type="checkbox" class="nodrag h-4 w-4 rounded border-[var(--ui-border-strong)] text-[var(--ui-success)] focus:ring-[color-mix(in_srgb,var(--ui-success)_35%,transparent)]" :checked="Boolean(config.active ?? true)" @change="setConfigValue('active', $event.target.checked)" />
                {{ $t('Active') }}
            </label>
        </details>
    </div>
</template>

<script setup>
import FlowAutosizeTextarea from '@/Components/AutomationFlows/FlowAutosizeTextarea.vue';
import { useFlowNodeInspectorContext } from '@/Components/AutomationFlows/useFlowNodeInspector.js';

const { config, nodeType, normalizeInteger, secret, setConfigValue, setSecretValue } = useFlowNodeInspectorContext();
</script>
