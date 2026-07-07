<template>
    <Head :title="t('layouts.page_title', 'CMS layouts')" />

    <AdminLayout
        :title="t('layouts.page_title', 'CMS layouts')"
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
                            <span class="mdi mdi-page-layout-body text-2xl" />
                        </div>
                        <div class="min-w-0">
                            <CardTitle class="text-lg">
                                {{ t('layouts.title', 'Layouts') }}
                            </CardTitle>
                            <CardDescription class="mt-1">
                                {{
                                    t(
                                        'layouts.description',
                                        'Beheer de layout-laag boven pagina content. Header/footer sections worden in de volgende stap toegevoegd.',
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
                                :href="route('admin.cms.layouts.create')"
                                class="gap-2"
                            >
                                <span
                                    class="mdi mdi-plus-circle text-base text-blue-700"
                                    aria-hidden="true"
                                />
                                {{ commonT('actions.new', 'Nieuw') }}
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
                    table-id="admin-cms-layouts-table"
                    :data="tableData"
                    :columns="columns"
                    :initial-height="'calc(100vh - 260px)'"
                    :rows-per-page="25"
                    sort-field="id"
                    sort-order="desc"
                    :row-options="[25, 50, 100, 250]"
                    :cell-class="cellClass"
                    @on-cell-click="onCellClick"
                >
                    <template #col-actions="{ row: layout }">
                        <div class="flex flex-wrap justify-end gap-2">
                            <Button as-child variant="outline" size="sm">
                                <Link
                                    :href="
                                        route('admin.cms.layouts.edit', {
                                            id: layout.id,
                                        })
                                    "
                                >
                                    {{ t('themes.edit', 'Edit') }}
                                </Link>
                            </Button>
                            <Button
                                v-if="
                                    !layout.is_default &&
                                    layout.pages_count === 0
                                "
                                type="button"
                                variant="destructive"
                                size="sm"
                                @click="deleteLayout(layout)"
                            >
                                {{ t('themes.delete', 'Delete') }}
                            </Button>
                        </div>
                    </template>
                </RwTable>
            </CardContent>
        </Card>
    </AdminLayout>
</template>

<script setup>
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
import RwTable from '@/Components/RwTable.vue';
import RwFlashMessage from '@/Components/RwFlashMessage.vue';

const props = defineProps({
    layouts: { type: Array, required: true },
});

const page = usePage();
const { t } = useAdminTranslations('cms_admin_ui');
const { t: commonT } = useAdminTranslations('admin_common_ui');
const locale = computed(() => page.props?.app?.locale || 'nl-BE');

const pageFlash = computed(() => {
    const flash = page.props?.flash || {};
    if (flash.error) return { type: 'danger', message: flash.error };
    if (flash.warning) return { type: 'warning', message: flash.warning };
    if (flash.status) return { type: 'success', message: flash.status };
    return { type: '', message: '' };
});

const formatDate = (value) => {
    if (!value) return '-';
    return new Date(value)
        .toLocaleString('en-US', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
        })
        .replace(',', '');
};

const tableRows = computed(() =>
    props.layouts.map((layout) => ({
        ...layout,
        default_label: layout.is_default ? t('common.yes', 'Yes') : '',
        default_color: layout.is_default ? 'blue' : '',
        active_label: layout.is_active
            ? t('common.yes', 'Yes')
            : t('common.no', 'No'),
        active_color: layout.is_active ? 'green' : 'red',
        updated_at_display: formatDate(layout.updated_at),
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
        sortable: true,
        clickable: true,
        width: 90,
    },
    {
        key: 'name',
        label: t('layouts.name', 'Name'),
        type: 'text',
        sortable: true,
    },
    {
        key: 'locale',
        label: t('common.columns.locale', 'Language'),
        type: 'text',
        sortable: true,
        width: 100,
    },
    {
        key: 'default_label',
        label: t('layouts.default', 'Default'),
        type: 'chip',
        colorKey: 'default_color',
        chipOnlyWhenColor: true,
        width: 100,
    },
    {
        key: 'active_label',
        label: t('common.columns.active', 'Active'),
        type: 'chip',
        colorKey: 'active_color',
        chipOnlyWhenColor: true,
        width: 100,
    },
    {
        key: 'pages_count',
        label: t('layouts.pages', "Pagina's"),
        type: 'number',
        sortable: true,
        width: 120,
    },
    {
        key: 'updated_at_display',
        label: t('common.columns.updated_at', 'Updated'),
        type: 'text',
        sortable: true,
    },
    {
        key: 'actions',
        label: t('layouts.actions', 'Actions'),
        align: 'right',
        sortable: false,
        width: 150,
    },
]);

function cellClass({ col }) {
    return col.key === 'id' ? 'cursor-pointer' : null;
}

function onCellClick(field, id) {
    if (field === 'id') {
        router.visit(route('admin.cms.layouts.edit', { id }));
    }
}

function deleteLayout(layout) {
    if (
        !window.confirm(
            t('layouts.delete_confirm', 'Layout ":name" verwijderen?', {
                name: layout.name,
            }),
        )
    ) {
        return;
    }

    router.delete(route('admin.cms.layouts.destroy', { id: layout.id }));
}
</script>
