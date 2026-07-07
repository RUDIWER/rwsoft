<template>
    <AdminLayout :suppress-flash="true">
        <Head :title="t('meta.page_title', 'Query Builder')" />

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
                            <span class="mdi mdi-database-search text-2xl" />
                        </div>
                        <div class="min-w-0">
                            <CardTitle class="text-lg">
                                {{ t('page.title', 'Query Builder') }}
                            </CardTitle>
                            <CardDescription class="mt-1">
                                {{
                                    t(
                                        'page.subtitle',
                                        'Manage SQL queries for tables, reports, exports, and charts.',
                                    )
                                }}
                            </CardDescription>
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2">
                        <Button
                            as-child
                            variant="outline"
                            size="icon"
                            class="text-slate-950 shadow-none hover:bg-slate-50 hover:text-slate-950"
                        >
                            <Link
                                :href="route('admin')"
                                :aria-label="commonT('actions.back', 'Back')"
                                :title="commonT('actions.back', 'Back')"
                            >
                                <span
                                    class="mdi mdi-arrow-left-circle text-lg"
                                />
                            </Link>
                        </Button>

                        <Button
                            as-child
                            variant="outline"
                            class="border-blue-200 text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800"
                        >
                            <Link
                                :href="route('admin.queries.builder.create')"
                                class="gap-2"
                            >
                                <span
                                    class="mdi mdi-plus-circle text-base text-blue-700"
                                />
                                {{ commonT('actions.new', 'New') }}
                            </Link>
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

            <CardContent class="p-0">
                <RwTable
                    table-id="admin-query-builder-table-v2"
                    :data="tableData"
                    :columns="columns"
                    :rows-per-page="25"
                    sort-field="id"
                    sort-order="desc"
                    :row-options="[25, 50, 100, 250]"
                    :cell-class="cellClass"
                    excel="true"
                    @on-cell-click="onCellClick"
                />
            </CardContent>
        </Card>
    </AdminLayout>
</template>

<script setup>
import RwFlashMessage from '@/Components/RwFlashMessage.vue';
import RwTable from '@/Components/RwTable.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    queries: { type: Array, default: () => [] },
});

const page = usePage();
const { t } = useAdminTranslations('query_builder_ui');
const { t: commonT } = useAdminTranslations('admin_common_ui');

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

const outputModeLabels = computed(() => ({
    table: t('form.output_modes.table', 'Table'),
    report: t('form.output_modes.report', 'Report'),
    excel: t('form.output_modes.excel', 'Excel'),
    chart: t('form.output_modes.chart', 'Chart'),
}));

const queryModeLabels = computed(() => ({
    builder: t('form.query_modes.builder', 'Builder'),
    sql: t('form.query_modes.sql', 'SQL editor'),
}));

const tableRows = computed(() =>
    props.queries.map((query) => ({
        ...query,
        query_mode_label:
            queryModeLabels.value[query.query_mode] || query.query_mode || '-',
        output_mode_label:
            outputModeLabels.value[query.output_mode] ||
            query.output_mode ||
            '-',
        active_label: query.is_active
            ? t('status.active', 'Active')
            : t('status.inactive', 'Inactive'),
        active_color: query.is_active ? 'green' : 'red',
        updated_at_display: formatTableDateTime(query.updated_at),
        created_at_display: formatTableDateTime(query.created_at),
    })),
);

const tableData = computed(() => ({
    data: tableRows.value,
    total: tableRows.value.length,
}));

const columns = computed(() => [
    {
        key: 'id',
        label: commonT('columns.id', 'ID'),
        type: 'number',
        selected: true,
        sortable: true,
        filterable: true,
        clickable: true,
        width: 90,
    },
    {
        key: 'description',
        label: t('columns.description', 'Description'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
        minWidth: 260,
    },
    {
        key: 'slug',
        label: t('columns.slug', 'Slug'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
        minWidth: 180,
    },
    {
        key: 'query_mode_label',
        label: t('columns.query_mode', 'Query mode'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
        width: 140,
    },
    {
        key: 'output_mode_label',
        label: t('columns.output_mode', 'Output'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
        width: 140,
    },
    {
        key: 'active_label',
        label: commonT('columns.status', 'Status'),
        type: 'chip',
        colorKey: 'active_color',
        chipOnlyWhenColor: true,
        selected: true,
        sortable: true,
        filterable: true,
        width: 130,
    },
    {
        key: 'updated_at_display',
        label: commonT('columns.updated_at', 'Updated'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
        width: 180,
    },
    {
        key: 'created_at_display',
        label: t('columns.created_at', 'Created'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
        width: 180,
    },
]);

function formatTableDateTime(value) {
    if (!value) {
        return '-';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '-';
    }

    const datePart = new Intl.DateTimeFormat('nl-BE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(date);
    const timePart = new Intl.DateTimeFormat('nl-BE', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).format(date);

    return `${datePart} ${timePart}`;
}

function cellClass({ col }) {
    return col.key === 'id' ? 'cursor-pointer' : null;
}

function onCellClick(field, id) {
    if (field !== 'id') {
        return;
    }

    router.visit(route('admin.queries.builder.edit', { query: id }));
}
</script>
