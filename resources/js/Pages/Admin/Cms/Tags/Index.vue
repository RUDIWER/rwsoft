<template>
    <Head :title="t('tags.page_title', 'CMS tags')" />

    <AdminLayout :title="t('tags.page_title', 'CMS tags')">
        <Card class="rounded-none">
            <CardHeader
                class="flex flex-row items-center justify-between gap-3"
            >
                <div>
                    <CardTitle>{{ t('tags.title', 'Tags') }}</CardTitle>
                    <CardDescription>
                        {{
                            t(
                                'tags.description',
                                'Beheer vlakke labels voor CMS-berichten.',
                            )
                        }}
                    </CardDescription>
                </div>
                <Button as-child>
                    <Link :href="route('admin.cms.tags.create')">{{
                        t('tags.new', 'Nieuwe tag')
                    }}</Link>
                </Button>
            </CardHeader>
            <CardContent class="p-0">
                <RwTable
                    table-id="admin-cms-tags-table"
                    :data="tableData"
                    :columns="columns"
                    :initial-height="'calc(100vh - 260px)'"
                    :row-options="[10, 25, 50, 100]"
                    @on-cell-click="onCellClick"
                />
            </CardContent>
        </Card>
    </AdminLayout>
</template>

<script setup>
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
    tags: { type: Array, required: true },
});

const page = usePage();
const { t } = useAdminTranslations('cms_admin_ui');
const locale = computed(() => page.props?.app?.locale || 'nl-BE');

const tableRows = computed(() =>
    props.tags.map((tag) => ({
        ...tag,
        active_label: tag.is_active
            ? t('common.yes', 'Ja')
            : t('common.no', 'Nee'),
        updated_at_display: formatDate(tag.updated_at),
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
        label: t('common.columns.title', 'Titel'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
        clickable: true,
    },
    {
        key: 'slug',
        label: t('common.columns.slug', 'Slug'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
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
        key: 'posts_count',
        label: t('common.columns.posts', 'Berichten'),
        type: 'number',
        selected: true,
        sortable: true,
        filterable: true,
        width: 120,
    },
    {
        key: 'active_label',
        label: t('common.columns.active', 'Actief'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
        width: 100,
    },
    {
        key: 'updated_at_display',
        label: t('common.columns.updated_at', 'Gewijzigd'),
        type: 'text',
        selected: true,
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
    if (!['id', 'title'].includes(field)) {
        return;
    }

    router.visit(route('admin.cms.tags.edit', { id }));
}
</script>
