const FLOW_NODE_VISUALS = {
    start: {
        icon: 'play',
        shortIcon: 'ST',
        tone: 'start',
    },
    condition: {
        icon: 'condition',
        shortIcon: 'IF',
        tone: 'condition',
    },
    switch: {
        icon: 'switch',
        shortIcon: 'SW',
        tone: 'condition',
    },
    send_text: {
        icon: 'message',
        shortIcon: 'TXT',
        tone: 'messaging',
    },
    ask_question: {
        icon: 'question',
        shortIcon: 'Q',
        tone: 'messaging',
    },
    quick_reply_prompt: {
        icon: 'quick_reply',
        shortIcon: 'QR',
        tone: 'messaging',
    },
    send_template: {
        icon: 'template',
        shortIcon: 'TPL',
        tone: 'messaging',
    },
    wait: {
        icon: 'wait',
        shortIcon: 'WT',
        tone: 'wait',
    },
    set_contact_field: {
        icon: 'set_field',
        shortIcon: 'FLD',
        tone: 'contact',
    },
    get_contact_field: {
        icon: 'get_field',
        shortIcon: 'GET',
        tone: 'contact',
    },
    set_tag: {
        icon: 'set_tag',
        shortIcon: 'TAG',
        tone: 'contact',
    },
    remove_tag: {
        icon: 'remove_tag',
        shortIcon: 'UNT',
        tone: 'contact',
    },
    tag_contact: {
        icon: 'set_tag',
        shortIcon: 'TAG',
        tone: 'contact',
    },
    jump_to: {
        icon: 'jump',
        shortIcon: 'JMP',
        tone: 'control',
    },
    create_ticket: {
        icon: 'ticket',
        shortIcon: 'TKT',
        tone: 'service',
    },
    update_ticket_status: {
        icon: 'status',
        shortIcon: 'STS',
        tone: 'service',
    },
    human_handoff: {
        icon: 'handoff',
        shortIcon: 'HOF',
        tone: 'service',
    },
    assign_agent: {
        icon: 'assign',
        shortIcon: 'ASN',
        tone: 'service',
    },
    notify_team: {
        icon: 'notify',
        shortIcon: 'NTF',
        tone: 'operations',
    },
    log_event: {
        icon: 'log',
        shortIcon: 'LOG',
        tone: 'operations',
    },
    analytics_event: {
        icon: 'analytics',
        shortIcon: 'ANL',
        tone: 'operations',
    },
    ai_intent: {
        icon: 'ai_intent',
        shortIcon: 'AI',
        tone: 'ai',
    },
    ai_reply: {
        icon: 'ai_reply',
        shortIcon: 'AI',
        tone: 'ai',
    },
    stop: {
        icon: 'stop',
        shortIcon: 'SP',
        tone: 'stop',
    },
};

export const getFlowNodeVisual = (type) => FLOW_NODE_VISUALS[type] || {
    icon: 'node',
    shortIcon: 'ND',
    tone: 'default',
};

export const getFlowNodeTone = (type) => getFlowNodeVisual(type).tone || 'default';
