<template>
    <AdminLayout :title="pageTitle" :suppress-flash="true">
        <Head :title="pageTitle">
            <component
                :is="'style'"
                v-if="activeThemeFontFaceCss"
                type="text/css"
            >
                {{ activeThemeFontFaceCss }}
            </component>
        </Head>

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
                                <span class="mdi mdi-page-next text-2xl" />
                            </div>
                            <div class="min-w-0">
                                <CardTitle class="text-lg">
                                    {{ pageTitle }}
                                </CardTitle>
                                <CardDescription class="mt-1">
                                    {{
                                        t(
                                            'pages.form.card_description',
                                            'Basic details, content, SEO and translations of the CMS page.',
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

                            <DropdownMenu v-if="isEditMode">
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        class="text-slate-950 shadow-none hover:bg-slate-50 hover:text-slate-950"
                                        :aria-label="
                                            commonT(
                                                'actions.more',
                                                'More actions',
                                            )
                                        "
                                        :title="
                                            commonT(
                                                'actions.more',
                                                'More actions',
                                            )
                                        "
                                    >
                                        <span
                                            class="mdi mdi-dots-vertical text-lg"
                                            aria-hidden="true"
                                        />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" class="w-44">
                                    <DropdownMenuItem as-child>
                                        <button
                                            type="button"
                                            class="flex w-full items-center gap-2 text-red-700"
                                            @click="openDeleteDialog"
                                        >
                                            <span
                                                class="mdi mdi-delete text-base"
                                                aria-hidden="true"
                                            />
                                            {{ t('themes.delete', 'Delete') }}
                                        </button>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>

                            <AdminFormSaveButton
                                :dirty="saveButtonDirty"
                                :processing="form.processing"
                                :label="t('actions.save', 'Save')"
                            />
                        </div>
                    </div>
                </CardHeader>

                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700 sm:px-5"
                >
                    <div class="font-medium text-slate-700">
                        {{ commonT('record_meta.id', 'ID') }}:
                        <span class="ml-1 text-base font-bold text-slate-950">
                            {{ recordIdLabel }}
                        </span>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-5 gap-y-1">
                        <div class="font-medium text-slate-700">
                            {{ commonT('record_meta.updated_at', 'Updated') }}:
                            <span
                                class="ml-1 text-base font-bold text-slate-950"
                            >
                                {{ updatedAtLabel }}
                            </span>
                        </div>
                        <div class="font-medium text-slate-700">
                            {{ commonT('record_meta.created_at', 'Created') }}:
                            <span
                                class="ml-1 text-base font-bold text-slate-950"
                            >
                                {{ createdAtLabel }}
                            </span>
                        </div>
                    </div>
                </div>

                <div
                    v-if="pageFlash.message"
                    class="shrink-0 border-b border-slate-200 px-4 py-3 sm:px-5"
                >
                    <RwFlashMessage
                        :type="pageFlash.type"
                        :message="pageFlash.message"
                        :details="pageFlash.details"
                    />
                </div>

                <div
                    v-if="validationFlash.message"
                    class="shrink-0 border-b border-slate-200 px-4 py-3 sm:px-5"
                >
                    <RwFlashMessage
                        :type="validationFlash.type"
                        :message="validationFlash.message"
                        :details="validationFlash.details"
                        @select="scrollToIssue"
                    />
                </div>

                <CardContent class="flex min-h-0 flex-1 flex-col p-0">
                    <div class="shrink-0 border-b border-slate-200">
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

                    <div class="min-h-0 flex-1 overflow-auto p-4 sm:p-5">
                        <AiTranslationReviewBanner
                            v-if="isEditMode"
                            type="page"
                            :record-id="pageItem.id"
                            :review="pageItem.ai_translation_review"
                        />

                        <section v-if="activeTab === 'basis'" class="space-y-5">
                            <div class="space-y-4">
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
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            v-for="item in translationStatusItems"
                                            :key="item.key"
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-full border px-2 py-1 text-xs font-medium transition hover:-translate-y-px hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1"
                                            :class="
                                                translationStatusClass(item)
                                            "
                                            :title="translationChipTitle(item)"
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
                                <p
                                    v-if="isEditMode && !isMultilingualEnabled"
                                    class="rounded-md border border-orange-200 bg-orange-50 px-3 py-2 text-sm text-orange-800"
                                >
                                    {{
                                        t(
                                            'content_form.multilingual_disabled',
                                            'Multilingual content is currently disabled in CMS settings.',
                                        )
                                    }}
                                </p>
                            </div>

                            <div
                                class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]"
                            >
                                <div class="grid gap-4">
                                    <div class="grid gap-1">
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
                                        <Label for="short_description">{{
                                            t(
                                                'content_form.short_description',
                                                'Short description',
                                            )
                                        }}</Label>
                                        <textarea
                                            id="short_description"
                                            v-model="form.short_description"
                                            rows="4"
                                            class="min-h-24 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        ></textarea>
                                        <p
                                            v-if="form.errors.short_description"
                                            class="text-sm text-red-600"
                                        >
                                            {{ form.errors.short_description }}
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
                                                v-model="form.is_home"
                                                type="checkbox"
                                                class="h-4 w-4 rounded border-slate-300"
                                            />
                                            {{
                                                t(
                                                    'content_form.home_for_language',
                                                    'Homepage for this language',
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
                                </div>
                            </div>

                            <section class="grid gap-4">
                                <div>
                                    <h2
                                        class="text-base font-semibold text-slate-900"
                                    >
                                        {{
                                            t(
                                                'pages.form.structure_title',
                                                'Page structure',
                                            )
                                        }}
                                    </h2>
                                    <p class="mt-1 text-sm text-slate-500">
                                        {{
                                            t(
                                                'pages.form.structure_description',
                                                'Configure hierarchy, templates and page-level layout behavior.',
                                            )
                                        }}
                                    </p>
                                </div>

                                <div class="grid gap-4 lg:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label for="parent_id">{{
                                            t(
                                                'content_form.parent_page',
                                                'Parent page',
                                            )
                                        }}</Label>
                                        <RwAutoCompleteInput
                                            id="parent_id"
                                            v-model="form.parent_id"
                                            :items="parentSelectOptions"
                                            item-title="label"
                                            item-value="value"
                                            :search-fields="['label', 'locale']"
                                            :aria-label="
                                                t(
                                                    'content_form.parent_page',
                                                    'Parent page',
                                                )
                                            "
                                        />
                                        <p
                                            v-if="form.errors.parent_id"
                                            class="text-sm text-red-600"
                                        >
                                            {{ form.errors.parent_id }}
                                        </p>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label
                                            for="detail_template_id"
                                            class="flex items-center gap-1"
                                        >
                                            <span
                                                class="text-red-600"
                                                aria-hidden="true"
                                                >*</span
                                            >
                                            {{
                                                t(
                                                    'templates.fields.page_detail_template',
                                                    'Detail template',
                                                )
                                            }}
                                        </Label>
                                        <RwAutoCompleteInput
                                            id="detail_template_id"
                                            v-model="form.detail_template_id"
                                            :items="
                                                filteredDetailTemplateOptions
                                            "
                                            item-title="label"
                                            item-value="id"
                                            :search-fields="[
                                                'label',
                                                'name',
                                                'locale',
                                            ]"
                                            :aria-label="
                                                t(
                                                    'templates.fields.page_detail_template',
                                                    'Detail template',
                                                )
                                            "
                                            :required-missing="
                                                !form.detail_template_id
                                            "
                                            required-highlight-color="#fefce8"
                                            @blur="
                                                touchAndClear(
                                                    'detail_template_id',
                                                )
                                            "
                                        />
                                        <FieldValidationMessage
                                            :message="
                                                validationMessage(
                                                    'detail_template_id',
                                                )
                                            "
                                        />
                                        <p class="text-xs text-slate-500">
                                            {{
                                                t(
                                                    'pages.form.detail_template_help',
                                                    'Template used to render this public page and select its layout.',
                                                )
                                            }}
                                        </p>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="scroll_mode">{{
                                            t(
                                                'layouts.scroll_mode',
                                                'Scroll behavior',
                                            )
                                        }}</Label>
                                        <RwAutoCompleteInput
                                            id="scroll_mode"
                                            v-model="form.scroll_mode"
                                            :items="scrollModeOptions"
                                            item-title="label"
                                            item-value="value"
                                            :search-fields="['label', 'value']"
                                            :aria-label="
                                                t(
                                                    'layouts.scroll_mode',
                                                    'Scroll behavior',
                                                )
                                            "
                                        />
                                        <p class="text-xs text-slate-500">
                                            {{
                                                t(
                                                    'layouts.scroll_mode_help',
                                                    'Use normal browser scrolling unless you intentionally keep header/footer fixed around an internal content zone.',
                                                )
                                            }}
                                        </p>
                                        <p
                                            v-if="form.errors.scroll_mode"
                                            class="text-sm text-red-600"
                                        >
                                            {{ form.errors.scroll_mode }}
                                        </p>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="template">{{
                                            t(
                                                'content_form.template',
                                                'Template',
                                            )
                                        }}</Label>
                                        <Input
                                            id="template"
                                            v-model="form.template"
                                        />
                                        <p
                                            v-if="form.errors.template"
                                            class="text-sm text-red-600"
                                        >
                                            {{ form.errors.template }}
                                        </p>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="sort_order">{{
                                            t(
                                                'content_form.sort_order',
                                                'Order',
                                            )
                                        }}</Label>
                                        <Input
                                            id="sort_order"
                                            v-model="form.sort_order"
                                            type="number"
                                            min="0"
                                        />
                                        <p
                                            v-if="form.errors.sort_order"
                                            class="text-sm text-red-600"
                                        >
                                            {{ form.errors.sort_order }}
                                        </p>
                                    </div>
                                </div>
                            </section>
                        </section>

                        <section
                            id="cms-template-data-editor"
                            v-if="activeTab === 'content'"
                            class="grid gap-2"
                        >
                            <TemplateDataForm
                                :model-value="form.template_data"
                                :contract="selectedTemplateContract"
                                :locale="form.locale"
                                :title="
                                    t(
                                        'content_form.template_data_title',
                                        'Template content',
                                    )
                                "
                                :description="
                                    t(
                                        'content_form.template_data_description',
                                        'Fill the fields requested by the selected page template.',
                                    )
                                "
                                v-model:media-options="localMediaOptions"
                                v-model:media-folders="localMediaFolders"
                                :download-options="downloadOptions"
                                :download-folders="downloadFolders"
                                upload-context-type="page"
                                :upload-context-id="pageRecordId"
                                :errors="form.errors"
                                :empty-text="
                                    t(
                                        'content_form.template_data_empty',
                                        'This template does not request extra page content fields.',
                                    )
                                "
                                @update:model-value="updateTemplateData"
                                @blur="touchAndClear"
                            />
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
                                id-prefix="page_json_ld"
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
                            content-type="page"
                            :record-id="pageRecordId"
                        />

                        <section v-if="activeTab === 'css'" class="space-y-5">
                            <div>
                                <h2
                                    class="text-base font-semibold text-slate-900"
                                >
                                    {{
                                        t(
                                            'pages.form.css_title',
                                            'Page styling',
                                        )
                                    }}
                                </h2>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{
                                        t(
                                            'pages.form.css_description',
                                            'Configure styling for the page wrapper. Content blocks keep their own styling.',
                                        )
                                    }}
                                </p>
                            </div>

                            <div class="grid gap-4 lg:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="page_foreground_color">{{
                                        t(
                                            'pages.form.foreground_color',
                                            'Text color',
                                        )
                                    }}</Label>
                                    <div class="flex items-center gap-2">
                                        <Input
                                            id="page_foreground_color"
                                            v-model="
                                                form.page_style.foreground_color
                                            "
                                            type="color"
                                            class="h-10 w-16 p-1"
                                        />
                                        <Input
                                            v-model="
                                                form.page_style.foreground_color
                                            "
                                            :placeholder="
                                                t(
                                                    'layouts.colors.hex_placeholder',
                                                    '#1f2937',
                                                )
                                            "
                                        />
                                    </div>
                                    <p
                                        v-if="
                                            form.errors[
                                                'page_style.foreground_color'
                                            ]
                                        "
                                        class="text-sm text-red-600"
                                    >
                                        {{
                                            form.errors[
                                                'page_style.foreground_color'
                                            ]
                                        }}
                                    </p>
                                </div>

                                <div class="grid gap-2">
                                    <Label for="page_width_mode">{{
                                        t('pages.form.width_mode', 'Width mode')
                                    }}</Label>
                                    <RwAutoCompleteInput
                                        id="page_width_mode"
                                        v-model="form.page_style.width_mode"
                                        :items="pageWidthModeOptions"
                                        item-title="label"
                                        item-value="value"
                                        :search-fields="['label', 'value']"
                                        :aria-label="
                                            t(
                                                'pages.form.width_mode',
                                                'Width mode',
                                            )
                                        "
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="page_content_gap">{{
                                        t(
                                            'pages.form.content_gap',
                                            'Content gap',
                                        )
                                    }}</Label>
                                    <RwAutoCompleteInput
                                        id="page_content_gap"
                                        v-model="form.page_style.content_gap"
                                        :items="pageContentGapOptions"
                                        item-title="label"
                                        item-value="value"
                                        :search-fields="['label', 'value']"
                                        :aria-label="
                                            t(
                                                'pages.form.content_gap',
                                                'Content gap',
                                            )
                                        "
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="page_css_class">{{
                                        t('pages.form.css_class', 'CSS class')
                                    }}</Label>
                                    <Input
                                        id="page_css_class"
                                        v-model="form.page_style.css_class"
                                        autocomplete="off"
                                        :placeholder="
                                            t(
                                                'pages.form.css_class_placeholder',
                                                'custom-page-class',
                                            )
                                        "
                                    />
                                    <p class="text-xs text-slate-500">
                                        {{
                                            t(
                                                'pages.form.css_class_help',
                                                'Optional class added to the public page wrapper.',
                                            )
                                        }}
                                    </p>
                                </div>

                                <div class="grid gap-2">
                                    <Label for="page_html_anchor">{{
                                        t(
                                            'pages.form.html_anchor',
                                            'HTML anchor',
                                        )
                                    }}</Label>
                                    <Input
                                        id="page_html_anchor"
                                        v-model="form.page_style.html_anchor"
                                        autocomplete="off"
                                        :placeholder="
                                            t(
                                                'pages.form.html_anchor_placeholder',
                                                'page-anchor',
                                            )
                                        "
                                    />
                                    <p class="text-xs text-slate-500">
                                        {{
                                            t(
                                                'pages.form.html_anchor_help',
                                                'Optional stable id for the public page wrapper.',
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>

                            <BackgroundPickerField
                                v-model="form.page_style.background"
                                v-model:palette-items="localColorPaletteItems"
                                :assets="localMediaOptions"
                                :folders="localMediaFolders"
                                id-prefix="page-background"
                                :label="
                                    t(
                                        'pages.form.background_title',
                                        'Page background',
                                    )
                                "
                            />

                            <BoxSpacingEditor
                                v-model="form.page_style.box"
                                id-prefix="page-box"
                                :title="
                                    t('pages.form.box_title', 'Page spacing')
                                "
                                :description="
                                    t(
                                        'pages.form.box_description',
                                        'Adjust page wrapper padding and margin per device.',
                                    )
                                "
                            />
                        </section>

                        <section
                            v-if="activeTab === 'code' && canManageCodeBlocks"
                            class="space-y-5"
                        >
                            <div>
                                <h2
                                    class="text-base font-semibold text-slate-900"
                                >
                                    {{
                                        t('pages.form.code_title', 'Page code')
                                    }}
                                </h2>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{
                                        t(
                                            'pages.form.code_description',
                                            'Trusted developer code for this page only.',
                                        )
                                    }}
                                </p>
                            </div>

                            <p
                                class="flex items-start gap-2 rounded-md border border-orange-200 bg-orange-50 px-3 py-2 text-sm leading-5 text-orange-800"
                            >
                                <span
                                    class="mdi mdi-alert-circle flex h-5 w-5 shrink-0 items-center justify-center text-base leading-none text-orange-700"
                                    aria-hidden="true"
                                />
                                <span>
                                    {{
                                        t(
                                            'pages.form.code_warning',
                                            'This code is rendered unescaped on the public site. Use only trusted snippets.',
                                        )
                                    }}
                                </span>
                            </p>

                            <div class="grid gap-2">
                                <Label for="page_css_source">{{
                                    t('pages.form.page_css', 'Page CSS')
                                }}</Label>
                                <RwCodeEditor
                                    id="page_css_source"
                                    v-model="form.developer.css_source"
                                    language="css"
                                    height="280px"
                                    :line-wrapping="true"
                                    :placeholder="
                                        t(
                                            'pages.form.page_css_placeholder',
                                            '/* Page-specific CSS */',
                                        )
                                    "
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="page_head_code">{{
                                    t('pages.form.head_code', 'Head code')
                                }}</Label>
                                <RwCodeEditor
                                    id="page_head_code"
                                    v-model="form.developer.head_code"
                                    language="html"
                                    height="240px"
                                    :line-wrapping="true"
                                    :placeholder="
                                        t(
                                            'pages.form.head_code_placeholder',
                                            '<!-- Page-specific head code -->',
                                        )
                                    "
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="page_body_end_code">{{
                                    t(
                                        'pages.form.body_end_code',
                                        'Body-end code',
                                    )
                                }}</Label>
                                <RwCodeEditor
                                    id="page_body_end_code"
                                    v-model="form.developer.body_end_code"
                                    language="html"
                                    height="240px"
                                    :line-wrapping="true"
                                    :placeholder="
                                        t(
                                            'pages.form.body_end_code_placeholder',
                                            '<!-- Page-specific body-end code -->',
                                        )
                                    "
                                />
                            </div>
                        </section>

                        <section
                            v-if="activeTab === 'layout'"
                            class="grid gap-4"
                        >
                            <div class="grid gap-2">
                                <Label for="parent_id">{{
                                    t('content_form.parent_page', 'Parent page')
                                }}</Label>
                                <RwAutoCompleteInput
                                    id="parent_id"
                                    v-model="form.parent_id"
                                    :items="parentSelectOptions"
                                    item-title="label"
                                    item-value="value"
                                    :search-fields="['label', 'locale']"
                                    :aria-label="
                                        t(
                                            'content_form.parent_page',
                                            'Parent page',
                                        )
                                    "
                                />
                                <p
                                    v-if="form.errors.parent_id"
                                    class="text-sm text-red-600"
                                >
                                    {{ form.errors.parent_id }}
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <Label
                                    for="detail_template_id"
                                    class="flex items-center gap-1"
                                >
                                    <span
                                        class="text-red-600"
                                        aria-hidden="true"
                                        >*</span
                                    >
                                    {{
                                        t(
                                            'templates.fields.page_detail_template',
                                            'Detail template',
                                        )
                                    }}
                                </Label>
                                <RwAutoCompleteInput
                                    id="detail_template_id"
                                    v-model="form.detail_template_id"
                                    :items="filteredDetailTemplateOptions"
                                    item-title="label"
                                    item-value="id"
                                    :search-fields="['label', 'name', 'locale']"
                                    :aria-label="
                                        t(
                                            'templates.fields.page_detail_template',
                                            'Detail template',
                                        )
                                    "
                                    :required-missing="!form.detail_template_id"
                                    required-highlight-color="#fefce8"
                                    @blur="touchAndClear('detail_template_id')"
                                />
                                <FieldValidationMessage
                                    :message="
                                        validationMessage('detail_template_id')
                                    "
                                />
                                <p class="text-xs text-slate-500">
                                    {{
                                        t(
                                            'pages.form.detail_template_help',
                                            'Template used to render this public page and select its layout.',
                                        )
                                    }}
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <Label for="scroll_mode">{{
                                    t('layouts.scroll_mode', 'Scroll behavior')
                                }}</Label>
                                <RwAutoCompleteInput
                                    id="scroll_mode"
                                    v-model="form.scroll_mode"
                                    :items="scrollModeOptions"
                                    item-title="label"
                                    item-value="value"
                                    :search-fields="['label', 'value']"
                                    :aria-label="
                                        t(
                                            'layouts.scroll_mode',
                                            'Scroll behavior',
                                        )
                                    "
                                />
                                <p class="text-xs text-slate-500">
                                    {{
                                        t(
                                            'layouts.scroll_mode_help',
                                            'Use normal browser scrolling unless you intentionally keep header/footer fixed around an internal content zone.',
                                        )
                                    }}
                                </p>
                                <p
                                    v-if="form.errors.scroll_mode"
                                    class="text-sm text-red-600"
                                >
                                    {{ form.errors.scroll_mode }}
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <Label for="template">{{
                                    t('content_form.template', 'Template')
                                }}</Label>
                                <Input id="template" v-model="form.template" />
                                <p
                                    v-if="form.errors.template"
                                    class="text-sm text-red-600"
                                >
                                    {{ form.errors.template }}
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <Label for="sort_order">{{
                                    t('content_form.sort_order', 'Order')
                                }}</Label>
                                <Input
                                    id="sort_order"
                                    v-model="form.sort_order"
                                    type="number"
                                    min="0"
                                />
                                <p
                                    v-if="form.errors.sort_order"
                                    class="text-sm text-red-600"
                                >
                                    {{ form.errors.sort_order }}
                                </p>
                            </div>
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
                                                'pages.form.translations_description',
                                                'Create and open linked language versions of this page.',
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
                                                route('admin.cms.pages.edit', {
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
                                                    'content_form.missing_page_help',
                                                    'No linked page for this active language yet.',
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
                        t('content_form.make_translation', 'Maak vertaling')
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            t(
                                'content_form.page_translation_dialog_description',
                                'Kies of de conceptpagina met AI vertaald wordt of eerst als kopie wordt aangemaakt.',
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
                                    'content_form.ai_page_help',
                                    'Met AI vertalen maakt direct een vertaalde conceptpagina.',
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

        <Dialog v-model:open="showDeleteDialog">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{
                        t('content_form.delete_page_title', 'Delete page')
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            t(
                                'content_form.delete_page_description',
                                'Choose whether only this page is deleted, or all linked translations as well.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 py-2">
                    <div
                        class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-900"
                    >
                        {{
                            t(
                                'content_form.delete_page_warning',
                                'This action removes the page from the public site. Records are safely soft-deleted.',
                            )
                        }}
                    </div>

                    <label
                        class="flex items-start gap-3 rounded-md border border-slate-200 p-3 text-sm"
                    >
                        <input
                            v-model="deleteForm.delete_translations"
                            type="checkbox"
                            class="mt-1 h-4 w-4 rounded border-slate-300"
                        />
                        <span>
                            <span class="block font-semibold text-slate-900">
                                {{
                                    t(
                                        'content_form.delete_translations',
                                        'Delete all language versions',
                                    )
                                }}
                            </span>
                            <span class="block text-slate-500">
                                {{
                                    t(
                                        'content_form.delete_translations_help',
                                        'All pages with the same translation link are deleted. Linked menu items are automatically disabled.',
                                    )
                                }}
                            </span>
                        </span>
                    </label>

                    <div
                        v-if="otherTranslations.length > 0"
                        class="grid gap-1 text-sm text-slate-600"
                    >
                        <div class="font-medium text-slate-800">
                            {{
                                t(
                                    'content_form.linked_translations',
                                    'Linked translations:',
                                )
                            }}
                        </div>
                        <div
                            v-for="translation in otherTranslations"
                            :key="translation.id"
                        >
                            {{ translation.locale }} - {{ translation.title }}
                        </div>
                    </div>
                </div>

                <DialogFooter class="justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="border-red-200 text-red-700 shadow-none hover:bg-red-50 hover:text-red-800"
                        :disabled="deleteForm.processing"
                        @click="deletePage"
                    >
                        {{ t('themes.delete', 'Delete') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <CmsRevisionHistoryDialog
            v-if="isEditMode"
            v-model:open="showRevisionDialog"
            subject-type="page"
            restore-route-name="admin.cms.pages.revisions.restore"
            :restore-route-params="{ page: pageItem.id }"
            :revisions="revisions"
        />
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminFormBackButton from '@/Components/Admin/Form/AdminFormBackButton.vue';
import AdminFormSaveButton from '@/Components/Admin/Form/AdminFormSaveButton.vue';
import RwAutoCompleteInput from '@/Components/RwAutoCompleteInput.vue';
import RwCodeEditor from '@/Components/RwCodeEditor.vue';
import RwFlashMessage from '@/Components/RwFlashMessage.vue';
import CmsContentStatisticsPanel from '@/Pages/Admin/Cms/Components/CmsContentStatisticsPanel.vue';
import CmsRevisionHistoryDialog from '@/Pages/Admin/Cms/Components/CmsRevisionHistoryDialog.vue';
import TemplateDataForm from '@/Pages/Admin/Cms/Components/TemplateDataForm.vue';
import BackgroundPickerField from '@/Pages/Admin/Cms/Layouts/Partials/BackgroundPickerField.vue';
import BoxSpacingEditor from '@/Pages/Admin/Cms/Layouts/Partials/BoxSpacingEditor.vue';
import { normalizeBoxSpacing } from '@/Pages/Admin/Cms/Layouts/Partials/boxSpacing.js';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    pageItem: {
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
    canManageCodeBlocks: {
        type: Boolean,
        default: false,
    },
    colorPaletteItems: {
        type: Array,
        default: () => [],
    },
    styleTokenOptions: {
        type: Object,
        default: () => ({}),
    },
    activeThemeFontFaceCss: {
        type: String,
        default: '',
    },
    parentOptions: {
        type: Array,
        required: true,
    },
    detailTemplateOptions: {
        type: Array,
        default: () => [],
    },
    statusOptions: {
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
const { t: commonT } = useAdminTranslations('admin_common_ui');
const page = usePage();

const metaTabs = computed(() => [
    { key: 'seo', label: t('content_form.seo', 'SEO') },
    { key: 'json_ld', label: t('content_form.json_ld', 'JSON-LD') },
]);

const activeTab = ref('basis');
const activeMetaTab = ref('seo');
const showTranslationDialog = ref(false);
const showDeleteDialog = ref(false);
const showRevisionDialog = ref(false);
const localMediaOptions = ref([...props.mediaOptions]);
const localMediaFolders = ref([...props.mediaFolders]);
const localColorPaletteItems = ref([...props.colorPaletteItems]);

const form = useForm({
    parent_id: props.pageItem?.parent_id ?? '',
    detail_template_id: props.pageItem?.detail_template_id ?? '',
    title: props.pageItem?.title ?? '',
    slug: props.pageItem?.slug ?? '',
    locale: props.pageItem?.locale ?? props.activeLanguages[0]?.locale ?? 'nl',
    status: props.pageItem?.status ?? 'draft',
    template: props.pageItem?.template ?? '',
    short_description: props.pageItem?.short_description ?? '',
    template_data: normalizeTemplateData(props.pageItem?.template_data),
    seo_title: props.pageItem?.seo_title ?? '',
    seo_description: props.pageItem?.seo_description ?? '',
    canonical_url: props.pageItem?.canonical_url ?? '',
    og_image_path: props.pageItem?.og_image_path ?? '',
    noindex: Boolean(props.pageItem?.noindex ?? false),
    is_home: Boolean(props.pageItem?.is_home ?? false),
    is_searchable: Boolean(props.pageItem?.is_searchable ?? true),
    pdf_download_enabled: Boolean(
        props.pageItem?.pdf_download_enabled ?? false,
    ),
    sort_order: props.pageItem?.sort_order ?? 0,
    published_at: props.pageItem?.published_at ?? '',
    scroll_mode: props.pageItem?.scroll_mode ?? 'inherit',
    structured_data_schema_type:
        props.pageItem?.structured_data_schema_type ?? 'auto',
    structured_data_extra: props.pageItem?.structured_data_extra ?? '',
    page_style: normalizePageStyle(props.pageItem?.page_style),
    developer: normalizePageDeveloper(props.pageItem?.developer),
});

const selectedTemplateContract = computed(
    () =>
        props.detailTemplateOptions.find(
            (template) =>
                String(template.id) === String(form.detail_template_id),
        )?.block_data_contract || { blocks: [] },
);

const {
    FieldValidationMessage,
    validation: fieldValidation,
    formValidation,
    message: validationMessage,
    warning: validationWarning,
    counterMax,
    touchAndClear,
    validationFlash,
} = useCmsFormValidation(form, {
    fields: {
        ...createCmsSeoFields(form, {
            t,
            seoSettings: props.seoSettings,
        }),
        detail_template_id: {
            label: t(
                'templates.fields.page_detail_template',
                'Detail template',
            ),
            elementId: 'detail_template_id',
            value: () => form.detail_template_id,
            rules: [
                (value) =>
                    String(value ?? '').trim() !== '' ||
                    t('validation.required', 'This field is required.'),
            ],
        },
    },
    serverFields: {
        'template_data.*': {
            label: t('content_form.template_data_title', 'Template content'),
            tab: 'content',
            elementId: 'cms-template-data-editor',
        },
    },
    messages: {
        blocked: t(
            'validation.client_error_flash',
            'Saving is blocked. Check the validation messages below.',
        ),
        client: t(
            'validation.client_error_flash',
            'Saving is blocked. Check the validation messages below.',
        ),
        server: t(
            'validation.server_error_flash',
            'Saving failed. Check the validation messages below.',
        ),
    },
    activateTab: (tab) => {
        if (['basis', 'content', 'seo', 'css', 'code'].includes(tab)) {
            activeTab.value = tab;

            return;
        }

        activeTab.value = 'seo';
        activeMetaTab.value = tab;
    },
});
const { errors: validationErrors } = fieldValidation;
const { validateBeforeSubmit, scrollToIssue } = formValidation;

const isEditMode = computed(() => Boolean(props.pageItem?.id));
const pageRecordId = computed(() => props.pageItem?.id ?? null);
const recordIdLabel = computed(() => props.pageItem?.id ?? '-');
const updatedAtLabel = computed(() =>
    formatRecordDate(props.pageItem?.updated_at),
);
const createdAtLabel = computed(() =>
    formatRecordDate(props.pageItem?.created_at),
);
const pageTitle = computed(() =>
    isEditMode.value
        ? t('pages.form.edit_title', 'Pagina bewerken')
        : t('pages.form.create_title', 'Pagina toevoegen'),
);
const backHref = computed(() =>
    resolveReturnToUrl(route('admin.cms.pages.index')),
);
const isMultilingualEnabled = computed(() => props.multilingualEnabled);
const filteredDetailTemplateOptions = computed(() => [
    ...props.detailTemplateOptions.filter(
        (option) => option.locale === form.locale,
    ),
]);
const tabOptions = computed(() => [
    { value: 'basis', label: t('pages.form.tabs.basic', 'Basic') },
    { value: 'content', label: t('pages.form.tabs.content', 'Content') },
    { value: 'seo', label: t('pages.form.tabs.seo', 'SEO') },
    ...(isEditMode.value
        ? [
              {
                  value: 'statistics',
                  label: t('content_form.tabs.statistics', 'Statistics'),
              },
          ]
        : []),
    { value: 'css', label: t('pages.form.tabs.css', 'CSS') },
    ...(props.canManageCodeBlocks
        ? [{ value: 'code', label: t('pages.form.tabs.code', 'Code') }]
        : []),
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
const translationsByLocale = computed(() => {
    const map = new Map();

    props.translations.forEach((translation) => {
        if (translation?.locale) {
            map.set(translation.locale, translation);
        }
    });

    return map;
});
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
const parentSelectOptions = computed(() => [
    { value: '', label: t('content_form.none', 'None'), locale: '' },
    ...props.parentOptions.map((option) => ({
        value: option.id,
        label: `${option.title} (${option.locale})`,
        locale: option.locale,
    })),
]);
const scrollModeOptions = computed(() => [
    {
        value: 'inherit',
        label: t('layouts.scroll_modes.inherit', 'Inherit from layout'),
    },
    {
        value: 'browser',
        label: t('layouts.scroll_modes.browser', 'Normal browser scroll'),
    },
    {
        value: 'internal',
        label: t('layouts.scroll_modes.internal', 'Internal content scroll'),
    },
]);
const missingLanguages = computed(() =>
    props.missingLanguages.filter(
        (language) => language.locale !== form.locale,
    ),
);

const translationStatusItems = computed(() => {
    const items = [];
    const missingLocaleSet = new Set(
        missingLanguages.value.map((language) => language.locale),
    );

    selectableLanguages.value.forEach((language) => {
        const translation = translationsByLocale.value.get(language.locale);

        if (translation) {
            items.push({
                key: `translation-${translation.id}`,
                label: languageLabel(language),
                type: translation.is_current ? 'current' : 'translation',
                id: translation.id,
                status:
                    translation.status === 'published' ? 'success' : 'warning',
                isCurrent: translation.is_current,
            });

            return;
        }

        if (missingLocaleSet.has(language.locale)) {
            items.push({
                key: `missing-${language.locale}`,
                label: languageLabel(language),
                type: 'missing',
                locale: language.locale,
                status: 'danger',
                isCurrent: false,
            });
        }
    });

    return items;
});

const saveButtonDirty = computed(
    () => form.isDirty || validationErrors.value.length > 0,
);

const pageWidthModeOptions = computed(() => [
    { value: 'content', label: t('pages.form.width_content', 'Content width') },
    { value: 'display', label: t('pages.form.width_display', 'Display width') },
]);

const pageContentGapOptions = computed(() => [
    { value: 'none', label: t('pages.form.gap_none', 'No gap') },
    { value: 'compact', label: t('pages.form.gap_compact', 'Compact') },
    { value: 'normal', label: t('pages.form.gap_normal', 'Normal') },
    { value: 'spacious', label: t('pages.form.gap_spacious', 'Spacious') },
]);

const selectedTranslationLanguageLabel = computed(() => {
    const language = missingLanguages.value.find(
        (item) => item.locale === translationForm.target_locale,
    );

    return language
        ? languageLabel(language)
        : t('content_form.no_language_selected', 'Geen taal gekozen');
});

const translationForm = useForm({
    target_locale: '',
    use_ai: true,
});

const deleteForm = useForm({
    delete_translations: false,
});

function normalizePageStyle(value) {
    const style = value && typeof value === 'object' ? value : {};
    const widthModes = ['content', 'display'];
    const contentGaps = ['none', 'compact', 'normal', 'spacious'];

    return {
        foreground_color: normalizeHexColor(style.foreground_color),
        width_mode: widthModes.includes(style.width_mode)
            ? style.width_mode
            : 'content',
        content_gap: contentGaps.includes(style.content_gap)
            ? style.content_gap
            : 'normal',
        css_class: typeof style.css_class === 'string' ? style.css_class : '',
        html_anchor:
            typeof style.html_anchor === 'string' ? style.html_anchor : '',
        background: normalizePageBackground(style.background),
        box: normalizeBoxSpacing(style.box),
    };
}

function normalizePageBackground(value) {
    const background = value && typeof value === 'object' ? value : {};
    const imageModes = [
        'cover',
        'contain',
        'stretch',
        'center',
        'repeat',
        'repeat-x',
        'repeat-y',
    ];
    const imagePositions = [
        'center center',
        'center top',
        'center bottom',
        'left center',
        'right center',
    ];
    const opacity = Number(background.image_opacity ?? 100);

    return {
        color: normalizeHexColor(background.color),
        media_asset_id: background.media_asset_id || null,
        mode: imageModes.includes(background.mode) ? background.mode : 'cover',
        position: imagePositions.includes(background.position)
            ? background.position
            : 'center center',
        image_opacity: Number.isFinite(opacity)
            ? Math.min(100, Math.max(0, Math.round(opacity)))
            : 100,
    };
}

function normalizePageDeveloper(value) {
    const developer = value && typeof value === 'object' ? value : {};

    return {
        css_source:
            typeof developer.css_source === 'string'
                ? developer.css_source
                : '',
        head_code:
            typeof developer.head_code === 'string' ? developer.head_code : '',
        body_end_code:
            typeof developer.body_end_code === 'string'
                ? developer.body_end_code
                : '',
    };
}

function normalizeTemplateData(value) {
    return value && typeof value === 'object' && !Array.isArray(value)
        ? value
        : {};
}

function normalizeHexColor(value) {
    return typeof value === 'string' && /^#[0-9a-fA-F]{6}$/.test(value)
        ? value
        : '';
}

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

function updateTemplateData(value) {
    form.template_data = normalizeTemplateData(value);
}

async function submit() {
    if (!(await validateBeforeSubmit())) {
        return;
    }
    form.parent_id = form.parent_id || null;
    form.detail_template_id = form.detail_template_id || null;
    form.structured_data_extra = form.structured_data_extra || null;

    form.post(route('admin.cms.pages.store', { id: props.pageItem?.id ?? 0 }), {
        preserveScroll: true,
        preserveState: true,
    });
}

function openTranslationDialog(locale = '') {
    translationForm.clearErrors();
    translationForm.target_locale =
        locale || missingLanguages.value[0]?.locale || '';
    translationForm.use_ai = true;
    showTranslationDialog.value = true;
}

function createTranslation(useAi) {
    if (!props.pageItem?.id) {
        return;
    }

    translationForm.use_ai = useAi;
    translationForm.post(
        route('admin.cms.pages.translations.store', { id: props.pageItem.id }),
    );
}

function openDeleteDialog() {
    deleteForm.clearErrors();
    deleteForm.delete_translations = false;
    showDeleteDialog.value = true;
}

function deletePage() {
    if (!props.pageItem?.id) {
        return;
    }

    deleteForm.delete(
        route('admin.cms.pages.destroy', { id: props.pageItem.id }),
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

function handleTranslationChipClick(item) {
    if (item.type === 'translation' && item.id) {
        router.visit(route('admin.cms.pages.edit', { id: item.id }));

        return;
    }

    if (item.type === 'missing' && item.locale) {
        openTranslationDialog(item.locale);
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
    const label = language.native_name || language.name || language.locale;

    return `${label} (${language.locale})`;
}

function formatRecordDate(value) {
    if (!value) {
        return '-';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '-';
    }

    return new Intl.DateTimeFormat('nl-BE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(date);
}
</script>
