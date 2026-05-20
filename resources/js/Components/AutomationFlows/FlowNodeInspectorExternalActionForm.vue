<template>
    <div v-if="nodeType === 'send_email'" class="grid gap-4">
        <label class="block">
            <div class="mb-2 text-sm font-semibold text-slate-900">{{ $t('Subject') }}</div>
            <input type="text" class="nodrag w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-emerald-300" :value="config.subject || ''" @input="setConfigValue('subject', $event.target.value)" />
        </label>

        <label class="block">
            <div class="mb-2 text-sm font-semibold text-slate-900">{{ $t('Body') }}</div>
            <FlowAutosizeTextarea rows="4" class="nodrag w-full min-h-[116px] rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm leading-7 text-slate-700 outline-none transition focus:border-emerald-300" :value="config.body || ''" @input="setConfigValue('body', $event)" />
        </label>

        <details class="nodrag rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <summary class="nodrag cursor-pointer list-none text-sm font-semibold text-slate-900">
                {{ $t('Advanced settings') }}
            </summary>
            <div class="mt-4 grid gap-3">
                <div class="text-sm font-semibold text-slate-900">{{ $t('SMTP Settings') }}</div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <input type="text" class="nodrag w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-emerald-300" :value="secret.host || ''" :placeholder="$t('SMTP Host')" @input="setSecretValue('host', $event.target.value)" />
                    <input type="text" class="nodrag w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-emerald-300" :value="secret.port || ''" :placeholder="$t('SMTP Port')" @input="setSecretValue('port', $event.target.value)" />
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <input type="text" class="nodrag w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-emerald-300" :value="secret.username || ''" :placeholder="$t('Username')" @input="setSecretValue('username', $event.target.value)" />
                    <input type="password" class="nodrag w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-emerald-300" :placeholder="secret.has_password ? $t('Password saved. Enter a new value only if you want to replace it.') : $t('Password')" @input="setSecretValue('password', $event.target.value)" />
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <select class="nodrag w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-emerald-300" :value="secret.encryption || 'tls'" @change="setSecretValue('encryption', $event.target.value)">
                        <option value="tls">{{ $t('TLS') }}</option>
                        <option value="ssl">{{ $t('SSL') }}</option>
                        <option value="none">{{ $t('None') }}</option>
                    </select>
                    <input type="text" class="nodrag w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-emerald-300" :value="secret.from_name || ''" :placeholder="$t('From Name')" @input="setSecretValue('from_name', $event.target.value)" />
                </div>
                <input type="email" class="nodrag w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-emerald-300" :value="secret.from_email || ''" :placeholder="$t('From Email')" @input="setSecretValue('from_email', $event.target.value)" />
                <label class="flex items-center gap-3 text-sm font-medium text-slate-700">
                    <input type="checkbox" class="nodrag h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-200" :checked="Boolean(config.active ?? true)" @change="setConfigValue('active', $event.target.checked)" />
                    {{ $t('Active') }}
                </label>
            </div>
        </details>
    </div>

    <div v-else-if="nodeType === 'delay'" class="grid gap-4">
        <label class="block">
            <div class="mb-2 text-sm font-semibold text-slate-900">{{ $t('Duration (minutes)') }}</div>
            <input type="number" min="1" class="nodrag w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-emerald-300" :value="config.minutes || 5" @input="setConfigValue('minutes', normalizeInteger($event.target.value, 5))" />
        </label>

        <details class="nodrag rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
            <summary class="nodrag cursor-pointer list-none text-sm font-semibold text-slate-900">
                {{ $t('Advanced settings') }}
            </summary>
            <label class="mt-3 flex items-center gap-3 text-sm font-medium text-slate-700">
                <input type="checkbox" class="nodrag h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-200" :checked="Boolean(config.active ?? true)" @change="setConfigValue('active', $event.target.checked)" />
                {{ $t('Active') }}
            </label>
        </details>
    </div>

    <div v-else class="grid gap-4">
        <label class="block">
            <div class="mb-2 text-sm font-semibold text-slate-900">{{ $t('URL') }}</div>
            <input type="url" class="nodrag w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-emerald-300" :value="config.url || ''" :placeholder="$t('Paste the destination URL')" @input="setConfigValue('url', $event.target.value)" />
        </label>

        <label class="block">
            <div class="mb-2 text-sm font-semibold text-slate-900">{{ $t('Method') }}</div>
            <select class="nodrag w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-emerald-300" :value="config.method || 'POST'" @change="setConfigValue('method', $event.target.value)">
                <option value="POST">{{ $t('POST') }}</option>
                <option value="GET">{{ $t('GET') }}</option>
                <option value="PUT">{{ $t('PUT') }}</option>
                <option value="PATCH">{{ $t('PATCH') }}</option>
                <option value="DELETE">{{ $t('DELETE') }}</option>
            </select>
        </label>

        <details class="nodrag rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
            <summary class="nodrag cursor-pointer list-none text-sm font-semibold text-slate-900">
                {{ $t('Advanced settings') }}
            </summary>
            <label class="mt-3 flex items-center gap-3 text-sm font-medium text-slate-700">
                <input type="checkbox" class="nodrag h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-200" :checked="Boolean(config.active ?? true)" @change="setConfigValue('active', $event.target.checked)" />
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
