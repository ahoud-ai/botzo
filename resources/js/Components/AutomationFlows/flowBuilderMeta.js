import {
    Bot,
    Circle,
    CircleStop,
    Clock3,
    Database,
    GitBranch,
    Headset,
    List,
    Mail,
    MessageSquareText,
    MousePointerClick,
    Paperclip,
    PencilLine,
    UserCheck,
    UserMinus,
    UserPlus,
    Workflow,
} from 'lucide-vue-next';

const FALLBACK_ICON = Circle;
const SUMMARY_MAX_LENGTH = 56;

const NODE_ICONS = {
    Bot,
    Circle,
    CircleStop,
    Clock3,
    Database,
    GitBranch,
    Headset,
    List,
    Mail,
    MessageSquareText,
    MousePointerClick,
    Paperclip,
    PencilLine,
    UserCheck,
    UserMinus,
    UserPlus,
    Workflow,
};

const NODE_META = {
    trigger: {
        labelKey: 'flow_builder.node.trigger',
        defaultLabel: 'Trigger',
        categoryKey: 'flow_builder.category.trigger',
        defaultCategory: 'Trigger',
        icon: 'Workflow',
    },
    send_text: {
        labelKey: 'flow_builder.node.send_text',
        defaultLabel: 'WhatsApp message',
        categoryKey: 'flow_builder.category.messages',
        defaultCategory: 'Messages',
        icon: 'MessageSquareText',
    },
    send_media: {
        labelKey: 'flow_builder.node.send_media',
        defaultLabel: 'Media message',
        categoryKey: 'flow_builder.category.messages',
        defaultCategory: 'Messages',
        icon: 'Paperclip',
    },
    send_buttons: {
        labelKey: 'flow_builder.node.send_buttons',
        defaultLabel: 'Button choices',
        categoryKey: 'flow_builder.category.messages',
        defaultCategory: 'Messages',
        icon: 'MousePointerClick',
    },
    send_list: {
        labelKey: 'flow_builder.node.send_list',
        defaultLabel: 'List choices',
        categoryKey: 'flow_builder.category.messages',
        defaultCategory: 'Messages',
        icon: 'List',
    },
    save_reply_to_field: {
        labelKey: 'flow_builder.node.save_reply_to_field',
        defaultLabel: 'Save answer',
        categoryKey: 'flow_builder.category.actions',
        defaultCategory: 'Actions',
        icon: 'Database',
    },
    condition: {
        labelKey: 'flow_builder.node.condition',
        defaultLabel: 'Route rule',
        categoryKey: 'flow_builder.category.actions',
        defaultCategory: 'Actions',
        icon: 'GitBranch',
    },
    add_to_group: {
        labelKey: 'flow_builder.node.add_to_group',
        defaultLabel: 'Add to Group',
        categoryKey: 'flow_builder.category.actions',
        defaultCategory: 'Actions',
        icon: 'UserPlus',
    },
    remove_from_group: {
        labelKey: 'flow_builder.node.remove_from_group',
        defaultLabel: 'Remove from Group',
        categoryKey: 'flow_builder.category.actions',
        defaultCategory: 'Actions',
        icon: 'UserMinus',
    },
    update_contact_field: {
        labelKey: 'flow_builder.node.update_contact_field',
        defaultLabel: 'Update Contact',
        categoryKey: 'flow_builder.category.actions',
        defaultCategory: 'Actions',
        icon: 'PencilLine',
    },
    assign_to_agent: {
        labelKey: 'flow_builder.node.assign_to_agent',
        defaultLabel: 'Assign agent',
        categoryKey: 'flow_builder.category.actions',
        defaultCategory: 'Actions',
        icon: 'UserCheck',
    },
    human_handoff: {
        labelKey: 'flow_builder.node.human_handoff',
        defaultLabel: 'Human takeover',
        categoryKey: 'flow_builder.category.actions',
        defaultCategory: 'Actions',
        icon: 'Headset',
    },
    handoff_to_ai_assistant: {
        labelKey: 'flow_builder.node.handoff_to_ai_assistant',
        defaultLabel: 'AI takeover',
        categoryKey: 'flow_builder.category.actions',
        defaultCategory: 'Actions',
        icon: 'Bot',
    },
    send_email: {
        labelKey: 'flow_builder.node.send_email',
        defaultLabel: 'Send Email',
        categoryKey: 'flow_builder.category.actions',
        defaultCategory: 'Actions',
        icon: 'Mail',
    },
    delay: {
        labelKey: 'flow_builder.node.delay',
        defaultLabel: 'Wait',
        categoryKey: 'flow_builder.category.actions',
        defaultCategory: 'Actions',
        icon: 'Clock3',
    },
    end: {
        labelKey: 'flow_builder.node.end',
        defaultLabel: 'End flow',
        categoryKey: 'flow_builder.category.actions',
        defaultCategory: 'Actions',
        icon: 'CircleStop',
    },
};

export const flowNodeMeta = NODE_META;

// Each node "tone" is derived from the app's existing --ui-* design tokens via
// color-mix(), never raw hex, so the flow builder automatically follows the
// same light/dark palette as the rest of the dashboard. --flow-tone-* custom
// properties are defined once in flowBuilderCanvas.css and shared with the
// edge tones in flowBuilderStudio.js so node color and branch color stay in
// sync from a single source of truth.
const VISUAL_TONES = {
    trigger: {
        railClass: 'bg-[linear-gradient(90deg,var(--flow-tone-trigger),color-mix(in_srgb,var(--flow-tone-trigger)_55%,var(--ui-surface)))]',
        iconClass: 'border-[color-mix(in_srgb,var(--flow-tone-trigger)_35%,var(--ui-border))] bg-[color-mix(in_srgb,var(--flow-tone-trigger)_14%,var(--ui-surface))] text-[var(--flow-tone-trigger)] shadow-[0_10px_24px_color-mix(in_srgb,var(--flow-tone-trigger)_20%,transparent)]',
        accentClass: 'border-[color-mix(in_srgb,var(--flow-tone-trigger)_30%,var(--ui-border))] bg-[color-mix(in_srgb,var(--flow-tone-trigger)_8%,var(--ui-surface))] text-[var(--ui-text)]',
        categoryClass: 'border-[color-mix(in_srgb,var(--flow-tone-trigger)_30%,var(--ui-border))] bg-[var(--ui-surface)] text-[var(--flow-tone-trigger)]',
        handleClass: '!bg-[var(--flow-tone-trigger)]',
    },
    message: {
        railClass: 'bg-[linear-gradient(90deg,var(--flow-tone-message),color-mix(in_srgb,var(--flow-tone-message)_55%,var(--ui-surface)))]',
        iconClass: 'border-[color-mix(in_srgb,var(--flow-tone-message)_35%,var(--ui-border))] bg-[color-mix(in_srgb,var(--flow-tone-message)_14%,var(--ui-surface))] text-[var(--flow-tone-message)] shadow-[0_10px_24px_color-mix(in_srgb,var(--flow-tone-message)_20%,transparent)]',
        accentClass: 'border-[color-mix(in_srgb,var(--flow-tone-message)_30%,var(--ui-border))] bg-[color-mix(in_srgb,var(--flow-tone-message)_8%,var(--ui-surface))] text-[var(--ui-text)]',
        categoryClass: 'border-[color-mix(in_srgb,var(--flow-tone-message)_30%,var(--ui-border))] bg-[var(--ui-surface)] text-[var(--flow-tone-message)]',
        handleClass: '!bg-[var(--flow-tone-message)]',
    },
    logic: {
        railClass: 'bg-[linear-gradient(90deg,var(--flow-tone-branch),color-mix(in_srgb,var(--flow-tone-branch)_55%,var(--ui-surface)))]',
        iconClass: 'border-[color-mix(in_srgb,var(--flow-tone-branch)_35%,var(--ui-border))] bg-[color-mix(in_srgb,var(--flow-tone-branch)_14%,var(--ui-surface))] text-[var(--flow-tone-branch)] shadow-[0_10px_24px_color-mix(in_srgb,var(--flow-tone-branch)_20%,transparent)]',
        accentClass: 'border-[color-mix(in_srgb,var(--flow-tone-branch)_30%,var(--ui-border))] bg-[color-mix(in_srgb,var(--flow-tone-branch)_8%,var(--ui-surface))] text-[var(--ui-text)]',
        categoryClass: 'border-[color-mix(in_srgb,var(--flow-tone-branch)_30%,var(--ui-border))] bg-[var(--ui-surface)] text-[var(--flow-tone-branch)]',
        handleClass: '!bg-[var(--flow-tone-branch)]',
    },
    action: {
        railClass: 'bg-[linear-gradient(90deg,var(--flow-tone-action),color-mix(in_srgb,var(--flow-tone-action)_55%,var(--ui-surface)))]',
        iconClass: 'border-[color-mix(in_srgb,var(--flow-tone-action)_35%,var(--ui-border))] bg-[color-mix(in_srgb,var(--flow-tone-action)_14%,var(--ui-surface))] text-[var(--flow-tone-action)] shadow-[0_10px_24px_color-mix(in_srgb,var(--flow-tone-action)_20%,transparent)]',
        accentClass: 'border-[color-mix(in_srgb,var(--flow-tone-action)_30%,var(--ui-border))] bg-[color-mix(in_srgb,var(--flow-tone-action)_8%,var(--ui-surface))] text-[var(--ui-text)]',
        categoryClass: 'border-[color-mix(in_srgb,var(--flow-tone-action)_30%,var(--ui-border))] bg-[var(--ui-surface)] text-[var(--flow-tone-action)]',
        handleClass: '!bg-[var(--flow-tone-action)]',
    },
    handoff: {
        railClass: 'bg-[linear-gradient(90deg,var(--flow-tone-handoff),color-mix(in_srgb,var(--flow-tone-handoff)_55%,var(--ui-surface)))]',
        iconClass: 'border-[color-mix(in_srgb,var(--flow-tone-handoff)_35%,var(--ui-border))] bg-[color-mix(in_srgb,var(--flow-tone-handoff)_14%,var(--ui-surface))] text-[var(--flow-tone-handoff)] shadow-[0_10px_24px_color-mix(in_srgb,var(--flow-tone-handoff)_22%,transparent)]',
        accentClass: 'border-[color-mix(in_srgb,var(--flow-tone-handoff)_30%,var(--ui-border))] bg-[color-mix(in_srgb,var(--flow-tone-handoff)_9%,var(--ui-surface))] text-[var(--ui-text)]',
        categoryClass: 'border-[color-mix(in_srgb,var(--flow-tone-handoff)_30%,var(--ui-border))] bg-[var(--ui-surface)] text-[var(--flow-tone-handoff)]',
        handleClass: '!bg-[var(--flow-tone-handoff)]',
    },
    end: {
        railClass: 'bg-[linear-gradient(90deg,var(--ui-muted),color-mix(in_srgb,var(--ui-muted)_55%,var(--ui-surface)))]',
        iconClass: 'border-[var(--ui-border-strong)] bg-[var(--ui-surface-soft)] text-[var(--ui-muted)] shadow-[0_10px_24px_color-mix(in_srgb,var(--ui-muted)_16%,transparent)]',
        accentClass: 'border-[var(--ui-border)] bg-[var(--ui-surface-soft)] text-[var(--ui-text)]',
        categoryClass: 'border-[var(--ui-border)] bg-[var(--ui-surface)] text-[var(--ui-muted)]',
        handleClass: '!bg-[var(--ui-muted)]',
    },
    fallback: {
        railClass: 'bg-[linear-gradient(90deg,var(--ui-muted),color-mix(in_srgb,var(--ui-muted)_45%,var(--ui-surface)))]',
        iconClass: 'border-[var(--ui-border)] bg-[var(--ui-surface-soft)] text-[var(--ui-muted)] shadow-[0_10px_24px_color-mix(in_srgb,var(--ui-muted)_12%,transparent)]',
        accentClass: 'border-[var(--ui-border)] bg-[var(--ui-surface-soft)] text-[var(--ui-text)]',
        categoryClass: 'border-[var(--ui-border)] bg-[var(--ui-surface)] text-[var(--ui-muted)]',
        handleClass: '!bg-[var(--ui-muted)]',
    },
};

const resolveToneKey = (type) => {
    if (type === 'trigger') {
        return 'trigger';
    }

    if (['send_text', 'send_media', 'send_buttons', 'send_list'].includes(type)) {
        return 'message';
    }

    if (['save_reply_to_field', 'condition'].includes(type)) {
        return 'logic';
    }

    if (['assign_to_agent', 'human_handoff', 'handoff_to_ai_assistant'].includes(type)) {
        return 'handoff';
    }

    if (['add_to_group', 'remove_from_group', 'update_contact_field', 'send_email', 'delay'].includes(type)) {
        return 'action';
    }

    if (type === 'end') {
        return 'end';
    }

    return 'fallback';
};

export const flowNodeLabel = (type, t) => {
    const fallback = NODE_META[type]?.defaultLabel || type;
    const labelKey = NODE_META[type]?.labelKey || null;

    if (!t || !labelKey) {
        return fallback;
    }

    const translated = t(labelKey);

    return translated === labelKey ? fallback : translated;
};

export const resolveFlowNodeTitle = (source = {}, t = (value) => value) => {
    const type = String(source?.nodeType || source?.type || '').trim();
    const rawTitle = String(source?.title || source?.label || '').trim();
    const canonicalLabel = type ? flowNodeLabel(type, t) : rawTitle;
    const meta = NODE_META[type] || null;

    if (rawTitle === '') {
        return canonicalLabel || '';
    }

    if (
        rawTitle === type
        || rawTitle === meta?.labelKey
        || rawTitle === meta?.defaultLabel
    ) {
        return canonicalLabel || rawTitle;
    }

    const translated = t(rawTitle);

    if (translated !== rawTitle) {
        return translated;
    }

    return rawTitle;
};

export const flowNodeCategory = (type, t) => {
    const fallback = NODE_META[type]?.defaultCategory || 'Automation';
    const categoryKey = NODE_META[type]?.categoryKey || null;

    if (!t || !categoryKey) {
        return fallback;
    }

    const translated = t(categoryKey);

    return translated === categoryKey ? fallback : translated;
};

export const flowNodeIcon = (type) => {
    const iconName = NODE_META[type]?.icon;

    return iconName ? NODE_ICONS[iconName] || FALLBACK_ICON : FALLBACK_ICON;
};

export const flowNodeVisualTone = (type) => VISUAL_TONES[resolveToneKey(type)] || VISUAL_TONES.fallback;

const normalizeSummaryText = (value) => String(value || '')
    .replace(/\s+/g, ' ')
    .trim();

const truncateSummary = (value, maxLength = SUMMARY_MAX_LENGTH) => {
    const normalized = normalizeSummaryText(value);

    if (normalized.length <= maxLength) {
        return normalized;
    }

    return `${normalized.slice(0, Math.max(0, maxLength - 1)).trimEnd()}…`;
};

export const flowNodeSummary = (type, config = {}, options = {}, t = (value) => value) => {
    switch (type) {
        case 'trigger':
            return t('Start this flow from a matching incoming message.');
        case 'send_text':
            return truncateSummary(config.text || '');
        case 'send_media':
            return truncateSummary(options.asset?.original_name || config.caption || '');
        case 'send_buttons':
            return truncateSummary(config.body || config.buttons?.[0]?.title || '');
        case 'send_list':
            return truncateSummary(config.body || config.button_label || '');
        case 'save_reply_to_field':
            return t('Wait for a reply and save it.');
        case 'condition':
            return t('Split the journey by one rule.');
        case 'add_to_group':
            return t('Add the contact to a group.');
        case 'remove_from_group':
            return t('Remove the contact from a group.');
        case 'update_contact_field':
            return t('Update one saved contact value.');
        case 'assign_to_agent':
            return t('Route the conversation to an agent.');
        case 'human_handoff':
            return t('Hand the conversation to a human.');
        case 'handoff_to_ai_assistant':
            return t('Hand the conversation to the AI assistant.');
        case 'send_email':
            return truncateSummary(config.subject || '');
        case 'delay':
            return t('Pause before the next step.');
        case 'end':
            return t('Finish this flow.');
        default:
            return '';
    }
};

export const flowNodeCustomerOutcome = (type, config = {}, options = {}, t = (value) => value) => {
    switch (type) {
        case 'trigger':
            return t('Decide exactly which incoming WhatsApp moment should open this journey.');
        case 'send_text':
            return truncateSummary(config.text || t('The customer receives one clear WhatsApp reply that moves the conversation forward.'), 120);
        case 'send_media':
            return truncateSummary(options.asset?.original_name || t('The customer receives a media asset with context that supports the next action.'), 120);
        case 'send_buttons':
            return truncateSummary(config.body || t('The customer sees short reply buttons and can move forward with one tap.'), 120);
        case 'send_list':
            return truncateSummary(config.body || t('The customer sees a scannable menu and chooses the right path from the list.'), 120);
        case 'save_reply_to_field':
            return t('The journey pauses here until the customer answers, then saves the response for the next step.');
        case 'condition':
            return t('The journey checks one rule and sends the customer to the correct route automatically.');
        case 'add_to_group':
            return t('The contact is added to a segment so your team can target them later.');
        case 'remove_from_group':
            return t('The contact is removed from a segment that no longer fits this journey.');
        case 'update_contact_field':
            return t('A customer detail is updated so later automations and the team can act on it.');
        case 'assign_to_agent':
            return t('A ticket is created and the conversation is handed to the team without losing context.');
        case 'human_handoff':
            return t('Automation stops here so a human teammate can take full control of the conversation.');
        case 'handoff_to_ai_assistant':
            return t('Automation stops here and the built-in AI assistant continues the conversation.');
        case 'send_email':
            return truncateSummary(config.subject || t('An email is sent outside WhatsApp to support the journey.'), 120);
        case 'delay':
            return t('The journey waits before the next step, keeping the timing natural for the customer.');
        case 'end':
            return t('The journey finishes cleanly with no further automated action.');
        default:
            return t('This step changes the customer journey and should stay easy to understand at a glance.');
    }
};

export const flowNodeHasActiveToggle = (type) => [
    'add_to_group',
    'remove_from_group',
    'update_contact_field',
    'send_email',
    'delay',
].includes(type);
