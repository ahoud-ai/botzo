const FLOW_PLACEHOLDER_PATTERN = /:([A-Za-z_]+)/g;

export const interpolateFlowMessage = (message, params = {}) => String(message || '').replace(
    FLOW_PLACEHOLDER_PATTERN,
    (placeholder, key) => (
        Object.prototype.hasOwnProperty.call(params, key)
            ? String(params[key] ?? '')
            : placeholder
    ),
);

export const translateFlowMessage = (t, key, params = {}) => {
    const translated = typeof t === 'function' ? t(key) : key;

    return interpolateFlowMessage(translated, params);
};
