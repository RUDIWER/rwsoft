<script setup>
import RwActionButton from '@/Components/RwActionButton.vue';
import { autocompletion } from '@codemirror/autocomplete';
import { sql, MySQL } from '@codemirror/lang-sql';
import { EditorView } from '@codemirror/view';
import { usePage } from '@inertiajs/vue3';
import { Codemirror } from 'vue-codemirror';
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

/**
 * Herbruikbare SQL editor + inspectie feedbackkaart.
 * Gebruikt in QueryForm (SQL mode).
 */

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    errorMessage: {
        type: String,
        default: '',
    },
    inspectResult: {
        type: Object,
        default: () => ({
            valid: null,
            message: '',
            bindings: [],
        }),
    },
    rows: {
        type: Number,
        default: 12,
    },
    sqlStructure: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['update:modelValue', 'inspect', 'import-bindings']);

const page = usePage();

const uiMessages = computed(() => {
    const messages = page.props?.app?.translations?.query_builder_ui ?? {};

    return messages && typeof messages === 'object' ? messages : {};
});

function getNestedTranslation(source, key) {
    if (!source || typeof source !== 'object') {
        return null;
    }

    return String(key || '')
        .split('.')
        .filter((segment) => segment !== '')
        .reduce((carry, segment) => {
            if (!carry || typeof carry !== 'object') {
                return null;
            }

            if (!Object.prototype.hasOwnProperty.call(carry, segment)) {
                return null;
            }

            return carry[segment];
        }, source);
}

function t(key, fallback = '') {
    const translated = getNestedTranslation(uiMessages.value, key);

    if (typeof translated === 'string' && translated.trim() !== '') {
        return translated;
    }

    return fallback || key;
}

function onInspect() {
    emit('inspect');
}

function onImportBindings() {
    emit('import-bindings');
}

const sqlTables = computed(() => {
    return Object.entries(props.sqlStructure || {})
        .filter(([tableName]) => String(tableName || '').trim() !== '')
        .map(([tableName, meta]) => {
            const normalizedTable = String(tableName || '').trim();
            const rawFields = Array.isArray(meta?.fields) ? meta.fields : [];

            return {
                name: normalizedTable,
                columns: rawFields
                    .map((field) => String(field || '').trim())
                    .filter((field) => field !== ''),
            };
        })
        .filter((table) => table.name !== '');
});

const tableCompletionOptions = computed(() => {
    return sqlTables.value.map((table) => ({
        label: table.name,
        type: 'class',
        detail: `${table.columns.length} velden`,
    }));
});

const columnCompletionOptions = computed(() => {
    const options = [];

    sqlTables.value.forEach((table) => {
        table.columns.forEach((column) => {
            options.push({
                label: `${table.name}.${column}`,
                type: 'property',
            });
        });
    });

    return options;
});

const sqlKeywordOptions = [
    'SELECT',
    'FROM',
    'WHERE',
    'JOIN',
    'LEFT JOIN',
    'RIGHT JOIN',
    'INNER JOIN',
    'ON',
    'GROUP BY',
    'ORDER BY',
    'HAVING',
    'LIMIT',
    'OFFSET',
    'AS',
    'DISTINCT',
    'UNION',
    'UNION ALL',
    'AND',
    'OR',
    'NOT',
    'IN',
    'IS',
    'NULL',
    'LIKE',
    'BETWEEN',
    'EXISTS',
].map((label) => ({
    label,
    type: 'keyword',
}));

const editorExtensions = computed(() => [
    sql({ dialect: MySQL }),
    autocompletion({
        override: [sqlCompletionSource],
        activateOnTyping: true,
    }),
    EditorView.lineWrapping,
]);

function sqlCompletionSource(context) {
    const word = context.matchBefore(/[\w.]+/);

    if (!word && !context.explicit) {
        return null;
    }

    return {
        from: word ? word.from : context.pos,
        options: [
            ...sqlKeywordOptions,
            ...tableCompletionOptions.value,
            ...columnCompletionOptions.value,
        ],
    };
}
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-center justify-between gap-2">
                <CardTitle>{{
                    t('form.query.sql_card_title', 'SQL query')
                }}</CardTitle>
                <div class="flex items-center gap-2">
                    <RwActionButton
                        :label="t('form.query.inspect', 'Inspecteer SQL')"
                        icon="mdi mdi-stethoscope"
                        tone="neutral"
                        @click="onInspect"
                    />
                    <RwActionButton
                        :label="
                            t(
                                'form.query.import_bindings',
                                'Neem bindings over',
                            )
                        "
                        icon="mdi mdi-download"
                        tone="neutral"
                        :disabled="(inspectResult?.bindings || []).length === 0"
                        @click="onImportBindings"
                    />
                </div>
            </div>
        </CardHeader>
        <CardContent class="space-y-3 p-4">
            <div class="grid gap-1">
                <label class="text-xs text-slate-600">{{
                    t('form.query.sql_label', 'SQL *')
                }}</label>
                <div class="overflow-hidden rounded-md border border-slate-300">
                    <Codemirror
                        :model-value="modelValue"
                        :extensions="editorExtensions"
                        :autofocus="true"
                        :indent-with-tab="true"
                        :tab-size="2"
                        :placeholder="
                            t(
                                'form.query.sql_placeholder',
                                'Schrijf hier je SQL query...',
                            )
                        "
                        class="query-sql-editor"
                        @update:model-value="
                            (value) =>
                                emit('update:modelValue', String(value || ''))
                        "
                    />
                </div>
                <p v-if="errorMessage" class="text-[11px] text-red-600">
                    {{ errorMessage }}
                </p>
            </div>

            <div
                v-if="inspectResult?.valid !== null"
                class="rounded-md border px-3 py-2 text-sm"
                :class="
                    inspectResult?.valid
                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                        : 'border-red-200 bg-red-50 text-red-700'
                "
            >
                <p>
                    {{
                        inspectResult?.message ||
                        t('form.query.inspect_done', 'Inspectie uitgevoerd.')
                    }}
                </p>
                <p
                    v-if="(inspectResult?.bindings || []).length > 0"
                    class="mt-1 text-xs"
                >
                    {{ t('form.query.found_bindings', 'Gevonden bindings:') }}
                    {{ (inspectResult?.bindings || []).join(', ') }}
                </p>
            </div>
        </CardContent>
    </Card>
</template>

<style scoped>
.query-sql-editor {
    min-height: 360px;
}

.query-sql-editor :deep(.cm-editor),
.query-sql-editor :deep(.cm-scroller) {
    min-height: 360px;
    font-family:
        ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas,
        'Liberation Mono', 'Courier New', monospace;
    font-size: 12px;
}

.query-sql-editor :deep(.cm-content) {
    padding: 0.75rem;
}
</style>
