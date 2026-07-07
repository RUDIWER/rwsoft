<template>
    <Head :title="t('database_logs.meta_title', 'Database backup logs')" />

    <AdminLayout>
        <RwFormTemplate
            :title="t('database_logs.title', 'Database backup logs')"
            :subtitle="
                t(
                    'database_logs.subtitle',
                    'Overzicht van manuele database backup sessies',
                )
            "
        >
            <template #back>
                <RwActionButton
                    :label="t('actions.back', 'Terug')"
                    icon="mdi-arrow-left-circle"
                    tone="back"
                    @click="goBack"
                />
            </template>

            <div class="grid gap-4">
                <RwTable
                    :data="normalizedLogs"
                    :columns="columns"
                    :managed="true"
                    :data-source="{
                        type: 'inertia',
                        path: route('admin.database-logs'),
                        data: 'logs',
                    }"
                    table-id="database-logs-table-v1"
                    @on-cell-click="handleCellClick"
                />
            </div>
        </RwFormTemplate>

        <Dialog v-model:open="detailDialogOpen">
            <DialogContent class="max-w-4xl">
                <DialogHeader>
                    <DialogTitle>
                        {{ detailTitle }}
                    </DialogTitle>
                </DialogHeader>

                <div
                    v-if="selectedLogSteps.length > 0"
                    class="max-h-[520px] overflow-auto rounded-md border border-slate-200"
                >
                    <div
                        v-for="(step, index) in selectedLogSteps"
                        :key="`detail-${index}`"
                        class="border-b border-slate-100 px-3 py-2 text-sm last:border-b-0"
                    >
                        <div class="text-xs text-slate-500">
                            {{ step.timestamp || '-' }}
                        </div>
                        <div
                            :class="
                                step.level === 'error'
                                    ? 'text-red-700'
                                    : 'text-slate-700'
                            "
                        >
                            {{ step.message || '-' }}
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-slate-500">
                    {{
                        t(
                            'database_logs.empty_steps',
                            'Geen gedetailleerde stappen gevonden.',
                        )
                    }}
                </p>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="detailDialogOpen = false"
                    >
                        {{ t('database_logs.close', 'Sluiten') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RwActionButton from '@/Components/RwActionButton.vue';
import RwFormTemplate from '@/Components/RwFormTemplate.vue';
import RwTable from '@/Components/RwTable.vue';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    logs: {
        type: Object,
        required: true,
    },
});

const detailDialogOpen = ref(false);
const selectedLog = ref(null);
const { t } = useAdminTranslations('db_diagram_ui');

const normalizedLogs = computed(() => {
    const payload =
        props.logs && typeof props.logs === 'object' ? props.logs : {};
    const sourceData = Array.isArray(payload.data) ? payload.data : [];

    return {
        ...payload,
        data: sourceData.map((row) => ({
            ...row,
            status_color: statusToColor(row.status),
        })),
    };
});

const columns = computed(() => [
    {
        key: 'created_at',
        label: t('database_logs.columns.created_at', 'Datum/Tijd'),
        type: 'datetime',
        sortable: true,
        filterable: true,
        width: 180,
        clickable: true,
    },
    {
        key: 'project_name',
        label: t('database_logs.columns.project', 'Project'),
        type: 'text',
        sortable: true,
        filterable: true,
        width: 140,
    },
    {
        key: 'status',
        label: t('database_logs.columns.status', 'Status'),
        type: 'chip',
        sortable: true,
        filterable: true,
        width: 120,
        colorKey: 'status_color',
    },
    {
        key: 'file_size_kb',
        label: t('database_logs.columns.file_size', 'Grootte (KB)'),
        type: 'number',
        sortable: true,
        width: 120,
    },
    {
        key: 'filename',
        label: t('database_logs.columns.filename', 'Bestand'),
        type: 'text',
        sortable: true,
        filterable: true,
    },
    {
        key: 'user.name',
        label: t('database_logs.columns.user', 'Uitgevoerd door'),
        type: 'text',
        sortable: false,
        width: 180,
    },
]);

const selectedLogSteps = computed(() => {
    const details = selectedLog.value?.log_details;

    return Array.isArray(details) ? details : [];
});

const detailTitle = computed(() =>
    t('database_logs.details_title', 'Backup details: :filename', {
        filename:
            selectedLog.value?.filename ||
            t('database_logs.session_fallback', 'Sessie'),
    }),
);

function statusToColor(status) {
    if (status === 'completed') {
        return 'success';
    }

    if (status === 'failed') {
        return 'error';
    }

    if (status === 'processing') {
        return 'blue';
    }

    return 'grey';
}

function goBack() {
    router.visit(route('admin.db-diagram'));
}

function handleCellClick(_field, id) {
    const rows = Array.isArray(normalizedLogs.value?.data)
        ? normalizedLogs.value.data
        : [];
    const log = rows.find((row) => Number(row.id) === Number(id));

    if (!log) {
        return;
    }

    selectedLog.value = log;
    detailDialogOpen.value = true;
}
</script>
