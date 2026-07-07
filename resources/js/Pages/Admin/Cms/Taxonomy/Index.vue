<template>
    <Head :title="t('taxonomy.page_title', 'CMS categories and tags')" />

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
                            <span class="mdi mdi-tag-multiple text-2xl" />
                        </div>
                        <div class="min-w-0">
                            <CardTitle class="text-lg">
                                {{ t('taxonomy.title', 'Categories and tags') }}
                            </CardTitle>
                            <CardDescription class="mt-1">
                                {{
                                    t(
                                        'taxonomy.description',
                                        'Manage categories and tags for CMS posts.',
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
                            <Link :href="createRoute" class="gap-2">
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
                    :details="pageFlash.details"
                />
            </div>

            <CardContent class="p-0">
                <div class="border-b border-slate-200">
                    <div class="flex flex-wrap gap-4 px-4 sm:px-5">
                        <button
                            v-for="tab in tabs"
                            :key="tab.value"
                            type="button"
                            :class="[
                                '-mb-px border-b-2 px-1 py-2 text-sm font-medium transition',
                                activeTab === tab.value
                                    ? 'border-blue-600 text-blue-700'
                                    : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900',
                            ]"
                            @click="activeTab = tab.value"
                        >
                            {{ tab.label }}
                        </button>
                    </div>
                </div>

                <RwTable
                    v-if="activeTab === 'categories'"
                    table-id="admin-cms-taxonomy-categories-table"
                    :data="categoryTableData"
                    :columns="categoryColumns"
                    :initial-height="'calc(100vh - 350px)'"
                    :rows-per-page="25"
                    sort-field="id"
                    sort-order="desc"
                    :row-options="[25, 50, 100, 250]"
                    :cell-class="cellClass"
                    excel="true"
                    @on-cell-click="onCategoryCellClick"
                />

                <RwTable
                    v-else
                    table-id="admin-cms-taxonomy-tags-table"
                    :data="tagTableData"
                    :columns="tagColumns"
                    :initial-height="'calc(100vh - 350px)'"
                    :rows-per-page="25"
                    sort-field="id"
                    sort-order="desc"
                    :row-options="[25, 50, 100, 250]"
                    :cell-class="cellClass"
                    excel="true"
                    @on-cell-click="onTagCellClick"
                />
            </CardContent>
        </Card>
    </AdminLayout>
</template>

<script setup>
import RwFlashMessage from '@/Components/RwFlashMessage.vue';
import RwTable from '@/Components/RwTable.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    activeTab: { type: String, default: 'categories' },
    categories: { type: Array, required: true },
    tags: { type: Array, required: true },
});

const page = usePage();
const { t } = useAdminTranslations('cms_admin_ui');
const { t: commonT } = useAdminTranslations('admin_common_ui');
const locale = computed(() => page.props?.app?.locale || 'nl-BE');
const activeTab = ref(
    ['categories', 'tags'].includes(props.activeTab)
        ? props.activeTab
        : 'categories',
);

const tabs = computed(() => [
    {
        value: 'categories',
        label: t('taxonomy.tabs.categories', 'Categories'),
    },
    { value: 'tags', label: t('taxonomy.tabs.tags', 'Tags') },
]);
const createRoute = computed(() =>
    activeTab.value === 'categories'
        ? route('admin.cms.categories.create')
        : route('admin.cms.tags.create'),
);
const pageFlash = computed(() => {
    const flash = page.props?.flash || {};

    if (flash.error) {
        return {
            type: 'danger',
            message: flash.error,
            details: flash.details || [],
        };
    }

    if (flash.warning) {
        return {
            type: 'warning',
            message: flash.warning,
            details: flash.details || [],
        };
    }

    if (flash.status) {
        return {
            type: 'success',
            message: flash.status,
            details: flash.details || [],
        };
    }

    return { type: '', message: '', details: [] };
});
const categoryRows = computed(() =>
    props.categories.map((category) => ({
        ...category,
        parent_title: category.parent?.title ?? '-',
        active_label: category.is_active
            ? t('common.yes', 'Yes')
            : t('common.no', 'No'),
        active_color: category.is_active ? 'green' : 'red',
        updated_at_display: formatDateTime(category.updated_at),
    })),
);
const tagRows = computed(() =>
    props.tags.map((tag) => ({
        ...tag,
        active_label: tag.is_active
            ? t('common.yes', 'Yes')
            : t('common.no', 'No'),
        active_color: tag.is_active ? 'green' : 'red',
        updated_at_display: formatDateTime(tag.updated_at),
    })),
);
const categoryTableData = computed(() => ({
    data: categoryRows.value,
    total: categoryRows.value.length,
}));
const tagTableData = computed(() => ({
    data: tagRows.value,
    total: tagRows.value.length,
}));
const categoryColumns = computed(() => [
    baseIdColumn(),
    {
        key: 'title',
        label: t('common.columns.title', 'Title'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
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
        key: 'parent_title',
        label: t('common.columns.parent', 'Parent'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
    },
    {
        key: 'posts_count',
        label: t('common.columns.posts', 'Posts'),
        type: 'number',
        selected: true,
        sortable: true,
        filterable: true,
        width: 120,
    },
    activeColumn(),
    updatedColumn(),
]);
const tagColumns = computed(() => [
    baseIdColumn(),
    {
        key: 'title',
        label: t('common.columns.title', 'Title'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
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
        key: 'posts_count',
        label: t('common.columns.posts', 'Posts'),
        type: 'number',
        selected: true,
        sortable: true,
        filterable: true,
        width: 120,
    },
    activeColumn(),
    updatedColumn(),
]);

function baseIdColumn() {
    return {
        key: 'id',
        label: t('common.columns.id', 'ID'),
        type: 'number',
        selected: true,
        sortable: true,
        filterable: true,
        clickable: true,
        width: 90,
    };
}

function activeColumn() {
    return {
        key: 'active_label',
        label: t('common.columns.active', 'Active'),
        type: 'chip',
        colorKey: 'active_color',
        selected: true,
        sortable: true,
        filterable: true,
        width: 110,
    };
}

function updatedColumn() {
    return {
        key: 'updated_at_display',
        label: t('common.columns.updated_at', 'Updated'),
        type: 'text',
        selected: true,
        sortable: true,
        filterable: true,
    };
}

function formatDateTime(value) {
    if (!value) {
        return '-';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '-';
    }

    return new Intl.DateTimeFormat(locale.value, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

function onCategoryCellClick(field, id) {
    if (field !== 'id') {
        return;
    }

    router.visit(route('admin.cms.categories.edit', { id }));
}

function onTagCellClick(field, id) {
    if (field !== 'id') {
        return;
    }

    router.visit(route('admin.cms.tags.edit', { id }));
}

function cellClass({ col }) {
    return col.clickable ? 'cursor-pointer' : null;
}
</script>
