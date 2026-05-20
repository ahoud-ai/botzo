const FLOW_INDEX_PATH = '/automation/flows';
const FLOW_UUID_REGEX = /[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}/ig;

export const extractFlowBuilderUuid = (value) => {
    if (typeof value !== 'string' || value.trim() === '') {
        return null;
    }

    const matches = value.trim().match(FLOW_UUID_REGEX);
    if (!Array.isArray(matches) || matches.length === 0) {
        return null;
    }

    return matches[matches.length - 1];
};

export const normalizeFlowBuilderRedirect = (value) => {
    if (typeof value !== 'string' || value.trim() === '') {
        return FLOW_INDEX_PATH;
    }

    const trimmed = value.trim();

    if (/^https?:\/\//i.test(trimmed)) {
        return trimmed;
    }

    const extractedUuid = extractFlowBuilderUuid(trimmed);
    if (extractedUuid) {
        return `${FLOW_INDEX_PATH}/${extractedUuid}`;
    }

    if (trimmed.startsWith(FLOW_INDEX_PATH)) {
        return trimmed;
    }

    if (trimmed.startsWith('/')) {
        return trimmed;
    }

    if (trimmed.startsWith('automation/flows')) {
        return `/${trimmed}`;
    }

    if (trimmed.startsWith('flows/')) {
        return `/automation/${trimmed}`;
    }

    return FLOW_INDEX_PATH;
};

export const resolveFlowBuilderDestination = (payload = {}) => {
    const flowUuid = extractFlowBuilderUuid(payload?.flow_uuid || payload?.uuid || payload?.id || '');
    if (flowUuid) {
        return `${FLOW_INDEX_PATH}/${flowUuid}`;
    }

    return normalizeFlowBuilderRedirect(payload?.redirect_to);
};

export { FLOW_INDEX_PATH };
