const SYSTEM_BINDINGS = [
    'CURRENTSCHOOLYEAR',
    'USERSCHOOLIDS',
    'USERWISASCHOOLIDS',
    'USERWISAVIRTSCHOOLIDS',
];

/**
 * Centrale parsing/helpers voor query bindings.
 *
 * Gebruik deze composable als single source of truth voor:
 * - extractie van :bindings uit SQL
 * - detectie van systeembindings
 * - normalisatie en fallback-meta voor binding rows
 */

export function normalizeBindingName(value) {
    return String(value || '').trim();
}

export function isSystemBindingName(bindingName) {
    return SYSTEM_BINDINGS.includes(
        normalizeBindingName(bindingName).toUpperCase(),
    );
}

export function extractBindingNamesFromSql(sql) {
    const source = String(sql || '');
    const regex = /:([A-Za-z_][A-Za-z0-9_]*)/g;
    const found = new Set();
    let match = regex.exec(source);

    while (match) {
        const binding = normalizeBindingName(match[1]);

        if (binding !== '') {
            found.add(binding);
        }

        match = regex.exec(source);
    }

    return Array.from(found.values());
}

export function isMissingBindingValue(value) {
    if (value === null || value === undefined) {
        return true;
    }

    return String(value).trim() === '';
}

export function buildBindingMeta(bindingRows, bindingNames) {
    const rows = Array.isArray(bindingRows) ? bindingRows : [];
    const byParameter = new Map();

    rows.forEach((row) => {
        const parameter = normalizeBindingName(row?.parameter);
        const parameterTo = normalizeBindingName(row?.parameter_to);

        if (parameter !== '' && !byParameter.has(parameter)) {
            byParameter.set(parameter, row);
        }

        if (parameterTo !== '' && !byParameter.has(parameterTo)) {
            byParameter.set(parameterTo, row);
        }
    });

    const names = Array.isArray(bindingNames) ? bindingNames : [];

    return names
        .map((binding) => normalizeBindingName(binding))
        .filter((binding) => binding !== '' && !isSystemBindingName(binding))
        .map((binding) => {
            const row = byParameter.get(binding);
            const rowParameter = normalizeBindingName(row?.parameter);
            const rowParameterTo = normalizeBindingName(row?.parameter_to);
            const baseTitle = normalizeBindingName(row?.title);
            const title =
                baseTitle !== ''
                    ? rowParameterTo === binding && rowParameter !== binding
                        ? `${baseTitle} (tot)`
                        : baseTitle
                    : binding;

            return {
                parameter: binding,
                title,
                title_key: String(row?.title_key || row?.titleKey || ''),
                prompt: String(row?.prompt || ''),
                prompt_key: String(row?.prompt_key || row?.promptKey || ''),
                type: String(row?.type || 'text'),
                source_table_id: Number(row?.source_table_id || 0) || null,
            };
        });
}

export function resolveBindingRowsForRequired(
    bindingRows,
    requiredBindingNames,
) {
    const rows = Array.isArray(bindingRows) ? bindingRows : [];
    const requiredNames = Array.isArray(requiredBindingNames)
        ? requiredBindingNames
              .map((binding) => normalizeBindingName(binding))
              .filter(
                  (binding) => binding !== '' && !isSystemBindingName(binding),
              )
        : [];
    const requiredSet = new Set(requiredNames);

    const filteredRows = rows.filter((row) => {
        const parameter = normalizeBindingName(row?.parameter);
        const parameterTo = normalizeBindingName(row?.parameter_to);

        return requiredSet.has(parameter) || requiredSet.has(parameterTo);
    });

    const knownParameters = new Set(
        filteredRows.flatMap((row) => {
            const values = [];
            const parameter = normalizeBindingName(row?.parameter);
            const parameterTo = normalizeBindingName(row?.parameter_to);

            if (parameter !== '') {
                values.push(parameter);
            }

            if (parameterTo !== '') {
                values.push(parameterTo);
            }

            return values;
        }),
    );

    const fallbackRows = requiredNames
        .filter((binding) => !knownParameters.has(binding))
        .map((binding) => ({
            type: 'text',
            parameter: binding,
            parameter_to: '',
            title: binding,
            title_key: '',
            prompt: '',
            prompt_key: '',
        }));

    return [...filteredRows, ...fallbackRows];
}
