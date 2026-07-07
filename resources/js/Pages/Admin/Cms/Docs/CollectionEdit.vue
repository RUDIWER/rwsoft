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
                                <span
                                    class="mdi mdi-folder-bookmark text-2xl"
                                />
                            </div>
                            <div class="min-w-0">
                                <CardTitle class="text-lg">{{
                                    pageTitle
                                }}</CardTitle>
                                <CardDescription class="mt-1">{{
                                    t(
                                        'docs.collections.form_description',
                                        'Manage a documentation collection.',
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
                    :id="collection?.id"
                    :created-at="collection?.created_at"
                    :updated-at="collection?.updated_at"
                />
                <FlashZone />

                <CardContent class="flex min-h-0 flex-1 flex-col p-4 sm:p-5">
                    <div class="grid gap-5 lg:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="name" class="flex items-center gap-1"
                                ><span class="text-red-600" aria-hidden="true"
                                    >*</span
                                >{{ commonT('columns.name', 'Name') }}</Label
                            >
                            <Input
                                id="name"
                                v-model="form.name"
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
                        <div class="grid gap-2 lg:col-span-2">
                            <Label for="description">{{
                                commonT('columns.description', 'Description')
                            }}</Label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="4"
                                class="min-h-24 rounded-md border border-slate-200 px-3 py-2 text-sm shadow-none focus:outline-none focus:ring-2 focus:ring-blue-500"
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

const props = defineProps({ collection: { type: Object, default: null } });
const { t } = useAdminTranslations('cms_admin_ui');
const { t: commonT } = useAdminTranslations('admin_common_ui');
const pageTitle = computed(() =>
    props.collection?.id
        ? t('docs.collections.edit_title', 'Edit documentation collection')
        : t('docs.collections.create_title', 'Create documentation collection'),
);
const form = useForm({
    name: props.collection?.name ?? '',
    slug: props.collection?.slug ?? '',
    description: props.collection?.description ?? '',
    is_active: props.collection?.is_active ?? true,
    sort_order: props.collection?.sort_order ?? 0,
});

function submit() {
    form.post(
        route('admin.cms.docs.collections.store', {
            collection: props.collection?.id ?? 0,
        }),
        { preserveScroll: true },
    );
}
</script>
