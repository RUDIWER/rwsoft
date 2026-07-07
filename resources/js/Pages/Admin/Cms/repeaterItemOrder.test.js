import assert from 'node:assert/strict';
import test from 'node:test';
import { reorderRepeaterItems } from './repeaterItemOrder.js';

test('reorderRepeaterItems moves an item to a later position', () => {
    const items = [{ uid: 'a' }, { uid: 'b' }, { uid: 'c' }];

    const reorderedItems = reorderRepeaterItems(items, 0, 2);

    assert.deepEqual(
        reorderedItems.map((item) => item.uid),
        ['b', 'c', 'a'],
    );
    assert.deepEqual(
        items.map((item) => item.uid),
        ['a', 'b', 'c'],
    );
});

test('reorderRepeaterItems moves an item to an earlier position', () => {
    const items = [{ uid: 'a' }, { uid: 'b' }, { uid: 'c' }];

    const reorderedItems = reorderRepeaterItems(items, 2, 0);

    assert.deepEqual(
        reorderedItems.map((item) => item.uid),
        ['c', 'a', 'b'],
    );
});

test('reorderRepeaterItems ignores invalid indices', () => {
    const items = [{ uid: 'a' }, { uid: 'b' }];

    assert.equal(reorderRepeaterItems(items, -1, 1), items);
    assert.equal(reorderRepeaterItems(items, 0, 2), items);
    assert.equal(reorderRepeaterItems(items, 1, 1), items);
});
