<template>
    <AdminLayout :suppress-flash="true">
        <Head :title="t('sql_editor.meta.page_title', 'Database SQL editor')" />

        <Card class="overflow-hidden rounded-none border border-slate-200 bg-white shadow-none">
            <CardHeader class="px-4 py-4 sm:px-5">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div class="flex min-w-0 items-start gap-3">
                        <div
                            class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-blue-50 text-blue-700 ring-1 ring-blue-100"
                            aria-hidden="true"
                        >
                            <span class="mdi mdi-code-braces text-2xl" />
                        </div>
                        <div class="min-w-0">
                            <CardTitle class="text-lg font-semibold text-slate-900">
                                {{ t('sql_editor.page.title', 'Database SQL editor') }}
                            </CardTitle>
                            <CardDescription class="mt-1 text-sm text-slate-400">
                                {{
                                    t(
                                        'sql_editor.page.subtitle',
                                        'Run read-only SQL and, when allowed, destructive DML statements.',
                                    )
                                }}
                            </CardDescription>
                        </div>
                    </div>
                    <div class="flex flex-wrap justify-end gap-2">
                        <Button
                            variant="outline"
                            size="icon"
                            class="border-slate-300 text-slate-950 shadow-none hover:bg-slate-50 hover:text-slate-950"
                            :aria-label="t('actions.back', 'Back')"
                            :title="t('actions.back', 'Back')"
                            @click="goBack"
                        >
                            <span class="mdi mdi-arrow-left-circle text-lg" />
                        </Button>
                        <RwActionButton
                            :label="t('sql_editor.actions.execute', 'Execute')"
                            icon="mdi-play-circle-outline"
                            tone="new"
                            :loading="runningExecute"
                            :disabled="!canExecute"
                            @click="runExecute"
                        />
                    </div>
                </div>
            </CardHeader>

            <div class="border-t border-slate-200" />

            <div
                v-if="warningMessage || executionFlash.message || queryError"
                class="grid gap-2 px-4 pt-4 sm:px-5"
            >
                <RwFlashMessage
                    v-if="warningMessage"
                    type="warning"
                    :message="warningMessage"
                />

                <RwFlashMessage
                    v-if="executionFlash.message"
                    :type="executionFlash.type"
                    :message="executionFlash.message"
                />

                <RwFlashMessage
                    v-if="queryError"
                    type="danger"
                    :message="queryError"
                />
            </div>

            <CardContent class="grid min-w-0 gap-4 px-4 pb-5 pt-4 sm:px-5">
                <Card class="rw-flat-card-clear min-w-0 overflow-hidden">
                    <CardHeader>
                        <CardTitle class="text-base">{{
                            t('sql_editor.query.title', 'SQL query')
                        }}</CardTitle>
                        <CardDescription>
                            {{
                                t(
                                    'sql_editor.query.subtitle',
                                    'Only one query is allowed per execution.',
                                )
                            }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="grid min-w-0 gap-3">
                        <div class="sql-editor-wrap w-full min-w-0">
                            <Codemirror
                                v-model="form.query"
                                :extensions="editorExtensions"
                                :autofocus="true"
                                :indent-with-tab="true"
                                :tab-size="2"
                                :placeholder="
                                    t(
                                        'sql_editor.query.placeholder',
                                        'Write your SQL query here...',
                                    )
                                "
                                class="sql-editor"
                            />
                        </div>

                        <div
                            class="sql-resize-divider"
                            :class="{ 'is-active': isResizing }"
                            role="separator"
                            aria-orientation="horizontal"
                            @mousedown="startResize"
                            @touchstart="startResize"
                        >
                            <div class="sql-resize-line"></div>
                            <div class="sql-resize-handle"></div>
                        </div>
                    </CardContent>
                </Card>

                <Card
                    v-if="runMode === 'readonly'"
                    class="rw-flat-card-clear min-w-0 overflow-hidden"
                >
                    <CardHeader>
                        <CardTitle class="text-base">{{
                            t('sql_editor.results.title', 'Result')
                        }}</CardTitle>
                        <CardDescription>
                            {{
                                t(
                                    'sql_editor.results.row_count',
                                    ':count rows',
                                    { count: readonlyRowCount },
                                )
                            }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="min-w-0 p-0">
                        <div class="sql-results-wrap">
                            <RwTable
                                :data="readonlyTableData"
                                :columns="readonlyColumns"
                                :global-search="false"
                                :row-quantity-select="false"
                                :horizontal-scroll="true"
                                :initial-height="'520px'"
                                table-id="rw-db-sql-results"
                            />
                        </div>
                    </CardContent>
                </Card>
            </CardContent>
        </Card>

        <Dialog v-model:open="destructiveDialogOpen">
            <DialogContent class="gap-0 overflow-hidden p-0 shadow-none sm:max-w-lg">
                <DialogHeader class="px-4 py-4 pr-12 sm:px-5 sm:pr-12">
                    <DialogTitle>{{
                        t(
                            'sql_editor.confirm.title',
                            'Confirm destructive query',
                        )
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            t(
                                'sql_editor.confirm.description',
                                'This query changes data in the database. Only confirm if you fully understand the impact.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="border-t border-slate-200 px-4 py-3 sm:px-5">
                    <Button
                        type="button"
                        variant="outline"
                        class="shadow-none"
                        @click="cancelDestructiveExecution"
                    >
                        {{ t('sql_editor.actions.cancel', 'Cancel') }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        class="border-red-300 text-red-700 shadow-none hover:bg-red-50 hover:text-red-800"
                        :disabled="runningExecute"
                        @click="executeDestructiveConfirmed"
                    >
                        {{
                            t(
                                'sql_editor.actions.execute_anyway',
                                'Execute anyway',
                            )
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RwActionButton from '@/Components/RwActionButton.vue';
import RwFlashMessage from '@/Components/RwFlashMessage.vue';
import RwTable from '@/Components/RwTable.vue';
import { autocompletion } from '@codemirror/autocomplete';
import { sql, MySQL } from '@codemirror/lang-sql';
import { EditorView } from '@codemirror/view';
import { Codemirror } from 'vue-codemirror';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    canRunDestructiveSql: {
        type: Boolean,
        default: false,
    },
    sqlMetadata: {
        type: Object,
        default: () => ({ tables: [] }),
    },
    warningMessage: {
        type: String,
        default: '',
    },
});

const page = usePage();

const uiMessages = computed(() => {
    const messages = page.props?.app?.translations?.db_diagram_ui ?? {};

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

function t(key, fallback = '', replacements = {}) {
    const translated = getNestedTranslation(uiMessages.value, key);
    const resolved =
        typeof translated === 'string' && translated.trim() !== ''
            ? translated
            : fallback || key;

    return Object.entries(replacements).reduce(
        (carry, [token, replacement]) => {
            return carry.replaceAll(`:${token}`, String(replacement ?? ''));
        },
        resolved,
    );
}

const form = useForm({
    query: '',
});

const queryError = ref('');
const executionFlash = ref({ type: '', message: '' });
const runMode = ref('none');
const rows = ref([]);
const columns = ref([]);
const runningExecute = ref(false);
const destructiveDialogOpen = ref(false);
const pendingDestructiveQuery = ref('');
const editorHeight = ref(320);
const isResizing = ref(false);
const resizeStartY = ref(0);
const resizeStartHeight = ref(320);

const editorHeightCss = computed(() => `${editorHeight.value}px`);

const sqlTables = computed(() => {
    const source = Array.isArray(props.sqlMetadata?.tables)
        ? props.sqlMetadata.tables
        : [];

    return source
        .filter((table) => table && typeof table.name === 'string')
        .map((table) => ({
            name: String(table.name),
            columns: Array.isArray(table.columns)
                ? table.columns
                      .filter(
                          (column) => column && typeof column.name === 'string',
                      )
                      .map((column) => ({
                          name: String(column.name),
                          type: String(column.type || column.data_type || ''),
                      }))
                : [],
        }));
});

const tableNameByLower = computed(() => {
    const map = new Map();
    for (const table of sqlTables.value) {
        map.set(table.name.toLowerCase(), table.name);
    }

    return map;
});

const columnsByTableLower = computed(() => {
    const map = new Map();
    for (const table of sqlTables.value) {
        map.set(table.name.toLowerCase(), table.columns);
    }

    return map;
});

const tableCompletionOptions = computed(() => {
    return sqlTables.value.map((table) => ({
        label: table.name,
        type: 'class',
        detail: t('sql_editor.autocomplete.fields_count', ':count fields', {
            count: table.columns.length,
        }),
    }));
});

const sqlKeywordOptions = [
    'SELECT',
    'FROM',
    'WHERE',
    'JOIN',
    'LEFT JOIN',
    'RIGHT JOIN',
    'INNER JOIN',
    'OUTER JOIN',
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
    'INSERT',
    'INTO',
    'VALUES',
    'UPDATE',
    'SET',
    'DELETE',
    'REPLACE',
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

const readonlyTableData = computed(() => ({
    data: rows.value,
    total: rows.value.length,
}));

const warningMessage = computed(() => {
    const backendFallback = String(props.warningMessage || '').trim();

    return t(
        'sql_editor.warning_message',
        backendFallback ||
            'Warning: this SQL editor works directly on the database. Only use it when you fully understand the impact on data and relationships.',
    ).trim();
});

const normalizedQuery = computed(() => String(form.query || '').trim());

const canExecute = computed(() => {
    return !runningExecute.value && normalizedQuery.value !== '';
});

const readonlyRowCount = computed(() => rows.value.length);

const readonlyColumns = computed(() => {
    return columns.value.map((key) => ({
        key,
        label: key,
        type: inferColumnType(rows.value[0]?.[key]),
        sortable: true,
        selected: true,
    }));
});

function inferColumnType(value) {
    if (typeof value === 'number') {
        return 'number';
    }

    if (typeof value === 'boolean') {
        return 'boolean';
    }

    return 'text';
}

function goBack() {
    router.visit(route('admin.db-diagram'));
}

function sqlCompletionSource(context) {
    const queryText = context.state.doc.toString();
    const before = queryText.slice(Math.max(0, context.pos - 700), context.pos);
    const word = context.matchBefore(/[A-Za-z_][A-Za-z0-9_]*/);
    const explicit = context.explicit;

    if (!explicit && !word && !before.endsWith('.')) {
        return null;
    }

    const aliasMap = extractAliasMap(queryText);

    const tableContextMatch =
        /\b(from|join|update|into)\s+([A-Za-z_][A-Za-z0-9_]*)?$/i.exec(before);
    if (tableContextMatch) {
        const prefix = tableContextMatch[2] || '';
        const options = filterCompletionOptions(
            tableCompletionOptions.value,
            prefix,
        );

        return buildCompletionResult(
            context.pos - prefix.length,
            options,
            /^[A-Za-z0-9_]*$/,
        );
    }

    const aliasDotMatch =
        /([A-Za-z_][A-Za-z0-9_]*)\.\s*([A-Za-z_][A-Za-z0-9_]*)?$/.exec(before);

    if (aliasDotMatch) {
        const alias = String(aliasDotMatch[1] || '').toLowerCase();
        const prefix = String(aliasDotMatch[2] || '');
        const tableName =
            aliasMap.get(alias) || tableNameByLower.value.get(alias) || null;

        if (!tableName) {
            return null;
        }

        const columnOptions = buildColumnOptionsForTable(tableName, false);
        const options = filterCompletionOptions(columnOptions, prefix);

        return buildCompletionResult(
            context.pos - prefix.length,
            options,
            /^[A-Za-z0-9_]*$/,
        );
    }

    const prefix = word?.text || '';
    const from = word ? word.from : context.pos;
    const referencedTables = Array.from(new Set(aliasMap.values()));
    const scopedColumnOptions = referencedTables.flatMap((tableName) =>
        buildColumnOptionsForTable(tableName, true),
    );

    const options = filterCompletionOptions(
        [
            ...sqlKeywordOptions,
            ...tableCompletionOptions.value,
            ...scopedColumnOptions,
        ],
        prefix,
    );

    return buildCompletionResult(from, options, /^[A-Za-z0-9_]*$/);
}

function buildCompletionResult(from, options, validFor) {
    if (!Array.isArray(options) || options.length === 0) {
        return null;
    }

    return {
        from,
        options: options.slice(0, 300),
        validFor,
    };
}

function filterCompletionOptions(options, prefix) {
    const needle = String(prefix || '').toLowerCase();
    if (needle === '') {
        return options;
    }

    return options.filter((option) =>
        String(option.label || '')
            .toLowerCase()
            .startsWith(needle),
    );
}

function buildColumnOptionsForTable(tableName, includeTableNameInApply) {
    const tableKey = String(tableName || '').toLowerCase();
    const realTableName = tableNameByLower.value.get(tableKey) || tableName;
    const columns = columnsByTableLower.value.get(tableKey) || [];

    return columns.map((column) => ({
        label: column.name,
        type: 'property',
        detail: column.type
            ? `${realTableName}.${column.name} (${column.type})`
            : `${realTableName}.${column.name}`,
        apply: includeTableNameInApply
            ? `${realTableName}.${column.name}`
            : column.name,
    }));
}

function extractAliasMap(sqlText) {
    const stripped = stripSqlStringsAndComments(String(sqlText || ''));
    const map = new Map();
    const tablePattern = /`?([A-Za-z_][A-Za-z0-9_]*)`?/;
    const aliasPattern = /`?([A-Za-z_][A-Za-z0-9_]*)`?/;

    const fromJoinRegex = new RegExp(
        `\\b(?:from|join)\\s+${tablePattern.source}(?:\\s+(?:as\\s+)?${aliasPattern.source})?`,
        'gi',
    );
    const updateRegex = new RegExp(
        `\\bupdate\\s+${tablePattern.source}(?:\\s+(?:as\\s+)?${aliasPattern.source})?`,
        'gi',
    );

    for (const regex of [fromJoinRegex, updateRegex]) {
        let match = regex.exec(stripped);
        while (match) {
            const tableRaw = String(match[1] || '');
            const aliasRaw = String(match[2] || tableRaw);
            const tableName =
                tableNameByLower.value.get(tableRaw.toLowerCase()) || tableRaw;

            if (tableName !== '' && aliasRaw !== '') {
                map.set(aliasRaw.toLowerCase(), tableName);
                map.set(tableName.toLowerCase(), tableName);
            }

            match = regex.exec(stripped);
        }
    }

    return map;
}

function stripSqlStringsAndComments(sqlText) {
    let stripped = sqlText.replace(/'(?:[^'\\]|\\.)*'|"(?:[^"\\]|\\.)*"/g, '');
    stripped = stripped.replace(/--.*(\r?\n|$)/g, '');
    stripped = stripped.replace(/\/\*[\s\S]*?\*\//g, '');

    return stripped.trim();
}

function getClientY(event) {
    if (typeof event?.clientY === 'number') {
        return event.clientY;
    }

    if (event?.touches?.length > 0) {
        return event.touches[0].clientY;
    }

    return null;
}

function getMaxEditorHeight() {
    if (typeof window === 'undefined') {
        return 720;
    }

    return Math.max(260, Math.floor(window.innerHeight * 0.72));
}

function clampEditorHeight(value) {
    const minHeight = 180;
    const maxHeight = getMaxEditorHeight();

    return Math.min(maxHeight, Math.max(minHeight, Math.round(value)));
}

function startResize(event) {
    const clientY = getClientY(event);
    if (typeof clientY !== 'number') {
        return;
    }

    isResizing.value = true;
    resizeStartY.value = clientY;
    resizeStartHeight.value = editorHeight.value;

    window.addEventListener('mousemove', onResizeMove);
    window.addEventListener('mouseup', stopResize);
    window.addEventListener('touchmove', onResizeMove, { passive: false });
    window.addEventListener('touchend', stopResize);

    if (event?.preventDefault) {
        event.preventDefault();
    }
}

function onResizeMove(event) {
    if (!isResizing.value) {
        return;
    }

    const clientY = getClientY(event);
    if (typeof clientY !== 'number') {
        return;
    }

    const delta = clientY - resizeStartY.value;
    editorHeight.value = clampEditorHeight(resizeStartHeight.value + delta);

    if (event?.cancelable) {
        event.preventDefault();
    }
}

function stopResize() {
    if (!isResizing.value) {
        return;
    }

    isResizing.value = false;
    window.removeEventListener('mousemove', onResizeMove);
    window.removeEventListener('mouseup', stopResize);
    window.removeEventListener('touchmove', onResizeMove);
    window.removeEventListener('touchend', stopResize);
}

function detectStatementType(sqlText) {
    const stripped = stripSqlStringsAndComments(String(sqlText || ''));
    const match = /^\s*([a-z]+)/i.exec(stripped);

    return String(match?.[1] || '').toLowerCase();
}

function isDestructiveStatement(statementType) {
    return ['insert', 'update', 'delete', 'replace'].includes(statementType);
}

function clearExecutionFlash() {
    executionFlash.value = { type: '', message: '' };
}

async function runExecute() {
    const query = normalizedQuery.value;
    const statementType = detectStatementType(query);
    const destructive = isDestructiveStatement(statementType);

    queryError.value = '';
    clearExecutionFlash();
    runMode.value = 'none';

    if (query === '') {
        queryError.value = t(
            'sql_editor.errors.query_required',
            'Enter a SQL query first.',
        );

        return;
    }

    if (destructive && !props.canRunDestructiveSql) {
        queryError.value = t(
            'sql_editor.errors.no_destructive_permission',
            'You do not have permission to run destructive SQL.',
        );

        return;
    }

    if (destructive) {
        pendingDestructiveQuery.value = query;
        destructiveDialogOpen.value = true;

        return;
    }

    await executeReadonly(query);
}

async function executeReadonly(query) {
    runningExecute.value = true;

    try {
        const response = await window.axios.post(
            route('admin.db-diagram.sql-execute'),
            {
                query,
            },
        );

        rows.value = Array.isArray(response?.data?.rows)
            ? response.data.rows
            : [];
        columns.value = Array.isArray(response?.data?.columns)
            ? response.data.columns
            : [];
        runMode.value = 'readonly';
    } catch (error) {
        queryError.value =
            error?.response?.data?.errors?.query?.[0] ||
            error?.response?.data?.message ||
            t(
                'sql_editor.errors.execute_failed',
                'The query could not be executed.',
            );
    } finally {
        runningExecute.value = false;
    }
}

async function executeDestructiveConfirmed() {
    const query = String(pendingDestructiveQuery.value || '');
    if (query === '') {
        destructiveDialogOpen.value = false;

        return;
    }

    runningExecute.value = true;

    try {
        const response = await window.axios.post(
            route('admin.db-diagram.sql-execute-destructive'),
            {
                query,
            },
        );

        const affectedRows = Number(response?.data?.affectedRows ?? 0);
        rows.value = [];
        columns.value = [];
        runMode.value = 'none';
        executionFlash.value = {
            type: 'warning',
            message: t(
                'sql_editor.feedback.destructive_success',
                'Destructive query executed. Changed records: :count',
                { count: affectedRows },
            ),
        };
        destructiveDialogOpen.value = false;
        pendingDestructiveQuery.value = '';
    } catch (error) {
        queryError.value =
            error?.response?.data?.errors?.query?.[0] ||
            error?.response?.data?.message ||
            t(
                'sql_editor.errors.execute_failed',
                'The query could not be executed.',
            );
    } finally {
        runningExecute.value = false;
    }
}

function cancelDestructiveExecution() {
    destructiveDialogOpen.value = false;
    pendingDestructiveQuery.value = '';
}

function handleGlobalKeydown(event) {
    const isEnter = event?.key === 'Enter';
    const hasModifier = Boolean(event?.ctrlKey || event?.metaKey);

    if (!isEnter || !hasModifier || !canExecute.value) {
        return;
    }

    if (event?.preventDefault) {
        event.preventDefault();
    }

    void runExecute();
}

onMounted(() => {
    window.addEventListener('keydown', handleGlobalKeydown);
});

onBeforeUnmount(() => {
    stopResize();
    window.removeEventListener('keydown', handleGlobalKeydown);
});
</script>

<style scoped>
.sql-editor-wrap {
    width: 100%;
    min-width: 0;
    max-width: 100%;
    border: 1px solid rgb(203 213 225);
    border-radius: 0.5rem;
    overflow: hidden;
    --sql-editor-height: v-bind(editorHeightCss);
}

.sql-editor {
    width: 100%;
    min-width: 0;
}

.sql-editor :deep(.cm-editor) {
    width: 100%;
    min-width: 0;
    max-width: 100%;
    height: var(--sql-editor-height);
    min-height: 180px;
    font-family:
        ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas,
        'Liberation Mono', 'Courier New', monospace;
    font-size: 0.9rem;
}

.sql-editor :deep(.cm-scroller) {
    width: 100%;
    max-width: 100%;
    height: 100%;
}

.sql-editor :deep(.cm-content) {
    padding: 10px 12px;
}

.sql-editor :deep(.cm-tooltip-autocomplete) {
    max-width: 520px;
}

.sql-resize-divider {
    position: relative;
    height: 18px;
    cursor: row-resize;
    touch-action: none;
}

.sql-resize-line {
    position: absolute;
    left: 0;
    right: 0;
    top: 50%;
    transform: translateY(-50%);
    height: 1px;
    background-color: rgb(226 232 240);
}

.sql-resize-handle {
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    width: 64px;
    height: 6px;
    border-radius: 999px;
    background-color: rgb(59 130 246 / 45%);
    transition: background-color 120ms ease;
}

.sql-resize-divider.is-active .sql-resize-handle,
.sql-resize-divider:hover .sql-resize-handle {
    background-color: rgb(37 99 235 / 70%);
}

.sql-results-wrap {
    width: 100%;
    min-width: 0;
    overflow-x: auto;
}
</style>
