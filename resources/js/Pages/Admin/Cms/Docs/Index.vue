<template>
    <Head :title="t('docs.page_title', 'Documentation')" />

    <AdminLayout
        :title="t('docs.page_title', 'Documentation')"
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
                            <span
                                class="mdi mdi-book-open-page-variant text-2xl"
                            />
                        </div>
                        <div class="min-w-0">
                            <CardTitle class="text-lg">
                                {{
                                    t('docs.collections_title', 'Documentation')
                                }}
                            </CardTitle>
                            <CardDescription class="mt-1">
                                {{
                                    t(
                                        'docs.collections_description',
                                        'Choose a documentation collection to manage its pages.',
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
                            class="gap-2 border-blue-200 text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800"
                        >
                            <Link
                                :href="
                                    route('admin.cms.docs.collections.create')
                                "
                            >
                                <span
                                    class="mdi mdi-folder-plus text-base text-blue-700"
                                    aria-hidden="true"
                                />
                                {{
                                    t(
                                        'docs.actions.new_collection',
                                        'Collection',
                                    )
                                }}
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

            <CardContent class="p-4 sm:p-5">
                <div
                    class="mb-4 grid gap-2 rounded border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700 sm:grid-cols-3"
                >
                    <div>
                        <span class="font-semibold">
                            {{ t('docs.collections_count', 'Collections') }}:
                        </span>
                        {{ collections.length }}
                    </div>
                    <div>
                        <span class="font-semibold">
                            {{ t('docs.versions_count', 'Versions') }}:
                        </span>
                        {{ totalVersions }}
                    </div>
                    <div>
                        <span class="font-semibold">
                            {{ t('docs.pages_count', 'Pages') }}:
                        </span>
                        {{ totalPages }}
                    </div>
                </div>

                <div
                    v-if="templateInfo.length > 0"
                    class="mb-4 grid gap-2 rounded border border-blue-100 bg-blue-50 p-3 text-sm text-blue-900"
                >
                    <div class="font-semibold">
                        {{
                            t('docs.templates.title', 'Documentation templates')
                        }}
                    </div>
                    <div class="grid gap-2 md:grid-cols-3">
                        <div
                            v-for="template in templateInfo"
                            :key="template.id"
                            class="rounded border border-blue-100 bg-white/70 p-2"
                        >
                            <div class="font-semibold">
                                {{ template.template_key }} ·
                                {{ template.locale.toUpperCase() }}
                            </div>
                            <div class="text-xs text-blue-800">
                                {{ t('docs.templates.layout', 'Layout') }}:
                                {{ template.layout?.name || '-' }}
                            </div>
                            <Link
                                class="mt-1 inline-flex text-xs font-semibold text-blue-700 underline-offset-2 hover:underline"
                                :href="template.edit_url"
                            >
                                {{ t('docs.templates.edit', 'Edit template') }}
                            </Link>
                        </div>
                    </div>
                </div>

                <div
                    v-if="collections.length === 0"
                    class="rounded border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-600"
                >
                    {{
                        t(
                            'docs.empty_collections',
                            'No documentation collections have been created yet.',
                        )
                    }}
                </div>

                <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div
                        v-for="collection in collections"
                        :key="collection.id"
                        class="group grid gap-4 rounded-lg border border-slate-200 bg-white p-4 transition hover:border-blue-200 hover:bg-blue-50/30"
                    >
                        <Link
                            class="grid min-h-40 gap-3 text-left"
                            :href="
                                route('admin.cms.docs.collections.pages', {
                                    collection: collection.id,
                                })
                            "
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h2
                                        class="truncate text-base font-semibold text-slate-950 group-hover:text-blue-800"
                                    >
                                        {{ collection.name }}
                                    </h2>
                                    <p class="mt-1 text-xs text-slate-500">
                                        /{{ collection.slug }}
                                    </p>
                                </div>
                                <span
                                    class="rounded-full border px-2.5 py-0.5 text-xs font-medium"
                                    :class="
                                        collection.is_active
                                            ? 'border-green-200 bg-green-50 text-green-700'
                                            : 'border-slate-200 bg-slate-50 text-slate-600'
                                    "
                                >
                                    {{ statusLabel(collection) }}
                                </span>
                            </div>

                            <p class="line-clamp-3 text-sm text-slate-600">
                                {{
                                    collection.description ||
                                    t(
                                        'docs.collection_no_description',
                                        'No description added yet.',
                                    )
                                }}
                            </p>

                            <div
                                class="grid gap-2 border-t border-slate-100 pt-3 text-xs text-slate-600 sm:grid-cols-2"
                            >
                                <div>
                                    <span class="font-semibold text-slate-800">
                                        {{ collection.versions_count }}
                                    </span>
                                    {{ t('docs.versions_count', 'Versions') }}
                                </div>
                                <div>
                                    <span class="font-semibold text-slate-800">
                                        {{ collection.pages_count }}
                                    </span>
                                    {{ t('docs.pages_count', 'Pages') }}
                                </div>
                                <div>
                                    <span class="font-semibold text-green-700">
                                        {{ collection.published_pages_count }}
                                    </span>
                                    {{ t('docs.published_count', 'Published') }}
                                </div>
                                <div>
                                    <span class="font-semibold text-slate-800">
                                        {{ collection.draft_pages_count }}
                                    </span>
                                    {{ t('docs.draft_count', 'Draft') }}
                                </div>
                            </div>
                        </Link>

                        <div class="flex flex-wrap justify-end gap-2">
                            <Button
                                v-if="collection.published_pages_count > 0"
                                as-child
                                variant="outline"
                                class="gap-2 border-green-200 text-green-700 shadow-none hover:bg-green-50 hover:text-green-800"
                            >
                                <a
                                    :href="collection.preview_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <span
                                        class="mdi mdi-eye-outline text-base"
                                        aria-hidden="true"
                                    />
                                    {{ t('docs.actions.preview', 'Preview') }}
                                </a>
                            </Button>
                            <Button
                                v-else
                                type="button"
                                variant="outline"
                                class="gap-2 border-slate-200 text-slate-400 shadow-none"
                                disabled
                                :title="
                                    t(
                                        'docs.preview_unavailable',
                                        'Publish at least one page before opening a preview.',
                                    )
                                "
                            >
                                <span
                                    class="mdi mdi-eye-off-outline text-base"
                                    aria-hidden="true"
                                />
                                {{ t('docs.actions.preview', 'Preview') }}
                            </Button>
                            <Button
                                as-child
                                variant="outline"
                                class="gap-2 border-blue-200 text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800"
                            >
                                <Link
                                    :href="
                                        route(
                                            'admin.cms.docs.collections.pages',
                                            { collection: collection.id },
                                        )
                                    "
                                >
                                    <span
                                        class="mdi mdi-table-eye text-base"
                                        aria-hidden="true"
                                    />
                                    {{
                                        t(
                                            'docs.actions.open_pages',
                                            'Edit content',
                                        )
                                    }}
                                </Link>
                            </Button>
                            <Button
                                as-child
                                variant="outline"
                                class="gap-2 border-slate-200 text-slate-700 shadow-none hover:bg-slate-50 hover:text-slate-900"
                            >
                                <Link
                                    :href="
                                        route(
                                            'admin.cms.docs.collections.edit',
                                            { collection: collection.id },
                                        )
                                    "
                                >
                                    <span
                                        class="mdi mdi-pencil text-base"
                                        aria-hidden="true"
                                    />
                                    {{ commonT('actions.edit', 'Edit') }}
                                </Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </AdminLayout>
</template>

<script setup>
import RwFlashMessage from '@/Components/RwFlashMessage.vue';
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
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    collections: { type: Array, required: true },
    templateInfo: { type: Array, default: () => [] },
});

const page = usePage();
const { t } = useAdminTranslations('cms_admin_ui');
const { t: commonT } = useAdminTranslations('admin_common_ui');

const totalVersions = computed(() =>
    props.collections.reduce(
        (total, collection) => total + Number(collection.versions_count || 0),
        0,
    ),
);
const totalPages = computed(() =>
    props.collections.reduce(
        (total, collection) => total + Number(collection.pages_count || 0),
        0,
    ),
);

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

function statusLabel(collection) {
    return collection?.is_active
        ? t('common.status.active', 'Active')
        : t('common.status.inactive', 'Inactive');
}
</script>
