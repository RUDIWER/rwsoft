<template>
    <Head :title="t('submissions.page_title', 'CMS inzendingen')" />

    <AdminLayout :title="t('submissions.page_title', 'CMS inzendingen')">
        <Card class="rounded-none">
            <CardHeader>
                <CardTitle>{{
                    t('submissions.title', 'Inzendingen')
                }}</CardTitle>
                <CardDescription>
                    {{
                        t(
                            'submissions.description',
                            'Bekijk de laatste formulierinzendingen. Inzendingen worden hier niet verwijderd.',
                        )
                    }}
                </CardDescription>
            </CardHeader>
            <CardContent class="grid gap-4">
                <RwTable
                    table-id="admin-cms-form-submissions-table"
                    :data="tableData"
                    :columns="columns"
                    :initial-height="'420px'"
                    :row-options="[10, 25, 50, 100]"
                    @on-cell-click="onCellClick"
                />

                <Card
                    v-if="selectedSubmission"
                    class="border-blue-200 bg-blue-50/40"
                >
                    <CardHeader>
                        <CardTitle>{{
                            t('submissions.detail_title', 'Inzending #:id', {
                                id: selectedSubmission.id,
                            })
                        }}</CardTitle>
                        <CardDescription>
                            {{ selectedSubmission.form_title }} -
                            {{ selectedSubmission.submitted_at || '-' }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-3">
                        <div
                            v-for="value in selectedSubmission.values"
                            :key="`${selectedSubmission.id}-${value.field_translation_key}`"
                            class="grid gap-1 rounded-lg border border-slate-200 bg-white p-3"
                        >
                            <div
                                class="text-xs font-medium uppercase tracking-wide text-slate-500"
                            >
                                {{ value.label }}
                            </div>
                            <div
                                class="whitespace-pre-line text-sm text-slate-900"
                            >
                                {{ value.value || '-' }}
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </CardContent>
        </Card>
    </AdminLayout>
</template>

<script setup>
import RwTable from '@/Components/RwTable.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    submissions: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const { t } = useAdminTranslations('cms_admin_ui');
const locale = computed(() => page.props?.app?.locale || 'nl-BE');
const selectedId = ref(props.submissions[0]?.id ?? null);

const tableRows = computed(() =>
    props.submissions.map((submission) => ({
        ...submission,
        submitted_at_display: formatDate(submission.submitted_at),
        page_label: submission.page_title || '-',
    })),
);

const tableData = computed(() => ({
    data: tableRows.value,
    total: tableRows.value.length,
}));

const selectedSubmission = computed(() =>
    props.submissions.find(
        (submission) => Number(submission.id) === Number(selectedId.value),
    ),
);

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
        key: 'form_title',
        label: t('submissions.columns.form', 'Formulier'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
        clickable: true,
    },
    {
        key: 'locale',
        label: t('common.columns.locale', 'Taal'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
        width: 100,
    },
    {
        key: 'page_label',
        label: t('submissions.columns.page', 'Pagina'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
    },
    {
        key: 'status',
        label: t('common.columns.status', 'Status'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
        width: 120,
    },
    {
        key: 'submitted_at_display',
        label: t('submissions.columns.submitted_at', 'Ingezonden'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
    },
    {
        key: 'ip_address',
        label: t('submissions.columns.ip', 'IP'),
        type: 'text',
        selected: false,
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

function onCellClick(field, id) {
    if (!['id', 'form_title'].includes(field)) {
        return;
    }

    selectedId.value = id;
}
</script>
