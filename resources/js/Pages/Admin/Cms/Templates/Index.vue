<template>
    <Head :title="t('templates.page_title', 'CMS templates')" />

    <AdminLayout :suppress-flash="true">
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
                            <span class="mdi mdi-view-quilt-outline text-2xl" />
                        </div>
                        <div class="min-w-0">
                            <CardTitle class="text-lg">
                                {{ t('templates.title', 'Templates') }}
                            </CardTitle>
                            <CardDescription class="mt-1">
                                {{
                                    t(
                                        'templates.description',
                                        'Manage reusable page, blog, category and tag templates.',
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
                                :href="createHref"
                                class="gap-2"
                                :aria-label="commonT('actions.new', 'New')"
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
                <div class="border-b border-slate-200">
                    <div class="flex flex-wrap gap-4 px-4 sm:px-5">
                        <button
                            v-for="tab in classTabs"
                            :key="tab.value"
                            type="button"
                            class="-mb-px border-b-2 px-1 py-2 text-sm font-medium transition"
                            :class="
                                activeClass === tab.value
                                    ? 'border-blue-600 text-blue-700'
                                    : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900'
                            "
                            @click="activeClass = tab.value"
                        >
                            {{ tab.label }}
                        </button>
                    </div>
                </div>

                <RwTable
                    table-id="admin-cms-templates-table"
                    :data="tableData"
                    :columns="columns"
                    :initial-height="'calc(100vh - 310px)'"
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
import { computed, ref } from 'vue';

const props = defineProps({
    templates: { type: Array, required: true },
    templateOptions: { type: Array, required: true },
});

const page = usePage();
const { t } = useAdminTranslations('cms_admin_ui');
const { t: commonT } = useAdminTranslations('admin_common_ui');
const activeClass = ref('page');
const locale = computed(() => page.props?.app?.locale || 'nl-BE');

const classTabs = computed(() =>
    props.templateOptions.map((option) => ({
        value: option.value,
        label: t(option.label_key, option.value),
    })),
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

const createHref = computed(() =>
    route('admin.cms.templates.create', {
        template_class: activeClass.value,
        template_key: defaultTemplateKeyFor(activeClass.value),
    }),
);

const tableRows = computed(() =>
    props.templates
        .filter((template) => template.template_class === activeClass.value)
        .map((template) => ({
            ...template,
            class_label: classLabel(template.template_class),
            template_type_label: templateTypeLabel(
                template.template_class,
                template.template_key,
            ),
            layout_label: template.layout_name || '-',
            default_label: template.is_default
                ? commonT('common.yes', 'Yes')
                : commonT('common.no', 'No'),
            active_label: template.is_active
                ? commonT('common.yes', 'Yes')
                : commonT('common.no', 'No'),
            active_color: template.is_active ? 'green' : 'red',
            updated_at_display: formatDate(template.updated_at),
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
        key: 'name',
        label: t('templates.columns.name', 'Name'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
    },
    {
        key: 'template_type_label',
        label: t('templates.columns.template_type', 'Template type'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
    },
    {
        key: 'layout_label',
        label: t('templates.columns.layout', 'Layout'),
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
        key: 'default_label',
        label: t('templates.columns.default', 'Default'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
        width: 110,
    },
    {
        key: 'active_label',
        label: t('common.columns.active', 'Active'),
        type: 'chip',
        colorKey: 'active_color',
        selected: true,
        sortable: true,
        filterable: true,
        width: 100,
    },
    {
        key: 'usage_count',
        label: t('templates.columns.usage_count', 'Usage'),
        type: 'number',
        selected: true,
        sortable: true,
        filterable: true,
        width: 110,
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

function defaultTemplateKeyFor(templateClass) {
    const option = props.templateOptions.find(
        (item) => item.value === templateClass,
    );

    return option?.template_types?.[0]?.value || 'page.detail';
}

function classLabel(templateClass) {
    const option = props.templateOptions.find(
        (item) => item.value === templateClass,
    );

    return option ? t(option.label_key, option.value) : templateClass;
}

function templateTypeLabel(templateClass, templateKey) {
    const option = props.templateOptions.find(
        (item) => item.value === templateClass,
    );
    const typeOption = option?.template_types?.find(
        (item) => item.value === templateKey,
    );

    return typeOption ? t(typeOption.label_key, templateKey) : templateKey;
}

function formatDate(value) {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat(locale.value, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function onCellClick(field, id) {
    if (field !== 'id') {
        return;
    }

    router.visit(route('admin.cms.templates.edit', { id }));
}

function cellClass({ col }) {
    return col.clickable ? 'cursor-pointer' : null;
}
</script>
