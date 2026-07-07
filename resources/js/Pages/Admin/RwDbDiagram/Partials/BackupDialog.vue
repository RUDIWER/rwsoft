<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] max-w-3xl gap-0 overflow-hidden p-0 shadow-none">
            <DialogHeader class="border-b border-slate-200 px-4 py-4 pr-12 sm:px-5 sm:pr-12">
                <div class="min-w-0">
                    <DialogTitle>{{
                        t('backup_dialog.title', 'Full database backup')
                    }}</DialogTitle>
                    <DialogDescription class="mt-1">
                        {{
                            t(
                                'backup_dialog.subtitle',
                                'Select the tables for a manual ZIP backup.',
                            )
                        }}
                    </DialogDescription>
                </div>
            </DialogHeader>

            <div
                v-if="errorMessage || infoMessage"
                class="grid gap-2 px-4 pt-4 sm:px-5"
            >
                <RwFlashMessage
                    v-if="errorMessage"
                    type="danger"
                    :message="errorMessage"
                />

                <RwFlashMessage
                    v-if="infoMessage"
                    type="info"
                    :message="infoMessage"
                />
            </div>

            <div class="grid max-h-[calc(90vh-11rem)] gap-4 overflow-y-auto px-4 py-4 sm:px-5">
                <div class="grid gap-2 md:grid-cols-[auto_auto] md:justify-end">
                    <Button
                        type="button"
                        variant="outline"
                        class="shadow-none"
                        :disabled="isBusy"
                        @click="selectAll"
                    >
                        {{ t('backup_dialog.select_all', 'Select all') }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        class="shadow-none"
                        :disabled="isBusy"
                        @click="clearSelection"
                    >
                        {{ t('backup_dialog.clear_all', 'Clear all') }}
                    </Button>
                </div>

                <div
                    class="max-h-64 overflow-auto rounded-md border border-slate-200 p-3"
                >
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label
                            v-for="table in tableNames"
                            :key="table"
                            class="flex items-center gap-2 text-sm text-slate-700"
                        >
                            <input
                                :value="table"
                                :checked="selectedTables.includes(table)"
                                :disabled="isBusy"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-600"
                                @change="toggleTable(table, $event)"
                            />
                            <span class="font-mono">{{ table }}</span>
                        </label>
                    </div>
                </div>

                <div
                    v-if="logDetails.length > 0"
                    class="max-h-48 overflow-auto rounded-md border border-slate-200"
                >
                    <div
                        v-for="(step, idx) in logDetails"
                        :key="`step-${idx}`"
                        class="border-b border-slate-100 px-3 py-2 text-xs last:border-b-0"
                    >
                        <div class="font-medium text-slate-500">
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
            </div>

            <DialogFooter class="flex-wrap gap-2 border-t border-slate-200 px-4 py-3 sm:px-5">
                <Button
                    v-if="downloadUrl"
                    type="button"
                    variant="outline"
                    class="shadow-none"
                    @click="downloadBackup"
                >
                    {{ t('backup_dialog.download_zip', 'Download ZIP') }}
                </Button>
                <Button
                    v-if="canViewLogs"
                    type="button"
                    variant="outline"
                    class="shadow-none"
                    @click="openBackupLogs"
                >
                    <i class="mdi mdi-history mr-1" />
                    {{ t('backup_dialog.logs', 'Backup logs') }}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    class="border-blue-300 text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800"
                    :disabled="isBusy || selectedTables.length === 0"
                    @click="startBackup"
                >
                    <i
                        class="mdi mr-1"
                        :class="
                            isBusy
                                ? 'mdi-loading animate-spin'
                                : 'mdi-play-circle-outline'
                        "
                    />
                    {{ t('backup_dialog.start', 'Start backup') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<script setup>
import RwFlashMessage from '@/Components/RwFlashMessage.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
    tableNames: {
        type: Array,
        default: () => [],
    },
    projectName: {
        type: String,
        default: 'rwsoft',
    },
    canViewLogs: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue']);
const page = usePage();

const messages = computed(() => {
    const source = page.props?.app?.translations?.db_diagram_ui ?? {};

    return source && typeof source === 'object' ? source : {};
});

function getNestedTranslation(source, key) {
    if (!source || typeof source !== 'object') {
        return null;
    }

    return String(key || '')
        .split('.')
        .filter((segment) => segment !== '')
        .reduce((carry, segment) => {
            if (!carry || typeof carry !== 'object') {
                return null;
            }

            if (!Object.prototype.hasOwnProperty.call(carry, segment)) {
                return null;
            }

            return carry[segment];
        }, source);
}

function t(key, fallback = '', replacements = {}) {
    const translated = getNestedTranslation(messages.value, key);
    const resolved =
        typeof translated === 'string' && translated.trim() !== ''
            ? translated
            : fallback || key;

    return Object.entries(replacements).reduce(
        (carry, [token, replacement]) => {
            return carry.replaceAll(`:${token}`, String(replacement ?? ''));
        },
        resolved,
    );
}

const selectedTables = ref([]);
const backupId = ref(null);
const backupStatus = ref('idle');
const errorMessage = ref('');
const infoMessage = ref('');
const logDetails = ref([]);
const downloadUrl = ref('');

let pollTimer = null;

const open = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const isBusy = computed(() =>
    ['starting', 'pending', 'processing'].includes(backupStatus.value),
);

watch(
    () => props.modelValue,
    (isOpen) => {
        if (!isOpen) {
            stopPolling();
            return;
        }

        selectedTables.value = [...props.tableNames];
        backupId.value = null;
        backupStatus.value = 'idle';
        errorMessage.value = '';
        infoMessage.value = '';
        logDetails.value = [];
        downloadUrl.value = '';
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    stopPolling();
});

function selectAll() {
    selectedTables.value = [...props.tableNames];
}

function clearSelection() {
    selectedTables.value = [];
}

function toggleTable(tableName, event) {
    const checked = Boolean(event?.target?.checked);
    if (checked) {
        if (!selectedTables.value.includes(tableName)) {
            selectedTables.value.push(tableName);
        }
        return;
    }

    selectedTables.value = selectedTables.value.filter(
        (name) => name !== tableName,
    );
}

async function startBackup() {
    if (selectedTables.value.length === 0) {
        errorMessage.value = t(
            'backup_dialog.errors.select_table',
            'Select at least one table.',
        );
        return;
    }

    errorMessage.value = '';
    infoMessage.value = '';
    downloadUrl.value = '';
    logDetails.value = [];
    backupStatus.value = 'starting';

    try {
        const response = await window.axios.post(
            route('admin.db-diagram.backup-full.start'),
            {
                tables: selectedTables.value,
                project_name: String(props.projectName || 'rwsoft'),
            },
        );

        backupId.value = Number(response?.data?.backup_id || 0) || null;
        backupStatus.value = 'pending';
        infoMessage.value = t(
            'backup_dialog.info.started',
            'Backup process started. Status is being fetched...',
        );

        startPolling();
    } catch (error) {
        backupStatus.value = 'failed';
        errorMessage.value =
            error?.response?.data?.message ||
            error?.response?.data?.errors?.tables?.[0] ||
            t(
                'backup_dialog.errors.start_failed',
                'Backup could not be started.',
            );
    }
}

function startPolling() {
    stopPolling();

    if (!backupId.value) {
        return;
    }

    pollTimer = window.setInterval(() => {
        void pollStatus();
    }, 2000);

    void pollStatus();
}

function stopPolling() {
    if (pollTimer) {
        window.clearInterval(pollTimer);
        pollTimer = null;
    }
}

async function pollStatus() {
    if (!backupId.value) {
        return;
    }

    try {
        const response = await window.axios.get(
            route('admin.db-diagram.backup-full.status', {
                id: backupId.value,
            }),
        );

        const payload = response?.data || {};
        backupStatus.value = String(payload.status || 'unknown');
        logDetails.value = Array.isArray(payload.log_details)
            ? payload.log_details
            : [];

        if (backupStatus.value === 'completed') {
            stopPolling();
            infoMessage.value = t(
                'backup_dialog.info.completed',
                'Backup completed (:size KB).',
                { size: Number(payload.file_size_kb || 0) },
            );
            downloadUrl.value = route('admin.db-diagram.backup-full.download', {
                id: backupId.value,
            });
            return;
        }

        if (backupStatus.value === 'failed') {
            stopPolling();
            errorMessage.value =
                String(payload.error_message || '') ||
                t('backup_dialog.errors.failed', 'Backup failed.');
        }
    } catch {
        stopPolling();
        errorMessage.value = t(
            'backup_dialog.errors.status_failed',
            'Failed to fetch backup status.',
        );
    }
}

function downloadBackup() {
    if (!downloadUrl.value) {
        return;
    }

    window.location.assign(downloadUrl.value);
}

function openBackupLogs() {
    window.location.assign(route('admin.database-logs'));
}
</script>
