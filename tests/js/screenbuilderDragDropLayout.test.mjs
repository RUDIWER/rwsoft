import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import {
    actionButtonMinColumns,
    fineGridWidthForActionButton,
} from '../../resources/js/screenbuilder/actionButtonLayout.js';
import { normalizeActionGroups } from '../../resources/js/screenbuilder/actionGroupNormalizer.js';
import {
    findSchemaNode,
    moveNodeWithLayout,
} from '../../resources/js/screenbuilder/dragDropLayout.js';

function field(id, y, x = 0, w = 6) {
    return {
        id,
        type: 'text_input',
        props: { name: id, label: id },
        layout: {
            desktop: { x, y, w, h: 1, order: y * 12 + x },
        },
    };
}

function schemaWithForm(children) {
    return {
        id: 'screen',
        type: 'screen',
        props: {},
        layout: { desktop: { columns: 12 } },
        children: [
            {
                id: 'form',
                type: 'form',
                props: {},
                layout: {
                    desktop: { columns: 12, x: 0, y: 0, w: 12, h: 1 },
                    tablet: { columns: 8, x: 0, y: 0, w: 8, h: 1 },
                    mobile: { columns: 1, x: 0, y: 0, w: 1, h: 1 },
                },
                children,
            },
        ],
    };
}

function actionButton(id, alignment = 'right') {
    return {
        id,
        type: 'action_button',
        props: { label: id, alignment },
    };
}

function actionButtonField(id, y, x = 0, w = 2, props = {}, h = 1) {
    return {
        id,
        type: 'action_button',
        props: { label: id, ...props },
        layout: {
            desktop: { x, y, w, h, order: y * 12 + x },
        },
    };
}

describe('screenbuilder drag/drop layout', () => {
    it('inserts a dragged field on a new row before the target and shifts lower rows', () => {
        const schema = schemaWithForm([
            field('first', 0),
            field('remember', 2),
            field('email', 1),
        ]);

        const next = moveNodeWithLayout(schema, {
            nodeId: 'remember',
            targetParentId: 'form',
            targetNodeId: 'email',
            placement: 'before',
            breakpoint: 'desktop',
        });

        assert.equal(findSchemaNode(next, 'remember').layout.desktop.y, 1);
        assert.equal(findSchemaNode(next, 'remember').layout.desktop.x, 0);
        assert.equal(findSchemaNode(next, 'email').layout.desktop.y, 2);
        assert.equal(findSchemaNode(next, 'first').layout.desktop.y, 0);
    });

    it('places a dragged field to the right when the row has enough free space', () => {
        const schema = schemaWithForm([
            field('email', 0, 0, 3),
            field('remember', 1, 0, 4),
        ]);

        const next = moveNodeWithLayout(schema, {
            nodeId: 'remember',
            targetParentId: 'form',
            targetNodeId: 'email',
            placement: 'right',
            breakpoint: 'desktop',
        });

        assert.equal(findSchemaNode(next, 'remember').layout.desktop.y, 0);
        assert.equal(findSchemaNode(next, 'remember').layout.desktop.x, 3);
        assert.equal(findSchemaNode(next, 'email').layout.desktop.w, 3);
        assert.equal(findSchemaNode(next, 'remember').layout.desktop.w, 4);
    });

    it('preserves row field widths when side placement fits within the grid', () => {
        const schema = schemaWithForm([
            field('id', 0, 0, 3),
            field('name', 0, 3, 4),
            field('created_at', 2, 0, 5),
        ]);

        const next = moveNodeWithLayout(schema, {
            nodeId: 'created_at',
            targetParentId: 'form',
            targetNodeId: 'id',
            placement: 'right',
            breakpoint: 'desktop',
        });

        assert.deepEqual(findSchemaNode(next, 'id').layout.desktop, {
            x: 0,
            y: 0,
            w: 3,
            h: 1,
            order: 0,
        });
        assert.deepEqual(findSchemaNode(next, 'created_at').layout.desktop, {
            x: 3,
            y: 0,
            w: 5,
            h: 1,
            order: 3,
        });
        assert.deepEqual(findSchemaNode(next, 'name').layout.desktop, {
            x: 8,
            y: 0,
            w: 4,
            h: 1,
            order: 8,
        });
    });

    it('redistributes fields when side placement would overflow the grid', () => {
        const schema = schemaWithForm([
            field('id', 1, 0, 12),
            field('created_at', 4, 0, 12),
            field('updated_at', 8, 0, 12),
        ]);

        const next = moveNodeWithLayout(schema, {
            nodeId: 'created_at',
            targetParentId: 'form',
            targetNodeId: 'id',
            placement: 'right',
            breakpoint: 'desktop',
        });

        assert.deepEqual(findSchemaNode(next, 'id').layout.desktop, {
            x: 0,
            y: 1,
            w: 6,
            h: 1,
            order: 12,
        });
        assert.deepEqual(findSchemaNode(next, 'created_at').layout.desktop, {
            x: 6,
            y: 1,
            w: 6,
            h: 1,
            order: 18,
        });
        assert.equal(findSchemaNode(next, 'updated_at').layout.desktop.y, 2);
    });

    it('does not create extra empty rows when repeating the same side drop', () => {
        const schema = schemaWithForm([
            field('id', 1, 0, 12),
            field('created_at', 4, 0, 12),
            field('updated_at', 8, 0, 12),
        ]);

        const once = moveNodeWithLayout(schema, {
            nodeId: 'created_at',
            targetParentId: 'form',
            targetNodeId: 'id',
            placement: 'right',
            breakpoint: 'desktop',
        });
        const twice = moveNodeWithLayout(once, {
            nodeId: 'created_at',
            targetParentId: 'form',
            targetNodeId: 'id',
            placement: 'right',
            breakpoint: 'desktop',
        });

        assert.equal(findSchemaNode(twice, 'id').layout.desktop.y, 1);
        assert.equal(findSchemaNode(twice, 'created_at').layout.desktop.y, 1);
        assert.equal(findSchemaNode(twice, 'updated_at').layout.desktop.y, 2);
    });

    it('redistributes an occupied row instead of creating a new row', () => {
        const schema = schemaWithForm([
            field('id', 0, 0, 6),
            field('name', 0, 6, 6),
            field('created_at', 3, 0, 12),
        ]);

        const next = moveNodeWithLayout(schema, {
            nodeId: 'created_at',
            targetParentId: 'form',
            targetNodeId: 'id',
            placement: 'right',
            breakpoint: 'desktop',
        });

        assert.equal(findSchemaNode(next, 'id').layout.desktop.x, 0);
        assert.equal(findSchemaNode(next, 'id').layout.desktop.w, 4);
        assert.equal(findSchemaNode(next, 'created_at').layout.desktop.x, 4);
        assert.equal(findSchemaNode(next, 'created_at').layout.desktop.w, 4);
        assert.equal(findSchemaNode(next, 'name').layout.desktop.x, 8);
        assert.equal(findSchemaNode(next, 'name').layout.desktop.w, 4);
        assert.equal(findSchemaNode(next, 'created_at').layout.desktop.y, 0);
    });

    it('keeps mobile side placement stacked', () => {
        const schema = schemaWithForm([
            {
                ...field('id', 0, 0, 12),
                layout: { mobile: { x: 0, y: 0, w: 1, h: 1, order: 0 } },
            },
            {
                ...field('created_at', 3, 0, 12),
                layout: { mobile: { x: 0, y: 3, w: 1, h: 1, order: 3 } },
            },
        ]);

        const next = moveNodeWithLayout(schema, {
            nodeId: 'created_at',
            targetParentId: 'form',
            targetNodeId: 'id',
            placement: 'right',
            breakpoint: 'mobile',
        });

        assert.equal(findSchemaNode(next, 'id').layout.mobile.y, 0);
        assert.equal(findSchemaNode(next, 'created_at').layout.mobile.y, 1);
        assert.equal(findSchemaNode(next, 'created_at').layout.mobile.x, 0);
    });

    it('places a dragged field on a free grid column in the target row', () => {
        const schema = schemaWithForm([
            field('id', 0, 0, 3),
            field('created_at', 2, 0, 3),
        ]);

        const next = moveNodeWithLayout(schema, {
            nodeId: 'created_at',
            targetParentId: 'form',
            placement: 'grid-position',
            gridX: 9,
            gridY: 0,
            breakpoint: 'desktop',
        });

        assert.deepEqual(findSchemaNode(next, 'created_at').layout.desktop, {
            x: 9,
            y: 0,
            w: 3,
            h: 1,
            order: 9,
        });
        assert.deepEqual(findSchemaNode(next, 'id').layout.desktop, {
            x: 0,
            y: 0,
            w: 3,
            h: 1,
            order: 0,
        });
    });

    it('keeps empty horizontal space when grid positioning in the middle of a row', () => {
        const schema = schemaWithForm([
            field('id', 0, 0, 3),
            field('name', 0, 9, 3),
            field('created_at', 2, 0, 3),
        ]);

        const next = moveNodeWithLayout(schema, {
            nodeId: 'created_at',
            targetParentId: 'form',
            placement: 'grid-position',
            gridX: 4,
            gridY: 0,
            breakpoint: 'desktop',
        });

        assert.equal(findSchemaNode(next, 'created_at').layout.desktop.x, 4);
        assert.equal(findSchemaNode(next, 'created_at').layout.desktop.y, 0);
        assert.equal(findSchemaNode(next, 'name').layout.desktop.x, 9);
    });

    it('moves a grid-position drop to a new row when the target row is full', () => {
        const schema = schemaWithForm([
            field('id', 0, 0, 6),
            field('name', 0, 6, 6),
            field('created_at', 3, 0, 3),
        ]);

        const next = moveNodeWithLayout(schema, {
            nodeId: 'created_at',
            targetParentId: 'form',
            placement: 'grid-position',
            gridX: 4,
            gridY: 0,
            breakpoint: 'desktop',
        });

        assert.equal(findSchemaNode(next, 'created_at').layout.desktop.x, 0);
        assert.equal(findSchemaNode(next, 'created_at').layout.desktop.y, 1);
        assert.equal(findSchemaNode(next, 'id').layout.desktop.y, 0);
        assert.equal(findSchemaNode(next, 'name').layout.desktop.y, 0);
    });

    it('keeps mobile grid-position placement stacked', () => {
        const schema = schemaWithForm([
            {
                ...field('id', 0, 0, 12),
                layout: { mobile: { x: 0, y: 0, w: 1, h: 1, order: 0 } },
            },
            {
                ...field('created_at', 3, 0, 12),
                layout: { mobile: { x: 0, y: 3, w: 1, h: 1, order: 3 } },
            },
        ]);

        const next = moveNodeWithLayout(schema, {
            nodeId: 'created_at',
            targetParentId: 'form',
            placement: 'grid-position',
            gridX: 0,
            gridY: 0,
            breakpoint: 'mobile',
        });

        assert.equal(findSchemaNode(next, 'id').layout.mobile.y, 0);
        assert.equal(findSchemaNode(next, 'created_at').layout.mobile.y, 1);
        assert.equal(findSchemaNode(next, 'created_at').layout.mobile.x, 0);
    });

    it('moves an action button to the requested free grid column', () => {
        const schema = schemaWithForm([
            field('email', 0, 0, 6),
            actionButtonField('save', 1, 0, 2),
        ]);

        const next = moveNodeWithLayout(schema, {
            nodeId: 'save',
            targetParentId: 'form',
            placement: 'grid-position',
            gridX: 40,
            gridY: 0,
            breakpoint: 'desktop',
        });

        assert.equal(findSchemaNode(next, 'save').layout.desktop.x, 10);
        assert.equal(findSchemaNode(next, 'save').layout.desktop.xFine, 40);
        assert.equal(findSchemaNode(next, 'save').layout.desktop.y, 0);
    });

    it('moves an action button left to a free grid column', () => {
        const schema = schemaWithForm([
            field('email', 0, 3, 6),
            actionButtonField('save', 1, 10, 2),
        ]);

        const next = moveNodeWithLayout(schema, {
            nodeId: 'save',
            targetParentId: 'form',
            placement: 'grid-position',
            gridX: 0,
            gridY: 0,
            breakpoint: 'desktop',
        });

        assert.equal(findSchemaNode(next, 'save').layout.desktop.x, 0);
        assert.equal(findSchemaNode(next, 'save').layout.desktop.xFine, 0);
        assert.equal(findSchemaNode(next, 'save').layout.desktop.y, 0);
    });

    it('places an action button directly after a half-width field on the fine grid', () => {
        const schema = schemaWithForm([
            field('email', 0, 0, 6),
            actionButtonField('save', 1, 10, 2),
        ]);

        const next = moveNodeWithLayout(schema, {
            nodeId: 'save',
            targetParentId: 'form',
            placement: 'grid-position',
            gridX: 24,
            gridY: 0,
            breakpoint: 'desktop',
        });

        assert.equal(findSchemaNode(next, 'save').layout.desktop.xFine, 24);
        assert.equal(findSchemaNode(next, 'save').layout.desktop.y, 0);
    });

    it('resets stale action button height when placing on the fine grid', () => {
        const schema = schemaWithForm([
            field('email', 0, 0, 6),
            actionButtonField('save', 1, 10, 2, {}, 2),
        ]);

        const next = moveNodeWithLayout(schema, {
            nodeId: 'save',
            targetParentId: 'form',
            placement: 'grid-position',
            gridX: 24,
            gridY: 0,
            breakpoint: 'desktop',
        });

        assert.equal(findSchemaNode(next, 'save').layout.desktop.xFine, 24);
        assert.equal(findSchemaNode(next, 'save').layout.desktop.y, 0);
        assert.equal(findSchemaNode(next, 'save').layout.desktop.h, 1);
    });

    it('ignores stale action button height when checking row overlap', () => {
        const schema = schemaWithForm([
            actionButtonField('back', 0, 0, 2, {}, 2),
            actionButtonField('save', 2, 10, 2),
        ]);

        const next = moveNodeWithLayout(schema, {
            nodeId: 'save',
            targetParentId: 'form',
            placement: 'grid-position',
            gridX: 0,
            gridY: 1,
            breakpoint: 'desktop',
        });

        assert.equal(findSchemaNode(next, 'save').layout.desktop.xFine, 0);
        assert.equal(findSchemaNode(next, 'save').layout.desktop.y, 1);
        assert.equal(findSchemaNode(next, 'save').layout.desktop.h, 1);
    });

    it('swaps action buttons when the requested grid column is occupied by a button', () => {
        const schema = schemaWithForm([
            actionButtonField('back', 0, 0, 2),
            actionButtonField('save', 0, 10, 2),
        ]);

        const next = moveNodeWithLayout(schema, {
            nodeId: 'back',
            targetParentId: 'form',
            placement: 'grid-position',
            gridX: 40,
            gridY: 0,
            breakpoint: 'desktop',
        });

        assert.equal(findSchemaNode(next, 'back').layout.desktop.x, 10);
        assert.equal(findSchemaNode(next, 'back').layout.desktop.xFine, 40);
        assert.equal(findSchemaNode(next, 'save').layout.desktop.x, 0);
        assert.equal(findSchemaNode(next, 'save').layout.desktop.xFine, 0);
        assert.equal(findSchemaNode(next, 'back').layout.desktop.y, 0);
        assert.equal(findSchemaNode(next, 'save').layout.desktop.y, 0);
    });

    it('swaps action buttons when moving the right button left', () => {
        const schema = schemaWithForm([
            actionButtonField('back', 0, 0, 2),
            actionButtonField('save', 0, 10, 2),
        ]);

        const next = moveNodeWithLayout(schema, {
            nodeId: 'save',
            targetParentId: 'form',
            placement: 'grid-position',
            gridX: 0,
            gridY: 0,
            breakpoint: 'desktop',
        });

        assert.equal(findSchemaNode(next, 'save').layout.desktop.x, 0);
        assert.equal(findSchemaNode(next, 'save').layout.desktop.xFine, 0);
        assert.equal(findSchemaNode(next, 'back').layout.desktop.x, 10);
        assert.equal(findSchemaNode(next, 'back').layout.desktop.xFine, 40);
        assert.equal(findSchemaNode(next, 'save').layout.desktop.y, 0);
        assert.equal(findSchemaNode(next, 'back').layout.desktop.y, 0);
    });

    it('calculates action button minimum widths from label and action metadata', () => {
        assert.equal(actionButtonMinColumns({ label: 'Bewaren' }), 2);
        assert.equal(actionButtonMinColumns({ label: 'Verwijderen' }), 3);
        assert.equal(
            actionButtonMinColumns({ label: 'Definitief verwijderen' }),
            4,
        );
        assert.equal(
            actionButtonMinColumns({ tone: 'back', label: 'Terug' }),
            2,
        );
    });

    it('ignores stale coarse button width unless button size was manually resized', () => {
        assert.equal(
            fineGridWidthForActionButton(
                { x: 10, w: 2, wFine: 8 },
                { label: 'Bewaren', icon: 'mdi-content-save' },
                12,
            ),
            4,
        );
        assert.equal(
            fineGridWidthForActionButton(
                { x: 10, w: 2, wFine: 8, buttonSizeMode: 'manual' },
                { label: 'Bewaren', icon: 'mdi-content-save' },
                12,
            ),
            8,
        );
    });

    it('unwraps legacy action groups into direct action buttons', () => {
        const schema = schemaWithForm([
            field('email', 0, 0, 6),
            {
                id: 'actions',
                type: 'action_group',
                props: { gap: 8 },
                layout: {
                    desktop: { x: 0, y: 1, w: 12, h: 1, order: 12 },
                    tablet: { x: 0, y: 1, w: 8, h: 1, order: 8 },
                    mobile: { x: 0, y: 1, w: 1, h: 1, order: 1 },
                },
                children: [
                    actionButton('back', 'left'),
                    actionButton('delete', 'right'),
                    actionButton('save', 'right'),
                ],
            },
        ]);

        const next = normalizeActionGroups(schema);
        const form = findSchemaNode(next, 'form');

        assert.equal(findSchemaNode(next, 'actions'), null);
        assert.deepEqual(
            form.children.map((child) => child.id),
            ['email', 'back', 'delete', 'save'],
        );
        assert.equal(findSchemaNode(next, 'back').layout.desktop.x, 0);
        assert.equal(findSchemaNode(next, 'delete').layout.desktop.x, 8);
        assert.equal(findSchemaNode(next, 'save').layout.desktop.x, 10);
        assert.equal(findSchemaNode(next, 'save').layout.mobile.y, 3);
    });
});
