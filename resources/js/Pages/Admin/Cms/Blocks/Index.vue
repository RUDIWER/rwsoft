<template>
    <Head :title="t('blocks.page_title', 'CMS blokken')" />

    <AdminLayout
        :title="t('blocks.page_title', 'CMS blokken')"
        :suppress-flash="true"
    >
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
                            <span class="mdi mdi-view-grid-plus text-2xl" />
                        </div>
                        <div class="min-w-0">
                            <CardTitle class="text-lg">
                                {{ t('blocks.title', 'Blokken') }}
                            </CardTitle>
                            <CardDescription class="mt-1">
                                {{
                                    t(
                                        'blocks.description',
                                        'Beheer de bouwstenen, SafeBlade templates en CSS voor pagina- en layoutsecties.',
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
                                :href="route('admin.cms.blocks.create')"
                                class="gap-2"
                            >
                                <span
                                    class="mdi mdi-plus-circle text-base text-blue-700"
                                    aria-hidden="true"
                                />
                                {{ commonT('actions.new', 'Nieuw') }}
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
                    :data="tableData"
                    :columns="columns"
                    table-id="cms-blocks-table-v1"
                    :initial-height="'calc(100vh - 300px)'"
                    :rows-per-page="25"
                    :cell-class="cellClass"
                    @on-cell-click="onCellClick"
                >
                    <template #col-name="{ row: block }">
                        <div class="font-medium text-slate-900">
                            {{ block.name }}
                        </div>
                        <div class="text-xs text-slate-500">
                            {{
                                block.description ||
                                t('blocks.no_description', 'Geen omschrijving')
                            }}
                        </div>
                    </template>

                    <template #col-status="{ row: block }">
                        <span
                            class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                            :class="statusClass(block.status)"
                        >
                            {{ statusLabel(block.status) }}
                        </span>
                    </template>

                    <template #col-latest_revision="{ row: block }">
                        <span v-if="block.latest_published_revision">
                            #{{
                                block.latest_published_revision.revision_number
                            }}
                        </span>
                        <span v-else class="text-slate-400">-</span>
                    </template>

                    <template #col-badges="{ row: block }">
                        <div class="flex flex-wrap gap-1">
                            <span
                                v-if="block.category === 'system'"
                                class="rounded-full bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700"
                            >
                                {{ t('blocks.badge_system', 'Systeemblok') }}
                            </span>
                            <span
                                v-if="block.category === 'code'"
                                class="rounded-full bg-orange-50 px-2 py-1 text-xs font-medium text-orange-700"
                            >
                                {{ t('blocks.badge_code', 'Codeblok') }}
                            </span>
                            <span
                                v-if="block.category === 'mail'"
                                class="rounded-full bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700"
                            >
                                {{ t('blocks.badge_mail', 'Mail block') }}
                            </span>
                            <span
                                v-if="block.source === 'package'"
                                class="rounded-full bg-purple-50 px-2 py-1 text-xs font-medium text-purple-700"
                            >
                                {{ t('blocks.badge_package', 'Package') }}
                            </span>
                            <span
                                v-if="block.is_locked"
                                class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700"
                            >
                                {{ t('blocks.badge_locked', 'Locked') }}
                            </span>
                        </div>
                    </template>

                    <template #col-actions="{ row: block }">
                        <div class="flex flex-wrap justify-end gap-2">
                            <Button as-child variant="outline" size="sm">
                                <Link
                                    :href="
                                        route('admin.cms.blocks.edit', {
                                            block: block.id,
                                        })
                                    "
                                >
                                    {{ t('blocks.edit', 'Bewerken') }}
                                </Link>
                            </Button>
                        </div>
                    </template>
                </RwTable>
            </CardContent>
        </Card>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RwFlashMessage from '@/Components/RwFlashMessage.vue';
import RwTable from '@/Components/RwTable.vue';
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
    blocks: { type: Array, required: true },
});

const page = usePage();
const { t } = useAdminTranslations('cms_admin_ui');
const { t: commonT } = useAdminTranslations('admin_common_ui');
const activeTab = ref('screen');

const tabs = computed(() => [
    { value: 'screen', label: t('blocks.tabs.screen', 'Screen') },
    { value: 'mail', label: t('blocks.tabs.mail', 'Mail') },
]);

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
    props.blocks
        .filter((block) =>
            activeTab.value === 'mail'
                ? block.category === 'mail'
                : block.category !== 'mail',
        )
        .map((block) => ({
            ...block,
            zones_display: Array.isArray(block.allowed_zones)
                ? block.allowed_zones.join(', ')
                : '',
            updated_at_display: formatDate(block.updated_at),
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
        sortable: true,
        filterable: true,
        clickable: true,
        width: 90,
    },
    {
        key: 'name',
        label: t('common.columns.name', 'Naam'),
        sortable: true,
    },
    {
        key: 'category',
        label: t('common.columns.category', 'Categorie'),
        sortable: true,
        filterable: true,
    },
    {
        key: 'zones_display',
        label: t('blocks.allowed_zones', 'Plaatsen'),
        sortable: false,
    },
    {
        key: 'rendering_mode',
        label: t('blocks.rendering_mode', 'Rendering'),
        sortable: true,
        filterable: true,
    },
    {
        key: 'badges',
        label: t('blocks.badges', 'Labels'),
        sortable: false,
    },
    {
        key: 'key',
        label: t('common.columns.key', 'Key'),
        sortable: true,
        cellClass: () => 'font-mono text-xs text-slate-600',
    },
    {
        key: 'status',
        label: t('common.columns.status', 'Status'),
        sortable: true,
        filterable: true,
    },
    {
        key: 'latest_revision',
        label: t('blocks.latest_revision', 'Laatste publicatie'),
        sortable: false,
    },
    {
        key: 'blocks_count',
        label: t('blocks.blocks_count', 'Instanties'),
        type: 'number',
        sortable: true,
    },
    {
        key: 'updated_at_display',
        label: t('common.columns.updated_at', 'Gewijzigd'),
        type: 'text',
        sortable: true,
    },
    {
        key: 'actions',
        label: t('blocks.actions', 'Acties'),
        align: 'right',
        sortable: false,
    },
]);

function formatDate(value) {
    if (!value) {
        return '-';
    }

    return new Date(value)
        .toLocaleString('en-US', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
        })
        .replace(',', '');
}

function onCellClick(field, id) {
    if (field === 'id') {
        router.visit(route('admin.cms.blocks.edit', { block: id }));
    }
}

function cellClass({ col }) {
    return col.key === 'id' ? 'cursor-pointer' : null;
}

function statusClass(status) {
    if (status === 'published') {
        return 'bg-emerald-50 text-emerald-700';
    }

    if (status === 'archived') {
        return 'bg-slate-100 text-slate-600';
    }

    return 'bg-amber-50 text-amber-700';
}

function statusLabel(status) {
    return t(`common.status.${status}`, status);
}
</script>
