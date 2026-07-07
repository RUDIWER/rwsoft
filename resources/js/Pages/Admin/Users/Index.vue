<template>
    <Head :title="t('users.meta_title', 'Users')" />

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
                            <span class="mdi mdi-account-multiple text-2xl" />
                        </div>
                        <div class="min-w-0">
                            <CardTitle class="text-lg">
                                {{ t('users.index_title', 'User management') }}
                            </CardTitle>
                            <CardDescription class="mt-1">
                                {{
                                    t(
                                        'users.index_subtitle',
                                        'Manage backoffice users, roles and access settings.',
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
                                :href="route('admin.users.edit', { id: 0 })"
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
                    table-id="admin-users-table-v2"
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
    users: {
        type: Array,
        required: true,
    },
});

const { t } = useAdminTranslations('admin_security_ui');
const { t: commonT } = useAdminTranslations('admin_common_ui');
const { roleLabel } = useSecurityTranslations();

const tableRows = computed(() =>
    props.users.map((user) => ({
        ...user,
        roles_label: user.roles?.map((role) => roleKey(role)) || [],
    })),
);

const roleOptions = computed(() => {
    const optionsByKey = new Map();

    props.users.forEach((user) => {
        (user.roles || []).forEach((role) => {
            const key = roleKey(role);

            if (!key || optionsByKey.has(key)) {
                return;
            }

            optionsByKey.set(key, {
                value: key,
                title: roleLabel(role),
                color: roleChipColor(role),
            });
        });
    });

    return [...optionsByKey.values()];
});

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
        key: 'name',
        label: t('columns.name', 'Name'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
    },
    {
        key: 'email',
        label: t('columns.email', 'E-mail'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
    },
    {
        key: 'roles_label',
        label: t('columns.roles', 'Roles'),
        type: 'text',
        editMultiple: true,
        editItems: roleOptions.value,
        editItemTitle: 'title',
        editItemValue: 'value',
        editItemColor: 'color',
        editMaxSelectionChips: 6,
        selected: true,
        sortable: true,
        filterable: true,
        minWidth: 220,
    },
]);

function cellClass({ col }) {
    return col.key === 'id' ? 'cursor-pointer' : null;
}

function onCellClick(field, id) {
    if (field !== 'id') {
        return;
    }

    router.visit(route('admin.users.edit', { id }));
}

function roleKey(role) {
    return String(role?.key || role?.name || role?.id || '').trim();
}

function roleChipColor(role) {
    const key = roleKey(role).toLowerCase().replace(/[-\s]+/g, '_');
    const label = roleLabel(role).toLowerCase().replace(/[-\s]+/g, '_');

    if (['super_admin', 'superadmin'].includes(key) || label === 'super_admin') {
        return 'red';
    }

    if (key === 'admin' || label === 'admin') {
        return 'orange';
    }

    return null;
}
</script>
