<template>
    <Head :title="t('menus.page_title', 'CMS menu\'s')" />

    <AdminLayout :title="t('menus.page_title', 'CMS menu\'s')">
        <Card class="rounded-none">
            <CardHeader
                class="flex flex-row items-center justify-between gap-3"
            >
                <div>
                    <CardTitle>{{ t('menus.title', "Menu's") }}</CardTitle>
                    <CardDescription>
                        {{
                            t(
                                'menus.description',
                                'Manage reusable navigation menus for layout menu blocks.',
                            )
                        }}
                    </CardDescription>
                </div>
                <Button as-child>
                    <Link :href="route('admin.cms.menus.create')">
                        {{ t('menus.new', 'Nieuw menu') }}
                    </Link>
                </Button>
            </CardHeader>
            <CardContent class="p-0">
                <RwTable
                    table-id="admin-cms-menus-table"
                    :data="tableData"
                    :columns="columns"
                    :initial-height="'calc(100vh - 260px)'"
                    :row-options="[10, 25, 50, 100]"
                    @on-cell-click="onCellClick"
                />
            </CardContent>
        </Card>
    </AdminLayout>
</template>

<script setup>
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
    menus: {
        type: Array,
        required: true,
    },
    menuPlacementOptions: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const { t } = useAdminTranslations('cms_admin_ui');
const locale = computed(() => page.props?.app?.locale || 'nl-BE');

const tableRows = computed(() =>
    props.menus.map((menu) => ({
        ...menu,
        placements_label: placementLabels(menu.placements),
        active_label: menu.is_active
            ? t('common.yes', 'Yes')
            : t('common.no', 'No'),
        updated_at_display: formatDate(menu.updated_at),
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
        clickable: true,
    },
    {
        key: 'placements_label',
        label: t('menus.columns.placements', 'Places'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
    },
    {
        key: 'items_count',
        label: t('menus.columns.item_groups', 'Itemgroepen'),
        type: 'number',
        selected: true,
        sortable: true,
        filterable: true,
        width: 130,
    },
    {
        key: 'active_label',
        label: t('common.columns.active', 'Active'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
        width: 100,
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

function formatDate(value) {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
}

function placementLabels(placements) {
    if (!Array.isArray(placements) || placements.length === 0) {
        return '-';
    }

    const labels = new Map(
        props.menuPlacementOptions.map((option) => [
            option.value,
            option.label,
        ]),
    );

    return placements
        .map((placement) => labels.get(placement) || placement)
        .join(', ');
}

function onCellClick(field, id) {
    if (field !== 'id') {
        return;
    }

    router.visit(route('admin.cms.menus.edit', { id }));
}
</script>
