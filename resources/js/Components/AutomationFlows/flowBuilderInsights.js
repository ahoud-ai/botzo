const normalizedNodes = (graph = {}) => (Array.isArray(graph?.nodes) ? graph.nodes : []);

const countInteractiveButtons = (nodes = []) => nodes
    .filter((node) => node?.type === 'send_buttons')
    .reduce((carry, node) => carry + (Array.isArray(node?.config?.buttons) ? node.config.buttons.filter((button) => button?.id && button?.title).length : 0), 0);

const countInteractiveListChoices = (nodes = []) => nodes
    .filter((node) => node?.type === 'send_list')
    .reduce((carry, node) => carry + (Array.isArray(node?.config?.sections)
        ? node.config.sections.reduce((sectionCarry, section) => (
            sectionCarry + (Array.isArray(section?.rows) ? section.rows.filter((row) => row?.id && row?.title).length : 0)
        ), 0)
        : 0), 0);

const countReplyCaptureSteps = (nodes = []) => nodes.filter((node) => {
    if (node?.type === 'save_reply_to_field') {
        return true;
    }

    if (node?.type !== 'update_contact_field') {
        return false;
    }

    const mode = String(node?.config?.mode || node?.config?.value_mode || 'save_last_user_message');

    return ['save_last_user_message', 'last_input'].includes(mode);
}).length;

export const collectFlowInsights = ({
    graph = {},
    planLimits = {},
    validation = {},
    t,
}) => {
    const nodes = normalizedNodes(graph);
    const nodeCount = nodes.length;
    const buttonChoices = countInteractiveButtons(nodes);
    const listChoices = countInteractiveListChoices(nodes);
    const conditionSteps = nodes.filter((node) => node?.type === 'condition').length;
    const replyCaptureSteps = countReplyCaptureSteps(nodes);
    const blockers = Array.isArray(validation?.errors) ? validation.errors : [];
    const warnings = Array.isArray(validation?.warnings) ? validation.warnings : [];
    const nodesPerFlowLimit = Number(planLimits?.nodes_per_flow ?? -1);
    const totalChoices = buttonChoices + listChoices;

    const suggestions = [];

    if (blockers.length) {
        suggestions.push(t('Fix the blockers before publishing.'));
    }

    if (buttonChoices + listChoices > 0) {
        suggestions.push(t('Test every customer choice at least once.'));
    }

    if (conditionSteps > 0) {
        suggestions.push(t('Test both matched and unmatched branches.'));
    }

    if (replyCaptureSteps > 0) {
        suggestions.push(t('Use realistic customer replies when testing saved fields.'));
    }

    if (!suggestions.length) {
        suggestions.push(t('Run one quick preview, then publish when ready.'));
    }

    const primaryAction = blockers.length
        ? blockers[0]
        : totalChoices > 0
            ? t('Tap every customer choice once in preview.')
            : conditionSteps > 0
                ? t('Run both condition outcomes before publishing.')
                : replyCaptureSteps > 0
                    ? t('Send one realistic customer reply and confirm the saved value.')
                    : t('Run one quick preview, then publish when ready.');

    return {
        nodeCount,
        blockers,
        warnings,
        buttonChoices,
        listChoices,
        conditionSteps,
        replyCaptureSteps,
        totalChoices,
        interactiveSteps: nodes.filter((node) => ['send_buttons', 'send_list'].includes(node?.type)).length,
        nodesWithinPlanLimit: nodesPerFlowLimit < 0 || nodeCount <= nodesPerFlowLimit,
        primaryAction,
        suggestions,
    };
};
