import {
    normalizeRuleTokens,
    validateValueWithRules,
} from '../Components/RwTable/validation/rules.js';
import { resolveExtendedValidationRule } from './extended_rules.js';

function replaceTokens(template, replacements = {}) {
    return Object.entries(replacements).reduce(
        (carry, [token, replacement]) => {
            return carry.replaceAll(`:${token}`, String(replacement ?? ''));
        },
        String(template || ''),
    );
}

function translate(options, key, fallback, replacements = {}) {
    if (typeof options?.translate === 'function' && key !== '') {
        const translated = options.translate(key, fallback, replacements);

        if (typeof translated === 'string' && translated.trim() !== '') {
            return translated;
        }
    }

    return replaceTokens(fallback, replacements);
}

function parseCustomRuleToken(token) {
    const normalizedToken = String(token || '').trim();
    const lowerToken = normalizedToken.toLowerCase();

    if (!lowerToken.startsWith('custom:') && !lowerToken.startsWith('x:')) {
        return null;
    }

    const payload = normalizedToken.includes(':')
        ? normalizedToken.slice(normalizedToken.indexOf(':') + 1)
        : '';

    const parts = payload
        .split(',')
        .map((entry) => entry.trim())
        .filter((entry) => entry !== '');

    const key = String(parts.shift() || '')
        .trim()
        .toLowerCase();

    if (key === '') {
        return null;
    }

    return {
        key,
        parameters: parts,
        token: normalizedToken,
    };
}

function normalizeCustomRuleResult(result, customRule, fieldLabel, options) {
    if (result === null || result === undefined || result === true) {
        return null;
    }

    if (typeof result === 'string') {
        const text = result.trim();

        if (text === '') {
            return null;
        }

        return replaceTokens(text, {
            attribute: fieldLabel,
            rule: customRule.key,
        });
    }

    if (result === false) {
        return translate(
            options,
            'validation.custom_failed',
            ':attribute is invalid for client rule :rule.',
            {
                attribute: fieldLabel,
                rule: customRule.key,
            },
        );
    }

    if (typeof result === 'object') {
        if (result.valid === true) {
            return null;
        }

        const replacements = {
            attribute: fieldLabel,
            rule: customRule.key,
            ...(result.replacements && typeof result.replacements === 'object'
                ? result.replacements
                : {}),
        };

        if (
            typeof result.message === 'string' &&
            result.message.trim() !== ''
        ) {
            return replaceTokens(result.message, replacements);
        }

        if (
            typeof result.messageKey === 'string' &&
            result.messageKey.trim() !== ''
        ) {
            return translate(
                options,
                result.messageKey,
                typeof result.fallback === 'string' &&
                    result.fallback.trim() !== ''
                    ? result.fallback
                    : ':attribute is invalid for client rule :rule.',
                replacements,
            );
        }
    }

    return translate(
        options,
        'validation.custom_failed',
        ':attribute is invalid for client rule :rule.',
        {
            attribute: fieldLabel,
            rule: customRule.key,
        },
    );
}

function validateCustomRule(value, customRule, options) {
    const fieldLabel =
        String(options?.fieldLabel || '').trim() ||
        translate(options, 'validation.this_field', 'This field');
    const handler = resolveExtendedValidationRule(customRule.key);

    if (typeof handler !== 'function') {
        return translate(
            options,
            'validation.custom_unknown_rule',
            ':attribute has an unknown client rule :rule.',
            {
                attribute: fieldLabel,
                rule: customRule.key,
            },
        );
    }

    try {
        const result = handler({
            value,
            values:
                options?.values && typeof options.values === 'object'
                    ? options.values
                    : {},
            field: String(options?.field || '').trim(),
            fieldLabel,
            parameters: customRule.parameters,
            ruleKey: customRule.key,
            token: customRule.token,
            translate: options?.translate,
        });

        if (result && typeof result.then === 'function') {
            return translate(
                options,
                'validation.custom_runtime_error',
                'Client rule :rule failed to execute.',
                {
                    attribute: fieldLabel,
                    rule: customRule.key,
                },
            );
        }

        return normalizeCustomRuleResult(
            result,
            customRule,
            fieldLabel,
            options,
        );
    } catch {
        return translate(
            options,
            'validation.custom_runtime_error',
            'Client rule :rule failed to execute.',
            {
                attribute: fieldLabel,
                rule: customRule.key,
            },
        );
    }
}

export function validateValueWithExtendedRules(value, rules, options = {}) {
    const tokens = normalizeRuleTokens(rules);

    if (tokens.length === 0) {
        return null;
    }

    const customRules = [];
    const builtInTokens = [];

    tokens.forEach((token) => {
        const customRule = parseCustomRuleToken(token);

        if (customRule) {
            customRules.push(customRule);
            return;
        }

        builtInTokens.push(token);
    });

    if (builtInTokens.length > 0) {
        const builtInMessage = validateValueWithRules(
            value,
            builtInTokens.join('|'),
            options,
        );

        if (builtInMessage) {
            return builtInMessage;
        }
    }

    for (const customRule of customRules) {
        const customMessage = validateCustomRule(value, customRule, options);

        if (customMessage) {
            return customMessage;
        }
    }

    return null;
}
