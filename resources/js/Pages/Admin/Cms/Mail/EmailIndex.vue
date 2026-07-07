<template>
    <AdminLayout :suppress-flash="true">
        <Head :title="t('mail.emails_page_title', 'CMS emails')" />

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
                            <span class="mdi mdi-email-outline text-2xl" />
                        </div>
                        <div class="min-w-0">
                            <CardTitle class="text-lg">
                                {{ t('mail.emails_title', 'Emails') }}
                            </CardTitle>
                            <CardDescription class="mt-1">
                                {{
                                    t(
                                        'mail.emails_description',
                                        'Manage editable email content and review recent delivery logs.',
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
                                :href="route('admin.cms.emails.create')"
                                class="gap-2"
                            >
                                <span
                                    class="mdi mdi-plus-circle text-base text-blue-700"
                                    aria-hidden="true"
                                />
                                {{ t('mail.new_email', 'New email') }}
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
                            v-for="tab in tabs"
                            :key="tab.value"
                            type="button"
                            class="-mb-px border-b-2 px-1 py-2 text-sm font-medium transition"
                            :class="
                                activeTab === tab.value
                                    ? 'border-blue-600 text-blue-700'
                                    : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900'
                            "
                            @click="activeTab = tab.value"
                        >
                            {{ tab.label }}
                        </button>
                    </div>
                </div>

                <RwTable
                    v-if="activeTab === 'emails'"
                    table-id="admin-cms-emails-table"
                    :data="emailTableData"
                    :columns="emailColumns"
                    :initial-height="'calc(100vh - 310px)'"
                    :rows-per-page="25"
                    sort-field="id"
                    sort-order="desc"
                    :row-options="[25, 50, 100, 250]"
                    :cell-class="cellClass"
                    excel="true"
                    @on-cell-click="onEmailCellClick"
                />

                <RwTable
                    v-else
                    table-id="admin-cms-email-deliveries-table"
                    :data="deliveryTableData"
                    :columns="deliveryColumns"
                    :initial-height="'calc(100vh - 310px)'"
                    :rows-per-page="25"
                    sort-field="id"
                    sort-order="desc"
                    :row-options="[25, 50, 100, 250]"
                    excel="true"
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
    emails: { type: Array, required: true },
    deliveries: { type: Array, required: true },
});

const page = usePage();
const { t } = useAdminTranslations('cms_admin_ui');
const { t: commonT } = useAdminTranslations('admin_common_ui');
const activeTab = ref('emails');
const locale = computed(() => page.props?.app?.locale || 'nl-BE');

const tabs = computed(() => [
    { value: 'emails', label: t('mail.emails_tab', 'Emails') },
    { value: 'deliveries', label: t('mail.deliveries_tab', 'Mail log') },
]);

const pageFlash = computed(() => {
    const flash = page.props?.flash || {};

    if (flash.error) return { type: 'danger', message: flash.error };
    if (flash.warning) return { type: 'warning', message: flash.warning };
    if (flash.status) return { type: 'success', message: flash.status };

    return { type: '', message: '' };
});

const emailRows = computed(() =>
    props.emails.map((email) => ({
        ...email,
        type_label: t(`mail.email_types.${email.email_type}`, email.email_type),
        type_color: email.email_type === 'system' ? 'orange' : 'blue',
        active_label: email.is_active
            ? commonT('common.yes', 'Yes')
            : commonT('common.no', 'No'),
        active_color: email.is_active ? 'green' : 'red',
        updated_at_display: formatDate(email.updated_at),
    })),
);

const deliveryRows = computed(() =>
    props.deliveries.map((delivery) => ({
        ...delivery,
        status_label: t(
            `mail.delivery_statuses.${delivery.status}`,
            delivery.status,
        ),
        status_color:
            delivery.status === 'sent'
                ? 'green'
                : delivery.status === 'failed'
                  ? 'red'
                  : 'orange',
        sent_at_display: formatDateTime(delivery.sent_at),
        created_at_display: formatDateTime(delivery.created_at),
    })),
);

const emailTableData = computed(() => ({
    data: emailRows.value,
    total: emailRows.value.length,
}));

const deliveryTableData = computed(() => ({
    data: deliveryRows.value,
    total: deliveryRows.value.length,
}));

const emailColumns = computed(() => [
    idColumn(),
    textColumn('title', commonT('columns.title', 'Title')),
    chipColumn('type_label', commonT('columns.type', 'Type'), 'type_color'),
    textColumn('system_key', t('mail.columns.system_key', 'System key')),
    textColumn('locale', commonT('columns.locale', 'Language')),
    textColumn('mail_template_name', t('mail.columns.template', 'Template')),
    textColumn('subject', t('mail.columns.subject', 'Subject')),
    chipColumn(
        'active_label',
        commonT('columns.active', 'Active'),
        'active_color',
    ),
    textColumn('updated_at_display', commonT('columns.updated_at', 'Updated')),
]);

const deliveryColumns = computed(() => [
    idColumn(false),
    textColumn('email_title', commonT('columns.title', 'Title')),
    textColumn('recipient_email', t('mail.columns.recipient', 'Recipient')),
    chipColumn(
        'status_label',
        commonT('columns.status', 'Status'),
        'status_color',
    ),
    textColumn('subject_snapshot', t('mail.columns.subject', 'Subject')),
    textColumn('context_type', t('mail.columns.context', 'Context')),
    numberColumn('context_id', t('mail.columns.context_id', 'Context ID')),
    textColumn('sent_at_display', t('mail.columns.sent_at', 'Sent')),
    textColumn('created_at_display', commonT('columns.created_at', 'Created')),
]);

function idColumn(clickable = true) {
    return {
        key: 'id',
        label: commonT('columns.id', 'ID'),
        type: 'number',
        selected: true,
        sortable: true,
        filterable: true,
        clickable,
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

function onEmailCellClick(field, id) {
    if (field !== 'id') return;
    router.visit(route('admin.cms.emails.edit', { id }));
}

function formatDate(value) {
    if (!value) return '-';
    return new Intl.DateTimeFormat(locale.value).format(new Date(value));
}

function formatDateTime(value) {
    if (!value) return '-';
    return new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
}
</script>
