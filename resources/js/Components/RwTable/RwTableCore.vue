<script setup>
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import debounce from 'lodash/debounce';
import RwAutocompleteInput from './components/RwAutocompleteInput.vue';
import { validateValueWithRules } from './validation/rules';
import {
    buildRwTableRestoreStateKey,
    clearRwTableReturnMarker,
    normalizeRwTableRestorePath,
    readRwTableReturnMarker,
    shouldApplyRwTableReturnMarker,
} from './utils/restoreState.js';

const props = defineProps({
    data: { type: Object, required: true },
    columns: { type: Array, required: true },
    tableId: { type: String, default: null },
    restoreState: { type: Boolean, default: false },
    idKey: { type: String, default: 'id' },
    initialHeight: { type: String, default: '520px' },
    horizontalScroll: { type: Boolean, default: true },
    checkboxColumn: { type: Boolean, default: false },
    checkedRows: { type: Array, default: () => [] },
    globalSearch: { type: Boolean, default: true },
    searchFieldWidth: { type: [String, Number], default: null },
    searchFieldMinWidth: { type: [String, Number], default: null },
    showRecordCount: { type: Boolean, default: true },
    rowQuantitySelect: { type: Boolean, default: true },
    rowOptions: { type: Array, default: () => [10, 25, 50, 100] },
    menuItems: { type: Array, default: () => [] },
    inlineUpdateRoute: { type: [String, Function], default: null },
    inlineDeleteRoute: { type: [String, Function], default: null },
    beforeInlineDelete: { type: Function, default: null },
    charts: { type: String, default: 'false' },
    excel: { type: String, default: 'false' },
    cellStyle: { type: Function, default: null },
    cellClass: { type: Function, default: null },
    startOnMount: { type: Boolean, default: true },
    initialData: { type: Object, default: null },
    autoEditLastRowFirstField: { type: Boolean, default: false },
    inlineCreate: { type: Boolean, default: false },
    inlineCreateRoute: { type: [String, Function], default: null },
    inlineCreateButton: { type: Boolean, default: false },
    inlineCreateDefaults: { type: [Object, Function], default: () => ({}) },
    manualOrdering: { type: Boolean, default: false },
    manualOrderField: { type: String, default: 'index' },
    rowMenu: { type: Boolean, default: false },
    rowMenuItems: { type: [Array, Function], default: null },
    expandableRows: { type: Boolean, default: false },
    rowCanExpand: { type: Function, default: null },
    expandedRowContent: { type: Function, default: null },
    managed: { type: Boolean, default: false },
    serverSide: { type: Boolean, default: false },
    dataSource: {
        type: Object,
        default: () => ({
            type: 'inertia',
            path: null,
            method: 'get',
            data: null,
            params: {},
            preserve: true,
        }),
    },
    urlSync: { type: String, default: 'auto' },
    columnsParamMode: { type: String, default: 'full' },
    paramMap: {
        type: Object,
        default: () => ({
            page: 'page',
            rowsPerPage: 'rowsPerPage',
            sortField: 'sortField',
            sortOrder: 'sortOrder',
            global: 'global',
            columns: 'columns',
            filters: 'filters',
            filterModes: 'filterModes',
            filterTypes: 'filterTypes',
            selectionFilter: 'selectionFilter',
            selectedRowIds: 'selectedRowIds',
            manualOrdering: 'manualOrdering',
            manualOrderField: 'manualOrderField',
        }),
    },
    responseMap: {
        type: Object,
        default: () => ({
            data: 'data',
            total: 'total',
            current: 'current_page',
            last: 'last_page',
        }),
    },
    options: { type: Object, default: () => ({}) },
    sortField: { type: String, default: 'id' },
    sortOrder: { type: String, default: 'asc' },
    i18n: { type: Object, default: () => ({}) },
    validationMode: { type: String, default: 'both' },
    clientValidationOn: {
        type: [Array, String],
        default: () => ['tab', 'blur', 'save'],
    },
    validationAdapter: { type: Function, default: null },
});

const emit = defineEmits([
    'on-cell-click',
    'on-menu-item-click',
    'on-row-menu-item-click',
    'change',
    'update:checkedRows',
]);

const page = usePage();

function getNestedTranslation(source, key) {
    if (!source || typeof source !== 'object') {
        return null;
    }

    return String(key || '')
        .split('.')
        .filter((part) => part !== '')
        .reduce((carry, part) => {
            if (!carry || typeof carry !== 'object') {
                return null;
            }

            if (!Object.prototype.hasOwnProperty.call(carry, part)) {
                return null;
            }

            return carry[part];
        }, source);
}

function interpolateTranslation(value, replacements = {}) {
    if (typeof value !== 'string') {
        return value;
    }

    return Object.entries(replacements).reduce(
        (carry, [token, replacement]) => {
            return carry.replaceAll(`:${token}`, String(replacement ?? ''));
        },
        value,
    );
}

const rwtableMessages = computed(() => {
    const propMessages = props.i18n?.messages;

    if (propMessages && typeof propMessages === 'object') {
        return propMessages;
    }

    const pageMessages = page?.props?.rwtable?.translations;

    if (pageMessages && typeof pageMessages === 'object') {
        return pageMessages;
    }

    return {};
});

const rwtableLocale = computed(() => {
    const propLocale = String(props.i18n?.locale || '')
        .trim()
        .toLowerCase();

    if (propLocale !== '') {
        return propLocale;
    }

    const pageLocale = String(page?.props?.rwtable?.locale || '')
        .trim()
        .toLowerCase();

    if (pageLocale !== '') {
        return pageLocale;
    }

    return 'en';
});

function t(key, fallback = '', replacements = {}) {
    const translated = getNestedTranslation(rwtableMessages.value, key);
    const resolved =
        typeof translated === 'string' ? translated : fallback || key;

    return interpolateTranslation(resolved, replacements);
}

function localizedLabelFromMap(labelTranslations) {
    if (!labelTranslations || typeof labelTranslations !== 'object') {
        return null;
    }

    const locale = String(rwtableLocale.value || '').toLowerCase();

    if (locale !== '' && typeof labelTranslations[locale] === 'string') {
        return labelTranslations[locale];
    }

    const localeRoot = locale.includes('-') ? locale.split('-')[0] : locale;

    if (
        localeRoot !== '' &&
        typeof labelTranslations[localeRoot] === 'string'
    ) {
        return labelTranslations[localeRoot];
    }

    if (typeof labelTranslations.default === 'string') {
        return labelTranslations.default;
    }

    return null;
}

function columnLabel(column) {
    const fallback = String(
        column?.label ?? column?.key ?? t('common.dash', '-'),
    );

    const fromMap = localizedLabelFromMap(column?.labelTranslations);

    if (typeof fromMap === 'string' && fromMap.trim() !== '') {
        return fromMap;
    }

    const labelKey = String(column?.labelKey || '').trim();

    if (labelKey === '') {
        const columnKey = String(column?.key || '').trim();

        if (columnKey !== '') {
            const sourceColumn = props.columns.find(
                (item) =>
                    String(item?.key || '').trim() === columnKey &&
                    item !== column,
            );

            if (sourceColumn) {
                return columnLabel(sourceColumn);
            }
        }

        return fallback;
    }

    const candidateKeys = labelKey.includes('.')
        ? [labelKey]
        : [`columns.${labelKey}`];

    for (const key of candidateKeys) {
        const translated = getNestedTranslation(rwtableMessages.value, key);

        if (typeof translated === 'string' && translated.trim() !== '') {
            return translated;
        }
    }

    return fallback;
}

const rootRef = ref(null);
const containerRef = ref(null);
const infiniteSentinelRef = ref(null);
const columnMenuRef = ref(null);

const global = ref('');
const currentPage = ref(1);
const rowsPerPage = ref(Number(props.rowOptions[0] ?? 25));
const activeSortField = ref(props.sortField || props.idKey);
const activeSortOrder = ref(props.sortOrder === 'desc' ? 'desc' : 'asc');

const internalRows = ref([]);
const internalMeta = ref({ total: 0, current: 1, last: 1 });
const serverInfiniteRows = ref([]);
const clientInfiniteCount = ref(rowsPerPage.value);

const tableLoading = ref(false);
const mutationLoading = ref(false);
const mutationErrorMessage = ref('');
const createFieldErrors = ref({});
const editFieldErrors = ref({});
const toolbarMenuOpen = ref(false);
const columnMenuOpen = ref(false);
const openFilterKey = ref(null);
const rowMenuOpenId = ref(null);
const hoveredRowId = ref(null);
const hasKeyboardContext = ref(false);
const hasAppliedInitialData = ref(false);

const activeRowIndex = ref(0);
const editingRowId = ref(null);
const editingColumnKey = ref(null);
const editingValue = ref('');
const editingExtraUpdates = ref({});
const editCommitPromise = ref(null);

const createRowVisible = ref(false);
const createDraft = ref({});
const createInsertAboveId = ref(null);

const columnConfig = ref([]);
const filterValues = ref({});
const filterOptionValues = ref({});
const filterModes = ref({});
const filterTypes = ref({});
const selectionFilter = ref('none');
const draggedColumnKey = ref(null);
const dragOverColumnKey = ref(null);

const showExcelDialog = ref(false);
const excelViewMode = ref('list');
const savedExports = ref([]);
const currentExport = ref({
    id: null,
    description: '',
    format: 'xlsx',
    columns: [],
    csv: {
        delimiter: ';',
        enclosure: '"',
    },
});
const exportLoading = ref(false);
const exportSuccessMessage = ref('');
const exportNoticeMessage = ref('');
const exportFormatItems = computed(() => [
    { title: 'XLSX', value: 'xlsx' },
    { title: 'CSV', value: 'csv' },
]);
const csvDelimiterItems = computed(() => [
    { title: t('excel.csv.delimiter.semicolon', 'Puntkomma (;)'), value: ';' },
    { title: t('excel.csv.delimiter.comma', 'Komma (,)'), value: ',' },
    { title: t('excel.csv.delimiter.tab', 'Tab'), value: 'tab' },
]);
const csvEnclosureItems = computed(() => [
    { title: t('excel.csv.enclosure.none', 'Geen'), value: 'none' },
    {
        title: t('excel.csv.enclosure.double', 'Dubbele aanhaling (")'),
        value: '"',
    },
    {
        title: t('excel.csv.enclosure.single', "Enkele aanhaling (')"),
        value: "'",
    },
]);
const draggedExportColumnKey = ref(null);
const dragOverExportColumnKey = ref(null);

const showChartDialog = ref(false);
const chartViewMode = ref('list');
const savedCharts = ref([]);
const currentChart = ref({
    id: null,
    description: '',
    config: null,
});
const chartLoading = ref(false);
const chartNoticeMessage = ref('');
const chartSuccessMessage = ref('');
const chartRows = ref([]);
const chartPreviewLoading = ref(false);
const chartPreviewError = ref('');
const selectedViewerChartType = ref('');
const chartStageEditRef = ref(null);
const chartStageViewRef = ref(null);
const chartPdfLoading = ref(false);

const editInputElements = new Map();
const createInputElements = new Map();
const filterTextInputElements = new Map();

const pendingFilterFocus = ref(null);
const restorePath =
    typeof window !== 'undefined'
        ? normalizeRwTableRestorePath(window.location.href)
        : null;
const pendingReturnRestoreMarker = ref(null);
const pendingReturnRestoreState = ref(null);
const hasAppliedReturnRestore = ref(false);
const pendingReturnRestoreAnchor = ref(null);
let restoreMarkerCleanupTimer = null;

const MIN_COLUMN_WIDTH = 80;
const UTILITY_COLUMN_WIDTH = 40;
const OPTION_FILTER_CONTAINS_MODE = 'option_contains';
const OPTION_FILTER_EQUALS_MODE = 'option_equals';
const STICKY_BODY_BACKGROUND = 'rgb(255 255 255)';
const CREATE_ROW_STICKY_BACKGROUND = 'rgb(239 246 255)';
const Z_LAYERS = Object.freeze({
    toolbar: 50,
    toolbarOverlay: 60,
    stickyHeader: 40,
    stickyUtilityHeader: 36,
    stickyDataHeader: 32,
    rowMenuOverlay: 120,
    headerFilterOverlay: 30,
    stickyUtilityBody: 18,
    stickyDataBody: 16,
    resizeHandle: 38,
});
const DEFAULT_CLIENT_VALIDATION_TRIGGERS = Object.freeze([
    'tab',
    'blur',
    'save',
]);

const columnWidths = ref({});
const lockCount = ref(0);
const configPanelOpen = ref(false);
const columnMenuMaxHeight = ref(0);
const expandedRowIds = ref([]);

const localUsePagination = ref(props.options?.scrollMode !== 'infinite');
const localHorizontalScroll = ref(Boolean(props.horizontalScroll));
const localRowQuantitySelect = ref(Boolean(props.rowQuantitySelect));
const localShowRecordCount = ref(Boolean(props.showRecordCount));
const localHeight = ref(String(props.initialHeight || '520px'));

let infiniteObserver = null;
let resizingState = null;

const isServerMode = computed(() => props.serverSide || props.managed);
const isInfiniteMode = computed(() => !localUsePagination.value);
const tableDomIdBase = computed(() => normalizeDomId(storageKey()));
const globalSearchInputId = computed(
    () => `${tableDomIdBase.value}-global-search`,
);
const rowsPerPageSelectId = computed(
    () => `${tableDomIdBase.value}-rows-per-page`,
);
const tableWidthPx = computed(() => {
    let totalWidth = 0;

    if (props.checkboxColumn) {
        totalWidth += UTILITY_COLUMN_WIDTH;
    }

    if (props.expandableRows) {
        totalWidth += UTILITY_COLUMN_WIDTH;
    }

    selectedColumns.value.forEach((column) => {
        totalWidth += getColumnWidth(column.key);
    });

    return totalWidth;
});

const tableStyle = computed(() => {
    if (localHorizontalScroll.value) {
        return {
            width: `max(100%, ${tableWidthPx.value}px)`,
        };
    }

    return {
        width: '100%',
    };
});

const resolvedTableHeight = computed(() =>
    normalizeHeightValue(localHeight.value),
);
const toolbarStyle = Object.freeze({ zIndex: Z_LAYERS.toolbar });
const toolbarOverlayStyle = Object.freeze({ zIndex: Z_LAYERS.toolbarOverlay });
const columnMenuOverlayStyle = computed(() => {
    const style = {
        ...toolbarOverlayStyle,
    };

    if (columnMenuMaxHeight.value > 0) {
        style.maxHeight = `${columnMenuMaxHeight.value}px`;
    }

    return style;
});
const stickyHeaderStyle = Object.freeze({
    zIndex: Z_LAYERS.stickyHeader,
    boxShadow: 'inset 0 -1px 0 rgb(203 213 225)',
});
const headerFilterOverlayStyle = Object.freeze({
    zIndex: Z_LAYERS.headerFilterOverlay,
});
const rowMenuOverlayStyle = Object.freeze({ zIndex: Z_LAYERS.rowMenuOverlay });
const resolvedSearchFieldWidth = computed(() =>
    normalizeSizeValue(props.searchFieldWidth),
);
const resolvedSearchFieldMinWidth = computed(() =>
    normalizeSizeValue(props.searchFieldMinWidth),
);
const searchFieldContainerClass = computed(() =>
    resolvedSearchFieldWidth.value
        ? 'min-w-0 flex-none'
        : resolvedSearchFieldMinWidth.value
          ? 'flex-1'
          : 'min-w-56 flex-1',
);
const searchFieldContainerStyle = computed(() => {
    const style = {};

    if (resolvedSearchFieldWidth.value) {
        style.width = resolvedSearchFieldWidth.value;
        style.maxWidth = '100%';
    }

    if (resolvedSearchFieldMinWidth.value) {
        style.minWidth = resolvedSearchFieldMinWidth.value;
    }

    return Object.keys(style).length > 0 ? style : null;
});

const normalizedMenuItems = computed(() => {
    return Array.isArray(props.menuItems) ? props.menuItems : [];
});

const CHART_TYPE_ITEMS = computed(() => [
    { value: 'bar', label: t('charts.type_items.bar', 'Staaf') },
    { value: 'line', label: t('charts.type_items.line', 'Lijn') },
    { value: 'pie', label: t('charts.type_items.pie', 'Taart') },
    { value: 'doughnut', label: t('charts.type_items.doughnut', 'Donut') },
    { value: 'bar3d', label: t('charts.type_items.bar3d', 'Staaf 3D') },
    { value: 'line3d', label: t('charts.type_items.line3d', 'Lijn 3D') },
    {
        value: 'bar3d_webgl',
        label: t('charts.type_items.bar3d_webgl', 'Staaf 3D (WebGL)'),
    },
    {
        value: 'line3d_webgl',
        label: t('charts.type_items.line3d_webgl', 'Lijn 3D (WebGL)'),
    },
]);

const CHART_AGGREGATE_ITEMS = computed(() => [
    {
        value: 'count',
        label: t('charts.aggregate_items.count', 'Aantal (count)'),
    },
    { value: 'sum', label: t('charts.aggregate_items.sum', 'Som (sum)') },
    {
        value: 'avg',
        label: t('charts.aggregate_items.avg', 'Gemiddelde (avg)'),
    },
    { value: 'min', label: t('charts.aggregate_items.min', 'Minimum (min)') },
    { value: 'max', label: t('charts.aggregate_items.max', 'Maximum (max)') },
]);

const CHART_SORT_DIRECTION_ITEMS = computed(() => [
    { value: 'desc', label: t('charts.sort_direction_items.desc', 'Aflopend') },
    { value: 'asc', label: t('charts.sort_direction_items.asc', 'Oplopend') },
]);

const CHART_ORIENTATION_ITEMS = computed(() => [
    {
        value: 'vertical',
        label: t('charts.orientation_items.vertical', 'Verticaal'),
    },
    {
        value: 'horizontal',
        label: t('charts.orientation_items.horizontal', 'Horizontaal'),
    },
]);

const CHART_COLOR_PALETTE = Object.freeze([
    '#2563eb',
    '#16a34a',
    '#dc2626',
    '#d97706',
    '#0891b2',
    '#7c3aed',
    '#db2777',
    '#475569',
]);

let echartsModule = null;
let echartsGlLoaded = false;
let chartInstance = null;
let html2pdfModule = null;

function defaultChartConfig() {
    return {
        version: 1,
        builder: {
            dataset: {
                x_field: '',
                metric_field: '',
                aggregate: 'count',
                series_field: '',
                limit: 25,
                sort_direction: 'desc',
            },
            chart: {
                type: 'bar',
                orientation: 'vertical',
                stacked: false,
                show_legend: true,
            },
            presentation: {
                allow_chart_type_change: false,
            },
        },
    };
}

const excelEnabled = computed(() => String(props.excel) === 'true');
const chartEnabled = computed(() => String(props.charts) === 'true');
const exportTableIdentifier = computed(() => {
    if (typeof props.tableId === 'string' && props.tableId.trim() !== '') {
        return normalizeDomId(props.tableId.trim());
    }

    const fallback = props.columns
        .map((column) => String(column?.key ?? 'col'))
        .join('-');

    return normalizeDomId(fallback);
});
const exportEndpointPrefix = computed(() => {
    const rawPath = String(props.dataSource?.path || '').trim();
    const match = rawPath.match(/^\/?([^/?#]+)/);

    if (match?.[1]) {
        return `/${match[1]}`;
    }

    return '/admin';
});
const exportSelectedColumns = computed(() =>
    (currentExport.value.columns || []).filter((column) => column.selected),
);
const isExportValid = computed(() => {
    const description = String(currentExport.value.description || '').trim();
    if (description === '' || exportSelectedColumns.value.length === 0) {
        return false;
    }

    if (currentExport.value.format !== 'csv') {
        return true;
    }

    return (
        [';', ',', 'tab'].includes(currentExport.value.csv?.delimiter) &&
        ['none', '"', "'"].includes(currentExport.value.csv?.enclosure)
    );
});

const chartFieldItems = computed(() => {
    return (Array.isArray(props.columns) ? props.columns : []).map((column) => {
        const key = String(column?.key ?? '');

        return {
            value: key,
            label: String(column?.label ?? key ?? '-'),
        };
    });
});

const chartCurrentDataset = computed(() => {
    return currentChart.value.config?.builder?.dataset || {};
});

const chartCurrentChartSettings = computed(() => {
    return currentChart.value.config?.builder?.chart || {};
});

const chartAggregateMode = computed(() => {
    return String(chartCurrentDataset.value.aggregate || 'count');
});

const chartRequiresMetricField = computed(() => {
    return chartAggregateMode.value !== 'count';
});

const chartEffectiveType = computed(() => {
    if (chartViewMode.value === 'view' && selectedViewerChartType.value) {
        return selectedViewerChartType.value;
    }

    return String(chartCurrentChartSettings.value.type || 'bar');
});

const isChartValid = computed(() => {
    const description = String(currentChart.value.description || '').trim();
    const xField = String(chartCurrentDataset.value.x_field || '').trim();
    const metricField = String(
        chartCurrentDataset.value.metric_field || '',
    ).trim();

    if (description === '' || xField === '') {
        return false;
    }

    if (chartRequiresMetricField.value && metricField === '') {
        return false;
    }

    return true;
});

const chartPreviewData = computed(() => {
    return buildChartPreviewData(chartRows.value, currentChart.value.config);
});

const chartHasRenderableData = computed(() => {
    return (
        Array.isArray(chartPreviewData.value?.labels) &&
        chartPreviewData.value.labels.length > 0 &&
        Array.isArray(chartPreviewData.value?.series) &&
        chartPreviewData.value.series.length > 0
    );
});

const selectedColumns = computed(() => {
    return columnConfig.value.filter((column) => column.selected !== false);
});

const editableColumns = computed(() => {
    return selectedColumns.value.filter((column) => Boolean(column.editable));
});

const hasActiveFilters = computed(() => {
    return Object.keys(activeFiltersPayload.value).length > 0;
});

const activeFiltersPayload = computed(() => {
    const filters = {};

    const keys = new Set([
        ...Object.keys(filterValues.value),
        ...Object.keys(filterOptionValues.value),
        ...Object.keys(filterModes.value),
    ]);

    keys.forEach((key) => {
        const mode = resolveFilterModeForColumn(key);
        const value = isOptionFilterMode(mode)
            ? filterOptionValues.value[key]
            : filterValues.value[key];

        if (isFilterValueEmpty(value)) {
            return;
        }

        filters[key] = value;
    });

    return filters;
});

const activeFilterModesPayload = computed(() => {
    const modes = {};

    Object.keys(activeFiltersPayload.value).forEach((key) => {
        modes[key] = resolveFilterModeForColumn(key);
    });

    return modes;
});

const activeFilterTypesPayload = computed(() => {
    const types = {};

    Object.keys(activeFiltersPayload.value).forEach((key) => {
        types[key] = filterTypes.value[key] ?? 'text';
    });

    return types;
});

const requestFiltersPayload = computed(() => {
    const mapped = {};

    Object.entries(activeFiltersPayload.value).forEach(([columnKey, value]) => {
        const targetKey = resolveFilterRequestKey(columnKey);
        mapped[targetKey] = value;
    });

    return mapped;
});

const requestFilterModesPayload = computed(() => {
    const mapped = {};

    Object.entries(activeFilterModesPayload.value).forEach(
        ([columnKey, mode]) => {
            const targetKey = resolveFilterRequestKey(columnKey);
            mapped[targetKey] = mode;
        },
    );

    return mapped;
});

const requestFilterTypesPayload = computed(() => {
    const mapped = {};

    Object.entries(activeFilterTypesPayload.value).forEach(
        ([columnKey, type]) => {
            const targetKey = resolveFilterRequestKey(columnKey);
            mapped[targetKey] = type;
        },
    );

    return mapped;
});

const clientRows = computed(() => {
    let rows = [...internalRows.value];

    if (global.value.trim() !== '') {
        const searchValue = global.value.trim().toLowerCase();

        rows = rows.filter((row) => {
            return selectedColumns.value.some((column) => {
                const value = row[column.key];

                if (value === null || value === undefined) {
                    return false;
                }

                return String(value).toLowerCase().includes(searchValue);
            });
        });
    }

    rows = rows.filter((row) => {
        return Object.keys(activeFiltersPayload.value).every((field) => {
            const mode = activeFilterModesPayload.value[field];
            const type = activeFilterTypesPayload.value[field];
            const filterValue = activeFiltersPayload.value[field];
            const rowValue = resolveRowValueForFilter(row, field, mode);

            return rowMatchesFilter(rowValue, mode, type, filterValue);
        });
    });

    const effectiveSortField = props.manualOrdering
        ? props.manualOrderField
        : activeSortField.value;
    const effectiveSortOrder = props.manualOrdering
        ? 'asc'
        : activeSortOrder.value;

    rows.sort((first, second) => {
        const firstValue = first[effectiveSortField];
        const secondValue = second[effectiveSortField];

        if (firstValue === secondValue) {
            return 0;
        }

        const result = String(firstValue ?? '').localeCompare(
            String(secondValue ?? ''),
            undefined,
            {
                numeric: true,
                sensitivity: 'base',
            },
        );

        return effectiveSortOrder === 'asc' ? result : -result;
    });

    return rows;
});

const renderedRows = computed(() => {
    if (isServerMode.value) {
        if (isInfiniteMode.value) {
            return serverInfiniteRows.value;
        }

        return internalRows.value;
    }

    if (isInfiniteMode.value) {
        return clientRows.value.slice(0, clientInfiniteCount.value);
    }

    const from = (currentPage.value - 1) * rowsPerPage.value;
    const to = from + rowsPerPage.value;

    return clientRows.value.slice(from, to);
});

const totalRows = computed(() => {
    if (isServerMode.value) {
        const fallbackLength = isInfiniteMode.value
            ? serverInfiniteRows.value.length
            : internalRows.value.length;

        return Number(internalMeta.value.total ?? fallbackLength);
    }

    return clientRows.value.length;
});

const lastPage = computed(() => {
    if (isServerMode.value) {
        return Number(internalMeta.value.last ?? 1);
    }

    return Math.max(1, Math.ceil(totalRows.value / rowsPerPage.value));
});

const smartPagination = computed(() => {
    const total = Math.max(1, Number(lastPage.value || 1));
    const current = Math.min(
        Math.max(1, Number(currentPage.value || 1)),
        total,
    );
    const delta = 2;
    const range = [];
    const result = [];

    for (let page = 1; page <= total; page += 1) {
        if (
            page <= 2 ||
            page >= total - 1 ||
            Math.abs(page - current) <= delta
        ) {
            range.push(page);
        }
    }

    let previous = null;

    range.forEach((page) => {
        if (previous && page - previous > 1) {
            result.push('...');
        }

        result.push(page);
        previous = page;
    });

    return result;
});

const hasCreateInsertAboveTarget = computed(() => {
    if (
        createInsertAboveId.value === null ||
        createInsertAboveId.value === undefined
    ) {
        return false;
    }

    return renderedRows.value.some(
        (row) => row?.[props.idKey] === createInsertAboveId.value,
    );
});

const showCreateRowAtBottom = computed(() => {
    if (!createRowVisible.value) {
        return false;
    }

    if (
        createInsertAboveId.value === null ||
        createInsertAboveId.value === undefined
    ) {
        return true;
    }

    return !hasCreateInsertAboveTarget.value;
});

const showExpandColumn = computed(() => props.expandableRows === true);

const tableColspan = computed(
    () =>
        selectedColumns.value.length +
        (props.checkboxColumn ? 1 : 0) +
        (showExpandColumn.value ? 1 : 0),
);

const hasAllVisibleRowsSelected = computed(() => {
    const visibleIds = renderedRows.value
        .map((row) => row[props.idKey])
        .filter((id) => id !== undefined && id !== null);

    if (visibleIds.length === 0) {
        return false;
    }

    return visibleIds.every((id) => props.checkedRows.includes(id));
});

const requestStateSignature = computed(() => {
    return JSON.stringify({
        global: global.value,
        rowsPerPage: rowsPerPage.value,
        sortField: activeSortField.value,
        sortOrder: activeSortOrder.value,
        columns: selectedColumns.value.map((column) => column.key),
        filters: activeFiltersPayload.value,
        modes: activeFilterModesPayload.value,
        types: activeFilterTypesPayload.value,
        selectionFilter: selectionFilter.value,
        checkedRows: props.checkedRows,
        manualOrdering: props.manualOrdering,
        manualOrderField: props.manualOrderField,
    });
});

const debouncedManagedFetch = debounce(() => {
    if (!props.managed) {
        return;
    }

    fetchManaged({ append: false });
}, 250);

watch(
    () => props.columns,
    () => {
        initializeColumnConfig();
        syncColumnLayoutState();
        initializeFilterMetadata();
    },
    { immediate: true, deep: true },
);

watch(
    () => props.data,
    (value) => {
        if (!props.managed) {
            syncFromPayload(value, { append: false });
        }
    },
    { deep: true, immediate: true },
);

watch(
    () => props.initialData,
    (value) => {
        if (!value) {
            return;
        }

        if (props.managed && hasAppliedInitialData.value) {
            return;
        }

        syncFromPayload(value, { append: false });
        hasAppliedInitialData.value = true;
    },
    { deep: true, immediate: true },
);

watch(
    () =>
        renderedRows.value.map((row) => {
            const rowId = row?.[props.idKey];

            if (rowId === null || rowId === undefined) {
                return null;
            }

            return String(rowId);
        }),
    (rowIds) => {
        const validRowIds = new Set(rowIds.filter(Boolean));
        expandedRowIds.value = expandedRowIds.value.filter((rowId) =>
            validRowIds.has(rowId),
        );
    },
    { immediate: true },
);

watch(
    () => props.expandableRows,
    (enabled) => {
        if (!enabled) {
            expandedRowIds.value = [];
        }
    },
);

watch(rowsPerPage, () => {
    rowsPerPage.value = normalizeRowsPerPageValue(rowsPerPage.value);

    if (isInfiniteMode.value) {
        clientInfiniteCount.value = rowsPerPage.value;
    }

    currentPage.value = 1;
    persistStoredConfig();
});

watch(requestStateSignature, () => {
    emit('change', buildRequestPayload(1));

    if (isInfiniteMode.value) {
        resetInfiniteState();
    }

    if (props.managed) {
        if (isInfiniteMode.value) {
            fetchManaged({ append: false, pageOverride: 1 });
            return;
        }

        debouncedManagedFetch();
    }
});

watch(currentPage, () => {
    emit('change', buildRequestPayload());

    if (props.managed && !isInfiniteMode.value) {
        debouncedManagedFetch();
    }

    persistReturnRestoreStateDebounced();
});

watch(activeRowIndex, () => {
    persistReturnRestoreStateDebounced();
});

watch(tableLoading, (loading) => {
    if (loading) {
        return;
    }

    nextTick(() => {
        restorePendingFilterFocus();
    });
});

watch(localUsePagination, async (usePagination, previousValue) => {
    if (usePagination === previousValue) {
        return;
    }

    currentPage.value = 1;

    if (!usePagination) {
        resetInfiniteState();
    }

    if (props.managed) {
        await fetchManaged({ append: false, pageOverride: 1 });
    }

    await nextTick();
    setupInfiniteObserver();
});

watch(
    [
        localUsePagination,
        localHorizontalScroll,
        localRowQuantitySelect,
        localShowRecordCount,
        localHeight,
    ],
    () => {
        persistStoredConfig();
    },
    { deep: true },
);

watch(
    () => renderedRows.value.length,
    async () => {
        normalizeActiveRowIndex();
        await nextTick();

        if (createRowVisible.value && showCreateRowAtBottom.value) {
            scrollCreateRowToBottom();
            focusFirstCreateInput();
        }

        setupInfiniteObserver();
    },
);

watch(columnConfig, persistColumnConfig, { deep: true });

watch(
    () => selectedColumns.value.length,
    (nextLength) => {
        const maxLockCount = Math.max(0, nextLength);
        const clamped = Math.min(Math.max(lockCount.value, 0), maxLockCount);

        if (clamped !== lockCount.value) {
            lockCount.value = clamped;
            persistLockCount();
        }

        clampColumnWidthsToMinimums(true);
    },
);

watch(
    () => localHorizontalScroll.value,
    () => {
        clampColumnWidthsToMinimums(true);
    },
);

watch(createRowVisible, (visible) => {
    if (!visible) {
        createInsertAboveId.value = null;
        createDraft.value = {};
        clearCreateFieldErrors();
        return;
    }

    mutationErrorMessage.value = '';
    clearCreateFieldErrors();
    initializeCreateDraft();
    focusFirstCreateInput();
});

function updateColumnMenuMaxHeight() {
    if (typeof window === 'undefined' || !columnMenuOpen.value) {
        return;
    }

    const menuElement = columnMenuRef.value;

    if (!menuElement) {
        return;
    }

    const viewportHeight =
        window.innerHeight || document.documentElement?.clientHeight || 0;

    if (viewportHeight <= 0) {
        return;
    }

    const menuRect = menuElement.getBoundingClientRect();
    const bottomGap = 12;
    const availableHeight = Math.floor(
        viewportHeight - menuRect.top - bottomGap,
    );
    const fallbackHeight = Math.floor(viewportHeight * 0.8);
    columnMenuMaxHeight.value = Math.max(
        240,
        availableHeight > 0 ? availableHeight : fallbackHeight,
    );
}

function refreshColumnMenuMaxHeight() {
    nextTick(() => {
        requestAnimationFrame(() => {
            updateColumnMenuMaxHeight();
        });
    });
}

function onWindowViewportChange() {
    if (columnMenuOpen.value) {
        updateColumnMenuMaxHeight();
    }

    if (showChartDialog.value) {
        resizeChart();
    }
}

watch(columnMenuOpen, (open) => {
    if (!open) {
        columnMenuMaxHeight.value = 0;
        return;
    }

    refreshColumnMenuMaxHeight();
});

watch(configPanelOpen, () => {
    if (!columnMenuOpen.value) {
        return;
    }

    refreshColumnMenuMaxHeight();
});

watch(showExcelDialog, (open) => {
    if (!open) {
        exportSuccessMessage.value = '';
        exportNoticeMessage.value = '';
        draggedExportColumnKey.value = null;
        dragOverExportColumnKey.value = null;
        return;
    }

    excelViewMode.value = 'list';
    exportSuccessMessage.value = '';
    exportNoticeMessage.value = '';
    void fetchExports();
});

watch(showChartDialog, (open) => {
    if (!open) {
        chartSuccessMessage.value = '';
        chartNoticeMessage.value = '';
        chartPreviewError.value = '';
        chartPdfLoading.value = false;
        selectedViewerChartType.value = '';
        disposeChart();
        return;
    }

    chartViewMode.value = 'list';
    chartSuccessMessage.value = '';
    chartNoticeMessage.value = '';
    chartPreviewError.value = '';
    chartPdfLoading.value = false;
    selectedViewerChartType.value = '';
    void fetchCharts();
});

watch(
    () => chartViewMode.value,
    () => {
        chartPreviewError.value = '';

        if (chartViewMode.value === 'list') {
            chartPdfLoading.value = false;
            disposeChart();
            return;
        }

        void refreshChartPreview();
    },
);

watch(
    () => currentChart.value.config,
    () => {
        if (!showChartDialog.value || chartViewMode.value === 'list') {
            return;
        }

        chartPreviewError.value = '';
        void renderChart();
    },
    { deep: true },
);

watch(
    () => selectedViewerChartType.value,
    () => {
        if (!showChartDialog.value || chartViewMode.value !== 'view') {
            return;
        }

        chartPreviewError.value = '';
        void renderChart();
    },
);

watch(
    () => chartRows.value,
    () => {
        if (!showChartDialog.value || chartViewMode.value === 'list') {
            return;
        }

        chartPreviewError.value = '';
        void renderChart();
    },
    { deep: true },
);

onMounted(async () => {
    initializeReturnRestoreContext();
    initializeStoredConfig();
    initializeColumnConfig();
    initializeColumnLayoutState();
    initializeFilterMetadata();

    if (props.startOnMount && props.managed) {
        fetchManaged({ append: false, pageOverride: 1 });
    }

    if (props.autoEditLastRowFirstField) {
        nextTick(() => {
            startEditLastRowFirstEditableCell();
        });
    }

    window.addEventListener('keydown', onGlobalKeydown, { capture: true });
    window.addEventListener('pointerdown', onGlobalPointerdown, {
        capture: true,
    });
    window.addEventListener('focusin', onGlobalFocusin, { capture: true });
    window.addEventListener('resize', onWindowViewportChange);
    window.addEventListener('scroll', onWindowViewportChange);

    await nextTick();
    if (containerRef.value) {
        try {
            containerRef.value.addEventListener('scroll', onContainerScroll, {
                passive: true,
            });
        } catch {
            containerRef.value.addEventListener('scroll', onContainerScroll);
        }
    }
    setupInfiniteObserver();
    await applyReturnRestoreState();
});

onBeforeUnmount(() => {
    persistReturnRestoreState();
    if (restoreMarkerCleanupTimer) {
        window.clearTimeout(restoreMarkerCleanupTimer);
        restoreMarkerCleanupTimer = null;
    }
    if (infiniteObserver) {
        infiniteObserver.disconnect();
        infiniteObserver = null;
    }

    debouncedManagedFetch.cancel();
    stopResize();
    window.removeEventListener('keydown', onGlobalKeydown, { capture: true });
    window.removeEventListener('pointerdown', onGlobalPointerdown, {
        capture: true,
    });
    window.removeEventListener('focusin', onGlobalFocusin, {
        capture: true,
    });
    window.removeEventListener('resize', onWindowViewportChange);
    window.removeEventListener('scroll', onWindowViewportChange);
    if (containerRef.value) {
        try {
            containerRef.value.removeEventListener(
                'scroll',
                onContainerScroll,
                {
                    passive: true,
                },
            );
        } catch {
            containerRef.value.removeEventListener('scroll', onContainerScroll);
        }
    }
    disposeChart();
});

function initializeColumnConfig() {
    const fallbackConfig = props.columns.map((column) => ({
        ...column,
        selected: column.selected !== false,
    }));

    const stored = readStoredColumnConfig();

    if (!stored) {
        columnConfig.value = fallbackConfig;
        return;
    }

    const selectedMap = stored.selected ?? {};
    const order = Array.isArray(stored.order) ? stored.order : [];

    const byKey = new Map();
    fallbackConfig.forEach((column) => {
        const selected = Object.prototype.hasOwnProperty.call(
            selectedMap,
            column.key,
        )
            ? Boolean(selectedMap[column.key])
            : column.selected !== false;

        byKey.set(column.key, {
            ...column,
            selected,
        });
    });

    const ordered = [];

    order.forEach((key) => {
        if (byKey.has(key)) {
            ordered.push(byKey.get(key));
            byKey.delete(key);
        }
    });

    byKey.forEach((value) => ordered.push(value));
    columnConfig.value = ordered;
}

function initializeColumnLayoutState() {
    const storedWidths = readStoredColumnWidths();
    const nextWidths = {};

    props.columns.forEach((column) => {
        const key = String(column.key);
        const storedWidth = Number(storedWidths?.[key]);
        const minWidth = minimumColumnWidth(key);

        if (Number.isFinite(storedWidth) && storedWidth > 0) {
            nextWidths[key] = Math.max(minWidth, Math.round(storedWidth));
            return;
        }

        nextWidths[key] = defaultColumnWidth(column);
    });

    columnWidths.value = nextWidths;

    const storedLockCount = readStoredLockCount();
    const maxLockCount = Math.max(0, selectedColumns.value.length);
    const clampedLockCount = Math.min(
        Math.max(storedLockCount, 0),
        maxLockCount,
    );
    lockCount.value = clampedLockCount;

    if (clampedLockCount !== storedLockCount) {
        persistLockCount();
    }
}

function syncColumnLayoutState() {
    const nextWidths = {};

    props.columns.forEach((column) => {
        const key = String(column.key);
        const existingWidth = Number(columnWidths.value?.[key]);
        const minWidth = minimumColumnWidth(key);

        if (Number.isFinite(existingWidth) && existingWidth > 0) {
            nextWidths[key] = Math.max(minWidth, Math.round(existingWidth));
            return;
        }

        nextWidths[key] = defaultColumnWidth(column);
    });

    columnWidths.value = nextWidths;

    const maxLockCount = Math.max(0, selectedColumns.value.length);
    const clampedLockCount = Math.min(
        Math.max(lockCount.value, 0),
        maxLockCount,
    );

    if (clampedLockCount !== lockCount.value) {
        lockCount.value = clampedLockCount;
        persistLockCount();
    }
}

function defaultColumnWidth(column) {
    const minWidth = minimumColumnWidthForColumn(column);
    const explicit = Number(column?.width);

    if (Number.isFinite(explicit) && explicit > 0) {
        return Math.max(minWidth, Math.round(explicit));
    }

    if (column?.type === 'boolean') {
        return Math.max(minWidth, 90);
    }

    if (column?.type === 'number') {
        return Math.max(minWidth, 110);
    }

    if (column?.type === 'date' || column?.type === 'datetime') {
        return Math.max(minWidth, 140);
    }

    return Math.max(minWidth, 180);
}

function findColumnByKey(columnKey) {
    return (
        props.columns.find(
            (column) => String(column.key) === String(columnKey),
        ) || null
    );
}

function minimumColumnWidthForColumn(column, columnKey = null) {
    const key = String(columnKey ?? column?.key ?? '');
    const configuredMinimum = Number(column?.minWidth);
    const selectedIndex = selectedColumns.value.findIndex(
        (selectedColumn) => String(selectedColumn.key) === key,
    );
    const showsLockIcon =
        localHorizontalScroll.value &&
        selectedIndex >= 0 &&
        Boolean(lockIconName(selectedIndex));

    let minimum = MIN_COLUMN_WIDTH;

    const controlCount =
        (column?.sortable !== false ? 1 : 0) +
        (column?.filterable ? 1 : 0) +
        (showsLockIcon ? 1 : 0);

    const labelLength = String(column?.label ?? '').trim().length;
    const labelReserve = Math.min(24, Math.max(8, labelLength * 3));
    const controlsReserve = controlCount * 20;
    const spacingReserve = 10;

    minimum = Math.max(
        minimum,
        controlsReserve + labelReserve + spacingReserve,
    );

    if (Number.isFinite(configuredMinimum) && configuredMinimum > 0) {
        minimum = Math.max(minimum, Math.round(configuredMinimum));
    }

    return minimum;
}

function minimumColumnWidth(columnKey) {
    const column = findColumnByKey(columnKey);
    return minimumColumnWidthForColumn(column, columnKey);
}

function clampColumnWidthsToMinimums(shouldPersist = false) {
    const nextWidths = { ...columnWidths.value };
    let hasChanges = false;

    props.columns.forEach((column) => {
        const key = String(column.key);
        const existingWidth = Number(nextWidths[key]);
        const minimum = minimumColumnWidth(key);

        if (!Number.isFinite(existingWidth) || existingWidth <= 0) {
            nextWidths[key] = Math.max(minimum, defaultColumnWidth(column));
            hasChanges = true;
            return;
        }

        const clampedWidth = Math.max(minimum, Math.round(existingWidth));

        if (clampedWidth !== Math.round(existingWidth)) {
            nextWidths[key] = clampedWidth;
            hasChanges = true;
        }
    });

    if (!hasChanges) {
        return;
    }

    columnWidths.value = nextWidths;

    if (shouldPersist) {
        persistColumnWidths();
    }
}

function initializeFilterMetadata() {
    const nextTypes = { ...filterTypes.value };
    const nextModes = { ...filterModes.value };

    props.columns.forEach((column) => {
        const type = normalizeColumnType(column.type);
        nextTypes[column.key] = type;

        if (!nextModes[column.key]) {
            nextModes[column.key] = defaultFilterMode(type);
        }
    });

    filterTypes.value = nextTypes;
    filterModes.value = nextModes;
}

function initializeCreateDraft() {
    const draft = {};

    selectedColumns.value.forEach((column) => {
        if (!column.editable) {
            return;
        }

        const field = column.editField || column.key;

        if (
            Object.prototype.hasOwnProperty.call(column, 'defaultInsertValue')
        ) {
            draft[field] = column.defaultInsertValue;
            return;
        }

        if (column.type === 'boolean') {
            draft[field] = false;
            return;
        }

        if (column.editMultiple === true) {
            draft[field] = [];
            return;
        }

        draft[field] = '';
    });

    const defaults =
        typeof props.inlineCreateDefaults === 'function'
            ? props.inlineCreateDefaults()
            : props.inlineCreateDefaults;

    createDraft.value = {
        ...draft,
        ...(defaults || {}),
    };
}

function startEditLastRowFirstEditableCell() {
    if (editableColumns.value.length === 0 || renderedRows.value.length === 0) {
        return;
    }

    const lastIndex = renderedRows.value.length - 1;
    const row = renderedRows.value[lastIndex];

    activeRowIndex.value = lastIndex;
    startEdit(row, editableColumns.value[0]);
}

function normalizeColumnType(type) {
    if (!type) {
        return 'text';
    }

    if (['number', 'date', 'datetime', 'boolean'].includes(type)) {
        return type;
    }

    return 'text';
}

function defaultFilterMode(type) {
    if (type === 'text') {
        return 'bevat';
    }

    if (type === 'date' || type === 'datetime') {
        return '=';
    }

    return '=';
}

function filterModeOptions(type) {
    if (type === 'text') {
        return [
            { value: 'bevat', label: t('filters.modes.contains', 'Bevat') },
            {
                value: 'bevat niet',
                label: t('filters.modes.not_contains', 'Bevat niet'),
            },
            { value: '=', label: t('filters.modes.equals', 'Is gelijk aan') },
            {
                value: '!=',
                label: t('filters.modes.not_equals', 'Is niet gelijk aan'),
            },
        ];
    }

    if (type === 'date' || type === 'datetime') {
        return [
            { value: '=', label: t('filters.modes.equals', 'Is gelijk aan') },
            {
                value: '!=',
                label: t('filters.modes.not_equals', 'Is niet gelijk aan'),
            },
            { value: '>', label: t('filters.modes.after', 'Na') },
            { value: '<', label: t('filters.modes.before', 'Voor') },
            { value: 'tussen', label: t('filters.modes.between', 'Tussen') },
        ];
    }

    return [
        { value: '=', label: t('filters.modes.equals', 'Is gelijk aan') },
        {
            value: '!=',
            label: t('filters.modes.not_equals', 'Is niet gelijk aan'),
        },
        { value: '>', label: t('filters.modes.greater_than', 'Groter dan') },
        { value: '<', label: t('filters.modes.less_than', 'Kleiner dan') },
    ];
}

function resolveColumnByKey(columnKey) {
    return (
        selectedColumns.value.find((column) => column.key === columnKey) ||
        props.columns.find((column) => column.key === columnKey) ||
        null
    );
}

function defaultOptionFilterMode(column) {
    if (!canUseFilterOptionInput(column)) {
        return '=';
    }

    return OPTION_FILTER_CONTAINS_MODE;
}

function isOptionFilterMode(mode) {
    return (
        mode === OPTION_FILTER_CONTAINS_MODE ||
        mode === OPTION_FILTER_EQUALS_MODE
    );
}

function filterModeOptionsForColumn(column) {
    const type =
        filterTypes.value[column.key] ?? normalizeColumnType(column.type);
    const options = [...filterModeOptions(type)];

    if (type !== 'text' || !hasFilterOptionItems(column)) {
        return options;
    }

    options.push({
        value: OPTION_FILTER_CONTAINS_MODE,
        label:
            column.editMultiple === true
                ? t(
                      'filters.modes.contains_option_all',
                      'Bevat optie (alle gekozen)',
                  )
                : t('filters.modes.contains_option', 'Bevat optie'),
    });
    options.push({
        value: OPTION_FILTER_EQUALS_MODE,
        label:
            column.editMultiple === true
                ? t(
                      'filters.modes.equals_option_exact',
                      'Is gelijk aan optie (exacte set)',
                  )
                : t('filters.modes.equals_option', 'Is gelijk aan optie'),
    });

    return options;
}

function resolveFilterModeForColumn(columnKey) {
    const explicitMode = filterModes.value[columnKey];

    if (typeof explicitMode === 'string' && explicitMode !== '') {
        return explicitMode;
    }

    const column = resolveColumnByKey(columnKey);

    if (column && !isFilterValueEmpty(filterOptionValues.value[columnKey])) {
        return defaultOptionFilterMode(column);
    }

    const type =
        filterTypes.value[columnKey] ?? normalizeColumnType(column?.type);

    return defaultFilterMode(type);
}

function resolveFilterRequestKey(columnKey) {
    const column = resolveColumnByKey(columnKey);
    const mode = resolveFilterModeForColumn(columnKey);

    if (!column || !isOptionFilterMode(mode)) {
        return columnKey;
    }

    if (
        typeof column.filterField === 'string' &&
        column.filterField.trim() !== ''
    ) {
        return column.filterField.trim();
    }

    if (
        typeof column.editField === 'string' &&
        column.editField.trim() !== ''
    ) {
        return column.editField.trim();
    }

    return columnKey;
}

function shouldRenderOptionFilterInput(column) {
    return (
        hasFilterOptionItems(column) &&
        isOptionFilterMode(resolveFilterModeForColumn(column.key))
    );
}

function normalizeFilterOptionSelection(column, value) {
    if (column?.editMultiple === true) {
        return normalizeMultiValue(value)
            .map((item) => (item === null || item === undefined ? '' : item))
            .filter((item) => String(item).trim() !== '');
    }

    if (Array.isArray(value)) {
        return value.length > 0 ? value[0] : '';
    }

    return value ?? '';
}

function normalizeFilterOptionItem(column, item) {
    const valueField =
        column.filterItemValue || column.editItemValue || 'value';
    const titleField =
        column.filterItemTitle || column.editItemTitle || 'title';

    if (item !== null && typeof item === 'object') {
        const valueCandidate =
            item?.[valueField] ??
            item?.value ??
            item?.id ??
            item?.[titleField] ??
            item?.label;
        const titleCandidate =
            item?.[titleField] ??
            item?.label ??
            item?.title ??
            item?.[valueField] ??
            valueCandidate;

        if (
            valueCandidate === undefined ||
            valueCandidate === null ||
            valueCandidate === ''
        ) {
            return null;
        }

        return {
            value: valueCandidate,
            title: String(titleCandidate ?? valueCandidate),
        };
    }

    if (item === undefined || item === null || item === '') {
        return null;
    }

    return {
        value: item,
        title: String(item),
    };
}

function extractOptionSourceValues(column, sourceValue) {
    if (
        sourceValue === null ||
        sourceValue === undefined ||
        sourceValue === ''
    ) {
        return [];
    }

    if (column?.editMultiple === true || Array.isArray(sourceValue)) {
        return normalizeMultiValue(sourceValue);
    }

    return [sourceValue];
}

function uniqueFilterOptionItems(items) {
    const normalized = [];
    const seen = new Set();

    items.forEach((item) => {
        const key = String(item.value);

        if (seen.has(key)) {
            return;
        }

        seen.add(key);
        normalized.push(item);
    });

    return normalized;
}

function deriveUniqueFilterOptionItems(column) {
    const values = uniqueFilterOptionItems(
        internalRows.value
            .flatMap((row) =>
                extractOptionSourceValues(column, row?.[column.key]),
            )
            .map((value) => normalizeFilterOptionItem(column, value))
            .filter((item) => item !== null),
    );

    return values.sort((a, b) =>
        String(a.title).localeCompare(String(b.title)),
    );
}

function filterOptionItemsForColumn(column) {
    const configuredItems = Array.isArray(column?.filterItems)
        ? column.filterItems
        : Array.isArray(column?.editItems)
          ? column.editItems
          : [];

    const normalizedConfiguredItems = uniqueFilterOptionItems(
        configuredItems
            .map((item) => normalizeFilterOptionItem(column, item))
            .filter((item) => item !== null),
    );

    if (normalizedConfiguredItems.length > 0) {
        return normalizedConfiguredItems;
    }

    return deriveUniqueFilterOptionItems(column);
}

function canUseFilterOptionInput(column) {
    if (!column || typeof column !== 'object') {
        return false;
    }

    const type =
        filterTypes.value[column.key] ?? normalizeColumnType(column.type);

    if (type === 'boolean' || type === 'date' || type === 'datetime') {
        return false;
    }

    return (
        Array.isArray(column.filterItems) ||
        column.editInput === 'select' ||
        column.editInput === 'autocomplete'
    );
}

function filterUsesAutocompleteInput(column) {
    if (!canUseFilterOptionInput(column)) {
        return false;
    }

    if (column.filterInput === 'autocomplete') {
        return true;
    }

    return column.editInput === 'autocomplete';
}

function hasFilterOptionItems(column) {
    if (!canUseFilterOptionInput(column)) {
        return false;
    }

    return filterOptionItemsForColumn(column).length > 0;
}

function isOptionFilterActive(columnKey) {
    return !isFilterValueEmpty(filterOptionValues.value[columnKey]);
}

function isTextFilterActive(columnKey) {
    return !isFilterValueEmpty(filterValues.value[columnKey]);
}

function resolveRowValueForFilter(row, columnKey, mode) {
    const column = resolveColumnByKey(columnKey);

    if (!column || !isOptionFilterMode(mode)) {
        return row?.[columnKey];
    }

    const targetField =
        typeof column.filterField === 'string' &&
        column.filterField.trim() !== ''
            ? column.filterField.trim()
            : typeof column.editField === 'string' &&
                column.editField.trim() !== ''
              ? column.editField.trim()
              : columnKey;

    if (Object.prototype.hasOwnProperty.call(row || {}, targetField)) {
        return row?.[targetField];
    }

    return row?.[columnKey];
}

function normalizeComparableToken(value) {
    return String(value ?? '')
        .trim()
        .toLowerCase();
}

function normalizeOptionTokenList(value) {
    return Array.from(
        new Set(
            normalizeMultiValue(value)
                .map((item) => normalizeComparableToken(item))
                .filter((item) => item !== ''),
        ),
    );
}

function rowMatchesOptionFilter(value, mode, filterValue) {
    const selectedTokens = normalizeOptionTokenList(filterValue);

    if (selectedTokens.length === 0) {
        return true;
    }

    const isCandidateMulti =
        Array.isArray(value) ||
        (typeof value === 'string' &&
            value.trim().startsWith('[') &&
            value.trim().endsWith(']'));

    if (isCandidateMulti) {
        const candidateTokens = normalizeOptionTokenList(value);

        if (mode === OPTION_FILTER_EQUALS_MODE) {
            if (candidateTokens.length !== selectedTokens.length) {
                return false;
            }

            return selectedTokens.every((item) =>
                candidateTokens.includes(item),
            );
        }

        return selectedTokens.every((item) => candidateTokens.includes(item));
    }

    const candidateToken = normalizeComparableToken(value);

    if (mode === OPTION_FILTER_EQUALS_MODE) {
        return (
            selectedTokens.length === 1 && candidateToken === selectedTokens[0]
        );
    }

    return selectedTokens.every((item) => candidateToken.includes(item));
}

function rowMatchesFilter(value, mode, type, filterValue) {
    if (isOptionFilterMode(mode)) {
        return rowMatchesOptionFilter(value, mode, filterValue);
    }

    if (type === 'date' || type === 'datetime') {
        const candidate = toDateString(value);

        if (!candidate) {
            return false;
        }

        if (mode === 'tussen') {
            const from = toDateString(filterValue?.from);
            const to = toDateString(filterValue?.to);

            if (!from || !to) {
                return true;
            }

            return candidate >= from && candidate <= to;
        }

        const compareTo = toDateString(filterValue);

        if (!compareTo) {
            return true;
        }

        if (mode === '=') return candidate === compareTo;
        if (mode === '!=') return candidate !== compareTo;
        if (mode === '>') return candidate > compareTo;
        if (mode === '<') return candidate < compareTo;

        return true;
    }

    if (type === 'number') {
        const candidate = Number(value);
        const compareTo = Number(filterValue);

        if (Number.isNaN(compareTo)) {
            return true;
        }

        if (mode === '=') return candidate === compareTo;
        if (mode === '!=') return candidate !== compareTo;
        if (mode === '>') return candidate > compareTo;
        if (mode === '<') return candidate < compareTo;

        return true;
    }

    if (type === 'boolean') {
        const candidate = normalizeBoolean(value);
        const compareTo = normalizeBoolean(filterValue);

        if (compareTo === null) {
            return true;
        }

        if (mode === '!=') {
            return candidate !== compareTo;
        }

        return candidate === compareTo;
    }

    const candidate = String(value ?? '').toLowerCase();
    const compareTo = String(filterValue ?? '').toLowerCase();

    if (mode === 'bevat') return candidate.includes(compareTo);
    if (mode === 'bevat niet') return !candidate.includes(compareTo);
    if (mode === '=') return candidate === compareTo;
    if (mode === '!=') return candidate !== compareTo;

    return true;
}

function toDateString(value) {
    if (!value) {
        return null;
    }

    if (typeof value === 'string') {
        return value.slice(0, 10);
    }

    if (value instanceof Date) {
        return value.toISOString().slice(0, 10);
    }

    return null;
}

function resolveDateTimezoneMode(column) {
    const mode = String(column?.dateAssumeTimezone || 'auto')
        .trim()
        .toLowerCase();

    if (mode === 'utc' || mode === 'local') {
        return mode;
    }

    return 'auto';
}

function resolveDateDisplayFallback(column) {
    if (
        Object.prototype.hasOwnProperty.call(
            column || {},
            'dateDisplayFallback',
        )
    ) {
        const fallback = column?.dateDisplayFallback;

        if (fallback === null || fallback === undefined || fallback === '') {
            return '-';
        }

        return String(fallback);
    }

    return '-';
}

function extractDateParts(rawValue) {
    const match = String(rawValue)
        .trim()
        .match(
            /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?(?:\.(\d{1,3}))?)?$/,
        );

    if (!match) {
        return null;
    }

    const year = Number(match[1]);
    const month = Number(match[2]);
    const day = Number(match[3]);
    const hour = Number(match[4] || 0);
    const minute = Number(match[5] || 0);
    const second = Number(match[6] || 0);
    const millisecond = Number((match[7] || '').padEnd(3, '0') || 0);

    return {
        year,
        month,
        day,
        hour,
        minute,
        second,
        millisecond,
    };
}

function buildDateFromParts(parts, mode) {
    if (!parts) {
        return null;
    }

    const date =
        mode === 'utc'
            ? new Date(
                  Date.UTC(
                      parts.year,
                      parts.month - 1,
                      parts.day,
                      parts.hour,
                      parts.minute,
                      parts.second,
                      parts.millisecond,
                  ),
              )
            : new Date(
                  parts.year,
                  parts.month - 1,
                  parts.day,
                  parts.hour,
                  parts.minute,
                  parts.second,
                  parts.millisecond,
              );

    if (!Number.isFinite(date.getTime())) {
        return null;
    }

    if (mode === 'utc') {
        if (
            date.getUTCFullYear() !== parts.year ||
            date.getUTCMonth() + 1 !== parts.month ||
            date.getUTCDate() !== parts.day ||
            date.getUTCHours() !== parts.hour ||
            date.getUTCMinutes() !== parts.minute ||
            date.getUTCSeconds() !== parts.second
        ) {
            return null;
        }

        return date;
    }

    if (
        date.getFullYear() !== parts.year ||
        date.getMonth() + 1 !== parts.month ||
        date.getDate() !== parts.day ||
        date.getHours() !== parts.hour ||
        date.getMinutes() !== parts.minute ||
        date.getSeconds() !== parts.second
    ) {
        return null;
    }

    return date;
}

function parseDateValueForDisplay(value, column) {
    const timezoneMode = resolveDateTimezoneMode(column);

    if (value instanceof Date) {
        return Number.isFinite(value.getTime())
            ? new Date(value.getTime())
            : null;
    }

    if (typeof value === 'number') {
        const parsedFromNumber = new Date(value);
        return Number.isFinite(parsedFromNumber.getTime())
            ? parsedFromNumber
            : null;
    }

    if (typeof value !== 'string') {
        return null;
    }

    const rawValue = value.trim();

    if (rawValue === '') {
        return null;
    }

    const hasTimezoneInfo = /(?:Z|[+-]\d{2}:?\d{2})$/i.test(rawValue);
    const parts = extractDateParts(rawValue);

    if (parts && !hasTimezoneInfo) {
        const partsMode = timezoneMode === 'utc' ? 'utc' : 'local';
        const parsedFromParts = buildDateFromParts(parts, partsMode);

        if (parsedFromParts) {
            return parsedFromParts;
        }
    }

    const parsedNative = new Date(rawValue);

    if (Number.isFinite(parsedNative.getTime())) {
        return parsedNative;
    }

    if (rawValue.includes(' ')) {
        const isoCandidate = rawValue.replace(' ', 'T');
        const parsedIsoCandidate = new Date(isoCandidate);

        if (Number.isFinite(parsedIsoCandidate.getTime())) {
            return parsedIsoCandidate;
        }
    }

    return null;
}

function padDatePart(value) {
    return String(value).padStart(2, '0');
}

function formatDateByPattern(date, pattern, timezoneMode = 'auto') {
    const useUtc = timezoneMode === 'utc';

    const year = useUtc ? date.getUTCFullYear() : date.getFullYear();
    const month = (useUtc ? date.getUTCMonth() : date.getMonth()) + 1;
    const day = useUtc ? date.getUTCDate() : date.getDate();
    const hour = useUtc ? date.getUTCHours() : date.getHours();
    const minute = useUtc ? date.getUTCMinutes() : date.getMinutes();
    const second = useUtc ? date.getUTCSeconds() : date.getSeconds();

    const tokenMap = {
        yyyy: String(year),
        yy: String(year).slice(-2),
        MM: padDatePart(month),
        M: String(month),
        dd: padDatePart(day),
        d: String(day),
        HH: padDatePart(hour),
        H: String(hour),
        mm: padDatePart(minute),
        m: String(minute),
        ss: padDatePart(second),
        s: String(second),
    };

    return Object.keys(tokenMap)
        .sort((first, second) => second.length - first.length)
        .reduce(
            (formatted, token) => formatted.replaceAll(token, tokenMap[token]),
            pattern,
        );
}

function normalizeBoolean(value) {
    if (value === true || value === 1 || value === '1' || value === 'true') {
        return true;
    }

    if (value === false || value === 0 || value === '0' || value === 'false') {
        return false;
    }

    return null;
}

function isFilterValueEmpty(value) {
    if (value === null || value === undefined || value === '') {
        return true;
    }

    if (Array.isArray(value)) {
        if (value.length === 0) {
            return true;
        }

        return value.every((item) => {
            return (
                item === null ||
                item === undefined ||
                String(item).trim() === ''
            );
        });
    }

    if (typeof value === 'object') {
        const hasFrom = Boolean(value.from);
        const hasTo = Boolean(value.to);

        return !hasFrom || !hasTo;
    }

    return false;
}

function extractRows(payload) {
    if (!payload) {
        return [];
    }

    if (Array.isArray(payload)) {
        return payload;
    }

    if (Array.isArray(payload.data)) {
        return payload.data;
    }

    const mapped = payload?.[props.responseMap.data];

    if (Array.isArray(mapped)) {
        return mapped;
    }

    return [];
}

function syncFromPayload(
    payload,
    options = { append: false, targetPage: null },
) {
    const rows = extractRows(payload);
    const total =
        payload?.[props.responseMap.total] ?? payload?.total ?? rows.length;
    const current =
        payload?.[props.responseMap.current] ??
        payload?.current_page ??
        options.targetPage ??
        1;
    const last =
        payload?.[props.responseMap.last] ??
        payload?.last_page ??
        Math.max(1, Math.ceil((total || rows.length) / rowsPerPage.value));

    internalMeta.value = {
        total: Number(total ?? rows.length),
        current: Number(current ?? 1),
        last: Number(last ?? 1),
    };

    internalRows.value = rows;

    if (isInfiniteMode.value && options.append) {
        appendServerRows(rows);
    } else {
        serverInfiniteRows.value = [...rows];
    }

    if (props.managed) {
        currentPage.value = Number(current ?? 1);
    }
}

function appendServerRows(rows) {
    const merged = [...serverInfiniteRows.value];
    const seen = new Set(
        merged
            .map((row) => row[props.idKey])
            .filter((id) => id !== null && id !== undefined),
    );

    rows.forEach((row) => {
        const rowId = row[props.idKey];

        if (rowId === undefined || rowId === null) {
            merged.push(row);
            return;
        }

        if (seen.has(rowId)) {
            return;
        }

        seen.add(rowId);
        merged.push(row);
    });

    serverInfiniteRows.value = merged;
}

function buildRequestPayload(pageOverride = null) {
    const targetPage = pageOverride ?? currentPage.value;
    const params = {
        [props.paramMap.page]: targetPage,
        [props.paramMap.rowsPerPage]: rowsPerPage.value,
        [props.paramMap.sortField]: activeSortField.value,
        [props.paramMap.sortOrder]: activeSortOrder.value,
        [props.paramMap.global]: global.value,
        [props.paramMap.filters]: requestFiltersPayload.value,
        [props.paramMap.filterModes]: requestFilterModesPayload.value,
        [props.paramMap.filterTypes]: requestFilterTypesPayload.value,
        [props.paramMap.selectionFilter]: selectionFilter.value,
        [props.paramMap.selectedRowIds]: props.checkedRows,
    };

    if (props.paramMap.manualOrdering) {
        params[props.paramMap.manualOrdering] = props.manualOrdering ? 1 : 0;
    }

    if (props.paramMap.manualOrderField) {
        params[props.paramMap.manualOrderField] = props.manualOrderField;
    }

    if (props.columnsParamMode === 'keys') {
        params[props.paramMap.columns] = selectedColumns.value.map(
            (column) => column.key,
        );
    } else if (props.columnsParamMode === 'full') {
        params[props.paramMap.columns] = selectedColumns.value;
    }

    return params;
}

async function fetchManaged(options = { append: false, pageOverride: null }) {
    if (!props.dataSource?.path) {
        return;
    }

    const append = Boolean(options.append);
    const targetPage = options.pageOverride ?? currentPage.value;

    tableLoading.value = true;

    const payload = {
        ...buildRequestPayload(targetPage),
        ...resolveDataSourceParams(),
    };

    try {
        if ((props.dataSource.type || 'inertia') === 'axios') {
            const axiosInstance = getAxios();

            const method = (props.dataSource.method || 'get').toLowerCase();
            const response = await axiosInstance({
                method,
                url: props.dataSource.path,
                params: method === 'get' ? payload : undefined,
                data: method !== 'get' ? payload : undefined,
            });

            syncFromPayload(response.data, { append, targetPage });
            tableLoading.value = false;
            return;
        }

        router.visit(props.dataSource.path, {
            method: props.dataSource.method || 'get',
            data: payload,
            preserveState: props.dataSource.preserve ?? true,
            preserveScroll: true,
            replace: props.urlSync === 'none',
            only: props.dataSource.data ? [props.dataSource.data] : undefined,
            onSuccess: (page) => {
                if (props.dataSource.data) {
                    syncFromPayload(page.props[props.dataSource.data], {
                        append,
                        targetPage,
                    });
                    return;
                }

                syncFromPayload(page.props, { append, targetPage });
            },
            onFinish: () => {
                tableLoading.value = false;
            },
        });
    } catch (error) {
        tableLoading.value = false;
        throw error;
    }
}

function resolveDataSourceParams() {
    if (typeof props.dataSource?.params === 'function') {
        return props.dataSource.params() || {};
    }

    return props.dataSource?.params || {};
}

function exportEndpoint() {
    return `${exportEndpointPrefix.value}/rw-table-exports/${encodeURIComponent(exportTableIdentifier.value)}`;
}

function chartEndpoint() {
    return `${exportEndpointPrefix.value}/rw-table-charts/${encodeURIComponent(exportTableIdentifier.value)}`;
}

function toggleExcelDialog() {
    const nextState = !showExcelDialog.value;
    showExcelDialog.value = nextState;

    if (nextState) {
        showChartDialog.value = false;
    }
}

function toggleChartDialog() {
    const nextState = !showChartDialog.value;
    showChartDialog.value = nextState;

    if (nextState) {
        showExcelDialog.value = false;
    }
}

function normalizeChartType(type) {
    const normalized = String(type || 'bar').toLowerCase();
    const allowed = CHART_TYPE_ITEMS.value.map((item) => item.value);

    return allowed.includes(normalized) ? normalized : 'bar';
}

function normalizeChartAggregate(aggregate) {
    const normalized = String(aggregate || 'count').toLowerCase();
    const allowed = CHART_AGGREGATE_ITEMS.value.map((item) => item.value);

    return allowed.includes(normalized) ? normalized : 'count';
}

function normalizeChartSortDirection(direction) {
    return String(direction || 'desc').toLowerCase() === 'asc' ? 'asc' : 'desc';
}

function normalizeChartOrientation(orientation) {
    return String(orientation || 'vertical').toLowerCase() === 'horizontal'
        ? 'horizontal'
        : 'vertical';
}

function normalizeChartLimit(limit) {
    const numericLimit = Number(limit);

    if (!Number.isFinite(numericLimit)) {
        return 25;
    }

    return Math.min(500, Math.max(1, Math.round(numericLimit)));
}

function chartTypeLabel(type) {
    const normalized = normalizeChartType(type);
    const item = CHART_TYPE_ITEMS.value.find(
        (candidate) => candidate.value === normalized,
    );

    return item?.label || normalized;
}

function buildChartPdfFilename() {
    const description = String(currentChart.value?.description || '')
        .trim()
        .toLowerCase();
    const slug = description
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

    return `${slug || t('charts.pdf.default_filename', 'grafiek')}.pdf`;
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function normalizeChartConfig(rawConfig) {
    const defaults = defaultChartConfig();
    const config =
        rawConfig && typeof rawConfig === 'object' && !Array.isArray(rawConfig)
            ? rawConfig
            : {};

    const builderConfig =
        config.builder &&
        typeof config.builder === 'object' &&
        !Array.isArray(config.builder)
            ? config.builder
            : {};

    const datasetConfig =
        builderConfig.dataset &&
        typeof builderConfig.dataset === 'object' &&
        !Array.isArray(builderConfig.dataset)
            ? builderConfig.dataset
            : {};

    const chartConfig =
        builderConfig.chart &&
        typeof builderConfig.chart === 'object' &&
        !Array.isArray(builderConfig.chart)
            ? builderConfig.chart
            : {};

    const presentationConfig =
        builderConfig.presentation &&
        typeof builderConfig.presentation === 'object' &&
        !Array.isArray(builderConfig.presentation)
            ? builderConfig.presentation
            : {};

    const fallbackDataset = {
        x_field: config.xAxis,
        metric_field: config.yAxis,
        aggregate: config.operation,
        series_field: config.seriesField || config.series,
        limit: config.limit,
        sort_direction: config.sortDirection,
    };

    const fallbackChart = {
        type: config.type,
        orientation: config.orientation,
        stacked: config.stacked,
        show_legend: config.showLegend,
    };

    const fallbackPresentation = {
        allow_chart_type_change:
            config.allowViewerChartTypeChange ?? config.allow_chart_type_change,
    };

    const normalizedDataset = {
        ...defaults.builder.dataset,
        ...fallbackDataset,
        ...datasetConfig,
    };

    const normalizedChart = {
        ...defaults.builder.chart,
        ...fallbackChart,
        ...chartConfig,
    };

    const normalizedPresentation = {
        ...defaults.builder.presentation,
        ...fallbackPresentation,
        ...presentationConfig,
    };

    return {
        version: 1,
        builder: {
            dataset: {
                x_field: String(normalizedDataset.x_field || '').trim(),
                metric_field: String(
                    normalizedDataset.metric_field || '',
                ).trim(),
                aggregate: normalizeChartAggregate(normalizedDataset.aggregate),
                series_field: String(
                    normalizedDataset.series_field || '',
                ).trim(),
                limit: normalizeChartLimit(normalizedDataset.limit),
                sort_direction: normalizeChartSortDirection(
                    normalizedDataset.sort_direction,
                ),
            },
            chart: {
                type: normalizeChartType(normalizedChart.type),
                orientation: normalizeChartOrientation(
                    normalizedChart.orientation,
                ),
                stacked: Boolean(normalizedChart.stacked),
                show_legend: normalizedChart.show_legend !== false,
            },
            presentation: {
                allow_chart_type_change:
                    normalizedPresentation.allow_chart_type_change === true,
            },
        },
    };
}

function buildExportColumnsFromCurrent() {
    const columnsByKey = new Map(
        props.columns.map((column) => [String(column.key), column]),
    );

    return columnConfig.value.map((columnState) => {
        const key = String(columnState.key);
        const source = columnsByKey.get(key);

        return {
            key,
            label: source ? columnLabel(source) : (columnState.label ?? key),
            selected: columnState.selected !== false,
            width: getColumnWidth(key),
        };
    });
}

function defaultCsvOptions() {
    return {
        delimiter: ';',
        enclosure: '"',
    };
}

function normalizeExportConfig(config) {
    if (Array.isArray(config)) {
        return {
            format: 'xlsx',
            columns: config,
            csv: defaultCsvOptions(),
        };
    }

    const normalizedConfig = config && typeof config === 'object' ? config : {};
    const csvConfig =
        normalizedConfig.csv && typeof normalizedConfig.csv === 'object'
            ? normalizedConfig.csv
            : {};

    return {
        format: ['xlsx', 'csv'].includes(normalizedConfig.format)
            ? normalizedConfig.format
            : 'xlsx',
        columns: Array.isArray(normalizedConfig.columns)
            ? normalizedConfig.columns
            : [],
        csv: {
            delimiter: [';', ',', 'tab'].includes(csvConfig.delimiter)
                ? csvConfig.delimiter
                : ';',
            enclosure: ['none', '"', "'"].includes(csvConfig.enclosure)
                ? csvConfig.enclosure
                : '"',
        },
    };
}

function resolveCsvDelimiter(delimiter) {
    return delimiter === 'tab' ? '\t' : String(delimiter || ';');
}

function escapeCsvValue(value, delimiterChar, enclosure) {
    const stringValue =
        value === null || value === undefined ? '' : String(value);

    if (enclosure === 'none') {
        if (
            stringValue.includes(delimiterChar) ||
            stringValue.includes('\n') ||
            stringValue.includes('\r')
        ) {
            throw new Error(
                'CSV export zonder tekstomsluiting is niet mogelijk omdat minstens één waarde een scheidingsteken of regeleinde bevat.',
            );
        }

        return stringValue;
    }

    const escapedValue = stringValue
        .split(enclosure)
        .join(enclosure + enclosure);
    const shouldWrap =
        escapedValue.includes(delimiterChar) ||
        escapedValue.includes('\n') ||
        escapedValue.includes('\r') ||
        escapedValue.includes(enclosure);

    return shouldWrap
        ? `${enclosure}${escapedValue}${enclosure}`
        : escapedValue;
}

function createNewExport() {
    currentExport.value = {
        id: null,
        description: '',
        format: 'xlsx',
        columns: buildExportColumnsFromCurrent(),
        csv: defaultCsvOptions(),
    };
    exportSuccessMessage.value = '';
    exportNoticeMessage.value = '';
    excelViewMode.value = 'edit';
}

function prepareExportState(item) {
    const columnsByKey = new Map(
        props.columns.map((column) => [String(column.key), column]),
    );
    const normalizedConfig = normalizeExportConfig(
        item?.config ?? item?.columns ?? [],
    );
    const configColumns = normalizedConfig.columns;

    const normalized = [];
    const seen = new Set();

    configColumns.forEach((column) => {
        const key = String(column?.key ?? '');

        if (!key || seen.has(key)) {
            return;
        }

        seen.add(key);
        const source = columnsByKey.get(key);

        normalized.push({
            key,
            label: column?.label ?? (source ? columnLabel(source) : key),
            selected: Boolean(column?.selected),
            width: Number(column?.width) || getColumnWidth(key),
        });
    });

    props.columns.forEach((column) => {
        const key = String(column.key);

        if (seen.has(key)) {
            return;
        }

        normalized.push({
            key,
            label: columnLabel(column),
            selected: false,
            width: getColumnWidth(key),
        });
    });

    currentExport.value = {
        id: item?.id ?? null,
        description: String(item?.description ?? ''),
        format: normalizedConfig.format,
        columns: normalized,
        csv: normalizedConfig.csv,
    };

    exportSuccessMessage.value = '';
    exportNoticeMessage.value = '';
}

function editExport(item) {
    prepareExportState(item);
    excelViewMode.value = 'edit';
}

async function fetchExports() {
    try {
        const axiosInstance = getAxios();
        const response = await axiosInstance.get(exportEndpoint());
        savedExports.value = Array.isArray(response?.data?.exports)
            ? response.data.exports
            : [];
        exportNoticeMessage.value = '';
    } catch {
        savedExports.value = [];
        exportNoticeMessage.value = t(
            'excel.messages.load_failed',
            'Kon opgeslagen exports niet laden voor deze tabel.',
        );
    }
}

async function saveExport() {
    if (!isExportValid.value) {
        return;
    }

    exportLoading.value = true;

    try {
        const axiosInstance = getAxios();
        const payload = {
            id: currentExport.value.id,
            description: String(currentExport.value.description || '').trim(),
            config: {
                format: currentExport.value.format,
                columns: currentExport.value.columns,
                csv: currentExport.value.csv,
            },
        };
        const response = await axiosInstance.post(exportEndpoint(), payload);

        if (response?.data?.export?.id) {
            currentExport.value.id = response.data.export.id;
        }

        await fetchExports();
        const successMessage = t(
            'excel.messages.saved',
            'Export configuratie opgeslagen.',
        );
        exportSuccessMessage.value = successMessage;
        exportNoticeMessage.value = '';
        setTimeout(() => {
            if (exportSuccessMessage.value === successMessage) {
                exportSuccessMessage.value = '';
            }
        }, 2500);
    } catch {
        exportNoticeMessage.value = t(
            'excel.messages.save_failed',
            'Opslaan van de export configuratie is mislukt.',
        );
    } finally {
        exportLoading.value = false;
    }
}

async function deleteExport(exportId) {
    exportLoading.value = true;

    try {
        const axiosInstance = getAxios();
        await axiosInstance.delete(
            `${exportEndpointPrefix.value}/rw-table-exports/${Number(exportId)}`,
        );
        await fetchExports();
        exportNoticeMessage.value = '';

        if (currentExport.value.id === exportId) {
            excelViewMode.value = 'list';
        }
    } catch {
        exportNoticeMessage.value = t(
            'excel.messages.delete_failed',
            'Verwijderen van de export is mislukt.',
        );
    } finally {
        exportLoading.value = false;
    }
}

function moveExportColumnToTarget(sourceKey, targetKey) {
    if (!sourceKey || !targetKey || sourceKey === targetKey) {
        return;
    }

    const fromIndex = currentExport.value.columns.findIndex(
        (item) => item.key === sourceKey,
    );
    const toIndex = currentExport.value.columns.findIndex(
        (item) => item.key === targetKey,
    );

    if (fromIndex < 0 || toIndex < 0 || fromIndex === toIndex) {
        return;
    }

    const next = [...currentExport.value.columns];
    const [moved] = next.splice(fromIndex, 1);
    next.splice(toIndex, 0, moved);
    currentExport.value.columns = next;
}

function setDragPreviewFromRow(event) {
    if (
        typeof document === 'undefined' ||
        !event?.dataTransfer ||
        !event?.currentTarget
    ) {
        return;
    }

    const source = event.currentTarget;
    const rowElement =
        source?.closest?.('[data-drag-preview-row="true"]') || source;

    if (!(rowElement instanceof HTMLElement)) {
        return;
    }

    const preview = rowElement.cloneNode(true);

    if (!(preview instanceof HTMLElement)) {
        return;
    }

    const rect = rowElement.getBoundingClientRect();

    preview.style.position = 'fixed';
    preview.style.top = '-9999px';
    preview.style.left = '-9999px';
    preview.style.width = `${Math.max(260, Math.round(rect.width))}px`;
    preview.style.pointerEvents = 'none';
    preview.style.background = '#ffffff';
    preview.style.border = '1px solid rgb(148 163 184)';
    preview.style.borderRadius = '8px';
    preview.style.boxShadow = '0 12px 28px rgba(15, 23, 42, 0.18)';
    preview.style.opacity = '0.96';
    preview.style.transform = 'none';
    preview.style.zIndex = '2147483647';

    document.body.appendChild(preview);

    try {
        event.dataTransfer.setDragImage(preview, 20, 16);
    } catch {
        preview.remove();
        return;
    }

    requestAnimationFrame(() => {
        preview.remove();
    });
}

function onExportColumnDragStart(columnKey, event) {
    draggedExportColumnKey.value = columnKey;

    if (event?.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';

        try {
            event.dataTransfer.setData('text/plain', String(columnKey));
            setDragPreviewFromRow(event);
        } catch {
            return;
        }
    }
}

function onExportColumnDragOver(columnKey, event) {
    if (!draggedExportColumnKey.value) {
        return;
    }

    event.preventDefault();
    dragOverExportColumnKey.value = columnKey;

    if (event?.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
}

function onExportColumnDrop(targetKey, event) {
    event.preventDefault();
    const fromTransfer = event?.dataTransfer?.getData?.('text/plain') || null;
    const sourceKey = draggedExportColumnKey.value || fromTransfer;

    moveExportColumnToTarget(sourceKey, targetKey);
    onExportColumnDragEnd();
}

function onExportColumnDragEnd() {
    draggedExportColumnKey.value = null;
    dragOverExportColumnKey.value = null;
}

function exportCellValue(row, column) {
    if (rendersAsIconColumn(column)) {
        const rawValue = getIconValue(column, row);
        return rawValue ?? '';
    }

    if (column.type === 'boolean') {
        return formatBooleanLabel(column, row[column.key]);
    }

    const formatted = formatCellValue(row, column);

    if (
        formatted === '-' &&
        (row[column.key] === null ||
            row[column.key] === undefined ||
            row[column.key] === '')
    ) {
        return '';
    }

    return formatted;
}

async function fetchExportRows() {
    if (!isServerMode.value) {
        return clientRows.value;
    }

    const payload = {
        ...buildRequestPayload(1),
        ...resolveDataSourceParams(),
    };

    payload[props.paramMap.page] = 1;
    payload[props.paramMap.rowsPerPage] = 100000;

    const axiosInstance = getAxios();
    const method = (props.dataSource?.method || 'get').toLowerCase();

    if ((props.dataSource?.type || 'inertia') === 'axios') {
        const response = await axiosInstance({
            method,
            url: props.dataSource.path,
            params: method === 'get' ? payload : undefined,
            data: method !== 'get' ? payload : undefined,
        });

        return extractRows(response.data);
    }

    const headers = {
        'X-Inertia': 'true',
    };

    if (page?.version) {
        headers['X-Inertia-Version'] = page.version;
    }

    if (page?.component) {
        headers['X-Inertia-Partial-Component'] = page.component;
        if (props.dataSource?.data) {
            headers['X-Inertia-Partial-Data'] = props.dataSource.data;
        }
    }

    const response = await axiosInstance({
        method,
        url: props.dataSource.path,
        params: method === 'get' ? payload : undefined,
        data: method !== 'get' ? payload : undefined,
        headers,
    });

    const pageProps = response?.data?.props;

    if (pageProps) {
        if (props.dataSource?.data && pageProps[props.dataSource.data]) {
            return extractRows(pageProps[props.dataSource.data]);
        }

        return extractRows(pageProps);
    }

    return extractRows(response?.data);
}

function exportFileName() {
    const fallback = `export-${exportTableIdentifier.value}`;
    const base = String(currentExport.value.description || fallback)
        .trim()
        .replace(/[\\/:*?"<>|]+/g, '-')
        .replace(/\s+/g, ' ')
        .slice(0, 120);

    return `${base || fallback}.${currentExport.value.format === 'csv' ? 'csv' : 'xlsx'}`;
}

async function downloadCurrentExport() {
    if (exportLoading.value) {
        return;
    }

    exportLoading.value = true;
    exportSuccessMessage.value = '';
    exportNoticeMessage.value = '';

    try {
        const rows = await fetchExportRows();

        if (!Array.isArray(rows) || rows.length === 0) {
            exportNoticeMessage.value = t(
                'excel.messages.no_data',
                'Geen data gevonden om te exporteren.',
            );
            return;
        }

        const columns = exportSelectedColumns.value;

        if (columns.length === 0) {
            exportNoticeMessage.value = t(
                'excel.messages.no_columns_selected',
                'Selecteer minstens één kolom voor export.',
            );
            return;
        }

        const exportRows = rows.map((row) => {
            const mapped = {};

            columns.forEach((column) => {
                mapped[column.key] = exportCellValue(row, column);
            });

            return mapped;
        });

        if (currentExport.value.format === 'csv') {
            const delimiterChar = resolveCsvDelimiter(
                currentExport.value.csv.delimiter,
            );
            const enclosure = currentExport.value.csv.enclosure || '"';
            const headerLine = columns
                .map((column) =>
                    escapeCsvValue(
                        columnLabel(column),
                        delimiterChar,
                        enclosure,
                    ),
                )
                .join(delimiterChar);
            const dataLines = exportRows.map((exportRow) =>
                columns
                    .map((column) =>
                        escapeCsvValue(
                            exportRow[column.key],
                            delimiterChar,
                            enclosure,
                        ),
                    )
                    .join(delimiterChar),
            );
            const csvContent = `\uFEFF${[headerLine, ...dataLines].join('\r\n')}`;
            const blob = new Blob([csvContent], {
                type: 'text/csv;charset=utf-8',
            });
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', exportFileName());
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
            return;
        }

        const excelModule = await import('exceljs');
        const ExcelJS = excelModule.default || excelModule;
        const workbook = new ExcelJS.Workbook();
        const sheet = workbook.addWorksheet('Export');

        sheet.columns = columns.map((column) => ({
            header: columnLabel(column),
            key: column.key,
            width: Math.max((Number(column.width) || 100) / 7, 10),
        }));

        sheet.addRows(exportRows);
        sheet.getRow(1).font = { bold: true };

        const buffer = await workbook.xlsx.writeBuffer();
        const blob = new Blob([buffer], {
            type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', exportFileName());
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
    } catch (error) {
        exportNoticeMessage.value = t(
            'excel.messages.download_failed',
            error instanceof Error && error.message
                ? error.message
                : currentExport.value.format === 'csv'
                  ? 'Er is een fout opgetreden bij het genereren van de CSV export.'
                  : 'Er is een fout opgetreden bij het genereren van de Excel export.',
        );
    } finally {
        exportLoading.value = false;
    }
}

async function quickDownloadExport(item) {
    prepareExportState(item);
    await downloadCurrentExport();
}

function createNewChart() {
    currentChart.value = {
        id: null,
        description: '',
        config: normalizeChartConfig(defaultChartConfig()),
    };
    chartSuccessMessage.value = '';
    chartNoticeMessage.value = '';
    chartPreviewError.value = '';
    selectedViewerChartType.value =
        currentChart.value.config.builder.chart.type || 'bar';
    chartViewMode.value = 'edit';
}

function prepareChartState(item) {
    const normalizedConfig = normalizeChartConfig(item?.config || {});

    currentChart.value = {
        id: item?.id ?? null,
        description: String(item?.description || ''),
        config: normalizedConfig,
    };
    chartSuccessMessage.value = '';
    chartNoticeMessage.value = '';
    chartPreviewError.value = '';
    selectedViewerChartType.value =
        normalizedConfig.builder.chart.type || 'bar';
}

function editChart(item) {
    prepareChartState(item);
    chartViewMode.value = 'edit';
}

function viewChart(item) {
    prepareChartState(item);
    chartViewMode.value = 'view';
}

async function fetchCharts() {
    try {
        const axiosInstance = getAxios();
        const response = await axiosInstance.get(chartEndpoint());
        savedCharts.value = Array.isArray(response?.data?.charts)
            ? response.data.charts
            : [];
        chartNoticeMessage.value = '';
    } catch {
        savedCharts.value = [];
        chartNoticeMessage.value = t(
            'charts.messages.load_failed',
            'Kon opgeslagen grafieken niet laden voor deze tabel.',
        );
    }
}

async function saveChart() {
    if (!isChartValid.value) {
        return;
    }

    chartLoading.value = true;

    try {
        const axiosInstance = getAxios();
        const payload = {
            id: currentChart.value.id,
            description: String(currentChart.value.description || '').trim(),
            config: normalizeChartConfig(currentChart.value.config),
        };
        const response = await axiosInstance.post(chartEndpoint(), payload);

        if (response?.data?.chart?.id) {
            currentChart.value.id = response.data.chart.id;
        }

        await fetchCharts();
        const successMessage = t(
            'charts.messages.saved',
            'Grafiekconfiguratie opgeslagen.',
        );
        chartSuccessMessage.value = successMessage;
        chartNoticeMessage.value = '';

        setTimeout(() => {
            if (chartSuccessMessage.value === successMessage) {
                chartSuccessMessage.value = '';
            }
        }, 2500);
    } catch {
        chartNoticeMessage.value = t(
            'charts.messages.save_failed',
            'Opslaan van de grafiekconfiguratie is mislukt.',
        );
    } finally {
        chartLoading.value = false;
    }
}

async function deleteChart(chartId) {
    chartLoading.value = true;

    try {
        const axiosInstance = getAxios();
        await axiosInstance.delete(
            `${exportEndpointPrefix.value}/rw-table-charts/${Number(chartId)}`,
        );
        await fetchCharts();

        if (currentChart.value.id === chartId) {
            chartViewMode.value = 'list';
        }

        chartNoticeMessage.value = '';
    } catch {
        chartNoticeMessage.value = t(
            'charts.messages.delete_failed',
            'Verwijderen van de grafiekconfiguratie is mislukt.',
        );
    } finally {
        chartLoading.value = false;
    }
}

async function refreshChartPreview() {
    if (chartViewMode.value === 'list') {
        return;
    }

    chartPreviewLoading.value = true;
    chartPreviewError.value = '';

    let loadedRows = null;

    try {
        loadedRows = await fetchExportRows();
    } catch {
        chartRows.value = [];
        chartPreviewError.value = t(
            'charts.messages.source_load_failed',
            'Kon brondata voor de grafiek niet laden.',
        );
        disposeChart();
    } finally {
        chartPreviewLoading.value = false;
    }

    if (chartPreviewError.value) {
        return;
    }

    chartRows.value = Array.isArray(loadedRows) ? loadedRows : [];
    await renderChart();
}

async function downloadChartPdf() {
    if (chartPreviewLoading.value || !chartHasRenderableData.value) {
        return;
    }

    chartPdfLoading.value = true;
    let exportElement = null;

    try {
        await nextTick();

        if (!chartInstance) {
            await renderChart();
        }

        if (!chartInstance) {
            chartNoticeMessage.value = t(
                'charts.messages.pdf_not_available',
                'Kan de PDF niet genereren omdat de grafiek nog niet beschikbaar is.',
            );
            return;
        }

        chartInstance.resize();

        const chartImageUrl = chartInstance.getDataURL({
            type: 'png',
            pixelRatio: 2,
            backgroundColor: '#ffffff',
        });
        const title =
            String(currentChart.value?.description || '').trim() ||
            t('charts.pdf.default_title', 'Grafiek');
        const subtitle = `${chartTypeLabel(chartEffectiveType.value)} - ${new Date().toLocaleString(rwtableLocale.value)}`;

        exportElement = document.createElement('div');
        exportElement.className = 'rw-chart-pdf-export';
        exportElement.innerHTML = `
            <div style="width: 1080px; background: #ffffff; padding: 24px 28px 18px; box-sizing: border-box; font-family: Arial, sans-serif;">
                <div style="font-size: 22px; font-weight: 700; color: #0f172a; line-height: 1.35; margin-bottom: 4px;">${escapeHtml(title)}</div>
                <div style="font-size: 14px; color: #64748b; line-height: 1.4; margin-bottom: 18px;">${escapeHtml(subtitle)}</div>
                <div style="width: 100%; display: flex; justify-content: center; align-items: center; overflow: hidden;">
                    <img src="${chartImageUrl}" alt="${escapeHtml(t('charts.pdf.image_alt', 'Grafiek export'))}" style="display: block; width: 100%; height: auto; object-fit: contain;" />
                </div>
            </div>
        `;

        document.body.appendChild(exportElement);

        const html2pdf = await ensureHtml2PdfModule();

        await html2pdf()
            .set({
                margin: [10, 10, 10, 10],
                filename: buildChartPdfFilename(),
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: {
                    scale: 2,
                    useCORS: true,
                    backgroundColor: '#ffffff',
                },
                jsPDF: {
                    unit: 'mm',
                    format: 'a4',
                    orientation: 'landscape',
                },
                pagebreak: { mode: ['css', 'legacy'] },
            })
            .from(exportElement)
            .save();

        chartNoticeMessage.value = '';
    } catch {
        chartNoticeMessage.value = t(
            'charts.messages.pdf_failed',
            'PDF afdrukken van de grafiek is mislukt.',
        );
    } finally {
        if (exportElement) {
            exportElement.remove();
        }

        chartPdfLoading.value = false;
    }
}

function normalizeChartDimensionValue(value) {
    if (value === null || value === undefined || value === '') {
        return '(Leeg)';
    }

    return String(value);
}

function normalizeChartMetricValue(value) {
    const numericValue = Number(value);

    if (!Number.isFinite(numericValue)) {
        return 0;
    }

    return numericValue;
}

function createChartAggregateState() {
    return {
        count: 0,
        sum: 0,
        min: null,
        max: null,
    };
}

function updateChartAggregateState(state, aggregate, metricValue) {
    state.count += 1;

    if (aggregate === 'count') {
        return;
    }

    state.sum += metricValue;

    if (state.min === null || metricValue < state.min) {
        state.min = metricValue;
    }

    if (state.max === null || metricValue > state.max) {
        state.max = metricValue;
    }
}

function resolveChartAggregateValue(state, aggregate) {
    if (!state) {
        return 0;
    }

    switch (aggregate) {
        case 'sum':
            return state.sum;
        case 'avg':
            return state.count > 0 ? state.sum / state.count : 0;
        case 'min':
            return state.min ?? 0;
        case 'max':
            return state.max ?? 0;
        case 'count':
        default:
            return state.count;
    }
}

function buildChartPreviewData(rows, config) {
    const sourceRows = Array.isArray(rows) ? rows : [];
    const normalizedConfig = normalizeChartConfig(config);
    const dataset = normalizedConfig.builder.dataset;
    const xField = String(dataset.x_field || '').trim();
    const metricField = String(dataset.metric_field || '').trim();
    const seriesField = String(dataset.series_field || '').trim();
    const aggregate = normalizeChartAggregate(dataset.aggregate);

    if (!xField) {
        return { labels: [], series: [] };
    }

    if (aggregate !== 'count' && metricField === '') {
        return { labels: [], series: [] };
    }

    const groupedByLabel = new Map();
    const seriesOrder = [];

    sourceRows.forEach((row) => {
        const label = normalizeChartDimensionValue(row?.[xField]);
        const seriesName = seriesField
            ? normalizeChartDimensionValue(row?.[seriesField])
            : t('charts.series.total', 'Totaal');

        if (!groupedByLabel.has(label)) {
            groupedByLabel.set(label, new Map());
        }

        const seriesMap = groupedByLabel.get(label);

        if (!seriesMap.has(seriesName)) {
            seriesMap.set(seriesName, createChartAggregateState());

            if (!seriesOrder.includes(seriesName)) {
                seriesOrder.push(seriesName);
            }
        }

        const metricValue =
            aggregate === 'count'
                ? 0
                : normalizeChartMetricValue(row?.[metricField]);

        updateChartAggregateState(
            seriesMap.get(seriesName),
            aggregate,
            metricValue,
        );
    });

    const direction = normalizeChartSortDirection(dataset.sort_direction);
    const limit = normalizeChartLimit(dataset.limit);

    const orderedLabels = [...groupedByLabel.entries()]
        .map(([label, seriesMap]) => {
            let total = 0;

            seriesMap.forEach((state) => {
                total += resolveChartAggregateValue(state, aggregate);
            });

            return {
                label,
                total,
            };
        })
        .sort((first, second) => {
            if (first.total === second.total) {
                return first.label.localeCompare(second.label, undefined, {
                    numeric: true,
                    sensitivity: 'base',
                });
            }

            return direction === 'asc'
                ? first.total - second.total
                : second.total - first.total;
        })
        .slice(0, limit)
        .map((entry) => entry.label);

    if (orderedLabels.length === 0) {
        return { labels: [], series: [] };
    }

    const seriesNames = seriesField
        ? seriesOrder
        : [t('charts.series.total', 'Totaal')];
    const series = seriesNames.map((name) => {
        const data = orderedLabels.map((label) => {
            const state = groupedByLabel.get(label)?.get(name);
            const value = resolveChartAggregateValue(state, aggregate);

            if (!Number.isFinite(value)) {
                return 0;
            }

            return Number(value);
        });

        return {
            name,
            data,
        };
    });

    return {
        labels: orderedLabels,
        series,
    };
}

function chartStageElement() {
    if (chartViewMode.value === 'view') {
        return chartStageViewRef.value;
    }

    if (chartViewMode.value === 'edit') {
        return chartStageEditRef.value;
    }

    return null;
}

function browserSupportsWebGl() {
    try {
        const canvas = document.createElement('canvas');

        if (!canvas) {
            return false;
        }

        const context =
            canvas.getContext('webgl2') ||
            canvas.getContext('webgl') ||
            canvas.getContext('experimental-webgl');

        return Boolean(context);
    } catch {
        return false;
    }
}

async function ensureEchartsModule() {
    if (echartsModule) {
        return echartsModule;
    }

    const [resolvedEcharts] = await Promise.all([
        import('echarts'),
        import('echarts-gl'),
    ]);

    echartsModule = resolvedEcharts;
    echartsGlLoaded = true;

    return echartsModule;
}

async function ensureHtml2PdfModule() {
    if (html2pdfModule) {
        return html2pdfModule;
    }

    const module = await import('html2pdf.js');
    html2pdfModule = module.default || module;

    return html2pdfModule;
}

async function ensureEchartsGlModule() {
    if (echartsGlLoaded) {
        return;
    }

    await ensureEchartsModule();
}

function disposeChart() {
    if (!chartInstance) {
        return;
    }

    chartInstance.dispose();
    chartInstance = null;
}

function createPieChartOption(previewData) {
    const labels = Array.isArray(previewData?.labels) ? previewData.labels : [];
    const series = Array.isArray(previewData?.series) ? previewData.series : [];
    const type = String(chartEffectiveType.value || 'pie');
    const pieData = labels.map((label, index) => {
        const value = series.reduce((carry, serie) => {
            const itemValue = Array.isArray(serie.data)
                ? Number(serie.data[index] || 0)
                : 0;

            return carry + (Number.isFinite(itemValue) ? itemValue : 0);
        }, 0);

        return {
            name: String(label),
            value,
        };
    });

    return {
        color: CHART_COLOR_PALETTE,
        tooltip: {
            trigger: 'item',
        },
        legend: {
            show: chartCurrentChartSettings.value.show_legend !== false,
            bottom: 0,
        },
        series: [
            {
                type: 'pie',
                radius: type === 'doughnut' ? ['45%', '72%'] : ['0%', '72%'],
                data: pieData,
                label: { formatter: '{b}: {d}%' },
            },
        ],
    };
}

function createCartesianChartOption(previewData, baseType = 'bar') {
    const labels = Array.isArray(previewData?.labels) ? previewData.labels : [];
    const series = Array.isArray(previewData?.series) ? previewData.series : [];
    const isHorizontal =
        normalizeChartOrientation(
            chartCurrentChartSettings.value.orientation,
        ) === 'horizontal';
    const chartType =
        isHorizontal && baseType === 'line' ? 'bar' : String(baseType || 'bar');

    return {
        color: CHART_COLOR_PALETTE,
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
        },
        legend: {
            show: chartCurrentChartSettings.value.show_legend !== false,
            top: 8,
        },
        grid: {
            top: 48,
            left: 32,
            right: 16,
            bottom: 40,
            containLabel: true,
        },
        xAxis: isHorizontal
            ? { type: 'value' }
            : { type: 'category', data: labels },
        yAxis: isHorizontal
            ? { type: 'category', data: labels }
            : { type: 'value' },
        series: series.map((serie) => ({
            name: String(serie.name || t('charts.series.total', 'Totaal')),
            type: chartType,
            stack: chartCurrentChartSettings.value.stacked ? 'total' : null,
            smooth: chartType === 'line',
            data: Array.isArray(serie.data)
                ? serie.data.map((item) => Number(item || 0))
                : [],
        })),
    };
}

function createPseudoThreeDimensionalOption(previewData, baseType = 'bar3d') {
    const echarts = echartsModule;
    const labels = Array.isArray(previewData?.labels) ? previewData.labels : [];
    const series = Array.isArray(previewData?.series) ? previewData.series : [];
    const isHorizontal =
        normalizeChartOrientation(
            chartCurrentChartSettings.value.orientation,
        ) === 'horizontal';
    const type = String(baseType || 'bar3d');

    if (type === 'line3d') {
        return {
            color: CHART_COLOR_PALETTE,
            tooltip: { trigger: 'item' },
            legend: {
                show: chartCurrentChartSettings.value.show_legend !== false,
                top: 8,
            },
            grid: {
                top: 48,
                left: 32,
                right: 16,
                bottom: 40,
                containLabel: true,
            },
            xAxis: isHorizontal
                ? { type: 'value' }
                : { type: 'category', data: labels },
            yAxis: isHorizontal
                ? { type: 'category', data: labels }
                : { type: 'value' },
            series: series.map((serie, seriesIndex) => ({
                name: String(serie.name || `Reeks ${seriesIndex + 1}`),
                type: 'line',
                smooth: true,
                symbolSize: 8,
                lineStyle: {
                    width: 4,
                    shadowBlur: 12,
                    shadowOffsetY: 8,
                    shadowColor: 'rgba(15, 23, 42, 0.3)',
                },
                areaStyle: {
                    opacity: 0.14,
                    color: echarts?.graphic?.LinearGradient
                        ? new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                              {
                                  offset: 0,
                                  color: CHART_COLOR_PALETTE[
                                      seriesIndex % CHART_COLOR_PALETTE.length
                                  ],
                              },
                              { offset: 1, color: '#0f172a' },
                          ])
                        : CHART_COLOR_PALETTE[
                              seriesIndex % CHART_COLOR_PALETTE.length
                          ],
                },
                data: Array.isArray(serie.data)
                    ? serie.data.map((item) => Number(item || 0))
                    : [],
            })),
        };
    }

    return {
        color: CHART_COLOR_PALETTE,
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
        },
        legend: {
            show: chartCurrentChartSettings.value.show_legend !== false,
            top: 8,
        },
        grid: {
            top: 48,
            left: 32,
            right: 16,
            bottom: 40,
            containLabel: true,
        },
        xAxis: isHorizontal
            ? { type: 'value' }
            : { type: 'category', data: labels },
        yAxis: isHorizontal
            ? { type: 'category', data: labels }
            : { type: 'value' },
        series: series.map((serie, seriesIndex) => ({
            name: String(serie.name || `Reeks ${seriesIndex + 1}`),
            type: 'bar',
            stack: chartCurrentChartSettings.value.stacked ? 'total' : null,
            barMaxWidth: 48,
            itemStyle: {
                color: CHART_COLOR_PALETTE[
                    seriesIndex % CHART_COLOR_PALETTE.length
                ],
                borderColor: '#0f172a',
                borderWidth: 0.6,
                shadowBlur: 10,
                shadowOffsetY: 8,
                shadowColor: 'rgba(15, 23, 42, 0.25)',
            },
            data: Array.isArray(serie.data)
                ? serie.data.map((item) => Number(item || 0))
                : [],
        })),
    };
}

function createWebGlThreeDimensionalOption(
    previewData,
    baseType = 'bar3d_webgl',
) {
    const labels = Array.isArray(previewData?.labels) ? previewData.labels : [];
    const series = Array.isArray(previewData?.series) ? previewData.series : [];
    const type = String(baseType || 'bar3d_webgl');
    const seriesNames = series.map((serie, index) =>
        String(serie.name || `Reeks ${index + 1}`),
    );
    const maxValue = series.reduce((accumulator, serie) => {
        const values = Array.isArray(serie.data) ? serie.data : [];
        const localMax = Math.max(
            0,
            ...values.map((value) => Number(value || 0)),
        );

        return Math.max(accumulator, localMax);
    }, 0);

    if (type === 'line3d_webgl') {
        return {
            tooltip: {},
            legend: {
                show: chartCurrentChartSettings.value.show_legend !== false,
                top: 8,
            },
            xAxis3D: {
                type: 'category',
                data: labels,
            },
            yAxis3D: {
                type: 'category',
                data: seriesNames,
            },
            zAxis3D: {
                type: 'value',
                min: 0,
                max: maxValue > 0 ? maxValue : 10,
            },
            grid3D: {
                boxWidth: 120,
                boxDepth: Math.max(40, seriesNames.length * 14),
                viewControl: {
                    alpha: 22,
                    beta: 38,
                    distance: 220,
                },
            },
            series: series.map((serie, seriesIndex) => ({
                name: String(serie.name || `Reeks ${seriesIndex + 1}`),
                type: 'line3D',
                lineStyle: {
                    width: 4,
                },
                data: (Array.isArray(serie.data) ? serie.data : []).map(
                    (value, valueIndex) => [
                        valueIndex,
                        seriesIndex,
                        Number(value || 0),
                    ],
                ),
            })),
        };
    }

    const points = [];
    series.forEach((serie, seriesIndex) => {
        const values = Array.isArray(serie.data) ? serie.data : [];
        values.forEach((value, valueIndex) => {
            points.push({
                value: [valueIndex, seriesIndex, Number(value || 0)],
            });
        });
    });

    return {
        tooltip: {},
        visualMap: {
            max: maxValue > 0 ? maxValue : 10,
            calculable: true,
            orient: 'horizontal',
            left: 'center',
            bottom: 10,
            inRange: {
                color: ['#93c5fd', '#3b82f6', '#1d4ed8'],
            },
        },
        xAxis3D: {
            type: 'category',
            data: labels,
        },
        yAxis3D: {
            type: 'category',
            data: seriesNames,
        },
        zAxis3D: {
            type: 'value',
            min: 0,
            max: maxValue > 0 ? maxValue : 10,
        },
        grid3D: {
            boxWidth: 120,
            boxDepth: Math.max(40, seriesNames.length * 14),
            viewControl: {
                alpha: 22,
                beta: 32,
                distance: 220,
            },
        },
        series: [
            {
                type: 'bar3D',
                data: points,
                shading: 'lambert',
            },
        ],
    };
}

function buildChartOption(previewData) {
    const type = String(chartEffectiveType.value || 'bar');

    if (type === 'pie' || type === 'doughnut') {
        return createPieChartOption(previewData);
    }

    if (type === 'bar3d_webgl' || type === 'line3d_webgl') {
        return createWebGlThreeDimensionalOption(previewData, type);
    }

    if (type === 'bar3d' || type === 'line3d') {
        return createPseudoThreeDimensionalOption(previewData, type);
    }

    return createCartesianChartOption(
        previewData,
        type === 'line' ? 'line' : 'bar',
    );
}

function resizeChart() {
    if (!chartInstance) {
        return;
    }

    chartInstance.resize();
}

async function renderChart() {
    if (!showChartDialog.value || chartViewMode.value === 'list') {
        disposeChart();
        return;
    }

    await nextTick();

    const target = chartStageElement();

    if (!target || !chartHasRenderableData.value) {
        disposeChart();
        return;
    }

    const chartType = String(chartEffectiveType.value || 'bar');

    if (chartType === 'bar3d_webgl' || chartType === 'line3d_webgl') {
        if (!browserSupportsWebGl()) {
            chartPreviewError.value = t(
                'charts.messages.webgl_unsupported',
                'WebGL wordt niet ondersteund in deze browser of op deze GPU. Kies een niet-WebGL grafiektype.',
            );
            disposeChart();
            return;
        }
    }

    const echarts = await ensureEchartsModule();

    if (chartType === 'bar3d_webgl' || chartType === 'line3d_webgl') {
        await ensureEchartsGlModule();
    }

    const container = chartStageElement();

    if (!container) {
        return;
    }

    if (!chartInstance || chartInstance.getDom() !== container) {
        disposeChart();
        chartInstance = echarts.init(container);
    }

    try {
        chartInstance.setOption(buildChartOption(chartPreviewData.value), true);
        resizeChart();
    } catch {
        chartPreviewError.value = t(
            'charts.messages.render_failed',
            'Kon de grafiek niet renderen met de huidige instellingen.',
        );
    }
}

function onSort(columnKey) {
    if (props.manualOrdering) {
        return;
    }

    if (activeSortField.value === columnKey) {
        activeSortOrder.value =
            activeSortOrder.value === 'asc' ? 'desc' : 'asc';
        return;
    }

    activeSortField.value = columnKey;
    activeSortOrder.value = 'asc';
}

function isHeaderSortEnabled(column) {
    return !props.manualOrdering && column?.sortable !== false;
}

function onPaginationClick(pageLink) {
    if (isInfiniteMode.value || pageLink === '...') {
        return;
    }

    const nextPage = Number(pageLink);

    if (!Number.isFinite(nextPage)) {
        return;
    }

    const targetPage = Math.round(nextPage);

    if (targetPage < 1 || targetPage > lastPage.value) {
        return;
    }

    if (targetPage === currentPage.value) {
        return;
    }

    currentPage.value = targetPage;
}

async function handleCellClick(column, row, rowIndex, colIndex) {
    setActiveRowByIndex(rowIndex);
    pendingReturnRestoreAnchor.value = buildReturnRestoreAnchor(
        row?.[props.idKey],
    );
    persistReturnRestoreState();

    const cellIsCurrentlyEditing = isEditingCell(row, column);

    if (
        editingRowId.value !== null &&
        editingColumnKey.value !== null &&
        !cellIsCurrentlyEditing
    ) {
        const saved = await commitEdit('blur');

        if (!saved) {
            return;
        }
    }

    if (column.editable) {
        if (cellIsCurrentlyEditing) {
            return;
        }

        startEdit(row, column);
        return;
    }

    if (!column.clickable) {
        return;
    }

    const emittedValue = rendersAsIconColumn(column)
        ? getIconValue(column, row)
        : row[column.key];

    emit('on-cell-click', column.key, row[props.idKey], emittedValue);

    if (typeof props.cellStyle === 'function') {
        props.cellStyle({
            row,
            col: column,
            value: row[column.key],
            rowId: row[props.idKey],
            rowIndex,
            colIndex,
        });
    }
}

function setActiveRowByIndex(index) {
    if (renderedRows.value.length === 0) {
        activeRowIndex.value = 0;
        return;
    }

    const safeIndex = Math.max(
        0,
        Math.min(index, renderedRows.value.length - 1),
    );
    activeRowIndex.value = safeIndex;
}

function normalizeActiveRowIndex() {
    setActiveRowByIndex(activeRowIndex.value);
}

function buildToolbarMenuPayload(item) {
    return {
        item,
        filters: { ...requestFiltersPayload.value },
        filterModes: { ...requestFilterModesPayload.value },
        filterTypes: { ...requestFilterTypesPayload.value },
        global: global.value,
        selectionFilter: selectionFilter.value,
        selectedRowIds: [...props.checkedRows],
        sortField: activeSortField.value,
        sortOrder: activeSortOrder.value,
        page: currentPage.value,
        rowsPerPage: rowsPerPage.value,
    };
}

function onToolbarMenuClick(item) {
    emit('on-menu-item-click', buildToolbarMenuPayload(item));

    toolbarMenuOpen.value = false;
}

function toggleRowSelection(rowId) {
    const next = [...props.checkedRows];
    const index = next.indexOf(rowId);

    if (index >= 0) {
        next.splice(index, 1);
    } else {
        next.push(rowId);
    }

    emit('update:checkedRows', next);
}

function toggleAllVisibleRows(checked) {
    const visibleIds = renderedRows.value
        .map((row) => row[props.idKey])
        .filter((id) => id !== undefined && id !== null);

    if (!checked) {
        const remaining = props.checkedRows.filter(
            (id) => !visibleIds.includes(id),
        );
        emit('update:checkedRows', remaining);
        return;
    }

    const merged = Array.from(new Set([...props.checkedRows, ...visibleIds]));
    emit('update:checkedRows', merged);
}

function normalizeMultiValue(value) {
    if (Array.isArray(value)) {
        return [...value];
    }

    if (value === null || value === undefined || value === '') {
        return [];
    }

    if (typeof value === 'string') {
        const trimmed = value.trim();

        if (trimmed.startsWith('[') && trimmed.endsWith(']')) {
            try {
                const parsed = JSON.parse(trimmed);

                if (Array.isArray(parsed)) {
                    return parsed;
                }
            } catch {
                return [value];
            }
        }
    }

    return [value];
}

function resolveMultiSelectionLabels(column, row) {
    return resolveMultiSelectionItems(column, row).map((item) => item.label);
}

function normalizeMultiSelectionItem(value, color = null) {
    if (value !== null && typeof value === 'object') {
        const label =
            value.label ??
            value.title ??
            value.name ??
            value.value ??
            value.key ??
            '';

        return {
            label: String(label),
            color: value.color ?? value.chipColor ?? color ?? null,
        };
    }

    return {
        label: String(value),
        color,
    };
}

function resolveMultiSelectionItems(column, row) {
    if (column?.editMultiple !== true) {
        return [];
    }

    const valueField = column.editItemValue || 'value';
    const titleField = column.editItemTitle || 'title';
    const colorField = column.editItemColor || 'color';
    const targetField = column.editField || column.key;
    const sourceValue =
        row?.[targetField] !== undefined
            ? row?.[targetField]
            : row?.[column.key];
    const selectedValues = normalizeMultiValue(sourceValue);

    if (selectedValues.length === 0) {
        return [];
    }

    if (!Array.isArray(column.editItems) || column.editItems.length === 0) {
        return selectedValues.map((value) =>
            normalizeMultiSelectionItem(value),
        );
    }

    return selectedValues.map((value) => {
        const selectedItem = column.editItems.find(
            (item) => String(item?.[valueField]) === String(value),
        );

        if (selectedItem) {
            const label = Object.prototype.hasOwnProperty.call(
                selectedItem,
                titleField,
            )
                ? selectedItem[titleField]
                : value;
            const color = Object.prototype.hasOwnProperty.call(
                selectedItem,
                colorField,
            )
                ? selectedItem[colorField]
                : null;

            return normalizeMultiSelectionItem(label, color);
        }

        return normalizeMultiSelectionItem(value);
    });
}

function formatCellValue(row, column) {
    const value = row[column.key];

    if (column.editMultiple === true) {
        const labels = resolveMultiSelectionLabels(column, row);

        if (labels.length > 0) {
            return labels.join(', ');
        }

        const sourceValue =
            row?.[column.editField || column.key] !== undefined
                ? row?.[column.editField || column.key]
                : row?.[column.key];

        if (normalizeMultiValue(sourceValue).length === 0) {
            return '-';
        }
    }

    if (
        (column.editInput === 'select' ||
            column.editInput === 'autocomplete') &&
        Array.isArray(column.editItems)
    ) {
        const valueField = column.editItemValue || 'value';
        const titleField = column.editItemTitle || 'title';
        const targetField = column.editField || column.key;
        const selectedItem = column.editItems.find(
            (item) => String(item?.[valueField]) === String(row[targetField]),
        );

        if (
            selectedItem &&
            Object.prototype.hasOwnProperty.call(selectedItem, titleField)
        ) {
            return selectedItem[titleField] ?? '-';
        }
    }

    if (value === null || value === undefined || value === '') {
        return '-';
    }

    if (
        (column.type === 'date' || column.type === 'datetime') &&
        typeof column.dateDisplayFormat === 'string' &&
        column.dateDisplayFormat.trim() !== ''
    ) {
        const parsedDate = parseDateValueForDisplay(value, column);

        if (!parsedDate) {
            return resolveDateDisplayFallback(column);
        }

        return formatDateByPattern(
            parsedDate,
            column.dateDisplayFormat.trim(),
            resolveDateTimezoneMode(column),
        );
    }

    if (column.type === 'boolean') {
        return formatBooleanLabel(column, value);
    }

    return value;
}

function hasColumnOption(column, key) {
    return Boolean(
        column &&
        typeof column === 'object' &&
        Object.prototype.hasOwnProperty.call(column, key),
    );
}

function normalizeAutocompleteCustomText(column, rawValue) {
    const value =
        rawValue === undefined || rawValue === null ? '' : String(rawValue);

    if (column?.editCustomTrim === false) {
        return value;
    }

    return value.trim();
}

function resolveAutocompleteCommit(column, payload, currentPrimaryValue) {
    const baseField = column.editField || column.key;
    const customField =
        typeof column.editCustomField === 'string' &&
        column.editCustomField !== ''
            ? column.editCustomField
            : null;
    const sentinelConfigured = hasColumnOption(
        column,
        'editCustomSentinelValue',
    );
    const sentinelFieldConfigured =
        typeof column.editCustomSentinelField === 'string' &&
        column.editCustomSentinelField !== ''
            ? column.editCustomSentinelField
            : null;
    const sentinelField =
        sentinelFieldConfigured || (sentinelConfigured ? baseField : null);

    const result = {
        primaryValue: currentPrimaryValue,
        extraUpdates: {},
    };

    if (payload?.kind === 'multiple') {
        result.primaryValue = normalizeMultiValue(payload.values);
        return result;
    }

    if (payload?.kind === 'item') {
        result.primaryValue = payload.value;

        if (
            column.editSelectExtraUpdates &&
            typeof column.editSelectExtraUpdates === 'object' &&
            payload.item &&
            typeof payload.item === 'object'
        ) {
            Object.entries(column.editSelectExtraUpdates).forEach(
                ([targetField, sourceField]) => {
                    if (
                        typeof targetField !== 'string' ||
                        targetField === '' ||
                        typeof sourceField !== 'string' ||
                        sourceField === ''
                    ) {
                        return;
                    }

                    result.extraUpdates[targetField] =
                        payload.item[sourceField];
                },
            );
        }

        if (customField && column.editCustomClearOnSelect !== false) {
            result.extraUpdates[customField] = '';
        }

        if (
            sentinelField &&
            sentinelField !== baseField &&
            hasColumnOption(column, 'editCustomResetValueOnSelect')
        ) {
            result.extraUpdates[sentinelField] =
                column.editCustomResetValueOnSelect;
        }

        return result;
    }

    if (
        payload?.kind !== 'custom' ||
        column.editAutocompleteAllowCustom !== true
    ) {
        return result;
    }

    const customText = normalizeAutocompleteCustomText(
        column,
        payload.input ?? payload.value ?? payload.label ?? '',
    );

    const minimumLength = Math.max(0, Number(column.editCustomMinLength || 0));

    if (customText === '' || customText.length < minimumLength) {
        return result;
    }

    if (customField) {
        result.extraUpdates[customField] = customText;
    }

    if (sentinelField) {
        const sentinelValue = sentinelConfigured
            ? column.editCustomSentinelValue
            : null;

        if (sentinelField === baseField) {
            result.primaryValue = sentinelValue;
        } else {
            result.extraUpdates[sentinelField] = sentinelValue;
        }

        return result;
    }

    if (customField && customField !== baseField) {
        if (hasColumnOption(column, 'editCustomPrimaryValueOnCustom')) {
            result.primaryValue = column.editCustomPrimaryValueOnCustom;
        } else {
            result.primaryValue = null;
        }

        return result;
    }

    result.primaryValue = customText;

    return result;
}

function onEditAutocompleteResolve(column, payload) {
    const rowId = editingRowId.value;
    const baseField = column.editField || column.key;
    const resolved = resolveAutocompleteCommit(
        column,
        payload,
        editingValue.value,
    );
    editingValue.value = resolved.primaryValue;
    editingExtraUpdates.value = resolved.extraUpdates;

    if (rowId !== null && rowId !== undefined) {
        clearEditErrorsForColumn(rowId, column);
        clearEditFieldError(rowId, baseField);
    }

    mutationErrorMessage.value = '';
}

function onCreateAutocompleteResolve(column, payload) {
    const field = column.editField || column.key;
    const currentValue = createDraft.value[field] ?? null;
    const resolved = resolveAutocompleteCommit(column, payload, currentValue);

    createDraft.value = {
        ...createDraft.value,
        [field]: resolved.primaryValue,
        ...resolved.extraUpdates,
    };

    clearCreateErrorsForColumn(column);
    clearCreateFieldError(field);
    mutationErrorMessage.value = '';
}

function autocompleteDisplayValue(column, source) {
    if (!source || typeof source !== 'object') {
        return null;
    }

    if (column?.editMultiple === true) {
        return null;
    }

    if (column.editAutocompleteAllowCustom === true) {
        const customField =
            typeof column.editCustomField === 'string' &&
            column.editCustomField !== ''
                ? column.editCustomField
                : null;

        if (
            customField &&
            source[customField] !== undefined &&
            source[customField] !== null
        ) {
            return source[customField];
        }
    }

    if (source[column.key] !== undefined && source[column.key] !== null) {
        return source[column.key];
    }

    return null;
}

function resolveEditExtraValidationRules(column, extraUpdates) {
    if (!extraUpdates || Object.keys(extraUpdates).length === 0) {
        return {};
    }

    const rules = {};

    if (
        column.editExtraValidationRules &&
        typeof column.editExtraValidationRules === 'object'
    ) {
        Object.entries(extraUpdates).forEach(([field]) => {
            if (
                Object.prototype.hasOwnProperty.call(
                    column.editExtraValidationRules,
                    field,
                )
            ) {
                rules[field] = column.editExtraValidationRules[field];
            }
        });
    }

    if (
        typeof column.editCustomField === 'string' &&
        column.editCustomField !== '' &&
        Object.prototype.hasOwnProperty.call(
            extraUpdates,
            column.editCustomField,
        ) &&
        hasColumnOption(column, 'editCustomFieldValidationRules')
    ) {
        rules[column.editCustomField] = column.editCustomFieldValidationRules;
    }

    const sentinelField =
        typeof column.editCustomSentinelField === 'string' &&
        column.editCustomSentinelField !== ''
            ? column.editCustomSentinelField
            : null;

    if (
        sentinelField &&
        Object.prototype.hasOwnProperty.call(extraUpdates, sentinelField) &&
        hasColumnOption(column, 'editCustomSentinelValidationRules')
    ) {
        rules[sentinelField] = column.editCustomSentinelValidationRules;
    }

    return rules;
}

function applyLocalExtraUpdates(row, extraUpdates) {
    if (!extraUpdates || typeof extraUpdates !== 'object') {
        return;
    }

    Object.entries(extraUpdates).forEach(([field, value]) => {
        row[field] = value;
    });
}

function formatBooleanLabel(column, value) {
    const normalized = normalizeBoolean(value);
    const labels = column?.booleanLabels;
    const trueLabel =
        labels?.true ??
        labels?.yes ??
        column?.booleanTrueLabel ??
        t('common.yes', 'Ja');
    const falseLabel =
        labels?.false ??
        labels?.no ??
        column?.booleanFalseLabel ??
        t('common.no', 'Nee');

    if (normalized === true) {
        return trueLabel;
    }

    if (normalized === false) {
        return falseLabel;
    }

    return value ?? t('common.dash', '-');
}

function rendersAsIconColumn(column) {
    if (column?.type === 'icon') {
        return true;
    }

    if (column?.type !== 'boolean') {
        return false;
    }

    return column?.booleanDisplay === 'icon' || column?.booleanAsIcon === true;
}

function getIconValue(column, row) {
    if (typeof column?.iconValue === 'function') {
        return column.iconValue(row);
    }

    return row?.[column?.key];
}

function iconTruthyState(value) {
    const normalized = normalizeBoolean(value);

    if (normalized !== null) {
        return normalized;
    }

    return Boolean(value);
}

function getIconName(column, row) {
    const value = getIconValue(column, row);

    if (column?.iconMap && typeof column.iconMap === 'object') {
        const key = value ?? 'default';

        return (
            column.iconMap[key] ??
            column.iconMap.default ??
            (iconTruthyState(value)
                ? column?.iconTrue || 'mdi-check-circle'
                : column?.iconFalse || 'mdi-close-circle')
        );
    }

    return iconTruthyState(value)
        ? column?.iconTrue || 'mdi-check-circle'
        : column?.iconFalse || 'mdi-close-circle';
}

function getIconColor(column, row) {
    const value = getIconValue(column, row);

    if (column?.iconColorMap && typeof column.iconColorMap === 'object') {
        const key = value ?? 'default';

        return (
            column.iconColorMap[key] ??
            column.iconColorMap.default ??
            (iconTruthyState(value)
                ? column?.iconTrueColor || '#16a34a'
                : column?.iconFalseColor || '#ef4444')
        );
    }

    return iconTruthyState(value)
        ? column?.iconTrueColor || '#16a34a'
        : column?.iconFalseColor || '#ef4444';
}

const CHIP_COLOR_STYLE_TONAL_MAP = Object.freeze({
    slate: Object.freeze({
        borderColor: '#cbd5e1',
        backgroundColor: '#f1f5f9',
        color: '#334155',
    }),
    blue: Object.freeze({
        borderColor: '#bfdbfe',
        backgroundColor: '#dbeafe',
        color: '#1d4ed8',
    }),
    green: Object.freeze({
        borderColor: '#bbf7d0',
        backgroundColor: '#dcfce7',
        color: '#15803d',
    }),
    emerald: Object.freeze({
        borderColor: '#a7f3d0',
        backgroundColor: '#d1fae5',
        color: '#047857',
    }),
    amber: Object.freeze({
        borderColor: '#fde68a',
        backgroundColor: '#fef3c7',
        color: '#92400e',
    }),
    red: Object.freeze({
        borderColor: '#fecaca',
        backgroundColor: '#fee2e2',
        color: '#b91c1c',
    }),
    orange: Object.freeze({
        borderColor: '#fed7aa',
        backgroundColor: '#ffedd5',
        color: '#c2410c',
    }),
    yellow: Object.freeze({
        borderColor: '#fde047',
        backgroundColor: '#fef9c3',
        color: '#854d0e',
    }),
    teal: Object.freeze({
        borderColor: '#99f6e4',
        backgroundColor: '#ccfbf1',
        color: '#0f766e',
    }),
    cyan: Object.freeze({
        borderColor: '#a5f3fc',
        backgroundColor: '#cffafe',
        color: '#0e7490',
    }),
    indigo: Object.freeze({
        borderColor: '#c7d2fe',
        backgroundColor: '#e0e7ff',
        color: '#4338ca',
    }),
    purple: Object.freeze({
        borderColor: '#e9d5ff',
        backgroundColor: '#f3e8ff',
        color: '#7e22ce',
    }),
    pink: Object.freeze({
        borderColor: '#fbcfe8',
        backgroundColor: '#fce7f3',
        color: '#be185d',
    }),
    rose: Object.freeze({
        borderColor: '#fecdd3',
        backgroundColor: '#ffe4e6',
        color: '#be123c',
    }),
    gray: Object.freeze({
        borderColor: '#d1d5db',
        backgroundColor: '#f3f4f6',
        color: '#374151',
    }),
    grey: Object.freeze({
        borderColor: '#d1d5db',
        backgroundColor: '#f3f4f6',
        color: '#374151',
    }),
});

const CHIP_COLOR_STYLE_SOLID_MAP = Object.freeze({
    slate: Object.freeze({
        borderColor: '#475569',
        backgroundColor: '#475569',
        color: '#ffffff',
    }),
    blue: Object.freeze({
        borderColor: '#2563eb',
        backgroundColor: '#2563eb',
        color: '#ffffff',
    }),
    green: Object.freeze({
        borderColor: '#16a34a',
        backgroundColor: '#16a34a',
        color: '#ffffff',
    }),
    emerald: Object.freeze({
        borderColor: '#059669',
        backgroundColor: '#059669',
        color: '#ffffff',
    }),
    amber: Object.freeze({
        borderColor: '#f59e0b',
        backgroundColor: '#f59e0b',
        color: '#111827',
    }),
    red: Object.freeze({
        borderColor: '#dc2626',
        backgroundColor: '#dc2626',
        color: '#ffffff',
    }),
    orange: Object.freeze({
        borderColor: '#ea580c',
        backgroundColor: '#ea580c',
        color: '#ffffff',
    }),
    yellow: Object.freeze({
        borderColor: '#facc15',
        backgroundColor: '#facc15',
        color: '#111827',
    }),
    teal: Object.freeze({
        borderColor: '#0d9488',
        backgroundColor: '#0d9488',
        color: '#ffffff',
    }),
    cyan: Object.freeze({
        borderColor: '#0891b2',
        backgroundColor: '#0891b2',
        color: '#ffffff',
    }),
    indigo: Object.freeze({
        borderColor: '#4f46e5',
        backgroundColor: '#4f46e5',
        color: '#ffffff',
    }),
    purple: Object.freeze({
        borderColor: '#9333ea',
        backgroundColor: '#9333ea',
        color: '#ffffff',
    }),
    pink: Object.freeze({
        borderColor: '#db2777',
        backgroundColor: '#db2777',
        color: '#ffffff',
    }),
    rose: Object.freeze({
        borderColor: '#e11d48',
        backgroundColor: '#e11d48',
        color: '#ffffff',
    }),
    gray: Object.freeze({
        borderColor: '#4b5563',
        backgroundColor: '#4b5563',
        color: '#ffffff',
    }),
    grey: Object.freeze({
        borderColor: '#4b5563',
        backgroundColor: '#4b5563',
        color: '#ffffff',
    }),
});

function hasChipValue(value) {
    if (value === null || value === undefined) {
        return false;
    }

    return String(value).trim() !== '';
}

function getChipDisplayValue(column, row) {
    const formatted = formatCellValue(row, column);

    if (
        formatted === '-' &&
        (row?.[column?.key] === null ||
            row?.[column?.key] === undefined ||
            row?.[column?.key] === '')
    ) {
        return '';
    }

    return formatted;
}

function getChipColor(column, row) {
    if (column?.colorKey && hasChipValue(row?.[column.colorKey])) {
        return row[column.colorKey];
    }

    if (hasChipValue(column?.chipDefaultColor)) {
        return column.chipDefaultColor;
    }

    return 'slate';
}

function shouldRenderAsChip(column, row) {
    if (column?.type !== 'chip') {
        return false;
    }

    if (!hasChipValue(getChipDisplayValue(column, row))) {
        return false;
    }

    if (column?.chipOnlyWhenColor) {
        return column?.colorKey ? hasChipValue(row?.[column.colorKey]) : false;
    }

    return true;
}

function multiSelectionChipLimit(column) {
    const raw = Number(column?.editMaxSelectionChips || 0);

    if (!Number.isFinite(raw) || raw <= 0) {
        return 3;
    }

    return Math.max(1, Math.floor(raw));
}

function multiSelectionVisibleItems(column, row) {
    const items = resolveMultiSelectionItems(column, row);

    if (items.length === 0) {
        return [];
    }

    return items.slice(0, multiSelectionChipLimit(column));
}

function multiSelectionHiddenCount(column, row) {
    const labels = resolveMultiSelectionLabels(column, row);

    if (labels.length === 0) {
        return 0;
    }

    return Math.max(0, labels.length - multiSelectionChipLimit(column));
}

function shouldRenderAsMultiSelection(column, row) {
    if (column?.editMultiple !== true) {
        return false;
    }

    return resolveMultiSelectionLabels(column, row).length > 0;
}

function multiSelectionChipStyle(column, item) {
    const color = hasChipValue(item?.color)
        ? item.color
        : column?.chipDefaultColor;

    if (!hasChipValue(color)) {
        return chipPresetStyle(column, 'slate');
    }

    const token = String(color).trim().toLowerCase();
    const variant = normalizeChipVariant(column);

    if (
        Object.prototype.hasOwnProperty.call(
            CHIP_COLOR_STYLE_TONAL_MAP,
            token,
        ) ||
        Object.prototype.hasOwnProperty.call(CHIP_COLOR_STYLE_SOLID_MAP, token)
    ) {
        return chipPresetStyle(column, color);
    }

    if (variant === 'tonal') {
        return {
            borderColor: String(color),
            backgroundColor: 'transparent',
            color: String(color),
        };
    }

    return {
        backgroundColor: String(color),
        borderColor: 'transparent',
        color: '#ffffff',
    };
}

function normalizeChipVariant(column) {
    const variant = String(column?.chipVariant || 'solid').toLowerCase();

    return variant === 'tonal' ? 'tonal' : 'solid';
}

function chipPresetStyle(column, color) {
    const token = hasChipValue(color) ? String(color).trim().toLowerCase() : '';
    const paletteByVariant =
        normalizeChipVariant(column) === 'tonal'
            ? CHIP_COLOR_STYLE_TONAL_MAP
            : CHIP_COLOR_STYLE_SOLID_MAP;

    return paletteByVariant[token] || paletteByVariant.slate;
}

function chipStyle(column, row) {
    const color = getChipColor(column, row);
    const token = hasChipValue(color) ? String(color).trim().toLowerCase() : '';
    const variant = normalizeChipVariant(column);

    if (hasChipValue(token)) {
        if (
            Object.prototype.hasOwnProperty.call(
                CHIP_COLOR_STYLE_TONAL_MAP,
                token,
            ) ||
            Object.prototype.hasOwnProperty.call(
                CHIP_COLOR_STYLE_SOLID_MAP,
                token,
            )
        ) {
            return chipPresetStyle(column, color);
        }
    }

    if (!hasChipValue(color)) {
        return chipPresetStyle(column, 'slate');
    }

    if (variant === 'tonal') {
        return {
            borderColor: String(color),
            backgroundColor: 'transparent',
            color: String(color),
        };
    }

    return {
        backgroundColor: String(color),
        borderColor: 'transparent',
        color: '#ffffff',
    };
}

function chipClass(column) {
    const variant = normalizeChipVariant(column);

    return [
        'inline-flex max-w-full items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium leading-5',
        variant === 'solid' ? 'shadow-sm' : '',
        column?.clickable ? 'transition-colors group-hover:brightness-95' : '',
    ];
}

function getColumnWidth(columnKey) {
    const numeric = Number(columnWidths.value?.[columnKey]);
    const minWidth = minimumColumnWidth(columnKey);

    if (Number.isFinite(numeric) && numeric > 0) {
        return Math.max(minWidth, Math.round(numeric));
    }

    const fromProps = findColumnByKey(columnKey);
    return defaultColumnWidth(fromProps || { key: columnKey });
}

function normalizeColumnAlign(align) {
    if (align === 'right' || align === 'center') {
        return align;
    }

    return 'left';
}

function contentAlignmentClass(column) {
    const align = normalizeColumnAlign(column?.align);

    if (align === 'center') {
        return 'justify-center';
    }

    if (align === 'right') {
        return 'justify-end';
    }

    return 'justify-start';
}

function rowMenuOffsetClass(column, hasInlineMenu) {
    if (!hasInlineMenu) {
        return '';
    }

    return normalizeColumnAlign(column?.align) === 'left' ? 'pl-9' : '';
}

function isIdLikeColumn(column) {
    const key = String(column?.key ?? '').toLowerCase();
    const label = String(column?.label ?? '').toLowerCase();
    const idKey = String(props.idKey ?? '').toLowerCase();

    if (!key) {
        return false;
    }

    if (key === idKey || key === 'id' || key.endsWith('_id')) {
        return true;
    }

    return label === 'id' || label.endsWith(' id');
}

function isLikelyIdClickTarget(column, row) {
    if (isIdLikeColumn(column)) {
        return true;
    }

    const key = String(column?.key ?? '').toLowerCase();
    const label = String(column?.label ?? '').toLowerCase();
    const value = row?.[column?.key];
    const rowId = row?.[props.idKey];

    if (key.includes('id') || label.includes('id')) {
        return true;
    }

    if (
        value !== undefined &&
        value !== null &&
        rowId !== undefined &&
        rowId !== null &&
        String(value) === String(rowId)
    ) {
        return true;
    }

    const keyLooksLikeIdentifier =
        key.includes('nummer') ||
        key.includes('number') ||
        key.endsWith('_nr') ||
        key.includes('code');

    if (!keyLooksLikeIdentifier) {
        return false;
    }

    return (
        typeof value === 'string' ||
        typeof value === 'number' ||
        typeof value === 'bigint'
    );
}

function clickableCellClass(column) {
    if (!column?.clickable && column?.editable !== true) {
        return '';
    }

    return 'rw-cell-clickable';
}

function clickableCellValueClass(column, row) {
    if (!column?.clickable) {
        if (column?.editable === true) {
            return 'rw-cell-editable truncate';
        }

        return 'truncate';
    }

    if (isLikelyIdClickTarget(column, row)) {
        return 'rw-id-hover inline-flex max-w-full items-center rounded-full px-2 py-0.5 font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-300';
    }

    return 'truncate text-blue-700 hover:underline';
}

function rowMenuPositionClass(rowIndex) {
    const totalRows = renderedRows.value.length;

    if (totalRows <= 0) {
        return 'top-0';
    }

    const openUpThreshold = Math.max(0, totalRows - 3);

    if (rowIndex >= openUpThreshold) {
        return 'bottom-0';
    }

    return 'top-0';
}

function hasExpandedRowValue(value) {
    return String(value ?? '').trim() !== '';
}

function expandedRowContentItems(row) {
    if (
        !props.expandableRows ||
        typeof props.expandedRowContent !== 'function' ||
        !row
    ) {
        return [];
    }

    const items = props.expandedRowContent(row);

    if (!Array.isArray(items)) {
        return [];
    }

    return items.filter((item) => item && hasExpandedRowValue(item.value));
}

function canExpandRow(row) {
    if (!props.expandableRows || !row) {
        return false;
    }

    if (expandedRowContentItems(row).length === 0) {
        return false;
    }

    if (typeof props.rowCanExpand === 'function') {
        return props.rowCanExpand(row) === true;
    }

    return true;
}

function isRowExpanded(row) {
    const rowId = row?.[props.idKey];

    if (rowId === null || rowId === undefined) {
        return false;
    }

    return expandedRowIds.value.includes(String(rowId));
}

function toggleExpandedRow(row) {
    if (!canExpandRow(row)) {
        return;
    }

    const rowId = String(row?.[props.idKey]);

    if (expandedRowIds.value.includes(rowId)) {
        expandedRowIds.value = expandedRowIds.value.filter(
            (value) => value !== rowId,
        );
        return;
    }

    expandedRowIds.value = [...expandedRowIds.value, rowId];
}

function utilityOffsetLeft() {
    let offset = 0;

    if (props.checkboxColumn) {
        offset += UTILITY_COLUMN_WIDTH;
    }

    if (showExpandColumn.value) {
        offset += UTILITY_COLUMN_WIDTH;
    }

    return offset;
}

function stickyLeftForColumn(columnIndex) {
    let leftOffset = utilityOffsetLeft();

    for (let idx = 0; idx < columnIndex; idx += 1) {
        const previousColumn = selectedColumns.value[idx];

        if (!previousColumn) {
            continue;
        }

        leftOffset += getColumnWidth(previousColumn.key);
    }

    return leftOffset;
}

function utilityColumnStyle(type, isHeader = false, bodyBackground = null) {
    if (!localHorizontalScroll.value) {
        return null;
    }

    return {
        position: 'sticky',
        left: '0px',
        width: `${UTILITY_COLUMN_WIDTH}px`,
        minWidth: `${UTILITY_COLUMN_WIDTH}px`,
        maxWidth: `${UTILITY_COLUMN_WIDTH}px`,
        zIndex: isHeader
            ? Z_LAYERS.stickyUtilityHeader
            : Z_LAYERS.stickyUtilityBody,
        backgroundColor: isHeader
            ? 'rgb(248 250 252)'
            : bodyBackground || STICKY_BODY_BACKGROUND,
    };
}

function expandColumnStyle(isHeader = false, bodyBackground = null) {
    if (!localHorizontalScroll.value) {
        return {
            width: `${UTILITY_COLUMN_WIDTH}px`,
            minWidth: `${UTILITY_COLUMN_WIDTH}px`,
            maxWidth: `${UTILITY_COLUMN_WIDTH}px`,
        };
    }

    return {
        position: 'sticky',
        left: `${props.checkboxColumn ? UTILITY_COLUMN_WIDTH : 0}px`,
        width: `${UTILITY_COLUMN_WIDTH}px`,
        minWidth: `${UTILITY_COLUMN_WIDTH}px`,
        maxWidth: `${UTILITY_COLUMN_WIDTH}px`,
        zIndex: isHeader
            ? Z_LAYERS.stickyUtilityHeader
            : Z_LAYERS.stickyUtilityBody,
        backgroundColor: isHeader
            ? 'rgb(248 250 252)'
            : bodyBackground || STICKY_BODY_BACKGROUND,
    };
}

function headerColumnStyle(column, columnIndex) {
    const width = getColumnWidth(column.key);
    const baseStyle = {
        width: `${width}px`,
        minWidth: `${width}px`,
        maxWidth: `${width}px`,
        textAlign: normalizeColumnAlign(column.align),
    };

    if (!localHorizontalScroll.value || columnIndex >= lockCount.value) {
        return baseStyle;
    }

    return {
        ...baseStyle,
        position: 'sticky',
        left: `${stickyLeftForColumn(columnIndex)}px`,
        zIndex: Z_LAYERS.stickyDataHeader,
        backgroundColor: 'rgb(248 250 252)',
    };
}

function bodyColumnStyle(column, columnIndex, stickyBackground = null) {
    const width = getColumnWidth(column.key);
    const baseStyle = {
        width: `${width}px`,
        minWidth: `${width}px`,
        maxWidth: `${width}px`,
        textAlign: normalizeColumnAlign(column.align),
    };

    if (!localHorizontalScroll.value || columnIndex >= lockCount.value) {
        return baseStyle;
    }

    return {
        ...baseStyle,
        position: 'sticky',
        left: `${stickyLeftForColumn(columnIndex)}px`,
        zIndex: Z_LAYERS.stickyDataBody,
        backgroundColor: stickyBackground || STICKY_BODY_BACKGROUND,
    };
}

function mergedCellStyleFor(row, column, rowIndex, colIndex) {
    const baseStyle = bodyColumnStyle(column, colIndex);

    if (typeof props.cellStyle !== 'function') {
        return baseStyle;
    }

    const dynamicStyle = props.cellStyle({
        row,
        col: column,
        value: row[column.key],
        rowId: row[props.idKey],
        rowIndex,
        colIndex,
    });

    if (!dynamicStyle || typeof dynamicStyle !== 'object') {
        return baseStyle;
    }

    return {
        ...baseStyle,
        ...dynamicStyle,
    };
}

function cellStyleWithOverlay(row, column, rowIndex, colIndex) {
    const baseStyle = mergedCellStyleFor(row, column, rowIndex, colIndex);

    if (isEditingCell(row, column)) {
        if (localHorizontalScroll.value && colIndex < lockCount.value) {
            return {
                ...baseStyle,
                zIndex: Z_LAYERS.stickyDataBody + 2,
            };
        }

        return {
            ...baseStyle,
        };
    }

    if (showsInlineRowMenu(row, colIndex) && isRowMenuOpen(row[props.idKey])) {
        return {
            ...baseStyle,
            zIndex: Z_LAYERS.rowMenuOverlay + 1,
        };
    }

    return baseStyle;
}

function createColumnStyle(column, columnIndex) {
    return bodyColumnStyle(column, columnIndex, CREATE_ROW_STICKY_BACKGROUND);
}

function lockIconName(columnIndex) {
    if (columnIndex < lockCount.value) {
        return 'mdi-lock';
    }

    if (columnIndex === lockCount.value) {
        return 'mdi-lock-open-variant';
    }

    return '';
}

function toggleLock(columnIndex) {
    if (!localHorizontalScroll.value) {
        return;
    }

    const maxLockCount = Math.max(0, selectedColumns.value.length);
    const nextValue =
        columnIndex === lockCount.value ? lockCount.value + 1 : columnIndex;

    lockCount.value = Math.min(Math.max(nextValue, 0), maxLockCount);
    persistLockCount();
    clampColumnWidthsToMinimums(true);
}

function onResizeMousemove(event) {
    if (!resizingState) {
        return;
    }

    const delta = event.clientX - resizingState.startX;
    const newWidth = Math.max(
        resizingState.minWidth,
        Math.round(resizingState.startWidth + delta),
    );

    columnWidths.value = {
        ...columnWidths.value,
        [resizingState.key]: newWidth,
    };
}

function startResize(event, columnKey) {
    resizingState = {
        key: columnKey,
        startX: event.clientX,
        startWidth: getColumnWidth(columnKey),
        minWidth: minimumColumnWidth(columnKey),
    };

    window.addEventListener('mousemove', onResizeMousemove);
    window.addEventListener('mouseup', stopResize);
}

function stopResize() {
    if (!resizingState) {
        return;
    }

    window.removeEventListener('mousemove', onResizeMousemove);
    window.removeEventListener('mouseup', stopResize);
    resizingState = null;
    persistColumnWidths();
}

function cellClassFor(row, column, rowIndex, colIndex) {
    if (typeof props.cellClass !== 'function') {
        return null;
    }

    return props.cellClass({
        row,
        col: column,
        value: row[column.key],
        rowId: row[props.idKey],
        rowIndex,
        colIndex,
    });
}

function toggleColumnSelection(columnKey) {
    const column = columnConfig.value.find((item) => item.key === columnKey);

    if (!column) {
        return;
    }

    column.selected = !column.selected;
}

function moveColumn(columnKey, direction) {
    const fromIndex = columnConfig.value.findIndex(
        (item) => item.key === columnKey,
    );

    if (fromIndex < 0) {
        return;
    }

    const toIndex = fromIndex + direction;

    if (toIndex < 0 || toIndex >= columnConfig.value.length) {
        return;
    }

    const next = [...columnConfig.value];
    const [moved] = next.splice(fromIndex, 1);
    next.splice(toIndex, 0, moved);
    columnConfig.value = next;
}

function moveColumnToTarget(sourceKey, targetKey) {
    if (!sourceKey || !targetKey || sourceKey === targetKey) {
        return;
    }

    const fromIndex = columnConfig.value.findIndex(
        (item) => item.key === sourceKey,
    );
    const toIndex = columnConfig.value.findIndex(
        (item) => item.key === targetKey,
    );

    if (fromIndex < 0 || toIndex < 0 || fromIndex === toIndex) {
        return;
    }

    const next = [...columnConfig.value];
    const [moved] = next.splice(fromIndex, 1);
    next.splice(toIndex, 0, moved);
    columnConfig.value = next;
}

function onColumnDragStart(columnKey, event) {
    draggedColumnKey.value = columnKey;

    if (event?.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';

        try {
            event.dataTransfer.setData('text/plain', String(columnKey));
            setDragPreviewFromRow(event);
        } catch {
            return;
        }
    }
}

function onColumnDragOver(columnKey, event) {
    if (!draggedColumnKey.value) {
        return;
    }

    event.preventDefault();
    dragOverColumnKey.value = columnKey;

    if (event?.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
}

function onColumnDrop(targetKey, event) {
    event.preventDefault();

    const fromTransfer = event?.dataTransfer?.getData?.('text/plain') || null;
    const sourceKey = draggedColumnKey.value || fromTransfer;

    moveColumnToTarget(sourceKey, targetKey);
    onColumnDragEnd();
}

function onColumnDragEnd() {
    draggedColumnKey.value = null;
    dragOverColumnKey.value = null;
}

function openFilter(columnKey) {
    openFilterKey.value = openFilterKey.value === columnKey ? null : columnKey;

    const column = selectedColumns.value.find((item) => item.key === columnKey);

    if (!column) {
        return;
    }

    if (!filterModes.value[columnKey]) {
        filterModes.value[columnKey] = defaultFilterMode(
            filterTypes.value[columnKey],
        );
    }
}

function isColumnFilterActive(columnKey) {
    return Object.prototype.hasOwnProperty.call(
        activeFiltersPayload.value,
        columnKey,
    );
}

function clearFilter(columnKey) {
    delete filterValues.value[columnKey];
    delete filterOptionValues.value[columnKey];
    delete filterModes.value[columnKey];
    openFilterKey.value = null;
}

function updateFilterMode(column, mode) {
    const columnKey = column.key;
    filterModes.value[columnKey] = mode;

    if (isOptionFilterMode(mode)) {
        if (isTextFilterActive(columnKey)) {
            setFilterValue(columnKey, '');
        }

        filterOptionValues.value[columnKey] = normalizeFilterOptionSelection(
            column,
            filterOptionValues.value[columnKey],
        );

        return;
    }

    if (isOptionFilterActive(columnKey)) {
        setFilterOptionValue(columnKey, column.editMultiple === true ? [] : '');
    }

    if (mode === 'tussen') {
        const current = filterValues.value[columnKey];

        if (!current || typeof current !== 'object') {
            filterValues.value[columnKey] = { from: '', to: '' };
        }

        return;
    }

    if (typeof filterValues.value[columnKey] === 'object') {
        filterValues.value[columnKey] = '';
    }
}

function setFilterValue(columnKey, value) {
    filterValues.value[columnKey] = value;
}

function setFilterOptionValue(columnKey, value) {
    filterOptionValues.value[columnKey] = value;
}

function registerFilterTextInput(columnKey, el) {
    if (!(el instanceof HTMLInputElement)) {
        filterTextInputElements.delete(columnKey);
        return;
    }

    filterTextInputElements.set(columnKey, el);
}

function restorePendingFilterFocus() {
    const pending = pendingFilterFocus.value;

    if (!pending) {
        return;
    }

    if (openFilterKey.value !== pending.columnKey) {
        return;
    }

    const target = filterTextInputElements.get(pending.columnKey);

    if (!(target instanceof HTMLInputElement)) {
        return;
    }

    const activeElement = document.activeElement;

    if (
        activeElement &&
        activeElement !== document.body &&
        activeElement !== target
    ) {
        pendingFilterFocus.value = null;
        return;
    }

    target.focus({ preventScroll: true });

    if (
        Number.isInteger(pending.selectionStart) &&
        Number.isInteger(pending.selectionEnd)
    ) {
        try {
            target.setSelectionRange(
                pending.selectionStart,
                pending.selectionEnd,
            );
        } catch {
            // noop
        }
    }

    pendingFilterFocus.value = null;
}

function onFilterTextInput(columnKey, event) {
    const target = event?.target;
    const nextValue = target?.value ?? '';

    setFilterValue(columnKey, nextValue);

    if (isOptionFilterActive(columnKey)) {
        setFilterOptionValue(columnKey, '');
    }

    if (!(target instanceof HTMLInputElement)) {
        return;
    }

    pendingFilterFocus.value = {
        columnKey,
        selectionStart: target.selectionStart,
        selectionEnd: target.selectionEnd,
    };

    if (!props.managed) {
        nextTick(() => {
            restorePendingFilterFocus();
        });
    }
}

function onFilterOptionInput(columnKey, value) {
    const column = resolveColumnByKey(columnKey);
    setFilterOptionValue(
        columnKey,
        normalizeFilterOptionSelection(column, value),
    );

    if (isTextFilterActive(columnKey)) {
        setFilterValue(columnKey, '');
    }
}

function readSelectFilterValue(target, column) {
    if (!(target instanceof HTMLSelectElement)) {
        return column?.editMultiple === true ? [] : '';
    }

    if (column?.editMultiple === true) {
        return Array.from(target.selectedOptions).map((option) => option.value);
    }

    return target.value;
}

function setFilterRangeValue(columnKey, edge, value) {
    const current = filterValues.value[columnKey];

    if (!current || typeof current !== 'object') {
        filterValues.value[columnKey] = { from: '', to: '' };
    }

    filterValues.value[columnKey] = {
        ...filterValues.value[columnKey],
        [edge]: value,
    };
}

function resetAllFilters() {
    filterValues.value = {};
    filterOptionValues.value = {};
    filterModes.value = {};
    openFilterKey.value = null;
    initializeFilterMetadata();
}

function filterInputType(columnType, mode) {
    if (mode === 'tussen') {
        return 'date';
    }

    if (columnType === 'number') {
        return 'number';
    }

    if (columnType === 'date' || columnType === 'datetime') {
        return 'date';
    }

    return 'text';
}

function resetInfiniteState() {
    currentPage.value = 1;
    clientInfiniteCount.value = rowsPerPage.value;
    serverInfiniteRows.value = [];
}

function onInfiniteIntersect(entries) {
    const [entry] = entries;

    if (!entry?.isIntersecting || tableLoading.value) {
        return;
    }

    if (isServerMode.value) {
        if (currentPage.value >= lastPage.value) {
            return;
        }

        const nextPage = currentPage.value + 1;

        if (props.managed) {
            fetchManaged({ append: true, pageOverride: nextPage });
            return;
        }

        currentPage.value = nextPage;
        return;
    }

    if (clientInfiniteCount.value >= clientRows.value.length) {
        return;
    }

    clientInfiniteCount.value = Math.min(
        clientInfiniteCount.value + rowsPerPage.value,
        clientRows.value.length,
    );
}

function setupInfiniteObserver() {
    if (infiniteObserver) {
        infiniteObserver.disconnect();
        infiniteObserver = null;
    }

    if (!isInfiniteMode.value || !infiniteSentinelRef.value) {
        return;
    }

    infiniteObserver = new IntersectionObserver(onInfiniteIntersect, {
        root: containerRef.value,
        rootMargin: '200px',
        threshold: 0,
    });

    infiniteObserver.observe(infiniteSentinelRef.value);
}

function openCreateRow(insertAboveId = null) {
    if (!props.inlineCreate) {
        return;
    }

    const shouldAppendAtEnd =
        insertAboveId === null || insertAboveId === undefined;

    if (shouldAppendAtEnd && !isInfiniteMode.value) {
        currentPage.value = lastPage.value;
    }

    createInsertAboveId.value = insertAboveId;
    createRowVisible.value = true;
    rowMenuOpenId.value = null;
    mutationErrorMessage.value = '';
    clearCreateFieldErrors();

    if (shouldAppendAtEnd) {
        nextTick(() => {
            scrollCreateRowToBottom();
        });
    }
}

function shouldRenderCreateRowAbove(row) {
    if (!createRowVisible.value) {
        return false;
    }

    if (
        createInsertAboveId.value === null ||
        createInsertAboveId.value === undefined
    ) {
        return false;
    }

    return row?.[props.idKey] === createInsertAboveId.value;
}

function syncClientInfiniteCountAfterMutation() {
    if (!isInfiniteMode.value) {
        return;
    }

    const total = internalRows.value.length;
    clientInfiniteCount.value = Math.max(
        rowsPerPage.value,
        Math.min(Math.max(clientInfiniteCount.value, total), total),
    );
}

function insertCreatedRowLocally(newRow) {
    if (!newRow || typeof newRow !== 'object') {
        return;
    }

    if (
        createInsertAboveId.value === null ||
        createInsertAboveId.value === undefined
    ) {
        internalRows.value = [...internalRows.value, newRow];
        syncClientInfiniteCountAfterMutation();
        return;
    }

    const targetIndex = internalRows.value.findIndex(
        (item) => item?.[props.idKey] === createInsertAboveId.value,
    );

    if (targetIndex < 0) {
        internalRows.value = [...internalRows.value, newRow];
        return;
    }

    const next = [...internalRows.value];
    next.splice(targetIndex, 0, newRow);
    internalRows.value = next;
    syncClientInfiniteCountAfterMutation();
}

function replaceCreatedRowLocally(previousRowId, newRow) {
    if (
        previousRowId === null ||
        previousRowId === undefined ||
        !newRow ||
        typeof newRow !== 'object'
    ) {
        return;
    }

    const targetIndex = internalRows.value.findIndex(
        (item) => item?.[props.idKey] === previousRowId,
    );

    if (targetIndex < 0) {
        insertCreatedRowLocally(newRow);
        return;
    }

    const next = [...internalRows.value];
    next[targetIndex] = {
        ...next[targetIndex],
        ...newRow,
    };
    internalRows.value = next;
    syncClientInfiniteCountAfterMutation();
}

function removeCreatedRowLocally(rowId) {
    if (rowId === null || rowId === undefined) {
        return;
    }

    internalRows.value = internalRows.value.filter(
        (item) => item?.[props.idKey] !== rowId,
    );
    syncClientInfiniteCountAfterMutation();
}

function closeCreateRow() {
    createRowVisible.value = false;
    clearCreateFieldErrors();
}

function fallbackCreatedRowId() {
    const numericIds = internalRows.value
        .map((row) => Number(row?.[props.idKey]))
        .filter((value) => Number.isFinite(value));

    if (numericIds.length > 0) {
        return Math.max(...numericIds) + 1;
    }

    return Date.now();
}

function fallbackCreatedRowOrderValue() {
    const orderField = props.manualOrderField;

    if (!orderField) {
        return null;
    }

    const numericValues = internalRows.value
        .map((row) => Number(row?.[orderField]))
        .filter((value) => Number.isFinite(value));

    if (numericValues.length > 0) {
        return Math.max(...numericValues) + 1000;
    }

    return 1000;
}

function hydrateCreatedRowFromPayload(rawCreatedRow, payload, rawResponseData) {
    const metaPayloadKeys = new Set([
        'validationType',
        'manualOrdering',
        'manualOrderField',
        'insertAboveId',
    ]);

    const payloadData = Object.entries(payload || {}).reduce(
        (carry, [key, value]) => {
            if (!metaPayloadKeys.has(key)) {
                carry[key] = value;
            }

            return carry;
        },
        {},
    );

    const responseData =
        rawCreatedRow && typeof rawCreatedRow === 'object' ? rawCreatedRow : {};
    const createdRow = {
        ...payloadData,
        ...responseData,
    };

    const responseId = rawResponseData?.[props.idKey] ?? rawResponseData?.id;

    if (
        (createdRow[props.idKey] === undefined ||
            createdRow[props.idKey] === null) &&
        responseId !== undefined &&
        responseId !== null
    ) {
        createdRow[props.idKey] = responseId;
    }

    if (
        createdRow[props.idKey] === undefined ||
        createdRow[props.idKey] === null
    ) {
        createdRow[props.idKey] = fallbackCreatedRowId();
    }

    if (
        props.manualOrdering &&
        (createInsertAboveId.value === null ||
            createInsertAboveId.value === undefined)
    ) {
        const orderField = props.manualOrderField;

        if (
            orderField &&
            (createdRow[orderField] === undefined ||
                createdRow[orderField] === null)
        ) {
            const fallbackOrder = fallbackCreatedRowOrderValue();

            if (fallbackOrder !== null) {
                createdRow[orderField] = fallbackOrder;
            }
        }
    }

    if (
        !Object.prototype.hasOwnProperty.call(createdRow, props.idKey) ||
        createdRow[props.idKey] === undefined ||
        createdRow[props.idKey] === null
    ) {
        createdRow[props.idKey] = fallbackCreatedRowId();
    }

    return createdRow;
}

function firstValidationMessage(value) {
    if (Array.isArray(value)) {
        return value.length > 0 ? String(value[0]) : '';
    }

    if (value === undefined || value === null) {
        return '';
    }

    return String(value);
}

function extractValidationFeedback(error) {
    const responseData = error?.response?.data ?? {};
    const rawErrors = responseData?.errors;
    const errors = {};

    if (rawErrors && typeof rawErrors === 'object') {
        Object.entries(rawErrors).forEach(([field, message]) => {
            const normalizedField = String(field || '').trim();

            if (normalizedField === '') {
                return;
            }

            const normalizedMessage = firstValidationMessage(message);

            if (normalizedMessage !== '') {
                errors[normalizedField] = normalizedMessage;
            }
        });
    }

    return {
        status: Number(error?.response?.status || 0),
        message:
            typeof responseData?.message === 'string'
                ? responseData.message
                : '',
        errors,
    };
}

function hasRequiredRule(validationRules) {
    if (!validationRules) {
        return false;
    }

    if (typeof validationRules === 'string') {
        const parts = validationRules.split('|').map((part) => part.trim());
        return parts.includes('required');
    }

    if (Array.isArray(validationRules)) {
        return validationRules.some((rule) => {
            if (typeof rule === 'string') {
                return rule.split(':')[0] === 'required';
            }

            return false;
        });
    }

    return false;
}

function isColumnRequired(column) {
    if (!column || typeof column !== 'object') {
        return false;
    }

    if (column.required === true) {
        return true;
    }

    if (column.required === false) {
        return false;
    }

    return hasRequiredRule(column.validationRules);
}

function isFieldEmptyValue(value) {
    if (value === null || value === undefined) {
        return true;
    }

    if (typeof value === 'string') {
        return value.trim() === '';
    }

    if (Array.isArray(value)) {
        return value.length === 0;
    }

    return false;
}

function requiredHighlightColor(column) {
    if (typeof column?.requiredHighlightColor === 'string') {
        const color = column.requiredHighlightColor.trim();
        if (color !== '') {
            return color;
        }
    }

    return 'rgba(59, 130, 246, 0.1)';
}

function clearMutationErrorMessage() {
    mutationErrorMessage.value = '';
}

function normalizedValidationMode() {
    const mode = String(props.validationMode || 'both')
        .trim()
        .toLowerCase();

    if (mode === 'client' || mode === 'server' || mode === 'both') {
        return mode;
    }

    return 'both';
}

function normalizeValidationTriggerList(value, fallback = []) {
    const source =
        value === undefined || value === null || value === ''
            ? fallback
            : value;

    const arrayValue = Array.isArray(source) ? source : [source];

    const normalized = arrayValue
        .map((item) =>
            String(item || '')
                .trim()
                .toLowerCase(),
        )
        .filter((item) => item !== '');

    return normalized.length > 0 ? normalized : [...fallback];
}

function columnValidationTriggers(column) {
    return normalizeValidationTriggerList(
        column?.clientValidationOn,
        normalizeValidationTriggerList(
            props.clientValidationOn,
            DEFAULT_CLIENT_VALIDATION_TRIGGERS,
        ),
    );
}

function shouldRunClientValidation(column, trigger) {
    const mode = normalizedValidationMode();

    if (mode === 'server') {
        return false;
    }

    const normalizedTrigger = String(trigger || 'save').toLowerCase();
    const triggers = columnValidationTriggers(column);

    if (!triggers.includes(normalizedTrigger)) {
        return false;
    }

    return Boolean(
        typeof props.validationAdapter === 'function' ||
        typeof column?.clientValidate === 'function' ||
        column?.validationRules ||
        column?.required === true,
    );
}

function resolveColumnRulesForField(column, field) {
    const baseField = String(column?.editField || column?.key || '');

    if (
        typeof column?.editCustomField === 'string' &&
        column.editCustomField === field &&
        Object.prototype.hasOwnProperty.call(
            column,
            'editCustomFieldValidationRules',
        )
    ) {
        return column.editCustomFieldValidationRules;
    }

    if (
        typeof column?.editCustomSentinelField === 'string' &&
        column.editCustomSentinelField === field &&
        Object.prototype.hasOwnProperty.call(
            column,
            'editCustomSentinelValidationRules',
        )
    ) {
        return column.editCustomSentinelValidationRules;
    }

    if (field === baseField || field === String(column?.key || '')) {
        if (column?.validationRules) {
            return column.validationRules;
        }

        if (column?.required === true) {
            return 'required';
        }

        return null;
    }
    if (
        column?.editExtraValidationRules &&
        typeof column.editExtraValidationRules === 'object'
    ) {
        if (
            Object.prototype.hasOwnProperty.call(
                column.editExtraValidationRules,
                field,
            )
        ) {
            return column.editExtraValidationRules[field];
        }
    }

    return null;
}

function normalizeClientValidationResult(result) {
    if (result === null || result === undefined || result === true) {
        return null;
    }

    if (result === false) {
        return t('validation.invalid_value', 'Ongeldige waarde.');
    }

    if (typeof result === 'string') {
        return result.trim() === '' ? null : result;
    }

    if (typeof result === 'object') {
        if (result.valid === true) {
            return null;
        }

        if (
            typeof result.message === 'string' &&
            result.message.trim() !== ''
        ) {
            return result.message;
        }
    }

    return null;
}

function runClientValidationForField({
    column,
    field,
    value,
    trigger,
    contextMode,
    row,
    draft,
}) {
    const normalizedTrigger = String(trigger || 'save').toLowerCase();

    if (!shouldRunClientValidation(column, normalizedTrigger)) {
        return null;
    }

    const context = {
        value,
        field,
        column,
        row,
        draft,
        trigger: normalizedTrigger,
        mode: contextMode,
        rules: resolveColumnRulesForField(column, field),
    };

    if (typeof props.validationAdapter === 'function') {
        const adapterResult = props.validationAdapter(context);
        const normalizedAdapterResult =
            normalizeClientValidationResult(adapterResult);

        if (normalizedAdapterResult) {
            return normalizedAdapterResult;
        }
    }

    if (typeof column?.clientValidate === 'function') {
        const customResult = column.clientValidate(context);
        const normalizedCustomResult =
            normalizeClientValidationResult(customResult);

        if (normalizedCustomResult) {
            return normalizedCustomResult;
        }
    }

    const rules = context.rules;

    if (!rules) {
        return null;
    }

    return validateValueWithRules(value, rules, {
        fieldLabel: String(
            column?.label || field || t('validation.this_field', 'Dit veld'),
        ),
    });
}

function clearCreateErrorsForColumn(column) {
    const targets = createValidationFieldTargets(column, true);

    targets.forEach((field) => {
        clearCreateFieldError(field);
    });
    clearCreateFieldError(column?.key);
}

function clearEditErrorsForColumn(rowId, column) {
    const targets = createValidationFieldTargets(column, true);

    targets.forEach((field) => {
        clearEditFieldError(rowId, field);
    });
    clearEditFieldError(rowId, column?.key);
}

function createValidationFieldTargets(column, includeExtended = false) {
    const baseField = String(column?.editField || column?.key || '');
    const targets = [];

    if (baseField !== '') {
        targets.push(baseField);
    }

    if (!includeExtended) {
        return targets;
    }

    const customField =
        typeof column?.editCustomField === 'string'
            ? column.editCustomField
            : '';

    if (customField !== '' && !targets.includes(customField)) {
        targets.push(customField);
    }

    const sentinelField =
        typeof column?.editCustomSentinelField === 'string'
            ? column.editCustomSentinelField
            : '';

    if (sentinelField !== '' && !targets.includes(sentinelField)) {
        targets.push(sentinelField);
    }

    if (
        column?.editExtraValidationRules &&
        typeof column.editExtraValidationRules === 'object'
    ) {
        Object.keys(column.editExtraValidationRules).forEach((field) => {
            const normalized = String(field || '').trim();

            if (normalized !== '' && !targets.includes(normalized)) {
                targets.push(normalized);
            }
        });
    }

    return targets;
}

function validateCreateColumn(column, trigger, includeExtended = false) {
    const targets = createValidationFieldTargets(column, includeExtended);
    const errors = {};

    targets.forEach((field) => {
        const value = createDraft.value[field];
        const message = runClientValidationForField({
            column,
            field,
            value,
            trigger,
            contextMode: 'create',
            row: null,
            draft: createDraft.value,
        });

        if (message) {
            errors[field] = message;
        }
    });

    return errors;
}

function validateEditColumn(column, row, trigger, includeExtended = false) {
    const baseField = String(column?.editField || column?.key || '');
    const extendedTargets = includeExtended
        ? createValidationFieldTargets(column, true)
        : [baseField];
    const targets = extendedTargets.filter((field) => field !== '');
    const values = {
        [baseField]: editingValue.value,
        ...editingExtraUpdates.value,
    };
    const errors = {};

    targets.forEach((field) => {
        const message = runClientValidationForField({
            column,
            field,
            value: values[field],
            trigger,
            contextMode: 'edit',
            row,
            draft: values,
        });

        if (message) {
            errors[field] = message;
        }
    });

    return errors;
}

function applyCreateColumnValidation(column, trigger, includeExtended = false) {
    const errors = validateCreateColumn(column, trigger, includeExtended);
    const targets = createValidationFieldTargets(column, includeExtended);

    targets.forEach((field) => {
        clearCreateFieldError(field);
    });
    clearCreateFieldError(column.key);

    if (Object.keys(errors).length === 0) {
        return true;
    }

    setCreateFieldErrors({
        ...createFieldErrors.value,
        ...errors,
    });
    mutationErrorMessage.value = t(
        'validation.not_saved_check_fields',
        'Niet bewaard. Controleer de rood gemarkeerde velden.',
    );

    return false;
}

function clearCreateFieldErrors() {
    createFieldErrors.value = {};
}

function clearCreateFieldError(field) {
    const key = String(field || '');

    if (key === '' || !createFieldErrors.value[key]) {
        return;
    }

    const next = { ...createFieldErrors.value };
    delete next[key];
    createFieldErrors.value = next;
}

function setCreateFieldErrors(errors) {
    createFieldErrors.value =
        errors && typeof errors === 'object' ? errors : {};
}

function createColumnFieldError(column) {
    const baseField = String(column?.editField || column?.key || '');
    const customField =
        typeof column?.editCustomField === 'string'
            ? column.editCustomField
            : '';
    const sentinelField =
        typeof column?.editCustomSentinelField === 'string'
            ? column.editCustomSentinelField
            : '';

    return (
        createFieldErrors.value[baseField] ||
        createFieldErrors.value[String(column?.key || '')] ||
        (customField ? createFieldErrors.value[customField] : '') ||
        (sentinelField ? createFieldErrors.value[sentinelField] : '') ||
        ''
    );
}

function clearEditErrorsForRow(rowId) {
    const rowKey = String(rowId ?? '');

    if (rowKey === '' || !editFieldErrors.value[rowKey]) {
        return;
    }

    const next = { ...editFieldErrors.value };
    delete next[rowKey];
    editFieldErrors.value = next;
}

function setEditErrorsForRow(rowId, errors) {
    const rowKey = String(rowId ?? '');

    if (rowKey === '') {
        return;
    }

    editFieldErrors.value = {
        ...editFieldErrors.value,
        [rowKey]: errors && typeof errors === 'object' ? errors : {},
    };
}

function clearEditFieldError(rowId, field) {
    const rowKey = String(rowId ?? '');
    const fieldKey = String(field || '');

    if (rowKey === '' || fieldKey === '') {
        return;
    }

    const rowErrors = editFieldErrors.value[rowKey];

    if (!rowErrors || !rowErrors[fieldKey]) {
        return;
    }

    const nextRowErrors = { ...rowErrors };
    delete nextRowErrors[fieldKey];

    editFieldErrors.value = {
        ...editFieldErrors.value,
        [rowKey]: nextRowErrors,
    };
}

function editColumnFieldError(rowId, column) {
    const rowKey = String(rowId ?? '');

    if (rowKey === '') {
        return '';
    }

    const rowErrors = editFieldErrors.value[rowKey] || {};
    const baseField = String(column?.editField || column?.key || '');
    const customField =
        typeof column?.editCustomField === 'string'
            ? column.editCustomField
            : '';
    const sentinelField =
        typeof column?.editCustomSentinelField === 'string'
            ? column.editCustomSentinelField
            : '';

    return (
        rowErrors[baseField] ||
        rowErrors[String(column?.key || '')] ||
        (customField ? rowErrors[customField] : '') ||
        (sentinelField ? rowErrors[sentinelField] : '') ||
        ''
    );
}

function createColumnInputStyle(column) {
    const field = String(column?.editField || column?.key || '');

    if (!isColumnRequired(column) || column?.requiredHighlight === false) {
        return null;
    }

    if (createColumnFieldError(column)) {
        return null;
    }

    const value = createDraft.value[field];

    if (!isFieldEmptyValue(value)) {
        return null;
    }

    return {
        backgroundColor: requiredHighlightColor(column),
    };
}

function editColumnInputStyle(row, column) {
    if (!isColumnRequired(column) || column?.requiredHighlight === false) {
        return null;
    }

    if (editColumnFieldError(row?.[props.idKey], column)) {
        return null;
    }

    if (!isFieldEmptyValue(editingValue.value)) {
        return null;
    }

    return {
        backgroundColor: requiredHighlightColor(column),
    };
}

async function submitCreateRow(trigger = 'save') {
    if (!props.inlineCreate) {
        return false;
    }

    if (mutationLoading.value) {
        return false;
    }

    const url = resolveInlineCreateRoute();
    const payload = {
        ...createDraft.value,
        validationType: 'model',
    };

    mutationErrorMessage.value = '';
    clearCreateFieldErrors();

    const clientValidationErrors = {};

    createEditableFields().forEach((column) => {
        const errors = validateCreateColumn(
            column,
            trigger,
            trigger === 'save' || trigger === 'tab' || trigger === 'blur',
        );

        Object.entries(errors).forEach(([field, message]) => {
            if (!clientValidationErrors[field]) {
                clientValidationErrors[field] = message;
            }
        });
    });

    if (Object.keys(clientValidationErrors).length > 0) {
        setCreateFieldErrors(clientValidationErrors);
        mutationErrorMessage.value = t(
            'validation.not_saved_check_fields',
            'Niet bewaard. Controleer de rood gemarkeerde velden.',
        );
        return false;
    }

    const mode = normalizedValidationMode();

    if (mode === 'client') {
        const clientOnlyRow = {
            ...createDraft.value,
            [props.idKey]: fallbackCreatedRowId(),
        };

        insertCreatedRowLocally(clientOnlyRow);
        closeCreateRow();
        return true;
    }

    if (props.manualOrdering) {
        payload.manualOrdering = true;
        payload.manualOrderField = props.manualOrderField;
    }

    if (
        createInsertAboveId.value !== null &&
        createInsertAboveId.value !== undefined
    ) {
        payload.insertAboveId = createInsertAboveId.value;
    }

    if (!url) {
        const newRow = {
            ...createDraft.value,
            [props.idKey]: Date.now(),
        };

        insertCreatedRowLocally(newRow);
        closeCreateRow();
        return true;
    }

    let optimisticRowId = null;

    if (!props.managed) {
        const optimisticRow = hydrateCreatedRowFromPayload({}, payload, {});
        insertCreatedRowLocally(optimisticRow);
        optimisticRowId = optimisticRow?.[props.idKey] ?? null;
    }

    mutationLoading.value = true;

    try {
        const axiosInstance = getAxios();
        const response = await axiosInstance.post(url, payload);
        const rawResponseData = response?.data ?? {};
        const createdRow = hydrateCreatedRowFromPayload(
            rawResponseData?.data ?? rawResponseData,
            payload,
            rawResponseData,
        );

        if (props.managed) {
            await fetchManaged({
                append: false,
                pageOverride: currentPage.value,
            });
        } else if (createdRow) {
            if (optimisticRowId !== null && optimisticRowId !== undefined) {
                replaceCreatedRowLocally(optimisticRowId, createdRow);
            } else {
                insertCreatedRowLocally(createdRow);
            }

            if (
                createInsertAboveId.value === null ||
                createInsertAboveId.value === undefined
            ) {
                currentPage.value = lastPage.value;
            }
        }

        closeCreateRow();
        return true;
    } catch (error) {
        if (optimisticRowId !== null && optimisticRowId !== undefined) {
            removeCreatedRowLocally(optimisticRowId);
        }

        const feedback = extractValidationFeedback(error);

        if (Object.keys(feedback.errors).length > 0) {
            setCreateFieldErrors(feedback.errors);
            mutationErrorMessage.value = t(
                'validation.not_saved_check_fields',
                'Niet bewaard. Controleer de rood gemarkeerde velden.',
            );
        } else if (feedback.message) {
            mutationErrorMessage.value = feedback.message;
        } else {
            mutationErrorMessage.value = t(
                'validation.not_saved_unexpected',
                'Niet bewaard door een onverwachte fout.',
            );
        }

        return false;
    } finally {
        mutationLoading.value = false;
    }
}

function resolveInlineCreateRoute() {
    if (typeof props.inlineCreateRoute === 'function') {
        return props.inlineCreateRoute();
    }

    if (
        typeof props.inlineCreateRoute === 'string' &&
        props.inlineCreateRoute
    ) {
        return props.inlineCreateRoute;
    }

    if (typeof props.dataSource?.path === 'string' && props.dataSource.path) {
        return `${props.dataSource.path.replace(/\/$/, '')}/inline-create`;
    }

    return null;
}

function startEdit(row, column) {
    if (!column?.editable) {
        return;
    }

    const rowId = row?.[props.idKey];

    if (rowId === undefined || rowId === null) {
        return;
    }

    if (createRowVisible.value) {
        return;
    }

    editingRowId.value = rowId;
    editingColumnKey.value = column.key;
    editingValue.value =
        column.editMultiple === true
            ? normalizeMultiValue(row[column.editField || column.key])
            : (row[column.editField || column.key] ?? '');
    editingExtraUpdates.value = {};
    mutationErrorMessage.value = '';
    clearEditErrorsForRow(rowId);

    focusEditInput(rowId, column.key);
}

function isSameRowId(left, right) {
    if (
        left === null ||
        left === undefined ||
        right === null ||
        right === undefined
    ) {
        return false;
    }

    return String(left) === String(right);
}

function isEditingCell(row, column) {
    return (
        isSameRowId(editingRowId.value, row?.[props.idKey]) &&
        editingColumnKey.value === column?.key
    );
}

function isCurrentEditSession(rowId, columnKey) {
    return (
        isSameRowId(editingRowId.value, rowId) &&
        editingColumnKey.value === columnKey
    );
}

function cancelEdit() {
    if (editingRowId.value !== null && editingRowId.value !== undefined) {
        clearEditErrorsForRow(editingRowId.value);
    }

    editingRowId.value = null;
    editingColumnKey.value = null;
    editingValue.value = '';
    editingExtraUpdates.value = {};
}

async function commitEdit(trigger = 'save') {
    if (editingRowId.value === null || editingColumnKey.value === null) {
        return false;
    }

    if (editCommitPromise.value) {
        return editCommitPromise.value;
    }

    const originRowId = editingRowId.value;
    const originColumnKey = editingColumnKey.value;
    const originValue = editingValue.value;
    const originExtraUpdates = { ...editingExtraUpdates.value };

    const commitPromise = (async () => {
        const row = findRowById(originRowId);
        const column = selectedColumns.value.find(
            (item) => item.key === originColumnKey,
        );

        if (!row || !column) {
            if (isCurrentEditSession(originRowId, originColumnKey)) {
                cancelEdit();
            }

            return false;
        }

        const field = column.editField || column.key;
        const value = originValue;
        const extraUpdates = { ...originExtraUpdates };
        const updateUrl = resolveInlineUpdateRoute(originRowId);

        mutationErrorMessage.value = '';
        clearEditErrorsForRow(originRowId);

        const clientErrors = validateEditColumn(
            column,
            row,
            trigger,
            trigger === 'save',
        );

        if (Object.keys(clientErrors).length > 0) {
            setEditErrorsForRow(originRowId, clientErrors);
            mutationErrorMessage.value = t(
                'validation.not_saved_check_fields',
                'Niet bewaard. Controleer de rood gemarkeerde velden.',
            );
            return false;
        }

        if (normalizedValidationMode() === 'client') {
            applyLocalUpdate(row, column, value);
            applyLocalExtraUpdates(row, extraUpdates);

            if (isCurrentEditSession(originRowId, originColumnKey)) {
                cancelEdit();
            }

            return true;
        }

        if (!updateUrl) {
            applyLocalUpdate(row, column, value);
            applyLocalExtraUpdates(row, extraUpdates);

            if (isCurrentEditSession(originRowId, originColumnKey)) {
                cancelEdit();
            }

            return true;
        }

        mutationLoading.value = true;

        try {
            const axiosInstance = getAxios();

            const payload = {
                field,
                value,
                validationType: column.validationType || 'model',
            };

            if (Object.keys(extraUpdates).length > 0) {
                payload.extraUpdates = Object.entries(extraUpdates).map(
                    ([updateField, updateValue]) => ({
                        field: updateField,
                        value: updateValue,
                    }),
                );

                const extraValidationRules = resolveEditExtraValidationRules(
                    column,
                    extraUpdates,
                );

                if (Object.keys(extraValidationRules).length > 0) {
                    payload.extraValidationRules = extraValidationRules;
                }
            }

            if (payload.validationType === 'client' && column.validationRules) {
                payload.validationRules = column.validationRules;
            }

            const response = await axiosInstance.patch(updateUrl, payload);

            const serverValue =
                response?.data?.value !== undefined
                    ? response.data.value
                    : value;
            const serverUpdated =
                response?.data?.updated &&
                typeof response.data.updated === 'object'
                    ? response.data.updated
                    : null;

            applyLocalUpdate(row, column, serverValue);

            if (serverUpdated) {
                applyLocalExtraUpdates(row, serverUpdated);
            } else {
                applyLocalExtraUpdates(row, extraUpdates);
            }

            if (isCurrentEditSession(originRowId, originColumnKey)) {
                cancelEdit();
            }

            return true;
        } catch (error) {
            const feedback = extractValidationFeedback(error);

            if (Object.keys(feedback.errors).length > 0) {
                setEditErrorsForRow(originRowId, feedback.errors);
                mutationErrorMessage.value = t(
                    'validation.not_saved_check_fields',
                    'Niet bewaard. Controleer de rood gemarkeerde velden.',
                );
            } else if (feedback.message) {
                mutationErrorMessage.value = feedback.message;
            } else {
                mutationErrorMessage.value = t(
                    'validation.not_saved_unexpected',
                    'Niet bewaard door een onverwachte fout.',
                );
            }

            return false;
        } finally {
            mutationLoading.value = false;
        }
    })();

    editCommitPromise.value = commitPromise;

    try {
        return await commitPromise;
    } finally {
        if (editCommitPromise.value === commitPromise) {
            editCommitPromise.value = null;
        }
    }
}

function applyLocalUpdate(row, column, value) {
    const field = column.editField || column.key;

    row[field] = value;

    if (field !== column.key) {
        const displayFields = column.editDisplayFields;

        if (displayFields && typeof displayFields === 'object') {
            const valueField = column.editItemValue || 'value';
            const selectedItem = Array.isArray(column.editItems)
                ? column.editItems.find(
                      (item) => String(item?.[valueField]) === String(value),
                  )
                : null;

            if (selectedItem) {
                Object.entries(displayFields).forEach(
                    ([targetField, itemKey]) => {
                        row[targetField] = selectedItem[itemKey];
                    },
                );
            }
        }
    }

    row[column.key] = row[column.key] ?? value;
}

function resolveInlineUpdateRoute(id) {
    if (typeof props.inlineUpdateRoute === 'function') {
        return props.inlineUpdateRoute(id);
    }

    if (
        typeof props.inlineUpdateRoute === 'string' &&
        props.inlineUpdateRoute
    ) {
        if (props.inlineUpdateRoute.includes(':id')) {
            return props.inlineUpdateRoute.replace(':id', String(id));
        }

        if (props.inlineUpdateRoute.includes('{id}')) {
            return props.inlineUpdateRoute.replace('{id}', String(id));
        }

        return `${props.inlineUpdateRoute.replace(/\/$/, '')}/${id}/inline`;
    }

    if (typeof props.dataSource?.path === 'string' && props.dataSource.path) {
        return `${props.dataSource.path.replace(/\/$/, '')}/${id}/inline`;
    }

    return null;
}

function resolveInlineDeleteRoute(id) {
    if (typeof props.inlineDeleteRoute === 'function') {
        return props.inlineDeleteRoute(id);
    }

    if (
        typeof props.inlineDeleteRoute === 'string' &&
        props.inlineDeleteRoute
    ) {
        if (props.inlineDeleteRoute.includes(':id')) {
            return props.inlineDeleteRoute.replace(':id', String(id));
        }

        if (props.inlineDeleteRoute.includes('{id}')) {
            return props.inlineDeleteRoute.replace('{id}', String(id));
        }

        return `${props.inlineDeleteRoute.replace(/\/$/, '')}/${id}`;
    }

    if (typeof props.dataSource?.path === 'string' && props.dataSource.path) {
        return `${props.dataSource.path.replace(/\/$/, '')}/${id}`;
    }

    return null;
}

async function deleteRow(row) {
    const rowId = row?.[props.idKey];

    if (rowId === undefined || rowId === null) {
        return;
    }

    let extraPayload = {};

    if (typeof props.beforeInlineDelete === 'function') {
        const hookResult = await props.beforeInlineDelete({ id: rowId, row });

        if (hookResult === false) {
            return;
        }

        if (hookResult && typeof hookResult === 'object') {
            if (hookResult.proceed === false) {
                return;
            }

            if (hookResult.payload && typeof hookResult.payload === 'object') {
                extraPayload = hookResult.payload;
            }
        }
    }

    const deleteUrl = resolveInlineDeleteRoute(rowId);

    if (!deleteUrl) {
        internalRows.value = internalRows.value.filter(
            (item) => item[props.idKey] !== rowId,
        );
        syncClientInfiniteCountAfterMutation();
        return;
    }

    mutationLoading.value = true;

    try {
        const axiosInstance = getAxios();
        await axiosInstance.delete(deleteUrl, { data: extraPayload });

        internalRows.value = internalRows.value.filter(
            (item) => item[props.idKey] !== rowId,
        );
        syncClientInfiniteCountAfterMutation();
        serverInfiniteRows.value = serverInfiniteRows.value.filter(
            (item) => item[props.idKey] !== rowId,
        );
    } finally {
        mutationLoading.value = false;
    }
}

function resolvedRowMenuItems(row) {
    if (typeof props.rowMenuItems === 'function') {
        const customItems = props.rowMenuItems(row);

        if (Array.isArray(customItems)) {
            return customItems;
        }
    }

    if (Array.isArray(props.rowMenuItems) && props.rowMenuItems.length) {
        return props.rowMenuItems;
    }

    const items = [
        {
            key: 'edit',
            label: t('actions.edit', 'Bewerken'),
            icon: 'mdi-pencil',
            disabled: editableColumns.value.length === 0,
        },
    ];

    if (props.inlineCreate && props.manualOrdering) {
        items.push({
            key: 'insertAbove',
            label: t('actions.insert_above', 'Invoegen boven'),
            icon: 'mdi-arrow-up-bold-box-outline',
        });
    }

    if (props.inlineCreate) {
        items.push({
            key: 'insert',
            label: t('actions.new_row', 'Nieuwe rij'),
            icon: 'mdi-plus-box-outline',
        });
    }

    items.push({
        key: 'delete',
        label: t('actions.delete', 'Verwijderen'),
        icon: 'mdi-delete-outline',
    });

    return items;
}

async function onRowMenuAction(item, row) {
    emit('on-row-menu-item-click', { item, row });
    rowMenuOpenId.value = null;

    if (!item || item.disabled) {
        return;
    }

    if (item.key === 'edit') {
        const firstEditable = editableColumns.value[0];

        if (firstEditable) {
            startEdit(row, firstEditable);
        }

        return;
    }

    if (item.key === 'insertAbove') {
        openCreateRow(row[props.idKey]);
        return;
    }

    if (item.key === 'insert') {
        openCreateRow(null);
        return;
    }

    if (item.key === 'delete') {
        await deleteRow(row);
    }
}

function onCreateInputBlur(column) {
    if (!column?.editable) {
        return;
    }

    applyCreateColumnValidation(column, 'blur', true);
}

async function onEditInputBlur(row, column) {
    if (!column?.editable || !isEditingCell(row, column)) {
        return;
    }

    await commitEdit('blur');
}

async function onEditInputKeydown(event) {
    if (event.defaultPrevented) {
        return;
    }

    if (event.key === 'Enter') {
        event.preventDefault();
        await commitEdit();
        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        cancelEdit();
        return;
    }

    if (event.key === 'Tab') {
        event.preventDefault();
        await navigateEditFocus(event.shiftKey ? -1 : 1, false, 'tab');
        return;
    }

    if (event.key === 'ArrowLeft') {
        event.preventDefault();
        await navigateEditFocus(-1, false);
        return;
    }

    if (event.key === 'ArrowRight') {
        event.preventDefault();
        await navigateEditFocus(1, false);
        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        await navigateEditFocus(-1, true);
        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        await navigateEditFocus(1, true);
    }
}

function resolveHorizontalEditTarget(direction, originRowId, originColumnKey) {
    const order = editableCellOrder();
    const currentIndex = order.findIndex(
        (item) =>
            isSameRowId(item.rowId, originRowId) &&
            item.columnKey === originColumnKey,
    );

    if (currentIndex < 0) {
        return { target: null, boundary: false };
    }

    const nextIndex = currentIndex + direction;

    if (nextIndex < 0 || nextIndex >= order.length) {
        return { target: null, boundary: true };
    }

    return { target: order[nextIndex], boundary: false };
}

function resolveVerticalEditTarget(direction, originRowId, originColumnKey) {
    if (!originColumnKey) {
        return null;
    }

    const currentRowIndex = renderedRows.value.findIndex((row) =>
        isSameRowId(row[props.idKey], originRowId),
    );

    if (currentRowIndex < 0) {
        return null;
    }

    const nextRowIndex = currentRowIndex + direction;

    if (nextRowIndex < 0 || nextRowIndex >= renderedRows.value.length) {
        return null;
    }

    const targetRow = renderedRows.value[nextRowIndex];
    const targetColumn = selectedColumns.value.find(
        (column) => column.key === originColumnKey,
    );

    if (!targetRow || !targetColumn || !targetColumn.editable) {
        return null;
    }

    return {
        row: targetRow,
        column: targetColumn,
    };
}

async function navigateEditFocus(
    direction,
    vertical = false,
    trigger = 'save',
) {
    if (editingRowId.value === null || editingColumnKey.value === null) {
        return;
    }

    const originRowId = editingRowId.value;
    const originColumnKey = editingColumnKey.value;
    const verticalTarget = vertical
        ? resolveVerticalEditTarget(direction, originRowId, originColumnKey)
        : null;
    const horizontalTarget = vertical
        ? { target: null, boundary: false }
        : resolveHorizontalEditTarget(direction, originRowId, originColumnKey);

    const saved = await commitEdit(trigger);

    if (!saved) {
        return;
    }

    if (vertical) {
        if (verticalTarget) {
            startEdit(verticalTarget.row, verticalTarget.column);
            return;
        }

        if (direction > 0 && props.inlineCreate) {
            const targetColumn = selectedColumns.value.find(
                (column) => column.key === originColumnKey && column.editable,
            );

            if (!targetColumn) {
                return;
            }

            openCreateRow(null);
            await nextTick();
            focusCreateField(targetColumn);
        }

        return;
    }

    if (!horizontalTarget.target) {
        if (horizontalTarget.boundary && direction > 0 && props.inlineCreate) {
            openCreateRow(null);
        }

        return;
    }

    startEdit(horizontalTarget.target.row, horizontalTarget.target.column);
}

async function onCreateInputKeydown(event) {
    if (event.defaultPrevented) {
        return;
    }

    if (event.key === 'Enter') {
        event.preventDefault();
        await submitCreateRow();
        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        closeCreateRow();
        return;
    }

    if (event.key === 'Tab') {
        event.preventDefault();

        const fields = createEditableFields();
        const activeField =
            document.activeElement?.getAttribute('data-create-field');
        const activeColumn =
            fields.find(
                (column) => (column.editField || column.key) === activeField,
            ) ?? null;

        if (
            activeColumn &&
            !applyCreateColumnValidation(activeColumn, 'tab', true)
        ) {
            return;
        }

        if (event.shiftKey) {
            const currentIndex = fields.findIndex(
                (column) => (column.editField || column.key) === activeField,
            );

            if (currentIndex <= 0) {
                const previousTarget =
                    resolveCreatePreviousRowLastEditableTarget();
                await jumpFromCreateToEdit(previousTarget);
                return;
            }

            moveCreateFocus(-1);
            return;
        }

        if (fields.length === 0) {
            return;
        }
        const currentIndex = fields.findIndex(
            (column) => (column.editField || column.key) === activeField,
        );

        if (currentIndex < 0) {
            focusCreateField(fields[0]);
            return;
        }

        if (currentIndex >= fields.length - 1) {
            const openNextAppendRow =
                createInsertAboveId.value === null ||
                createInsertAboveId.value === undefined;

            const saved = await submitCreateRow('tab');

            if (saved && openNextAppendRow) {
                await nextTick();
                openCreateRow(null);
            }

            return;
        }

        moveCreateFocus(1);
        return;
    }

    if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
        event.preventDefault();

        const activeField =
            document.activeElement?.getAttribute('data-create-field');

        if (!activeField) {
            return;
        }

        const target = resolveCreateNeighborTarget(
            event.key === 'ArrowUp' ? -1 : 1,
            activeField,
        );

        await jumpFromCreateToEdit(target);
    }
}

function editableCellOrder() {
    const order = [];

    renderedRows.value.forEach((row) => {
        selectedColumns.value.forEach((column) => {
            if (!column.editable) {
                return;
            }

            order.push({
                rowId: row[props.idKey],
                columnKey: column.key,
                row,
                column,
            });
        });
    });

    return order;
}

function createEditableFields() {
    return selectedColumns.value.filter((column) => column.editable);
}

function createRenderIndex() {
    if (!createRowVisible.value) {
        return null;
    }

    if (
        createInsertAboveId.value === null ||
        createInsertAboveId.value === undefined
    ) {
        return renderedRows.value.length;
    }

    const index = renderedRows.value.findIndex((row) =>
        isSameRowId(row?.[props.idKey], createInsertAboveId.value),
    );

    if (index < 0) {
        return renderedRows.value.length;
    }

    return index;
}

function resolveCreateNeighborTarget(direction, fieldKey) {
    const editableField = createEditableFields().find(
        (column) => (column.editField || column.key) === fieldKey,
    );

    if (!editableField) {
        return null;
    }

    const index = createRenderIndex();

    if (index === null) {
        return null;
    }

    const targetIndex = direction < 0 ? index - 1 : index;

    if (targetIndex < 0 || targetIndex >= renderedRows.value.length) {
        return null;
    }

    const targetRow = renderedRows.value[targetIndex];

    if (!targetRow) {
        return null;
    }

    const targetColumn = selectedColumns.value.find(
        (column) => column.key === editableField.key && column.editable,
    );

    if (!targetColumn) {
        return null;
    }

    return {
        row: targetRow,
        column: targetColumn,
    };
}

function resolveCreatePreviousRowLastEditableTarget() {
    const index = createRenderIndex();

    if (index === null) {
        return null;
    }

    const targetIndex = index - 1;

    if (targetIndex < 0 || targetIndex >= renderedRows.value.length) {
        return null;
    }

    const targetRow = renderedRows.value[targetIndex];
    const lastEditable =
        editableColumns.value[editableColumns.value.length - 1];

    if (!targetRow || !lastEditable) {
        return null;
    }

    return {
        row: targetRow,
        column: lastEditable,
    };
}

async function jumpFromCreateToEdit(target) {
    if (!target?.row || !target?.column) {
        return;
    }

    closeCreateRow();
    await nextTick();
    startEdit(target.row, target.column);
}

function moveCreateFocus(direction) {
    const fields = createEditableFields();

    if (fields.length === 0) {
        return;
    }

    const active = document.activeElement;
    const activeField = active?.getAttribute('data-create-field');
    const currentIndex = fields.findIndex(
        (column) => (column.editField || column.key) === activeField,
    );

    if (currentIndex < 0) {
        focusCreateField(fields[0]);
        return;
    }

    const nextIndex = currentIndex + direction;

    if (nextIndex < 0 || nextIndex >= fields.length) {
        return;
    }

    focusCreateField(fields[nextIndex]);
}

function focusCreateField(column) {
    const field = column.editField || column.key;
    const el = createInputElements.get(field);

    if (!el) {
        return;
    }

    if (typeof el.scrollIntoView === 'function') {
        el.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    }

    el.focus();
    if (typeof el.select === 'function') {
        el.select();
    }
}

function scrollCreateRowToBottom() {
    const container = containerRef.value;

    if (!container) {
        return;
    }

    if (typeof container.scrollTo === 'function') {
        container.scrollTo({ top: container.scrollHeight, behavior: 'smooth' });
        return;
    }

    container.scrollTop = container.scrollHeight;
}

function focusFirstCreateInput() {
    nextTick(() => {
        const fields = createEditableFields();

        if (fields.length === 0) {
            return;
        }

        focusCreateField(fields[0]);
    });
}

function registerEditInput(rowId, columnKey, el) {
    const token = `${String(rowId)}::${columnKey}`;

    if (!el) {
        editInputElements.delete(token);
        return;
    }

    editInputElements.set(token, el);
}

function registerCreateInput(field, el) {
    if (!el) {
        createInputElements.delete(field);
        return;
    }

    createInputElements.set(field, el);
}

function focusEditInput(rowId, columnKey) {
    nextTick(() => {
        const token = `${String(rowId)}::${columnKey}`;
        const el = editInputElements.get(token);

        if (!el) {
            return;
        }

        el.focus();
        if (typeof el.select === 'function') {
            el.select();
        }
    });
}

function findRowById(rowId) {
    const fromInternal = internalRows.value.find((row) =>
        isSameRowId(row[props.idKey], rowId),
    );

    if (fromInternal) {
        return fromInternal;
    }

    return serverInfiniteRows.value.find((row) =>
        isSameRowId(row[props.idKey], rowId),
    );
}

function getAxios() {
    if (!window?.axios) {
        throw new Error('window.axios is not available.');
    }

    return window.axios;
}

function toggleRowMenu(rowId) {
    rowMenuOpenId.value = rowMenuOpenId.value === rowId ? null : rowId;
}

function isRowMenuOpen(rowId) {
    return rowMenuOpenId.value === rowId;
}

function showsInlineRowMenu(row, colIndex) {
    if (!props.rowMenu || colIndex !== 0) {
        return false;
    }

    return row?.[props.idKey] !== undefined && row?.[props.idKey] !== null;
}

function isRowHovered(row) {
    const rowId = row?.[props.idKey];

    if (rowId === undefined || rowId === null) {
        return false;
    }

    return hoveredRowId.value === rowId;
}

function shouldShowInlineRowMenuButton(row) {
    const rowId = row?.[props.idKey];

    if (rowId === undefined || rowId === null) {
        return false;
    }

    return isRowMenuOpen(rowId) || isRowHovered(row);
}

function rowClass() {
    return 'border-b border-slate-100 last:border-b-0';
}

function rowHighlightColor(row, rowIndex) {
    if (isRowHovered(row)) {
        return 'rgb(241 245 249)';
    }

    if (hasKeyboardContext.value && rowIndex === activeRowIndex.value) {
        return 'rgb(245 247 250)';
    }

    return null;
}

function withRowHighlight(style, row, rowIndex) {
    const highlight = rowHighlightColor(row, rowIndex);

    if (!highlight) {
        return style;
    }

    return {
        ...(style || {}),
        backgroundColor: highlight,
    };
}

function onRowMouseEnter(row) {
    const rowId = row?.[props.idKey];
    hoveredRowId.value = rowId === undefined || rowId === null ? null : rowId;
}

function onRowMouseLeave(row) {
    const rowId = row?.[props.idKey];

    if (rowId === undefined || rowId === null) {
        return;
    }

    if (hoveredRowId.value === rowId) {
        hoveredRowId.value = null;
    }
}

function onRowFocus(rowIndex) {
    setActiveRowByIndex(rowIndex);
    hasKeyboardContext.value = true;
}

function closeFloatingOverlays() {
    toolbarMenuOpen.value = false;
    columnMenuOpen.value = false;
    configPanelOpen.value = false;
    showExcelDialog.value = false;
    showChartDialog.value = false;
    openFilterKey.value = null;
    pendingFilterFocus.value = null;
    rowMenuOpenId.value = null;
    onColumnDragEnd();
}

function onGlobalPointerdown(event) {
    if (!rootRef.value) {
        return;
    }

    const target = event.target;

    if (!(target instanceof HTMLElement)) {
        return;
    }

    const isInsideRoot = rootRef.value.contains(target);
    const isInsideAutocompletePortal = Boolean(
        target.closest('[data-rw-autocomplete-portal="true"]'),
    );

    if (!isInsideRoot && !isInsideAutocompletePortal) {
        closeFloatingOverlays();
        hasKeyboardContext.value = false;
        return;
    }

    hasKeyboardContext.value = true;
}

function onGlobalFocusin(event) {
    if (!rootRef.value) {
        return;
    }

    const target = event.target;

    if (!(target instanceof HTMLElement)) {
        return;
    }

    hasKeyboardContext.value = rootRef.value.contains(target);
}

function onGlobalKeydown(event) {
    if (!rootRef.value) {
        return;
    }

    const target = event.target;

    if (!(target instanceof HTMLElement)) {
        return;
    }

    const isInsideRoot = rootRef.value.contains(target);

    if (isInsideRoot) {
        hasKeyboardContext.value = true;
    }

    if (!isInsideRoot && !hasKeyboardContext.value) {
        return;
    }

    if (event.key === 'Escape') {
        if (isAutocompleteListOpenForTarget(target)) {
            return;
        }

        if (showChartDialog.value) {
            event.preventDefault();
            showChartDialog.value = false;
            return;
        }

        if (showExcelDialog.value) {
            event.preventDefault();
            showExcelDialog.value = false;
            return;
        }

        if (
            rowMenuOpenId.value !== null ||
            openFilterKey.value !== null ||
            columnMenuOpen.value ||
            toolbarMenuOpen.value
        ) {
            event.preventDefault();
            closeFloatingOverlays();
            return;
        }

        if (createRowVisible.value) {
            event.preventDefault();
            closeCreateRow();
            return;
        }

        if (editingRowId.value !== null) {
            event.preventDefault();
            cancelEdit();
        }

        return;
    }

    if (isInputLike(target)) {
        return;
    }

    if (event.key === 'Insert' && props.inlineCreate) {
        event.preventDefault();
        openCreateRow(null);
        return;
    }

    if (event.altKey && event.key.toLowerCase() === 'm' && props.rowMenu) {
        event.preventDefault();
        const row = renderedRows.value[activeRowIndex.value];

        if (row) {
            toggleRowMenu(row[props.idKey]);
        }

        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        setActiveRowByIndex(activeRowIndex.value + 1);
        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        setActiveRowByIndex(activeRowIndex.value - 1);
        return;
    }

    if (event.key === 'Enter') {
        const row = renderedRows.value[activeRowIndex.value];
        const firstEditable = editableColumns.value[0];

        if (row && firstEditable) {
            event.preventDefault();
            startEdit(row, firstEditable);
        }
    }
}

function isAutocompleteListOpenForTarget(target) {
    const autocompleteInput = target.closest('[data-rw-autocomplete-input]');

    if (!autocompleteInput) {
        return false;
    }

    return autocompleteInput.getAttribute('aria-expanded') === 'true';
}

function isInputLike(target) {
    const tag = target.tagName;

    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(tag)) {
        return true;
    }

    return target.isContentEditable;
}

function normalizeDomId(value) {
    const normalized = String(value ?? 'rwtable')
        .toLowerCase()
        .replace(/[^a-z0-9_-]+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');

    return normalized || 'rwtable';
}

function normalizeHeightValue(value) {
    const raw = String(value ?? '').trim();

    if (raw === '') {
        return '520px';
    }

    if (/^\d+$/.test(raw)) {
        return `${raw}px`;
    }

    return raw;
}

function normalizeSizeValue(value) {
    if (value === null || value === undefined) {
        return null;
    }

    const raw = String(value).trim();

    if (raw === '') {
        return null;
    }

    if (/^\d+(\.\d+)?$/.test(raw)) {
        return `${raw}px`;
    }

    return raw;
}

function normalizeRowsPerPageValue(value) {
    const fallbackCandidate = Number(props.rowOptions?.[0] ?? 25);
    const fallback =
        Number.isFinite(fallbackCandidate) && fallbackCandidate > 0
            ? Math.round(fallbackCandidate)
            : 25;

    const numeric = Number(value);

    if (!Number.isFinite(numeric) || numeric <= 0) {
        return fallback;
    }

    const normalized = Math.round(numeric);
    const allowed = Array.isArray(props.rowOptions)
        ? props.rowOptions
              .map((option) => Number(option))
              .filter((option) => Number.isFinite(option) && option > 0)
              .map((option) => Math.round(option))
        : [];

    if (allowed.length === 0) {
        return normalized;
    }

    return allowed.includes(normalized) ? normalized : fallback;
}

function storageKey() {
    const fromProp =
        typeof props.tableId === 'string' && props.tableId.trim() !== ''
            ? props.tableId.trim()
            : props.columns.map((column) => String(column.key)).join('|');

    return `rwtable:columns:${fromProp}`;
}

function columnWidthsStorageKey() {
    return `${storageKey()}:widths`;
}

function lockCountStorageKey() {
    return `${storageKey()}:lockCount`;
}

function configStorageKey() {
    return `${storageKey()}:config`;
}

function returnRestoreStateStorageKey() {
    if (!props.restoreState || !restorePath) {
        return null;
    }

    return buildRwTableRestoreStateKey(restorePath, storageKey());
}

function getRowOffsetFromViewportTop(rowId) {
    if (!containerRef.value || rowId === null || rowId === undefined) {
        return null;
    }

    const rowEl = containerRef.value.querySelector(
        `tr[data-row-id="${rowId}"]`,
    );
    if (!rowEl) {
        return null;
    }

    const containerRect = containerRef.value.getBoundingClientRect();
    const rowRect = rowEl.getBoundingClientRect();

    return rowRect.top - containerRect.top;
}

function buildReturnRestoreAnchor(rowId) {
    if (rowId === null || rowId === undefined) {
        return null;
    }

    const anchor = {
        rowId,
        offsetFromViewportTop: getRowOffsetFromViewportTop(rowId),
        page: Number(currentPage.value ?? 1) || 1,
        rowIndexInPage: null,
    };

    if (!isInfiniteMode.value) {
        const pageRows = renderedRows.value ?? [];
        const pageIndex = pageRows.findIndex((row) =>
            isSameRowId(row?.[props.idKey], rowId),
        );
        anchor.rowIndexInPage = pageIndex >= 0 ? pageIndex : null;

        return anchor;
    }

    const perPage = Math.max(1, Number(rowsPerPage.value ?? 25) || 25);

    if (isServerMode.value) {
        const loadedRows = serverInfiniteRows.value ?? [];
        const loadedIndex = loadedRows.findIndex((row) =>
            isSameRowId(row?.[props.idKey], rowId),
        );
        if (loadedIndex >= 0) {
            anchor.page = 1 + Math.floor(loadedIndex / perPage);
            anchor.rowIndexInPage = loadedIndex % perPage;
        }

        return anchor;
    }

    const loadedRows = renderedRows.value ?? [];
    const loadedIndex = loadedRows.findIndex((row) =>
        isSameRowId(row?.[props.idKey], rowId),
    );
    if (loadedIndex >= 0) {
        anchor.page = 1 + Math.floor(loadedIndex / perPage);
        anchor.rowIndexInPage = loadedIndex % perPage;
    }

    return anchor;
}

function currentActiveRowId() {
    const row = renderedRows.value[activeRowIndex.value];
    return row?.[props.idKey] ?? null;
}

function setActiveRowById(rowId) {
    if (rowId === undefined || rowId === null) {
        return;
    }

    const nextIndex = renderedRows.value.findIndex(
        (row) => String(row?.[props.idKey]) === String(rowId),
    );

    if (nextIndex >= 0) {
        setActiveRowByIndex(nextIndex);
    }
}

function readStoredReturnRestoreState() {
    if (typeof window === 'undefined') {
        return null;
    }

    const key = returnRestoreStateStorageKey();
    if (!key) {
        return null;
    }

    try {
        const raw = window.sessionStorage.getItem(key);
        if (!raw) {
            return null;
        }

        const parsed = JSON.parse(raw);
        return parsed && typeof parsed === 'object' ? parsed : null;
    } catch {
        return null;
    }
}

function persistReturnRestoreState() {
    if (!props.restoreState || typeof window === 'undefined') {
        return;
    }

    const key = returnRestoreStateStorageKey();
    if (!key) {
        return;
    }

    const scrollTop = Number(containerRef.value?.scrollTop ?? 0);
    const payload = {
        activeRowId: currentActiveRowId(),
        activeRowIndex: Number(activeRowIndex.value ?? 0) || 0,
        scrollTop: Number.isFinite(scrollTop) ? scrollTop : 0,
        currentPage: Number(currentPage.value ?? 1) || 1,
        loadedEndPage:
            Number(internalMeta.value?.current ?? currentPage.value ?? 1) || 1,
        rowsPerPage: Number(rowsPerPage.value ?? 25) || 25,
        anchorPage: Number(pendingReturnRestoreAnchor.value?.page ?? 0) || null,
        anchorRowIndexInPage: Number.isFinite(
            Number(pendingReturnRestoreAnchor.value?.rowIndexInPage),
        )
            ? Number(pendingReturnRestoreAnchor.value?.rowIndexInPage)
            : null,
        anchorOffsetFromViewportTop: Number.isFinite(
            Number(pendingReturnRestoreAnchor.value?.offsetFromViewportTop),
        )
            ? Number(pendingReturnRestoreAnchor.value?.offsetFromViewportTop)
            : null,
        savedAt: Date.now(),
    };

    window.sessionStorage.setItem(key, JSON.stringify(payload));
}

const persistReturnRestoreStateDebounced = debounce(() => {
    persistReturnRestoreState();
}, 120);

function queueReturnRestoreMarkerCleanup(
    marker = pendingReturnRestoreMarker.value,
) {
    if (!marker || typeof window === 'undefined') {
        return;
    }

    if (restoreMarkerCleanupTimer) {
        window.clearTimeout(restoreMarkerCleanupTimer);
    }

    restoreMarkerCleanupTimer = window.setTimeout(() => {
        clearRwTableReturnMarker(marker.token);
        restoreMarkerCleanupTimer = null;
    }, 800);
}

function initializeReturnRestoreContext() {
    if (!props.restoreState || typeof window === 'undefined' || !restorePath) {
        return;
    }

    const marker = readRwTableReturnMarker();
    if (!shouldApplyRwTableReturnMarker(marker, restorePath)) {
        return;
    }

    pendingReturnRestoreMarker.value = marker;
    pendingReturnRestoreState.value = readStoredReturnRestoreState();
    queueReturnRestoreMarkerCleanup(marker);
}

async function waitForTableIdle(maxChecks = 200) {
    let checks = maxChecks;
    while (tableLoading.value && checks > 0) {
        await new Promise((resolve) => window.setTimeout(resolve, 25));
        checks -= 1;
    }
}

function returnRestorePage(snapshot) {
    const rawPage = Number(
        isInfiniteMode.value
            ? (snapshot?.anchorPage ?? 1)
            : (snapshot?.currentPage ?? 1),
    );

    if (!Number.isFinite(rawPage) || rawPage < 1) {
        return 1;
    }

    return Math.max(1, Math.floor(rawPage));
}

async function restoreInfiniteRowsFromSnapshot(snapshot) {
    const targetPage = Number(
        snapshot?.anchorPage ?? snapshot?.loadedEndPage ?? 1,
    );
    if (!Number.isFinite(targetPage) || targetPage <= 1) {
        return;
    }

    if (props.managed) {
        const currentLoadedPage = Math.max(
            1,
            Number(internalMeta.value?.current ?? currentPage.value ?? 1) || 1,
        );
        if (
            currentLoadedPage === targetPage &&
            currentPage.value === targetPage
        ) {
            return;
        }

        await fetchManaged({ append: false, pageOverride: targetPage });
        await waitForTableIdle();
        return;
    }

    if (isServerMode.value) {
        currentPage.value = targetPage;
        await nextTick();
        return;
    }

    if (!isServerMode.value) {
        const nextCount = Math.max(
            targetPage * rowsPerPage.value,
            rowsPerPage.value,
        );
        clientInfiniteCount.value = Math.min(
            nextCount,
            clientRows.value.length,
        );
    }
}

async function applyReturnRestoreState() {
    if (
        hasAppliedReturnRestore.value ||
        !props.restoreState ||
        !pendingReturnRestoreState.value
    ) {
        return;
    }

    hasAppliedReturnRestore.value = true;
    const snapshot = pendingReturnRestoreState.value;

    await new Promise((resolve) => window.setTimeout(resolve, 260));
    await waitForTableIdle();

    if (isInfiniteMode.value) {
        await restoreInfiniteRowsFromSnapshot(snapshot);
    } else {
        const targetPage = returnRestorePage(snapshot);
        if (
            props.managed &&
            targetPage > 1 &&
            currentPage.value !== targetPage
        ) {
            await fetchManaged({ append: false, pageOverride: targetPage });
            await waitForTableIdle();
        } else if (!props.managed && targetPage > 1) {
            currentPage.value = targetPage;
            await nextTick();
        }
    }

    await nextTick();

    let targetRowId = snapshot?.activeRowId ?? null;
    if (targetRowId !== null && targetRowId !== undefined) {
        setActiveRowById(targetRowId);
    } else if (Number.isFinite(Number(snapshot?.anchorRowIndexInPage))) {
        const fallbackRow = (renderedRows.value ?? [])[
            Number(snapshot.anchorRowIndexInPage)
        ];
        if (fallbackRow) {
            targetRowId = fallbackRow?.[props.idKey] ?? null;
            setActiveRowById(targetRowId);
        }
    } else if (Number.isFinite(Number(snapshot?.activeRowIndex))) {
        setActiveRowByIndex(Number(snapshot.activeRowIndex));
        targetRowId = currentActiveRowId();
    }

    await nextTick();

    const anchorOffset = Number(
        snapshot?.anchorOffsetFromViewportTop ?? Number.NaN,
    );
    if (
        containerRef.value &&
        targetRowId !== null &&
        targetRowId !== undefined
    ) {
        const rowEl = containerRef.value.querySelector(
            `tr[data-row-id="${targetRowId}"]`,
        );
        if (rowEl && Number.isFinite(anchorOffset)) {
            containerRef.value.scrollTop = Math.max(
                0,
                rowEl.offsetTop - anchorOffset,
            );
        } else if (rowEl) {
            rowEl.scrollIntoView({ block: 'nearest' });
        }
    } else {
        const savedScrollTop = Number(snapshot?.scrollTop ?? -1);
        if (
            containerRef.value &&
            Number.isFinite(savedScrollTop) &&
            savedScrollTop >= 0
        ) {
            containerRef.value.scrollTop = savedScrollTop;
        }
    }

    pendingReturnRestoreAnchor.value = null;
    persistReturnRestoreState();
}

function onContainerScroll() {
    persistReturnRestoreStateDebounced();
}

function readStoredColumnConfig() {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const raw = window.localStorage.getItem(storageKey());

        if (!raw) {
            return null;
        }

        const parsed = JSON.parse(raw);

        if (!parsed || typeof parsed !== 'object') {
            return null;
        }

        return parsed;
    } catch {
        return null;
    }
}

function readStoredColumnWidths() {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const raw = window.localStorage.getItem(columnWidthsStorageKey());

        if (!raw) {
            return null;
        }

        const parsed = JSON.parse(raw);

        if (!parsed || typeof parsed !== 'object') {
            return null;
        }

        return parsed;
    } catch {
        return null;
    }
}

function readStoredLockCount() {
    if (typeof window === 'undefined') {
        return 0;
    }

    try {
        const raw = window.localStorage.getItem(lockCountStorageKey());
        const parsed = Number(raw);

        if (!Number.isFinite(parsed)) {
            return 0;
        }

        return Math.max(0, Math.round(parsed));
    } catch {
        return 0;
    }
}

function readStoredConfig() {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const raw = window.localStorage.getItem(configStorageKey());

        if (!raw) {
            return null;
        }

        const parsed = JSON.parse(raw);

        if (!parsed || typeof parsed !== 'object') {
            return null;
        }

        return parsed;
    } catch {
        return null;
    }
}

function initializeStoredConfig() {
    const stored = readStoredConfig();

    if (!stored) {
        localHeight.value = normalizeHeightValue(localHeight.value);
        return;
    }

    if (typeof stored.usePagination === 'boolean') {
        localUsePagination.value = stored.usePagination;
    }

    if (typeof stored.horizontalScroll === 'boolean') {
        localHorizontalScroll.value = stored.horizontalScroll;
    }

    if (typeof stored.rowQuantitySelect === 'boolean') {
        localRowQuantitySelect.value = stored.rowQuantitySelect;
    }

    if (stored.rowsPerPage !== undefined && stored.rowsPerPage !== null) {
        rowsPerPage.value = normalizeRowsPerPageValue(stored.rowsPerPage);
    }

    if (typeof stored.showRecordCount === 'boolean') {
        localShowRecordCount.value = stored.showRecordCount;
    }

    localHeight.value = normalizeHeightValue(
        stored.height ?? localHeight.value,
    );
}

function persistColumnConfig() {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        const payload = {
            order: columnConfig.value.map((column) => column.key),
            selected: columnConfig.value.reduce((carry, column) => {
                carry[column.key] = column.selected !== false;
                return carry;
            }, {}),
        };

        window.localStorage.setItem(storageKey(), JSON.stringify(payload));
    } catch {
        return;
    }
}

function persistColumnWidths() {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(
            columnWidthsStorageKey(),
            JSON.stringify(columnWidths.value),
        );
    } catch {
        return;
    }
}

function persistLockCount() {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(
            lockCountStorageKey(),
            String(lockCount.value),
        );
    } catch {
        return;
    }
}

function persistStoredConfig() {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        const payload = {
            usePagination: localUsePagination.value,
            horizontalScroll: localHorizontalScroll.value,
            rowQuantitySelect: localRowQuantitySelect.value,
            rowsPerPage: normalizeRowsPerPageValue(rowsPerPage.value),
            showRecordCount: localShowRecordCount.value,
            height: normalizeHeightValue(localHeight.value),
        };

        window.localStorage.setItem(
            configStorageKey(),
            JSON.stringify(payload),
        );
    } catch {
        return;
    }
}

function restoreTableConfig() {
    localUsePagination.value = props.options?.scrollMode !== 'infinite';
    localHorizontalScroll.value = Boolean(props.horizontalScroll);
    localRowQuantitySelect.value = Boolean(props.rowQuantitySelect);
    rowsPerPage.value = normalizeRowsPerPageValue(props.rowOptions?.[0] ?? 25);
    localShowRecordCount.value = Boolean(props.showRecordCount);
    localHeight.value = normalizeHeightValue(props.initialHeight);

    if (typeof window !== 'undefined') {
        try {
            window.localStorage.removeItem(configStorageKey());
        } catch {
            return;
        }
    }
}
</script>

<template>
    <div ref="rootRef" class="rw-table w-full min-w-0 max-w-full space-y-0">
        <div
            class="relative flex flex-wrap items-center gap-2 rounded-none border-x border-t border-slate-200 bg-white px-3 py-2"
            :style="toolbarStyle"
        >
            <div
                v-if="globalSearch"
                :class="searchFieldContainerClass"
                :style="searchFieldContainerStyle"
            >
                <label :for="globalSearchInputId" class="sr-only">
                    {{ t('search.all_columns', 'Zoeken in alle kolommen') }}
                </label>
                <input
                    :id="globalSearchInputId"
                    :name="globalSearchInputId"
                    :aria-label="
                        t('search.all_columns', 'Zoeken in alle kolommen')
                    "
                    v-model="global"
                    type="text"
                    :placeholder="
                        t('search.all_columns', 'Zoeken in alle kolommen')
                    "
                    class="h-9 w-full rounded-md border border-slate-300 px-3 text-sm outline-none focus:border-slate-300"
                />
            </div>

            <button
                v-if="hasActiveFilters"
                type="button"
                class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-red-200 text-red-600 hover:bg-red-50"
                :aria-label="t('filters.clear_all', 'Filters wissen')"
                @click="resetAllFilters"
            >
                <i class="mdi mdi-filter-off text-base leading-none" />
            </button>

            <div class="ml-auto flex items-center gap-2">
                <div
                    v-if="localRowQuantitySelect && localUsePagination"
                    class="flex items-center gap-2"
                >
                    <select
                        :id="rowsPerPageSelectId"
                        :name="rowsPerPageSelectId"
                        :aria-label="
                            t('table.rows_per_page', 'Aantal rijen per pagina')
                        "
                        v-model.number="rowsPerPage"
                        class="h-9 w-28 rounded-md border border-slate-300 bg-white pl-3 pr-8 text-sm"
                    >
                        <option
                            v-for="option in rowOptions"
                            :key="option"
                            :value="option"
                        >
                            {{ option }}
                        </option>
                    </select>
                </div>

                <button
                    v-if="inlineCreate && inlineCreateButton"
                    type="button"
                    class="rw-btn-hover inline-flex h-9 w-9 items-center justify-center rounded-md border border-input bg-background text-slate-600 shadow-sm"
                    @click="openCreateRow(null)"
                >
                    <i class="mdi mdi-plus text-lg leading-none" />
                </button>

                <div v-if="chartEnabled" class="relative">
                    <button
                        type="button"
                        class="rw-btn-hover inline-flex h-9 w-9 items-center justify-center rounded-md border border-input bg-background shadow-sm"
                        :class="
                            showChartDialog
                                ? 'border-blue-300 bg-blue-50 text-blue-600'
                                : 'text-slate-600'
                        "
                        :title="t('charts.manage_title', 'Grafiekbeheer')"
                        @click="toggleChartDialog"
                    >
                        <i
                            class="mdi mdi-chart-bar text-lg leading-none"
                            :style="{
                                color: showChartDialog ? '#2563eb' : '#475569',
                            }"
                        />
                    </button>

                    <div
                        v-if="showChartDialog"
                        class="absolute right-0 top-11 mt-3 flex flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl"
                        :style="{
                            zIndex: Z_LAYERS.toolbarOverlay + 30,
                            width: 'min(96vw, 960px)',
                            maxWidth: 'calc(100vw - 1rem)',
                            maxHeight: 'calc(100vh - 120px)',
                        }"
                    >
                        <div
                            class="flex min-h-11 items-center justify-between bg-sky-700 px-4 text-sm font-semibold text-white"
                        >
                            <span>
                                {{
                                    chartViewMode === 'list'
                                        ? t(
                                              'charts.dialog.title_list',
                                              'Grafieken Beheer',
                                          )
                                        : chartViewMode === 'view'
                                          ? currentChart.description ||
                                            t(
                                                'charts.dialog.title_view',
                                                'Grafiek bekijken',
                                            )
                                          : currentChart.id
                                            ? t(
                                                  'charts.dialog.title_edit',
                                                  'Grafiek Bewerken',
                                              )
                                            : t(
                                                  'charts.dialog.title_new',
                                                  'Nieuwe Grafiek',
                                              )
                                }}
                            </span>
                            <button
                                type="button"
                                class="inline-flex h-7 w-7 items-center justify-center rounded text-sky-100 hover:bg-sky-600"
                                :aria-label="t('actions.close', 'Sluiten')"
                                @click="showChartDialog = false"
                            >
                                <i
                                    class="mdi mdi-close text-base leading-none"
                                />
                            </button>
                        </div>

                        <div
                            class="flex items-center gap-2 border-b border-slate-200 bg-white px-3 pb-2 pt-2"
                        >
                            <template v-if="chartViewMode === 'list'">
                                <button
                                    type="button"
                                    class="rw-btn-hover inline-flex h-8 items-center rounded border border-input bg-background px-2 text-sm text-slate-700 shadow-sm"
                                    @click="showChartDialog = false"
                                >
                                    <i
                                        class="mdi mdi-arrow-left-circle mr-1 text-base leading-none"
                                    />
                                    {{ t('actions.back', 'Terug') }}
                                </button>
                                <div class="ml-auto" />
                                <button
                                    type="button"
                                    class="rw-btn-hover-tone-blue inline-flex h-8 items-center rounded border border-blue-200 bg-blue-50 px-2 text-sm text-blue-700"
                                    :disabled="chartLoading"
                                    @click="createNewChart"
                                >
                                    <i
                                        class="mdi mdi-plus-circle mr-1 text-base leading-none"
                                    />
                                    {{ t('actions.new', 'Nieuw') }}
                                </button>
                            </template>

                            <template v-else>
                                <button
                                    type="button"
                                    class="rw-btn-hover inline-flex h-8 items-center rounded border border-input bg-background px-2 text-sm text-slate-700 shadow-sm"
                                    :disabled="chartLoading"
                                    @click="chartViewMode = 'list'"
                                >
                                    <i
                                        class="mdi mdi-arrow-left-circle mr-1 text-base leading-none"
                                    />
                                    {{ t('actions.back', 'Terug') }}
                                </button>
                                <div class="ml-auto" />
                                <button
                                    v-if="chartViewMode === 'view'"
                                    type="button"
                                    class="rw-btn-hover inline-flex h-8 items-center rounded border border-input bg-background px-2 text-sm text-slate-700 shadow-sm disabled:opacity-60"
                                    :disabled="
                                        chartPdfLoading ||
                                        chartPreviewLoading ||
                                        !chartHasRenderableData ||
                                        !!chartPreviewError
                                    "
                                    @click="downloadChartPdf"
                                >
                                    <i
                                        class="mdi mdi-file-pdf-box mr-1 text-base leading-none"
                                    />
                                    {{
                                        t(
                                            'charts.actions.print_pdf',
                                            'PDF afdrukken',
                                        )
                                    }}
                                </button>
                                <button
                                    v-if="chartViewMode === 'edit'"
                                    type="button"
                                    class="rw-btn-hover-tone-teal inline-flex h-8 items-center rounded border border-teal-200 bg-teal-50 px-2 text-sm text-teal-700 disabled:opacity-60"
                                    :disabled="chartLoading || !isChartValid"
                                    @click="saveChart"
                                >
                                    <i
                                        class="mdi mdi-content-save mr-1 text-base leading-none"
                                    />
                                    {{ t('actions.save', 'Bewaren') }}
                                </button>
                            </template>
                        </div>

                        <p
                            v-if="chartSuccessMessage"
                            class="border-b border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800"
                        >
                            {{ chartSuccessMessage }}
                        </p>

                        <p
                            v-if="chartNoticeMessage"
                            class="border-b border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800"
                        >
                            {{ chartNoticeMessage }}
                        </p>

                        <div
                            v-if="chartViewMode === 'list'"
                            class="min-h-0 flex-1 overflow-auto"
                            :style="{ maxHeight: 'calc(100vh - 240px)' }"
                        >
                            <table class="w-full border-collapse text-sm">
                                <thead class="bg-white text-slate-600">
                                    <tr>
                                        <th
                                            class="w-16 border-b border-r border-slate-200 px-3 py-2 text-left font-medium"
                                        >
                                            {{ t('table.id', 'Id') }}
                                        </th>
                                        <th
                                            class="border-b border-r border-slate-200 px-3 py-2 text-left font-medium"
                                        >
                                            {{
                                                t(
                                                    'table.description',
                                                    'Omschrijving',
                                                )
                                            }}
                                        </th>
                                        <th
                                            class="w-28 border-b border-slate-200 px-3 py-2 text-right font-medium"
                                        >
                                            {{ t('table.actions', 'Acties') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="savedCharts.length === 0">
                                        <td
                                            colspan="3"
                                            class="px-3 py-8 text-center text-slate-500"
                                        >
                                            {{
                                                t(
                                                    'charts.messages.none_saved',
                                                    'Geen opgeslagen grafieken gevonden.',
                                                )
                                            }}
                                        </td>
                                    </tr>

                                    <tr
                                        v-for="item in savedCharts"
                                        :key="item.id"
                                        class="border-b border-slate-100"
                                    >
                                        <td
                                            class="border-r border-slate-100 px-3 py-2"
                                        >
                                            <button
                                                type="button"
                                                class="rw-id-hover inline-flex h-7 items-center justify-center rounded-full px-2 font-semibold transition-colors"
                                                :disabled="chartLoading"
                                                @click="editChart(item)"
                                            >
                                                {{ item.id }}
                                            </button>
                                        </td>
                                        <td
                                            class="border-r border-slate-100 px-3 py-2"
                                        >
                                            {{ item.description }}
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <button
                                                type="button"
                                                class="rw-btn-hover inline-flex h-8 w-8 items-center justify-center rounded border border-input bg-background text-blue-700 shadow-sm disabled:opacity-50"
                                                :disabled="chartLoading"
                                                :title="
                                                    t(
                                                        'actions.view',
                                                        'Bekijken',
                                                    )
                                                "
                                                @click="viewChart(item)"
                                            >
                                                <i
                                                    class="mdi mdi-eye text-lg leading-none"
                                                />
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div
                            v-else
                            class="min-h-0 flex-1 overflow-auto p-4"
                            :style="{ maxHeight: 'calc(100vh - 240px)' }"
                        >
                            <div class="space-y-4">
                                <div
                                    v-if="chartViewMode === 'edit'"
                                    class="space-y-4"
                                >
                                    <div>
                                        <label
                                            :for="`${tableDomIdBase}-chart-description`"
                                            class="mb-1 block text-xs text-slate-600"
                                        >
                                            {{
                                                t(
                                                    'common.description_title',
                                                    'Omschrijving / Titel',
                                                )
                                            }}
                                        </label>
                                        <input
                                            :id="`${tableDomIdBase}-chart-description`"
                                            :name="`${tableDomIdBase}-chart-description`"
                                            v-model="currentChart.description"
                                            type="text"
                                            class="h-9 w-full rounded border border-slate-300 px-3 text-sm"
                                            maxlength="255"
                                            :placeholder="
                                                t(
                                                    'charts.placeholders.description',
                                                    'Bijvoorbeeld: Inschrijvingen per schooljaar',
                                                )
                                            "
                                        />
                                    </div>

                                    <div class="grid gap-3 md:grid-cols-3">
                                        <div>
                                            <label
                                                :for="`${tableDomIdBase}-chart-type`"
                                                class="mb-1 block text-xs text-slate-600"
                                            >
                                                {{
                                                    t(
                                                        'charts.fields.type',
                                                        'Grafiektype',
                                                    )
                                                }}
                                            </label>
                                            <select
                                                :id="`${tableDomIdBase}-chart-type`"
                                                :name="`${tableDomIdBase}-chart-type`"
                                                v-model="
                                                    currentChart.config.builder
                                                        .chart.type
                                                "
                                                class="h-9 w-full rounded border border-slate-300 bg-white px-2 text-sm"
                                            >
                                                <option
                                                    v-for="item in CHART_TYPE_ITEMS"
                                                    :key="item.value"
                                                    :value="item.value"
                                                >
                                                    {{ item.label }}
                                                </option>
                                            </select>
                                        </div>

                                        <div>
                                            <label
                                                :for="`${tableDomIdBase}-chart-sort`"
                                                class="mb-1 block text-xs text-slate-600"
                                            >
                                                {{
                                                    t(
                                                        'charts.fields.sort',
                                                        'Sortering',
                                                    )
                                                }}
                                            </label>
                                            <select
                                                :id="`${tableDomIdBase}-chart-sort`"
                                                :name="`${tableDomIdBase}-chart-sort`"
                                                v-model="
                                                    currentChart.config.builder
                                                        .dataset.sort_direction
                                                "
                                                class="h-9 w-full rounded border border-slate-300 bg-white px-2 text-sm"
                                            >
                                                <option
                                                    v-for="item in CHART_SORT_DIRECTION_ITEMS"
                                                    :key="item.value"
                                                    :value="item.value"
                                                >
                                                    {{ item.label }}
                                                </option>
                                            </select>
                                        </div>

                                        <div>
                                            <label
                                                :for="`${tableDomIdBase}-chart-orientation`"
                                                class="mb-1 block text-xs text-slate-600"
                                            >
                                                {{
                                                    t(
                                                        'charts.fields.orientation',
                                                        'Orientatie',
                                                    )
                                                }}
                                            </label>
                                            <select
                                                :id="`${tableDomIdBase}-chart-orientation`"
                                                :name="`${tableDomIdBase}-chart-orientation`"
                                                v-model="
                                                    currentChart.config.builder
                                                        .chart.orientation
                                                "
                                                class="h-9 w-full rounded border border-slate-300 bg-white px-2 text-sm"
                                            >
                                                <option
                                                    v-for="item in CHART_ORIENTATION_ITEMS"
                                                    :key="item.value"
                                                    :value="item.value"
                                                >
                                                    {{ item.label }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="grid gap-3 md:grid-cols-2">
                                        <div>
                                            <label
                                                :for="`${tableDomIdBase}-chart-x-field`"
                                                class="mb-1 block text-xs text-slate-600"
                                            >
                                                {{
                                                    t(
                                                        'charts.fields.x_field',
                                                        'X-veld',
                                                    )
                                                }}
                                            </label>
                                            <select
                                                :id="`${tableDomIdBase}-chart-x-field`"
                                                :name="`${tableDomIdBase}-chart-x-field`"
                                                v-model="
                                                    currentChart.config.builder
                                                        .dataset.x_field
                                                "
                                                class="h-9 w-full rounded border border-slate-300 bg-white px-2 text-sm"
                                            >
                                                <option value="">
                                                    {{
                                                        t(
                                                            'common.choose_field',
                                                            '-- Kies veld --',
                                                        )
                                                    }}
                                                </option>
                                                <option
                                                    v-for="item in chartFieldItems"
                                                    :key="item.value"
                                                    :value="item.value"
                                                >
                                                    {{ item.label }}
                                                </option>
                                            </select>
                                        </div>

                                        <div>
                                            <label
                                                :for="`${tableDomIdBase}-chart-aggregate`"
                                                class="mb-1 block text-xs text-slate-600"
                                            >
                                                {{
                                                    t(
                                                        'charts.fields.aggregate',
                                                        'Berekening (aggregatie)',
                                                    )
                                                }}
                                            </label>
                                            <select
                                                :id="`${tableDomIdBase}-chart-aggregate`"
                                                :name="`${tableDomIdBase}-chart-aggregate`"
                                                v-model="
                                                    currentChart.config.builder
                                                        .dataset.aggregate
                                                "
                                                class="h-9 w-full rounded border border-slate-300 bg-white px-2 text-sm"
                                            >
                                                <option
                                                    v-for="item in CHART_AGGREGATE_ITEMS"
                                                    :key="item.value"
                                                    :value="item.value"
                                                >
                                                    {{ item.label }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="grid gap-3 md:grid-cols-3">
                                        <div>
                                            <label
                                                :for="`${tableDomIdBase}-chart-metric-field`"
                                                class="mb-1 block text-xs text-slate-600"
                                            >
                                                {{
                                                    t(
                                                        'charts.fields.metric_field',
                                                        'Metric veld',
                                                    )
                                                }}
                                            </label>
                                            <select
                                                :id="`${tableDomIdBase}-chart-metric-field`"
                                                :name="`${tableDomIdBase}-chart-metric-field`"
                                                v-model="
                                                    currentChart.config.builder
                                                        .dataset.metric_field
                                                "
                                                :disabled="
                                                    !chartRequiresMetricField
                                                "
                                                class="h-9 w-full rounded border border-slate-300 bg-white px-2 text-sm disabled:bg-slate-100"
                                            >
                                                <option value="">
                                                    {{
                                                        t(
                                                            'common.choose_field',
                                                            '-- Kies veld --',
                                                        )
                                                    }}
                                                </option>
                                                <option
                                                    v-for="item in chartFieldItems"
                                                    :key="item.value"
                                                    :value="item.value"
                                                >
                                                    {{ item.label }}
                                                </option>
                                            </select>
                                        </div>

                                        <div>
                                            <label
                                                :for="`${tableDomIdBase}-chart-series-field`"
                                                class="mb-1 block text-xs text-slate-600"
                                            >
                                                {{
                                                    t(
                                                        'charts.fields.series_field_optional',
                                                        'Series veld (optioneel)',
                                                    )
                                                }}
                                            </label>
                                            <select
                                                :id="`${tableDomIdBase}-chart-series-field`"
                                                :name="`${tableDomIdBase}-chart-series-field`"
                                                v-model="
                                                    currentChart.config.builder
                                                        .dataset.series_field
                                                "
                                                class="h-9 w-full rounded border border-slate-300 bg-white px-2 text-sm"
                                            >
                                                <option value="">
                                                    {{
                                                        t(
                                                            'charts.fields.no_series',
                                                            '-- Geen series --',
                                                        )
                                                    }}
                                                </option>
                                                <option
                                                    v-for="item in chartFieldItems"
                                                    :key="item.value"
                                                    :value="item.value"
                                                >
                                                    {{ item.label }}
                                                </option>
                                            </select>
                                        </div>

                                        <div>
                                            <label
                                                :for="`${tableDomIdBase}-chart-limit`"
                                                class="mb-1 block text-xs text-slate-600"
                                            >
                                                {{
                                                    t(
                                                        'charts.fields.limit',
                                                        'Top N (1-500)',
                                                    )
                                                }}
                                            </label>
                                            <input
                                                :id="`${tableDomIdBase}-chart-limit`"
                                                :name="`${tableDomIdBase}-chart-limit`"
                                                v-model.number="
                                                    currentChart.config.builder
                                                        .dataset.limit
                                                "
                                                type="number"
                                                min="1"
                                                max="500"
                                                class="h-9 w-full rounded border border-slate-300 px-2 text-sm"
                                            />
                                        </div>
                                    </div>

                                    <div class="grid gap-2 md:grid-cols-2">
                                        <label
                                            class="inline-flex items-center gap-2 text-sm text-slate-700"
                                        >
                                            <input
                                                type="checkbox"
                                                :name="`${tableDomIdBase}-chart-show-legend`"
                                                v-model="
                                                    currentChart.config.builder
                                                        .chart.show_legend
                                                "
                                            />
                                            {{
                                                t(
                                                    'charts.fields.show_legend',
                                                    'Toon legenda',
                                                )
                                            }}
                                        </label>
                                        <label
                                            class="inline-flex items-center gap-2 text-sm text-slate-700"
                                        >
                                            <input
                                                type="checkbox"
                                                :name="`${tableDomIdBase}-chart-stacked`"
                                                v-model="
                                                    currentChart.config.builder
                                                        .chart.stacked
                                                "
                                            />
                                            {{
                                                t(
                                                    'charts.fields.stacked',
                                                    'Stapelen',
                                                )
                                            }}
                                        </label>
                                        <label
                                            class="inline-flex items-center gap-2 text-sm text-slate-700"
                                        >
                                            <input
                                                type="checkbox"
                                                :name="`${tableDomIdBase}-chart-allow-type-change`"
                                                v-model="
                                                    currentChart.config.builder
                                                        .presentation
                                                        .allow_chart_type_change
                                                "
                                            />
                                            {{
                                                t(
                                                    'charts.fields.allow_type_change',
                                                    'Grafiektype aanpassen in viewer',
                                                )
                                            }}
                                        </label>
                                    </div>

                                    <p
                                        v-if="!isChartValid"
                                        class="rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800"
                                    >
                                        {{
                                            t(
                                                'charts.validation.minimum_required',
                                                'Vul minstens Omschrijving, X-veld en indien nodig een Metric veld in.',
                                            )
                                        }}
                                    </p>
                                </div>

                                <div
                                    v-if="chartViewMode === 'view'"
                                    class="max-w-xs"
                                >
                                    <label
                                        :for="`${tableDomIdBase}-chart-view-type`"
                                        class="mb-1 block text-xs text-slate-600"
                                    >
                                        {{
                                            t(
                                                'charts.fields.viewer_type',
                                                'Grafiektype in viewer',
                                            )
                                        }}
                                    </label>
                                    <select
                                        :id="`${tableDomIdBase}-chart-view-type`"
                                        :name="`${tableDomIdBase}-chart-view-type`"
                                        v-model="selectedViewerChartType"
                                        class="h-9 w-full rounded border border-slate-300 bg-white px-2 text-sm"
                                    >
                                        <option
                                            v-for="item in CHART_TYPE_ITEMS"
                                            :key="item.value"
                                            :value="item.value"
                                        >
                                            {{ item.label }}
                                        </option>
                                    </select>
                                </div>

                                <p
                                    v-if="chartPreviewError"
                                    class="rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800"
                                >
                                    {{ chartPreviewError }}
                                </p>

                                <div
                                    v-if="chartPreviewLoading"
                                    class="rounded border border-slate-200 bg-slate-50 px-3 py-4 text-sm text-slate-600"
                                >
                                    {{
                                        t(
                                            'charts.messages.loading_data',
                                            'Grafiekdata laden...',
                                        )
                                    }}
                                </div>

                                <div
                                    v-else-if="!chartHasRenderableData"
                                    class="rounded border border-slate-200 bg-slate-50 px-3 py-4 text-sm text-slate-600"
                                >
                                    {{
                                        t(
                                            'charts.messages.no_renderable_data',
                                            'Geen renderbare grafiekdata gevonden voor de huidige instellingen.',
                                        )
                                    }}
                                </div>

                                <div
                                    v-else
                                    class="rounded border border-slate-200 bg-white p-2"
                                >
                                    <div
                                        v-if="chartViewMode === 'view'"
                                        ref="chartStageViewRef"
                                        class="w-full"
                                        :style="{
                                            height: '52vh',
                                            minHeight: '360px',
                                        }"
                                    />
                                    <div
                                        v-else
                                        ref="chartStageEditRef"
                                        class="w-full"
                                        :style="{
                                            height: '42vh',
                                            minHeight: '300px',
                                        }"
                                    />
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="chartViewMode === 'edit'"
                            class="flex items-center border-t border-slate-200 bg-white px-3 py-2"
                        >
                            <button
                                v-if="currentChart.id"
                                type="button"
                                class="inline-flex h-8 items-center rounded border border-red-200 bg-red-50 px-2 text-sm text-red-700 hover:bg-red-100 disabled:opacity-50"
                                :disabled="chartLoading"
                                @click="deleteChart(currentChart.id)"
                            >
                                <i
                                    class="mdi mdi-delete mr-1 text-base leading-none"
                                />
                                {{ t('actions.delete', 'Verwijderen') }}
                            </button>
                            <div class="ml-auto" />
                        </div>
                    </div>
                </div>

                <div v-if="excelEnabled" class="relative">
                    <button
                        type="button"
                        class="rw-btn-hover inline-flex h-9 w-9 items-center justify-center rounded-md border border-input bg-background shadow-sm"
                        :class="
                            showExcelDialog
                                ? 'border-emerald-300 bg-emerald-50 text-emerald-600'
                                : 'text-slate-600'
                        "
                        :title="t('excel.dialog.title_button', 'Excel export')"
                        @click="toggleExcelDialog"
                    >
                        <i
                            class="mdi mdi-microsoft-excel text-lg leading-none"
                            :style="{
                                color: showExcelDialog ? '#047857' : '#475569',
                            }"
                        />
                    </button>

                    <div
                        v-if="showExcelDialog"
                        class="absolute right-0 top-11 z-[80] mt-3 flex flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl"
                        :style="{
                            zIndex: Z_LAYERS.toolbarOverlay + 20,
                            width: 'min(90vw, 375px)',
                            maxWidth: 'calc(100vw - 2rem)',
                            minWidth: '320px',
                        }"
                    >
                        <div
                            class="flex min-h-11 items-center justify-between bg-emerald-700 px-4 text-sm font-semibold text-white"
                        >
                            <span>
                                {{
                                    excelViewMode === 'list'
                                        ? t(
                                              'excel.dialog.title_list',
                                              'Excel Export Beheer',
                                          )
                                        : currentExport.id
                                          ? t(
                                                'excel.dialog.title_edit',
                                                'Export Bewerken',
                                            )
                                          : t(
                                                'excel.dialog.title_new',
                                                'Nieuwe Export',
                                            )
                                }}
                            </span>
                            <button
                                type="button"
                                class="inline-flex h-7 w-7 items-center justify-center rounded text-emerald-100 hover:bg-emerald-600"
                                :aria-label="t('actions.close', 'Sluiten')"
                                @click="showExcelDialog = false"
                            >
                                <i
                                    class="mdi mdi-close text-base leading-none"
                                />
                            </button>
                        </div>

                        <div
                            class="flex items-center gap-2 border-b border-slate-200 bg-white px-3 pb-2 pt-2"
                        >
                            <template v-if="excelViewMode === 'list'">
                                <button
                                    type="button"
                                    class="rw-btn-hover inline-flex h-8 items-center rounded border border-input bg-background px-2 text-sm text-slate-700 shadow-sm"
                                    @click="showExcelDialog = false"
                                >
                                    <i
                                        class="mdi mdi-arrow-left-circle mr-1 text-base leading-none"
                                    />
                                    {{ t('actions.back', 'Terug') }}
                                </button>
                                <div class="ml-auto" />
                                <button
                                    type="button"
                                    class="rw-btn-hover-tone-blue inline-flex h-8 items-center rounded border border-blue-200 bg-blue-50 px-2 text-sm text-blue-700"
                                    :disabled="exportLoading"
                                    @click="createNewExport"
                                >
                                    <i
                                        class="mdi mdi-plus-circle mr-1 text-base leading-none"
                                    />
                                    {{ t('actions.new', 'Nieuw') }}
                                </button>
                            </template>

                            <template v-else>
                                <button
                                    type="button"
                                    class="rw-btn-hover inline-flex h-8 items-center rounded border border-input bg-background px-2 text-sm text-slate-700 shadow-sm"
                                    :disabled="exportLoading"
                                    @click="excelViewMode = 'list'"
                                >
                                    <i
                                        class="mdi mdi-arrow-left-circle mr-1 text-base leading-none"
                                    />
                                    {{ t('actions.back', 'Terug') }}
                                </button>
                                <div class="ml-auto" />
                                <button
                                    v-if="!exportLoading && isExportValid"
                                    type="button"
                                    class="rw-btn-hover-tone-teal inline-flex h-8 items-center rounded border border-teal-200 bg-teal-50 px-2 text-sm text-teal-700"
                                    @click="saveExport"
                                >
                                    <i
                                        class="mdi mdi-content-save mr-1 text-base leading-none"
                                    />
                                    {{ t('actions.save', 'Bewaren') }}
                                </button>
                            </template>
                        </div>

                        <p
                            v-if="exportSuccessMessage"
                            class="border-b border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800"
                        >
                            {{ exportSuccessMessage }}
                        </p>

                        <p
                            v-if="exportNoticeMessage"
                            class="border-b border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800"
                        >
                            {{ exportNoticeMessage }}
                        </p>

                        <div
                            v-if="excelViewMode === 'list'"
                            class="max-h-[calc(100vh-220px)] min-h-0 flex-1 overflow-auto"
                        >
                            <table class="w-full border-collapse text-sm">
                                <thead class="bg-white text-slate-600">
                                    <tr>
                                        <th
                                            class="w-16 border-b border-r border-slate-200 px-3 py-2 text-left font-medium"
                                        >
                                            {{ t('table.id', 'Id') }}
                                        </th>
                                        <th
                                            class="border-b border-r border-slate-200 px-3 py-2 text-left font-medium"
                                        >
                                            {{
                                                t(
                                                    'table.description',
                                                    'Omschrijving',
                                                )
                                            }}
                                        </th>
                                        <th
                                            class="w-28 border-b border-slate-200 px-3 py-2 text-right font-medium"
                                        >
                                            {{ t('table.actions', 'Acties') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="savedExports.length === 0">
                                        <td
                                            colspan="3"
                                            class="px-3 py-8 text-center text-slate-500"
                                        >
                                            {{
                                                t(
                                                    'excel.messages.none_saved',
                                                    'Geen opgeslagen exports gevonden.',
                                                )
                                            }}
                                        </td>
                                    </tr>

                                    <tr
                                        v-for="item in savedExports"
                                        :key="item.id"
                                        class="border-b border-slate-100"
                                    >
                                        <td
                                            class="border-r border-slate-100 px-3 py-2"
                                        >
                                            <button
                                                type="button"
                                                class="rw-id-hover inline-flex h-7 items-center justify-center rounded-full px-2 font-semibold transition-colors"
                                                :disabled="exportLoading"
                                                @click="editExport(item)"
                                            >
                                                {{ item.id }}
                                            </button>
                                        </td>
                                        <td
                                            class="border-r border-slate-100 px-3 py-2"
                                        >
                                            {{ item.description }}
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <button
                                                type="button"
                                                class="rw-btn-hover inline-flex h-8 w-8 items-center justify-center rounded border border-input bg-background text-emerald-700 shadow-sm disabled:opacity-50"
                                                :disabled="exportLoading"
                                                :title="
                                                    t(
                                                        'excel.actions.download_direct',
                                                        'Direct downloaden',
                                                    )
                                                "
                                                @click="
                                                    quickDownloadExport(item)
                                                "
                                            >
                                                <i
                                                    class="mdi mdi-download text-lg leading-none"
                                                />
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div
                            v-else
                            class="max-h-[calc(100vh-220px)] min-h-0 flex-1 overflow-auto p-4"
                        >
                            <div class="space-y-4">
                                <div>
                                    <label
                                        :for="`${tableDomIdBase}-export-description`"
                                        class="mb-1 block text-xs text-slate-600"
                                    >
                                        {{
                                            t(
                                                'common.description_title',
                                                'Omschrijving / Titel',
                                            )
                                        }}
                                    </label>
                                    <input
                                        :id="`${tableDomIdBase}-export-description`"
                                        :name="`${tableDomIdBase}-export-description`"
                                        v-model="currentExport.description"
                                        type="text"
                                        class="h-9 w-full rounded border border-slate-300 px-3 text-sm"
                                        maxlength="255"
                                        :placeholder="
                                            t(
                                                'excel.placeholders.description',
                                                'Bijvoorbeeld: Overzicht actieve records',
                                            )
                                        "
                                    />
                                </div>

                                <div>
                                    <label
                                        :for="`${tableDomIdBase}-export-format`"
                                        class="mb-1 block text-xs text-slate-600"
                                    >
                                        {{
                                            t(
                                                'excel.fields.format',
                                                'Export formaat',
                                            )
                                        }}
                                    </label>
                                    <select
                                        :id="`${tableDomIdBase}-export-format`"
                                        v-model="currentExport.format"
                                        class="h-9 w-full rounded border border-slate-300 px-3 text-sm"
                                    >
                                        <option
                                            v-for="item in exportFormatItems"
                                            :key="item.value"
                                            :value="item.value"
                                        >
                                            {{ item.title }}
                                        </option>
                                    </select>
                                </div>

                                <div
                                    v-if="currentExport.format === 'csv'"
                                    class="grid gap-4 md:grid-cols-2"
                                >
                                    <div>
                                        <label
                                            :for="`${tableDomIdBase}-csv-delimiter`"
                                            class="mb-1 block text-xs text-slate-600"
                                        >
                                            {{
                                                t(
                                                    'excel.fields.csv_delimiter',
                                                    'Scheidingsteken',
                                                )
                                            }}
                                        </label>
                                        <select
                                            :id="`${tableDomIdBase}-csv-delimiter`"
                                            v-model="
                                                currentExport.csv.delimiter
                                            "
                                            class="h-9 w-full rounded border border-slate-300 px-3 text-sm"
                                        >
                                            <option
                                                v-for="item in csvDelimiterItems"
                                                :key="item.value"
                                                :value="item.value"
                                            >
                                                {{ item.title }}
                                            </option>
                                        </select>
                                    </div>

                                    <div>
                                        <label
                                            :for="`${tableDomIdBase}-csv-enclosure`"
                                            class="mb-1 block text-xs text-slate-600"
                                        >
                                            {{
                                                t(
                                                    'excel.fields.csv_enclosure',
                                                    'Tekstomsluiting',
                                                )
                                            }}
                                        </label>
                                        <select
                                            :id="`${tableDomIdBase}-csv-enclosure`"
                                            v-model="
                                                currentExport.csv.enclosure
                                            "
                                            class="h-9 w-full rounded border border-slate-300 px-3 text-sm"
                                        >
                                            <option
                                                v-for="item in csvEnclosureItems"
                                                :key="item.value"
                                                :value="item.value"
                                            >
                                                {{ item.title }}
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <p
                                        class="mb-2 text-sm font-medium text-slate-700"
                                    >
                                        {{
                                            t(
                                                'excel.fields.select_sort_columns',
                                                'Selecteer en sorteer kolommen',
                                            )
                                        }}
                                    </p>
                                    <div
                                        class="max-h-[420px] overflow-y-auto rounded border border-slate-200"
                                    >
                                        <div
                                            v-for="(
                                                column, index
                                            ) in currentExport.columns"
                                            :key="column.key"
                                            data-drag-preview-row="true"
                                            class="flex items-center gap-2 border-b border-slate-100 px-3 py-2 last:border-b-0"
                                            :class="
                                                dragOverExportColumnKey ===
                                                column.key
                                                    ? 'bg-blue-50'
                                                    : 'bg-white'
                                            "
                                            @dragover="
                                                onExportColumnDragOver(
                                                    column.key,
                                                    $event,
                                                )
                                            "
                                            @drop="
                                                onExportColumnDrop(
                                                    column.key,
                                                    $event,
                                                )
                                            "
                                        >
                                            <span
                                                class="inline-flex h-6 w-6 cursor-move items-center justify-center rounded text-slate-500 hover:bg-slate-100"
                                                draggable="true"
                                                :aria-label="`Versleep export kolom ${columnLabel(column)}`"
                                                @dragstart="
                                                    onExportColumnDragStart(
                                                        column.key,
                                                        $event,
                                                    )
                                                "
                                                @dragend="onExportColumnDragEnd"
                                            >
                                                <i
                                                    class="mdi mdi-drag text-sm leading-none"
                                                />
                                            </span>

                                            <input
                                                type="checkbox"
                                                :name="`export-column-${column.key}`"
                                                :aria-label="`Neem kolom ${columnLabel(column)} op in export`"
                                                v-model="column.selected"
                                            />

                                            <span
                                                class="min-w-0 flex-1 truncate text-sm"
                                            >
                                                {{ columnLabel(column) }}
                                            </span>

                                            <div
                                                class="flex items-center gap-1"
                                            >
                                                <button
                                                    type="button"
                                                    class="inline-flex h-6 w-6 items-center justify-center rounded border border-slate-200 text-slate-500 disabled:opacity-30"
                                                    :disabled="index === 0"
                                                    @click="
                                                        moveExportColumnToTarget(
                                                            column.key,
                                                            currentExport
                                                                .columns[
                                                                index - 1
                                                            ]?.key,
                                                        )
                                                    "
                                                >
                                                    <i
                                                        class="mdi mdi-chevron-up text-sm leading-none"
                                                    />
                                                </button>
                                                <button
                                                    type="button"
                                                    class="inline-flex h-6 w-6 items-center justify-center rounded border border-slate-200 text-slate-500 disabled:opacity-30"
                                                    :disabled="
                                                        index >=
                                                        currentExport.columns
                                                            .length -
                                                            1
                                                    "
                                                    @click="
                                                        moveExportColumnToTarget(
                                                            column.key,
                                                            currentExport
                                                                .columns[
                                                                index + 1
                                                            ]?.key,
                                                        )
                                                    "
                                                >
                                                    <i
                                                        class="mdi mdi-chevron-down text-sm leading-none"
                                                    />
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="excelViewMode === 'edit'"
                            class="flex items-center border-t border-slate-200 bg-white px-3 py-2"
                        >
                            <button
                                v-if="currentExport.id"
                                type="button"
                                class="inline-flex h-8 items-center rounded border border-red-200 bg-red-50 px-2 text-sm text-red-700 hover:bg-red-100 disabled:opacity-50"
                                :disabled="exportLoading"
                                @click="deleteExport(currentExport.id)"
                            >
                                <i
                                    class="mdi mdi-delete mr-1 text-base leading-none"
                                />
                                {{ t('actions.delete', 'Verwijderen') }}
                            </button>
                            <div class="ml-auto" />
                        </div>
                    </div>
                </div>

                <div v-if="normalizedMenuItems.length" class="relative">
                    <button
                        type="button"
                        class="rw-btn-hover inline-flex h-9 w-9 items-center justify-center rounded-md border border-input bg-background shadow-sm"
                        :class="
                            toolbarMenuOpen
                                ? 'border-red-300 bg-red-50 text-red-600'
                                : 'text-slate-600'
                        "
                        @click="toolbarMenuOpen = !toolbarMenuOpen"
                    >
                        <i
                            class="mdi mdi-dots-vertical text-lg leading-none"
                            :style="{
                                color: toolbarMenuOpen ? '#dc2626' : '#475569',
                            }"
                        />
                    </button>

                    <div
                        v-if="toolbarMenuOpen"
                        class="absolute right-0 top-11 mt-3 flex w-max max-w-[90vw] flex-col overflow-hidden rounded-md border border-slate-200 bg-white shadow-lg"
                        :style="toolbarOverlayStyle"
                    >
                        <div class="border-b border-slate-100 px-2 pb-2 pt-2">
                            <button
                                type="button"
                                class="rw-btn-hover inline-flex h-8 items-center rounded border border-input bg-background px-2 text-sm text-slate-700 shadow-sm"
                                @click="toolbarMenuOpen = false"
                            >
                                <i
                                    class="mdi mdi-arrow-left-circle mr-1 text-base leading-none"
                                />
                                {{ t('actions.back', 'Terug') }}
                            </button>
                        </div>

                        <div class="p-1">
                            <button
                                v-for="(item, idx) in normalizedMenuItems"
                                :key="idx"
                                type="button"
                                class="rw-btn-hover flex w-full items-center gap-2 whitespace-nowrap rounded px-2 py-1.5 text-left text-sm text-slate-700"
                                @click="onToolbarMenuClick(item)"
                            >
                                <i
                                    v-if="item.icon"
                                    class="mdi text-base leading-none"
                                    :class="item.icon"
                                />
                                {{ item.label }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="relative">
                    <button
                        type="button"
                        class="rw-btn-hover inline-flex h-9 w-9 items-center justify-center rounded-md border border-input bg-background shadow-sm"
                        :class="
                            columnMenuOpen
                                ? 'border-blue-300 bg-blue-50 text-blue-600'
                                : 'text-slate-600'
                        "
                        @click="columnMenuOpen = !columnMenuOpen"
                    >
                        <i
                            class="mdi mdi-cog text-lg leading-none"
                            :style="{
                                color: columnMenuOpen ? '#2563eb' : '#475569',
                            }"
                        />
                    </button>

                    <div
                        v-if="columnMenuOpen"
                        ref="columnMenuRef"
                        class="absolute right-2 top-full mt-3 flex w-72 flex-col overflow-hidden rounded-md border border-slate-200 bg-white p-2 shadow-lg"
                        :style="columnMenuOverlayStyle"
                    >
                        <div class="border-b border-slate-100 px-2 pb-2 pt-2">
                            <button
                                type="button"
                                class="rw-btn-hover inline-flex h-8 items-center rounded border border-input bg-background px-2 text-sm text-slate-700 shadow-sm"
                                @click="columnMenuOpen = false"
                            >
                                <i
                                    class="mdi mdi-arrow-left-circle mr-1 text-base leading-none"
                                />
                                {{ t('actions.back', 'Terug') }}
                            </button>
                        </div>

                        <div
                            class="min-h-0 flex-1 space-y-2 overflow-y-auto pr-1"
                        >
                            <button
                                type="button"
                                class="rw-btn-hover inline-flex w-full items-center justify-center rounded border border-input bg-background px-2 py-1.5 text-xs font-medium shadow-sm"
                                :class="
                                    configPanelOpen
                                        ? 'border-blue-200 bg-blue-50'
                                        : 'text-slate-700'
                                "
                                :style="{
                                    color: configPanelOpen
                                        ? '#1d4ed8'
                                        : '#334155',
                                }"
                                @click="configPanelOpen = !configPanelOpen"
                            >
                                {{ t('table.config.title', 'Configuratie') }}
                            </button>

                            <div
                                v-if="configPanelOpen"
                                class="mb-3 mt-2 space-y-3 rounded border border-slate-200 bg-slate-50 p-3"
                            >
                                <label
                                    class="rw-config-checkbox-row flex cursor-pointer items-center gap-3 rounded px-1 py-1 text-xs text-slate-700"
                                >
                                    <input
                                        type="checkbox"
                                        class="rw-config-checkbox-input h-4 w-4 shrink-0"
                                        :name="`${tableDomIdBase}-cfg-pagination`"
                                        :checked="localUsePagination"
                                        @change="
                                            localUsePagination =
                                                $event.target.checked
                                        "
                                    />
                                    {{
                                        t(
                                            'table.config.use_pagination',
                                            'Gebruik paginering i.p.v. infinite scroll',
                                        )
                                    }}
                                </label>

                                <label
                                    class="rw-config-checkbox-row flex cursor-pointer items-center gap-3 rounded px-1 py-1 text-xs text-slate-700"
                                >
                                    <input
                                        type="checkbox"
                                        class="rw-config-checkbox-input h-4 w-4 shrink-0"
                                        :name="`${tableDomIdBase}-cfg-hscroll`"
                                        :checked="localHorizontalScroll"
                                        @change="
                                            localHorizontalScroll =
                                                $event.target.checked
                                        "
                                    />
                                    {{
                                        t(
                                            'table.config.enable_horizontal_scroll',
                                            'Horizontale scroll inschakelen',
                                        )
                                    }}
                                </label>

                                <label
                                    class="rw-config-checkbox-row flex cursor-pointer items-center gap-3 rounded px-1 py-1 text-xs text-slate-700"
                                >
                                    <input
                                        type="checkbox"
                                        class="rw-config-checkbox-input h-4 w-4 shrink-0"
                                        :name="`${tableDomIdBase}-cfg-row-select`"
                                        :checked="localRowQuantitySelect"
                                        :disabled="!localUsePagination"
                                        @change="
                                            localRowQuantitySelect =
                                                $event.target.checked
                                        "
                                    />
                                    {{
                                        t(
                                            'table.config.show_row_quantity_select',
                                            'Toon aantal rijen-selectie',
                                        )
                                    }}
                                </label>

                                <label
                                    class="rw-config-checkbox-row flex cursor-pointer items-center gap-3 rounded px-1 py-1 text-xs text-slate-700"
                                >
                                    <input
                                        type="checkbox"
                                        class="rw-config-checkbox-input h-4 w-4 shrink-0"
                                        :name="`${tableDomIdBase}-cfg-record-count`"
                                        :checked="localShowRecordCount"
                                        @change="
                                            localShowRecordCount =
                                                $event.target.checked
                                        "
                                    />
                                    {{
                                        t(
                                            'table.config.show_record_count',
                                            'Toon aantal records',
                                        )
                                    }}
                                </label>

                                <div class="space-y-1">
                                    <label
                                        :for="`${tableDomIdBase}-cfg-height`"
                                        class="block text-xs text-slate-600"
                                    >
                                        {{
                                            t(
                                                'table.config.height',
                                                'Hoogte van de tabel',
                                            )
                                        }}
                                    </label>
                                    <input
                                        :id="`${tableDomIdBase}-cfg-height`"
                                        :name="`${tableDomIdBase}-cfg-height`"
                                        :aria-label="
                                            t(
                                                'table.config.height',
                                                'Hoogte van de tabel',
                                            )
                                        "
                                        :value="localHeight"
                                        class="h-8 w-full rounded border border-slate-300 bg-white px-2 text-xs"
                                        @input="
                                            localHeight = $event.target.value
                                        "
                                    />
                                </div>

                                <button
                                    type="button"
                                    class="rw-btn-hover inline-flex h-7 items-center rounded border border-input bg-background px-2 text-xs text-slate-700 shadow-sm"
                                    @click="restoreTableConfig"
                                >
                                    {{
                                        t(
                                            'table.config.restore_default',
                                            'Herstel standaard',
                                        )
                                    }}
                                </button>
                            </div>

                            <div class="space-y-1 pb-1 pt-1">
                                <div
                                    v-for="(column, index) in columnConfig"
                                    :key="column.key"
                                    data-drag-preview-row="true"
                                    class="flex items-center gap-2 rounded border px-2 py-1.5"
                                    :class="
                                        dragOverColumnKey === column.key
                                            ? 'border-blue-300 bg-blue-50/60'
                                            : 'border-slate-100'
                                    "
                                    @dragover="
                                        onColumnDragOver(column.key, $event)
                                    "
                                    @drop="onColumnDrop(column.key, $event)"
                                >
                                    <span
                                        class="inline-flex h-6 w-6 cursor-move items-center justify-center rounded text-slate-500 hover:bg-slate-100"
                                        draggable="true"
                                        :aria-label="
                                            t(
                                                'table.column.aria.drag_column',
                                                'Versleep kolom :label',
                                                { label: columnLabel(column) },
                                            )
                                        "
                                        @dragstart="
                                            onColumnDragStart(
                                                column.key,
                                                $event,
                                            )
                                        "
                                        @dragend="onColumnDragEnd"
                                    >
                                        <i
                                            class="mdi mdi-drag text-sm leading-none"
                                        />
                                    </span>

                                    <input
                                        type="checkbox"
                                        :name="`column-toggle-${column.key}`"
                                        :aria-label="
                                            t(
                                                'table.column.aria.toggle_column',
                                                'Toon kolom :label',
                                                { label: columnLabel(column) },
                                            )
                                        "
                                        :checked="column.selected !== false"
                                        @change="
                                            toggleColumnSelection(column.key)
                                        "
                                    />
                                    <span
                                        class="min-w-0 flex-1 truncate text-sm text-slate-700"
                                        >{{ columnLabel(column) }}</span
                                    >
                                    <div class="flex items-center gap-1">
                                        <button
                                            type="button"
                                            class="rw-btn-hover inline-flex h-6 w-6 items-center justify-center rounded border border-input bg-background text-slate-500 shadow-sm disabled:opacity-30"
                                            :disabled="index === 0"
                                            @click="moveColumn(column.key, -1)"
                                        >
                                            <i
                                                class="mdi mdi-chevron-up text-sm leading-none"
                                            />
                                        </button>
                                        <button
                                            type="button"
                                            class="rw-btn-hover inline-flex h-6 w-6 items-center justify-center rounded border border-input bg-background text-slate-500 shadow-sm disabled:opacity-30"
                                            :disabled="
                                                index ===
                                                columnConfig.length - 1
                                            "
                                            @click="moveColumn(column.key, 1)"
                                        >
                                            <i
                                                class="mdi mdi-chevron-down text-sm leading-none"
                                            />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            ref="containerRef"
            class="w-full min-w-0 max-w-full rounded-none border-x border-b border-t border-slate-200 bg-white"
            :class="
                localHorizontalScroll
                    ? 'overflow-x-auto overflow-y-auto'
                    : 'overflow-hidden'
            "
            :style="{ height: resolvedTableHeight }"
        >
            <table
                class="table-fixed border-collapse border-spacing-0 text-sm"
                :style="tableStyle"
            >
                <thead
                    class="sticky top-0 bg-slate-50 text-slate-600"
                    :style="stickyHeaderStyle"
                >
                    <tr>
                        <th
                            v-if="checkboxColumn"
                            class="w-10 border-b border-r border-slate-200 px-3 py-2 text-left"
                            :style="utilityColumnStyle('checkbox', true)"
                        >
                            <input
                                type="checkbox"
                                :name="`${tableDomIdBase}-select-all-visible`"
                                :aria-label="
                                    t(
                                        'table.aria.select_all_visible_rows',
                                        'Selecteer alle zichtbare rijen',
                                    )
                                "
                                :checked="hasAllVisibleRowsSelected"
                                @change="
                                    toggleAllVisibleRows($event.target.checked)
                                "
                            />
                        </th>

                        <th
                            v-if="showExpandColumn"
                            class="w-10 border-b border-r border-slate-200 px-3 py-2"
                            :style="expandColumnStyle(true)"
                        />

                        <th
                            v-for="(column, colIndex) in selectedColumns"
                            :key="column.key"
                            class="group relative whitespace-nowrap border-b border-slate-200 px-2 py-2 text-left font-medium"
                            :class="
                                colIndex < selectedColumns.length - 1
                                    ? 'border-r border-slate-200'
                                    : ''
                            "
                            :style="headerColumnStyle(column, colIndex)"
                        >
                            <div
                                class="relative flex w-full min-w-0 items-center gap-0.5 pr-1"
                            >
                                <button
                                    type="button"
                                    class="inline-flex min-w-0 flex-1 items-center gap-0.5"
                                    :title="
                                        props.manualOrdering
                                            ? t(
                                                  'table.manual_ordering_active',
                                                  'Manuele volgorde actief',
                                              )
                                            : undefined
                                    "
                                    :class="
                                        isHeaderSortEnabled(column)
                                            ? 'cursor-pointer'
                                            : 'cursor-default'
                                    "
                                    @click="
                                        isHeaderSortEnabled(column)
                                            ? onSort(column.key)
                                            : undefined
                                    "
                                >
                                    <span class="truncate">{{
                                        columnLabel(column)
                                    }}</span>
                                    <i
                                        v-if="isHeaderSortEnabled(column)"
                                        class="mdi shrink-0 text-base leading-none"
                                        :class="
                                            activeSortField === column.key
                                                ? activeSortOrder === 'asc'
                                                    ? 'mdi-arrow-up'
                                                    : 'mdi-arrow-down'
                                                : 'mdi-unfold-more-horizontal'
                                        "
                                    />
                                </button>

                                <div
                                    class="ml-auto inline-flex shrink-0 items-center gap-0"
                                >
                                    <button
                                        v-if="
                                            localHorizontalScroll &&
                                            lockIconName(colIndex)
                                        "
                                        type="button"
                                        class="rw-btn-hover inline-flex h-6 w-6 items-center justify-center rounded"
                                        :aria-label="
                                            t(
                                                'table.column.aria.pin_column',
                                                'Vastzetten kolom :label',
                                                { label: columnLabel(column) },
                                            )
                                        "
                                        @click.stop="toggleLock(colIndex)"
                                    >
                                        <i
                                            class="mdi text-sm leading-none"
                                            :class="lockIconName(colIndex)"
                                            :style="{
                                                color:
                                                    lockIconName(colIndex) ===
                                                    'mdi-lock'
                                                        ? '#2563eb'
                                                        : '#64748b',
                                            }"
                                        />
                                    </button>

                                    <button
                                        v-if="column.filterable"
                                        type="button"
                                        class="rw-btn-hover inline-flex h-6 w-6 items-center justify-center rounded"
                                        :class="
                                            isColumnFilterActive(column.key)
                                                ? 'text-green-600'
                                                : 'text-slate-500'
                                        "
                                        :aria-label="
                                            t(
                                                'filters.aria.filter_column',
                                                'Filter kolom :label',
                                                {
                                                    label: columnLabel(column),
                                                },
                                            )
                                        "
                                        @click="openFilter(column.key)"
                                    >
                                        <i
                                            class="mdi mdi-filter text-sm leading-none"
                                        />
                                    </button>
                                </div>
                            </div>

                            <div
                                v-if="openFilterKey === column.key"
                                class="absolute left-0 top-full mt-1 min-w-72 overflow-visible rounded-lg border border-slate-200 bg-white shadow-xl"
                                :style="headerFilterOverlayStyle"
                            >
                                <div
                                    class="flex items-center gap-2 border-b border-slate-200 bg-white px-2 pb-2 pt-2"
                                >
                                    <button
                                        type="button"
                                        class="rw-btn-hover inline-flex h-8 items-center rounded border border-input bg-background px-2 text-sm text-slate-700 shadow-sm"
                                        @click="openFilterKey = null"
                                    >
                                        <i
                                            class="mdi mdi-arrow-left-circle mr-1 text-base leading-none"
                                        />
                                        {{ t('actions.back', 'Terug') }}
                                    </button>
                                    <div class="ml-auto" />
                                    <button
                                        type="button"
                                        class="inline-flex h-8 items-center rounded border border-red-200 bg-red-50 px-2 text-sm text-red-700 hover:bg-red-100"
                                        @click="clearFilter(column.key)"
                                    >
                                        <i
                                            class="mdi mdi-delete mr-1 text-base leading-none"
                                        />
                                        {{ t('actions.clear', 'Wissen') }}
                                    </button>
                                </div>

                                <div class="grid gap-2 p-3">
                                    <select
                                        :name="`filter-mode-${column.key}`"
                                        :aria-label="
                                            t(
                                                'filters.aria.operator_for',
                                                'Filter operator voor :label',
                                                { label: columnLabel(column) },
                                            )
                                        "
                                        :value="
                                            resolveFilterModeForColumn(
                                                column.key,
                                            )
                                        "
                                        class="h-8 rounded-md border border-slate-300 bg-white px-2 text-xs"
                                        @change="
                                            updateFilterMode(
                                                column,
                                                $event.target.value,
                                            )
                                        "
                                    >
                                        <option
                                            v-for="option in filterModeOptionsForColumn(
                                                column,
                                            )"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>

                                    <template
                                        v-if="
                                            resolveFilterModeForColumn(
                                                column.key,
                                            ) === 'tussen'
                                        "
                                    >
                                        <label class="text-xs text-slate-500">{{
                                            t('filters.from', 'Van')
                                        }}</label>
                                        <input
                                            type="date"
                                            :name="`filter-from-${column.key}`"
                                            :aria-label="
                                                t(
                                                    'filters.aria.from_date_for',
                                                    'Filter vanaf datum voor :label',
                                                    {
                                                        label: columnLabel(
                                                            column,
                                                        ),
                                                    },
                                                )
                                            "
                                            :value="
                                                filterValues[column.key]
                                                    ?.from || ''
                                            "
                                            class="h-8 rounded-md border border-slate-300 px-2 text-xs"
                                            @input="
                                                setFilterRangeValue(
                                                    column.key,
                                                    'from',
                                                    $event.target.value,
                                                )
                                            "
                                        />

                                        <label class="text-xs text-slate-500">{{
                                            t('filters.to', 'Tot')
                                        }}</label>
                                        <input
                                            type="date"
                                            :name="`filter-to-${column.key}`"
                                            :aria-label="
                                                t(
                                                    'filters.aria.to_date_for',
                                                    'Filter tot datum voor :label',
                                                    {
                                                        label: columnLabel(
                                                            column,
                                                        ),
                                                    },
                                                )
                                            "
                                            :value="
                                                filterValues[column.key]?.to ||
                                                ''
                                            "
                                            class="h-8 rounded-md border border-slate-300 px-2 text-xs"
                                            @input="
                                                setFilterRangeValue(
                                                    column.key,
                                                    'to',
                                                    $event.target.value,
                                                )
                                            "
                                        />
                                    </template>

                                    <template
                                        v-else-if="
                                            filterTypes[column.key] ===
                                            'boolean'
                                        "
                                    >
                                        <label class="text-xs text-slate-500">{{
                                            t('filters.value', 'Waarde')
                                        }}</label>
                                        <select
                                            :name="`filter-value-${column.key}`"
                                            :aria-label="
                                                t(
                                                    'filters.aria.value_for',
                                                    'Filter waarde voor :label',
                                                    {
                                                        label: columnLabel(
                                                            column,
                                                        ),
                                                    },
                                                )
                                            "
                                            :value="
                                                filterValues[column.key] ?? ''
                                            "
                                            class="h-8 rounded-md border border-slate-300 bg-white px-2 text-xs"
                                            @change="
                                                setFilterValue(
                                                    column.key,
                                                    $event.target.value,
                                                )
                                            "
                                        >
                                            <option value="">
                                                {{
                                                    t(
                                                        'filters.choose_value',
                                                        'Kies waarde',
                                                    )
                                                }}
                                            </option>
                                            <option value="true">
                                                {{ t('common.yes', 'Ja') }}
                                            </option>
                                            <option value="false">
                                                {{ t('common.no', 'Nee') }}
                                            </option>
                                        </select>
                                    </template>

                                    <template v-else>
                                        <template
                                            v-if="
                                                shouldRenderOptionFilterInput(
                                                    column,
                                                )
                                            "
                                        >
                                            <label
                                                class="text-xs text-slate-500"
                                                >{{
                                                    t(
                                                        'filters.option_value',
                                                        'Waarde uit lijst',
                                                    )
                                                }}</label
                                            >

                                            <RwAutocompleteInput
                                                v-if="
                                                    filterUsesAutocompleteInput(
                                                        column,
                                                    )
                                                "
                                                :name="`filter-option-value-${column.key}`"
                                                :aria-label="
                                                    t(
                                                        'filters.aria.value_for',
                                                        'Filter waarde voor :label',
                                                        {
                                                            label: columnLabel(
                                                                column,
                                                            ),
                                                        },
                                                    )
                                                "
                                                :model-value="
                                                    filterOptionValues[
                                                        column.key
                                                    ] ?? ''
                                                "
                                                :display-value="
                                                    filterOptionValues[
                                                        column.key
                                                    ] ?? ''
                                                "
                                                :items="
                                                    filterOptionItemsForColumn(
                                                        column,
                                                    )
                                                "
                                                :item-title="'title'"
                                                :item-value="'value'"
                                                :search-fields="[
                                                    'title',
                                                    'value',
                                                ]"
                                                :allow-custom="
                                                    column.editAutocompleteAllowCustom ===
                                                    true
                                                "
                                                :custom-trim="
                                                    column.editCustomTrim !==
                                                    false
                                                "
                                                :custom-min-length="
                                                    Number(
                                                        column.editCustomMinLength ||
                                                            0,
                                                    )
                                                "
                                                :multiple="
                                                    column.editMultiple === true
                                                "
                                                :show-checkboxes="
                                                    column.editShowCheckboxes ===
                                                        true ||
                                                    column.editMultiple === true
                                                "
                                                :selection-chips="
                                                    column.editSelectionChips !==
                                                    false
                                                "
                                                :max-selection-chips="
                                                    Number(
                                                        column.editMaxSelectionChips ||
                                                            3,
                                                    )
                                                "
                                                :close-on-select="
                                                    hasColumnOption(
                                                        column,
                                                        'editCloseOnSelect',
                                                    )
                                                        ? column.editCloseOnSelect ===
                                                          true
                                                        : column.editMultiple !==
                                                          true
                                                "
                                                :messages="rwtableMessages"
                                                @update:model-value="
                                                    onFilterOptionInput(
                                                        column.key,
                                                        $event,
                                                    )
                                                "
                                            />

                                            <select
                                                v-else
                                                :name="`filter-option-value-${column.key}`"
                                                :aria-label="
                                                    t(
                                                        'filters.aria.value_for',
                                                        'Filter waarde voor :label',
                                                        {
                                                            label: columnLabel(
                                                                column,
                                                            ),
                                                        },
                                                    )
                                                "
                                                :value="
                                                    filterOptionValues[
                                                        column.key
                                                    ] ?? ''
                                                "
                                                class="rounded-md border border-slate-300 bg-white px-2 text-xs"
                                                :class="
                                                    column.editMultiple === true
                                                        ? 'min-h-[120px] py-1'
                                                        : 'h-8'
                                                "
                                                :multiple="
                                                    column.editMultiple === true
                                                "
                                                @change="
                                                    onFilterOptionInput(
                                                        column.key,
                                                        readSelectFilterValue(
                                                            $event.target,
                                                            column,
                                                        ),
                                                    )
                                                "
                                            >
                                                <option
                                                    v-if="
                                                        column.editMultiple !==
                                                        true
                                                    "
                                                    value=""
                                                >
                                                    {{
                                                        t(
                                                            'filters.choose_value',
                                                            'Kies waarde',
                                                        )
                                                    }}
                                                </option>
                                                <option
                                                    v-for="option in filterOptionItemsForColumn(
                                                        column,
                                                    )"
                                                    :key="`${column.key}-filter-option-${option.value}`"
                                                    :value="option.value"
                                                >
                                                    {{ option.title }}
                                                </option>
                                            </select>
                                        </template>

                                        <template v-else>
                                            <label
                                                class="text-xs text-slate-500"
                                                >{{
                                                    t(
                                                        'filters.free_text',
                                                        'Vrije tekst',
                                                    )
                                                }}</label
                                            >
                                            <input
                                                :ref="
                                                    (el) =>
                                                        registerFilterTextInput(
                                                            column.key,
                                                            el,
                                                        )
                                                "
                                                :name="`filter-value-${column.key}`"
                                                :aria-label="
                                                    t(
                                                        'filters.aria.value_for',
                                                        'Filter waarde voor :label',
                                                        {
                                                            label: columnLabel(
                                                                column,
                                                            ),
                                                        },
                                                    )
                                                "
                                                :type="
                                                    filterInputType(
                                                        filterTypes[column.key],
                                                        resolveFilterModeForColumn(
                                                            column.key,
                                                        ),
                                                    )
                                                "
                                                :value="
                                                    filterValues[column.key] ??
                                                    ''
                                                "
                                                class="h-8 rounded-md border border-slate-300 px-2 text-xs"
                                                @input="
                                                    onFilterTextInput(
                                                        column.key,
                                                        $event,
                                                    )
                                                "
                                            />
                                        </template>
                                    </template>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="bg-transparent p-0"
                                :aria-label="
                                    t(
                                        'table.column.aria.resize_column',
                                        'Wijzig breedte van :label',
                                        { label: columnLabel(column) },
                                    )
                                "
                                :style="{
                                    position: 'absolute',
                                    top: '0',
                                    bottom: '0',
                                    right: '-6px',
                                    width: '12px',
                                    zIndex: Z_LAYERS.resizeHandle,
                                    display: 'flex',
                                    justifyContent: 'center',
                                    alignItems: 'stretch',
                                    cursor: 'col-resize',
                                }"
                                @mousedown.stop.prevent="
                                    startResize($event, column.key)
                                "
                                @click.stop.prevent
                            >
                                <span
                                    aria-hidden="true"
                                    class="h-full rounded transition-colors group-hover:bg-blue-500"
                                    :style="{
                                        width: '1px',
                                        backgroundColor: 'transparent',
                                    }"
                                />
                            </button>
                        </th>
                    </tr>
                </thead>

                <tbody>
                    <tr v-if="tableLoading">
                        <td
                            :colspan="tableColspan"
                            class="px-3 py-4 text-center text-slate-500"
                        >
                            {{ t('table.loading', 'Laden...') }}
                        </td>
                    </tr>

                    <tr v-else-if="renderedRows.length === 0">
                        <td
                            :colspan="tableColspan"
                            class="px-3 py-4 text-center text-slate-500"
                        >
                            <span
                                class="inline-flex items-center justify-center gap-2"
                            >
                                <i
                                    class="mdi mdi-emoticon-confused text-base leading-none"
                                    aria-hidden="true"
                                />
                                {{
                                    t(
                                        'table.no_records',
                                        'Geen records gevonden.',
                                    )
                                }}
                            </span>
                        </td>
                    </tr>

                    <template
                        v-for="(row, rowIndex) in renderedRows"
                        :key="`${row[idKey] ?? 'row'}-${rowIndex}`"
                    >
                        <tr
                            v-if="shouldRenderCreateRowAbove(row)"
                            class="border-t border-blue-200 bg-blue-50/40"
                        >
                            <td
                                v-if="checkboxColumn"
                                class="border-r border-slate-200 px-3 py-2"
                                :style="
                                    utilityColumnStyle(
                                        'checkbox',
                                        false,
                                        CREATE_ROW_STICKY_BACKGROUND,
                                    )
                                "
                            />

                            <td
                                v-if="showExpandColumn"
                                class="border-r border-slate-200 px-3 py-2"
                                :style="
                                    expandColumnStyle(
                                        false,
                                        CREATE_ROW_STICKY_BACKGROUND,
                                    )
                                "
                            />

                            <td
                                v-for="(column, colIndex) in selectedColumns"
                                :key="`create-above-${row[idKey]}-${column.key}`"
                                class="px-3 py-2"
                                :class="
                                    colIndex < selectedColumns.length - 1
                                        ? 'border-r border-slate-200'
                                        : ''
                                "
                                :style="createColumnStyle(column, colIndex)"
                            >
                                <div
                                    class="flex items-center"
                                    :class="colIndex === 0 ? 'gap-2' : ''"
                                >
                                    <button
                                        v-if="colIndex === 0"
                                        type="button"
                                        class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded border border-red-200 text-red-600 hover:bg-red-50"
                                        :aria-label="
                                            t(
                                                'actions.cancel_new_row',
                                                'Annuleer nieuwe rij',
                                            )
                                        "
                                        @click.stop="closeCreateRow"
                                    >
                                        <i
                                            class="mdi mdi-delete text-base leading-none"
                                        />
                                    </button>

                                    <div class="min-w-0 flex-1">
                                        <template v-if="column.editable">
                                            <RwAutocompleteInput
                                                v-if="
                                                    (column.editInput ===
                                                        'select' ||
                                                        column.editInput ===
                                                            'autocomplete') &&
                                                    Array.isArray(
                                                        column.editItems,
                                                    )
                                                "
                                                :ref="
                                                    (el) =>
                                                        registerCreateInput(
                                                            column.editField ||
                                                                column.key,
                                                            el,
                                                        )
                                                "
                                                :name="`create-${column.editField || column.key}`"
                                                :aria-label="
                                                    t(
                                                        'table.aria.new_value_for',
                                                        'Nieuwe waarde voor :label',
                                                        {
                                                            label: columnLabel(
                                                                column,
                                                            ),
                                                        },
                                                    )
                                                "
                                                :data-create-field="
                                                    column.editField ||
                                                    column.key
                                                "
                                                :model-value="
                                                    createDraft[
                                                        column.editField ||
                                                            column.key
                                                    ]
                                                "
                                                :display-value="
                                                    autocompleteDisplayValue(
                                                        column,
                                                        createDraft,
                                                    )
                                                "
                                                :items="column.editItems"
                                                :item-title="
                                                    column.editItemTitle ||
                                                    'title'
                                                "
                                                :item-value="
                                                    column.editItemValue ||
                                                    'value'
                                                "
                                                :search-fields="
                                                    Array.isArray(
                                                        column.editSearchFields,
                                                    )
                                                        ? column.editSearchFields
                                                        : []
                                                "
                                                :allow-custom="
                                                    column.editInput ===
                                                        'autocomplete' &&
                                                    column.editAutocompleteAllowCustom ===
                                                        true
                                                "
                                                :custom-trim="
                                                    column.editCustomTrim !==
                                                    false
                                                "
                                                :custom-min-length="
                                                    Number(
                                                        column.editCustomMinLength ||
                                                            0,
                                                    )
                                                "
                                                :multiple="
                                                    column.editMultiple === true
                                                "
                                                :show-checkboxes="
                                                    column.editShowCheckboxes ===
                                                    true
                                                "
                                                :selection-chips="
                                                    column.editSelectionChips !==
                                                    false
                                                "
                                                :max-selection-chips="
                                                    Number(
                                                        column.editMaxSelectionChips ||
                                                            3,
                                                    )
                                                "
                                                :close-on-select="
                                                    hasColumnOption(
                                                        column,
                                                        'editCloseOnSelect',
                                                    )
                                                        ? column.editCloseOnSelect ===
                                                          true
                                                        : column.editMultiple !==
                                                          true
                                                "
                                                :invalid="
                                                    Boolean(
                                                        createColumnFieldError(
                                                            column,
                                                        ),
                                                    )
                                                "
                                                :error-message="
                                                    createColumnFieldError(
                                                        column,
                                                    )
                                                "
                                                :required-missing="
                                                    isColumnRequired(column) &&
                                                    isFieldEmptyValue(
                                                        createDraft[
                                                            column.editField ||
                                                                column.key
                                                        ],
                                                    ) &&
                                                    !createColumnFieldError(
                                                        column,
                                                    )
                                                "
                                                :required-highlight-color="
                                                    requiredHighlightColor(
                                                        column,
                                                    )
                                                "
                                                :messages="rwtableMessages"
                                                @update:model-value="
                                                    createDraft[
                                                        column.editField ||
                                                            column.key
                                                    ] = $event;
                                                    clearCreateErrorsForColumn(
                                                        column,
                                                    );
                                                    clearMutationErrorMessage();
                                                "
                                                @resolve="
                                                    onCreateAutocompleteResolve(
                                                        column,
                                                        $event,
                                                    )
                                                "
                                                @blur="
                                                    onCreateInputBlur(column)
                                                "
                                                @keydown="onCreateInputKeydown"
                                            />

                                            <div
                                                v-else-if="
                                                    column.type === 'boolean'
                                                "
                                                class="rw-input-with-tooltip"
                                            >
                                                <input
                                                    :ref="
                                                        (el) =>
                                                            registerCreateInput(
                                                                column.editField ||
                                                                    column.key,
                                                                el,
                                                            )
                                                    "
                                                    :data-create-field="
                                                        column.editField ||
                                                        column.key
                                                    "
                                                    type="checkbox"
                                                    :name="`create-${column.editField || column.key}`"
                                                    :aria-label="
                                                        t(
                                                            'table.aria.new_value_for',
                                                            'Nieuwe waarde voor :label',
                                                            {
                                                                label: columnLabel(
                                                                    column,
                                                                ),
                                                            },
                                                        )
                                                    "
                                                    :class="
                                                        createColumnFieldError(
                                                            column,
                                                        )
                                                            ? 'rounded ring-2 ring-red-400'
                                                            : ''
                                                    "
                                                    :checked="
                                                        Boolean(
                                                            createDraft[
                                                                column.editField ||
                                                                    column.key
                                                            ],
                                                        )
                                                    "
                                                    @change="
                                                        createDraft[
                                                            column.editField ||
                                                                column.key
                                                        ] =
                                                            $event.target.checked;
                                                        clearCreateErrorsForColumn(
                                                            column,
                                                        );
                                                        clearMutationErrorMessage();
                                                    "
                                                    @blur="
                                                        onCreateInputBlur(
                                                            column,
                                                        )
                                                    "
                                                    @keydown="
                                                        onCreateInputKeydown
                                                    "
                                                />
                                                <span
                                                    v-if="
                                                        createColumnFieldError(
                                                            column,
                                                        )
                                                    "
                                                    class="rw-inline-error-tooltip"
                                                >
                                                    {{
                                                        createColumnFieldError(
                                                            column,
                                                        )
                                                    }}
                                                </span>
                                            </div>

                                            <div
                                                v-else
                                                class="rw-input-with-tooltip"
                                            >
                                                <input
                                                    :ref="
                                                        (el) =>
                                                            registerCreateInput(
                                                                column.editField ||
                                                                    column.key,
                                                                el,
                                                            )
                                                    "
                                                    :data-create-field="
                                                        column.editField ||
                                                        column.key
                                                    "
                                                    :type="
                                                        column.type === 'number'
                                                            ? 'number'
                                                            : 'text'
                                                    "
                                                    :name="`create-${column.editField || column.key}`"
                                                    :aria-label="
                                                        t(
                                                            'table.aria.new_value_for',
                                                            'Nieuwe waarde voor :label',
                                                            {
                                                                label: columnLabel(
                                                                    column,
                                                                ),
                                                            },
                                                        )
                                                    "
                                                    :value="
                                                        createDraft[
                                                            column.editField ||
                                                                column.key
                                                        ] ?? ''
                                                    "
                                                    :aria-invalid="
                                                        createColumnFieldError(
                                                            column,
                                                        )
                                                            ? 'true'
                                                            : 'false'
                                                    "
                                                    class="h-8 w-full rounded border px-2 text-sm"
                                                    :class="
                                                        createColumnFieldError(
                                                            column,
                                                        )
                                                            ? 'border-red-400 bg-red-50'
                                                            : 'border-slate-300'
                                                    "
                                                    :style="
                                                        createColumnInputStyle(
                                                            column,
                                                        )
                                                    "
                                                    @input="
                                                        createDraft[
                                                            column.editField ||
                                                                column.key
                                                        ] = $event.target.value;
                                                        clearCreateErrorsForColumn(
                                                            column,
                                                        );
                                                        clearMutationErrorMessage();
                                                    "
                                                    @blur="
                                                        onCreateInputBlur(
                                                            column,
                                                        )
                                                    "
                                                    @keydown="
                                                        onCreateInputKeydown
                                                    "
                                                />
                                                <span
                                                    v-if="
                                                        createColumnFieldError(
                                                            column,
                                                        )
                                                    "
                                                    class="rw-inline-error-tooltip"
                                                >
                                                    {{
                                                        createColumnFieldError(
                                                            column,
                                                        )
                                                    }}
                                                </span>
                                            </div>
                                        </template>

                                        <template v-else>
                                            <span class="text-xs text-slate-400"
                                                >-</span
                                            >
                                        </template>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <tr
                            class="group hover:bg-slate-100/90"
                            :class="rowClass(row, rowIndex)"
                            :data-row-id="row[idKey]"
                            @click="onRowFocus(rowIndex)"
                            @mouseover="onRowMouseEnter(row)"
                            @mouseleave="onRowMouseLeave(row)"
                        >
                            <td
                                v-if="checkboxColumn"
                                class="border-r border-slate-200 px-3 py-2"
                                :style="
                                    withRowHighlight(
                                        utilityColumnStyle('checkbox'),
                                        row,
                                        rowIndex,
                                    )
                                "
                            >
                                <input
                                    type="checkbox"
                                    :name="`row-select-${row[idKey]}`"
                                    :aria-label="
                                        t(
                                            'table.aria.select_row',
                                            'Selecteer rij :id',
                                            {
                                                id: row[idKey],
                                            },
                                        )
                                    "
                                    :checked="checkedRows.includes(row[idKey])"
                                    @change="toggleRowSelection(row[idKey])"
                                    @click.stop
                                />
                            </td>

                            <td
                                v-if="showExpandColumn"
                                class="border-r border-slate-200 px-3 py-2 text-center"
                                :style="
                                    withRowHighlight(
                                        expandColumnStyle(),
                                        row,
                                        rowIndex,
                                    )
                                "
                            >
                                <button
                                    v-if="canExpandRow(row)"
                                    type="button"
                                    class="rw-btn-hover inline-flex h-7 w-7 items-center justify-center rounded text-slate-600"
                                    :aria-label="
                                        isRowExpanded(row)
                                            ? t(
                                                  'table.aria.hide_row_details',
                                                  'Verberg extra details',
                                              )
                                            : t(
                                                  'table.aria.show_row_details',
                                                  'Toon extra details',
                                              )
                                    "
                                    @click.stop="toggleExpandedRow(row)"
                                >
                                    <i
                                        class="mdi text-lg leading-none"
                                        :class="
                                            isRowExpanded(row)
                                                ? 'mdi-chevron-down'
                                                : 'mdi-chevron-right'
                                        "
                                    />
                                </button>
                            </td>

                            <td
                                v-for="(column, colIndex) in selectedColumns"
                                :key="`${row[idKey] ?? rowIndex}-${column.key}`"
                                class="relative px-3 py-2 text-slate-700"
                                :class="[
                                    clickableCellClass(column),
                                    colIndex < selectedColumns.length - 1
                                        ? 'border-r border-slate-200'
                                        : '',
                                    cellClassFor(
                                        row,
                                        column,
                                        rowIndex,
                                        colIndex,
                                    ),
                                ]"
                                :style="
                                    withRowHighlight(
                                        cellStyleWithOverlay(
                                            row,
                                            column,
                                            rowIndex,
                                            colIndex,
                                        ),
                                        row,
                                        rowIndex,
                                    )
                                "
                                @click.stop="
                                    handleCellClick(
                                        column,
                                        row,
                                        rowIndex,
                                        colIndex,
                                    )
                                "
                            >
                                <div
                                    v-if="showsInlineRowMenu(row, colIndex)"
                                    class="absolute left-2 top-1/2 z-10 -translate-y-1/2"
                                >
                                    <button
                                        type="button"
                                        class="rw-btn-hover inline-flex h-7 w-7 items-center justify-center rounded border border-input bg-background text-slate-600 shadow-sm transition-opacity"
                                        :class="
                                            shouldShowInlineRowMenuButton(row)
                                                ? 'opacity-100'
                                                : 'opacity-0 focus:opacity-100 group-hover:opacity-100'
                                        "
                                        @click.stop="toggleRowMenu(row[idKey])"
                                    >
                                        <i
                                            class="mdi mdi-dots-vertical text-base leading-none"
                                        />
                                    </button>

                                    <div
                                        v-if="isRowMenuOpen(row[idKey])"
                                        class="absolute left-full ml-1 max-h-72 min-w-56 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-xl"
                                        :class="rowMenuPositionClass(rowIndex)"
                                        :style="rowMenuOverlayStyle"
                                    >
                                        <div
                                            class="flex items-center justify-start border-b border-slate-200 bg-white px-2 pb-2 pt-2"
                                        >
                                            <button
                                                type="button"
                                                class="rw-btn-hover inline-flex h-8 items-center rounded border border-input bg-background px-2 text-sm text-slate-700 shadow-sm"
                                                @click.stop="
                                                    rowMenuOpenId = null
                                                "
                                            >
                                                <i
                                                    class="mdi mdi-arrow-left-circle mr-1 text-base leading-none"
                                                />
                                                {{ t('actions.back', 'Terug') }}
                                            </button>
                                        </div>

                                        <div class="p-1">
                                            <button
                                                v-for="(
                                                    item, idx
                                                ) in resolvedRowMenuItems(row)"
                                                :key="`${row[idKey]}-${idx}`"
                                                type="button"
                                                :disabled="item.disabled"
                                                class="rw-btn-hover flex w-full items-center gap-2 whitespace-nowrap rounded px-2 py-1.5 text-left text-sm text-slate-700 disabled:opacity-40"
                                                @click.stop="
                                                    onRowMenuAction(item, row)
                                                "
                                            >
                                                <i
                                                    v-if="item.icon"
                                                    class="mdi text-base leading-none"
                                                    :class="item.icon"
                                                />
                                                {{ item.label }}
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <template v-if="isEditingCell(row, column)">
                                    <RwAutocompleteInput
                                        v-if="
                                            (column.editInput === 'select' ||
                                                column.editInput ===
                                                    'autocomplete') &&
                                            Array.isArray(column.editItems)
                                        "
                                        :ref="
                                            (el) =>
                                                registerEditInput(
                                                    row[idKey],
                                                    column.key,
                                                    el,
                                                )
                                        "
                                        :name="`edit-${row[idKey]}-${column.editField || column.key}`"
                                        :aria-label="
                                            t(
                                                'table.aria.edit_field',
                                                'Bewerk :label',
                                                {
                                                    label: columnLabel(column),
                                                },
                                            )
                                        "
                                        :model-value="editingValue"
                                        :display-value="
                                            autocompleteDisplayValue(
                                                column,
                                                row,
                                            )
                                        "
                                        :items="column.editItems"
                                        :item-title="
                                            column.editItemTitle || 'title'
                                        "
                                        :item-value="
                                            column.editItemValue || 'value'
                                        "
                                        :search-fields="
                                            Array.isArray(
                                                column.editSearchFields,
                                            )
                                                ? column.editSearchFields
                                                : []
                                        "
                                        :allow-custom="
                                            column.editInput ===
                                                'autocomplete' &&
                                            column.editAutocompleteAllowCustom ===
                                                true
                                        "
                                        :custom-trim="
                                            column.editCustomTrim !== false
                                        "
                                        :custom-min-length="
                                            Number(
                                                column.editCustomMinLength || 0,
                                            )
                                        "
                                        :multiple="column.editMultiple === true"
                                        :show-checkboxes="
                                            column.editShowCheckboxes === true
                                        "
                                        :selection-chips="
                                            column.editSelectionChips !== false
                                        "
                                        :max-selection-chips="
                                            Number(
                                                column.editMaxSelectionChips ||
                                                    3,
                                            )
                                        "
                                        :close-on-select="
                                            hasColumnOption(
                                                column,
                                                'editCloseOnSelect',
                                            )
                                                ? column.editCloseOnSelect ===
                                                  true
                                                : column.editMultiple !== true
                                        "
                                        :invalid="
                                            Boolean(
                                                editColumnFieldError(
                                                    row[idKey],
                                                    column,
                                                ),
                                            )
                                        "
                                        :error-message="
                                            editColumnFieldError(
                                                row[idKey],
                                                column,
                                            )
                                        "
                                        :required-missing="
                                            isColumnRequired(column) &&
                                            isFieldEmptyValue(editingValue) &&
                                            !editColumnFieldError(
                                                row[idKey],
                                                column,
                                            )
                                        "
                                        :required-highlight-color="
                                            requiredHighlightColor(column)
                                        "
                                        :messages="rwtableMessages"
                                        @update:model-value="
                                            editingValue = $event;
                                            clearEditErrorsForColumn(
                                                row[idKey],
                                                column,
                                            );
                                            clearMutationErrorMessage();
                                        "
                                        @resolve="
                                            onEditAutocompleteResolve(
                                                column,
                                                $event,
                                            )
                                        "
                                        @blur="onEditInputBlur(row, column)"
                                        @keydown="onEditInputKeydown"
                                    />

                                    <div
                                        v-else-if="column.type === 'boolean'"
                                        class="rw-input-with-tooltip"
                                    >
                                        <input
                                            :ref="
                                                (el) =>
                                                    registerEditInput(
                                                        row[idKey],
                                                        column.key,
                                                        el,
                                                    )
                                            "
                                            type="checkbox"
                                            :name="`edit-${row[idKey]}-${column.editField || column.key}`"
                                            :aria-label="
                                                t(
                                                    'table.aria.edit_field',
                                                    'Bewerk :label',
                                                    {
                                                        label: columnLabel(
                                                            column,
                                                        ),
                                                    },
                                                )
                                            "
                                            :class="
                                                editColumnFieldError(
                                                    row[idKey],
                                                    column,
                                                )
                                                    ? 'rounded ring-2 ring-red-400'
                                                    : ''
                                            "
                                            :checked="Boolean(editingValue)"
                                            @change="
                                                editingValue =
                                                    $event.target.checked;
                                                clearEditErrorsForColumn(
                                                    row[idKey],
                                                    column,
                                                );
                                                clearMutationErrorMessage();
                                            "
                                            @blur="onEditInputBlur(row, column)"
                                            @keydown="onEditInputKeydown"
                                        />
                                        <span
                                            v-if="
                                                editColumnFieldError(
                                                    row[idKey],
                                                    column,
                                                )
                                            "
                                            class="rw-inline-error-tooltip"
                                        >
                                            {{
                                                editColumnFieldError(
                                                    row[idKey],
                                                    column,
                                                )
                                            }}
                                        </span>
                                    </div>

                                    <div v-else class="rw-input-with-tooltip">
                                        <input
                                            :ref="
                                                (el) =>
                                                    registerEditInput(
                                                        row[idKey],
                                                        column.key,
                                                        el,
                                                    )
                                            "
                                            :type="
                                                column.type === 'number'
                                                    ? 'number'
                                                    : 'text'
                                            "
                                            :name="`edit-${row[idKey]}-${column.editField || column.key}`"
                                            :aria-label="
                                                t(
                                                    'table.aria.edit_field',
                                                    'Bewerk :label',
                                                    {
                                                        label: columnLabel(
                                                            column,
                                                        ),
                                                    },
                                                )
                                            "
                                            :value="editingValue"
                                            :aria-invalid="
                                                editColumnFieldError(
                                                    row[idKey],
                                                    column,
                                                )
                                                    ? 'true'
                                                    : 'false'
                                            "
                                            class="h-8 w-full rounded border px-2 text-sm"
                                            :class="
                                                editColumnFieldError(
                                                    row[idKey],
                                                    column,
                                                )
                                                    ? 'border-red-400 bg-red-50'
                                                    : 'border-slate-300'
                                            "
                                            :style="
                                                editColumnInputStyle(
                                                    row,
                                                    column,
                                                )
                                            "
                                            @input="
                                                editingValue =
                                                    $event.target.value;
                                                clearEditErrorsForColumn(
                                                    row[idKey],
                                                    column,
                                                );
                                                clearMutationErrorMessage();
                                            "
                                            @blur="onEditInputBlur(row, column)"
                                            @keydown="onEditInputKeydown"
                                        />
                                        <span
                                            v-if="
                                                editColumnFieldError(
                                                    row[idKey],
                                                    column,
                                                )
                                            "
                                            class="rw-inline-error-tooltip"
                                        >
                                            {{
                                                editColumnFieldError(
                                                    row[idKey],
                                                    column,
                                                )
                                            }}
                                        </span>
                                    </div>
                                </template>

                                <template v-else>
                                    <div
                                        class="flex w-full min-w-0 items-center"
                                        :class="[
                                            contentAlignmentClass(column),
                                            rowMenuOffsetClass(
                                                column,
                                                showsInlineRowMenu(
                                                    row,
                                                    colIndex,
                                                ),
                                            ),
                                        ]"
                                    >
                                        <template
                                            v-if="rendersAsIconColumn(column)"
                                        >
                                            <i
                                                class="mdi text-base leading-none"
                                                :class="
                                                    getIconName(column, row)
                                                "
                                                :style="{
                                                    color: getIconColor(
                                                        column,
                                                        row,
                                                    ),
                                                }"
                                            />
                                        </template>
                                        <template
                                            v-else-if="
                                                shouldRenderAsChip(column, row)
                                            "
                                        >
                                            <span
                                                :class="chipClass(column, row)"
                                                :style="chipStyle(column, row)"
                                            >
                                                <i
                                                    v-if="
                                                        column.chipPrependIcon
                                                    "
                                                    class="mdi text-[14px] leading-none"
                                                    :class="
                                                        column.chipPrependIcon
                                                    "
                                                />
                                                <span class="truncate">
                                                    {{
                                                        getChipDisplayValue(
                                                            column,
                                                            row,
                                                        )
                                                    }}
                                                </span>
                                            </span>
                                        </template>
                                        <template
                                            v-else-if="
                                                shouldRenderAsMultiSelection(
                                                    column,
                                                    row,
                                                )
                                            "
                                        >
                                            <span
                                                class="inline-flex max-w-full flex-wrap items-center gap-1"
                                            >
                                                <span
                                                    v-for="(
                                                        item, idx
                                                    ) in multiSelectionVisibleItems(
                                                        column,
                                                        row,
                                                    )"
                                                    :key="`multi-chip-${idx}`"
                                                    class="inline-flex max-w-full items-center rounded-full border border-slate-300 bg-slate-100 px-2 py-0.5 text-xs font-medium leading-5 text-slate-700"
                                                    :style="
                                                        multiSelectionChipStyle(
                                                            column,
                                                            item,
                                                        )
                                                    "
                                                >
                                                    <span class="truncate">{{
                                                        item.label
                                                    }}</span>
                                                </span>
                                                <span
                                                    v-if="
                                                        multiSelectionHiddenCount(
                                                            column,
                                                            row,
                                                        ) > 0
                                                    "
                                                    class="inline-flex items-center rounded-full border border-slate-300 bg-slate-100 px-2 py-0.5 text-xs font-medium leading-5 text-slate-600"
                                                >
                                                    +{{
                                                        multiSelectionHiddenCount(
                                                            column,
                                                            row,
                                                        )
                                                    }}
                                                </span>
                                            </span>
                                        </template>
                                        <template v-else>
                                            <span
                                                :class="
                                                    clickableCellValueClass(
                                                        column,
                                                        row,
                                                    )
                                                "
                                            >
                                                {{
                                                    formatCellValue(row, column)
                                                }}
                                            </span>
                                        </template>
                                    </div>
                                </template>
                            </td>
                        </tr>

                        <tr v-if="canExpandRow(row) && isRowExpanded(row)">
                            <td
                                :colspan="tableColspan"
                                class="border-t border-slate-100 bg-slate-50/70 px-4 py-3"
                            >
                                <div class="flex flex-col gap-3">
                                    <div
                                        v-for="(
                                            item, expandedIndex
                                        ) in expandedRowContentItems(row)"
                                        :key="`${row[idKey] ?? rowIndex}-expanded-${expandedIndex}`"
                                        class="flex flex-col gap-1"
                                    >
                                        <div
                                            class="text-xs font-semibold uppercase tracking-wide text-slate-500"
                                        >
                                            {{ item.label }}
                                        </div>
                                        <div
                                            class="whitespace-pre-wrap break-words text-sm text-slate-700"
                                        >
                                            {{ item.value }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>

                    <tr
                        v-if="showCreateRowAtBottom"
                        class="border-t border-blue-200 bg-blue-50/40"
                    >
                        <td
                            v-if="checkboxColumn"
                            class="border-r border-slate-200 px-3 py-2"
                            :style="
                                utilityColumnStyle(
                                    'checkbox',
                                    false,
                                    CREATE_ROW_STICKY_BACKGROUND,
                                )
                            "
                        />

                        <td
                            v-if="showExpandColumn"
                            class="border-r border-slate-200 px-3 py-2"
                            :style="
                                expandColumnStyle(
                                    false,
                                    CREATE_ROW_STICKY_BACKGROUND,
                                )
                            "
                        />

                        <td
                            v-for="(column, colIndex) in selectedColumns"
                            :key="`create-${column.key}`"
                            class="px-3 py-2"
                            :class="
                                colIndex < selectedColumns.length - 1
                                    ? 'border-r border-slate-200'
                                    : ''
                            "
                            :style="createColumnStyle(column, colIndex)"
                        >
                            <div
                                class="flex items-center"
                                :class="colIndex === 0 ? 'gap-2' : ''"
                            >
                                <button
                                    v-if="colIndex === 0"
                                    type="button"
                                    class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded border border-red-200 text-red-600 hover:bg-red-50"
                                    :aria-label="
                                        t(
                                            'actions.cancel_new_row',
                                            'Annuleer nieuwe rij',
                                        )
                                    "
                                    @click.stop="closeCreateRow"
                                >
                                    <i
                                        class="mdi mdi-delete text-base leading-none"
                                    />
                                </button>

                                <div class="min-w-0 flex-1">
                                    <template v-if="column.editable">
                                        <RwAutocompleteInput
                                            v-if="
                                                (column.editInput ===
                                                    'select' ||
                                                    column.editInput ===
                                                        'autocomplete') &&
                                                Array.isArray(column.editItems)
                                            "
                                            :ref="
                                                (el) =>
                                                    registerCreateInput(
                                                        column.editField ||
                                                            column.key,
                                                        el,
                                                    )
                                            "
                                            :name="`create-${column.editField || column.key}`"
                                            :aria-label="
                                                t(
                                                    'table.aria.new_value_for',
                                                    'Nieuwe waarde voor :label',
                                                    {
                                                        label: columnLabel(
                                                            column,
                                                        ),
                                                    },
                                                )
                                            "
                                            :data-create-field="
                                                column.editField || column.key
                                            "
                                            :model-value="
                                                createDraft[
                                                    column.editField ||
                                                        column.key
                                                ]
                                            "
                                            :display-value="
                                                autocompleteDisplayValue(
                                                    column,
                                                    createDraft,
                                                )
                                            "
                                            :items="column.editItems"
                                            :item-title="
                                                column.editItemTitle || 'title'
                                            "
                                            :item-value="
                                                column.editItemValue || 'value'
                                            "
                                            :search-fields="
                                                Array.isArray(
                                                    column.editSearchFields,
                                                )
                                                    ? column.editSearchFields
                                                    : []
                                            "
                                            :allow-custom="
                                                column.editInput ===
                                                    'autocomplete' &&
                                                column.editAutocompleteAllowCustom ===
                                                    true
                                            "
                                            :custom-trim="
                                                column.editCustomTrim !== false
                                            "
                                            :custom-min-length="
                                                Number(
                                                    column.editCustomMinLength ||
                                                        0,
                                                )
                                            "
                                            :multiple="
                                                column.editMultiple === true
                                            "
                                            :show-checkboxes="
                                                column.editShowCheckboxes ===
                                                true
                                            "
                                            :selection-chips="
                                                column.editSelectionChips !==
                                                false
                                            "
                                            :max-selection-chips="
                                                Number(
                                                    column.editMaxSelectionChips ||
                                                        3,
                                                )
                                            "
                                            :close-on-select="
                                                hasColumnOption(
                                                    column,
                                                    'editCloseOnSelect',
                                                )
                                                    ? column.editCloseOnSelect ===
                                                      true
                                                    : column.editMultiple !==
                                                      true
                                            "
                                            :invalid="
                                                Boolean(
                                                    createColumnFieldError(
                                                        column,
                                                    ),
                                                )
                                            "
                                            :error-message="
                                                createColumnFieldError(column)
                                            "
                                            :required-missing="
                                                isColumnRequired(column) &&
                                                isFieldEmptyValue(
                                                    createDraft[
                                                        column.editField ||
                                                            column.key
                                                    ],
                                                ) &&
                                                !createColumnFieldError(column)
                                            "
                                            :required-highlight-color="
                                                requiredHighlightColor(column)
                                            "
                                            :messages="rwtableMessages"
                                            @update:model-value="
                                                createDraft[
                                                    column.editField ||
                                                        column.key
                                                ] = $event;
                                                clearCreateErrorsForColumn(
                                                    column,
                                                );
                                                clearMutationErrorMessage();
                                            "
                                            @resolve="
                                                onCreateAutocompleteResolve(
                                                    column,
                                                    $event,
                                                )
                                            "
                                            @blur="onCreateInputBlur(column)"
                                            @keydown="onCreateInputKeydown"
                                        />

                                        <div
                                            v-else-if="
                                                column.type === 'boolean'
                                            "
                                            class="rw-input-with-tooltip"
                                        >
                                            <input
                                                :ref="
                                                    (el) =>
                                                        registerCreateInput(
                                                            column.editField ||
                                                                column.key,
                                                            el,
                                                        )
                                                "
                                                :data-create-field="
                                                    column.editField ||
                                                    column.key
                                                "
                                                type="checkbox"
                                                :name="`create-${column.editField || column.key}`"
                                                :aria-label="
                                                    t(
                                                        'table.aria.new_value_for',
                                                        'Nieuwe waarde voor :label',
                                                        {
                                                            label: columnLabel(
                                                                column,
                                                            ),
                                                        },
                                                    )
                                                "
                                                :class="
                                                    createColumnFieldError(
                                                        column,
                                                    )
                                                        ? 'rounded ring-2 ring-red-400'
                                                        : ''
                                                "
                                                :checked="
                                                    Boolean(
                                                        createDraft[
                                                            column.editField ||
                                                                column.key
                                                        ],
                                                    )
                                                "
                                                @change="
                                                    createDraft[
                                                        column.editField ||
                                                            column.key
                                                    ] = $event.target.checked;
                                                    clearCreateErrorsForColumn(
                                                        column,
                                                    );
                                                    clearMutationErrorMessage();
                                                "
                                                @blur="
                                                    onCreateInputBlur(column)
                                                "
                                                @keydown="onCreateInputKeydown"
                                            />
                                            <span
                                                v-if="
                                                    createColumnFieldError(
                                                        column,
                                                    )
                                                "
                                                class="rw-inline-error-tooltip"
                                            >
                                                {{
                                                    createColumnFieldError(
                                                        column,
                                                    )
                                                }}
                                            </span>
                                        </div>

                                        <div
                                            v-else
                                            class="rw-input-with-tooltip"
                                        >
                                            <input
                                                :ref="
                                                    (el) =>
                                                        registerCreateInput(
                                                            column.editField ||
                                                                column.key,
                                                            el,
                                                        )
                                                "
                                                :data-create-field="
                                                    column.editField ||
                                                    column.key
                                                "
                                                :type="
                                                    column.type === 'number'
                                                        ? 'number'
                                                        : 'text'
                                                "
                                                :name="`create-${column.editField || column.key}`"
                                                :aria-label="
                                                    t(
                                                        'table.aria.new_value_for',
                                                        'Nieuwe waarde voor :label',
                                                        {
                                                            label: columnLabel(
                                                                column,
                                                            ),
                                                        },
                                                    )
                                                "
                                                :value="
                                                    createDraft[
                                                        column.editField ||
                                                            column.key
                                                    ] ?? ''
                                                "
                                                :aria-invalid="
                                                    createColumnFieldError(
                                                        column,
                                                    )
                                                        ? 'true'
                                                        : 'false'
                                                "
                                                class="h-8 w-full rounded border px-2 text-sm"
                                                :class="
                                                    createColumnFieldError(
                                                        column,
                                                    )
                                                        ? 'border-red-400 bg-red-50'
                                                        : 'border-slate-300'
                                                "
                                                :style="
                                                    createColumnInputStyle(
                                                        column,
                                                    )
                                                "
                                                @input="
                                                    createDraft[
                                                        column.editField ||
                                                            column.key
                                                    ] = $event.target.value;
                                                    clearCreateErrorsForColumn(
                                                        column,
                                                    );
                                                    clearMutationErrorMessage();
                                                "
                                                @blur="
                                                    onCreateInputBlur(column)
                                                "
                                                @keydown="onCreateInputKeydown"
                                            />
                                            <span
                                                v-if="
                                                    createColumnFieldError(
                                                        column,
                                                    )
                                                "
                                                class="rw-inline-error-tooltip"
                                            >
                                                {{
                                                    createColumnFieldError(
                                                        column,
                                                    )
                                                }}
                                            </span>
                                        </div>
                                    </template>

                                    <template v-else>
                                        <span class="text-xs text-slate-400"
                                            >-</span
                                        >
                                    </template>
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div ref="infiniteSentinelRef" class="h-1 w-full" />
        </div>

        <div
            class="flex flex-wrap items-center justify-between gap-2 border-x border-b border-slate-200 bg-white px-3 py-2"
        >
            <div
                v-if="!isInfiniteMode && lastPage > 1"
                class="flex items-center"
            >
                <button
                    v-for="(link, idx) in smartPagination"
                    :key="`pagination-${idx}-${link}`"
                    type="button"
                    class="inline-flex h-8 min-w-8 items-center justify-center border border-input bg-background px-2 text-xs text-slate-700 shadow-sm"
                    :class="[
                        idx > 0 ? '-ml-px' : '',
                        link === currentPage
                            ? 'z-10 bg-blue-50 font-semibold text-blue-700'
                            : 'rw-btn-hover',
                        link === '...'
                            ? 'cursor-default text-slate-400 hover:bg-white'
                            : '',
                    ]"
                    :disabled="link === '...'"
                    @click="onPaginationClick(link)"
                >
                    {{ link }}
                </button>
            </div>

            <div class="ml-auto flex items-center gap-2">
                <p v-if="localShowRecordCount" class="text-xs text-slate-500">
                    {{
                        t('table.record_count', 'Aantal rijen: :count', {
                            count: totalRows,
                        })
                    }}
                </p>
            </div>
        </div>
    </div>
</template>

<style scoped>
:where(button) {
    @apply font-medium transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50;
}

.rw-table :deep(input[type='text']),
.rw-table :deep(input[type='number']),
.rw-table :deep(input[type='date']),
.rw-table :deep(input[type='datetime-local']),
.rw-table :deep(select),
.rw-table :deep(textarea) {
    outline: none;
    box-shadow: none;
}

.rw-table
    :deep(
        input[type='text']:not(
                [data-rw-autocomplete-multiple-input='true']
            ):not([aria-invalid='true']):focus:not([class*='ring-red']):not(
                [class*='border-red']
            )
    ),
.rw-table
    :deep(
        input[type='number']:not([aria-invalid='true']):focus:not(
                [class*='ring-red']
            ):not([class*='border-red'])
    ),
.rw-table
    :deep(
        input[type='date']:not([aria-invalid='true']):focus:not(
                [class*='ring-red']
            ):not([class*='border-red'])
    ),
.rw-table
    :deep(
        input[type='datetime-local']:not([aria-invalid='true']):focus:not(
                [class*='ring-red']
            ):not([class*='border-red'])
    ),
.rw-table
    :deep(
        select:not([aria-invalid='true']):focus:not([class*='ring-red']):not(
                [class*='border-red']
            )
    ),
.rw-table
    :deep(
        textarea:not([aria-invalid='true']):focus:not([class*='ring-red']):not(
                [class*='border-red']
            )
    ),
.rw-table
    :deep(
        input[type='text']:not(
                [data-rw-autocomplete-multiple-input='true']
            ):not([aria-invalid='true']):focus-visible:not(
                [class*='ring-red']
            ):not([class*='border-red'])
    ),
.rw-table
    :deep(
        input[type='number']:not([aria-invalid='true']):focus-visible:not(
                [class*='ring-red']
            ):not([class*='border-red'])
    ),
.rw-table
    :deep(
        input[type='date']:not([aria-invalid='true']):focus-visible:not(
                [class*='ring-red']
            ):not([class*='border-red'])
    ),
.rw-table
    :deep(
        input[type='datetime-local']:not(
                [aria-invalid='true']
            ):focus-visible:not([class*='ring-red']):not([class*='border-red'])
    ),
.rw-table
    :deep(
        select:not([aria-invalid='true']):focus-visible:not(
                [class*='ring-red']
            ):not([class*='border-red'])
    ),
.rw-table
    :deep(
        textarea:not([aria-invalid='true']):focus-visible:not(
                [class*='ring-red']
            ):not([class*='border-red'])
    ) {
    border-color: rgb(203 213 225) !important;
    box-shadow: 0 0 0 1px rgba(203, 213, 225, 0.35) !important;
    outline: none !important;
    --tw-ring-color: transparent !important;
    --tw-ring-shadow: 0 0 #0000 !important;
}

.rw-table :deep(input[type='checkbox']) {
    accent-color: rgb(100 116 139) !important;
    color: rgb(100 116 139) !important;
}

.rw-table :deep(input[type='checkbox']:checked) {
    background-color: rgb(100 116 139) !important;
    border-color: rgb(100 116 139) !important;
}

.rw-table :deep(input[type='checkbox']:focus:not([class*='ring-'])),
.rw-table :deep(input[type='checkbox']:focus-visible:not([class*='ring-'])) {
    box-shadow: 0 0 0 1px rgba(203, 213, 225, 0.35);
    outline: none;
    --tw-ring-color: transparent !important;
    --tw-ring-shadow: 0 0 #0000 !important;
}

button.rw-btn-hover:hover {
    background-color: rgb(226 232 240) !important;
    border-color: rgb(203 213 225) !important;
}

button.rw-btn-hover-tone-blue:hover {
    background-color: rgb(226 232 240) !important;
    border-color: rgb(203 213 225) !important;
}

button.rw-btn-hover-tone-green:hover {
    background-color: rgb(226 232 240) !important;
    border-color: rgb(203 213 225) !important;
}

button.rw-btn-hover-tone-teal:hover {
    background-color: rgb(226 232 240) !important;
    border-color: rgb(203 213 225) !important;
}

.rw-id-hover {
    border: 1px solid transparent;
    background-color: transparent;
    color: rgb(29 78 216);
    cursor: pointer;
}

.rw-id-hover:hover {
    background-color: rgb(226 232 240);
    border-color: rgb(203 213 225);
    color: rgb(30 41 59);
}

.rw-cell-clickable {
    cursor: pointer;
}

.rw-cell-editable {
    cursor: pointer;
}

.rw-cell-editable:hover {
    color: rgb(30 41 59);
}

.rw-config-checkbox-row:hover {
    background-color: rgb(241 245 249);
}

.rw-config-checkbox-input {
    margin-right: 2px;
}

.rw-input-with-tooltip {
    position: relative;
}

.rw-inline-error-tooltip {
    position: absolute;
    left: 0;
    top: calc(100% + 4px);
    z-index: 40;
    max-width: 280px;
    border-radius: 8px;
    background: #b91c1c;
    color: #ffffff;
    font-size: 11px;
    line-height: 1.3;
    padding: 5px 8px;
    pointer-events: none;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.16);
}
</style>
