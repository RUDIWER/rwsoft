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
                                    class="mdi mdi-file-document-edit text-2xl"
                                />
                            </div>
                            <div class="min-w-0">
                                <CardTitle class="text-lg">{{
                                    pageTitle
                                }}</CardTitle>
                                <CardDescription class="mt-1">{{
                                    t(
                                        'docs.pages.form_description',
                                        'Write markdown documentation with versions, translations and page navigation.',
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
                    :id="docPage?.id"
                    :created-at="docPage?.created_at"
                    :updated-at="docPage?.updated_at"
                />
                <FlashZone />

                <CardContent class="flex min-h-0 flex-1 flex-col p-0">
                    <div class="shrink-0 border-b border-slate-200">
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

                    <div class="min-h-0 flex-1 overflow-auto p-4 sm:p-5">
                        <section
                            v-if="activeTab === 'basis'"
                            class="grid gap-5 lg:grid-cols-2"
                        >
                            <div class="space-y-2 lg:col-span-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="text-xs font-medium text-slate-600"
                                    >
                                        {{
                                            t(
                                                'content_form.translation_status_label',
                                                'Translations:',
                                            )
                                        }}
                                    </span>
                                    <div
                                        v-if="isEditMode"
                                        class="flex flex-wrap gap-2"
                                    >
                                        <button
                                            v-for="item in translationStatusItems"
                                            :key="item.key"
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-full border px-2 py-1 text-xs font-medium transition hover:-translate-y-px hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1"
                                            :class="
                                                translationStatusClass(item)
                                            "
                                            :title="translationChipTitle(item)"
                                            :disabled="
                                                translationForm.processing ||
                                                item.type === 'current'
                                            "
                                            @click="
                                                handleTranslationChipClick(item)
                                            "
                                        >
                                            <span
                                                class="h-1.5 w-1.5 rounded-full"
                                                :class="
                                                    translationStatusDotClass(
                                                        item.status,
                                                    )
                                                "
                                                aria-hidden="true"
                                            />
                                            {{ item.label }}
                                        </button>
                                    </div>
                                </div>
                                <p
                                    v-if="!isEditMode"
                                    class="text-xs text-slate-500"
                                >
                                    {{
                                        t(
                                            'pages.form.save_before_translations',
                                            'Save the page first to manage translations.',
                                        )
                                    }}
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <Label
                                    for="cms_doc_version_id"
                                    class="flex items-center gap-1"
                                    ><span
                                        class="text-red-600"
                                        aria-hidden="true"
                                        >*</span
                                    >{{
                                        t('docs.columns.version', 'Version')
                                    }}</Label
                                >
                                <RwAutoCompleteInput
                                    id="cms_doc_version_id"
                                    v-model="form.cms_doc_version_id"
                                    :items="versionOptions"
                                    item-title="label"
                                    item-value="id"
                                    :search-fields="['label']"
                                    class="bg-yellow-50"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="parent_id">{{
                                    commonT('columns.parent', 'Parent')
                                }}</Label>
                                <RwAutoCompleteInput
                                    id="parent_id"
                                    v-model="form.parent_id"
                                    :items="parentOptions"
                                    item-title="title"
                                    item-value="id"
                                    :search-fields="['title']"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label
                                    for="title"
                                    class="flex items-center gap-1"
                                    ><span
                                        class="text-red-600"
                                        aria-hidden="true"
                                        >*</span
                                    >{{
                                        commonT('columns.title', 'Title')
                                    }}</Label
                                >
                                <Input
                                    id="title"
                                    v-model="form.title"
                                    class="bg-yellow-50"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label
                                    for="slug"
                                    class="flex items-center gap-1"
                                    ><span
                                        class="text-red-600"
                                        aria-hidden="true"
                                        >*</span
                                    >{{
                                        commonT('columns.slug', 'Slug')
                                    }}</Label
                                >
                                <Input
                                    id="slug"
                                    v-model="form.slug"
                                    class="bg-yellow-50"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label
                                    for="path"
                                    class="flex items-center gap-1"
                                    ><span
                                        class="text-red-600"
                                        aria-hidden="true"
                                        >*</span
                                    >{{ t('docs.columns.path', 'Path') }}</Label
                                >
                                <Input
                                    id="path"
                                    v-model="form.path"
                                    class="bg-yellow-50"
                                    :placeholder="
                                        t(
                                            'docs.fields.path_placeholder',
                                            'getting-started/installation',
                                        )
                                    "
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label
                                    for="locale"
                                    class="flex items-center gap-1"
                                    ><span
                                        class="text-red-600"
                                        aria-hidden="true"
                                        >*</span
                                    >{{
                                        commonT('columns.locale', 'Language')
                                    }}</Label
                                >
                                <RwAutoCompleteInput
                                    id="locale"
                                    v-model="form.locale"
                                    :items="localeOptions"
                                    item-title="label"
                                    item-value="value"
                                    :search-fields="['label', 'value']"
                                    class="bg-yellow-50"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label
                                    for="status"
                                    class="flex items-center gap-1"
                                    ><span
                                        class="text-red-600"
                                        aria-hidden="true"
                                        >*</span
                                    >{{
                                        commonT('columns.status', 'Status')
                                    }}</Label
                                >
                                <RwAutoCompleteInput
                                    id="status"
                                    v-model="form.status"
                                    :items="statusOptions"
                                    item-title="label"
                                    item-value="value"
                                    :search-fields="['label', 'value']"
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
                                    v-model="form.noindex"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300 text-blue-600"
                                />
                                {{
                                    t(
                                        'docs.fields.noindex',
                                        'Exclude from search engines',
                                    )
                                }}
                            </label>
                        </section>

                        <section
                            v-if="activeTab === 'content'"
                            class="grid gap-4"
                        >
                            <div
                                class="grid gap-2 rounded border border-slate-200 bg-slate-50 p-3 lg:grid-cols-[minmax(0,1fr)_auto_auto]"
                            >
                                <div class="grid gap-2">
                                    <Label for="media_insert">{{
                                        t(
                                            'docs.fields.insert_media',
                                            'Insert media',
                                        )
                                    }}</Label>
                                    <RwAutoCompleteInput
                                        id="media_insert"
                                        v-model="selectedMediaId"
                                        :items="mediaOptions"
                                        item-title="label"
                                        item-value="id"
                                        :search-fields="['label']"
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="self-end shadow-none"
                                    @click="insertMediaToken"
                                >
                                    <span
                                        class="mdi mdi-image-plus text-base"
                                        aria-hidden="true"
                                    />
                                    {{
                                        t(
                                            'docs.actions.insert_media',
                                            'Insert image',
                                        )
                                    }}
                                </Button>
                                <Button
                                    v-if="isEditMode"
                                    type="button"
                                    variant="outline"
                                    class="self-end border-blue-200 text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800"
                                    :disabled="
                                        !contentChanged ||
                                        translationForm.processing ||
                                        targetLocalesForAi.length === 0
                                    "
                                    @click="createTranslation()"
                                >
                                    <span
                                        v-if="translationForm.processing"
                                        class="mdi mdi-loading animate-spin text-base"
                                        aria-hidden="true"
                                    />
                                    <span
                                        v-else
                                        class="mdi mdi-translate text-base"
                                        aria-hidden="true"
                                    />
                                    {{
                                        t(
                                            'docs.actions.translate_content_ai',
                                            'Update translations with AI',
                                        )
                                    }}
                                </Button>
                            </div>
                            <div class="grid gap-2">
                                <Label
                                    for="body"
                                    class="flex items-center gap-1"
                                    ><span
                                        class="text-red-600"
                                        aria-hidden="true"
                                        >*</span
                                    >{{
                                        t(
                                            'docs.fields.markdown_body',
                                            'Markdown content',
                                        )
                                    }}</Label
                                >
                                <textarea
                                    id="body"
                                    ref="bodyInput"
                                    v-model="form.body"
                                    rows="24"
                                    class="min-h-[28rem] rounded-md border border-slate-200 bg-yellow-50 px-3 py-2 font-mono text-sm shadow-none focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                                <p class="text-xs text-slate-600">
                                    {{
                                        t(
                                            'docs.fields.markdown_help',
                                            'Use headings for the page table of contents. Admonitions are supported with :::tip, :::warning and :::danger blocks.',
                                        )
                                    }}
                                </p>
                            </div>
                        </section>

                        <section
                            v-if="activeTab === 'seo'"
                            class="grid gap-5 lg:grid-cols-2"
                        >
                            <div class="grid gap-2">
                                <Label for="seo_title">{{
                                    t('pages.form.seo_title', 'SEO title')
                                }}</Label>
                                <Input
                                    id="seo_title"
                                    v-model="form.seo_title"
                                />
                            </div>
                            <div class="grid gap-2 lg:col-span-2">
                                <Label for="seo_description">{{
                                    t(
                                        'pages.form.seo_description',
                                        'SEO description',
                                    )
                                }}</Label>
                                <textarea
                                    id="seo_description"
                                    v-model="form.seo_description"
                                    rows="4"
                                    class="min-h-24 rounded-md border border-slate-200 px-3 py-2 text-sm shadow-none focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>
                        </section>
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
import { Button } from '@/components/ui/button';
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
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    docPage: { type: Object, default: null },
    versionOptions: { type: Array, required: true },
    parentOptions: { type: Array, required: true },
    activeLanguages: { type: Array, required: true },
    availableLocales: { type: Array, required: true },
    translations: { type: Array, required: true },
    missingLanguages: { type: Array, required: true },
    mediaOptions: { type: Array, required: true },
});

const { t } = useAdminTranslations('cms_admin_ui');
const { t: commonT } = useAdminTranslations('admin_common_ui');
const activeTab = ref('basis');
const selectedMediaId = ref(null);
const bodyInput = ref(null);
const pageTitle = computed(() =>
    props.docPage?.id
        ? t('docs.pages.edit_title', 'Edit documentation page')
        : t('docs.pages.create_title', 'Create documentation page'),
);
const isEditMode = computed(() => Boolean(props.docPage?.id));
const tabs = computed(() => [
    { value: 'basis', label: t('common.tabs.basic', 'Basic') },
    { value: 'content', label: t('docs.tabs.content', 'Content') },
    { value: 'seo', label: t('docs.tabs.seo', 'SEO') },
]);
const localeOptions = computed(() =>
    props.activeLanguages.map((item) => ({
        value: item.locale ?? item.value ?? item.code,
        label: item.label ?? item.name ?? item.locale,
    })),
);
const statusOptions = computed(() => [
    { value: 'draft', label: t('common.status.draft', 'Draft') },
    { value: 'published', label: t('common.status.published', 'Published') },
    { value: 'archived', label: t('common.status.archived', 'Archived') },
]);
const form = useForm({
    cms_doc_version_id:
        props.docPage?.cms_doc_version_id ??
        props.versionOptions[0]?.id ??
        null,
    parent_id: props.docPage?.parent_id ?? null,
    title: props.docPage?.title ?? '',
    slug: props.docPage?.slug ?? '',
    path: props.docPage?.path ?? '',
    locale: props.docPage?.locale ?? props.availableLocales[0] ?? 'en',
    status: props.docPage?.status ?? 'draft',
    body_format: 'markdown',
    body: props.docPage?.body ?? '',
    seo_title: props.docPage?.seo_title ?? '',
    seo_description: props.docPage?.seo_description ?? '',
    noindex: props.docPage?.noindex ?? false,
    sort_order: props.docPage?.sort_order ?? 0,
});
const translationForm = useForm({
    target_locale: '',
    target_locales: [],
    use_ai: true,
    source_title: '',
    source_body: '',
    source_seo_title: '',
    source_seo_description: '',
});
const contentChanged = computed(
    () => form.body !== (props.docPage?.body ?? ''),
);
const targetLocalesForAi = computed(() =>
    localeOptions.value
        .map((item) => item.value)
        .filter((locale) => locale && locale !== form.locale),
);
const missingLanguages = computed(() =>
    props.missingLanguages.filter(
        (language) => language.locale !== form.locale,
    ),
);
const translationsByLocale = computed(() => {
    const translations = new Map();

    props.translations.forEach((translation) => {
        translations.set(translation.locale, translation);
    });

    if (props.docPage?.locale && !translations.has(props.docPage.locale)) {
        translations.set(props.docPage.locale, {
            id: props.docPage.id,
            locale: props.docPage.locale,
            title: props.docPage.title,
            status: props.docPage.status,
            is_current: true,
        });
    }

    return translations;
});
const translationStatusItems = computed(() => {
    const items = [];
    const missingLocaleSet = new Set(
        missingLanguages.value.map((language) => language.locale),
    );

    props.activeLanguages.forEach((language) => {
        const locale = language.locale ?? language.value ?? language.code;
        const translation = translationsByLocale.value.get(locale);

        if (translation) {
            items.push({
                key: `translation-${translation.id}`,
                label: languageLabel(language),
                type: translation.is_current ? 'current' : 'translation',
                id: translation.id,
                status:
                    translation.status === 'published' ? 'success' : 'warning',
                isCurrent: Boolean(translation.is_current),
            });

            return;
        }

        if (missingLocaleSet.has(locale)) {
            items.push({
                key: `missing-${locale}`,
                label: languageLabel(language),
                type: 'missing',
                locale,
                status: 'danger',
                isCurrent: false,
            });
        }
    });

    return items;
});

function submit() {
    form.post(
        route('admin.cms.docs.pages.store', { page: props.docPage?.id ?? 0 }),
        { preserveScroll: true },
    );
}

function insertMediaToken() {
    if (!selectedMediaId.value) {
        return;
    }

    const token = `![${t('docs.fields.image_alt_placeholder', 'Image description')}](media:${selectedMediaId.value})`;
    const input = bodyInput.value;

    if (!input) {
        form.body = `${form.body}\n\n${token}`;
        return;
    }
    const start = input.selectionStart ?? form.body.length;
    const end = input.selectionEnd ?? form.body.length;
    form.body = `${form.body.slice(0, start)}${token}${form.body.slice(end)}`;
}

function createTranslation(locale = '') {
    if (!props.docPage?.id) {
        return;
    }

    const targetLocales = locale ? [locale] : targetLocalesForAi.value;

    if (targetLocales.length === 0) {
        return;
    }

    translationForm.target_locale = locale;
    translationForm.target_locales = locale ? [] : targetLocales;
    translationForm.use_ai = true;
    translationForm.source_title = form.title;
    translationForm.source_body = form.body;
    translationForm.source_seo_title = form.seo_title;
    translationForm.source_seo_description = form.seo_description;

    translationForm.post(
        route('admin.cms.docs.pages.translations.store', {
            page: props.docPage.id,
        }),
        { preserveScroll: true },
    );
}

function handleTranslationChipClick(item) {
    if (item.type === 'translation' && item.id) {
        router.visit(route('admin.cms.docs.pages.edit', { page: item.id }));

        return;
    }

    if (item.type === 'missing' && item.locale) {
        createTranslation(item.locale);
    }
}

function translationChipTitle(item) {
    if (item.type === 'translation') {
        return t('content_form.open', 'Open');
    }

    if (item.type === 'missing') {
        return t('content_form.create_translation', 'Create translation');
    }

    return t('content_form.current', 'Current');
}

function translationStatusClass(itemOrStatus) {
    const item =
        typeof itemOrStatus === 'string'
            ? { status: itemOrStatus, isCurrent: false }
            : itemOrStatus;
    const currentClass = item?.isCurrent
        ? ' ring-2 ring-blue-500 ring-offset-1'
        : '';

    if (item?.status === 'success') {
        return `border-green-200 bg-green-50 text-green-800${currentClass}`;
    }

    if (item?.status === 'warning') {
        return `border-orange-200 bg-orange-50 text-orange-800${currentClass}`;
    }

    return `border-red-200 bg-red-50 text-red-800${currentClass}`;
}

function translationStatusDotClass(status) {
    if (status === 'success') {
        return 'bg-green-500';
    }

    if (status === 'warning') {
        return 'bg-orange-500';
    }

    return 'bg-red-500';
}

function languageLabel(language) {
    const locale = language.locale ?? language.value ?? language.code;
    const label =
        language.native_name || language.name || language.label || locale;

    return `${label} (${locale})`;
}
</script>
