<template>
    <Head :title="t('themes.page_title', 'CMS thema\'s')" />

    <AdminLayout
        :title="t('themes.page_title', 'CMS thema\'s')"
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
                            <span class="mdi mdi-format-line-style text-2xl" />
                        </div>
                        <div class="min-w-0">
                            <CardTitle class="text-lg">
                                {{ t('themes.title', "Thema's") }}
                            </CardTitle>
                            <CardDescription class="mt-1">
                                {{
                                    t(
                                        'themes.description',
                                        'Beheer CSS themes voor deze website. system.css blijft altijd de basis.',
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
                                :href="route('admin.cms.themes.create')"
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
                class="border-b border-slate-200 bg-slate-50/50 px-4 py-3 sm:px-5"
            >
                <div class="grid max-w-xl gap-2">
                    <Label
                        for="theme_zip"
                        class="text-xs font-medium text-slate-700"
                        >{{
                            t('themes.import_title', 'Thema importeren')
                        }}</Label
                    >
                    <div class="flex items-center gap-3">
                        <input
                            id="theme_zip"
                            ref="fileInputRef"
                            type="file"
                            class="hidden"
                            accept=".zip,application/zip"
                            @input="
                                importForm.theme_zip =
                                    $event.target.files?.[0] ?? null
                            "
                        />
                        <Button
                            type="button"
                            variant="outline"
                            class="shrink-0"
                            @click="fileInputRef.click()"
                        >
                            {{ t('themes.zip_file', 'ZIP bestand') }}
                        </Button>
                        <span
                            class="min-w-0 flex-grow truncate text-sm text-slate-500"
                        >
                            {{
                                importForm.theme_zip?.name ||
                                t(
                                    'themes.no_file_chosen',
                                    'Geen bestand gekozen',
                                )
                            }}
                        </span>
                        <Button
                            type="submit"
                            variant="outline"
                            size="icon"
                            :disabled="
                                importForm.processing || !importForm.theme_zip
                            "
                            :title="t('themes.import', 'Importeren')"
                            @click="submitImport"
                        >
                            <span
                                class="mdi mdi-cloud-download text-lg"
                                aria-hidden="true"
                            />
                        </Button>
                    </div>
                    <p
                        v-if="importForm.errors.theme_zip"
                        class="text-sm text-red-600"
                    >
                        {{ importForm.errors.theme_zip }}
                    </p>
                </div>
            </div>

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
                    :data="tableData"
                    :columns="columns"
                    table-id="cms-themes-table-v2"
                    :initial-height="'calc(100vh - 350px)'"
                    :rows-per-page="25"
                    @on-cell-click="onCellClick"
                    :cell-class="cellClass"
                >
                    <template #col-name="{ row: theme }">
                        <div class="font-medium text-slate-900">
                            {{ theme.name }}
                        </div>
                        <div class="text-xs text-slate-500">
                            {{
                                theme.description ||
                                t('themes.no_description', 'Geen omschrijving')
                            }}
                        </div>
                    </template>

                    <template #col-actions="{ row: theme }">
                        <div class="flex flex-wrap justify-end gap-2">
                            <Button as-child variant="outline" size="sm">
                                <Link
                                    :href="
                                        route('admin.cms.themes.edit', {
                                            theme: theme.id,
                                        })
                                    "
                                >
                                    {{ t('themes.edit', 'Edit') }}
                                </Link>
                            </Button>
                            <Button
                                v-if="theme.preview_url"
                                as-child
                                variant="outline"
                                size="sm"
                            >
                                <a
                                    :href="theme.preview_url"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    {{ t('themes.preview', 'Preview') }}
                                </a>
                            </Button>
                            <Button
                                v-if="!theme.is_active"
                                type="button"
                                variant="destructive"
                                size="sm"
                                @click="deleteTheme(theme)"
                            >
                                {{ t('themes.delete', 'Delete') }}
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
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import RwTable from '@/Components/RwTable.vue';
import { computed, ref } from 'vue';
import RwFlashMessage from '@/Components/RwFlashMessage.vue';

const props = defineProps({
    themes: { type: Array, required: true },
});

const page = usePage();
const { t } = useAdminTranslations('cms_admin_ui');
const { t: commonT } = useAdminTranslations('admin_common_ui');
const fileInputRef = ref(null);

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

const formatDate = (value) => {
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
};

const tableRows = computed(() =>
    props.themes.map((theme) => ({
        ...theme,
        active_label: theme.is_active
            ? t('common.yes', 'Ja')
            : t('common.no', 'Nee'),
        active_color: theme.is_active ? 'green' : 'red',
        updated_at_display: formatDate(theme.updated_at),
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
        label: t('common.columns.name', 'Name'),
        sortable: true,
    },
    {
        key: 'key',
        label: t('common.columns.key', 'Key'),
        sortable: true,
        cellClass: () => 'font-mono text-xs text-slate-600',
    },
    {
        key: 'active_label',
        label: t('common.columns.status', 'Status'),
        type: 'chip',
        colorKey: 'active_color',
        chipOnlyWhenColor: true,
        sortable: true,
        filterable: true,
        width: 100,
    },
    {
        key: 'version',
        label: t('themes.version', 'Versie'),
        sortable: true,
    },
    {
        key: 'updated_at_display',
        label: t('common.columns.updated_at', 'Updated'),
        type: 'text',
        sortable: true,
    },
    {
        key: 'actions',
        label: t('themes.actions', 'Actions'),
        align: 'right',
        sortable: false,
    },
]);

const importForm = useForm({
    theme_zip: null,
});

function onCellClick(field, id) {
    if (field === 'id') {
        router.visit(route('admin.cms.themes.edit', { theme: id }));
    }
}

function cellClass({ col }) {
    return col.key === 'id' ? 'cursor-pointer' : null;
}

function submitImport() {
    importForm.post(route('admin.cms.themes.import'), {
        forceFormData: true,
        onSuccess: () => importForm.reset(),
    });
}

function deleteTheme(theme) {
    if (
        !window.confirm(
            t(
                'themes.delete_confirm',
                'Theme ":name" verwijderen? Dit kan niet ongedaan gemaakt worden.',
                { name: theme.name },
            ),
        )
    ) {
        return;
    }

    router.post(route('admin.cms.themes.delete', { theme: theme.id }));
}
</script>
