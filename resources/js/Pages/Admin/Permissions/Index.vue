<template>
    <Head :title="t('permissions.meta_title', 'Rights')" />

    <AdminLayout>
        <Card class="rounded-none shadow-none">
            <CardHeader class="gap-0 border-b border-slate-200 p-0">
                <div
                    class="flex flex-wrap items-start justify-between gap-3 px-4 py-4 sm:px-5"
                >
                    <div class="flex min-w-0 items-start gap-3">
                        <div
                            class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-blue-50 text-blue-700 ring-1 ring-blue-100"
                        >
                            <span class="mdi mdi-shield-key text-2xl" />
                        </div>
                        <div class="min-w-0">
                            <CardTitle class="text-lg">
                                {{
                                    t('permissions.index_title', 'Route rights')
                                }}
                            </CardTitle>
                            <CardDescription class="mt-1">
                                {{
                                    t(
                                        'permissions.index_subtitle',
                                        'Manage route rights for modules, actions and menu access.',
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
                                :href="
                                    route('admin.permissions.edit', { id: 0 })
                                "
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

            <CardContent class="p-0">
                <RwTable
                    table-id="admin-permissions-table-v3"
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
import { useSecurityTranslations } from '@/composables/useSecurityTranslations';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    permissions: {
        type: Array,
        required: true,
    },
});

const { t } = useAdminTranslations('admin_security_ui');
const { t: commonT } = useAdminTranslations('admin_common_ui');
const {
    permissionAction,
    permissionDescription,
    permissionModule,
    permissionType,
} = useSecurityTranslations();

const tableRows = computed(() =>
    props.permissions.map((permission) => ({
        ...permission,
        action_label: permissionAction(permission),
        description_label: permissionDescription(permission),
        menu_label: permission.menu
            ? t('common.yes', 'Yes')
            : t('common.no', 'No'),
        module_label: permissionModule(permission),
        type_label: permissionType(permission),
    })),
);

const tableData = computed(() => ({
    data: tableRows.value,
    total: tableRows.value.length,
}));

const columns = computed(() => [
    {
        key: 'id',
        label: t('columns.id', 'ID'),
        type: 'number',
        selected: true,
        sortable: true,
        filterable: true,
        clickable: true,
        width: 90,
    },
    {
        key: 'route_name',
        label: t('columns.route', 'Route'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
    },
    {
        key: 'description_label',
        label: t('columns.description', 'Description'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
    },
    {
        key: 'module_label',
        label: t('columns.module', 'Module'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
    },
    {
        key: 'action_label',
        label: t('columns.action', 'Action'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
    },
    {
        key: 'type_label',
        label: t('columns.type', 'Type'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
    },
    {
        key: 'menu_label',
        label: t('columns.in_menu', 'In menu'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
    },
]);

function cellClass({ col }) {
    return col.key === 'id' ? 'cursor-pointer' : null;
}

function onCellClick(field, id) {
    if (field !== 'id') {
        return;
    }

    router.visit(route('admin.permissions.edit', { id }));
}
</script>
