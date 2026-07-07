<template>
    <AdminLayout :suppress-flash="true">
        <Head :title="t('mail.page_title', 'CMS mail templates')" />

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
                            <span class="mdi mdi-email-edit-outline text-2xl" />
                        </div>
                        <div class="min-w-0">
                            <CardTitle class="text-lg">
                                {{ t('mail.title', 'Mail templates') }}
                            </CardTitle>
                            <CardDescription class="mt-1">
                                {{
                                    t(
                                        'mail.templates_description',
                                        'Manage reusable section and grid templates for CMS emails.',
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
                                :href="route('admin.cms.mail-templates.create')"
                                class="gap-2"
                            >
                                <span
                                    class="mdi mdi-plus-circle text-base text-blue-700"
                                    aria-hidden="true"
                                />
                                {{ t('mail.new_template', 'New template') }}
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
                    table-id="admin-cms-mail-templates-table"
                    :data="templateTableData"
                    :columns="templateColumns"
                    :initial-height="'calc(100vh - 280px)'"
                    :rows-per-page="25"
                    sort-field="id"
                    sort-order="desc"
                    :row-options="[25, 50, 100, 250]"
                    :cell-class="cellClass"
                    excel="true"
                    @on-cell-click="onTemplateCellClick"
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
    mailTemplates: { type: Array, required: true },
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

const templateRows = computed(() =>
    props.mailTemplates.map((template) => ({
        ...template,
        active_label: template.is_active
            ? commonT('common.yes', 'Yes')
            : commonT('common.no', 'No'),
        active_color: template.is_active ? 'green' : 'red',
        updated_at_display: formatDate(template.updated_at),
    })),
);

const templateTableData = computed(() => ({
    data: templateRows.value,
    total: templateRows.value.length,
}));

const templateColumns = computed(() => [
    idColumn(),
    textColumn('name', commonT('columns.name', 'Name')),
    textColumn('context_key', t('mail.columns.context', 'Context')),
    numberColumn('emails_count', t('mail.columns.emails', 'Emails')),
    chipColumn(
        'active_label',
        commonT('columns.active', 'Active'),
        'active_color',
    ),
    textColumn('updated_at_display', commonT('columns.updated_at', 'Updated')),
]);

function idColumn() {
    return {
        key: 'id',
        label: commonT('columns.id', 'ID'),
        type: 'number',
        selected: true,
        sortable: true,
        filterable: true,
        clickable: true,
        width: 90,
    };
}

function textColumn(key, label) {
    return {
        key,
        label,
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
    };
}

function numberColumn(key, label) {
    return {
        key,
        label,
        type: 'number',
        selected: true,
        sortable: true,
        filterable: true,
    };
}

function chipColumn(key, label, colorKey) {
    return {
        key,
        label,
        type: 'chip',
        colorKey,
        chipOnlyWhenColor: true,
        selected: true,
        sortable: true,
        filterable: true,
    };
}

function cellClass({ col }) {
    return col.clickable ? 'cursor-pointer' : null;
}

function onTemplateCellClick(field, id) {
    if (field !== 'id') return;
    router.visit(route('admin.cms.mail-templates.edit', { id }));
}

function formatDate(value) {
    if (!value) return '-';
    return new Intl.DateTimeFormat(locale.value).format(new Date(value));
}
</script>
