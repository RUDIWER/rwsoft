<template>
    <section class="grid gap-5">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-slate-900">
                    {{ t('statistics.title', 'Statistics') }}
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{
                        t(
                            'statistics.description',
                            'Review visitor statistics and Search Console visibility for this CMS record.',
                        )
                    }}
                </p>
            </div>

            <div class="flex flex-wrap items-end gap-2">
                <div class="grid gap-1">
                    <Label for="statistics_from" class="text-xs text-slate-600">
                        {{ t('statistics.from', 'From') }}
                    </Label>
                    <Input id="statistics_from" v-model="from" type="date" />
                </div>
                <div class="grid gap-1">
                    <Label for="statistics_to" class="text-xs text-slate-600">
                        {{ t('statistics.to', 'To') }}
                    </Label>
                    <Input id="statistics_to" v-model="to" type="date" />
                </div>
                <Button
                    type="button"
                    variant="outline"
                    class="gap-2 shadow-none"
                    :disabled="loading"
                    @click="loadStatistics"
                >
                    <span
                        :class="[
                            'mdi text-base',
                            loading
                                ? 'mdi-loading animate-spin'
                                : 'mdi-refresh',
                        ]"
                        aria-hidden="true"
                    />
                    {{ t('statistics.refresh', 'Refresh') }}
                </Button>
            </div>
        </div>

        <div
            v-if="!recordId"
            class="rounded-md border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600"
        >
            {{
                t(
                    'statistics.save_first',
                    'Save this record before statistics can be loaded.',
                )
            }}
        </div>

        <div v-else class="grid gap-5">
            <div
                v-if="visitErrorMessage"
                class="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800"
            >
                {{ visitErrorMessage }}
            </div>

            <div class="grid gap-3 md:grid-cols-4">
                <div
                    v-for="card in summaryCards"
                    :key="card.key"
                    class="rounded-md border border-slate-200 bg-white p-4"
                >
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-slate-500"
                    >
                        {{ card.label }}
                    </p>
                    <p class="mt-2 text-2xl font-semibold text-slate-950">
                        {{ card.value }}
                    </p>
                </div>
            </div>

            <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
                <div class="grid gap-5">
                    <section
                        class="rounded-md border border-slate-200 bg-white p-4"
                    >
                        <h3 class="text-sm font-semibold text-slate-900">
                            {{ t('statistics.urls_title', 'Public URLs') }}
                        </h3>
                        <div class="mt-3 grid gap-2">
                            <a
                                v-for="item in urlLabels"
                                :key="item.url"
                                :href="item.url"
                                target="_blank"
                                rel="noreferrer"
                                class="break-all rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-blue-700 hover:bg-blue-50"
                            >
                                {{ item.label }}: {{ item.url }}
                            </a>
                        </div>
                    </section>

                    <section
                        class="rounded-md border border-slate-200 bg-white p-4"
                    >
                        <h3 class="text-sm font-semibold text-slate-900">
                            {{
                                t('statistics.monthly_title', 'Monthly visits')
                            }}
                        </h3>
                        <p class="mt-1 text-sm text-slate-500">
                            {{
                                t(
                                    'statistics.monthly_description',
                                    'Pageviews and unique visitors in the selected period.',
                                )
                            }}
                        </p>

                        <div
                            v-if="visitsLoading"
                            class="mt-4 h-[22rem] animate-pulse rounded-md bg-slate-100"
                        />
                        <div
                            v-else-if="!hasMonthlyData"
                            class="mt-4 rounded-md border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-600"
                        >
                            {{
                                t(
                                    'statistics.no_monthly_data',
                                    'No visits found for this period.',
                                )
                            }}
                        </div>
                        <div
                            v-else
                            ref="visitChartElement"
                            class="mt-4 h-[22rem] w-full"
                        />

                        <div class="mt-4 grid gap-2">
                            <div
                                v-for="row in monthlyRows"
                                :key="row.month"
                                class="grid grid-cols-[7rem_minmax(0,1fr)_5rem] items-center gap-3 text-sm"
                            >
                                <span class="font-medium text-slate-700">{{
                                    row.month
                                }}</span>
                                <div
                                    class="h-2 overflow-hidden rounded-full bg-slate-100"
                                >
                                    <div
                                        class="h-full rounded-full bg-blue-600"
                                        :style="{
                                            width: monthlyWidth(row.pageviews),
                                        }"
                                    />
                                </div>
                                <span class="text-right text-slate-600">{{
                                    formatNumber(row.pageviews)
                                }}</span>
                            </div>
                        </div>
                    </section>

                    <section
                        class="rounded-md border border-slate-200 bg-white p-4"
                    >
                        <h3 class="text-sm font-semibold text-slate-900">
                            {{
                                t(
                                    'statistics.search_console_queries',
                                    'Search queries',
                                )
                            }}
                        </h3>
                        <div class="mt-3 overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="text-xs uppercase text-slate-500">
                                    <tr>
                                        <th class="py-2 pr-3">
                                            {{ t('statistics.query', 'Query') }}
                                        </th>
                                        <th class="py-2 pr-3 text-right">
                                            {{
                                                t('statistics.clicks', 'Clicks')
                                            }}
                                        </th>
                                        <th class="py-2 pr-3 text-right">
                                            {{
                                                t(
                                                    'statistics.impressions',
                                                    'Impressions',
                                                )
                                            }}
                                        </th>
                                        <th class="py-2 text-right">
                                            {{
                                                t(
                                                    'statistics.position',
                                                    'Position',
                                                )
                                            }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="query in queryRows"
                                        :key="query.query"
                                        class="border-t border-slate-100"
                                    >
                                        <td class="py-2 pr-3 text-slate-800">
                                            {{ query.query }}
                                        </td>
                                        <td
                                            class="py-2 pr-3 text-right text-slate-600"
                                        >
                                            {{ formatNumber(query.clicks) }}
                                        </td>
                                        <td
                                            class="py-2 pr-3 text-right text-slate-600"
                                        >
                                            {{
                                                formatNumber(query.impressions)
                                            }}
                                        </td>
                                        <td
                                            class="py-2 text-right text-slate-600"
                                        >
                                            {{ formatPosition(query.position) }}
                                        </td>
                                    </tr>
                                    <tr v-if="queryRows.length === 0">
                                        <td
                                            colspan="4"
                                            class="py-3 text-slate-500"
                                        >
                                            {{
                                                t(
                                                    'statistics.no_queries',
                                                    'No Search Console query data found for this period.',
                                                )
                                            }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>

                <div class="grid content-start gap-5">
                    <section
                        class="rounded-md border border-slate-200 bg-white p-4"
                    >
                        <h3 class="text-sm font-semibold text-slate-900">
                            {{
                                t(
                                    'statistics.search_console_title',
                                    'Google Search Console',
                                )
                            }}
                        </h3>
                        <p
                            v-if="searchConsolePanelMessage"
                            class="mt-2 text-sm text-orange-700"
                        >
                            {{ searchConsolePanelMessage }}
                        </p>
                        <dl class="mt-3 grid gap-2 text-sm">
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">
                                    {{ t('statistics.clicks', 'Clicks') }}
                                </dt>
                                <dd class="font-semibold text-slate-900">
                                    {{ formatNumber(searchSummary.clicks) }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">
                                    {{
                                        t(
                                            'statistics.impressions',
                                            'Impressions',
                                        )
                                    }}
                                </dt>
                                <dd class="font-semibold text-slate-900">
                                    {{
                                        formatNumber(searchSummary.impressions)
                                    }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">
                                    {{ t('statistics.ctr', 'CTR') }}
                                </dt>
                                <dd class="font-semibold text-slate-900">
                                    {{ formatPercent(searchSummary.ctr) }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">
                                    {{ t('statistics.position', 'Position') }}
                                </dt>
                                <dd class="font-semibold text-slate-900">
                                    {{ formatPosition(searchSummary.position) }}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section
                        class="rounded-md border border-slate-200 bg-white p-4"
                    >
                        <h3 class="text-sm font-semibold text-slate-900">
                            {{ t('statistics.indexing_title', 'Indexing') }}
                        </h3>
                        <div class="mt-3 grid gap-2">
                            <div
                                v-for="inspection in inspectionRows"
                                :key="inspection.url"
                                class="rounded border border-slate-200 bg-slate-50 p-3 text-sm"
                            >
                                <div
                                    class="flex items-center justify-between gap-2"
                                >
                                    <span class="font-medium text-slate-900">{{
                                        indexStatusLabel(inspection.status)
                                    }}</span>
                                    <a
                                        v-if="inspection.inspectionResultLink"
                                        :href="inspection.inspectionResultLink"
                                        target="_blank"
                                        rel="noreferrer"
                                        class="text-blue-700 hover:underline"
                                    >
                                        {{
                                            t(
                                                'statistics.open_google_result',
                                                'Open',
                                            )
                                        }}
                                    </a>
                                </div>
                                <p
                                    class="mt-1 break-all text-xs text-slate-500"
                                >
                                    {{ inspection.url }}
                                </p>
                                <p
                                    v-if="inspection.coverageState"
                                    class="mt-1 text-xs text-slate-600"
                                >
                                    {{ inspection.coverageState }}
                                </p>
                            </div>
                            <p
                                v-if="inspectionRows.length === 0"
                                class="text-sm text-slate-500"
                            >
                                {{
                                    t(
                                        'statistics.no_indexing_data',
                                        'No indexing data loaded.',
                                    )
                                }}
                            </p>
                        </div>
                    </section>

                    <section
                        class="rounded-md border border-slate-200 bg-white p-4"
                    >
                        <h3 class="text-sm font-semibold text-slate-900">
                            {{
                                t(
                                    'statistics.referrers_title',
                                    'External referrers',
                                )
                            }}
                        </h3>
                        <div class="mt-3 grid gap-2 text-sm">
                            <div
                                v-for="row in referrerRows"
                                :key="row.host"
                                class="flex justify-between gap-3"
                            >
                                <span class="truncate text-slate-700">{{
                                    row.host
                                }}</span>
                                <span class="font-semibold text-slate-900">{{
                                    formatNumber(row.visits)
                                }}</span>
                            </div>
                            <p
                                v-if="referrerRows.length === 0"
                                class="text-slate-500"
                            >
                                {{
                                    t(
                                        'statistics.no_referrers',
                                        'No external referrers found.',
                                    )
                                }}
                            </p>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';

const props = defineProps({
    contentType: {
        type: String,
        required: true,
    },
    recordId: {
        type: [Number, String],
        default: null,
    },
});

const { t } = useAdminTranslations('cms_admin_ui');
const today = new Date();
const fromDate = new Date(today);
fromDate.setFullYear(today.getFullYear() - 1);

const from = ref(dateInputValue(fromDate));
const to = ref(dateInputValue(today));
const visitsLoading = ref(false);
const searchConsoleLoading = ref(false);
const visitErrorMessage = ref('');
const searchConsoleErrorMessage = ref('');
const urls = ref({ labels: [] });
const visits = ref(null);
const searchConsole = ref(null);
const visitChartElement = ref(null);

let echartsModule = null;
let visitChartInstance = null;

const loading = computed(
    () => visitsLoading.value || searchConsoleLoading.value,
);

const visitSummary = computed(() => visits.value?.summary ?? {});
const searchSummary = computed(
    () =>
        searchConsole.value?.summary ?? {
            clicks: 0,
            impressions: 0,
            ctr: 0,
            position: null,
        },
);
const urlLabels = computed(() =>
    Array.isArray(urls.value?.labels) ? urls.value.labels : [],
);
const monthlyRows = computed(() =>
    Array.isArray(visits.value?.monthly) ? visits.value.monthly : [],
);
const referrerRows = computed(() =>
    Array.isArray(visits.value?.referrers) ? visits.value.referrers : [],
);
const queryRows = computed(() =>
    Array.isArray(searchConsole.value?.queries)
        ? searchConsole.value.queries
        : [],
);
const inspectionRows = computed(() =>
    Array.isArray(searchConsole.value?.inspections)
        ? searchConsole.value.inspections
        : [],
);
const searchConsoleMessage = computed(() => searchConsole.value?.message || '');
const searchConsolePanelMessage = computed(
    () => searchConsoleErrorMessage.value || searchConsoleMessage.value,
);
const maxMonthlyPageviews = computed(() =>
    Math.max(1, ...monthlyRows.value.map((row) => Number(row.pageviews || 0))),
);
const hasMonthlyData = computed(() =>
    monthlyRows.value.some(
        (row) =>
            Number(row.pageviews || 0) > 0 ||
            Number(row.uniqueVisitors || 0) > 0,
    ),
);
const summaryCards = computed(() => [
    {
        key: 'pageviews',
        label: t('statistics.pageviews', 'Pageviews'),
        value: formatNumber(visitSummary.value.pageviews),
    },
    {
        key: 'unique_visitors',
        label: t('statistics.unique_visitors', 'Unique visitors'),
        value: formatNumber(visitSummary.value.uniqueVisitors),
    },
    {
        key: 'external_referrers',
        label: t('statistics.external_referrers', 'External referrers'),
        value: formatNumber(visitSummary.value.externalReferrerCount),
    },
    {
        key: 'campaign_visits',
        label: t('statistics.campaign_visits', 'Campaign visits'),
        value: formatNumber(visitSummary.value.campaignVisitCount),
    },
]);

onMounted(() => {
    window.addEventListener('resize', resizeVisitChart);

    if (props.recordId) {
        loadStatistics();
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', resizeVisitChart);
    disposeVisitChart();
});

watch(
    monthlyRows,
    () => {
        renderVisitChart();
    },
    { deep: true },
);

async function loadStatistics() {
    if (!props.recordId) {
        return;
    }

    visitErrorMessage.value = '';
    searchConsoleErrorMessage.value = '';

    await Promise.allSettled([
        loadVisitStatistics(),
        loadSearchConsoleStatistics(),
    ]);
}

async function loadVisitStatistics() {
    visitsLoading.value = true;

    try {
        const response = await fetch(
            statisticsUrl('admin.cms.statistics.visits'),
            {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            },
        );

        if (!response.ok) {
            throw new Error(endpointErrorMessage('visits', response.status));
        }

        const payload = await response.json();

        urls.value = payload.urls ?? { labels: [] };
        visits.value = payload.statistics ?? null;
    } catch (error) {
        visits.value = null;
        visitErrorMessage.value =
            error?.message ||
            t(
                'statistics.visits_load_failed',
                'Visitor statistics could not be loaded.',
            );
    } finally {
        visitsLoading.value = false;
        renderVisitChart();
    }
}

async function loadSearchConsoleStatistics() {
    searchConsoleLoading.value = true;

    try {
        const response = await fetch(
            statisticsUrl('admin.cms.statistics.search-console'),
            {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            },
        );

        if (!response.ok) {
            throw new Error(
                endpointErrorMessage('search_console', response.status),
            );
        }

        const payload = await response.json();

        urls.value = urls.value?.labels?.length
            ? urls.value
            : (payload.urls ?? { labels: [] });
        searchConsole.value = payload.searchConsole ?? null;
    } catch (error) {
        searchConsole.value = null;
        searchConsoleErrorMessage.value =
            error?.message ||
            t(
                'statistics.search_console_load_failed',
                'Search Console statistics could not be loaded.',
            );
    } finally {
        searchConsoleLoading.value = false;
    }
}

function endpointErrorMessage(type, status) {
    const fallback =
        type === 'visits'
            ? t(
                  'statistics.visits_load_failed',
                  'Visitor statistics could not be loaded.',
              )
            : t(
                  'statistics.search_console_load_failed',
                  'Search Console statistics could not be loaded.',
              );

    return t(
        'statistics.endpoint_load_failed',
        ':label Request failed with status :status.',
        {
            label: fallback,
            status,
        },
    );
}

function statisticsUrl(routeName) {
    const url = new URL(route(routeName), window.location.origin);
    url.searchParams.set('content_type', props.contentType);
    url.searchParams.set('record_id', String(props.recordId));
    url.searchParams.set('from', from.value);
    url.searchParams.set('to', to.value);

    return url.toString();
}

function monthlyWidth(pageviews) {
    return `${Math.round((Number(pageviews || 0) / maxMonthlyPageviews.value) * 100)}%`;
}

async function ensureEchartsModule() {
    if (echartsModule) {
        return echartsModule;
    }

    echartsModule = await import('echarts');

    return echartsModule;
}

async function renderVisitChart() {
    if (!visitChartElement.value || !hasMonthlyData.value) {
        disposeVisitChart();

        return;
    }

    await nextTick();
    const echarts = await ensureEchartsModule();

    if (!visitChartElement.value) {
        return;
    }

    if (!visitChartInstance) {
        visitChartInstance = echarts.init(visitChartElement.value);
    }

    visitChartInstance.setOption(visitChartOption(), true);
}

function visitChartOption() {
    const labels = monthlyRows.value.map((row) => row.month);

    return {
        color: ['#2563eb', '#059669'],
        tooltip: {
            trigger: 'axis',
            valueFormatter: (value) => formatNumber(value),
        },
        legend: {
            top: 0,
            textStyle: { color: '#475569' },
        },
        grid: {
            top: 44,
            left: 16,
            right: 16,
            bottom: 24,
            containLabel: true,
        },
        xAxis: {
            type: 'category',
            boundaryGap: false,
            data: labels,
            axisLabel: { color: '#64748b' },
            axisLine: { lineStyle: { color: '#cbd5e1' } },
        },
        yAxis: {
            type: 'value',
            minInterval: 1,
            axisLabel: { color: '#64748b' },
            splitLine: { lineStyle: { color: '#e2e8f0' } },
        },
        series: [
            {
                name: t('statistics.chart.pageviews', 'Pageviews'),
                type: 'line',
                smooth: true,
                symbolSize: 7,
                lineStyle: { width: 3 },
                areaStyle: { opacity: 0.12 },
                data: monthlyRows.value.map((row) =>
                    Number(row.pageviews || 0),
                ),
            },
            {
                name: t('statistics.chart.unique_visitors', 'Unique visitors'),
                type: 'line',
                smooth: true,
                symbolSize: 7,
                lineStyle: { width: 3 },
                areaStyle: { opacity: 0.08 },
                data: monthlyRows.value.map((row) =>
                    Number(row.uniqueVisitors || 0),
                ),
            },
        ],
    };
}

function resizeVisitChart() {
    if (visitChartInstance) {
        visitChartInstance.resize();
    }
}

function disposeVisitChart() {
    if (visitChartInstance) {
        visitChartInstance.dispose();
        visitChartInstance = null;
    }
}

function formatNumber(value) {
    return Number(value || 0).toLocaleString();
}

function formatPercent(value) {
    return `${(Number(value || 0) * 100).toFixed(1)}%`;
}

function formatPosition(value) {
    return value === null || value === undefined
        ? '-'
        : Number(value).toFixed(1);
}

function indexStatusLabel(status) {
    const labels = {
        indexed: t('statistics.index_status.indexed', 'Indexed'),
        not_indexed: t('statistics.index_status.not_indexed', 'Not indexed'),
        excluded: t('statistics.index_status.excluded', 'Excluded'),
        noindex: t('statistics.index_status.noindex', 'Noindex'),
        unknown: t('statistics.index_status.unknown', 'Unknown'),
    };

    return labels[status] || labels.unknown;
}

function dateInputValue(date) {
    return [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-');
}
</script>
