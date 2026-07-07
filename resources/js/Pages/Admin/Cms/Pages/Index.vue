<template>
    <Head :title="t('pages.page_title', 'CMS pages')" />

    <AdminLayout
        :title="t('pages.page_title', 'CMS pages')"
        :suppress-flash="true"
    >
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
                            <span class="mdi mdi-page-next text-2xl" />
                        </div>
                        <div class="min-w-0">
                            <CardTitle class="text-lg">
                                {{ t('pages.title', 'Pages') }}
                            </CardTitle>
                            <CardDescription class="mt-1">
                                {{
                                    t(
                                        'pages.description',
                                        'Manage fixed pages and publication status.',
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
                                    aria-hidden="true"
                                />
                            </Link>
                        </Button>

                        <Button
                            as-child
                            variant="outline"
                            class="border-blue-200 text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800"
                        >
                            <Link
                                :href="route('admin.cms.pages.create')"
                                class="gap-2"
                            >
                                <span
                                    class="mdi mdi-plus-circle text-base text-blue-700"
                                    aria-hidden="true"
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
                    table-id="admin-cms-pages-table"
                    :data="tableData"
                    :columns="columns"
                    :initial-height="'calc(100vh - 260px)'"
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
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    pages: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const { t } = useAdminTranslations('cms_admin_ui');
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

const statusLabels = computed(() => ({
    draft: t('common.status.draft', 'Draft'),
    published: t('common.status.published', 'Published'),
    archived: t('common.status.archived', 'Archived'),
}));

const tableRows = computed(() =>
    props.pages.map((pageItem) => ({
        ...pageItem,
        status_label:
            statusLabels.value[pageItem.status] ?? pageItem.status ?? '-',
        status_color: statusColor(pageItem.status),
        parent_title: pageItem.parent?.title ?? '-',
        home_label: pageItem.is_home ? t('common.yes', 'Yes') : '',
        home_color: pageItem.is_home ? 'blue' : '',
        published_at_display: formatDate(pageItem.published_at),
        updated_at_display: formatDate(pageItem.updated_at),
    })),
);

const tableData = computed(() => ({
    data: tableRows.value,
    total: tableRows.value.length,
}));

const columns = computed(() => [
    {
        key: 'id',
        label: t('common.columns.id', 'ID'),
        type: 'number',
        selected: true,
        sortable: true,
        filterable: true,
        clickable: true,
        width: 90,
    },
    {
        key: 'title',
        label: t('common.columns.title', 'Title'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
    },
    {
        key: 'slug',
        label: t('common.columns.slug', 'Slug'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
    },
    {
        key: 'locale',
        label: t('common.columns.locale', 'Language'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
        width: 100,
    },
    {
        key: 'status_label',
        label: t('common.columns.status', 'Status'),
        type: 'chip',
        colorKey: 'status_color',
        selected: true,
        sortable: true,
        filterable: true,
    },
    {
        key: 'parent_title',
        label: t('common.columns.parent', 'Parent'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
    },
    {
        key: 'home_label',
        label: t('pages.columns.home', 'Home'),
        type: 'chip',
        colorKey: 'home_color',
        chipOnlyWhenColor: true,
        selected: true,
        sortable: true,
        filterable: true,
        width: 100,
    },
    {
        key: 'published_at_display',
        label: t('common.columns.published_at', 'Publication'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
    },
    {
        key: 'updated_at_display',
        label: t('common.columns.updated_at', 'Updated'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
    },
]);

function statusColor(status) {
    return (
        {
            draft: 'orange',
            published: 'green',
            archived: 'grey',
        }[status] || 'grey'
    );
}

function cellClass({ col }) {
    return col.key === 'id' ? 'cursor-pointer' : null;
}

function formatDate(value) {
    if (!value) {
        return '-';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '-';
    }

    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = String(date.getFullYear());
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${day}/${month}/${year} ${hours}:${minutes}`;
}

function onCellClick(field, id) {
    if (field !== 'id') {
        return;
    }

    router.visit(route('admin.cms.pages.edit', { id }));
}
</script>
