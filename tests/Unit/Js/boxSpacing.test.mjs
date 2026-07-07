import assert from 'node:assert/strict';
import { test } from 'node:test';

import {
    legacySectionSpacingBox,
    normalizeBoxSpacing,
} from '../../../resources/js/Pages/Admin/Cms/Layouts/Partials/boxSpacing.js';

test('empty box uses legacy section spacing fallback', () => {
    const normalized = normalizeBoxSpacing(
        {
            desktop: {
                padding: { unit: 'rem' },
                margin: { unit: 'rem' },
            },
            tablet: {
                padding: { unit: 'rem' },
                margin: { unit: 'rem' },
            },
            mobile: {
                padding: { unit: 'rem' },
                margin: { unit: 'rem' },
            },
        },
        legacySectionSpacingBox('compact'),
    );

    assert.equal(normalized.desktop.padding.top, 2);
    assert.equal(normalized.desktop.padding.bottom, 2);
    assert.equal(normalized.tablet.padding.top, 1.5);
    assert.equal(normalized.tablet.padding.bottom, 1.5);
    assert.equal(normalized.mobile.padding.top, 1);
    assert.equal(normalized.mobile.padding.bottom, 1);
});

test('filled box wins over legacy section spacing fallback', () => {
    const normalized = normalizeBoxSpacing(
        {
            desktop: {
                padding: { unit: 'px', top: 24 },
            },
        },
        legacySectionSpacingBox('compact'),
    );

    assert.equal(normalized.desktop.padding.unit, 'px');
    assert.equal(normalized.desktop.padding.top, 24);
    assert.equal(normalized.desktop.padding.bottom, null);
    assert.equal(normalized.tablet.padding.top, null);
});
