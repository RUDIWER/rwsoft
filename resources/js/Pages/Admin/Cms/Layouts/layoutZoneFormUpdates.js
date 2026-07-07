export function updatedLayoutSectionsForZone(
    currentSections,
    zone,
    zoneSections,
) {
    const sections =
        currentSections && typeof currentSections === 'object'
            ? currentSections
            : {};

    return {
        ...sections,
        [zone]: deepClone(Array.isArray(zoneSections) ? zoneSections : []),
    };
}

function deepClone(value) {
    if (typeof globalThis.structuredClone === 'function') {
        return globalThis.structuredClone(value);
    }

    return JSON.parse(JSON.stringify(value));
}
