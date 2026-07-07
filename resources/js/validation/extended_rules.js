function normalizeRuleKey(value) {
    return String(value || '')
        .trim()
        .toLowerCase();
}

function toWordCount(value) {
    return String(value || '')
        .trim()
        .split(/\s+/)
        .filter((segment) => segment !== '').length;
}

function digitsOnly(value) {
    return String(value || '').replace(/\D+/g, '');
}

function normalizeBelgianPhone(value) {
    const raw = String(value || '').trim();

    if (raw === '') {
        return '';
    }

    const compact = raw.replace(/[\s()./-]+/g, '');

    if (compact.startsWith('+32')) {
        return `0${digitsOnly(compact.slice(3))}`;
    }

    if (compact.startsWith('0032')) {
        return `0${digitsOnly(compact.slice(4))}`;
    }

    const normalizedDigits = digitsOnly(compact);

    if (
        normalizedDigits.startsWith('32') &&
        !normalizedDigits.startsWith('0') &&
        normalizedDigits.length >= 10
    ) {
        return `0${normalizedDigits.slice(2)}`;
    }

    return normalizedDigits;
}

function modulo97FromIbanNumericString(value) {
    let remainder = 0;

    for (const character of String(value || '').toUpperCase()) {
        if (character >= '0' && character <= '9') {
            remainder = (remainder * 10 + Number(character)) % 97;
            continue;
        }

        if (character < 'A' || character > 'Z') {
            return -1;
        }

        const alphaNumericValue = character.charCodeAt(0) - 55;
        remainder = (remainder * 100 + alphaNumericValue) % 97;
    }

    return remainder;
}

function isValidBelgianIban(value) {
    const normalized = String(value || '')
        .replace(/\s+/g, '')
        .toUpperCase();

    if (!/^BE\d{14}$/.test(normalized)) {
        return false;
    }

    const rearranged = normalized.slice(4) + normalized.slice(0, 4);

    return modulo97FromIbanNumericString(rearranged) === 1;
}

function isValidBelgianRrn(value) {
    const normalized = digitsOnly(value);

    if (normalized.length !== 11) {
        return false;
    }

    const base = Number.parseInt(normalized.slice(0, 9), 10);
    const providedChecksum = Number.parseInt(normalized.slice(9), 10);

    if (!Number.isFinite(base) || !Number.isFinite(providedChecksum)) {
        return false;
    }

    const checksumFor = (number) => {
        const computed = 97 - (number % 97);

        return computed === 0 ? 97 : computed;
    };

    const oldStyleChecksum = checksumFor(base);
    const newStyleChecksum = checksumFor(2000000000 + base);

    return (
        providedChecksum === oldStyleChecksum ||
        providedChecksum === newStyleChecksum
    );
}

function isValidBelgianEnterpriseNumber(value) {
    let normalized = String(value || '')
        .trim()
        .toUpperCase()
        .replace(/[\s./-]+/g, '');

    if (normalized.startsWith('BE')) {
        normalized = normalized.slice(2);
    }

    normalized = digitsOnly(normalized);

    if (normalized.length === 9) {
        normalized = `0${normalized}`;
    }

    if (!/^\d{10}$/.test(normalized)) {
        return false;
    }

    const base = Number.parseInt(normalized.slice(0, 8), 10);
    const providedChecksum = Number.parseInt(normalized.slice(8), 10);

    if (!Number.isFinite(base) || !Number.isFinite(providedChecksum)) {
        return false;
    }

    const checksum = 97 - (base % 97);
    const normalizedChecksum = checksum === 0 ? 97 : checksum;

    return providedChecksum === normalizedChecksum;
}

function isValidBelgianPostcode(value) {
    const normalized = String(value || '').trim();

    if (!/^\d{4}$/.test(normalized)) {
        return false;
    }

    const numeric = Number.parseInt(normalized, 10);

    return Number.isFinite(numeric) && numeric >= 1000 && numeric <= 9999;
}

const extendedValidationRules = {
    iban_be: ({ value }) => {
        if (String(value || '').trim() === '') {
            return true;
        }

        return {
            valid: isValidBelgianIban(value),
            messageKey: 'validation.custom.iban_be',
            fallback:
                ':attribute must be a valid Belgian IBAN (BE + 14 digits).',
        };
    },
    rrn_be: ({ value }) => {
        if (String(value || '').trim() === '') {
            return true;
        }

        return {
            valid: isValidBelgianRrn(value),
            messageKey: 'validation.custom.rrn_be',
            fallback:
                ':attribute must be a valid Belgian national register number (11 digits).',
        };
    },
    phone_be: ({ value }) => {
        if (String(value || '').trim() === '') {
            return true;
        }

        const normalized = normalizeBelgianPhone(value);

        return {
            valid: /^0\d{8,9}$/.test(normalized),
            messageKey: 'validation.custom.phone_be',
            fallback: ':attribute must be a valid Belgian phone number.',
        };
    },
    postcode_be: ({ value }) => {
        if (String(value || '').trim() === '') {
            return true;
        }

        return {
            valid: isValidBelgianPostcode(value),
            messageKey: 'validation.custom.postcode_be',
            fallback:
                ':attribute must be a valid Belgian postcode (1000-9999).',
        };
    },
    enterprise_be: ({ value }) => {
        if (String(value || '').trim() === '') {
            return true;
        }

        return {
            valid: isValidBelgianEnterpriseNumber(value),
            messageKey: 'validation.custom.enterprise_be',
            fallback:
                ':attribute must be a valid Belgian enterprise number (KBO/BCE).',
        };
    },
    min_words: ({ value, parameters }) => {
        const minimum = Number.parseInt(String(parameters?.[0] || '1'), 10);
        const normalizedMinimum =
            Number.isFinite(minimum) && minimum > 0 ? minimum : 1;

        if (String(value || '').trim() === '') {
            return true;
        }

        return {
            valid: toWordCount(value) >= normalizedMinimum,
            messageKey: 'validation.custom.min_words',
            fallback: ':attribute must contain at least :min words.',
            replacements: {
                min: normalizedMinimum,
            },
        };
    },
};

export function registerExtendedValidationRule(ruleKey, handler) {
    const normalizedRuleKey = normalizeRuleKey(ruleKey);

    if (normalizedRuleKey === '' || typeof handler !== 'function') {
        return;
    }

    extendedValidationRules[normalizedRuleKey] = handler;
}

export function resolveExtendedValidationRule(ruleKey) {
    const normalizedRuleKey = normalizeRuleKey(ruleKey);

    if (normalizedRuleKey === '') {
        return null;
    }

    return extendedValidationRules[normalizedRuleKey] || null;
}

export function extendedValidationRuleKeys() {
    return Object.keys(extendedValidationRules);
}
