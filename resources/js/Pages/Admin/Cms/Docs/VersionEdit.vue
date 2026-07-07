<template>
    <AdminLayout :title="pageTitle" :suppress-flash="true">
        <Head :title="pageTitle" />
        <form @submit.prevent="submit">
            <Card
                class="flex h-[calc(100vh-8rem)] flex-col overflow-hidden rounded-none shadow-none"
            >
                <CardHeader
                    class="shrink-0 gap-0 border-b border-slate-200 p-0"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-3 px-4 py-4 sm:px-5"
                    >
                        <div class="flex min-w-0 items-start gap-3">
                            <div
                                class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-blue-50 text-blue-700 ring-1 ring-blue-100"
                                aria-hidden="true"
                            >
                                <span class="mdi mdi-source-branch text-2xl" />
                            </div>
                            <div class="min-w-0">
                                <CardTitle class="text-lg">{{
                                    pageTitle
                                }}</CardTitle>
                                <CardDescription class="mt-1">{{
                                    t(
                                        'docs.versions.form_description',
                                        'Manage a documentation version.',
                                    )
                                }}</CardDescription>
                            </div>
                        </div>
                        <div class="flex flex-wrap justify-end gap-2">
                            <AdminFormBackButton
                                :href="route('admin.cms.docs.index')"
                                :dirty="form.isDirty"
                                :processing="form.processing"
                                :label="commonT('actions.back', 'Back')"
                                @save="submit"
                            />
                            <AdminFormSaveButton
                                :dirty="form.isDirty"
                                :processing="form.processing"
                                :label="commonT('actions.save', 'Save')"
                            />
                        </div>
                    </div>
                </CardHeader>

                <RecordMeta
                    :id="version?.id"
                    :created-at="version?.created_at"
                    :updated-at="version?.updated_at"
                />
                <FlashZone />

                <CardContent class="flex min-h-0 flex-1 flex-col p-4 sm:p-5">
                    <div class="grid gap-5 lg:grid-cols-2">
                        <div class="grid gap-2">
                            <Label
                                for="cms_doc_collection_id"
                                class="flex items-center gap-1"
                                ><span class="text-red-600" aria-hidden="true"
                                    >*</span
                                >{{
                                    t('docs.columns.collection', 'Collection')
                                }}</Label
                            >
                            <RwAutoCompleteInput
                                id="cms_doc_collection_id"
                                v-model="form.cms_doc_collection_id"
                                :items="collectionOptions"
                                item-title="name"
                                item-value="id"
                                :search-fields="['name']"
                                class="bg-yellow-50"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="label" class="flex items-center gap-1"
                                ><span class="text-red-600" aria-hidden="true"
                                    >*</span
                                >{{
                                    t(
                                        'docs.fields.version_label',
                                        'Version label',
                                    )
                                }}</Label
                            >
                            <Input
                                id="label"
                                v-model="form.label"
                                class="bg-yellow-50"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="slug" class="flex items-center gap-1"
                                ><span class="text-red-600" aria-hidden="true"
                                    >*</span
                                >{{ commonT('columns.slug', 'Slug') }}</Label
                            >
                            <Input
                                id="slug"
                                v-model="form.slug"
                                class="bg-yellow-50"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="sort_order">{{
                                t('docs.fields.sort_order', 'Sort order')
                            }}</Label>
                            <Input
                                id="sort_order"
                                v-model="form.sort_order"
                                type="number"
                                min="0"
                            />
                        </div>
                        <label
                            class="flex items-center gap-2 text-sm font-medium text-slate-700"
                        >
                            <input
                                v-model="form.is_default"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 text-blue-600"
                            />
                            {{
                                t(
                                    'docs.fields.default_version',
                                    'Default version',
                                )
                            }}
                        </label>
                        <label
                            class="flex items-center gap-2 text-sm font-medium text-slate-700"
                        >
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 text-blue-600"
                            />
                            {{ commonT('columns.active', 'Active') }}
                        </label>
                    </div>
                </CardContent>
            </Card>
        </form>
    </AdminLayout>
</template>

<script setup>
import RwAutoCompleteInput from '@/Components/RwAutoCompleteInput.vue';
import AdminFormBackButton from '@/Components/Admin/Form/AdminFormBackButton.vue';
import AdminFormSaveButton from '@/Components/Admin/Form/AdminFormSaveButton.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import FlashZone from '@/Pages/Admin/Cms/Docs/Partials/FlashZone.vue';
import RecordMeta from '@/Pages/Admin/Cms/Docs/Partials/RecordMeta.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    version: { type: Object, default: null },
    collectionOptions: { type: Array, required: true },
});
const { t } = useAdminTranslations('cms_admin_ui');
const { t: commonT } = useAdminTranslations('admin_common_ui');
const pageTitle = computed(() =>
    props.version?.id
        ? t('docs.versions.edit_title', 'Edit documentation version')
        : t('docs.versions.create_title', 'Create documentation version'),
);
const form = useForm({
    cms_doc_collection_id:
        props.version?.cms_doc_collection_id ??
        props.collectionOptions[0]?.id ??
        null,
    label: props.version?.label ?? '',
    slug: props.version?.slug ?? '',
    is_default: props.version?.is_default ?? false,
    is_active: props.version?.is_active ?? true,
    sort_order: props.version?.sort_order ?? 0,
});

function submit() {
    form.post(
        route('admin.cms.docs.versions.store', {
            version: props.version?.id ?? 0,
        }),
        { preserveScroll: true },
    );
}
</script>
