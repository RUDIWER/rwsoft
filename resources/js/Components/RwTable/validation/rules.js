function isEmptyValue(value) {
    if (value === null || value === undefined) {
        return true;
    }

    if (typeof value === 'string') {
        return value.trim() === '';
    }

    if (Array.isArray(value)) {
        return value.length === 0;
    }

    return false;
}

function toNumber(value) {
    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : null;
    }

    if (typeof value === 'string') {
        const normalized = value.trim();

        if (normalized === '') {
            return null;
        }

        const parsed = Number(normalized);
        return Number.isFinite(parsed) ? parsed : null;
    }

    return null;
}

function toInteger(value) {
    const parsed = toNumber(value);

    if (parsed === null) {
        return null;
    }

    return Number.isInteger(parsed) ? parsed : null;
}

function replaceTokens(template, replacements = {}) {
    return Object.entries(replacements).reduce(
        (carry, [token, replacement]) => {
            return carry.replaceAll(`:${token}`, String(replacement ?? ''));
        },
        String(template || ''),
    );
}

function translate(options, key, fallback, replacements = {}) {
    if (typeof options?.translate === 'function') {
        const translated = options.translate(key, fallback, replacements);

        if (typeof translated === 'string' && translated.trim() !== '') {
            return translated;
        }
    }

    return replaceTokens(fallback, replacements);
}

function normalizeRuleEntry(rule) {
    if (typeof rule !== 'string') {
        return [];
    }

    return rule
        .split('|')
        .map((entry) => entry.trim())
        .filter((entry) => entry !== '');
}

export function normalizeRuleTokens(rules) {
    if (!rules) {
        return [];
    }

    if (Array.isArray(rules)) {
        return rules.flatMap((entry) => normalizeRuleTokens(entry));
    }

    if (typeof rules === 'string') {
        return normalizeRuleEntry(rules);
    }

    return [];
}

function parseRuleToken(token) {
    const normalizedToken = String(token || '').trim();

    if (normalizedToken === '') {
        return {
            name: '',
            parameters: [],
        };
    }

    const separatorIndex = normalizedToken.indexOf(':');

    if (separatorIndex === -1) {
        return {
            name: normalizedToken.toLowerCase(),
            parameters: [],
        };
    }

    const name = normalizedToken.slice(0, separatorIndex).trim().toLowerCase();
    const rawParameters = normalizedToken.slice(separatorIndex + 1);

    if (name === 'regex' || name === 'not_regex') {
        return {
            name,
            parameters: [rawParameters],
        };
    }

    return {
        name,
        parameters: rawParameters.split(',').map((part) => part.trim()),
    };
}

function validationMessage(
    key,
    fallback,
    fieldLabel,
    options,
    replacements = {},
) {
    return translate(options, key, fallback, {
        attribute: fieldLabel,
        ...replacements,
    });
}

function messageForRule(ruleName, fieldLabel, options = {}, parameters = []) {
    if (ruleName === 'required') {
        return validationMessage(
            'validation.rules.required',
            ':attribute is required.',
            fieldLabel,
            options,
        );
    }

    if (ruleName === 'string') {
        return validationMessage(
            'validation.rules.string',
            ':attribute must be a string.',
            fieldLabel,
            options,
        );
    }

    if (ruleName === 'numeric') {
        return validationMessage(
            'validation.rules.numeric',
            ':attribute must be a number.',
            fieldLabel,
            options,
        );
    }

    if (ruleName === 'integer') {
        return validationMessage(
            'validation.rules.integer',
            ':attribute must be an integer.',
            fieldLabel,
            options,
        );
    }

    if (ruleName === 'boolean') {
        return validationMessage(
            'validation.rules.boolean',
            ':attribute must be true or false.',
            fieldLabel,
            options,
        );
    }

    if (ruleName === 'array') {
        return validationMessage(
            'validation.rules.array',
            ':attribute must be an array.',
            fieldLabel,
            options,
        );
    }

    if (ruleName === 'email') {
        return validationMessage(
            'validation.rules.email',
            ':attribute must be a valid email address.',
            fieldLabel,
            options,
        );
    }

    if (ruleName === 'regex') {
        return validationMessage(
            'validation.rules.regex',
            ':attribute format is invalid.',
            fieldLabel,
            options,
        );
    }

    if (ruleName === 'not_regex') {
        return validationMessage(
            'validation.rules.not_regex',
            ':attribute format is invalid.',
            fieldLabel,
            options,
        );
    }

    if (ruleName === 'same') {
        return validationMessage(
            'validation.rules.same',
            ':attribute must match :other.',
            fieldLabel,
            options,
            {
                other: parameters[0] ?? '',
            },
        );
    }

    if (ruleName === 'confirmed') {
        return validationMessage(
            'validation.rules.confirmed',
            ':attribute confirmation does not match.',
            fieldLabel,
            options,
        );
    }

    if (ruleName === 'in') {
        return validationMessage(
            'validation.rules.in',
            ':attribute must be one of: :values.',
            fieldLabel,
            options,
            {
                values: parameters.join(', '),
            },
        );
    }

    return validationMessage(
        'validation.invalid_value',
        ':attribute is invalid.',
        fieldLabel,
        options,
    );
}

function hasToken(tokens, name) {
    return tokens.some((token) => parseRuleToken(token).name === name);
}

function createRegexFromParameter(parameter) {
    const rawPattern = String(parameter || '').trim();

    if (rawPattern === '') {
        return null;
    }

    if (!rawPattern.startsWith('/')) {
        try {
            return new RegExp(rawPattern);
        } catch {
            return null;
        }
    }

    const lastDelimiterIndex = rawPattern.lastIndexOf('/');

    if (lastDelimiterIndex <= 0) {
        return null;
    }

    const patternBody = rawPattern.slice(1, lastDelimiterIndex);
    const flags = rawPattern.slice(lastDelimiterIndex + 1);

    try {
        return new RegExp(patternBody, flags);
    } catch {
        return null;
    }
}

function validateMinOrMax(
    name,
    value,
    parameters,
    tokens,
    fieldLabel,
    options,
) {
    const limit = toNumber(parameters[0]);

    if (limit === null) {
        return null;
    }

    const numericExpected =
        hasToken(tokens, 'numeric') || hasToken(tokens, 'integer');
    const arrayExpected = hasToken(tokens, 'array');

    if (arrayExpected) {
        if (!Array.isArray(value)) {
            return null;
        }

        const length = value.length;

        if (name === 'min' && length < limit) {
            return validationMessage(
                'validation.rules.min.array',
                ':attribute must contain at least :min items.',
                fieldLabel,
                options,
                { min: limit },
            );
        }

        if (name === 'max' && length > limit) {
            return validationMessage(
                'validation.rules.max.array',
                ':attribute may not contain more than :max items.',
                fieldLabel,
                options,
                { max: limit },
            );
        }

        return null;
    }

    if (numericExpected) {
        const numberValue = toNumber(value);

        if (numberValue === null) {
            return null;
        }

        if (name === 'min' && numberValue < limit) {
            return validationMessage(
                'validation.rules.min.numeric',
                ':attribute must be at least :min.',
                fieldLabel,
                options,
                { min: limit },
            );
        }

        if (name === 'max' && numberValue > limit) {
            return validationMessage(
                'validation.rules.max.numeric',
                ':attribute may not be greater than :max.',
                fieldLabel,
                options,
                { max: limit },
            );
        }

        return null;
    }

    const length = String(value ?? '').length;

    if (name === 'min' && length < limit) {
        return validationMessage(
            'validation.rules.min.string',
            ':attribute must be at least :min characters.',
            fieldLabel,
            options,
            { min: limit },
        );
    }

    if (name === 'max' && length > limit) {
        return validationMessage(
            'validation.rules.max.string',
            ':attribute may not be greater than :max characters.',
            fieldLabel,
            options,
            { max: limit },
        );
    }

    return null;
}

export function validateValueWithRules(value, rules, options = {}) {
    const tokens = normalizeRuleTokens(rules);

    if (tokens.length === 0) {
        return null;
    }

    const fieldLabel =
        String(options.fieldLabel || '').trim() ||
        translate(options, 'validation.this_field', 'This field');
    const emptyValue = isEmptyValue(value);
    const required = hasToken(tokens, 'required');

    if (emptyValue) {
        if (required) {
            return messageForRule('required', fieldLabel, options);
        }

        return null;
    }

    for (const token of tokens) {
        const { name, parameters } = parseRuleToken(token);

        if (name === '' || name === 'required' || name === 'nullable') {
            continue;
        }

        if (name === 'string' && typeof value !== 'string') {
            return messageForRule(name, fieldLabel, options);
        }

        if (name === 'numeric' && toNumber(value) === null) {
            return messageForRule(name, fieldLabel, options);
        }

        if (name === 'integer' && toInteger(value) === null) {
            return messageForRule(name, fieldLabel, options);
        }

        if (name === 'boolean') {
            const normalized = String(value).trim().toLowerCase();
            const boolLike =
                typeof value === 'boolean' ||
                normalized === '1' ||
                normalized === '0' ||
                normalized === 'true' ||
                normalized === 'false' ||
                normalized === 'on' ||
                normalized === 'off' ||
                normalized === 'yes' ||
                normalized === 'no';

            if (!boolLike) {
                return messageForRule(name, fieldLabel, options);
            }
        }

        if (name === 'array' && !Array.isArray(value)) {
            return messageForRule(name, fieldLabel, options);
        }

        if (name === 'email') {
            const emailPattern =
                /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;

            if (!emailPattern.test(String(value))) {
                return messageForRule(name, fieldLabel, options);
            }
        }

        if (name === 'min' || name === 'max') {
            const minMaxMessage = validateMinOrMax(
                name,
                value,
                parameters,
                tokens,
                fieldLabel,
                options,
            );

            if (minMaxMessage) {
                return minMaxMessage;
            }
        }

        if (name === 'in') {
            const allowed = parameters.map((entry) => String(entry));

            if (!allowed.includes(String(value))) {
                return messageForRule(name, fieldLabel, options, allowed);
            }
        }

        if (name === 'regex' || name === 'not_regex') {
            const regex = createRegexFromParameter(parameters[0]);

            if (!(regex instanceof RegExp)) {
                return messageForRule(name, fieldLabel, options);
            }

            const matches = regex.test(String(value));

            if (
                (name === 'regex' && !matches) ||
                (name === 'not_regex' && matches)
            ) {
                return messageForRule(name, fieldLabel, options);
            }
        }

        if (name === 'same') {
            const otherField = String(parameters[0] || '').trim();
            const otherValue = options?.values?.[otherField];

            if (String(value) !== String(otherValue ?? '')) {
                return messageForRule(name, fieldLabel, options, [otherField]);
            }
        }

        if (name === 'confirmed') {
            const fieldName = String(options?.field || '').trim();
            const confirmationField = `${fieldName}_confirmation`;
            const confirmationValue = options?.values?.[confirmationField];

            if (
                fieldName === '' ||
                String(value) !== String(confirmationValue ?? '')
            ) {
                return messageForRule(name, fieldLabel, options);
            }
        }
    }

    return null;
}

export function validateRecordWithRules(record, rulesByField, options = {}) {
    if (!rulesByField || typeof rulesByField !== 'object') {
        return {};
    }

    const values = record && typeof record === 'object' ? record : {};
    const errors = {};

    Object.entries(rulesByField).forEach(([field, rules]) => {
        const normalizedField = String(field || '').trim();

        if (normalizedField === '') {
            return;
        }

        const message = validateValueWithRules(values[normalizedField], rules, {
            ...options,
            field: normalizedField,
            values,
            fieldLabel:
                options?.labels?.[normalizedField] ||
                options?.fieldLabel ||
                normalizedField,
        });

        if (message) {
            errors[normalizedField] = message;
        }
    });

    return errors;
}
