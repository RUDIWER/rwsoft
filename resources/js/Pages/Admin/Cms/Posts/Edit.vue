<template>
    <AdminLayout :title="pageTitle" :suppress-flash="true">
        <Head :title="pageTitle" />

        <form @submit.prevent="submit">
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
                                    class="mdi mdi-newspaper-variant-outline text-2xl"
                                />
                            </div>
                            <div class="min-w-0">
                                <CardTitle class="text-lg">
                                    {{ pageTitle }}
                                </CardTitle>
                                <CardDescription class="mt-1">
                                    {{
                                        t(
                                            'posts.form.card_description',
                                            'Basisgegevens, content, SEO en vertalingen van het CMS-bericht.',
                                        )
                                    }}
                                </CardDescription>
                            </div>
                        </div>

                        <div class="flex flex-wrap justify-end gap-2">
                            <AdminFormBackButton
                                :href="backHref"
                                :dirty="form.isDirty"
                                :processing="form.processing"
                                :label="t('actions.back', 'Back')"
                                @save="submit"
                            />

                            <Button
                                v-if="isEditMode"
                                type="button"
                                variant="outline"
                                class="gap-2 shadow-none"
                                @click="showRevisionDialog = true"
                            >
                                <span
                                    class="mdi mdi-history text-base"
                                    aria-hidden="true"
                                />
                                {{ t('revisions.open', 'Versions') }}
                            </Button>

                            <AdminFormSaveButton
                                :dirty="form.isDirty"
                                :processing="form.processing"
                                :label="t('actions.save', 'Save')"
                            />
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
                                v-for="tab in tabOptions"
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

                    <div class="space-y-5 p-4 sm:p-5">
                        <FormValidationSummary
                            :visible="showSummary"
                            :errors="validationErrors"
                            :title="
                                t('validation.summary_title', 'Save is blocked')
                            "
                            :description="
                                t(
                                    'validation.summary_description',
                                    'Resolve the fields below and try again.',
                                )
                            "
                            @select="scrollToIssue"
                        />

                        <AiTranslationReviewBanner
                            v-if="isEditMode"
                            type="post"
                            :record-id="postItem.id"
                            :review="postItem.ai_translation_review"
                        />

                        <section v-if="activeTab === 'basis'" class="space-y-5">
                            <div
                                class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]"
                            >
                                <div class="grid gap-4">
                                    <div class="grid gap-2">
                                        <Label
                                            for="title"
                                            class="flex items-center gap-1"
                                        >
                                            <span
                                                class="text-red-600"
                                                aria-hidden="true"
                                                >*</span
                                            >
                                            {{
                                                t('content_form.title', 'Title')
                                            }}
                                        </Label>
                                        <Input
                                            id="title"
                                            v-model="form.title"
                                            required
                                            class="bg-yellow-50"
                                            @blur="touchTitle"
                                        />
                                        <FieldValidationMessage
                                            :message="
                                                validationMessage('title')
                                            "
                                            :warning="
                                                validationWarning('title')
                                            "
                                            :value="form.title"
                                            :max="counterMax('title')"
                                        />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label
                                            for="slug"
                                            class="flex items-center gap-1"
                                        >
                                            <span
                                                class="text-red-600"
                                                aria-hidden="true"
                                                >*</span
                                            >
                                            {{ t('content_form.slug', 'Slug') }}
                                        </Label>
                                        <Input
                                            id="slug"
                                            v-model="form.slug"
                                            required
                                            class="bg-yellow-50"
                                            @blur="touchAndClear('slug')"
                                        />
                                        <FieldValidationMessage
                                            :message="validationMessage('slug')"
                                            :warning="validationWarning('slug')"
                                            :value="form.slug"
                                            :max="counterMax('slug')"
                                        />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="excerpt">{{
                                            t('posts.form.excerpt', 'Intro')
                                        }}</Label>
                                        <textarea
                                            id="excerpt"
                                            v-model="form.excerpt"
                                            rows="4"
                                            class="min-h-24 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        ></textarea>
                                        <p
                                            v-if="form.errors.excerpt"
                                            class="text-sm text-red-600"
                                        >
                                            {{ form.errors.excerpt }}
                                        </p>
                                    </div>
                                </div>

                                <div class="grid content-start gap-4">
                                    <section
                                        class="grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-3"
                                    >
                                        <h2
                                            class="text-sm font-semibold text-slate-900"
                                        >
                                            {{
                                                t(
                                                    'content_form.publication',
                                                    'Publication',
                                                )
                                            }}
                                        </h2>

                                        <div class="grid gap-2">
                                            <Label
                                                for="locale"
                                                class="flex items-center gap-1"
                                            >
                                                <span
                                                    class="text-red-600"
                                                    aria-hidden="true"
                                                    >*</span
                                                >
                                                {{
                                                    t(
                                                        'common.columns.locale',
                                                        'Language',
                                                    )
                                                }}
                                            </Label>
                                            <RwAutoCompleteInput
                                                id="locale"
                                                v-model="form.locale"
                                                :items="
                                                    selectableLanguageOptions
                                                "
                                                item-title="label"
                                                item-value="value"
                                                :search-fields="[
                                                    'label',
                                                    'value',
                                                ]"
                                                :aria-label="
                                                    t(
                                                        'common.columns.locale',
                                                        'Language',
                                                    )
                                                "
                                                :required-missing="true"
                                                required-highlight-color="#fefce8"
                                            />
                                            <p
                                                v-if="form.errors.locale"
                                                class="text-sm text-red-600"
                                            >
                                                {{ form.errors.locale }}
                                            </p>
                                        </div>

                                        <div class="grid gap-2">
                                            <Label for="status">{{
                                                t(
                                                    'common.columns.status',
                                                    'Status',
                                                )
                                            }}</Label>
                                            <RwAutoCompleteInput
                                                id="status"
                                                v-model="form.status"
                                                :items="statusOptions"
                                                item-title="label"
                                                item-value="value"
                                                :search-fields="[
                                                    'label',
                                                    'value',
                                                ]"
                                                :aria-label="
                                                    t(
                                                        'common.columns.status',
                                                        'Status',
                                                    )
                                                "
                                            />
                                            <p
                                                v-if="form.errors.status"
                                                class="text-sm text-red-600"
                                            >
                                                {{ form.errors.status }}
                                            </p>
                                        </div>

                                        <div class="grid gap-2">
                                            <Label for="published_at">{{
                                                t(
                                                    'content_form.published_at',
                                                    'Publication date',
                                                )
                                            }}</Label>
                                            <Input
                                                id="published_at"
                                                v-model="form.published_at"
                                                type="datetime-local"
                                            />
                                            <p
                                                v-if="form.errors.published_at"
                                                class="text-sm text-red-600"
                                            >
                                                {{ form.errors.published_at }}
                                            </p>
                                        </div>
                                    </section>

                                    <section
                                        class="grid gap-3 rounded-md border border-slate-200 p-3"
                                    >
                                        <h2
                                            class="text-sm font-semibold text-slate-900"
                                        >
                                            {{
                                                t(
                                                    'content_form.options',
                                                    'Options',
                                                )
                                            }}
                                        </h2>
                                        <label
                                            class="flex items-center gap-2 text-sm"
                                        >
                                            <input
                                                v-model="form.is_featured"
                                                type="checkbox"
                                                class="h-4 w-4 rounded border-slate-300"
                                            />
                                            {{
                                                t(
                                                    'posts.form.featured_post',
                                                    'Featured post',
                                                )
                                            }}
                                        </label>
                                        <label
                                            class="flex items-center gap-2 text-sm"
                                        >
                                            <input
                                                v-model="form.is_searchable"
                                                type="checkbox"
                                                class="h-4 w-4 rounded border-slate-300"
                                            />
                                            {{
                                                t(
                                                    'content_form.searchable',
                                                    'Searchable',
                                                )
                                            }}
                                        </label>
                                        <label
                                            class="flex items-center gap-2 text-sm"
                                        >
                                            <input
                                                v-model="form.noindex"
                                                type="checkbox"
                                                class="h-4 w-4 rounded border-slate-300"
                                            />
                                            {{
                                                t(
                                                    'content_form.noindex',
                                                    'Noindex',
                                                )
                                            }}
                                        </label>
                                        <label
                                            class="flex items-center gap-2 text-sm"
                                        >
                                            <input
                                                v-model="
                                                    form.pdf_download_enabled
                                                "
                                                type="checkbox"
                                                class="h-4 w-4 rounded border-slate-300"
                                            />
                                            {{
                                                t(
                                                    'content_form.pdf_download_enabled',
                                                    'Enable public PDF download',
                                                )
                                            }}
                                        </label>
                                        <p class="text-xs text-slate-500">
                                            {{
                                                t(
                                                    'content_form.pdf_download_help',
                                                    'Adds a public .pdf download URL for this content when it is published.',
                                                )
                                            }}
                                        </p>
                                    </section>

                                    <section
                                        class="grid gap-3 rounded-md border border-slate-200 p-3"
                                    >
                                        <div
                                            class="flex items-center justify-between gap-2"
                                        >
                                            <h2
                                                class="text-sm font-semibold text-slate-900"
                                            >
                                                {{
                                                    t(
                                                        'content_form.translations',
                                                        'Translations',
                                                    )
                                                }}
                                            </h2>
                                            <Button
                                                v-if="isEditMode"
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                class="shadow-none"
                                                :disabled="
                                                    missingLanguages.length ===
                                                    0
                                                "
                                                @click="openTranslationDialog"
                                            >
                                                {{
                                                    t(
                                                        'content_form.make_translation',
                                                        'Create translation',
                                                    )
                                                }}
                                            </Button>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <span
                                                v-for="item in translationStatusItems"
                                                :key="item.key"
                                                class="inline-flex items-center gap-1 rounded-full border px-2 py-1 text-xs font-medium"
                                                :class="
                                                    translationStatusClass(
                                                        item.status,
                                                    )
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
                                            </span>
                                        </div>
                                        <p
                                            v-if="!isEditMode"
                                            class="text-xs text-slate-500"
                                        >
                                            {{
                                                t(
                                                    'posts.form.save_before_translations',
                                                    'Save the post first to manage translations.',
                                                )
                                            }}
                                        </p>
                                    </section>
                                </div>
                            </div>
                        </section>

                        <section
                            v-if="activeTab === 'content'"
                            class="grid gap-4"
                        >
                            <div class="grid gap-2">
                                <Label for="detail_template_id">{{
                                    t(
                                        'templates.fields.blog_detail_template',
                                        'Detail template',
                                    )
                                }}</Label>
                                <RwAutoCompleteInput
                                    id="detail_template_id"
                                    v-model="form.detail_template_id"
                                    :items="filteredDetailTemplateOptions"
                                    item-title="label"
                                    item-value="id"
                                    :search-fields="['label', 'name', 'locale']"
                                    :aria-label="
                                        t(
                                            'templates.fields.blog_detail_template',
                                            'Detail template',
                                        )
                                    "
                                />
                                <p
                                    v-if="form.errors.detail_template_id"
                                    class="text-sm text-red-600"
                                >
                                    {{ form.errors.detail_template_id }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    {{
                                        t(
                                            'posts.form.detail_template_help',
                                            'Optional template for this public blog detail page. The default blog detail template is used when empty.',
                                        )
                                    }}
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <CmsBlockEditor
                                    v-model="form.content_blocks"
                                    v-model:assets="localMediaOptions"
                                    v-model:folders="localMediaFolders"
                                    :downloads="downloadOptions"
                                    :download-folders="downloadFolders"
                                    :forms="formOptions"
                                    :categories="categoryOptions"
                                    :tags="tagOptions"
                                    :contact-settings="contactSettings"
                                    :placeable-blocks="placeableBlocks"
                                    upload-context-type="post"
                                    :upload-context-id="postRecordId"
                                    :label="
                                        t(
                                            'content_form.content_blocks',
                                            'Content blocks',
                                        )
                                    "
                                />
                                <p
                                    v-if="form.errors.content_blocks"
                                    class="text-sm text-red-600"
                                >
                                    {{ form.errors.content_blocks }}
                                </p>
                            </div>
                        </section>

                        <section v-if="activeTab === 'seo'" class="grid gap-4">
                            <div
                                class="flex flex-wrap gap-4 border-b border-slate-200"
                            >
                                <button
                                    v-for="tab in metaTabs"
                                    :key="tab.key"
                                    type="button"
                                    class="-mb-px border-b-2 px-1 py-2 text-sm font-medium transition"
                                    :class="
                                        activeMetaTab === tab.key
                                            ? 'border-blue-600 text-blue-700'
                                            : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900'
                                    "
                                    @click="activeMetaTab = tab.key"
                                >
                                    {{ tab.label }}
                                </button>
                            </div>

                            <section
                                v-if="activeMetaTab === 'seo'"
                                class="grid gap-4"
                            >
                                <div class="grid gap-2">
                                    <Label for="seo_title">{{
                                        t('content_form.seo_title', 'SEO title')
                                    }}</Label>
                                    <Input
                                        id="seo_title"
                                        v-model="form.seo_title"
                                        @blur="touchAndClear('seo_title')"
                                    />
                                    <FieldValidationMessage
                                        :message="
                                            validationMessage('seo_title')
                                        "
                                        :warning="
                                            validationWarning('seo_title')
                                        "
                                        :value="form.seo_title"
                                        :max="counterMax('seo_title')"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="seo_description">{{
                                        t(
                                            'content_form.seo_description_label',
                                            'SEO description',
                                        )
                                    }}</Label>
                                    <textarea
                                        id="seo_description"
                                        v-model="form.seo_description"
                                        rows="3"
                                        class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        @blur="touchAndClear('seo_description')"
                                    ></textarea>
                                    <FieldValidationMessage
                                        :message="
                                            validationMessage('seo_description')
                                        "
                                        :warning="
                                            validationWarning('seo_description')
                                        "
                                        :value="form.seo_description"
                                        :max="counterMax('seo_description')"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="canonical_url">{{
                                        t(
                                            'content_form.canonical_url',
                                            'Canonical URL',
                                        )
                                    }}</Label>
                                    <Input
                                        id="canonical_url"
                                        v-model="form.canonical_url"
                                        type="url"
                                        @blur="touchAndClear('canonical_url')"
                                    />
                                    <FieldValidationMessage
                                        :message="
                                            validationMessage('canonical_url')
                                        "
                                        :warning="
                                            validationWarning('canonical_url')
                                        "
                                        :value="form.canonical_url"
                                        :max="counterMax('canonical_url')"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="og_image_path">{{
                                        t(
                                            'content_form.og_image_path',
                                            'OG image path',
                                        )
                                    }}</Label>
                                    <Input
                                        id="og_image_path"
                                        v-model="form.og_image_path"
                                    />
                                    <p
                                        v-if="form.errors.og_image_path"
                                        class="text-sm text-red-600"
                                    >
                                        {{ form.errors.og_image_path }}
                                    </p>
                                </div>
                            </section>

                            <CmsStructuredDataEditor
                                v-if="activeMetaTab === 'json_ld'"
                                id-prefix="post_json_ld"
                                v-model:schema-type="
                                    form.structured_data_schema_type
                                "
                                :extra-json="form.structured_data_extra"
                                :automatic-json="structuredData.automatic"
                                :schema-type-options="
                                    structuredData.schemaTypeOptions
                                "
                                :placeholders="structuredData.placeholders"
                                :error="
                                    validationMessage('structured_data_extra')
                                "
                                @update:extra-json="updateStructuredDataExtra"
                            />
                        </section>

                        <CmsContentStatisticsPanel
                            v-if="activeTab === 'statistics' && isEditMode"
                            content-type="post"
                            :record-id="postRecordId"
                        />

                        <section
                            v-if="activeTab === 'taxonomy'"
                            class="grid gap-5"
                        >
                            <div class="grid gap-2">
                                <Label>{{
                                    t(
                                        'dashboard.sections.categories.title',
                                        'Categories',
                                    )
                                }}</Label>
                                <div
                                    class="grid max-h-72 gap-2 overflow-auto rounded-lg border border-slate-200 p-3"
                                >
                                    <label
                                        v-for="category in filteredCategoryOptions"
                                        :key="category.id"
                                        class="flex items-center gap-2 text-sm text-slate-700"
                                    >
                                        <input
                                            v-model="form.category_ids"
                                            type="checkbox"
                                            :value="category.id"
                                            class="h-4 w-4 rounded border-slate-300"
                                        />
                                        {{ category.title }} ({{
                                            category.locale
                                        }})
                                    </label>
                                    <p
                                        v-if="
                                            filteredCategoryOptions.length === 0
                                        "
                                        class="text-sm text-slate-500"
                                    >
                                        {{
                                            t(
                                                'posts.form.no_active_categories',
                                                'No active categories yet.',
                                            )
                                        }}
                                    </p>
                                </div>
                                <p
                                    v-if="form.errors.category_ids"
                                    class="text-sm text-red-600"
                                >
                                    {{ form.errors.category_ids }}
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <Label>{{
                                    t('dashboard.sections.tags.title', 'Tags')
                                }}</Label>
                                <div
                                    class="grid max-h-72 gap-2 overflow-auto rounded-lg border border-slate-200 p-3"
                                >
                                    <label
                                        v-for="tag in filteredTagOptions"
                                        :key="tag.id"
                                        class="flex items-center gap-2 text-sm text-slate-700"
                                    >
                                        <input
                                            v-model="form.tag_ids"
                                            type="checkbox"
                                            :value="tag.id"
                                            class="h-4 w-4 rounded border-slate-300"
                                        />
                                        {{ tag.title }} ({{ tag.locale }})
                                    </label>
                                    <p
                                        v-if="filteredTagOptions.length === 0"
                                        class="text-sm text-slate-500"
                                    >
                                        {{
                                            t(
                                                'posts.form.no_active_tags',
                                                'No active tags yet.',
                                            )
                                        }}
                                    </p>
                                </div>
                                <p
                                    v-if="form.errors.tag_ids"
                                    class="text-sm text-red-600"
                                >
                                    {{ form.errors.tag_ids }}
                                </p>
                            </div>
                        </section>

                        <section
                            v-if="activeTab === 'media'"
                            class="grid gap-3"
                        >
                            <h2 class="text-base font-semibold text-slate-900">
                                {{
                                    t(
                                        'posts.form.featured_image',
                                        'Featured image',
                                    )
                                }}
                            </h2>
                            <CmsMediaPicker
                                v-model="form.featured_media_asset_id"
                                v-model:assets="localMediaOptions"
                                v-model:folders="localMediaFolders"
                                uploaded-from="post_featured_image"
                                upload-context-type="post"
                                :upload-context-id="postRecordId"
                            />
                            <p
                                v-if="form.errors.featured_media_asset_id"
                                class="text-sm text-red-600"
                            >
                                {{ form.errors.featured_media_asset_id }}
                            </p>
                        </section>

                        <section
                            v-if="activeTab === 'translations'"
                            class="space-y-4"
                        >
                            <div
                                class="flex flex-wrap items-start justify-between gap-3"
                            >
                                <div>
                                    <h2
                                        class="text-base font-semibold text-slate-900"
                                    >
                                        {{
                                            t(
                                                'content_form.translations',
                                                'Translations',
                                            )
                                        }}
                                    </h2>
                                    <p class="mt-1 text-sm text-slate-500">
                                        {{
                                            t(
                                                'posts.form.translations_description',
                                                'Create and open linked language versions of this post.',
                                            )
                                        }}
                                    </p>
                                </div>
                                <Button
                                    v-if="isEditMode"
                                    type="button"
                                    variant="outline"
                                    class="shadow-none"
                                    :disabled="missingLanguages.length === 0"
                                    @click="openTranslationDialog"
                                >
                                    {{
                                        t(
                                            'content_form.make_translation',
                                            'Create translation',
                                        )
                                    }}
                                </Button>
                            </div>

                            <p
                                v-if="!isMultilingualEnabled"
                                class="rounded-md border border-orange-200 bg-orange-50 px-3 py-2 text-sm text-orange-800"
                            >
                                {{
                                    t(
                                        'content_form.multilingual_disabled',
                                        'Multilingual content is currently disabled in CMS settings.',
                                    )
                                }}
                            </p>

                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="item in translationStatusItems"
                                    :key="item.key"
                                    class="inline-flex items-center gap-1 rounded-full border px-2 py-1 text-xs font-medium"
                                    :class="translationStatusClass(item.status)"
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
                                </span>
                            </div>

                            <div
                                v-if="otherTranslations.length > 0"
                                class="grid gap-2"
                            >
                                <div
                                    v-for="translation in otherTranslations"
                                    :key="translation.id"
                                    class="flex items-center justify-between gap-3 rounded-lg border px-3 py-2 text-sm"
                                    :class="translationCardClass(translation)"
                                >
                                    <div class="min-w-0">
                                        <div
                                            class="flex flex-wrap items-center gap-2"
                                        >
                                            <span
                                                class="font-semibold uppercase text-slate-700"
                                            >
                                                {{ translation.locale }}
                                            </span>
                                            <span
                                                class="truncate text-slate-900"
                                            >
                                                {{ translation.title }}
                                            </span>
                                        </div>
                                        <div class="text-xs text-slate-500">
                                            {{
                                                statusLabel(translation.status)
                                            }}
                                            · /{{ translation.slug }}
                                        </div>
                                    </div>
                                    <Button
                                        as-child
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="shadow-none"
                                    >
                                        <Link
                                            :href="
                                                route('admin.cms.posts.edit', {
                                                    id: translation.id,
                                                })
                                            "
                                        >
                                            {{ t('content_form.open', 'Open') }}
                                        </Link>
                                    </Button>
                                </div>
                            </div>

                            <p v-else class="text-sm text-slate-500">
                                {{
                                    t(
                                        'content_form.no_other_translations',
                                        'No other linked translations yet.',
                                    )
                                }}
                            </p>

                            <div
                                v-if="missingLanguages.length > 0"
                                class="grid gap-2"
                            >
                                <div
                                    v-for="language in missingLanguages"
                                    :key="language.locale"
                                    class="flex items-center justify-between gap-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900"
                                >
                                    <div>
                                        <div class="font-semibold">
                                            {{
                                                t(
                                                    'content_form.missing_prefix',
                                                    'Missing: :language',
                                                    {
                                                        language:
                                                            languageLabel(
                                                                language,
                                                            ),
                                                    },
                                                )
                                            }}
                                        </div>
                                        <div class="text-xs text-red-700">
                                            {{
                                                t(
                                                    'posts.form.missing_translation_help',
                                                    'No linked post for this active language yet.',
                                                )
                                            }}
                                        </div>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        class="shadow-none"
                                        :disabled="translationForm.processing"
                                        @click="
                                            openTranslationDialog(
                                                language.locale,
                                            )
                                        "
                                    >
                                        {{
                                            t(
                                                'content_form.create_translation',
                                                'Create translation',
                                            )
                                        }}
                                    </Button>
                                </div>
                            </div>

                            <p
                                v-if="missingLanguages.length === 0"
                                class="text-sm text-orange-600"
                            >
                                {{
                                    t(
                                        'content_form.all_languages_linked',
                                        'All available languages are already linked.',
                                    )
                                }}
                            </p>
                        </section>
                    </div>
                </CardContent>
            </Card>
        </form>

        <Dialog v-model:open="showTranslationDialog">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{
                        t(
                            'posts.form.translation_dialog_title',
                            'Maak vertaling',
                        )
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            t(
                                'posts.form.translation_dialog_description',
                                'Kies of het conceptbericht met AI vertaald wordt of eerst als kopie wordt aangemaakt.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-3 py-2">
                    <div class="grid gap-2">
                        <Label>{{
                            t('content_form.chosen_language', 'Gekozen taal')
                        }}</Label>
                        <div
                            class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-900"
                        >
                            {{ selectedTranslationLanguageLabel }}
                        </div>
                        <p
                            v-if="translationForm.errors.target_locale"
                            class="text-sm text-red-600"
                        >
                            {{ translationForm.errors.target_locale }}
                        </p>
                    </div>

                    <div
                        class="rounded-md border border-slate-200 p-3 text-sm text-slate-600"
                    >
                        <p class="font-medium text-slate-900">
                            {{
                                t(
                                    'content_form.choices_title',
                                    'Beschikbare keuzes',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                t(
                                    'posts.form.ai_help',
                                    'Met AI vertalen maakt direct een vertaald conceptbericht.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                t(
                                    'content_form.copy_help',
                                    'Origineel kopieren maakt een concept met dezelfde teksten, zodat je later handmatig kan aanpassen.',
                                )
                            }}
                        </p>
                    </div>
                </div>

                <DialogFooter class="justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="shadow-none"
                        :disabled="
                            translationForm.processing ||
                            !translationForm.target_locale
                        "
                        @click="createTranslation(false)"
                    >
                        {{
                            t(
                                'content_form.copy_original',
                                'Origineel kopieren',
                            )
                        }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        class="border-blue-200 text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800"
                        :disabled="
                            translationForm.processing ||
                            !translationForm.target_locale
                        "
                        @click="createTranslation(true)"
                    >
                        {{ t('content_form.translate_ai', 'Met AI vertalen') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <CmsRevisionHistoryDialog
            v-if="isEditMode"
            v-model:open="showRevisionDialog"
            subject-type="post"
            restore-route-name="admin.cms.posts.revisions.restore"
            :restore-route-params="{ post: postItem.id }"
            :revisions="revisions"
        />
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminFormBackButton from '@/Components/Admin/Form/AdminFormBackButton.vue';
import AdminFormSaveButton from '@/Components/Admin/Form/AdminFormSaveButton.vue';
import RwAutoCompleteInput from '@/Components/RwAutoCompleteInput.vue';
import RwFlashMessage from '@/Components/RwFlashMessage.vue';
import CmsBlockEditor from '@/Pages/Admin/Cms/Components/CmsBlockEditor.vue';
import CmsContentStatisticsPanel from '@/Pages/Admin/Cms/Components/CmsContentStatisticsPanel.vue';
import CmsMediaPicker from '@/Pages/Admin/Cms/Components/CmsMediaPicker.vue';
import CmsRevisionHistoryDialog from '@/Pages/Admin/Cms/Components/CmsRevisionHistoryDialog.vue';
import AiTranslationReviewBanner from '@/Pages/Admin/Cms/Partials/AiTranslationReviewBanner.vue';
import { resolveReturnToUrl } from '@/composables/useReturnToUrl';
import CmsStructuredDataEditor from '@/Pages/Admin/Cms/Components/CmsStructuredDataEditor.vue';
import {
    createCmsSeoFields,
    useCmsFormValidation,
} from '@/composables/useCmsFormValidation';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    postItem: {
        type: Object,
        default: null,
    },
    translations: {
        type: Array,
        default: () => [],
    },
    revisions: {
        type: Array,
        default: () => [],
    },
    availableLocales: {
        type: Array,
        default: () => [],
    },
    activeLanguages: {
        type: Array,
        default: () => [],
    },
    missingLanguages: {
        type: Array,
        default: () => [],
    },
    multilingualEnabled: {
        type: Boolean,
        default: true,
    },
    statusOptions: {
        type: Array,
        required: true,
    },
    categoryOptions: {
        type: Array,
        required: true,
    },
    tagOptions: {
        type: Array,
        required: true,
    },
    mediaOptions: {
        type: Array,
        required: true,
    },
    mediaFolders: {
        type: Array,
        default: () => [],
    },
    downloadOptions: {
        type: Array,
        default: () => [],
    },
    downloadFolders: {
        type: Array,
        default: () => [],
    },
    formOptions: {
        type: Array,
        required: true,
    },
    placeableBlocks: {
        type: Array,
        default: () => [],
    },
    contactSettings: {
        type: Object,
        default: () => ({}),
    },
    detailTemplateOptions: {
        type: Array,
        default: () => [],
    },
    structuredData: {
        type: Object,
        required: true,
    },
    seoSettings: {
        type: Object,
        required: true,
    },
});

const { t } = useAdminTranslations('cms_admin_ui');
const page = usePage();

const metaTabs = computed(() => [
    { key: 'seo', label: t('content_form.seo', 'SEO') },
    { key: 'json_ld', label: t('content_form.json_ld', 'JSON-LD') },
]);

const activeTab = ref('basis');
const activeMetaTab = ref('seo');
const showTranslationDialog = ref(false);
const showRevisionDialog = ref(false);
const localMediaOptions = ref([...props.mediaOptions]);
const localMediaFolders = ref([...props.mediaFolders]);

const form = useForm({
    title: props.postItem?.title ?? '',
    slug: props.postItem?.slug ?? '',
    locale: props.postItem?.locale ?? props.activeLanguages[0]?.locale ?? 'nl',
    status: props.postItem?.status ?? 'draft',
    detail_template_id: props.postItem?.detail_template_id ?? '',
    excerpt: props.postItem?.excerpt ?? '',
    content_blocks: props.postItem?.content_blocks ?? [],
    featured_media_asset_id: props.postItem?.featured_media_asset_id ?? '',
    seo_title: props.postItem?.seo_title ?? '',
    seo_description: props.postItem?.seo_description ?? '',
    canonical_url: props.postItem?.canonical_url ?? '',
    og_image_path: props.postItem?.og_image_path ?? '',
    noindex: Boolean(props.postItem?.noindex ?? false),
    is_featured: Boolean(props.postItem?.is_featured ?? false),
    is_searchable: Boolean(props.postItem?.is_searchable ?? true),
    pdf_download_enabled: Boolean(
        props.postItem?.pdf_download_enabled ?? false,
    ),
    published_at: props.postItem?.published_at ?? '',
    category_ids: props.postItem?.category_ids ?? [],
    tag_ids: props.postItem?.tag_ids ?? [],
    structured_data_schema_type:
        props.postItem?.structured_data_schema_type ?? 'auto',
    structured_data_extra: props.postItem?.structured_data_extra ?? '',
});

const {
    FieldValidationMessage,
    FormValidationSummary,
    validation: fieldValidation,
    formValidation,
    message: validationMessage,
    warning: validationWarning,
    counterMax,
    touchAndClear,
} = useCmsFormValidation(form, {
    fields: createCmsSeoFields(form, {
        t,
        seoSettings: props.seoSettings,
    }),
    activateTab: (tab) => {
        activeTab.value = 'seo';
        activeMetaTab.value = tab;
    },
});
const { errors: validationErrors } = fieldValidation;
const { showSummary, validateBeforeSubmit, scrollToIssue } = formValidation;

const isEditMode = computed(() => Boolean(props.postItem?.id));
const postRecordId = computed(() => props.postItem?.id ?? null);
const pageTitle = computed(() =>
    isEditMode.value
        ? t('posts.form.edit_title', 'Bericht bewerken')
        : t('posts.form.create_title', 'Bericht toevoegen'),
);
const backHref = computed(() =>
    resolveReturnToUrl(route('admin.cms.posts.index')),
);
const isMultilingualEnabled = computed(() => props.multilingualEnabled);
const filteredDetailTemplateOptions = computed(() => [
    {
        id: '',
        label: t('templates.form.default_template', 'Default template'),
        name: '',
        locale: form.locale,
    },
    ...props.detailTemplateOptions.filter(
        (option) => option.locale === form.locale,
    ),
]);
const tabOptions = computed(() => [
    { value: 'basis', label: t('posts.form.tabs.basic', 'Basic') },
    { value: 'content', label: t('posts.form.tabs.content', 'Content') },
    { value: 'seo', label: t('posts.form.tabs.seo', 'SEO') },
    ...(isEditMode.value
        ? [
              {
                  value: 'statistics',
                  label: t('content_form.tabs.statistics', 'Statistics'),
              },
          ]
        : []),
    { value: 'taxonomy', label: t('posts.form.tabs.taxonomy', 'Taxonomy') },
    { value: 'media', label: t('posts.form.tabs.media', 'Media') },
    {
        value: 'translations',
        label: t('posts.form.tabs.translations', 'Translations'),
    },
]);
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
const otherTranslations = computed(() =>
    props.translations.filter((translation) => !translation.is_current),
);
const selectableLanguages = computed(() =>
    props.activeLanguages.length > 0
        ? props.activeLanguages
        : props.availableLocales.map((locale) => ({
              locale,
              name: locale,
              native_name: locale,
          })),
);
const selectableLanguageOptions = computed(() =>
    selectableLanguages.value.map((language) => ({
        value: language.locale,
        label: languageLabel(language),
    })),
);
const missingLanguages = computed(() =>
    props.missingLanguages.filter(
        (language) => language.locale !== form.locale,
    ),
);

const currentTranslation = computed(() =>
    props.translations.find((translation) => translation.is_current),
);

const translationStatusItems = computed(() => {
    const items = [];
    const current = currentTranslation.value;

    items.push({
        key: `current-${form.locale}`,
        label: `${form.locale} · ${t('content_form.current', 'Current')}`,
        status:
            (current?.status ?? form.status) === 'published'
                ? 'success'
                : 'warning',
    });

    otherTranslations.value.forEach((translation) => {
        items.push({
            key: `translation-${translation.id}`,
            label: `${translation.locale} · ${translation.title || translation.slug}`,
            status: translation.status === 'published' ? 'success' : 'warning',
        });
    });

    missingLanguages.value.forEach((language) => {
        items.push({
            key: `missing-${language.locale}`,
            label: languageLabel(language),
            status: 'danger',
        });
    });

    return items;
});

const selectedTranslationLanguageLabel = computed(() => {
    const language = missingLanguages.value.find(
        (item) => item.locale === translationForm.target_locale,
    );

    return language
        ? languageLabel(language)
        : t('content_form.no_language_selected', 'Geen taal gekozen');
});
const filteredCategoryOptions = computed(() =>
    props.categoryOptions.filter((category) => category.locale === form.locale),
);
const filteredTagOptions = computed(() =>
    props.tagOptions.filter((tag) => tag.locale === form.locale),
);

const translationForm = useForm({
    target_locale: '',
    use_ai: true,
});

function fillSlug() {
    if (form.slug || !form.title) {
        return;
    }

    form.slug = slugify(form.title);
}

function slugify(value) {
    return value
        .toString()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

function touchTitle() {
    fillSlug();
    touchAndClear('title');
}

function updateStructuredDataExtra(value) {
    form.structured_data_extra = value;
    touchAndClear('structured_data_extra');
}

async function submit() {
    if (!(await validateBeforeSubmit())) {
        return;
    }

    if (!Array.isArray(form.content_blocks)) {
        form.setError(
            'content_blocks',
            t(
                'content_form.content_blocks_error',
                'Content blocks moeten een lijst zijn.',
            ),
        );

        return;
    }

    form.clearErrors('content_blocks');
    form.category_ids = form.category_ids.filter((id) =>
        filteredCategoryOptions.value.some((category) => category.id === id),
    );
    form.tag_ids = form.tag_ids.filter((id) =>
        filteredTagOptions.value.some((tag) => tag.id === id),
    );
    form.featured_media_asset_id = form.featured_media_asset_id || null;
    form.detail_template_id = form.detail_template_id || null;
    form.structured_data_extra = form.structured_data_extra || null;

    form.post(route('admin.cms.posts.store', { id: props.postItem?.id ?? 0 }));
}

function openTranslationDialog(locale = '') {
    translationForm.clearErrors();
    translationForm.target_locale =
        locale || missingLanguages.value[0]?.locale || '';
    translationForm.use_ai = true;
    showTranslationDialog.value = true;
}

function createTranslation(useAi) {
    if (!props.postItem?.id) {
        return;
    }

    translationForm.use_ai = useAi;
    translationForm.post(
        route('admin.cms.posts.translations.store', { id: props.postItem.id }),
    );
}

function statusLabel(status) {
    const option = props.statusOptions.find((item) => item.value === status);

    return option?.label ?? status;
}

function translationCardClass(translation) {
    if (translation.status === 'published') {
        return 'border-slate-200 bg-white';
    }

    if (translation.status === 'draft') {
        return 'border-orange-200 bg-orange-50';
    }

    return 'border-slate-200 bg-slate-50';
}

function translationStatusClass(status) {
    if (status === 'success') {
        return 'border-green-200 bg-green-50 text-green-800';
    }

    if (status === 'warning') {
        return 'border-orange-200 bg-orange-50 text-orange-800';
    }

    return 'border-red-200 bg-red-50 text-red-800';
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
    const label = language.native_name || language.name || language.locale;

    return `${label} (${language.locale})`;
}
</script>
