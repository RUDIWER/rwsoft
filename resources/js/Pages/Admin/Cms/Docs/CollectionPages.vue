<template>
    <Head :title="collectionTitle" />

    <AdminLayout :title="collectionTitle" :suppress-flash="true">
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
                            <span
                                class="mdi mdi-book-open-page-variant text-2xl"
                            />
                        </div>
                        <div class="min-w-0">
                            <CardTitle class="text-lg">
                                {{ collectionTitle }}
                            </CardTitle>
                            <CardDescription class="mt-1">
                                {{
                                    collection.description ||
                                    t(
                                        'docs.collection_pages_description',
                                        'Manage pages for this documentation collection.',
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
                                :href="route('admin.cms.docs.index')"
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
                            class="gap-2 border-blue-200 text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800"
                        >
                            <Link
                                :href="route('admin.cms.docs.versions.create')"
                            >
                                <span
                                    class="mdi mdi-source-branch-plus text-base text-blue-700"
                                    aria-hidden="true"
                                />
                                {{ t('docs.actions.new_version', 'Version') }}
                            </Link>
                        </Button>
                        <Button
                            as-child
                            variant="outline"
                            class="gap-2 border-blue-200 text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800"
                        >
                            <Link
                                :href="
                                    route('admin.cms.docs.pages.create', {
                                        collection: collection.id,
                                    })
                                "
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
                <div class="border-b border-slate-200 px-4 py-3 sm:px-5">
                    <div
                        class="grid gap-3 rounded border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700"
                    >
                        <div class="grid gap-2 md:grid-cols-4">
                            <div>
                                <span class="font-semibold">
                                    {{
                                        t(
                                            'docs.collection_label',
                                            'Collection',
                                        )
                                    }}:
                                </span>
                                {{ collection.name }}
                            </div>
                            <div>
                                <span class="font-semibold">
                                    {{ t('docs.versions_count', 'Versions') }}:
                                </span>
                                {{ versions.length }}
                            </div>
                            <div>
                                <span class="font-semibold">
                                    {{ t('docs.pages_count', 'Pages') }}:
                                </span>
                                {{ pages.length }}
                            </div>
                            <div>
                                <span class="font-semibold">
                                    {{ t('docs.selected_count', 'Selected') }}:
                                </span>
                                {{ selectedDocPageIds.length }}
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                class="gap-2 border-green-200 text-green-700 shadow-none hover:bg-green-50 hover:text-green-800"
                                :disabled="
                                    bulkStatusForm.processing ||
                                    selectedDocPageIds.length === 0
                                "
                                @click="submitBulkStatus('publish')"
                            >
                                <span
                                    v-if="
                                        bulkStatusForm.processing &&
                                        bulkStatusAction === 'publish'
                                    "
                                    class="mdi mdi-loading animate-spin text-base"
                                    aria-hidden="true"
                                />
                                <span
                                    v-else
                                    class="mdi mdi-cloud-upload-outline text-base"
                                    aria-hidden="true"
                                />
                                {{ t('docs.actions.publish', 'Publish') }}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                class="gap-2 border-orange-200 text-orange-700 shadow-none hover:bg-orange-50 hover:text-orange-800"
                                :disabled="
                                    bulkStatusForm.processing ||
                                    selectedDocPageIds.length === 0
                                "
                                @click="submitBulkStatus('unpublish')"
                            >
                                <span
                                    v-if="
                                        bulkStatusForm.processing &&
                                        bulkStatusAction === 'unpublish'
                                    "
                                    class="mdi mdi-loading animate-spin text-base"
                                    aria-hidden="true"
                                />
                                <span
                                    v-else
                                    class="mdi mdi-cloud-off-outline text-base"
                                    aria-hidden="true"
                                />
                                {{ t('docs.actions.unpublish', 'Unpublish') }}
                            </Button>
                        </div>
                        <div
                            v-if="templateInfo.length > 0"
                            class="grid gap-2 border-t border-slate-200 pt-3 md:grid-cols-3"
                        >
                            <div
                                v-for="template in templateInfo"
                                :key="template.id"
                                class="rounded border border-blue-100 bg-white/70 p-2 text-xs text-blue-900"
                            >
                                <div class="font-semibold">
                                    {{ template.template_key }} ·
                                    {{ template.locale.toUpperCase() }}
                                </div>
                                <div>
                                    {{ t('docs.templates.layout', 'Layout') }}:
                                    {{ template.layout?.name || '-' }}
                                </div>
                                <Link
                                    class="mt-1 inline-flex font-semibold text-blue-700 underline-offset-2 hover:underline"
                                    :href="template.edit_url"
                                >
                                    {{
                                        t(
                                            'docs.templates.edit',
                                            'Edit template',
                                        )
                                    }}
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
                <RwTable
                    :table-id="`admin-cms-docs-collection-${collection.id}-pages-table`"
                    v-model:checkedRows="selectedPageIds"
                    :data="tableData"
                    :columns="columns"
                    :initial-height="'calc(100vh - 320px)'"
                    checkbox-column
                    :rows-per-page="25"
                    sort-field="id"
                    sort-order="desc"
                    :row-options="[25, 50, 100, 250]"
                    excel="true"
                    :cell-class="cellClass"
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
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    collection: { type: Object, required: true },
    templateInfo: { type: Array, default: () => [] },
    versions: { type: Array, required: true },
    pages: { type: Array, required: true },
});

const page = usePage();
const { t } = useAdminTranslations('cms_admin_ui');
const { t: commonT } = useAdminTranslations('admin_common_ui');
const selectedPageIds = ref([]);
const bulkStatusAction = ref('');
const bulkStatusForm = useForm({
    action: '',
    ids: [],
    collection_id: props.collection.id,
});

const collectionTitle = computed(() =>
    t('docs.collection_pages_title', 'Documentation: :collection').replace(
        ':collection',
        props.collection.name,
    ),
);

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

const rows = computed(() =>
    props.pages.map((item) => ({
        ...item,
        collection_name: item.version?.collection?.name ?? '-',
        version_label: item.version?.label ?? '-',
        parent_title: item.parent?.title ?? '-',
        status_color: statusColor(item.status),
        updated_at_display: formatDateTime(item.updated_at),
        published_at_display: formatDateTime(item.published_at),
    })),
);

const tableData = computed(() => ({
    data: rows.value,
    total: rows.value.length,
}));

const selectedDocPageIds = computed(() => {
    if (selectedPageIds.value.includes('all')) {
        return rows.value.map((row) => Number(row.id)).filter(Boolean);
    }

    return selectedPageIds.value
        .map((id) => Number(id))
        .filter((id) => Number.isInteger(id) && id > 0);
});

const columns = computed(() => [
    {
        key: 'id',
        label: commonT('columns.id', 'ID'),
        type: 'number',
        clickable: true,
        filterable: true,
        width: 90,
    },
    {
        key: 'title',
        label: commonT('columns.title', 'Title'),
        type: 'text',
        filterable: true,
    },
    {
        key: 'path',
        label: t('docs.columns.path', 'Path'),
        type: 'text',
        filterable: true,
    },
    {
        key: 'version_label',
        label: t('docs.columns.version', 'Version'),
        type: 'text',
        filterable: true,
    },
    {
        key: 'locale',
        label: commonT('columns.locale', 'Language'),
        type: 'text',
        filterable: true,
        width: 110,
    },
    {
        key: 'status',
        label: commonT('columns.status', 'Status'),
        type: 'chip',
        colorKey: 'status_color',
        filterable: true,
    },
    {
        key: 'updated_at_display',
        label: commonT('columns.updated_at', 'Updated'),
        type: 'text',
        filterable: true,
    },
]);

function onCellClick(field, id) {
    if (field !== 'id') return;
    router.visit(route('admin.cms.docs.pages.edit', { page: id }));
}

function submitBulkStatus(action) {
    const ids = selectedDocPageIds.value;

    if (ids.length === 0) {
        return;
    }

    bulkStatusAction.value = action;
    bulkStatusForm.action = action;
    bulkStatusForm.ids = ids;
    bulkStatusForm.collection_id = props.collection.id;
    bulkStatusForm.post(route('admin.cms.docs.pages.bulk-status'), {
        preserveScroll: true,
        onSuccess: () => {
            selectedPageIds.value = [];
            bulkStatusForm.reset();
            bulkStatusForm.collection_id = props.collection.id;
        },
        onFinish: () => {
            bulkStatusAction.value = '';
        },
    });
}

function cellClass({ col }) {
    return col.clickable ? 'cursor-pointer' : null;
}

function statusColor(status) {
    if (status === 'published') return 'green';
    if (status === 'archived') return 'orange';
    return 'grey';
}

function formatDateTime(value) {
    if (!value) return '-';
    const date = new Date(value);
    return Number.isNaN(date.getTime())
        ? '-'
        : new Intl.DateTimeFormat('nl-BE', {
              day: '2-digit',
              month: '2-digit',
              year: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
          }).format(date);
}
</script>
