<template>
    <AdminLayout :suppress-flash="true">
        <Head :title="t('forms.page_title', 'CMS forms')" />

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
                            <span class="mdi mdi-form-select text-2xl" />
                        </div>
                        <div class="min-w-0">
                            <CardTitle class="text-lg">
                                {{ t('forms.title', 'Forms') }}
                            </CardTitle>
                            <CardDescription class="mt-1">
                                {{
                                    t(
                                        'forms.description',
                                        'Manage CMS forms and the fields used on the public site.',
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
                                :href="
                                    route('admin.cms.form-submissions.index')
                                "
                                class="gap-2"
                            >
                                <span
                                    class="mdi mdi-inbox-arrow-down text-base text-blue-700"
                                    aria-hidden="true"
                                />
                                {{
                                    commonT(
                                        'navigation.cms_form_submissions',
                                        'Form submissions',
                                    )
                                }}
                            </Link>
                        </Button>

                        <Button
                            as-child
                            variant="outline"
                            class="border-blue-200 text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800"
                        >
                            <Link
                                :href="route('admin.cms.forms.create')"
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
                    table-id="admin-cms-forms-table"
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
    forms: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const { t } = useAdminTranslations('cms_admin_ui');
const { t: commonT } = useAdminTranslations('admin_common_ui');
const locale = computed(() => page.props?.app?.locale || 'nl-BE');

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

const tableRows = computed(() =>
    props.forms.map((form) => ({
        ...form,
        active_label: form.is_active
            ? t('common.yes', 'Yes')
            : t('common.no', 'No'),
        active_color: form.is_active ? 'green' : 'red',
        form_kind_label:
            form.form_kind === 'system'
                ? t('forms.system.badge', 'System form')
                : t('forms.system.normal_badge', 'CMS form'),
        form_kind_color: form.form_kind === 'system' ? 'orange' : 'blue',
        updated_at_display: formatDate(form.updated_at),
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
        key: 'form_kind_label',
        label: t('forms.columns.kind', 'Kind'),
        type: 'chip',
        colorKey: 'form_kind_color',
        chipOnlyWhenColor: true,
        selected: true,
        sortable: true,
        filterable: true,
        width: 140,
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
        key: 'fields_count',
        label: t('forms.columns.fields', 'Velden'),
        type: 'number',
        selected: true,
        sortable: true,
        filterable: true,
        width: 110,
    },
    {
        key: 'submissions_count',
        label: t('forms.columns.submissions', 'Inzendingen'),
        type: 'number',
        selected: true,
        sortable: true,
        filterable: true,
        width: 130,
    },
    {
        key: 'active_label',
        label: t('common.columns.active', 'Active'),
        type: 'chip',
        colorKey: 'active_color',
        chipOnlyWhenColor: true,
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

function cellClass({ col }) {
    return col.key === 'id' ? 'cursor-pointer' : null;
}

function formatDate(value) {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
}

function onCellClick(field, id) {
    if (field !== 'id') {
        return;
    }

    router.visit(route('admin.cms.forms.edit', { id }));
}
</script>
