<template>
    <Head :title="toolbarTitle" />

    <AdminLayout :suppress-flash="true">
        <RwFormTemplate
            :title="toolbarTitle"
            :subtitle="
                t(
                    'chart_view.subtitle',
                    'Interactieve grafiekweergave op basis van querydata.',
                )
            "
        >
            <template #back>
                <RwActionButton
                    :label="t('actions.back', 'Terug')"
                    icon="mdi mdi-arrow-left-circle"
                    tone="back"
                    @click="goBack"
                />
            </template>

            <template #actions>
                <RwActionButton
                    :label="t('actions.refresh', 'Ververs')"
                    icon="mdi mdi-refresh"
                    tone="neutral"
                    :loading="previewLoading"
                    @click="refreshPreview"
                />
                <RwActionButton
                    v-if="showPdfPrintButton"
                    :label="t('actions.print_pdf', 'PDF afdrukken')"
                    icon="mdi mdi-file-pdf-box"
                    tone="delete"
                    :loading="pdfLoading"
                    @click="downloadPdf"
                />
                <RwActionButton
                    v-if="showSourceTableButton"
                    :label="t('actions.source_table', 'Tabel brondata')"
                    icon="mdi mdi-table"
                    tone="neutral"
                    @click="openSourceTable"
                />
            </template>

            <template #flash>
                <RwFlashMessage type="danger" :message="previewError" />
            </template>

            <div class="space-y-4">
                <Card>
                    <CardHeader>
                        <div
                            class="grid w-full gap-3 md:grid-cols-[1fr_320px] md:items-start"
                        >
                            <div>
                                <CardTitle class="text-base">
                                    {{
                                        chartTitle ||
                                        t('chart_view.title', 'Grafiek')
                                    }}
                                </CardTitle>
                                <p
                                    v-if="chartSubtitle"
                                    class="mt-1 text-sm text-slate-500"
                                >
                                    {{ chartSubtitle }}
                                </p>
                            </div>

                            <div v-if="allowChartTypeChange" class="grid gap-1">
                                <label class="text-xs text-slate-600">{{
                                    t('chart_view.type', 'Grafiektype')
                                }}</label>
                                <RwAutoCompleteInput
                                    v-model="selectedChartType"
                                    :items="chartTypeOptions"
                                    item-title="label"
                                    item-value="value"
                                    :search-fields="['label']"
                                />
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent class="space-y-3 p-4">
                        <div
                            v-if="previewLoading"
                            class="h-1.5 overflow-hidden rounded bg-sky-100"
                        >
                            <div
                                class="h-full w-1/2 animate-pulse bg-sky-500"
                            />
                        </div>

                        <p
                            v-if="previewMeta && !previewLoading"
                            class="text-xs text-slate-500"
                        >
                            {{ t('chart_view.source_rows', 'Bron rijen:') }}
                            {{ previewMeta.source_row_count || 0 }}
                            {{ t('chart_view.source_separator', '-') }}
                            {{
                                t(
                                    'chart_view.shown_groups',
                                    'Getoonde groepen:',
                                )
                            }}
                            {{ previewMeta.sample_count || 0 }}
                            <span v-if="previewMeta.truncated">
                                {{ t('chart_view.truncated', '(ingekort)') }}
                            </span>
                        </p>

                        <div
                            v-if="!hasRenderableConfig"
                            class="rounded-md border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-600"
                        >
                            {{
                                t(
                                    'chart_view.invalid_config',
                                    'Geen geldige grafiekconfiguratie gevonden. Kies minimaal een X-veld en aggregatie in de query builder.',
                                )
                            }}
                        </div>

                        <div
                            v-else-if="!previewHasData"
                            class="rounded-md border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-600"
                        >
                            {{
                                t(
                                    'chart_view.no_data',
                                    'Geen gegevens beschikbaar voor deze grafiek.',
                                )
                            }}
                        </div>

                        <div
                            v-else
                            class="rounded-md border border-slate-200 bg-white p-2"
                        >
                            <div
                                ref="echartContainer"
                                class="rw-echart-canvas"
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </RwFormTemplate>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RwActionButton from '@/Components/RwActionButton.vue';
import RwAutoCompleteInput from '@/Components/RwAutoCompleteInput.vue';
import RwFlashMessage from '@/Components/RwFlashMessage.vue';
import RwFormTemplate from '@/Components/RwFormTemplate.vue';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { Head, router } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';

const props = defineProps({
    query: { type: Object, required: true },
    returnTo: { type: String, default: '/admin/run' },
    tablePreviewUrl: { type: String, required: true },
    bindings: { type: Object, default: () => ({}) },
    initialPreview: { type: Object, default: null },
    initialPreviewMeta: { type: Object, default: null },
    initialPreviewError: { type: String, default: '' },
});

const { t } = useAdminTranslations('query_builder_ui');

const chartTypeOptions = computed(() => [
    { value: 'bar', label: t('chart_view.types.bar', 'Staaf') },
    { value: 'line', label: t('chart_view.types.line', 'Lijn') },
    { value: 'bar3d', label: t('chart_view.types.bar3d', 'Staaf 3D') },
    { value: 'line3d', label: t('chart_view.types.line3d', 'Lijn 3D') },
    {
        value: 'bar3d_webgl',
        label: t('chart_view.types.bar3d_webgl', 'Staaf 3D (WebGL)'),
    },
    {
        value: 'line3d_webgl',
        label: t('chart_view.types.line3d_webgl', 'Lijn 3D (WebGL)'),
    },
    { value: 'pie', label: t('chart_view.types.pie', 'Taart') },
    { value: 'doughnut', label: t('chart_view.types.doughnut', 'Donut') },
]);

const palette = [
    '#1d4ed8',
    '#047857',
    '#b45309',
    '#6d28d9',
    '#be123c',
    '#0f766e',
    '#334155',
    '#0ea5e9',
];

const selectedChartType = ref('');
const previewLoading = ref(false);
const pdfLoading = ref(false);
const previewError = ref(String(props.initialPreviewError || ''));
const previewData = ref(
    props.initialPreview && typeof props.initialPreview === 'object'
        ? props.initialPreview
        : null,
);
const previewMeta = ref(
    props.initialPreviewMeta && typeof props.initialPreviewMeta === 'object'
        ? props.initialPreviewMeta
        : null,
);
const echartContainer = ref(null);

let echartsModule = null;
let html2pdfModule = null;
let echartInstance = null;

const normalizedConfig = computed(() => {
    const config =
        props.query?.chart_config &&
        typeof props.query.chart_config === 'object'
            ? props.query.chart_config
            : {};
    const builder =
        config.builder && typeof config.builder === 'object'
            ? config.builder
            : {};
    const dataset =
        builder.dataset && typeof builder.dataset === 'object'
            ? builder.dataset
            : {};
    const chart =
        builder.chart && typeof builder.chart === 'object' ? builder.chart : {};
    const presentation =
        builder.presentation && typeof builder.presentation === 'object'
            ? builder.presentation
            : {};

    const limit = Number(dataset.limit ?? 25);

    return {
        chart_type: String(chart.type || 'bar'),
        orientation: String(chart.orientation || 'vertical'),
        stacked: Boolean(chart.stacked),
        show_legend: chart.show_legend !== false,
        x_field: String(dataset.x_field || '').trim(),
        metric_field: String(dataset.metric_field || '').trim(),
        aggregate: String(dataset.aggregate || 'count'),
        series_field: String(dataset.series_field || '').trim(),
        sort_direction: String(dataset.sort_direction || 'desc'),
        limit: Number.isFinite(limit) && limit > 0 ? Math.min(limit, 500) : 25,
        title: String(presentation.title || '').trim(),
        subtitle: String(presentation.subtitle || '').trim(),
        show_source_table_button:
            presentation.show_source_table_button !== false,
        allow_chart_type_change: presentation.allow_chart_type_change !== false,
        show_pdf_print_button: Boolean(presentation.show_pdf_print_button),
    };
});

const toolbarTitle = computed(() => {
    const description = String(props.query?.description || '').trim();

    return description
        ? t('chart_view.title_prefix', 'Grafiek - :description', {
              description,
          })
        : t('chart_view.title', 'Grafiek');
});

const chartTitle = computed(() => normalizedConfig.value.title);
const chartSubtitle = computed(() => normalizedConfig.value.subtitle);
const allowChartTypeChange = computed(
    () => normalizedConfig.value.allow_chart_type_change,
);
const showSourceTableButton = computed(
    () => normalizedConfig.value.show_source_table_button,
);
const showPdfPrintButton = computed(
    () => normalizedConfig.value.show_pdf_print_button,
);

const effectiveChartType = computed(() => {
    const preferred = String(selectedChartType.value || '').trim();

    if (preferred !== '') {
        return preferred;
    }

    return String(normalizedConfig.value.chart_type || 'bar');
});

const hasRenderableConfig = computed(() => {
    if (!normalizedConfig.value.x_field) {
        return false;
    }

    if (normalizedConfig.value.aggregate === 'count') {
        return true;
    }

    return normalizedConfig.value.metric_field !== '';
});

const previewHasData = computed(() => {
    const labels = Array.isArray(previewData.value?.labels)
        ? previewData.value.labels
        : [];
    const series = Array.isArray(previewData.value?.series)
        ? previewData.value.series
        : [];

    return labels.length > 0 && series.length > 0;
});

function normalizePath(rawPath) {
    if (!rawPath || typeof rawPath !== 'string') {
        return '/admin/run';
    }

    const trimmed = rawPath.trim();

    if (trimmed === '') {
        return '/admin/run';
    }

    try {
        const url = new URL(trimmed, window.location.origin);

        if (url.origin !== window.location.origin) {
            return '/admin/run';
        }

        return `${url.pathname}${url.search}`;
    } catch {
        return trimmed.startsWith('/') ? trimmed : '/admin/run';
    }
}

function goBack() {
    router.visit(normalizePath(props.returnTo));
}

function openSourceTable() {
    window.location.href = props.tablePreviewUrl;
}

function buildPdfFilename() {
    const description = String(props.query?.description || '')
        .toLowerCase()
        .trim();
    const slug = description
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

    return `${slug || t('chart_view.pdf_filename', 'grafiek')}.pdf`;
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

async function downloadPdf() {
    if (!echartInstance) {
        return;
    }

    pdfLoading.value = true;
    let exportElement = null;

    try {
        await nextTick();
        echartInstance.resize();

        const chartImageUrl = echartInstance.getDataURL({
            type: 'png',
            pixelRatio: 2,
            backgroundColor: '#ffffff',
        });

        exportElement = document.createElement('div');
        exportElement.className = 'rw-chart-pdf-export';

        const titleMarkup = chartTitle.value
            ? `<div style="font-size:22px;font-weight:700;color:#0f172a;line-height:1.35;margin-bottom:4px;">${escapeHtml(chartTitle.value)}</div>`
            : '';
        const subtitleMarkup = chartSubtitle.value
            ? `<div style="font-size:14px;color:#64748b;line-height:1.4;margin-bottom:18px;">${escapeHtml(chartSubtitle.value)}</div>`
            : '';

        exportElement.innerHTML = `
            <div style="width:1080px;background:#ffffff;padding:24px 28px 18px;box-sizing:border-box;font-family:Arial,sans-serif;">
                ${titleMarkup}
                ${subtitleMarkup}
                <div style="width:100%;display:flex;justify-content:center;align-items:center;overflow:hidden;">
                    <img src="${chartImageUrl}" alt="${escapeHtml(t('chart_view.pdf_alt', 'Grafiek export'))}" style="display:block;width:100%;height:auto;object-fit:contain;" />
                </div>
            </div>
        `;

        document.body.appendChild(exportElement);

        const html2pdf = await ensureHtml2pdfModule();

        await html2pdf()
            .set({
                margin: [10, 10, 10, 10],
                filename: buildPdfFilename(),
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: {
                    scale: 2,
                    useCORS: true,
                    backgroundColor: '#ffffff',
                },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' },
                pagebreak: { mode: ['css', 'legacy'] },
            })
            .from(exportElement)
            .save();
    } finally {
        if (exportElement) {
            exportElement.remove();
        }

        pdfLoading.value = false;
    }
}

async function ensureHtml2pdfModule() {
    if (html2pdfModule) {
        return html2pdfModule;
    }

    const resolvedModule = await import('html2pdf.js');
    html2pdfModule = resolvedModule.default || resolvedModule;

    return html2pdfModule;
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

    return echartsModule;
}

function browserSupportsWebGl() {
    try {
        const canvas = document.createElement('canvas');

        return Boolean(
            canvas.getContext('webgl2') ||
            canvas.getContext('webgl') ||
            canvas.getContext('experimental-webgl'),
        );
    } catch {
        return false;
    }
}

function disposeEchart() {
    if (echartInstance) {
        echartInstance.dispose();
        echartInstance = null;
    }
}

function createPieOption() {
    const labels = Array.isArray(previewData.value?.labels)
        ? previewData.value.labels
        : [];
    const series = Array.isArray(previewData.value?.series)
        ? previewData.value.series
        : [];

    const data = labels.map((label, index) => {
        const value = series.reduce((carry, serie) => {
            const serieValue = Array.isArray(serie.data)
                ? Number(serie.data[index] || 0)
                : 0;

            return carry + (Number.isFinite(serieValue) ? serieValue : 0);
        }, 0);

        return { name: String(label), value };
    });

    const isDoughnut = effectiveChartType.value === 'doughnut';

    return {
        color: palette,
        tooltip: { trigger: 'item' },
        legend: {
            show: normalizedConfig.value.show_legend,
            bottom: 0,
        },
        series: [
            {
                type: 'pie',
                radius: isDoughnut ? ['45%', '72%'] : ['0%', '72%'],
                data,
                label: { formatter: '{b}: {d}%' },
            },
        ],
    };
}

function createCartesianOption() {
    const labels = Array.isArray(previewData.value?.labels)
        ? previewData.value.labels
        : [];
    const series = Array.isArray(previewData.value?.series)
        ? previewData.value.series
        : [];
    const isHorizontal = normalizedConfig.value.orientation === 'horizontal';
    const baseType = effectiveChartType.value === 'line' ? 'line' : 'bar';
    const chartType = isHorizontal && baseType === 'line' ? 'bar' : baseType;

    return {
        color: palette,
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
        },
        legend: {
            show: normalizedConfig.value.show_legend,
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
            name: String(serie.name || 'Totaal'),
            type: chartType,
            stack: normalizedConfig.value.stacked ? 'total' : null,
            smooth: chartType === 'line',
            data: Array.isArray(serie.data)
                ? serie.data.map((item) => Number(item || 0))
                : [],
        })),
    };
}

function createThreeDimensionalOption() {
    const labels = Array.isArray(previewData.value?.labels)
        ? previewData.value.labels
        : [];
    const series = Array.isArray(previewData.value?.series)
        ? previewData.value.series
        : [];
    const echarts = echartsModule;
    const gradientColor = (paletteIndex) => {
        if (!echarts?.graphic?.LinearGradient) {
            return palette[paletteIndex % palette.length];
        }

        const base = palette[paletteIndex % palette.length];

        return new echarts.graphic.LinearGradient(0, 0, 0, 1, [
            { offset: 0, color: base },
            { offset: 0.6, color: base },
            { offset: 1, color: '#0f172a' },
        ]);
    };

    const isHorizontal = normalizedConfig.value.orientation === 'horizontal';
    const chartType = String(effectiveChartType.value || 'bar3d');

    if (chartType === 'line3d') {
        return {
            color: palette,
            tooltip: { trigger: 'item' },
            legend: {
                show: normalizedConfig.value.show_legend,
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
            series: series.map((serie, index) => ({
                name: String(serie.name || `Reeks ${index + 1}`),
                type: 'line',
                smooth: true,
                symbolSize: 10,
                lineStyle: {
                    width: 4,
                    shadowBlur: 14,
                    shadowOffsetY: 8,
                    shadowColor: 'rgba(15, 23, 42, 0.35)',
                },
                itemStyle: {
                    color: palette[index % palette.length],
                },
                areaStyle: {
                    opacity: 0.14,
                    color: gradientColor(index),
                },
                data: Array.isArray(serie.data)
                    ? serie.data.map((item) => Number(item || 0))
                    : [],
            })),
        };
    }

    return {
        color: palette,
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
        },
        legend: {
            show: normalizedConfig.value.show_legend,
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
        series: series.map((serie, index) => ({
            name: String(serie.name || `Reeks ${index + 1}`),
            type: 'bar',
            data: Array.isArray(serie.data)
                ? serie.data.map((item) => Number(item || 0))
                : [],
            itemStyle: {
                color: gradientColor(index),
                borderColor: '#0f172a',
                borderWidth: 0.6,
                shadowBlur: 10,
                shadowOffsetY: 8,
                shadowColor: 'rgba(15, 23, 42, 0.35)',
            },
            barMinHeight: 4,
            barMaxWidth: 48,
            stack: normalizedConfig.value.stacked ? 'total' : null,
        })),
    };
}

function createWebGlThreeDimensionalOption() {
    const labels = Array.isArray(previewData.value?.labels)
        ? previewData.value.labels
        : [];
    const series = Array.isArray(previewData.value?.series)
        ? previewData.value.series
        : [];
    const chartType = String(effectiveChartType.value || 'bar3d_webgl');
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

    if (chartType === 'line3d_webgl') {
        return {
            tooltip: {},
            legend: {
                show: normalizedConfig.value.show_legend,
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
                light: {
                    main: {
                        intensity: 1.2,
                        shadow: true,
                    },
                    ambient: {
                        intensity: 0.45,
                    },
                },
                viewControl: {
                    alpha: 22,
                    beta: 38,
                    distance: 220,
                },
            },
            series: series.map((serie, index) => ({
                name: String(serie.name || `Reeks ${index + 1}`),
                type: 'line3D',
                lineStyle: {
                    width: 4,
                },
                data: (Array.isArray(serie.data) ? serie.data : []).map(
                    (value, valueIndex) => [
                        valueIndex,
                        index,
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
            light: {
                main: {
                    intensity: 1.2,
                    shadow: true,
                },
                ambient: {
                    intensity: 0.45,
                },
            },
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

function buildChartOption() {
    if (
        effectiveChartType.value === 'pie' ||
        effectiveChartType.value === 'doughnut'
    ) {
        return createPieOption();
    }

    if (
        effectiveChartType.value === 'bar3d_webgl' ||
        effectiveChartType.value === 'line3d_webgl'
    ) {
        return createWebGlThreeDimensionalOption();
    }

    if (
        effectiveChartType.value === 'bar3d' ||
        effectiveChartType.value === 'line3d'
    ) {
        return createThreeDimensionalOption();
    }

    return createCartesianOption();
}

async function renderEchart() {
    if (!previewHasData.value || !echartContainer.value) {
        disposeEchart();

        return;
    }

    await nextTick();
    const echarts = await ensureEchartsModule();

    if (!echartContainer.value) {
        return;
    }

    const chartType = String(effectiveChartType.value || 'bar');
    if (
        (chartType === 'bar3d_webgl' || chartType === 'line3d_webgl') &&
        !browserSupportsWebGl()
    ) {
        previewError.value = t(
            'chart_view.webgl_unsupported',
            'WebGL wordt niet ondersteund door deze browser of GPU. Kies een niet-WebGL grafiektype.',
        );
        disposeEchart();

        return;
    }

    if (!echartInstance) {
        echartInstance = echarts.init(echartContainer.value);
    }

    try {
        echartInstance.setOption(buildChartOption(), true);
    } catch {
        previewError.value = t(
            'chart_view.render_failed',
            'Kon de grafiek niet renderen met de huidige configuratie.',
        );
    }
}

function resizeEchart() {
    if (echartInstance) {
        echartInstance.resize();
    }
}

async function refreshPreview() {
    previewError.value = '';

    if (!hasRenderableConfig.value) {
        previewData.value = null;
        previewMeta.value = null;

        return;
    }

    previewLoading.value = true;

    try {
        const response = await window.axios.post(
            route('admin.run.queries.chart.preview', {
                query: props.query.id,
            }),
            {
                config: props.query?.chart_config || {},
                bindings: props.bindings || {},
            },
        );

        previewData.value =
            response?.data?.preview && typeof response.data.preview === 'object'
                ? response.data.preview
                : null;
        previewMeta.value =
            response?.data?.meta && typeof response.data.meta === 'object'
                ? response.data.meta
                : null;

        await renderEchart();
    } catch (error) {
        previewData.value = null;
        previewMeta.value = null;
        previewError.value =
            String(error?.response?.data?.message || '').trim() ||
            t(
                'chart_view.preview_failed',
                'Kon de grafiek-preview niet laden.',
            );
    } finally {
        previewLoading.value = false;
    }
}

watch(
    () => normalizedConfig.value,
    (config) => {
        selectedChartType.value = String(config.chart_type || 'bar');
        renderEchart();
    },
    { deep: true },
);

watch(selectedChartType, () => {
    renderEchart();
});

watch(
    previewData,
    () => {
        renderEchart();
    },
    { deep: true },
);

onMounted(() => {
    selectedChartType.value = String(
        normalizedConfig.value.chart_type || 'bar',
    );
    window.addEventListener('resize', resizeEchart);
    renderEchart();

    if (!previewData.value && hasRenderableConfig.value) {
        refreshPreview();
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', resizeEchart);
    disposeEchart();
});
</script>

<style scoped>
.rw-echart-canvas {
    width: 100%;
    height: 70vh;
    min-height: 520px;
}

@media (max-width: 960px) {
    .rw-echart-canvas {
        height: 58vh;
        min-height: 420px;
    }
}
</style>
