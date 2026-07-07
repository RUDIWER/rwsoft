<template>
    <Head :title="pageTitle" />

    <AdminLayout :title="pageTitle" :suppress-flash="true">
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
                                <span class="mdi mdi-folder-tag text-2xl" />
                            </div>
                            <div class="min-w-0">
                                <CardTitle class="text-lg">
                                    {{ pageTitle }}
                                </CardTitle>
                                <CardDescription class="mt-1">
                                    {{
                                        t(
                                            'categories.form.basic_description',
                                            'This category automatically creates a public CMS page.',
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
                                :label="commonT('actions.back', 'Back')"
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
                                :label="commonT('actions.save', 'Save')"
                            />
                        </div>
                    </div>
                </CardHeader>

                <div
                    class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700 sm:px-5"
                >
                    <div class="font-medium text-slate-700">
                        {{ commonT('record_meta.id', 'ID') }}:
                        <span class="ml-1 text-base font-bold text-slate-950">
                            {{ recordIdLabel }}
                        </span>
                        <span
                            v-if="isEditMode"
                            class="ml-1 text-sm font-semibold text-slate-600"
                            :title="currentRevisionTitle"
                        >
                            ({{ currentRevisionLabel }})
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

                <CardContent class="min-h-0 flex-1 overflow-y-auto p-0">
                    <div
                        class="sticky top-0 z-10 border-b border-slate-200 bg-white"
                    >
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
                        <AiTranslationReviewBanner
                            v-if="isEditMode"
                            type="category"
                            :record-id="category.id"
                            :review="category.ai_translation_review"
                        />

                        <FormValidationSummary
                            class="mb-5"
                            :visible="showSummary"
                            :errors="allValidationErrors"
                            :title="
                                commonT(
                                    'validation.summary_title',
                                    'Saving is blocked',
                                )
                            "
                            :description="
                                commonT(
                                    'validation.summary_description',
                                    'Resolve the fields below and try again.',
                                )
                            "
                            @select="scrollToIssue"
                        />

                        <section v-if="activeTab === 'basis'" class="space-y-5">
                            <div class="space-y-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="text-xs font-medium text-slate-600"
                                    >
                                        {{
                                            t(
                                                'forms.form.translation_status_label',
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
                                            'categories.form.save_before_translations',
                                            'Save the category first to manage translations.',
                                        )
                                    }}
                                </p>
                            </div>

                            <div
                                class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]"
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

                                    <div class="grid gap-4 md:grid-cols-2">
                                        <div
                                            v-if="!isEditMode"
                                            class="grid gap-2"
                                        >
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
                                                :items="selectableLanguages"
                                                :item-title="languageLabel"
                                                item-value="locale"
                                                :search-fields="[
                                                    'locale',
                                                    'name',
                                                    'native_name',
                                                ]"
                                                :required="true"
                                                :invalid="
                                                    Boolean(
                                                        validationMessage(
                                                            'locale',
                                                        ),
                                                    )
                                                "
                                                :error-message="
                                                    validationMessage('locale')
                                                "
                                                @blur="touchAndClear('locale')"
                                            />
                                            <FieldValidationMessage
                                                :message="
                                                    validationMessage('locale')
                                                "
                                                :value="form.locale"
                                            />
                                        </div>

                                        <div v-else class="grid gap-2">
                                            <Label>
                                                {{
                                                    t(
                                                        'common.columns.locale',
                                                        'Language',
                                                    )
                                                }}
                                            </Label>
                                            <div
                                                class="inline-flex w-fit items-center rounded bg-slate-100 px-3 py-1.5 text-sm font-semibold text-slate-800 ring-1 ring-slate-200"
                                            >
                                                {{ selectedLanguageLabel }}
                                            </div>
                                            <p class="text-xs text-slate-500">
                                                {{
                                                    t(
                                                        'categories.form.language_readonly_help',
                                                        'The language is fixed for existing categories. Use translations to create another language version.',
                                                    )
                                                }}
                                            </p>
                                        </div>

                                        <div class="grid gap-2">
                                            <Label for="parent_id">
                                                {{
                                                    t(
                                                        'content_form.parent_category',
                                                        'Parent category',
                                                    )
                                                }}
                                            </Label>
                                            <RwAutoCompleteInput
                                                id="parent_id"
                                                v-model="form.parent_id"
                                                :items="parentSelectOptions"
                                                item-title="label"
                                                item-value="id"
                                                :search-fields="[
                                                    'label',
                                                    'title',
                                                    'locale',
                                                ]"
                                                @blur="
                                                    touchAndClear('parent_id')
                                                "
                                            />
                                            <FieldValidationMessage
                                                :message="
                                                    validationMessage(
                                                        'parent_id',
                                                    )
                                                "
                                                :value="form.parent_id"
                                            />
                                        </div>

                                        <div class="grid gap-2">
                                            <Label for="type">
                                                {{
                                                    t(
                                                        'common.columns.type',
                                                        'Type',
                                                    )
                                                }}
                                            </Label>
                                            <RwAutoCompleteInput
                                                id="type"
                                                v-model="form.type"
                                                :items="typeOptions"
                                                item-title="label"
                                                item-value="value"
                                                :search-fields="[
                                                    'label',
                                                    'value',
                                                ]"
                                                @blur="touchAndClear('type')"
                                            />
                                            <FieldValidationMessage
                                                :message="
                                                    validationMessage('type')
                                                "
                                                :value="form.type"
                                            />
                                        </div>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="description">
                                            {{
                                                t(
                                                    'categories.form.description_label',
                                                    'Category description',
                                                )
                                            }}
                                        </Label>
                                        <textarea
                                            id="description"
                                            v-model="form.description"
                                            rows="3"
                                            class="min-h-20 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                            @blur="touchAndClear('description')"
                                        />
                                        <FieldValidationMessage
                                            :message="
                                                validationMessage('description')
                                            "
                                            :value="form.description"
                                        />
                                    </div>
                                </div>

                                <aside
                                    class="grid content-start gap-4 rounded-md border border-slate-200 bg-slate-50 p-3"
                                >
                                    <h2
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        {{
                                            t(
                                                'categories.form.visibility_title',
                                                'Visibility and publication',
                                            )
                                        }}
                                    </h2>

                                    <div class="grid gap-2">
                                        <label
                                            class="flex items-center gap-2 text-sm"
                                        >
                                            <input
                                                v-model="form.is_active"
                                                type="checkbox"
                                                class="h-4 w-4 rounded border-slate-300"
                                            />
                                            {{
                                                t(
                                                    'categories.form.active',
                                                    'Category active',
                                                )
                                            }}
                                        </label>
                                        <p class="text-xs text-slate-500">
                                            {{
                                                t(
                                                    'categories.form.active_help',
                                                    'Determines whether this category can be used in CMS relations and overviews.',
                                                )
                                            }}
                                        </p>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="status">
                                            {{
                                                t(
                                                    'categories.form.page_status',
                                                    'Page publication status',
                                                )
                                            }}
                                        </Label>
                                        <RwAutoCompleteInput
                                            id="status"
                                            v-model="form.status"
                                            :items="statusOptions"
                                            item-title="label"
                                            item-value="value"
                                            :search-fields="['label', 'value']"
                                            @blur="touchAndClear('status')"
                                        />
                                        <FieldValidationMessage
                                            :message="
                                                validationMessage('status')
                                            "
                                            :value="form.status"
                                        />
                                        <p class="text-xs text-slate-500">
                                            {{
                                                t(
                                                    'categories.form.page_status_help',
                                                    'Determines whether the linked category page is publicly visible.',
                                                )
                                            }}
                                        </p>
                                    </div>

                                    <div class="grid gap-2">
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
                                                    'Include in search',
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
                                                    'content_form.pdf_download_taxonomy_help',
                                                    'Adds public .pdf download URLs for the archive and info pages when they are published.',
                                                )
                                            }}
                                        </p>
                                    </div>
                                </aside>
                            </div>
                        </section>

                        <section
                            v-if="activeTab === 'weergave'"
                            class="space-y-5"
                        >
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="archive_template_id">
                                        {{
                                            t(
                                                'templates.fields.category_archive_template',
                                                'Archive template',
                                            )
                                        }}
                                    </Label>
                                    <RwAutoCompleteInput
                                        id="archive_template_id"
                                        v-model="form.archive_template_id"
                                        :items="filteredArchiveTemplateOptions"
                                        item-title="label"
                                        item-value="id"
                                        :search-fields="[
                                            'label',
                                            'name',
                                            'locale',
                                        ]"
                                        @blur="
                                            touchAndClear('archive_template_id')
                                        "
                                    />
                                    <FieldValidationMessage
                                        :message="
                                            validationMessage(
                                                'archive_template_id',
                                            )
                                        "
                                        :value="form.archive_template_id"
                                    />
                                    <p class="text-xs text-slate-500">
                                        {{
                                            t(
                                                'categories.form.archive_template_help',
                                                'Optional template for the category archive page with subcategories and blogs.',
                                            )
                                        }}
                                    </p>
                                    <p
                                        v-if="
                                            !form.archive_template_id &&
                                            defaultArchiveTemplateOption
                                        "
                                        class="text-xs font-medium text-blue-700"
                                    >
                                        {{
                                            t(
                                                'categories.form.default_archive_template_active',
                                                'Standaardtemplate actief: :name',
                                                {
                                                    name: defaultArchiveTemplateOption.name,
                                                },
                                            )
                                        }}
                                    </p>
                                </div>

                                <div class="grid gap-2">
                                    <Label for="detail_template_id">
                                        {{
                                            t(
                                                'templates.fields.category_detail_template',
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
                                        :search-fields="[
                                            'label',
                                            'name',
                                            'locale',
                                        ]"
                                        @blur="
                                            touchAndClear('detail_template_id')
                                        "
                                    />
                                    <FieldValidationMessage
                                        :message="
                                            validationMessage(
                                                'detail_template_id',
                                            )
                                        "
                                        :value="form.detail_template_id"
                                    />
                                    <p class="text-xs text-slate-500">
                                        {{
                                            t(
                                                'categories.form.detail_template_help',
                                                'Optional template for the category info/detail page.',
                                            )
                                        }}
                                    </p>
                                    <p
                                        v-if="
                                            !form.detail_template_id &&
                                            defaultDetailTemplateOption
                                        "
                                        class="text-xs font-medium text-blue-700"
                                    >
                                        {{
                                            t(
                                                'categories.form.default_detail_template_active',
                                                'Standaardtemplate actief: :name',
                                                {
                                                    name: defaultDetailTemplateOption.name,
                                                },
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div
                                    class="grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-4"
                                >
                                    <div>
                                        <h2
                                            class="text-sm font-semibold text-slate-900"
                                        >
                                            {{
                                                t(
                                                    'categories.form.archive_preview_title',
                                                    'Archive preview',
                                                )
                                            }}
                                        </h2>
                                        <p class="text-xs text-slate-500">
                                            {{
                                                t(
                                                    'categories.form.archive_preview_help',
                                                    'Open de publieke archivepagina voor deze categorie met subcategorieën en blogs.',
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div class="text-sm text-slate-600">
                                        {{
                                            selectedArchiveTemplateOption?.name ||
                                            defaultArchiveTemplateOption?.name ||
                                            t(
                                                'templates.form.default_template',
                                                'Default template',
                                            )
                                        }}
                                    </div>
                                    <Button
                                        v-if="category?.preview_archive_url"
                                        as-child
                                        type="button"
                                        variant="outline"
                                        class="w-fit gap-2 shadow-none"
                                    >
                                        <a
                                            :href="category.preview_archive_url"
                                            target="_blank"
                                            rel="noopener"
                                        >
                                            <span
                                                class="mdi mdi-open-in-new text-base"
                                                aria-hidden="true"
                                            />
                                            {{
                                                t(
                                                    'categories.form.preview_archive',
                                                    'Open archive preview',
                                                )
                                            }}
                                        </a>
                                    </Button>
                                </div>

                                <div
                                    class="grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-4"
                                >
                                    <div>
                                        <h2
                                            class="text-sm font-semibold text-slate-900"
                                        >
                                            {{
                                                t(
                                                    'categories.form.detail_preview_title',
                                                    'Detail preview',
                                                )
                                            }}
                                        </h2>
                                        <p class="text-xs text-slate-500">
                                            {{
                                                t(
                                                    'categories.form.detail_preview_help',
                                                    'Open de publieke infopagina voor deze categorie.',
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div class="text-sm text-slate-600">
                                        {{
                                            selectedDetailTemplateOption?.name ||
                                            defaultDetailTemplateOption?.name ||
                                            t(
                                                'templates.form.default_template',
                                                'Default template',
                                            )
                                        }}
                                    </div>
                                    <Button
                                        v-if="category?.preview_detail_url"
                                        as-child
                                        type="button"
                                        variant="outline"
                                        class="w-fit gap-2 shadow-none"
                                    >
                                        <a
                                            :href="category.preview_detail_url"
                                            target="_blank"
                                            rel="noopener"
                                        >
                                            <span
                                                class="mdi mdi-open-in-new text-base"
                                                aria-hidden="true"
                                            />
                                            {{
                                                t(
                                                    'categories.form.preview_detail',
                                                    'Open detail preview',
                                                )
                                            }}
                                        </a>
                                    </Button>
                                </div>
                            </div>
                        </section>

                        <section
                            v-if="activeTab === 'content'"
                            class="space-y-5"
                        >
                            <div
                                class="rounded-md border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700"
                            >
                                <div class="font-semibold text-slate-900">
                                    {{
                                        t(
                                            'categories.form.content_slot_title',
                                            'Content for the template slot',
                                        )
                                    }}
                                </div>
                                <p class="mt-1 text-xs text-slate-600">
                                    {{
                                        t(
                                            'categories.form.content_slot_help',
                                            'Deze inhoud wordt via content_slot in de gekozen category template ingevoegd.',
                                        )
                                    }}
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <Label for="excerpt">
                                    {{
                                        t(
                                            'content_form.excerpt',
                                            'Short description',
                                        )
                                    }}
                                </Label>
                                <textarea
                                    id="excerpt"
                                    v-model="form.excerpt"
                                    rows="3"
                                    class="min-h-20 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                    @blur="touchAndClear('excerpt')"
                                />
                                <FieldValidationMessage
                                    :message="validationMessage('excerpt')"
                                    :value="form.excerpt"
                                />
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
                                    upload-context-type="category"
                                    :upload-context-id="categoryRecordId"
                                    :label="
                                        t(
                                            'content_form.page_blocks',
                                            'Page blocks',
                                        )
                                    "
                                />
                                <FieldValidationMessage
                                    :message="
                                        validationMessage('content_blocks')
                                    "
                                />
                            </div>
                        </section>

                        <section v-if="activeTab === 'seo'" class="space-y-5">
                            <div class="grid gap-2">
                                <Label for="seo_title">
                                    {{
                                        t('content_form.seo_title', 'SEO title')
                                    }}
                                </Label>
                                <Input
                                    id="seo_title"
                                    v-model="form.seo_title"
                                    @blur="touchAndClear('seo_title')"
                                />
                                <FieldValidationMessage
                                    :message="validationMessage('seo_title')"
                                    :warning="validationWarning('seo_title')"
                                    :value="form.seo_title"
                                    :max="counterMax('seo_title')"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="seo_description">
                                    {{
                                        t(
                                            'content_form.seo_description_label',
                                            'SEO description',
                                        )
                                    }}
                                </Label>
                                <textarea
                                    id="seo_description"
                                    v-model="form.seo_description"
                                    rows="3"
                                    class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                    @blur="touchAndClear('seo_description')"
                                />
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
                                <Label for="canonical_url">
                                    {{
                                        t(
                                            'content_form.canonical_url',
                                            'Canonical URL',
                                        )
                                    }}
                                </Label>
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
                        </section>

                        <CmsContentStatisticsPanel
                            v-if="activeTab === 'statistics' && isEditMode"
                            content-type="category"
                            :record-id="categoryRecordId"
                        />

                        <section
                            v-if="activeTab === 'translations'"
                            class="space-y-5"
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
                                                'categories.form.translations_description',
                                                'Create and open linked language versions of this category.',
                                            )
                                        }}
                                    </p>
                                </div>
                                <Button
                                    v-if="isEditMode"
                                    type="button"
                                    variant="outline"
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

                            <div class="grid gap-2">
                                <div
                                    v-for="item in translationStatusItems"
                                    :key="item.key"
                                    class="flex items-center justify-between gap-3 rounded-lg border px-3 py-2 text-sm"
                                    :class="translationStatusPanelClass(item)"
                                >
                                    <div class="min-w-0">
                                        <div class="font-semibold">
                                            {{ item.label }}
                                        </div>
                                        <div class="text-xs opacity-80">
                                            {{ translationChipTitle(item) }}
                                        </div>
                                    </div>
                                    <Button
                                        v-if="item.type !== 'missing'"
                                        as-child
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                    >
                                        <Link
                                            :href="
                                                route(
                                                    'admin.cms.categories.edit',
                                                    { id: item.id },
                                                )
                                            "
                                        >
                                            {{ t('content_form.open', 'Open') }}
                                        </Link>
                                    </Button>
                                    <Button
                                        v-else-if="isEditMode"
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        :disabled="translationForm.processing"
                                        @click="
                                            openTranslationDialog(item.locale)
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
                        </section>
                    </div>
                </CardContent>
            </Card>
        </form>

        <Dialog v-model:open="showTranslationDialog">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {{
                            t(
                                'categories.form.translation_dialog_title',
                                'Create category translation',
                            )
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            t(
                                'categories.form.translation_dialog_description',
                                'Choose whether the draft category is translated with AI or first created as a copy.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-3 py-2">
                    <div class="grid gap-2">
                        <Label>
                            {{
                                t(
                                    'content_form.chosen_language',
                                    'Selected language',
                                )
                            }}
                        </Label>
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
                                    'Available choices',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                t(
                                    'categories.form.ai_help',
                                    'Translate with AI immediately creates a translated draft category with linked draft page.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                t(
                                    'content_form.copy_help',
                                    'Copy original creates a draft with the same texts, so you can manually adjust it later.',
                                )
                            }}
                        </p>
                    </div>
                </div>

                <DialogFooter class="justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="gap-2 shadow-none"
                        :disabled="
                            translationForm.processing ||
                            !translationForm.target_locale
                        "
                        @click="createTranslation(false)"
                    >
                        <span
                            v-if="
                                translationForm.processing &&
                                translationAction === 'copy'
                            "
                            class="mdi mdi-loading animate-spin text-base"
                            aria-hidden="true"
                        />
                        {{ t('content_form.copy_original', 'Copy original') }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        class="gap-2 border-purple-200 text-purple-700 shadow-none hover:bg-purple-50 hover:text-purple-800"
                        :disabled="
                            translationForm.processing ||
                            !translationForm.target_locale
                        "
                        @click="createTranslation(true)"
                    >
                        <span
                            v-if="
                                translationForm.processing &&
                                translationAction === 'ai'
                            "
                            class="mdi mdi-loading animate-spin text-base"
                            aria-hidden="true"
                        />
                        <span
                            v-else
                            class="mdi mdi-robot-outline text-base"
                            aria-hidden="true"
                        />
                        {{
                            t('content_form.translate_ai', 'Translate with AI')
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <CmsRevisionHistoryDialog
            v-if="isEditMode"
            v-model:open="showRevisionDialog"
            subject-type="category"
            restore-route-name="admin.cms.categories.revisions.restore"
            :restore-route-params="{ category: category.id }"
            :revisions="revisions"
        />
    </AdminLayout>
</template>

<script setup>
import RwAutoCompleteInput from '@/Components/RwAutoCompleteInput.vue';
import RwFlashMessage from '@/Components/RwFlashMessage.vue';
import AdminFormBackButton from '@/Components/Admin/Form/AdminFormBackButton.vue';
import AdminFormSaveButton from '@/Components/Admin/Form/AdminFormSaveButton.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CmsBlockEditor from '@/Pages/Admin/Cms/Components/CmsBlockEditor.vue';
import CmsContentStatisticsPanel from '@/Pages/Admin/Cms/Components/CmsContentStatisticsPanel.vue';
import CmsRevisionHistoryDialog from '@/Pages/Admin/Cms/Components/CmsRevisionHistoryDialog.vue';
import AiTranslationReviewBanner from '@/Pages/Admin/Cms/Partials/AiTranslationReviewBanner.vue';
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
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { resolveReturnToUrl } from '@/composables/useReturnToUrl';
import {
    createCmsSeoFields,
    useCmsFormValidation,
} from '@/composables/useCmsFormValidation';
import baseRules from '@/ValidationRules/Rules';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';

const { t } = useAdminTranslations('cms_admin_ui');
const { t: commonT } = useAdminTranslations('admin_common_ui');
const page = usePage();

const props = defineProps({
    category: { type: Object, default: null },
    translations: { type: Array, default: () => [] },
    revisions: { type: Array, default: () => [] },
    missingLanguages: { type: Array, default: () => [] },
    activeLanguages: { type: Array, default: () => [] },
    availableLocales: { type: Array, default: () => [] },
    parentOptions: { type: Array, required: true },
    typeOptions: { type: Array, required: true },
    statusOptions: { type: Array, required: true },
    mediaOptions: { type: Array, required: true },
    mediaFolders: { type: Array, default: () => [] },
    downloadOptions: { type: Array, default: () => [] },
    downloadFolders: { type: Array, default: () => [] },
    formOptions: { type: Array, required: true },
    placeableBlocks: { type: Array, default: () => [] },
    categoryOptions: { type: Array, default: () => [] },
    tagOptions: { type: Array, default: () => [] },
    contactSettings: { type: Object, default: () => ({}) },
    archiveTemplateOptions: { type: Array, default: () => [] },
    detailTemplateOptions: { type: Array, default: () => [] },
    seoSettings: { type: Object, required: true },
});

const defaultBlocks = () => [
    defaultBlockForRenderer('list_grid', {
        category_source: 'current',
        show_only_subcategories: true,
        limit: 24,
        sort_field: 'published_at',
        sort_direction: 'desc',
        show_excerpt: true,
        show_image: true,
        show_date: true,
        show_categories: true,
    }),
];

function defaultBlockForRenderer(rendererKey, data = {}) {
    const block = props.placeableBlocks.find(
        (placeableBlock) => placeableBlock.renderer_key === rendererKey,
    );

    return {
        cms_placeable_block_id: block?.id || 0,
        placeable_block_revision_id: block?.revision_id || null,
        ...data,
    };
}

const localMediaOptions = ref([...props.mediaOptions]);
const localMediaFolders = ref([...props.mediaFolders]);

const form = useForm({
    parent_id: props.category?.parent_id ?? '',
    type: props.category?.type ?? 'post',
    title: props.category?.title ?? '',
    slug: props.category?.slug ?? '',
    locale: props.category?.locale ?? props.activeLanguages[0]?.locale ?? 'nl',
    description: props.category?.description ?? '',
    sort_order: props.category?.sort_order ?? 0,
    archive_template_id: props.category?.archive_template_id ?? '',
    detail_template_id: props.category?.detail_template_id ?? '',
    is_active: Boolean(props.category?.is_active ?? true),
    status: props.category?.status ?? 'draft',
    template: props.category?.template ?? '',
    excerpt: props.category?.excerpt ?? props.category?.description ?? '',
    content_blocks: props.category?.content_blocks ?? defaultBlocks(),
    seo_title: props.category?.seo_title ?? '',
    seo_description: props.category?.seo_description ?? '',
    canonical_url: props.category?.canonical_url ?? '',
    og_image_path: props.category?.og_image_path ?? '',
    noindex: Boolean(props.category?.noindex ?? false),
    is_searchable: Boolean(props.category?.is_searchable ?? true),
    pdf_download_enabled: Boolean(
        props.category?.pdf_download_enabled ?? false,
    ),
    published_at: props.category?.published_at ?? '',
    structured_data_schema_type:
        props.category?.structured_data_schema_type ?? 'auto',
    structured_data_extra: props.category?.structured_data_extra ?? '',
});

const isEditMode = computed(() => Boolean(props.category?.id));
const categoryRecordId = computed(() => props.category?.id ?? null);
const recordIdLabel = computed(() => props.category?.id ?? '-');
const currentRevision = computed(() => props.revisions[0] ?? null);
const currentRevisionLabel = computed(() => {
    const revisionNumber = Number(currentRevision.value?.revision_number ?? 0);

    return revisionNumber > 0
        ? t('revisions.current_version_number', 'Version #:number', {
              number: revisionNumber,
          })
        : t('revisions.current_version_empty', 'Version -');
});
const currentRevisionTitle = computed(
    () =>
        currentRevision.value?.title ||
        t('revisions.current_version_tooltip', 'Latest saved version'),
);
const updatedAtLabel = computed(() =>
    formatRecordDate(props.category?.updated_at),
);
const createdAtLabel = computed(() =>
    formatRecordDate(props.category?.created_at),
);
const pageTitle = computed(() =>
    isEditMode.value
        ? t('categories.form.edit_title', 'Edit category')
        : t('categories.form.create_title', 'Add category'),
);
const backHref = computed(() =>
    resolveReturnToUrl(route('admin.cms.taxonomy.index')),
);
const activeTab = ref('basis');
const showTranslationDialog = ref(false);
const showRevisionDialog = ref(false);
const tabOptions = computed(() => [
    { value: 'basis', label: t('content_form.tabs.basic', 'Basic') },
    {
        value: 'weergave',
        label: t('categories.form.tabs.display', 'Weergave'),
    },
    { value: 'content', label: t('content_form.tabs.content', 'Content') },
    { value: 'seo', label: t('content_form.tabs.seo', 'SEO') },
    ...(isEditMode.value
        ? [
              {
                  value: 'statistics',
                  label: t('content_form.tabs.statistics', 'Statistics'),
              },
          ]
        : []),
]);
const selectableLanguages = computed(() =>
    props.activeLanguages.length > 0
        ? props.activeLanguages
        : props.availableLocales.map((locale) => ({
              locale,
              name: locale,
              native_name: locale,
          })),
);
const selectedLanguageLabel = computed(() => {
    const language = selectableLanguages.value.find(
        (item) => item.locale === form.locale,
    );

    return language ? languageLabel(language) : form.locale || '-';
});
const filteredParentOptions = computed(() =>
    props.parentOptions.filter(
        (option) => option.locale === form.locale && option.type === form.type,
    ),
);
const parentSelectOptions = computed(() => [
    { id: '', label: t('content_form.none', 'None'), title: '', locale: '' },
    ...filteredParentOptions.value.map((option) => ({
        ...option,
        label: `${option.title} (${option.type} / ${option.locale})`,
    })),
]);
const filteredArchiveTemplateOptions = computed(() => [
    {
        id: '',
        label: t('templates.form.default_template', 'Default template'),
        name: '',
        locale: form.locale,
    },
    ...props.archiveTemplateOptions.filter(
        (option) => option.locale === form.locale,
    ),
]);
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
const defaultArchiveTemplateOption = computed(() =>
    filteredArchiveTemplateOptions.value.find((option) => option.is_default),
);
const defaultDetailTemplateOption = computed(() =>
    filteredDetailTemplateOptions.value.find((option) => option.is_default),
);
const selectedArchiveTemplateOption = computed(
    () =>
        filteredArchiveTemplateOptions.value.find(
            (option) => String(option.id) === String(form.archive_template_id),
        ) || null,
);
const selectedDetailTemplateOption = computed(
    () =>
        filteredDetailTemplateOptions.value.find(
            (option) => String(option.id) === String(form.detail_template_id),
        ) || null,
);
const baseValidationFields = {
    title: requiredTextField('content_form.title', 'Title', 'title'),
    slug: {
        ...requiredTextField('content_form.slug', 'Slug', 'slug'),
        rules: [
            (value) => baseRules.required(value, requiredMessage()),
            (value) => maxChars('content_form.slug', 'Slug', 255, value),
            (value) => slugRule(value),
        ],
    },
    locale: requiredTextField(
        'common.columns.locale',
        'Language',
        'locale',
        12,
    ),
    status: requiredTextField(
        'categories.form.page_status',
        'Page status',
        'status',
    ),
    description: optionalTextField(
        'categories.form.description_label',
        'Category description',
        'description',
        5000,
    ),
    excerpt: optionalTextField(
        'content_form.excerpt',
        'Short description',
        'excerpt',
        5000,
    ),
    parent_id: {
        label: t('content_form.parent_category', 'Parent category'),
        tab: 'basis',
        elementId: 'parent_id',
        value: () => form.parent_id,
        rules: [],
    },
    archive_template_id: {
        label: t(
            'templates.fields.category_archive_template',
            'Archive template',
        ),
        tab: 'weergave',
        elementId: 'archive_template_id',
        value: () => form.archive_template_id,
        rules: [],
    },
    detail_template_id: {
        label: t(
            'templates.fields.category_detail_template',
            'Detail template',
        ),
        tab: 'weergave',
        elementId: 'detail_template_id',
        value: () => form.detail_template_id,
        rules: [],
    },
    type: requiredTextField('common.columns.type', 'Type', 'type'),
    content_blocks: {
        label: t('content_form.page_blocks', 'Page blocks'),
        tab: 'content',
        elementId: 'content_blocks',
        value: () => form.content_blocks,
        rules: [],
    },
};
const seoValidationFields = createCmsSeoFields(form, {
    t,
    seoSettings: props.seoSettings,
    structuredDataField: false,
});
delete seoValidationFields.title;
delete seoValidationFields.slug;

const validationFields = {
    ...baseValidationFields,
    ...seoValidationFields,
};
const {
    FieldValidationMessage,
    FormValidationSummary,
    allValidationErrors,
    formValidation,
    message: validationMessage,
    warning: validationWarning,
    counterMax,
    validationFlash,
    touchAndClear,
} = useCmsFormValidation(form, {
    fields: validationFields,
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
        activeTab.value = tab;
    },
});
const { showSummary, validateBeforeSubmit, scrollToIssue } = formValidation;
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
const translationsByLocale = computed(() => {
    const map = new Map();

    props.translations.forEach((translation) => {
        if (translation?.locale) {
            map.set(translation.locale, translation);
        }
    });

    return map;
});
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
                status: translation.is_active ? 'success' : 'warning',
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
const translationForm = useForm({
    target_locale: '',
    use_ai: true,
});
const translationAction = ref(null);
const selectedTranslationLanguageLabel = computed(() => {
    const language = missingLanguages.value.find(
        (item) => item.locale === translationForm.target_locale,
    );

    return language
        ? languageLabel(language)
        : t('content_form.no_language_selected', 'No language selected');
});

watch(
    () => form.locale,
    () => {
        if (
            !filteredParentOptions.value.some(
                (option) => option.id === form.parent_id,
            )
        ) {
            form.parent_id = '';
        }
    },
);

function requiredMessage() {
    return t('validation.required', 'This field is required.');
}

function requiredTextField(labelKey, fallback, elementId, max = 255) {
    return {
        label: t(labelKey, fallback),
        tab: 'basis',
        elementId,
        required: true,
        value: () => form[elementId],
        rules: [
            (value) => baseRules.required(value, requiredMessage()),
            (value) => maxChars(labelKey, fallback, max, value),
        ],
    };
}

function optionalTextField(labelKey, fallback, elementId, max = 255) {
    return {
        label: t(labelKey, fallback),
        tab: elementId === 'excerpt' ? 'content' : 'basis',
        elementId,
        value: () => form[elementId],
        rules: [(value) => maxChars(labelKey, fallback, max, value)],
    };
}

function maxChars(labelKey, fallback, max, value) {
    return baseRules.max(
        max,
        value,
        t('validation.max_chars', ':field is too long (:current/:max).', {
            field: t(labelKey, fallback),
            current: String(value ?? '').length,
            max,
        }),
    );
}

function slugRule(value) {
    const text = String(value ?? '').trim();

    if (text === '') {
        return true;
    }

    return /^[A-Za-z0-9_-]+$/.test(text)
        ? true
        : t(
              'validation.alpha_dash_ascii',
              'Use only letters, numbers, dashes and underscores.',
          );
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

async function submit() {
    if (!(await validateBeforeSubmit())) {
        return;
    }

    if (!Array.isArray(form.content_blocks)) {
        form.setError(
            'content_blocks',
            t(
                'content_form.content_blocks_error',
                'Content blocks must be a list.',
            ),
        );

        return;
    }

    form.clearErrors('content_blocks');
    form.parent_id = form.parent_id || null;
    form.archive_template_id = form.archive_template_id || null;
    form.detail_template_id = form.detail_template_id || null;
    form.excerpt = form.excerpt || form.description || null;
    form.canonical_url = form.canonical_url || null;
    form.structured_data_extra = form.structured_data_extra || null;
    form.post(
        route('admin.cms.categories.store', { id: props.category?.id ?? 0 }),
        {
            preserveScroll: true,
            onError: () => {
                nextTick(() => {
                    scrollToIssue(allValidationErrors.value[0]);
                });
            },
        },
    );
}

function openTranslationDialog(locale = '') {
    translationForm.clearErrors();
    translationForm.target_locale =
        locale || missingLanguages.value[0]?.locale || '';
    translationForm.use_ai = true;
    showTranslationDialog.value = true;
}

function createTranslation(useAi) {
    if (!props.category?.id) {
        return;
    }

    translationAction.value = useAi ? 'ai' : 'copy';
    translationForm.use_ai = useAi;
    translationForm.post(
        route('admin.cms.categories.translations.store', {
            id: props.category.id,
        }),
        {
            onFinish: () => {
                translationAction.value = null;
            },
        },
    );
}

function handleTranslationChipClick(item) {
    if (item.type === 'missing') {
        openTranslationDialog(item.locale);

        return;
    }

    if (!item.isCurrent && item.id) {
        window.location.href = route('admin.cms.categories.edit', {
            id: item.id,
        });
    }
}

function translationChipTitle(item) {
    if (item.type === 'missing') {
        return t(
            'categories.form.missing_translation_help',
            'No linked category for this active language yet.',
        );
    }

    if (item.isCurrent) {
        return t('content_form.current', 'Current');
    }

    return item.status === 'warning'
        ? t('content_form.page_status_prefix', 'page')
        : t('content_form.open', 'Open');
}

function translationStatusClass(item) {
    if (item.status === 'success') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    }

    if (item.status === 'warning') {
        return 'border-orange-200 bg-orange-50 text-orange-700';
    }

    return 'border-red-200 bg-red-50 text-red-700';
}

function translationStatusPanelClass(item) {
    if (item.status === 'success') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-900';
    }

    if (item.status === 'warning') {
        return 'border-orange-200 bg-orange-50 text-orange-900';
    }

    return 'border-red-200 bg-red-50 text-red-900';
}

function translationStatusDotClass(status) {
    if (status === 'success') {
        return 'bg-emerald-500';
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
