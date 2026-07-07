import assert from 'node:assert/strict';
import test from 'node:test';
import { validateValueWithExtendedRules } from './validate_with_extended_rules.js';

function translate(key, fallback, replacements = {}) {
    return Object.entries(replacements).reduce(
        (carry, [token, value]) => {
            return carry.replaceAll(`:${token}`, String(value ?? ''));
        },
        String(fallback || key || ''),
    );
}

test('validateValueWithExtendedRules keeps built-in required validation', () => {
    const message = validateValueWithExtendedRules('', 'required', {
        field: 'name',
        fieldLabel: 'Name',
        values: {},
        translate,
    });

    assert.equal(message, 'Name is required.');
});

test('validateValueWithExtendedRules validates custom iban_be rule', () => {
    const validMessage = validateValueWithExtendedRules(
        'BE68539007547034',
        'custom:iban_be',
        {
            field: 'iban',
            fieldLabel: 'IBAN',
            values: {},
            translate,
        },
    );

    assert.equal(validMessage, null);

    const invalidMessage = validateValueWithExtendedRules(
        'BE123',
        'custom:iban_be',
        {
            field: 'iban',
            fieldLabel: 'IBAN',
            values: {},
            translate,
        },
    );

    assert.equal(
        invalidMessage,
        'IBAN must be a valid Belgian IBAN (BE + 14 digits).',
    );

    const invalidChecksumMessage = validateValueWithExtendedRules(
        'BE68539007547035',
        'custom:iban_be',
        {
            field: 'iban',
            fieldLabel: 'IBAN',
            values: {},
            translate,
        },
    );

    assert.equal(
        invalidChecksumMessage,
        'IBAN must be a valid Belgian IBAN (BE + 14 digits).',
    );
});

test('validateValueWithExtendedRules validates custom rrn_be rule', () => {
    const validMessage = validateValueWithExtendedRules(
        '85073003328',
        'custom:rrn_be',
        {
            field: 'rrn',
            fieldLabel: 'National number',
            values: {},
            translate,
        },
    );

    assert.equal(validMessage, null);

    const invalidMessage = validateValueWithExtendedRules(
        '85073003399',
        'custom:rrn_be',
        {
            field: 'rrn',
            fieldLabel: 'National number',
            values: {},
            translate,
        },
    );

    assert.equal(
        invalidMessage,
        'National number must be a valid Belgian national register number (11 digits).',
    );
});

test('validateValueWithExtendedRules validates custom phone_be and postcode_be rules', () => {
    const validPhoneMessage = validateValueWithExtendedRules(
        '+32 475 12 34 56',
        'custom:phone_be',
        {
            field: 'phone',
            fieldLabel: 'Phone',
            values: {},
            translate,
        },
    );

    assert.equal(validPhoneMessage, null);

    const invalidPhoneMessage = validateValueWithExtendedRules(
        '1234',
        'custom:phone_be',
        {
            field: 'phone',
            fieldLabel: 'Phone',
            values: {},
            translate,
        },
    );

    assert.equal(
        invalidPhoneMessage,
        'Phone must be a valid Belgian phone number.',
    );

    const validPostcodeMessage = validateValueWithExtendedRules(
        '9000',
        'custom:postcode_be',
        {
            field: 'postcode',
            fieldLabel: 'Postcode',
            values: {},
            translate,
        },
    );

    assert.equal(validPostcodeMessage, null);

    const invalidPostcodeMessage = validateValueWithExtendedRules(
        '0999',
        'custom:postcode_be',
        {
            field: 'postcode',
            fieldLabel: 'Postcode',
            values: {},
            translate,
        },
    );

    assert.equal(
        invalidPostcodeMessage,
        'Postcode must be a valid Belgian postcode (1000-9999).',
    );
});

test('validateValueWithExtendedRules validates custom enterprise_be rule', () => {
    const validMessage = validateValueWithExtendedRules(
        '1234.567.894',
        'custom:enterprise_be',
        {
            field: 'enterprise',
            fieldLabel: 'Enterprise number',
            values: {},
            translate,
        },
    );

    assert.equal(validMessage, null);

    const invalidMessage = validateValueWithExtendedRules(
        '1234.567.899',
        'custom:enterprise_be',
        {
            field: 'enterprise',
            fieldLabel: 'Enterprise number',
            values: {},
            translate,
        },
    );

    assert.equal(
        invalidMessage,
        'Enterprise number must be a valid Belgian enterprise number (KBO/BCE).',
    );
});

test('validateValueWithExtendedRules supports custom rule parameters', () => {
    const message = validateValueWithExtendedRules(
        'one two',
        'custom:min_words,3',
        {
            field: 'description',
            fieldLabel: 'Description',
            values: {},
            translate,
        },
    );

    assert.equal(message, 'Description must contain at least 3 words.');
});

test('validateValueWithExtendedRules reports unknown custom rules', () => {
    const message = validateValueWithExtendedRules(
        'abc',
        'custom:not_existing_rule',
        {
            field: 'code',
            fieldLabel: 'Code',
            values: {},
            translate,
        },
    );

    assert.equal(message, 'Code has an unknown client rule not_existing_rule.');
});
