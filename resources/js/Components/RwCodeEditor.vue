<template>
    <div class="rw-code-editor-wrapper" :style="wrapperStyle">
        <Codemirror
            :model-value="String(modelValue || '')"
            :extensions="editorExtensions"
            :autofocus="autofocus"
            :tab-size="tabSize"
            :placeholder="placeholder"
            :disabled="disabled || readonly"
            class="rw-code-editor"
            @update:model-value="emitUpdate"
            @blur="emit('blur')"
        />
    </div>
</template>

<script setup>
import { sql, MySQL } from '@codemirror/lang-sql';
import { css } from '@codemirror/lang-css';
import { html } from '@codemirror/lang-html';
import { markdown } from '@codemirror/lang-markdown';
import { EditorState } from '@codemirror/state';
import { tags } from '@lezer/highlight';
import { autocompletion, closeBrackets } from '@codemirror/autocomplete';
import {
    HighlightStyle,
    bracketMatching,
    syntaxHighlighting,
} from '@codemirror/language';
import {
    drawSelection,
    EditorView,
    highlightActiveLine,
    highlightActiveLineGutter,
    lineNumbers,
} from '@codemirror/view';
import { javascript } from '@codemirror/lang-javascript';
import { php } from '@codemirror/lang-php';
import { Codemirror } from 'vue-codemirror';
import { computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    language: {
        type: String,
        default: 'text',
    },
    placeholder: {
        type: String,
        default: '',
    },
    height: {
        type: String,
        default: '420px',
    },
    tabSize: {
        type: Number,
        default: 2,
    },
    autofocus: {
        type: Boolean,
        default: false,
    },
    lineWrapping: {
        type: Boolean,
        default: false,
    },
    readonly: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    extensions: {
        type: Array,
        default: () => [],
    },
    theme: {
        type: String,
        default: 'default',
    },
});

const emit = defineEmits(['update:modelValue', 'blur']);

const wrapperStyle = computed(() => {
    const presets = {
        default: {
            bg: 'rgb(2 6 23)',
            fg: 'rgb(226 232 240)',
            border: 'rgb(51 65 85)',
            gutterBg: 'rgb(15 23 42)',
            gutterFg: 'rgb(148 163 184)',
            activeLine: 'rgb(15 23 42 / 0.55)',
            activeGutter: 'rgb(30 41 59)',
            selection: 'rgb(37 99 235 / 0.4)',
            cursor: 'rgb(226 232 240)',
        },
        graphite: {
            bg: 'rgb(17 24 39)',
            fg: 'rgb(229 231 235)',
            border: 'rgb(75 85 99)',
            gutterBg: 'rgb(31 41 55)',
            gutterFg: 'rgb(156 163 175)',
            activeLine: 'rgb(55 65 81 / 0.45)',
            activeGutter: 'rgb(75 85 99)',
            selection: 'rgb(148 163 184 / 0.35)',
            cursor: 'rgb(248 250 252)',
        },
    };

    const selectedPreset =
        presets[String(props.theme || 'default').toLowerCase()] ||
        presets.default;

    return {
        height: String(props.height || '420px'),
        '--rw-editor-bg': selectedPreset.bg,
        '--rw-editor-fg': selectedPreset.fg,
        '--rw-editor-border': selectedPreset.border,
        '--rw-editor-gutter-bg': selectedPreset.gutterBg,
        '--rw-editor-gutter-fg': selectedPreset.gutterFg,
        '--rw-editor-active-line': selectedPreset.activeLine,
        '--rw-editor-active-gutter': selectedPreset.activeGutter,
        '--rw-editor-selection': selectedPreset.selection,
        '--rw-editor-cursor': selectedPreset.cursor,
    };
});

const languageExtension = computed(() => {
    const language = String(props.language || 'text').toLowerCase();

    if (
        language === 'html' ||
        language === 'blade' ||
        language === 'safe_blade'
    ) {
        return html();
    }

    if (language === 'javascript' || language === 'js' || language === 'json') {
        return javascript();
    }

    if (language === 'php') {
        return php();
    }

    if (language === 'sql') {
        return sql({ dialect: MySQL });
    }

    if (language === 'css') {
        return css();
    }

    if (language === 'markdown' || language === 'md') {
        return markdown({ addKeymap: true });
    }

    if (language === 'robots') {
        return null;
    }

    return null;
});

const syntaxHighlightExtension = computed(() => {
    const selectedTheme = String(props.theme || 'default').toLowerCase();

    if (selectedTheme === 'graphite') {
        return syntaxHighlighting(
            HighlightStyle.define([
                { tag: tags.keyword, color: '#f59e0b', fontWeight: '600' },
                { tag: tags.tagName, color: '#fca5a5', fontWeight: '600' },
                { tag: tags.angleBracket, color: '#9ca3af' },
                { tag: [tags.name, tags.variableName], color: '#e5e7eb' },
                { tag: [tags.number, tags.bool], color: '#fbbf24' },
                { tag: tags.unit, color: '#fde68a' },
                {
                    tag: [tags.string, tags.special(tags.string)],
                    color: '#86efac',
                },
                {
                    tag: [tags.comment, tags.lineComment, tags.blockComment],
                    color: '#9ca3af',
                    fontStyle: 'italic',
                },
                { tag: [tags.typeName, tags.className], color: '#fca5a5' },
                {
                    tag: [tags.propertyName, tags.attributeName],
                    color: '#93c5fd',
                },
                { tag: tags.namespace, color: '#c4b5fd' },
                {
                    tag: tags.invalid,
                    color: '#fecaca',
                    textDecoration: 'underline',
                },
                {
                    tag: [tags.operator, tags.punctuation, tags.separator],
                    color: '#d1d5db',
                },
                {
                    tag: [
                        tags.function(tags.variableName),
                        tags.function(tags.propertyName),
                    ],
                    color: '#fdba74',
                },
            ]),
        );
    }

    return syntaxHighlighting(
        HighlightStyle.define([
            { tag: tags.keyword, color: '#38bdf8', fontWeight: '600' },
            { tag: tags.tagName, color: '#f472b6', fontWeight: '600' },
            { tag: tags.angleBracket, color: '#94a3b8' },
            { tag: [tags.name, tags.variableName], color: '#e2e8f0' },
            { tag: [tags.number, tags.bool], color: '#f59e0b' },
            { tag: tags.unit, color: '#facc15' },
            { tag: [tags.string, tags.special(tags.string)], color: '#22c55e' },
            {
                tag: [tags.comment, tags.lineComment, tags.blockComment],
                color: '#94a3b8',
                fontStyle: 'italic',
            },
            { tag: [tags.typeName, tags.className], color: '#fca5a5' },
            { tag: [tags.propertyName, tags.attributeName], color: '#93c5fd' },
            { tag: tags.namespace, color: '#c4b5fd' },
            {
                tag: tags.invalid,
                color: '#fecaca',
                textDecoration: 'underline',
            },
            {
                tag: [tags.operator, tags.punctuation, tags.separator],
                color: '#cbd5e1',
            },
            {
                tag: [
                    tags.function(tags.variableName),
                    tags.function(tags.propertyName),
                ],
                color: '#fb923c',
            },
        ]),
    );
});

const editorExtensions = computed(() => {
    const baseExtensions = [
        lineNumbers(),
        highlightActiveLineGutter(),
        highlightActiveLine(),
        drawSelection(),
        bracketMatching(),
        closeBrackets(),
        autocompletion(),
        EditorState.tabSize.of(Math.max(1, Number(props.tabSize || 2))),
    ];

    if (props.lineWrapping) {
        baseExtensions.push(EditorView.lineWrapping);
    }

    if (languageExtension.value) {
        baseExtensions.push(languageExtension.value);
    }

    if (syntaxHighlightExtension.value) {
        baseExtensions.push(syntaxHighlightExtension.value);
    }

    if (Array.isArray(props.extensions) && props.extensions.length > 0) {
        baseExtensions.push(...props.extensions);
    }

    return baseExtensions;
});

function emitUpdate(value) {
    emit('update:modelValue', String(value || ''));
}
</script>

<style scoped>
.rw-code-editor-wrapper {
    width: 100%;
    border: 1px solid var(--rw-editor-border, rgb(51 65 85));
    border-radius: 0.375rem;
    overflow: hidden;
    background: var(--rw-editor-bg, rgb(2 6 23));
}

.rw-code-editor {
    height: 100%;
}

.rw-code-editor :deep(.cm-editor) {
    height: 100%;
    font-family:
        ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas,
        'Liberation Mono', 'Courier New', monospace;
    font-size: 12px;
    background: var(--rw-editor-bg, rgb(2 6 23));
    color: var(--rw-editor-fg, rgb(226 232 240));
}

.rw-code-editor :deep(.cm-scroller) {
    overflow: auto;
}

.rw-code-editor :deep(.cm-content) {
    caret-color: var(--rw-editor-cursor, rgb(226 232 240));
}

.rw-code-editor :deep(.cm-gutters) {
    background: var(--rw-editor-gutter-bg, rgb(15 23 42));
    color: var(--rw-editor-gutter-fg, rgb(148 163 184));
    border-right: 1px solid var(--rw-editor-border, rgb(51 65 85));
}

.rw-code-editor :deep(.cm-activeLineGutter) {
    background: var(--rw-editor-active-gutter, rgb(30 41 59));
}

.rw-code-editor :deep(.cm-activeLine) {
    background: var(--rw-editor-active-line, rgb(15 23 42 / 0.55));
}

.rw-code-editor :deep(.cm-selectionBackground) {
    background: var(--rw-editor-selection, rgb(37 99 235 / 0.4)) !important;
}

.rw-code-editor :deep(.cm-cursor) {
    border-left-color: var(--rw-editor-cursor, rgb(226 232 240));
}
</style>
