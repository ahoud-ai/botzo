<template>
    <section class="flex h-full min-h-0 flex-col overflow-hidden rounded-[22px] border border-slate-200 bg-white shadow-sm">
        <template v-if="hasNode">
            <div class="border-b border-slate-200 px-4 py-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <span class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em]" :class="tone.categoryClass">
                            {{ nodeCategory }}
                        </span>
                        <div class="mt-3 truncate text-base font-semibold text-slate-950">{{ nodeTitle }}</div>
                    </div>
                    <span
                        class="inline-flex shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold"
                        :class="coachStatusClass"
                    >
                        {{ coachStatusLabel }}
                    </span>
                </div>
                <div class="mt-3 rounded-[18px] border px-3.5 py-3" :class="tone.accentClass">
                    <div class="text-[10px] font-semibold uppercase tracking-[0.16em] opacity-75">{{ $t('Customer outcome') }}</div>
                    <div class="mt-2 line-clamp-3 text-sm font-semibold leading-6">{{ customerOutcome }}</div>
                </div>
            </div>

            <div class="min-h-0 flex-1 space-y-3 overflow-y-auto bg-slate-50/55 p-3">
                <div class="rounded-[20px] border px-4 py-4" :class="focusCardClass">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] opacity-75">{{ $t('Focus now') }}</div>
                    <div class="mt-2 text-sm font-semibold leading-6">{{ guideFocusMessage }}</div>
                </div>

                <div class="rounded-[20px] border border-slate-200 bg-white px-4 py-4">
                    <div class="flex items-center justify-between gap-3">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $t('What to confirm') }}</div>
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[10px] font-semibold text-slate-500">
                            {{ coachChecklist.length }}
                        </span>
                    </div>
                    <div class="mt-3 space-y-2">
                        <div v-for="(item, index) in coachChecklist" :key="`${item}-${index}`" class="flex gap-3 rounded-2xl border border-slate-100 bg-slate-50/70 px-3 py-2.5">
                            <span class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-white text-[10px] font-semibold text-slate-500">
                                {{ index + 1 }}
                            </span>
                            <span class="text-sm leading-6 text-slate-700">{{ item }}</span>
                        </div>
                    </div>
                </div>

                <div v-if="recommendedNextSteps.length" class="rounded-[20px] border border-slate-200 bg-white px-4 py-4">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $t('Next best move') }}</div>
                    <div class="mt-2 text-sm leading-6 text-slate-600">{{ $t('Add the next step from here when the path is obvious and safe.') }}</div>
                    <div class="mt-3 grid gap-2">
                        <button
                            v-for="item in recommendedNextSteps"
                            :key="item.type"
                            type="button"
                            class="inline-flex w-full items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-white hover:text-slate-950"
                            @click="$emit('quick-add', item.type)"
                        >
                            <span>{{ item.label }}</span>
                            <span class="text-lg leading-none">+</span>
                        </button>
                    </div>
                </div>

                <div v-if="nodeSummary" class="rounded-[20px] border border-slate-200 bg-white px-4 py-3.5">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $t('Step summary') }}</div>
                    <div class="mt-2 text-sm leading-6 text-slate-700">{{ nodeSummary }}</div>
                </div>
            </div>
        </template>

        <template v-else>
            <div class="flex h-full min-h-[260px] items-center justify-center bg-slate-50/55 px-5 text-center">
                <div class="max-w-[280px] rounded-[24px] border border-dashed border-slate-300 bg-white px-5 py-8">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 text-slate-500">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none">
                            <path d="M12 20h9M12 4h9M4 9h16M4 15h16M4 4h.01M4 20h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </div>
                    <div class="mt-4 text-sm font-semibold text-slate-950">{{ $t('Pick a step to get guided help') }}</div>
                    <div class="mt-2 text-sm leading-6 text-slate-500">{{ $t('Select any step to see the customer outcome, what to confirm, and the safest next move.') }}</div>
                </div>
            </div>
        </template>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    flowNodeCategory,
    flowNodeCustomerOutcome,
    flowNodeLabel,
    flowNodeSummary,
    flowNodeVisualTone,
} from '@/Components/AutomationFlows/flowBuilderMeta.js';
import { translateFlowMessage } from '@/Components/AutomationFlows/flowBuilderCopy.js';

defineEmits(['quick-add']);

const props = defineProps({
    node: {
        type: Object,
        default: null,
    },
    library: {
        type: Array,
        default: () => [],
    },
});

const { t } = useI18n();

const hasNode = computed(() => Boolean(props.node?.id));
const nodeType = computed(() => String(props.node?.data?.nodeType || ''));
const nodeConfig = computed(() => props.node?.data?.config || {});
const blockers = computed(() => Array.isArray(props.node?.data?.errors) ? props.node.data.errors : []);
const nodeTitle = computed(() => props.node?.data?.title || props.node?.data?.label || t('Selected step'));
const nodeCategory = computed(() => flowNodeCategory(nodeType.value, t));
const tone = computed(() => flowNodeVisualTone(nodeType.value));
const nodeSummary = computed(() => flowNodeSummary(
    nodeType.value,
    nodeConfig.value,
    {
        asset: props.node?.data?.asset || null,
    },
    t,
));
const customerOutcome = computed(() => flowNodeCustomerOutcome(
    nodeType.value,
    nodeConfig.value,
    {
        asset: props.node?.data?.asset || null,
    },
    t,
));
const recommendedFocus = computed(() => ({
    trigger: t('Choose the exact moment that should start this journey, then connect the first real step.'),
    send_text: t('Keep this reply short, useful, and focused on the next action you want from the customer.'),
    send_media: t('Make sure the file is the final version and the next step still fits what the customer just saw.'),
    send_buttons: t('Keep every choice short and obvious, then confirm that every button is linked.'),
    send_list: t('Make the list easy to scan and make sure each row continues somewhere useful.'),
    save_reply_to_field: t('Save only the answer your team will really use later in the journey.'),
    update_contact_field: t('Keep the saved value simple so reporting, routing, and follow-up stay clear.'),
    condition: t('Keep the rule easy to explain and make sure both outcomes continue somewhere intentional.'),
    assign_to_agent: t('Use this when the team is genuinely ready to take over without losing context.'),
    human_handoff: t('Use this only when automation must stop completely and a human should own the next reply.'),
    handoff_to_ai_assistant: t('Use this only when the AI assistant is active and trusted to continue the conversation.'),
    send_email: t('Use this only when the email content and SMTP setup are already production-ready.'),
    delay: t('Keep the delay natural for the customer and short enough that the journey still feels responsive.'),
    end: t('Use the ending point only when no more automated follow-up should happen after this step.'),
})[nodeType.value] || t('Finish configuring this step before you publish.'));
const guideFocusMessage = computed(() => blockers.value[0] || recommendedFocus.value);
const checklistForNode = (type) => ({
    trigger: [
        t('The first connected step matches the customer journey you actually want to launch.'),
        t('Keyword or match rules are narrow enough that the wrong chats will not start this flow.'),
    ],
    send_text: [
        t('The message is easy to scan on WhatsApp and leads naturally to the next step.'),
        t('The next action is clear even if the customer reads this quickly on mobile.'),
    ],
    send_media: [
        t('The uploaded file is the final asset your team wants customers to receive.'),
        t('The caption and follow-up still make sense if the media is opened later.'),
    ],
    send_buttons: [
        t('Each button is short enough to scan instantly on mobile.'),
        t('Every button continues to a meaningful next step.'),
    ],
    send_list: [
        t('The list rows are easy to compare quickly.'),
        t('Each row leads to the right follow-up path.'),
    ],
    save_reply_to_field: [
        t('The saved answer will be useful for routing, segmentation, or the team later.'),
        t('The next step clearly uses the reply the customer just sent.'),
    ],
    update_contact_field: [
        t('The saved value is simple enough to stay reliable in CRM usage later.'),
        t('The new value will not confuse other automations or agents.'),
    ],
    condition: [
        t('Both condition outcomes lead somewhere intentional.'),
        t('The rule uses the right source and a value the team will understand later.'),
    ],
    assign_to_agent: [
        t('The receiving team can immediately understand why the conversation reached them.'),
        t('The path after assignment still matches the support experience you want.'),
    ],
    human_handoff: [
        t('Stopping automation here is the safest moment for a human to continue.'),
        t('The team will know exactly what happened before the handoff.'),
    ],
    handoff_to_ai_assistant: [
        t('The AI assistant has enough context to continue without confusing the customer.'),
        t('Stopping this flow here will not leave any important path unfinished.'),
    ],
    send_email: [
        t('The email subject and content are ready for real customers.'),
        t('SMTP details are saved and tested before launch.'),
    ],
    delay: [
        t('The wait time feels natural in the real customer experience.'),
        t('The next step still makes sense after the chosen delay.'),
    ],
    end: [
        t('The journey truly ends here with no missing follow-up.'),
        t('The customer is not left waiting for another response after this point.'),
    ],
})[type] || [
    t('This step still matches the customer experience you want to launch.'),
    t('The next path is clear enough that another teammate could explain it quickly.'),
];
const coachChecklist = computed(() => (
    blockers.value.length
        ? blockers.value.slice(0, 3)
        : checklistForNode(nodeType.value)
));
const coachStatusLabel = computed(() => (
    blockers.value.length
        ? translateFlowMessage(t, ':count blocker(s)', { count: blockers.value.length })
        : t('Ready for launch')
));
const coachStatusClass = computed(() => (
    blockers.value.length
        ? 'border border-rose-200 bg-rose-50 text-rose-700'
        : 'border border-emerald-200 bg-emerald-50 text-emerald-700'
));
const focusCardClass = computed(() => (
    blockers.value.length
        ? 'border-rose-200 bg-rose-50 text-rose-900'
        : 'border-slate-200 bg-slate-950 text-white'
));

const isSafeQuickAddContext = computed(() => {
    if (!hasNode.value) {
        return false;
    }

    if (['send_buttons', 'send_list', 'condition', 'human_handoff', 'handoff_to_ai_assistant', 'end'].includes(nodeType.value)) {
        return false;
    }

    if (nodeType.value === 'trigger') {
        return String(nodeConfig.value.starting_step || '').trim() === '';
    }

    return true;
});

const recommendedTypesForNode = (type) => ({
    trigger: ['send_text', 'send_buttons', 'send_list'],
    send_text: ['save_reply_to_field', 'condition', 'end'],
    send_media: ['save_reply_to_field', 'condition', 'end'],
    save_reply_to_field: ['condition', 'update_contact_field', 'assign_to_agent'],
    update_contact_field: ['condition', 'assign_to_agent', 'end'],
    add_to_group: ['send_text', 'assign_to_agent', 'end'],
    remove_from_group: ['send_text', 'assign_to_agent', 'end'],
    assign_to_agent: ['send_text', 'end'],
    send_email: ['delay', 'end'],
    delay: ['send_text', 'condition', 'end'],
})[type] || [];

const libraryTypes = computed(() => new Set((Array.isArray(props.library) ? props.library : []).map((item) => String(item?.type || ''))));

const recommendedNextSteps = computed(() => {
    if (!isSafeQuickAddContext.value) {
        return [];
    }

    return recommendedTypesForNode(nodeType.value)
        .filter((type) => libraryTypes.value.has(type))
        .map((type) => ({
            type,
            label: flowNodeLabel(type, t),
        }));
});
</script>
