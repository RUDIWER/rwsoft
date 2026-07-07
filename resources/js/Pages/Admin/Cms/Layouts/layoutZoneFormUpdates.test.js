import assert from 'node:assert/strict';
import test from 'node:test';
import { updatedLayoutSectionsForZone } from './layoutZoneFormUpdates.js';

test('updatedLayoutSectionsForZone replaces one zone with a cloned section array', () => {
    const currentSections = {
        header: [{ id: 1, placements: [] }],
        footer: [{ id: 2, placements: [{ id: 10 }] }],
    };
    const nextFooter = [{ id: 3, placements: [{ id: null }] }];

    const updatedSections = updatedLayoutSectionsForZone(
        currentSections,
        'footer',
        nextFooter,
    );

    assert.notEqual(updatedSections, currentSections);
    assert.equal(updatedSections.header, currentSections.header);
    assert.notEqual(updatedSections.footer, nextFooter);
    assert.deepEqual(updatedSections.footer, nextFooter);
});

test('updatedLayoutSectionsForZone protects form data from later child mutations', () => {
    const nextFooter = [{ id: 3, placements: [{ id: null }] }];

    const updatedSections = updatedLayoutSectionsForZone(
        {},
        'footer',
        nextFooter,
    );

    nextFooter[0].placements.push({ id: 20 });

    assert.deepEqual(updatedSections.footer, [
        { id: 3, placements: [{ id: null }] },
    ]);
});
