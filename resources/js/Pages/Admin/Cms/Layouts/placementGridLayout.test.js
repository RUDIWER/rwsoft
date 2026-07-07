import assert from 'node:assert/strict';
import test from 'node:test';
import {
    gridItemForPlacementLayout,
    resolvePlacementLayoutCollisions,
} from './placementGridLayout.js';

test('resolvePlacementLayoutCollisions stacks overlapping visible placements per device', () => {
    const placements = resolvePlacementLayoutCollisions([
        placement('address', 0),
        placement('form', 0),
        placement('menu', 0),
    ]);

    assert.deepEqual(
        placements.map((item) => item.layout_config.mobile.y),
        [0, 1, 2],
    );
});

test('resolvePlacementLayoutCollisions ignores hidden placements for device collisions', () => {
    const placements = resolvePlacementLayoutCollisions([
        placement('hidden', 0, { visible_mobile: false }),
        placement('visible', 0),
    ]);

    assert.equal(placements[0].layout_config.mobile.y, 0);
    assert.equal(placements[1].layout_config.mobile.y, 0);
});

test('resolvePlacementLayoutCollisions applies explicit alignment before collision resolving', () => {
    const placements = resolvePlacementLayoutCollisions(
        [
            placement('left', 0, { desktop_span: 4 }),
            placement('center', 0, {
                desktop_span: 4,
                style_config: {
                    devices: {
                        desktop: { alignment: 'center' },
                    },
                },
            }),
            placement('right', 0, {
                desktop_span: 4,
                style_config: {
                    devices: {
                        desktop: { alignment: 'right' },
                    },
                },
            }),
        ],
        { applyAlignment: true },
    );

    assert.deepEqual(
        placements.map((item) => item.layout_config.desktop.x),
        [0, 4, 8],
    );
    assert.deepEqual(
        placements.map((item) => item.layout_config.desktop.y),
        [0, 0, 0],
    );
});

test('gridItemForPlacementLayout applies alignment without changing default layout positions', () => {
    const defaultItem = gridItemForPlacementLayout(
        placement('default', 0, { desktop_span: 4 }),
        'desktop',
        0,
        { applyAlignment: true },
    );
    const centeredItem = gridItemForPlacementLayout(
        placement('center', 0, {
            desktop_span: 4,
            style_config: {
                devices: {
                    desktop: { alignment: 'center' },
                },
            },
        }),
        'desktop',
        0,
        { applyAlignment: true },
    );

    assert.equal(defaultItem.x, 0);
    assert.equal(centeredItem.x, 4);
});

function placement(uid, row, overrides = {}) {
    return {
        uid,
        mobile_span: 12,
        tablet_span: 12,
        desktop_span: 12,
        visible_mobile: true,
        visible_tablet: true,
        visible_desktop: true,
        layout_config: {
            mobile: { x: 0, y: row, w: 12, h: 1 },
            tablet: { x: 0, y: row, w: 12, h: 1 },
            desktop: { x: 0, y: row, w: overrides.desktop_span ?? 12, h: 1 },
        },
        style_config: {},
        ...overrides,
    };
}
