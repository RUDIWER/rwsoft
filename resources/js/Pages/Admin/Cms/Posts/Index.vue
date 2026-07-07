<template>
    <Head :title="t('posts.page_title', 'CMS berichten')" />

    <AdminLayout :title="t('posts.page_title', 'CMS berichten')">
        <Card class="rounded-none">
            <CardHeader
                class="flex flex-row items-center justify-between gap-3"
            >
                <div>
                    <CardTitle>{{ t('posts.title', 'Berichten') }}</CardTitle>
                    <CardDescription>
                        {{
                            t(
                                'posts.description',
                                'Beheer nieuws- en blogberichten.',
                            )
                        }}
                    </CardDescription>
                </div>
                <Button as-child>
                    <Link :href="route('admin.cms.posts.create')">
                        {{ t('posts.new', 'Nieuw bericht') }}
                    </Link>
                </Button>
            </CardHeader>
            <CardContent class="p-0">
                <RwTable
                    table-id="admin-cms-posts-table"
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
    posts: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const { t } = useAdminTranslations('cms_admin_ui');
const locale = computed(() => page.props?.app?.locale || 'nl-BE');

const statusLabels = computed(() => ({
    draft: t('common.status.draft', 'Draft'),
    published: t('common.status.published', 'Published'),
    archived: t('common.status.archived', 'Archived'),
}));

const tableRows = computed(() =>
    props.posts.map((post) => ({
        ...post,
        status_label: statusLabels.value[post.status] ?? post.status,
        featured_label: post.is_featured
            ? t('common.yes', 'Yes')
            : t('common.no', 'No'),
        published_at_display: formatDate(post.published_at),
        updated_at_display: formatDate(post.updated_at),
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
        label: t('common.columns.locale', 'Language'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
        width: 100,
    },
    {
        key: 'status_label',
        label: t('common.columns.status', 'Status'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
    },
    {
        key: 'featured_label',
        label: t('posts.columns.featured', 'Uitgelicht'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
        width: 120,
    },
    {
        key: 'published_at_display',
        label: t('common.columns.published_at', 'Publication'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
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

    router.visit(route('admin.cms.posts.edit', { id }));
}
</script>
