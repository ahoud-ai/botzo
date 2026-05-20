export const DEFAULT_API_DOC_REPLACEMENTS = {
    uuid: '00000000-0000-4000-8000-000000000000',
    id: '00000000-0000-4000-8000-000000000000',
    token: 'YOUR_API_TOKEN',
};

export function resolveApiDocExample(example, baseUrl, replacements = {}) {
    if (typeof example !== 'string' || example.length === 0) {
        return '';
    }

    const values = {
        base_url: String(baseUrl || '').replace(/\/$/, ''),
        ...DEFAULT_API_DOC_REPLACEMENTS,
        ...replacements,
    };

    return example.replace(/\{\{\s*([a-zA-Z_]+)\s*\}\}/g, (match, key) => {
        if (Object.prototype.hasOwnProperty.call(values, key)) {
            return values[key];
        }

        return match;
    });
}
