<template>
    <Head
        :title="`${t('runtime.meta_title_prefix', 'Run query')}: ${query.description}`"
    />

    <AdminLayout :suppress-flash="true">
        <Card class="rounded-none shadow-none">
            <CardHeader class="gap-0 border-b border-slate-200 p-0">
                <div
                    class="flex flex-wrap items-start justify-between gap-3 px-4 py-4 sm:px-5"
                >
                    <div class="flex min-w-0 items-start gap-3">
                        <div
                            class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-blue-50 text-blue-700 ring-1 ring-blue-100"
                            aria-hidden="true"
                        >
                            <span class="mdi mdi-database text-2xl" />
                        </div>
                        <div class="min-w-0">
                            <CardTitle class="text-lg">
                                {{ query.description }}
                            </CardTitle>
                            <CardDescription class="mt-1">
                                {{
                                    t(
                                        'runtime.subtitle',
                                        'Runtime result view for the selected query.',
                                    )
                                }}
                            </CardDescription>
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            class="text-slate-950 shadow-none hover:bg-slate-50 hover:text-slate-950"
                            :aria-label="t('actions.back', 'Back')"
                            :title="t('actions.back', 'Back')"
                            @click="goBack"
                        >
                            <span
                                class="mdi mdi-arrow-left-circle text-lg"
                                aria-hidden="true"
                            />
                        </Button>

                        <Button
                            v-if="!isTableOutputMode && canRunTablePreview"
                            type="button"
                            variant="outline"
                            class="gap-2 border-slate-300 text-slate-700 shadow-none hover:bg-slate-50"
                            @click="openTablePreview"
                        >
                            <span
                                class="mdi mdi-table text-base"
                                aria-hidden="true"
                            />
                            {{ t('actions.table', 'Table') }}
                        </Button>

                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            class="border-emerald-200 text-emerald-800 shadow-none hover:bg-emerald-50 hover:text-emerald-900"
                            :aria-label="
                                isTableOutputMode
                                    ? t('actions.execute', 'Execute')
                                    : outputPrimaryActionLabel()
                            "
                            :title="
                                isTableOutputMode
                                    ? t('actions.execute', 'Execute')
                                    : outputPrimaryActionLabel()
                            "
                            :disabled="
                                isExternalReportDataSource || tableLoading
                            "
                            @click="downloadOutput"
                        >
                            <span
                                :class="[
                                    tableLoading && isTableOutputMode
                                        ? 'mdi mdi-loading animate-spin'
                                        : isTableOutputMode
                                          ? 'mdi mdi-reload'
                                          : isChartOutputMode
                                            ? 'mdi mdi-chart-bar'
                                            : 'mdi mdi-download-circle-outline',
                                    'text-lg',
                                ]"
                                aria-hidden="true"
                            />
                        </Button>
                    </div>
                </div>
            </CardHeader>

            <div
                v-if="pageFlash.message"
                class="border-b border-slate-200 px-4 py-3 sm:px-5"
            >
                <RwFlashMessage
                    :type="pageFlash.type"
                    :message="pageFlash.message"
                />
            </div>
            <div
                v-if="tableError"
                class="border-b border-slate-200 px-4 py-3 sm:px-5"
            >
                <RwFlashMessage type="danger" :message="tableError" />
            </div>
            <div
                v-if="tableWarning"
                class="border-b border-slate-200 px-4 py-3 sm:px-5"
            >
                <RwFlashMessage type="warning" :message="tableWarning" />
            </div>
            <div
                v-if="bindingSourceWarning"
                class="border-b border-slate-200 px-4 py-3 sm:px-5"
            >
                <RwFlashMessage
                    type="warning"
                    :message="bindingSourceWarning"
                />
            </div>

            <div
                v-if="hasRequiredBindings && visibleBindingRows.length > 0"
                class="border-b border-slate-200 px-4 py-3 sm:px-5"
            >
                <div
                    class="grid w-full gap-3 rounded border border-slate-200 bg-slate-50 p-3"
                >
                    <h2 class="text-sm font-semibold text-slate-800">
                        {{
                            t(
                                'runtime.missing_variables',
                                'Missing query variables',
                            )
                        }}
                    </h2>
                    <QueryBindingFields
                        :rows="visibleBindingRows"
                        :values="bindings"
                        :has-error="queryBindingHasError"
                        :on-value-change="queryBindingOnValueChange"
                        :is-source-select-type="queryBindingIsSourceSelectType"
                        :is-range-type="queryBindingIsRangeType"
                        :input-type-for-binding="queryBindingInputType"
                        :source-options-for="queryBindingSourceOptionsFor"
                        :source-loading-for="queryBindingSourceLoadingFor"
                        :show-range-parameter-to="
                            queryBindingShowRangeParameterTo
                        "
                    />
                </div>
            </div>

            <CardContent class="p-0">
                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700 sm:px-5"
                >
                    <h2 class="text-sm font-semibold text-slate-800">
                        {{ t('runtime.result', 'Result') }}
                    </h2>
                    <div
                        v-if="tableLoading"
                        class="inline-flex items-center gap-2 text-xs font-medium text-slate-500"
                    >
                        <i
                            class="mdi mdi-loading animate-spin text-base text-blue-700"
                            aria-hidden="true"
                        />
                        <span>{{ t('runtime.loading', 'Loading') }}</span>
                    </div>
                </div>

                <div
                    v-if="!canRunTablePreview"
                    class="flex min-h-[28rem] items-center justify-center p-4"
                >
                    <div
                        class="w-full rounded-md border border-dashed border-amber-300 bg-amber-50 px-4 py-6 text-center text-sm text-amber-800"
                    >
                        {{
                            t(
                                'runtime.external_no_preview',
                                'This report uses external workflow data and has no manual table preview.',
                            )
                        }}
                    </div>
                </div>

                <div
                    v-else-if="tableColumns.length === 0 && !tableLoading"
                    class="flex min-h-[28rem] items-center justify-center p-4"
                >
                    <div
                        class="w-full rounded-md border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500"
                    >
                        {{
                            shouldAutoLoadTable
                                ? t(
                                      'runtime.empty_auto',
                                      'No data loaded yet. Click Execute.',
                                  )
                                : t(
                                      'runtime.empty_table',
                                      'No table preview loaded yet. Click Table.',
                                  )
                        }}
                    </div>
                </div>

                <RwTable
                    v-else-if="canRunTablePreview"
                    table-id="admin-query-run-result-table-v1"
                    :data="tableData"
                    :columns="tableColumns"
                    :server-side="true"
                    :rows-per-page="25"
                    sort-field="id"
                    sort-order="desc"
                    :initial-height="'calc(100vh - 20rem)'"
                    :show-record-count="true"
                    :row-quantity-select="true"
                    :row-options="tableRowOptions"
                    :options="tableOptions"
                    charts="true"
                    excel="true"
                    @change="onTableChange"
                />
            </CardContent>
        </Card>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import QueryBindingFields from '@/Components/Query/QueryBindingFields.vue';
import RwFlashMessage from '@/Components/RwFlashMessage.vue';
import RwTable from '@/Components/RwTable.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import {
    extractBindingNamesFromSql,
    isMissingBindingValue,
    isSystemBindingName,
    normalizeBindingName,
    resolveBindingRowsForRequired,
} from '@/composables/useQueryBindings';
import {
    bindingInputType as resolveBindingInputType,
    isRangeBindingType as isQueryBindingRangeType,
    isSourceSelectBindingType as isQueryBindingSourceSelectType,
    useQueryBindingInputState,
} from '@/composables/useQueryBindingInputState';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref } from 'vue';

const props = defineProps({
    query: { type: Object, required: true },
});

const page = usePage();
const { t } = useAdminTranslations('query_builder_ui');

const tableData = ref({
    data: [],
    total: 0,
    current_page: 1,
    last_page: 1,
});
const tableColumns = ref([]);
const tableColumnSignature = ref('');
const tableLoading = ref(false);
const tableError = ref('');
const tableWarning = ref('');

const paginationState = reactive({
    page: 1,
    rowsPerPage: 25,
    sortField: 'id',
    sortOrder: 'desc',
    global: '',
    filters: {},
    filterModes: {},
    filterTypes: {},
});
const tableOptions = { scrollMode: 'pagination' };
const tableRowOptions = [25, 50, 100, 250];

const bindingRows = computed(() => {
    return Array.isArray(props.query?.binding_rows)
        ? props.query.binding_rows
        : [];
});

const bindingInput = useQueryBindingInputState(async (sourceTableId) => {
    const response = await window.axios.get(
        route('admin.run.queries.binding-source-options'),
        {
            params: {
                source_table_id: Number(sourceTableId),
                limit: 100,
            },
        },
    );

    return Array.isArray(response?.data?.options) ? response.data.options : [];
});
const bindings = bindingInput.values;
const missingBindings = bindingInput.missing;
const bindingSourceWarning = bindingInput.warning;

const requiredBindingNames = computed(() => {
    return extractBindingNamesFromSql(props.query?.query || '').filter(
        (binding) => !isSystemBindingName(binding),
    );
});

const requiredBindingSet = computed(() => new Set(requiredBindingNames.value));

const bindingRowsForExecution = computed(() => {
    return resolveBindingRowsForRequired(
        bindingRows.value,
        requiredBindingNames.value,
    );
});

const missingBindingSet = computed(() => new Set(missingBindings.value));

const hasRequiredBindings = computed(() => {
    return requiredBindingNames.value.length > 0;
});

const isTableOutputMode = computed(
    () => String(props.query?.output_mode || 'table') === 'table',
);

const isExcelOutputMode = computed(
    () => String(props.query?.output_mode || '') === 'excel',
);

const isReportOutputMode = computed(
    () => String(props.query?.output_mode || '') === 'report',
);

const isChartOutputMode = computed(
    () => String(props.query?.output_mode || '') === 'chart',
);

const isExternalReportDataSource = computed(() => {
    return (
        isReportOutputMode.value &&
        String(props.query?.report_data_source || 'query') === 'external'
    );
});

const canRunTablePreview = computed(() => {
    if (
        isTableOutputMode.value ||
        isExcelOutputMode.value ||
        isChartOutputMode.value
    ) {
        return true;
    }

    return isReportOutputMode.value && !isExternalReportDataSource.value;
});

const shouldAutoLoadTable = computed(() => {
    if (!canRunTablePreview.value) {
        return false;
    }

    return isTableOutputMode.value || Boolean(props.query?.force_table);
});

const visibleBindingRows = computed(() => {
    return bindingRowsForExecution.value.filter((row) => rowNeedsInput(row));
});

const pageFlash = computed(() => {
    const flash = page.props?.flash || {};

    if (flash.error) {
        return { type: 'danger', message: flash.error };
    }

    if (flash.warning) {
        return { type: 'warning', message: flash.warning };
    }

    if (flash.status) {
        return { type: 'success', message: flash.status };
    }

    return { type: '', message: '' };
});

function goBack() {
    router.visit(route('admin.queries.builder.index'));
}

function outputPrimaryActionLabel() {
    if (isChartOutputMode.value) {
        return t('runtime.chart', 'Grafiek');
    }

    if (isExcelOutputMode.value) {
        return t('runtime.excel', 'Excel');
    }

    if (isReportOutputMode.value) {
        return t('runtime.report', 'Rapport');
    }

    return t('actions.execute', 'Uitvoeren');
}

function hasMissingBindingsForExecution() {
    return hasRequiredBindings.value && visibleBindingRows.value.length > 0;
}

function buildExecutionBindings() {
    const payload = {};

    requiredBindingNames.value.forEach((binding) => {
        const value = bindingInput.getValue(binding);

        if (isMissingBindingValue(value)) {
            return;
        }

        payload[binding] = String(value).trim();
    });

    return payload;
}

function openTablePreview() {
    if (hasMissingBindingsForExecution()) {
        tableError.value = t(
            'runtime.table_preview_required',
            'Vul eerst alle verplichte query variabelen in voor tabelpreview.',
        );

        return;
    }

    const payload = {
        query: props.query.id,
        __force_table: 1,
        ...buildExecutionBindings(),
    };

    router.visit(route('admin.run.queries.show', payload));
}

function downloadOutput() {
    if (isChartOutputMode.value) {
        if (hasMissingBindingsForExecution()) {
            tableError.value = t(
                'runtime.chart_required',
                'Vul eerst alle verplichte query variabelen in om de grafiek te openen.',
            );

            return;
        }

        const payload = {
            query: props.query.id,
            returnTo: `${window.location.pathname}${window.location.search}`,
            bindings: JSON.stringify(buildExecutionBindings()),
        };

        router.visit(route('admin.run.queries.chart.show', payload));

        return;
    }

    if (isTableOutputMode.value) {
        loadData({ page: 1 });

        return;
    }

    if (isExternalReportDataSource.value) {
        tableError.value = t(
            'runtime.external_report',
            'Dit rapport gebruikt externe workflow-data en kan niet manueel uitgevoerd worden.',
        );

        return;
    }

    if (hasMissingBindingsForExecution()) {
        tableError.value = t(
            'runtime.download_required',
            'Vul eerst alle verplichte query variabelen in om output te downloaden.',
        );

        return;
    }

    const payload = {
        query: props.query.id,
        ...buildExecutionBindings(),
    };

    const targetUrl = isExcelOutputMode.value
        ? route('admin.run.queries.export', payload)
        : route('admin.run.queries.report', payload);

    window.location.href = targetUrl;
}

function fillBindingsFromSchema() {
    bindingRowsForExecution.value.forEach((row) => {
        const parameter = normalizeBindingName(row?.parameter);
        const parameterTo = normalizeBindingName(row?.parameter_to);

        if (parameter === '') {
            if (parameterTo === '') {
                return;
            }
        }

        if (parameter !== '' && requiredBindingSet.value.has(parameter)) {
            bindingInput.ensureValue(parameter, '');
        }

        if (parameterTo !== '' && requiredBindingSet.value.has(parameterTo)) {
            bindingInput.ensureValue(parameterTo, '');
        }
    });
}

function fillBindingsFromUrl() {
    const params = new URLSearchParams(window.location.search);

    requiredBindingNames.value.forEach((binding) => {
        if (!params.has(binding)) {
            return;
        }

        bindingInput.setValue(
            binding,
            String(params.get(binding) || '').trim(),
        );
    });
}

function rowNeedsInput(row) {
    const parameter = normalizeBindingName(row?.parameter);
    const parameterTo = normalizeBindingName(row?.parameter_to);
    const candidates = [parameter, parameterTo].filter((key) =>
        requiredBindingSet.value.has(key),
    );

    if (candidates.length === 0) {
        return false;
    }

    if (missingBindingSet.value.size > 0) {
        return candidates.some((key) => missingBindingSet.value.has(key));
    }

    return candidates.some((key) =>
        isMissingBindingValue(bindingInput.getValue(key)),
    );
}

function queryBindingShowRangeParameterTo(row) {
    const parameterTo = normalizeBindingName(row?.parameter_to);

    if (parameterTo === '' || !requiredBindingSet.value.has(parameterTo)) {
        return false;
    }

    if (missingBindingSet.value.size > 0) {
        return missingBindingSet.value.has(parameterTo);
    }

    return true;
}

function queryBindingIsSourceSelectType(type) {
    return isQueryBindingSourceSelectType(type);
}

function queryBindingIsRangeType(type) {
    return isQueryBindingRangeType(type);
}

function queryBindingInputType(type) {
    return resolveBindingInputType(type);
}

function queryBindingSourceOptionsFor(row, index) {
    return bindingInput.sourceOptionsFor(row, index);
}

function queryBindingSourceLoadingFor(row, index) {
    return bindingInput.sourceLoadingFor(row, index);
}

function queryBindingHasError(parameter) {
    return bindingInput.hasError(parameter);
}

function queryBindingOnValueChange(parameter, value) {
    const bindingKey = normalizeBindingName(parameter);

    if (bindingKey === '') {
        return;
    }

    bindingInput.setValue(bindingKey, value);
}

function defaultColumnLabel(key) {
    return String(key || '')
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function setColumnsFromKeys(keys) {
    const orderedKeys = [...keys];
    const idIndex = orderedKeys.indexOf('id');

    if (idIndex > 0) {
        orderedKeys.splice(idIndex, 1);
        orderedKeys.unshift('id');
    }

    tableColumns.value = orderedKeys.map((key) => ({
        key,
        label: t(`runtime.columns.${key}`, defaultColumnLabel(key)),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
    }));

    tableColumnSignature.value = keys.join('|');
}

function shouldRefreshColumns(keys) {
    if (!Array.isArray(keys) || keys.length === 0) {
        return false;
    }

    if (!Array.isArray(tableColumns.value) || tableColumns.value.length === 0) {
        return true;
    }

    return tableColumnSignature.value !== keys.join('|');
}

async function loadData(overrides = {}) {
    tableLoading.value = true;
    tableError.value = '';
    tableWarning.value = '';
    bindingInput.setMissingBindings([]);

    paginationState.page = Number(overrides.page || paginationState.page || 1);
    paginationState.rowsPerPage = Number(
        overrides.rowsPerPage || paginationState.rowsPerPage || 25,
    );
    paginationState.sortField = String(
        overrides.sortField || paginationState.sortField || '',
    );
    paginationState.sortOrder = String(
        overrides.sortOrder || paginationState.sortOrder || 'asc',
    );
    paginationState.global = String(
        overrides.global ||
            overrides.globalSearch ||
            paginationState.global ||
            '',
    );
    paginationState.filters =
        overrides.filters && typeof overrides.filters === 'object'
            ? { ...overrides.filters }
            : { ...paginationState.filters };
    paginationState.filterModes =
        overrides.filterModes && typeof overrides.filterModes === 'object'
            ? { ...overrides.filterModes }
            : { ...paginationState.filterModes };
    paginationState.filterTypes =
        overrides.filterTypes && typeof overrides.filterTypes === 'object'
            ? { ...overrides.filterTypes }
            : { ...paginationState.filterTypes };

    try {
        const response = await window.axios.post(
            route('admin.run.queries.data', props.query.id),
            {
                page: paginationState.page,
                rowsPerPage: paginationState.rowsPerPage,
                sortField: paginationState.sortField,
                sortOrder: paginationState.sortOrder,
                global: paginationState.global,
                filters: paginationState.filters,
                filterModes: paginationState.filterModes,
                filterTypes: paginationState.filterTypes,
                bindings,
            },
        );

        tableData.value = {
            data: Array.isArray(response?.data?.data) ? response.data.data : [],
            total: Number(response?.data?.total || 0),
            current_page: Number(response?.data?.current_page || 1),
            last_page: Number(response?.data?.last_page || 1),
        };

        const columns = Array.isArray(response?.data?.columns)
            ? response.data.columns
            : [];

        if (shouldRefreshColumns(columns)) {
            setColumnsFromKeys(columns);
        }

        if (response?.data?.truncated) {
            tableWarning.value = t(
                'runtime.truncated',
                'Resultaat werd ingekort om performantie te bewaken.',
            );
        }
    } catch (error) {
        bindingInput.setMissingBindings(
            error?.response?.data?.missing_bindings,
        );

        tableData.value = {
            data: [],
            total: 0,
            current_page: 1,
            last_page: 1,
        };
        tableError.value =
            String(error?.response?.data?.message || '').trim() ||
            t('runtime.run_failed', 'Query uitvoeren mislukt.');
    } finally {
        tableLoading.value = false;
    }
}

function onTableChange(payload) {
    loadData(payload || {});
}

onMounted(() => {
    fillBindingsFromSchema();
    fillBindingsFromUrl();

    bindingInput.loadSourceOptionsForRows(bindingRowsForExecution.value);

    if (
        shouldAutoLoadTable.value &&
        (!hasRequiredBindings.value || visibleBindingRows.value.length === 0)
    ) {
        loadData({ page: 1 });
    }
});
</script>
