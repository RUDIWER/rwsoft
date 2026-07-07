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
                                    class="mdi mdi-view-quilt-outline text-2xl"
                                />
                            </div>
                            <div class="min-w-0">
                                <CardTitle class="text-lg">
                                    {{ pageTitle }}
                                </CardTitle>
                                <CardDescription class="mt-1">
                                    {{
                                        t(
                                            'templates.form_description',
                                            'Build reusable templates with sections, blocks and safe data fields.',
                                        )
                                    }}
                                </CardDescription>
                            </div>
                        </div>

                        <div class="flex flex-wrap justify-end gap-2">
                            <AdminFormBackButton
                                :href="route('admin.cms.templates.index')"
                                :dirty="form.isDirty"
                                :processing="form.processing"
                                :label="commonT('actions.back', 'Back')"
                                @save="submit"
                            />

                            <Button
                                v-if="isEditMode"
                                type="button"
                                variant="outline"
                                class="gap-2 border-slate-200 text-slate-700 shadow-none hover:bg-slate-50 hover:text-slate-900"
                                @click="showRevisionDialog = true"
                            >
                                <span
                                    class="mdi mdi-history text-base text-slate-700"
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
                    v-if="isEditMode"
                    class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700 sm:px-5"
                >
                    <div class="font-medium text-slate-700">
                        {{ commonT('record_meta.id', 'ID') }}:
                        <span class="ml-1 text-base font-bold text-slate-950">
                            {{ templateItem.id }}
                        </span>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-5 gap-y-1">
                        <div class="font-medium text-slate-700">
                            {{ commonT('record_meta.updated_at', 'Updated') }}:
                            <span
                                class="ml-1 text-base font-bold text-slate-950"
                            >
                                {{ formatDate(templateItem.updated_at, false) }}
                            </span>
                        </div>
                        <div class="font-medium text-slate-700">
                            {{ commonT('record_meta.created_at', 'Created') }}:
                            <span
                                class="ml-1 text-base font-bold text-slate-950"
                            >
                                {{ formatDate(templateItem.created_at, false) }}
                            </span>
                        </div>
                    </div>
                </div>

                <div
                    v-if="cardFlash.message"
                    class="border-b border-slate-200 px-4 py-3 sm:px-5"
                >
                    <RwFlashMessage
                        :type="cardFlash.type"
                        :message="cardFlash.message"
                        :details="cardFlash.details"
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
                            :visible="
                                showSummary && validationErrorList.length > 0
                            "
                            :errors="validationErrorList"
                            :title="
                                t('validation.summary_title', 'Save is blocked')
                            "
                            :description="
                                t(
                                    'validation.summary_description',
                                    'Resolve the fields below and try again.',
                                )
                            "
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
                                            'templates.translations.save_before',
                                            'Save the template first to manage translations.',
                                        )
                                    }}
                                </p>
                            </div>

                            <div
                                class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,24rem)]"
                            >
                                <div class="grid gap-4">
                                    <div class="grid gap-2">
                                        <Label
                                            for="name"
                                            class="flex items-center gap-1"
                                        >
                                            <span
                                                class="text-red-600"
                                                aria-hidden="true"
                                                >*</span
                                            >
                                            {{ t('templates.name', 'Name') }}
                                        </Label>
                                        <Input
                                            id="name"
                                            v-model="form.name"
                                            required
                                            class="bg-yellow-50"
                                            @blur="touch('name')"
                                        />
                                        <FieldValidationMessage
                                            :message="validationMessage('name')"
                                            :value="form.name"
                                        />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label class="flex items-center gap-1">
                                            <span
                                                class="text-red-600"
                                                aria-hidden="true"
                                                >*</span
                                            >
                                            {{
                                                t(
                                                    'templates.template_class',
                                                    'Template group',
                                                )
                                            }}
                                        </Label>
                                        <Input
                                            v-if="isEditMode"
                                            :model-value="
                                                currentTemplateClassLabel
                                            "
                                            readonly
                                            class="bg-slate-50 text-slate-700"
                                        />
                                        <RwAutoCompleteInput
                                            v-else
                                            v-model="form.template_class"
                                            :items="classOptions"
                                            item-title="label"
                                            item-value="value"
                                            :required="true"
                                            required-highlight-color="#fefce8"
                                            :placeholder="
                                                t(
                                                    'templates.choose_class',
                                                    'Choose template group',
                                                )
                                            "
                                            @blur="touch('template_class')"
                                        />
                                        <FieldValidationMessage
                                            :message="
                                                validationMessage(
                                                    'template_class',
                                                )
                                            "
                                            :value="form.template_class"
                                        />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label class="flex items-center gap-1">
                                            <span
                                                class="text-red-600"
                                                aria-hidden="true"
                                                >*</span
                                            >
                                            {{
                                                t(
                                                    'templates.template_type',
                                                    'Template type',
                                                )
                                            }}
                                        </Label>
                                        <Input
                                            v-if="isEditMode"
                                            :model-value="
                                                currentTemplateTypeLabel
                                            "
                                            readonly
                                            class="bg-slate-50 text-slate-700"
                                        />
                                        <RwAutoCompleteInput
                                            v-else
                                            v-model="form.template_key"
                                            :items="templateTypeOptions"
                                            item-title="label"
                                            item-value="value"
                                            :required="true"
                                            required-highlight-color="#fefce8"
                                            :placeholder="
                                                t(
                                                    'templates.choose_template_type',
                                                    'Choose template type',
                                                )
                                            "
                                            @blur="touch('template_key')"
                                        />
                                        <FieldValidationMessage
                                            :message="
                                                validationMessage(
                                                    'template_key',
                                                )
                                            "
                                            :value="form.template_key"
                                        />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label class="flex items-center gap-1">
                                            <span
                                                class="text-red-600"
                                                aria-hidden="true"
                                                >*</span
                                            >
                                            {{
                                                t('templates.layout', 'Layout')
                                            }}
                                        </Label>
                                        <RwAutoCompleteInput
                                            v-model="form.layout_id"
                                            :items="layoutOptionsForLocale"
                                            item-title="label"
                                            item-value="id"
                                            :required="true"
                                            required-highlight-color="#fefce8"
                                            :placeholder="
                                                t(
                                                    'templates.choose_layout',
                                                    'Choose layout',
                                                )
                                            "
                                            @blur="touch('layout_id')"
                                        />
                                        <FieldValidationMessage
                                            :message="
                                                validationMessage('layout_id')
                                            "
                                            :value="form.layout_id"
                                        />
                                        <p class="text-xs text-slate-500">
                                            {{
                                                t(
                                                    'templates.layout_help',
                                                    'Only active layouts in the same language can be linked to this template.',
                                                )
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <div class="grid content-start gap-2">
                                    <Label class="flex items-center gap-1">
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
                                    <Input
                                        v-if="isEditMode"
                                        :model-value="currentLanguageLabel"
                                        readonly
                                        class="bg-slate-50 text-slate-700"
                                    />
                                    <RwAutoCompleteInput
                                        v-else
                                        v-model="form.locale"
                                        :items="languageOptions"
                                        item-title="label"
                                        item-value="value"
                                        :required="true"
                                        required-highlight-color="#fefce8"
                                        :placeholder="
                                            t(
                                                'templates.choose_locale',
                                                'Choose language',
                                            )
                                        "
                                        @blur="touch('locale')"
                                    />
                                    <FieldValidationMessage
                                        :message="validationMessage('locale')"
                                        :value="form.locale"
                                    />
                                </div>
                            </div>
                        </section>

                        <section
                            v-if="activeTab === 'content'"
                            class="grid gap-2"
                        >
                            <CmsLayoutZoneEditor
                                :key="`${templateEditorKey}:content`"
                                :model-value="form.sections.content"
                                zone="content"
                                :responsive-grid="true"
                                :title="
                                    t(
                                        'templates.builder.title',
                                        'Template content',
                                    )
                                "
                                :description="
                                    t(
                                        'templates.builder.description',
                                        'Build this template with reusable sections and safe blocks.',
                                    )
                                "
                                :placeable-blocks="activePlaceableBlocks"
                                :color-palette-items="localColorPaletteItems"
                                @update:color-palette-items="
                                    updateColorPaletteItems
                                "
                                :layout-locale="form.locale"
                                :form-options="formOptions"
                                :menu-options="menuOptions"
                                :contact-settings="contactSettings"
                                :style-token-options="styleTokenOptions"
                                v-model:media-options="localMediaOptions"
                                v-model:media-folders="localMediaFolders"
                                :download-options="downloadOptions"
                                :download-folders="downloadFolders"
                                :can-manage-code-blocks="canManageCodeBlocks"
                                :dialog-flash="sectionDialogFlash('content')"
                                :placement-dialog-flash="
                                    placementDialogFlash('content')
                                "
                                :saving="
                                    sectionDialogSaveZone === 'content' &&
                                    form.processing
                                "
                                :placement-saving="
                                    placementDialogSaveZone === 'content' &&
                                    form.processing
                                "
                                @save-requested="
                                    submit('content', {
                                        source: 'section-dialog',
                                    })
                                "
                                @placement-save-requested="
                                    submit('content', {
                                        source: 'placement-dialog',
                                    })
                                "
                                @section-dialog-open-changed="
                                    handleZoneSectionDialogOpenChange
                                "
                                @placement-dialog-open-changed="
                                    handleZonePlacementDialogOpenChange
                                "
                                @update:model-value="
                                    updateTemplateContentSections
                                "
                            />
                            <p
                                v-if="form.errors.sections"
                                class="text-sm text-red-600"
                            >
                                {{ form.errors.sections }}
                            </p>
                        </section>

                        <section
                            v-if="activeTab === 'fields'"
                            class="grid gap-4"
                        >
                            <div class="grid gap-1">
                                <h2
                                    class="text-base font-semibold text-slate-900"
                                >
                                    {{
                                        t(
                                            'templates.fields.title',
                                            'Available data fields',
                                        )
                                    }}
                                </h2>
                                <p class="text-sm text-slate-500">
                                    {{
                                        t(
                                            'templates.fields.description',
                                            'Choose which whitelisted system fields may be used by dynamic field blocks in this template.',
                                        )
                                    }}
                                </p>
                            </div>
                            <section class="grid gap-3">
                                <div class="grid gap-1">
                                    <h3
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        {{
                                            t(
                                                'templates.fields.system_data_title',
                                                'System data',
                                            )
                                        }}
                                    </h3>
                                    <p class="text-sm text-slate-500">
                                        {{
                                            t(
                                                'templates.fields.system_data_description',
                                                'These fields come from the current page, navigation or publication context. Content-specific text belongs in block fields.',
                                            )
                                        }}
                                    </p>
                                </div>
                                <div
                                    class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3"
                                >
                                    <label
                                        v-for="field in currentSystemFields"
                                        :key="field.key"
                                        class="flex items-start gap-3 rounded-lg border border-slate-200 bg-white p-3 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            class="mt-1 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                            :checked="
                                                systemFieldEnabled(field.key)
                                            "
                                            @change="
                                                setSystemFieldEnabled(
                                                    field.key,
                                                    $event.target.checked,
                                                )
                                            "
                                        />
                                        <span class="grid gap-1">
                                            <span
                                                class="font-semibold text-slate-900"
                                            >
                                                {{ systemFieldLabel(field) }}
                                            </span>
                                            <span
                                                class="font-mono text-xs text-slate-500"
                                            >
                                                {{ field.key }}
                                            </span>
                                            <span
                                                class="text-xs text-slate-500"
                                            >
                                                {{
                                                    t(
                                                        field.group_key,
                                                        field.type,
                                                    )
                                                }}
                                                · {{ field.type }}
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            </section>
                        </section>

                        <section
                            v-if="activeTab === 'preview'"
                            class="grid gap-4"
                        >
                            <div class="grid gap-1">
                                <div
                                    class="flex flex-wrap items-start justify-between gap-3"
                                >
                                    <div class="grid gap-1">
                                        <h2
                                            class="text-base font-semibold text-slate-900"
                                        >
                                            {{
                                                t(
                                                    'templates.preview.title',
                                                    'Preview',
                                                )
                                            }}
                                        </h2>
                                        <p class="text-sm text-slate-500">
                                            {{
                                                t(
                                                    'templates.preview.description',
                                                    'Preview the saved template with representative public content.',
                                                )
                                            }}
                                        </p>
                                    </div>

                                    <Button
                                        v-if="previewUrl"
                                        as-child
                                        type="button"
                                        variant="outline"
                                        class="gap-2 shadow-none"
                                    >
                                        <a
                                            :href="previewUrl"
                                            target="_blank"
                                            rel="noopener"
                                        >
                                            <span
                                                class="mdi mdi-open-in-new text-base"
                                                aria-hidden="true"
                                            />
                                            {{
                                                t(
                                                    'templates.preview.open',
                                                    'Open preview',
                                                )
                                            }}
                                        </a>
                                    </Button>
                                </div>
                            </div>

                            <div
                                v-if="previewOptions.length > 0"
                                class="grid gap-2 md:max-w-md"
                            >
                                <Label>{{
                                    t(
                                        'templates.preview.sample_label',
                                        'Preview record',
                                    )
                                }}</Label>
                                <RwAutoCompleteInput
                                    v-model="selectedPreviewSampleId"
                                    :items="previewOptions"
                                    item-title="label"
                                    item-value="id"
                                    :placeholder="
                                        t(
                                            'templates.preview.sample_placeholder',
                                            'Choose preview record',
                                        )
                                    "
                                />
                                <p class="text-xs text-slate-500">
                                    {{
                                        t(
                                            'templates.preview.sample_help',
                                            'Only records matching this template type and language are available.',
                                        )
                                    }}
                                </p>
                            </div>

                            <div
                                v-if="!previewUrl"
                                class="rounded-md border border-dashed border-slate-300 p-5 text-sm text-slate-500"
                            >
                                {{
                                    t(
                                        'templates.preview.unsaved_placeholder',
                                        'Save this template before opening the preview.',
                                    )
                                }}
                            </div>

                            <div
                                v-else
                                class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm"
                            >
                                <iframe
                                    :key="previewUrl"
                                    :src="previewUrl"
                                    :title="
                                        t(
                                            'templates.preview.iframe_title',
                                            'Template preview',
                                        )
                                    "
                                    class="h-[70vh] w-full bg-white"
                                    loading="lazy"
                                />
                            </div>
                        </section>

                        <section
                            v-if="activeTab === 'instellingen'"
                            class="grid gap-5"
                        >
                            <div class="grid gap-1">
                                <h2
                                    class="text-base font-semibold text-slate-900"
                                >
                                    {{
                                        t(
                                            'templates.settings.title',
                                            'Settings',
                                        )
                                    }}
                                </h2>
                                <p class="text-sm text-slate-500">
                                    {{
                                        t(
                                            'templates.settings.description',
                                            'Manage activation, default behavior and cache strategy for this template.',
                                        )
                                    }}
                                </p>
                            </div>

                            <div
                                class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_20rem]"
                            >
                                <div class="grid gap-4">
                                    <div class="grid gap-2 md:max-w-sm">
                                        <Label>{{
                                            t(
                                                'layouts.cache_strategy',
                                                'Cache strategy',
                                            )
                                        }}</Label>
                                        <RwAutoCompleteInput
                                            v-model="form.cache_strategy"
                                            :items="cacheStrategyOptions"
                                            item-title="label"
                                            item-value="value"
                                        />
                                    </div>

                                    <div class="grid gap-2 md:max-w-sm">
                                        <Label for="html_anchor">
                                            {{
                                                t(
                                                    'templates.html_anchor',
                                                    'HTML anchor',
                                                )
                                            }}
                                        </Label>
                                        <Input
                                            id="html_anchor"
                                            v-model="form.settings.html_anchor"
                                            :disabled="isEditMode"
                                            :placeholder="
                                                t(
                                                    'templates.html_anchor_placeholder',
                                                    'template-main',
                                                )
                                            "
                                            @blur="
                                                touch('settings.html_anchor')
                                            "
                                        />
                                        <FieldValidationMessage
                                            :message="
                                                validationMessage(
                                                    'settings.html_anchor',
                                                ) || form.errors.html_anchor
                                            "
                                            :value="form.settings.html_anchor"
                                        />
                                        <p class="text-xs text-slate-500">
                                            {{
                                                t(
                                                    'templates.html_anchor_help',
                                                    'Stable public wrapper ID for custom CSS and anchors. Existing anchors cannot be changed.',
                                                )
                                            }}
                                        </p>
                                    </div>

                                    <div
                                        class="grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-3 md:max-w-sm"
                                    >
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
                                                    'common.columns.active',
                                                    'Actief',
                                                )
                                            }}
                                        </label>
                                        <label
                                            class="flex items-center gap-2 text-sm"
                                        >
                                            <input
                                                v-model="form.is_default"
                                                type="checkbox"
                                                class="h-4 w-4 rounded border-slate-300"
                                            />
                                            {{
                                                t(
                                                    'templates.default_template',
                                                    'Default template',
                                                )
                                            }}
                                        </label>
                                    </div>
                                </div>

                                <div
                                    class="grid content-start gap-2 rounded-lg border border-slate-200 bg-white p-4 text-sm"
                                >
                                    <div class="font-semibold text-slate-900">
                                        {{
                                            t(
                                                'templates.settings.summary_title',
                                                'Template overview',
                                            )
                                        }}
                                    </div>
                                    <div class="text-slate-600">
                                        {{ t('templates.layout', 'Layout') }}:
                                        <span
                                            class="font-medium text-slate-900"
                                        >
                                            {{
                                                templateItem?.layout_name || '-'
                                            }}
                                        </span>
                                    </div>
                                    <div class="text-slate-600">
                                        {{
                                            t(
                                                'templates.columns.sections_count',
                                                'Secties',
                                            )
                                        }}:
                                        <span
                                            class="font-medium text-slate-900"
                                        >
                                            {{
                                                templateItem?.sections_count ??
                                                0
                                            }}
                                        </span>
                                    </div>
                                    <div class="text-slate-600">
                                        {{
                                            t(
                                                'templates.columns.usage_count',
                                                'Gebruik',
                                            )
                                        }}:
                                        <span
                                            class="font-medium text-slate-900"
                                        >
                                            {{ templateImpactCount }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section
                            v-if="activeTab === 'vertalingen'"
                            class="grid gap-5"
                        >
                            <div class="grid gap-1">
                                <h2
                                    class="text-base font-semibold text-slate-900"
                                >
                                    {{
                                        t(
                                            'templates.translations.title',
                                            'Translations',
                                        )
                                    }}
                                </h2>
                                <p class="text-sm text-slate-500">
                                    {{
                                        t(
                                            'templates.translations.description',
                                            'Manage linked language versions of this template.',
                                        )
                                    }}
                                </p>
                            </div>

                            <div class="grid gap-3">
                                <div
                                    v-for="translation in otherTranslations"
                                    :key="translation.id"
                                    class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-3"
                                >
                                    <div class="min-w-0 space-y-1">
                                        <div
                                            class="flex flex-wrap items-center gap-2 text-sm"
                                        >
                                            <span
                                                class="font-semibold uppercase text-slate-700"
                                            >
                                                {{ translation.locale }}
                                            </span>
                                            <span
                                                class="truncate text-slate-900"
                                            >
                                                {{ translation.name }}
                                            </span>
                                        </div>
                                        <div class="text-xs text-slate-500">
                                            {{ translation.layout_name || '-' }}
                                            ·
                                            {{
                                                t(
                                                    'templates.columns.sections_count',
                                                    'Secties',
                                                )
                                            }}:
                                            {{
                                                translation.sections_count ?? 0
                                            }}
                                        </div>
                                    </div>
                                    <Button
                                        as-child
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="shadow-none"
                                    >
                                        <Link :href="translation.edit_url">
                                            {{ t('content_form.open', 'Open') }}
                                        </Link>
                                    </Button>
                                </div>
                            </div>

                            <p
                                v-if="otherTranslations.length === 0"
                                class="text-sm text-slate-500"
                            >
                                {{
                                    t(
                                        'templates.translations.no_other',
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
                                                    'templates.translations.missing_help',
                                                    'No linked template for this active language yet.',
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

        <CmsRevisionHistoryDialog
            v-if="isEditMode"
            v-model:open="showRevisionDialog"
            subject-type="template"
            restore-route-name="admin.cms.templates.revisions.restore"
            :restore-route-params="{ template: templateItem.id }"
            :revisions="revisions"
            :impact-items-count="templateImpactCount"
        />

        <Dialog v-model:open="showTranslationDialog">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {{
                            t(
                                'templates.translations.dialog_title',
                                'Create template translation',
                            )
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            t(
                                'templates.translations.dialog_description',
                                'Choose whether the translated template is first created as a copy or directly prefilled with AI-translated labels.',
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
                                    'templates.translations.ai_help',
                                    'Translate with AI creates an inactive translated template and pre-translates visible labels and text blocks.',
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
    </AdminLayout>
</template>

<script setup>
import RwAutoCompleteInput from '@/Components/RwAutoCompleteInput.vue';
import AdminFormBackButton from '@/Components/Admin/Form/AdminFormBackButton.vue';
import AdminFormSaveButton from '@/Components/Admin/Form/AdminFormSaveButton.vue';
import RwFlashMessage from '@/Components/RwFlashMessage.vue';
import FieldValidationMessage from '@/Components/Validation/FieldValidationMessage.vue';
import FormValidationSummary from '@/Components/Validation/FormValidationSummary.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CmsRevisionHistoryDialog from '@/Pages/Admin/Cms/Components/CmsRevisionHistoryDialog.vue';
import CmsLayoutZoneEditor from '@/Pages/Admin/Cms/Layouts/Partials/CmsLayoutZoneEditor.vue';
import { updatedLayoutSectionsForZone } from '@/Pages/Admin/Cms/Layouts/layoutZoneFormUpdates.js';
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
import { computed, ref, watch } from 'vue';

const props = defineProps({
    templateItem: { type: Object, default: null },
    templateOptions: { type: Array, required: true },
    layoutOptions: { type: Array, default: () => [] },
    fieldDefinitions: { type: Array, required: true },
    fieldDefinitionsByContext: { type: Object, default: () => ({}) },
    availableSystemFieldsByContext: { type: Object, default: () => ({}) },
    placeableBlocks: { type: Array, required: true },
    colorPaletteItems: { type: Array, default: () => [] },
    styleTokenOptions: { type: Object, default: () => ({}) },
    activeThemeFontFaceCss: { type: String, default: '' },
    previewOptions: { type: Array, default: () => [] },
    revisions: { type: Array, default: () => [] },
    templateImpactCount: { type: Number, default: 0 },
    translations: { type: Array, default: () => [] },
    missingLanguages: { type: Array, default: () => [] },
    activeLanguages: { type: Array, default: () => [] },
    availableLocales: { type: Array, default: () => [] },
    canManageCodeBlocks: { type: Boolean, default: false },
    cacheStrategyOptions: { type: Array, required: true },
    formOptions: { type: Array, default: () => [] },
    menuOptions: { type: Array, default: () => [] },
    contactSettings: { type: Object, default: () => ({}) },
    mediaOptions: { type: Array, default: () => [] },
    mediaFolders: { type: Array, default: () => [] },
    downloadOptions: { type: Array, default: () => [] },
    downloadFolders: { type: Array, default: () => [] },
});

const page = usePage();
const { t } = useAdminTranslations('cms_admin_ui');
const { t: commonT } = useAdminTranslations('admin_common_ui');
const activeTab = ref(initialActiveTab());
const touched = ref({});
const showSummary = ref(false);
const showRevisionDialog = ref(false);
const showTranslationDialog = ref(false);
const sectionDialogSaveZone = ref(null);
const sectionDialogSaveInitialFlashKey = ref(null);
const placementDialogSaveZone = ref(null);
const placementDialogSaveInitialFlashKey = ref(null);
const localMediaOptions = ref([...props.mediaOptions]);
const localMediaFolders = ref([...props.mediaFolders]);
const localColorPaletteItems = ref([...props.colorPaletteItems]);
const selectedPreviewSampleId = ref(props.previewOptions[0]?.id ?? '');
const translationAction = ref(null);
const locale = computed(() => page.props?.app?.locale || 'nl-BE');
const isEditMode = computed(() => Boolean(props.templateItem?.id));
const templateEditorKey = computed(() => props.templateItem?.id ?? 'new');

const form = useForm(templateFormData());
const translationForm = useForm({
    target_locale: '',
    use_ai: true,
});

const pageTitle = computed(() =>
    isEditMode.value
        ? t('templates.edit_title', 'Template bewerken')
        : t('templates.create_title', 'Template toevoegen'),
);

const previewUrl = computed(() => {
    if (!isEditMode.value) {
        return '';
    }

    const url = route('admin.cms.templates.preview', {
        id: props.templateItem.id,
    });

    if (!selectedPreviewSampleId.value) {
        return url;
    }

    const params = new URLSearchParams({
        sample_id: String(selectedPreviewSampleId.value),
    });

    return `${url}?${params.toString()}`;
});

const tabOptions = computed(() => [
    { value: 'basis', label: t('common.tabs.basic', 'Basis') },
    { value: 'content', label: t('templates.tabs.content', 'Content') },
    { value: 'fields', label: t('templates.tabs.fields', 'Fields') },
    { value: 'preview', label: t('templates.tabs.preview', 'Preview') },
    { value: 'instellingen', label: t('common.tabs.settings', 'Settings') },
]);

const classOptions = computed(() =>
    props.templateOptions.map((option) => ({
        value: option.value,
        label: t(option.label_key, option.value),
    })),
);

const templateTypeOptions = computed(() => {
    const option = props.templateOptions.find(
        (item) => item.value === form.template_class,
    );

    return (option?.template_types || []).map((templateType) => ({
        value: templateType.value,
        label: t(templateType.label_key, templateType.value),
    }));
});

const currentTemplateClassLabel = computed(
    () =>
        classOptions.value.find(
            (option) => option.value === form.template_class,
        )?.label || form.template_class,
);

const currentTemplateTypeLabel = computed(
    () =>
        templateTypeOptions.value.find(
            (option) => option.value === form.template_key,
        )?.label || form.template_key,
);

const layoutOptionsForLocale = computed(() =>
    props.layoutOptions.filter((layout) => layout.locale === form.locale),
);

const selectableLanguages = computed(() =>
    props.activeLanguages.length > 0
        ? props.activeLanguages
        : props.availableLocales.map((localeOption) => ({
              locale: localeOption,
              name: localeOption,
              native_name: localeOption,
          })),
);

const languageOptions = computed(() =>
    selectableLanguages.value.map((language) => ({
        value: language.locale,
        label: languageLabel(language),
    })),
);

const currentLanguageLabel = computed(
    () =>
        languageOptions.value.find((option) => option.value === form.locale)
            ?.label || form.locale,
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

const otherTranslations = computed(() =>
    props.translations.filter((translation) => !translation.is_current),
);

const selectedTranslationLanguageLabel = computed(() => {
    const language = missingLanguages.value.find(
        (item) => item.locale === translationForm.target_locale,
    );

    return language
        ? languageLabel(language)
        : t('content_form.no_language_selected', 'No language selected');
});

const currentFieldDefinitions = computed(() => {
    const enabledSystemKeys = new Set(
        (form.data_contract?.system_fields || [])
            .filter((field) => field.enabled !== false)
            .map((field) => field.key),
    );
    const systemFields = currentSystemFields.value
        .filter((field) => enabledSystemKeys.has(field.key))
        .map((field) => ({
            ...field,
            source: 'system',
            label: systemFieldLabel(field),
        }));
    return systemFields;
});

const currentSystemFields = computed(
    () =>
        props.availableSystemFieldsByContext[form.template_key] ||
        props.fieldDefinitionsByContext[form.template_key] ||
        props.fieldDefinitions,
);

const activePlaceableBlocks = computed(() => {
    const fieldOptions = currentFieldDefinitions.value.map((field) => ({
        value: field.key,
        label_key: field.label_key,
        label: field.label || field.key,
    }));

    return props.placeableBlocks.map((block) => {
        if (block.renderer_key !== 'dynamic_field') {
            return block;
        }

        return {
            ...block,
            schema: {
                ...(block.schema || {}),
                editor_fields: (block.schema?.editor_fields || []).map(
                    (field) => {
                        if (field.name !== 'field_key') {
                            return field;
                        }

                        return { ...field, options: fieldOptions };
                    },
                ),
            },
        };
    });
});

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

const currentFlashKey = computed(() => {
    const flash = page.props?.flash || {};

    return flash._id ?? `${pageFlash.value.type}:${pageFlash.value.message}`;
});

const cardFlash = computed(() => {
    if (
        sectionDialogSaveZone.value !== null ||
        placementDialogSaveZone.value !== null
    ) {
        return { type: '', message: '', details: [] };
    }

    return pageFlash.value;
});

const validationErrorMessages = computed(() => ({
    ...clientErrors.value,
    ...form.errors,
}));

const validationErrorList = computed(() =>
    Object.entries(validationErrorMessages.value).map(([name, error]) => ({
        name,
        label: fieldLabel(name),
        error,
    })),
);

const clientErrors = computed(() => {
    const errors = {};

    if (!String(form.name || '').trim()) {
        errors.name = t('validation.required', 'This field is required.');
    }

    if (!form.template_class) {
        errors.template_class = t(
            'validation.required',
            'This field is required.',
        );
    }

    if (!form.template_key) {
        errors.template_key = t(
            'validation.required',
            'This field is required.',
        );
    }

    if (!form.locale) {
        errors.locale = t('validation.required', 'This field is required.');
    }

    if (!form.layout_id) {
        errors.layout_id = t('validation.required', 'This field is required.');
    }

    appendSectionNameErrors(errors, 'content', form.sections?.content);

    return errors;
});

watch(
    () => form.template_class,
    () => {
        if (
            !templateTypeOptions.value.some(
                (option) => option.value === form.template_key,
            )
        ) {
            form.template_key =
                templateTypeOptions.value[0]?.value || 'page.detail';
        }
    },
);

watch(
    () => form.locale,
    () => {
        if (
            !layoutOptionsForLocale.value.some(
                (layout) => String(layout.id) === String(form.layout_id),
            )
        ) {
            form.layout_id =
                layoutOptionsForLocale.value.find((layout) => layout.is_default)
                    ?.id ||
                layoutOptionsForLocale.value[0]?.id ||
                '';
        }
    },
    { immediate: true },
);

function templateFormData() {
    const firstLocale = preferredCreateLocale();
    const settings = props.templateItem?.settings ?? {};

    return {
        name: props.templateItem?.name ?? '',
        locale: props.templateItem?.locale ?? firstLocale,
        layout_id: props.templateItem?.layout_id ?? '',
        template_class: props.templateItem?.template_class ?? 'page',
        template_key: props.templateItem?.template_key ?? 'page.detail',
        is_default: props.templateItem?.is_default ?? false,
        is_active: props.templateItem?.is_active ?? true,
        cache_strategy: props.templateItem?.cache_strategy ?? 'inherit',
        settings: { html_anchor: '', ...settings },
        sections: props.templateItem?.sections ?? { content: [] },
        data_contract: normalizeDataContract(
            props.templateItem?.data_contract ?? null,
            props.templateItem?.template_key ?? 'page.detail',
        ),
    };
}

function normalizeDataContract(
    contract,
    templateKey = form?.template_key || 'page.detail',
) {
    const systemFields =
        Array.isArray(contract?.system_fields) &&
        contract.system_fields.length > 0
            ? contract.system_fields
            : (
                  props.availableSystemFieldsByContext[templateKey] ||
                  props.fieldDefinitions
              ).map((field) => ({ key: field.key, enabled: true }));

    return {
        system_fields: systemFields.map((field) => ({
            key: field.key,
            enabled: field.enabled !== false,
        })),
    };
}

function systemFieldEnabled(key) {
    return (form.data_contract.system_fields || []).some(
        (field) => field.key === key && field.enabled !== false,
    );
}

function setSystemFieldEnabled(key, enabled) {
    const fields = [...(form.data_contract.system_fields || [])];
    const index = fields.findIndex((field) => field.key === key);

    if (index >= 0) {
        fields[index] = { ...fields[index], enabled };
    } else {
        fields.push({ key, enabled });
    }

    form.data_contract.system_fields = fields;
}

function systemFieldLabel(field) {
    return `${t(field.label_key, field.key)} (${field.key})`;
}

function preferredCreateLocale() {
    const appLocale = String(page.props?.app?.locale || '');
    const appBaseLocale = appLocale.split(/[-_]/)[0] || '';
    const activeLocales = props.activeLanguages
        .map((language) => language.locale)
        .filter(Boolean);

    return (
        activeLocales.find((localeOption) => localeOption === appLocale) ||
        activeLocales.find((localeOption) => localeOption === appBaseLocale) ||
        props.availableLocales.find(
            (localeOption) => localeOption === appLocale,
        ) ||
        props.availableLocales.find(
            (localeOption) => localeOption === appBaseLocale,
        ) ||
        activeLocales[0] ||
        props.availableLocales[0] ||
        'nl'
    );
}

function touch(field) {
    touched.value = { ...touched.value, [field]: true };
}

function updateColorPaletteItems(items) {
    localColorPaletteItems.value = [...items];
}

function updateTemplateContentSections(sections) {
    form.sections = updatedLayoutSectionsForZone(
        form.sections,
        'content',
        sections,
    );
}

function validationMessage(field) {
    if (!touched.value[field] && !showSummary.value) {
        return form.errors[field] || '';
    }

    return validationErrorMessages.value[field] || '';
}

function fieldLabel(field) {
    const sectionNameMatch = field.match(/^sections\.([^.]+)\.\d+\.name$/);

    if (sectionNameMatch) {
        const zoneLabels = {
            content: t('templates.tabs.content', 'Content'),
        };

        return `${zoneLabels[sectionNameMatch[1]] || sectionNameMatch[1]}: ${t('layouts.sections.section_name', 'Section name')}`;
    }

    const labels = {
        name: t('templates.name', 'Name'),
        template_class: t('templates.template_class', 'Template group'),
        template_key: t('templates.template_type', 'Template type'),
        locale: t('common.columns.locale', 'Language'),
        layout_id: t('templates.layout', 'Layout'),
    };

    return labels[field] || field;
}

function submit(returnTab = null, options = {}) {
    showSummary.value = true;
    form.data_contract = normalizeDataContract(
        form.data_contract,
        form.template_key,
    );
    touched.value = {
        name: true,
        template_class: true,
        template_key: true,
        locale: true,
        layout_id: true,
    };

    const clientErrorFields = Object.keys(clientErrors.value);

    if (clientErrorFields.length > 0) {
        activeTab.value = clientErrorFields.some((field) =>
            field.startsWith('sections.content.'),
        )
            ? 'content'
            : 'basis';

        return;
    }

    const normalizedReturnTab = normalizeActiveTab(returnTab);
    const isSectionDialogSave =
        options.source === 'section-dialog' && normalizedReturnTab !== null;
    const isPlacementDialogSave =
        options.source === 'placement-dialog' && normalizedReturnTab !== null;

    if (normalizedReturnTab !== null) {
        storePendingActiveTab(normalizedReturnTab);
    }

    if (isSectionDialogSave) {
        sectionDialogSaveZone.value = normalizedReturnTab;
        sectionDialogSaveInitialFlashKey.value = currentFlashKey.value;
        clearPlacementDialogFlashTarget();
    } else if (isPlacementDialogSave) {
        placementDialogSaveZone.value = normalizedReturnTab;
        placementDialogSaveInitialFlashKey.value = currentFlashKey.value;
        clearSectionDialogFlashTarget();
    } else {
        clearSectionDialogFlashTarget();
        clearPlacementDialogFlashTarget();
    }

    form.post(
        route('admin.cms.templates.store', {
            id: props.templateItem?.id ?? 0,
        }),
        {
            preserveState:
                isSectionDialogSave || isPlacementDialogSave ? true : 'errors',
            preserveScroll: true,
            onError: () => {
                clearPendingActiveTab();

                if (!isSectionDialogSave) {
                    clearSectionDialogFlashTarget();
                }

                if (!isPlacementDialogSave) {
                    clearPlacementDialogFlashTarget();
                }
            },
        },
    );
}

function sectionDialogFlash(zone) {
    const errorDetails = sectionDialogErrorDetails(zone);

    if (sectionDialogSaveZone.value === zone && errorDetails.length > 0) {
        return {
            type: 'danger',
            message: t('validation.summary_title', 'Save is blocked'),
            details: errorDetails,
        };
    }

    if (
        sectionDialogSaveZone.value !== zone ||
        sectionDialogSaveInitialFlashKey.value === currentFlashKey.value
    ) {
        return { type: '', message: '', details: [] };
    }

    return pageFlash.value;
}

function sectionDialogErrorDetails(zone) {
    return Object.entries(form.errors)
        .filter(
            ([name]) =>
                name === 'sections' || name.startsWith(`sections.${zone}.`),
        )
        .map(([name, error]) => ({
            name,
            label: fieldLabel(name),
            error,
        }));
}

function placementDialogFlash(zone) {
    const errorDetails = placementDialogErrorDetails();

    if (placementDialogSaveZone.value === zone && errorDetails.length > 0) {
        return {
            type: 'danger',
            message: t('validation.summary_title', 'Save is blocked'),
            details: errorDetails,
        };
    }

    if (
        placementDialogSaveZone.value !== zone ||
        placementDialogSaveInitialFlashKey.value === currentFlashKey.value
    ) {
        return { type: '', message: '', details: [] };
    }

    return pageFlash.value;
}

function placementDialogErrorDetails() {
    return Object.entries(form.errors).map(([name, error]) => ({
        name,
        label: fieldLabel(name),
        error,
    }));
}

function handleZoneSectionDialogOpenChange({ zone, open }) {
    if (!open && sectionDialogSaveZone.value === zone) {
        clearSectionDialogFlashTarget();
    }
}

function handleZonePlacementDialogOpenChange({ zone, open }) {
    if (!open && placementDialogSaveZone.value === zone) {
        clearPlacementDialogFlashTarget();
    }
}

function clearSectionDialogFlashTarget() {
    sectionDialogSaveZone.value = null;
    sectionDialogSaveInitialFlashKey.value = null;
}

function clearPlacementDialogFlashTarget() {
    placementDialogSaveZone.value = null;
    placementDialogSaveInitialFlashKey.value = null;
}

function initialActiveTab() {
    if (typeof window === 'undefined') {
        return 'basis';
    }

    const pendingTab = normalizeActiveTab(
        window.sessionStorage.getItem(activeTabStorageKey()),
    );
    window.sessionStorage.removeItem(activeTabStorageKey());

    return pendingTab ?? 'basis';
}

function activeTabStorageKey() {
    return `cms-template:${props.templateItem?.id ?? 'new'}:return-tab`;
}

function storePendingActiveTab(tab) {
    if (typeof window === 'undefined') {
        return;
    }

    window.sessionStorage.setItem(activeTabStorageKey(), tab);
}

function clearPendingActiveTab() {
    if (typeof window === 'undefined') {
        return;
    }

    window.sessionStorage.removeItem(activeTabStorageKey());
}

function normalizeActiveTab(value) {
    return ['basis', 'content', 'fields', 'preview', 'instellingen'].includes(
        value,
    )
        ? value
        : null;
}

function appendSectionNameErrors(errors, zone, sections) {
    (Array.isArray(sections) ? sections : []).forEach((section, index) => {
        if (String(section?.name || '').trim()) {
            return;
        }

        errors[`sections.${zone}.${index}.name`] = t(
            'validation.required',
            'This field is required.',
        );
    });
}

function openTranslationDialog(localeValue = '') {
    translationForm.clearErrors();
    translationForm.target_locale =
        localeValue || missingLanguages.value[0]?.locale || '';
    translationForm.use_ai = true;
    showTranslationDialog.value = true;
}

function createTranslation(useAi) {
    if (!props.templateItem?.id) {
        return;
    }

    translationAction.value = useAi ? 'ai' : 'copy';
    translationForm.use_ai = useAi;
    translationForm.post(
        route('admin.cms.templates.translations.store', {
            id: props.templateItem.id,
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
        window.location.href = route('admin.cms.templates.edit', {
            id: item.id,
        });
    }
}

function translationChipTitle(item) {
    if (item.type === 'missing') {
        return t(
            'templates.translations.missing_help',
            'No linked template for this active language yet.',
        );
    }

    if (item.isCurrent) {
        return t('content_form.current', 'Current');
    }

    return item.status === 'warning'
        ? t('common.columns.active', 'Actief')
        : t('content_form.open', 'Open');
}

function translationStatusClass(item) {
    const currentClass = item.isCurrent
        ? ' ring-2 ring-blue-500 ring-offset-1'
        : '';

    if (item.status === 'success') {
        return `border-emerald-200 bg-emerald-50 text-emerald-700${currentClass}`;
    }

    if (item.status === 'warning') {
        return `border-orange-200 bg-orange-50 text-orange-700${currentClass}`;
    }

    return `border-red-200 bg-red-50 text-red-700${currentClass}`;
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

function formatDate(value, includeTime = true) {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat(locale.value, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        ...(includeTime
            ? {
                  hour: '2-digit',
                  minute: '2-digit',
              }
            : {}),
    }).format(new Date(value));
}
</script>
