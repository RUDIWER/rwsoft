import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const editorFiles = [
    'resources/js/Pages/Admin/Cms/Components/CmsBlockEditor.vue',
    'resources/js/Pages/Admin/Cms/Layouts/Partials/CmsLayoutZoneEditor.vue',
];

test('CMS editors use the shared repeater field editor', () => {
    for (const file of editorFiles) {
        const contents = readFileSync(file, 'utf8');

        assert.match(contents, /CmsRepeaterFieldEditor/);
        assert.doesNotMatch(contents, /onRepeaterItemDragStart/);
        assert.doesNotMatch(contents, /dragOverRepeaterItemUid/);
    }
});

test('shared repeater field editor owns drag and reorder behavior', () => {
    const contents = readFileSync(
        'resources/js/Pages/Admin/Cms/Components/CmsRepeaterFieldEditor.vue',
        'utf8',
    );

    assert.match(contents, /reorderRepeaterItems/);
    assert.match(contents, /onItemDragStart/);
    assert.match(contents, /update:items/);
});
