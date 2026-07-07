<template>
    <Head :title="pageTitle">
        <component :is="'style'" v-if="activeThemeFontFaceCss" type="text/css">
            {{ activeThemeFontFaceCss }}
        </component>
    </Head>

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
                                <span
                                    class="mdi mdi-view-dashboard-edit text-2xl"
                                />
                            </div>
                            <div class="min-w-0">
                                <CardTitle class="text-lg">{{
                                    pageTitle
                                }}</CardTitle>
                                <CardDescription class="mt-1">
                                    {{
                                        t(
                                            'layouts.form_description',
                                            'Beheer de basisgegevens, header en footer van deze layout.',
                                        )
                                    }}
                                </CardDescription>
                            </div>
                        </div>
                        <div class="flex flex-wrap justify-end gap-2">
                            <AdminFormBackButton
                                :href="route('admin.cms.layouts.index')"
                                :dirty="form.isDirty"
                                :processing="form.processing"
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
                                {{ t('revisions.open', 'Versies') }}
                            </Button>
                            <AdminFormSaveButton
                                :dirty="form.isDirty"
                                :processing="form.processing"
                                :label="t('actions.save', 'Bewaren')"
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
                    v-if="cardFlash.message"
                    class="shrink-0 border-b border-slate-200 px-4 py-3 sm:px-5"
                >
                    <RwFlashMessage
                        :type="cardFlash.type"
                        :message="cardFlash.message"
                        :details="cardFlash.details"
                    />
                </div>

                <CardContent class="flex min-h-0 flex-1 flex-col p-0">
                    <div class="shrink-0 border-b border-slate-200">
                        <div class="flex flex-wrap gap-4 px-4 sm:px-5">
                            <button
                                v-for="tab in tabOptions"
                                :key="tab.value"
                                type="button"
                                class="-mb-px border-b-2 px-1 py-3 text-sm font-medium transition"
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
                            v-if="layoutItem?.ai_translation_review?.is_pending"
                            :review="layoutItem.ai_translation_review"
                        />

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
                                            'layouts.translations.save_before',
                                            'Bewaar de layout eerst om vertalingen te beheren.',
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
                                            'Meertaligheid staat momenteel uit in de CMS-instellingen.',
                                        )
                                    }}
                                </p>
                            </div>

                            <div class="grid gap-4 lg:grid-cols-2">
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
                                        {{ t('layouts.name', 'Naam') }}
                                    </Label>
                                    <Input
                                        id="name"
                                        v-model="form.name"
                                        autocomplete="off"
                                        name="name"
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
                                    <Label
                                        for="locale"
                                        class="flex items-center gap-1"
                                    >
                                        <span
                                            class="text-red-600"
                                            aria-hidden="true"
                                            >*</span
                                        >
                                        {{ t('common.columns.locale', 'Taal') }}
                                    </Label>
                                    <Input
                                        v-if="isEditMode"
                                        id="locale"
                                        :model-value="currentLanguageLabel"
                                        readonly
                                        class="bg-slate-50 text-slate-700"
                                    />
                                    <RwAutoCompleteInput
                                        v-else
                                        id="locale"
                                        v-model="form.locale"
                                        name="locale"
                                        :aria-label="
                                            t('common.columns.locale', 'Taal')
                                        "
                                        :items="languageOptions"
                                        item-title="label"
                                        item-value="value"
                                        :required="true"
                                        required-highlight-color="#fefce8"
                                        :placeholder="
                                            t(
                                                'layouts.choose_language',
                                                'Kies taal',
                                            )
                                        "
                                        @blur="touch('locale')"
                                    />
                                    <FieldValidationMessage
                                        :message="validationMessage('locale')"
                                        :value="form.locale"
                                    />
                                    <p
                                        v-if="isEditMode"
                                        class="text-xs text-slate-500"
                                    >
                                        {{
                                            t(
                                                'layouts.language_readonly_help',
                                                'The language is fixed for existing layouts. Use translations to create another language version.',
                                            )
                                        }}
                                    </p>
                                </div>
                                <div class="grid gap-2">
                                    <Label
                                        for="scroll_mode"
                                        class="flex items-center gap-1"
                                    >
                                        <span
                                            class="text-red-600"
                                            aria-hidden="true"
                                            >*</span
                                        >
                                        {{
                                            t(
                                                'layouts.scroll_mode',
                                                'Scrollgedrag',
                                            )
                                        }}
                                    </Label>
                                    <RwAutoCompleteInput
                                        id="scroll_mode"
                                        v-model="form.settings.scroll_mode"
                                        name="settings_scroll_mode"
                                        :aria-label="
                                            t(
                                                'layouts.scroll_mode',
                                                'Scrollgedrag',
                                            )
                                        "
                                        :items="scrollModeOptions"
                                        item-title="label"
                                        item-value="value"
                                        :required="true"
                                        required-highlight-color="#fefce8"
                                        :placeholder="
                                            t(
                                                'layouts.choose_scroll_mode',
                                                'Kies scrollgedrag',
                                            )
                                        "
                                        @blur="touch('settings.scroll_mode')"
                                    />
                                    <p class="text-xs text-slate-500">
                                        {{
                                            t(
                                                'layouts.scroll_mode_help',
                                                'Gebruik normale browser-scroll tenzij je header/footer bewust vast rond een interne contentzone wil zetten.',
                                            )
                                        }}
                                    </p>
                                    <FieldValidationMessage
                                        :message="
                                            validationMessage(
                                                'settings.scroll_mode',
                                            )
                                        "
                                        :value="form.settings.scroll_mode"
                                    />
                                </div>
                                <div
                                    class="grid gap-3 rounded-md border border-slate-200 p-3"
                                >
                                    <label
                                        class="flex items-center gap-2 text-sm"
                                    >
                                        <input
                                            id="is_active"
                                            v-model="form.is_active"
                                            name="is_active"
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-slate-300"
                                        />
                                        {{
                                            t('common.columns.active', 'Actief')
                                        }}
                                    </label>
                                    <label
                                        class="flex items-center gap-2 text-sm"
                                    >
                                        <input
                                            id="is_default"
                                            v-model="form.is_default"
                                            name="is_default"
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-slate-300"
                                        />
                                        {{
                                            t(
                                                'layouts.default_for_locale',
                                                'Default voor deze taal',
                                            )
                                        }}
                                    </label>
                                    <p class="text-xs text-slate-500">
                                        {{
                                            t(
                                                'layouts.default_help',
                                                'Een default layout wordt automatisch actief en vervangt de vorige default layout voor dezelfde taal.',
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>

                            <BackgroundPickerField
                                v-model="form.settings.background"
                                v-model:palette-items="localColorPaletteItems"
                                :assets="localMediaOptions"
                                :folders="localMediaFolders"
                                id-prefix="layout-background"
                                :label="
                                    t(
                                        'layouts.layout_background_title',
                                        'Achtergrond volledige layout',
                                    )
                                "
                                @update:assets="updateMediaOptions"
                                @update:folders="updateMediaFolders"
                            />
                            <p class="text-xs text-slate-500">
                                {{
                                    t(
                                        'layouts.layout_background_description',
                                        'Deze achtergrond wordt achter header, content en footer op de volledige layout-shell geplaatst.',
                                    )
                                }}
                            </p>

                            <div
                                v-if="form.settings.html_anchor"
                                class="grid max-w-md gap-1 rounded-md border border-slate-200 bg-slate-50 p-3 text-sm"
                            >
                                <Label for="layout_html_anchor">
                                    {{ t('layouts.css_anchor', 'CSS anchor') }}
                                </Label>
                                <Input
                                    id="layout_html_anchor"
                                    :model-value="form.settings.html_anchor"
                                    readonly
                                    class="bg-white font-mono text-xs"
                                />
                                <p class="text-xs text-slate-600">
                                    {{
                                        t(
                                            'layouts.css_anchor_help',
                                            'Use this stable ID only for custom site-specific CSS. Platform and theme CSS should keep using classes and tokens.',
                                        )
                                    }}
                                </p>
                            </div>

                            <div
                                v-if="layoutItem"
                                class="rounded-md border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600"
                            >
                                {{
                                    t(
                                        'layouts.current_usage',
                                        ":pages pagina's en :sections sections gekoppeld.",
                                        {
                                            pages: layoutItem.pages_count,
                                            sections: layoutItem.sections_count,
                                        },
                                    )
                                }}
                            </div>
                        </section>

                        <section
                            v-else-if="activeTab === 'head'"
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
                                                'layouts.sections.head_tab',
                                                'Head',
                                            )
                                        }}
                                    </h2>
                                    <p class="text-sm text-slate-500">
                                        {{
                                            t(
                                                'layouts.sections.head_stack_description',
                                                'Bepaal de volgorde van vaste systeemonderdelen en vertrouwde custom snippets in de HTML head.',
                                            )
                                        }}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="gap-2 shadow-none"
                                    @click="addHeadSnippet"
                                >
                                    <span
                                        class="mdi mdi-plus text-base"
                                        aria-hidden="true"
                                    />
                                    {{
                                        t(
                                            'layouts.sections.head_add_snippet',
                                            'Snippet toevoegen',
                                        )
                                    }}
                                </Button>
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
                                            'layouts.sections.custom_code_warning',
                                            'Deze code wordt ongeescaped in de publieke site geplaatst. Gebruik dit alleen voor vertrouwde snippets.',
                                        )
                                    }}
                                </span>
                            </p>
                            <div class="space-y-3">
                                <div
                                    v-for="(entry, index) in headStack"
                                    :key="entry.key"
                                    data-drag-preview-row="true"
                                    class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm"
                                    :class="
                                        dragOverHeadEntryKey === entry.key
                                            ? 'border-blue-300 bg-blue-50/60'
                                            : ''
                                    "
                                    @dragenter.prevent="
                                        onHeadEntryDragOver(entry.key, $event)
                                    "
                                    @dragover.prevent="
                                        onHeadEntryDragOver(entry.key, $event)
                                    "
                                    @drop.prevent="onHeadEntryDrop"
                                >
                                    <div
                                        class="flex flex-wrap items-center justify-between gap-3"
                                    >
                                        <div class="min-w-0">
                                            <div
                                                class="flex flex-wrap items-center gap-2"
                                            >
                                                <button
                                                    v-if="
                                                        isHeadEntryMoveable(
                                                            entry,
                                                        )
                                                    "
                                                    type="button"
                                                    class="inline-flex h-8 w-8 cursor-grab items-center justify-center rounded-md border border-slate-300 bg-white text-slate-600 shadow-none transition hover:bg-slate-100 active:cursor-grabbing"
                                                    draggable="true"
                                                    :title="
                                                        t(
                                                            'layouts.sections.drag_head_entry',
                                                            'Sleep om de volgorde te wijzigen',
                                                        )
                                                    "
                                                    :aria-label="
                                                        t(
                                                            'layouts.sections.drag_head_entry',
                                                            'Sleep om de volgorde te wijzigen',
                                                        )
                                                    "
                                                    @dragstart="
                                                        onHeadEntryDragStart(
                                                            entry.key,
                                                            $event,
                                                        )
                                                    "
                                                    @dragend="
                                                        onHeadEntryDragEnd
                                                    "
                                                >
                                                    <span
                                                        class="mdi mdi-drag-vertical text-xl"
                                                        aria-hidden="true"
                                                    />
                                                </button>
                                                <span
                                                    v-else
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 bg-slate-50 text-slate-400"
                                                    :title="
                                                        t(
                                                            'layouts.sections.head_locked_note',
                                                            'Verplicht systeemonderdeel. Dit onderdeel kan niet verwijderd worden.',
                                                        )
                                                    "
                                                    aria-hidden="true"
                                                >
                                                    <span
                                                        class="mdi mdi-lock text-base"
                                                        aria-hidden="true"
                                                    />
                                                </span>
                                                <span
                                                    class="font-medium text-slate-900"
                                                >
                                                    {{ headEntryTitle(entry) }}
                                                </span>
                                                <span
                                                    v-if="entry.locked"
                                                    class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-blue-100"
                                                >
                                                    {{
                                                        t(
                                                            'layouts.sections.head_locked_badge',
                                                            'Verplicht',
                                                        )
                                                    }}
                                                </span>
                                            </div>
                                            <p
                                                v-if="entry.locked"
                                                class="text-xs text-slate-500"
                                            >
                                                {{
                                                    t(
                                                        'layouts.sections.head_locked_note',
                                                        'Verplicht systeemonderdeel. Dit onderdeel kan niet verwijderd worden.',
                                                    )
                                                }}
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <Button
                                                v-if="entry.locked"
                                                type="button"
                                                variant="outline"
                                                class="h-8 gap-2 px-2 text-xs shadow-none"
                                                @click="
                                                    toggleHeadPreview(
                                                        entry.type,
                                                    )
                                                "
                                            >
                                                <span
                                                    class="mdi mdi-code-tags text-base"
                                                    aria-hidden="true"
                                                />
                                                {{
                                                    isHeadPreviewOpen(
                                                        entry.type,
                                                    )
                                                        ? t(
                                                              'layouts.sections.head_code_preview_hide',
                                                              'Code verbergen',
                                                          )
                                                        : t(
                                                              'layouts.sections.head_code_preview_show',
                                                              'Code tonen',
                                                          )
                                                }}
                                            </Button>
                                            <Button
                                                v-if="
                                                    isHeadEntryMoveable(entry)
                                                "
                                                type="button"
                                                variant="outline"
                                                size="icon"
                                                class="h-8 w-8 shadow-none"
                                                :disabled="index === 0"
                                                :title="
                                                    t(
                                                        'actions.move_up',
                                                        'Omhoog',
                                                    )
                                                "
                                                @click="
                                                    moveHeadEntry(index, -1)
                                                "
                                            >
                                                <span
                                                    class="mdi mdi-arrow-up text-base"
                                                    aria-hidden="true"
                                                />
                                            </Button>
                                            <Button
                                                v-if="
                                                    isHeadEntryMoveable(entry)
                                                "
                                                type="button"
                                                variant="outline"
                                                size="icon"
                                                class="h-8 w-8 shadow-none"
                                                :disabled="
                                                    index ===
                                                    headStack.length - 1
                                                "
                                                :title="
                                                    t(
                                                        'actions.move_down',
                                                        'Omlaag',
                                                    )
                                                "
                                                @click="moveHeadEntry(index, 1)"
                                            >
                                                <span
                                                    class="mdi mdi-arrow-down text-base"
                                                    aria-hidden="true"
                                                />
                                            </Button>
                                            <Button
                                                v-if="!entry.locked"
                                                type="button"
                                                variant="outline"
                                                size="icon"
                                                class="h-8 w-8 border-red-200 text-red-700 shadow-none hover:bg-red-50 hover:text-red-800"
                                                :title="
                                                    t(
                                                        'actions.delete',
                                                        'Verwijderen',
                                                    )
                                                "
                                                @click="removeHeadEntry(index)"
                                            >
                                                <span
                                                    class="mdi mdi-delete text-base"
                                                    aria-hidden="true"
                                                />
                                            </Button>
                                        </div>
                                    </div>
                                    <RwCodeEditor
                                        v-if="
                                            entry.locked &&
                                            isHeadPreviewOpen(entry.type)
                                        "
                                        :model-value="
                                            headSystemPreview(entry.type)
                                        "
                                        class="mt-3"
                                        language="php"
                                        height="320px"
                                        :readonly="true"
                                    />
                                    <RwCodeEditor
                                        v-if="!entry.locked"
                                        v-model="entry.code"
                                        class="mt-3"
                                        language="html"
                                        height="220px"
                                        :placeholder="
                                            t(
                                                'layouts.sections.head_code_placeholder',
                                                '<!-- Extra head code -->',
                                            )
                                        "
                                    />
                                </div>
                            </div>
                            <FieldValidationMessage
                                :message="validationMessage('sections.head')"
                            />
                        </section>

                        <section
                            v-else-if="activeTab === 'header'"
                            class="space-y-4"
                        >
                            <CmsLayoutZoneEditor
                                :key="`${layoutEditorKey}:header`"
                                :model-value="form.sections.header"
                                zone="header"
                                :title="
                                    t(
                                        'layouts.sections.header_title',
                                        'Header blokken',
                                    )
                                "
                                :description="
                                    t(
                                        'layouts.sections.header_description',
                                        'Blokken in deze zone worden boven de pagina-inhoud gestapeld.',
                                    )
                                "
                                :placeable-blocks="placeableBlocks"
                                :color-palette-items="localColorPaletteItems"
                                :style-token-options="styleTokenOptions"
                                :layout-locale="form.locale"
                                :form-options="formOptions"
                                :menu-options="menuOptions"
                                :contact-settings="contactSettings"
                                v-model:media-options="localMediaOptions"
                                v-model:media-folders="localMediaFolders"
                                @update:color-palette-items="
                                    updateColorPaletteItems
                                "
                                :can-manage-code-blocks="canManageCodeBlocks"
                                :dialog-flash="sectionDialogFlash('header')"
                                :placement-dialog-flash="
                                    placementDialogFlash('header')
                                "
                                :saving="
                                    sectionDialogSaveZone === 'header' &&
                                    form.processing
                                "
                                :placement-saving="
                                    placementDialogSaveZone === 'header' &&
                                    form.processing
                                "
                                @save-requested="
                                    submit('header', {
                                        source: 'section-dialog',
                                    })
                                "
                                @section-dialog-open-changed="
                                    handleZoneSectionDialogOpenChange
                                "
                                @placement-dialog-open-changed="
                                    handleZonePlacementDialogOpenChange
                                "
                                @placement-save-requested="
                                    submit('header', {
                                        source: 'placement-dialog',
                                    })
                                "
                                @update:model-value="
                                    updateLayoutZoneSections('header', $event)
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
                            v-else-if="activeTab === 'body_end'"
                            class="space-y-4"
                        >
                            <div>
                                <h2
                                    class="text-base font-semibold text-slate-900"
                                >
                                    {{
                                        t(
                                            'layouts.sections.body_end_tab',
                                            'Body einde',
                                        )
                                    }}
                                </h2>
                                <p class="text-sm text-slate-500">
                                    {{
                                        t(
                                            'layouts.sections.body_end_code_description',
                                            'Extra code die vlak voor het sluiten van de body wordt geplaatst, bijvoorbeeld scripts, widgets of tagmanager body-snippets.',
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
                                            'layouts.sections.custom_code_warning',
                                            'Deze code wordt ongeescaped in de publieke site geplaatst. Gebruik dit alleen voor vertrouwde snippets.',
                                        )
                                    }}
                                </span>
                            </p>
                            <RwCodeEditor
                                v-model="bodyEndCode"
                                language="html"
                                height="360px"
                                :placeholder="
                                    t(
                                        'layouts.sections.body_end_code_placeholder',
                                        '<!-- Extra body end code -->',
                                    )
                                "
                            />
                        </section>

                        <section
                            v-else-if="activeTab === 'footer'"
                            class="space-y-4"
                        >
                            <CmsLayoutZoneEditor
                                :key="`${layoutEditorKey}:footer`"
                                :model-value="form.sections.footer"
                                zone="footer"
                                :title="
                                    t(
                                        'layouts.sections.footer_title',
                                        'Footer blocks',
                                    )
                                "
                                :description="
                                    t(
                                        'layouts.sections.footer_description',
                                        'Footerblocks kunnen meescrollen of vast onderaan blijven staan.',
                                    )
                                "
                                :placeable-blocks="placeableBlocks"
                                :color-palette-items="localColorPaletteItems"
                                :style-token-options="styleTokenOptions"
                                :layout-locale="form.locale"
                                :form-options="formOptions"
                                :menu-options="menuOptions"
                                :contact-settings="contactSettings"
                                v-model:media-options="localMediaOptions"
                                v-model:media-folders="localMediaFolders"
                                @update:color-palette-items="
                                    updateColorPaletteItems
                                "
                                :can-manage-code-blocks="canManageCodeBlocks"
                                :dialog-flash="sectionDialogFlash('footer')"
                                :placement-dialog-flash="
                                    placementDialogFlash('footer')
                                "
                                :saving="
                                    sectionDialogSaveZone === 'footer' &&
                                    form.processing
                                "
                                :placement-saving="
                                    placementDialogSaveZone === 'footer' &&
                                    form.processing
                                "
                                @save-requested="
                                    submit('footer', {
                                        source: 'section-dialog',
                                    })
                                "
                                @section-dialog-open-changed="
                                    handleZoneSectionDialogOpenChange
                                "
                                @placement-dialog-open-changed="
                                    handleZonePlacementDialogOpenChange
                                "
                                @placement-save-requested="
                                    submit('footer', {
                                        source: 'placement-dialog',
                                    })
                                "
                                @update:model-value="
                                    updateLayoutZoneSections('footer', $event)
                                "
                            />
                            <p
                                v-if="form.errors.sections"
                                class="text-sm text-red-600"
                            >
                                {{ form.errors.sections }}
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
                                'layouts.translation_dialog_description',
                                'Kies of de layout met AI vertaald wordt of eerst als kopie wordt aangemaakt.',
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
                                    'layouts.ai_layout_help',
                                    'Met AI vertalen worden alleen menselijke layoutteksten vertaald. Codeblocks en systemblocks blijven ongewijzigd.',
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

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="showTranslationDialog = false"
                    >
                        {{ t('actions.back', 'Terug') }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
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
            subject-type="layout"
            restore-route-name="admin.cms.layouts.revisions.restore"
            :restore-route-params="{ layout: layoutItem.id }"
            :revisions="revisions"
            :impact-pages-count="layoutItem.pages_count"
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
import FieldValidationMessage from '@/Components/Validation/FieldValidationMessage.vue';
import FormValidationSummary from '@/Components/Validation/FormValidationSummary.vue';
import CmsRevisionHistoryDialog from '@/Pages/Admin/Cms/Components/CmsRevisionHistoryDialog.vue';
import BackgroundPickerField from '@/Pages/Admin/Cms/Layouts/Partials/BackgroundPickerField.vue';
import CmsLayoutZoneEditor from '@/Pages/Admin/Cms/Layouts/Partials/CmsLayoutZoneEditor.vue';
import { updatedLayoutSectionsForZone } from '@/Pages/Admin/Cms/Layouts/layoutZoneFormUpdates.js';
import AiTranslationReviewBanner from '@/Pages/Admin/Cms/Partials/AiTranslationReviewBanner.vue';
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
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    layoutItem: {
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
    missingLanguages: {
        type: Array,
        default: () => [],
    },
    availableLocales: {
        type: Array,
        default: () => [],
    },
    multilingualEnabled: {
        type: Boolean,
        default: true,
    },
    activeLanguages: {
        type: Array,
        default: () => [],
    },
    placeableBlocks: {
        type: Array,
        default: () => [],
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
    formOptions: {
        type: Array,
        default: () => [],
    },
    menuOptions: {
        type: Array,
        default: () => [],
    },
    contactSettings: {
        type: Object,
        default: () => ({}),
    },
    mediaOptions: {
        type: Array,
        default: () => [],
    },
    mediaFolders: {
        type: Array,
        default: () => [],
    },
    canManageCodeBlocks: {
        type: Boolean,
        default: false,
    },
    headSystemBlockPreviews: {
        type: Object,
        default: () => ({}),
    },
});

const { t } = useAdminTranslations('cms_admin_ui');
const { t: commonT } = useAdminTranslations('admin_common_ui');
const page = usePage();
const lockedHeadTypes = [
    'site_head_meta',
    'site_head_favicons',
    'site_head_system_assets',
    'site_head_theme',
];
const activeTab = ref(initialActiveTab());
const showTranslationDialog = ref(false);
const showRevisionDialog = ref(false);
const showSummary = ref(false);
const touched = ref({});
const sectionDialogSaveZone = ref(null);
const sectionDialogSaveInitialFlashKey = ref(null);
const placementDialogSaveZone = ref(null);
const placementDialogSaveInitialFlashKey = ref(null);
const expandedHeadPreviewTypes = ref({});
const draggedHeadEntryKey = ref(null);
const dragOverHeadEntryKey = ref(null);

const form = useForm(layoutFormData());
const localColorPaletteItems = ref([...props.colorPaletteItems]);
const localMediaOptions = ref([...props.mediaOptions]);
const localMediaFolders = ref([...props.mediaFolders]);
const headStack = ref(headStackFromSections(form.sections.head ?? []));
const bodyEndCode = ref(
    customCodeForSections(form.sections.body_end ?? [], 'custom_body_end_code'),
);

const translationForm = useForm({
    target_locale: '',
    use_ai: true,
});

const tabOptions = computed(() => [
    { value: 'basis', label: t('content_form.tabs.basic', 'Basis') },
    ...(props.canManageCodeBlocks
        ? [{ value: 'head', label: t('layouts.sections.head_tab', 'Head') }]
        : []),
    { value: 'header', label: t('layouts.sections.header_tab', 'Header') },
    ...(props.canManageCodeBlocks
        ? [
              {
                  value: 'body_end',
                  label: t('layouts.sections.body_end_tab', 'Body einde'),
              },
          ]
        : []),
    { value: 'footer', label: t('layouts.sections.footer_tab', 'Footer') },
]);

const pageTitle = computed(() =>
    props.layoutItem
        ? t('layouts.edit_title', 'Layout bewerken')
        : t('layouts.create_title', 'Layout toevoegen'),
);
const isEditMode = computed(() => Boolean(props.layoutItem?.id));
const recordIdLabel = computed(() => props.layoutItem?.id ?? '-');
const updatedAtLabel = computed(() =>
    formatRecordDate(props.layoutItem?.updated_at),
);
const createdAtLabel = computed(() =>
    formatRecordDate(props.layoutItem?.created_at),
);
const layoutEditorKey = computed(() => props.layoutItem?.id ?? 'new');
const isMultilingualEnabled = computed(() => props.multilingualEnabled);
const selectableLanguages = computed(() =>
    props.activeLanguages.length > 0
        ? props.activeLanguages
        : props.availableLocales.map((locale) => ({
              locale,
              name: locale,
              native_name: locale,
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
const scrollModeOptions = computed(() => [
    {
        value: 'browser',
        label: t('layouts.scroll_modes.browser', 'Normale browser-scroll'),
    },
    {
        value: 'internal',
        label: t('layouts.scroll_modes.internal', 'Interne content-scroll'),
    },
]);
const missingLanguages = computed(() =>
    props.missingLanguages.filter(
        (language) => language.locale !== form.locale,
    ),
);
const selectedTranslationLanguageLabel = computed(() => {
    const language = missingLanguages.value.find(
        (item) => item.locale === translationForm.target_locale,
    );

    return language
        ? languageLabel(language)
        : t('content_form.no_language_selected', 'Geen taal gekozen');
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

    if (!form.locale) {
        errors.locale = t('validation.required', 'This field is required.');
    }

    if (!form.settings?.scroll_mode) {
        errors['settings.scroll_mode'] = t(
            'validation.required',
            'This field is required.',
        );
    }

    appendSectionNameErrors(errors, 'header', form.sections?.header);
    appendSectionNameErrors(errors, 'footer', form.sections?.footer);

    return errors;
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

watch(
    () => props.layoutItem,
    () => {
        const nextData = layoutFormData();

        form.defaults(nextData);
        Object.assign(form, nextData);
        form.clearErrors();
        showSummary.value = false;
        touched.value = {};
        expandedHeadPreviewTypes.value = {};
        draggedHeadEntryKey.value = null;
        dragOverHeadEntryKey.value = null;
        headStack.value = headStackFromSections(nextData.sections.head ?? []);
        bodyEndCode.value = customCodeForSections(
            nextData.sections.body_end ?? [],
            'custom_body_end_code',
        );
    },
);

watch(
    () => props.colorPaletteItems,
    (items) => {
        localColorPaletteItems.value = [...items];
    },
);

watch(
    () => props.mediaOptions,
    (items) => {
        localMediaOptions.value = [...items];
    },
);

watch(
    () => props.mediaFolders,
    (folders) => {
        localMediaFolders.value = [...folders];
    },
);

function layoutFormData() {
    return {
        name: props.layoutItem?.name ?? '',
        locale:
            props.layoutItem?.locale ??
            props.activeLanguages[0]?.locale ??
            'nl',
        is_default: Boolean(props.layoutItem?.is_default ?? false),
        is_active: Boolean(props.layoutItem?.is_active ?? true),
        cache_strategy: layoutCacheStrategy(props.layoutItem?.cache_strategy),
        settings: {
            scroll_mode: 'browser',
            background: defaultBackgroundSettings(),
            ...(props.layoutItem?.settings ?? {}),
        },
        sections: props.layoutItem?.sections ?? {
            head: [],
            header: [],
            footer: [],
            body_end: [],
        },
    };
}

function defaultBackgroundSettings() {
    return {
        color: null,
        media_asset_id: null,
        mode: 'cover',
        position: 'center center',
        image_opacity: 100,
    };
}

function updateMediaOptions(items) {
    localMediaOptions.value = [...items];
}

function updateColorPaletteItems(items) {
    localColorPaletteItems.value = [...items];
}

function updateMediaFolders(folders) {
    localMediaFolders.value = [...folders];
}

function layoutCacheStrategy(value) {
    return ['none', 'block', 'layout'].includes(value) ? value : 'none';
}

function updateLayoutZoneSections(zone, sections) {
    form.sections = updatedLayoutSectionsForZone(form.sections, zone, sections);
}

function submit(returnTab = null, options = {}) {
    showSummary.value = true;
    touched.value = {
        name: true,
        locale: true,
        'settings.scroll_mode': true,
    };

    const clientErrorFields = Object.keys(clientErrors.value);

    if (clientErrorFields.length > 0) {
        if (
            clientErrorFields.some((field) =>
                field.startsWith('sections.header.'),
            )
        ) {
            activeTab.value = 'header';
        } else if (
            clientErrorFields.some((field) =>
                field.startsWith('sections.footer.'),
            )
        ) {
            activeTab.value = 'footer';
        } else {
            activeTab.value = 'basis';
        }

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

    form.transform((data) => {
        const sections = {
            ...(data.sections ?? {}),
        };

        if (props.canManageCodeBlocks) {
            sections.head = headStackSections();
            sections.body_end = customCodeSectionsFromCode(
                'body_end',
                'custom_body_end_code',
                bodyEndCode.value,
            );

            return {
                ...data,
                sections,
            };
        }

        return {
            ...data,
            sections: {
                header: sections.header ?? [],
                footer: sections.footer ?? [],
            },
        };
    }).post(
        route('admin.cms.layouts.store', {
            id: props.layoutItem?.id ?? 0,
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
            onFinish: () => form.transform((data) => data),
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

function handleZoneSectionDialogOpenChange({ zone, open }) {
    if (!open && sectionDialogSaveZone.value === zone) {
        clearSectionDialogFlashTarget();
    }
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
    return `cms-layout:${props.layoutItem?.id ?? 'new'}:return-tab`;
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
    const allowedTabs = ['basis', 'header', 'footer'];

    if (props.canManageCodeBlocks) {
        allowedTabs.push('head', 'body_end');
    }

    return allowedTabs.includes(value) ? value : null;
}

function touch(field) {
    touched.value = { ...touched.value, [field]: true };
}

function validationMessage(field) {
    if (!touched.value[field] && !showSummary.value) {
        return form.errors[field] || '';
    }

    return validationErrorMessages.value[field] || '';
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

function fieldLabel(field) {
    const sectionNameMatch = field.match(/^sections\.([^.]+)\.\d+\.name$/);

    if (sectionNameMatch) {
        const zoneLabels = {
            header: t('layouts.sections.header_tab', 'Header'),
            footer: t('layouts.sections.footer_tab', 'Footer'),
        };

        return `${zoneLabels[sectionNameMatch[1]] || sectionNameMatch[1]}: ${t('layouts.sections.section_name', 'Sectienaam')}`;
    }

    const labels = {
        name: t('layouts.name', 'Naam'),
        locale: t('common.columns.locale', 'Taal'),
        'settings.scroll_mode': t('layouts.scroll_mode', 'Scrollgedrag'),
        'sections.head': t('layouts.sections.head_tab', 'Head'),
    };

    return labels[field] || field;
}

function customCodeForSections(sections, blockType) {
    return (
        sections
            .flatMap((section) => section.placements ?? [])
            .find((placement) => placement.block?.type === blockType)?.block
            ?.code ?? ''
    );
}

function customCodeSectionsFromCode(zone, blockType, code) {
    const normalizedCode = String(code ?? '');
    const existingSection = customCodeSection(zone, blockType);
    const existingPlacement = customCodePlacement(zone, blockType);
    const placeableBlock = placeableBlockDefinitionByRenderer(blockType);

    if (normalizedCode.trim() === '') {
        return [];
    }

    return [
        {
            id: existingSection?.id ?? null,
            name:
                existingSection?.name ??
                (zone === 'head'
                    ? t('layouts.sections.head_tab', 'Head')
                    : t('layouts.sections.body_end_tab', 'Body einde')),
            is_active: true,
            visible_mobile: true,
            visible_tablet: true,
            visible_desktop: true,
            settings: {
                layout_type: 'standard',
                width_mode: 'content',
                spacing: 'none',
                background_color: null,
            },
            placements: [
                {
                    id: existingPlacement?.id ?? null,
                    is_active: true,
                    visible_mobile: true,
                    visible_tablet: true,
                    visible_desktop: true,
                    mobile_span: 12,
                    tablet_span: 12,
                    desktop_span: 12,
                    height_mode: 'auto',
                    height_value: null,
                    cache_strategy:
                        existingPlacement?.cache_strategy ?? 'inherit',
                    settings: {},
                    block: {
                        id: existingPlacement?.block?.id ?? null,
                        cms_placeable_block_id:
                            existingPlacement?.block?.cms_placeable_block_id ??
                            placeableBlock?.id ??
                            null,
                        placeable_block_revision_id:
                            existingPlacement?.block
                                ?.placeable_block_revision_id ??
                            placeableBlock?.latest_revision?.id ??
                            null,
                        type: blockType,
                        name: existingPlacement?.block?.name ?? null,
                        code: normalizedCode,
                        cache_strategy:
                            existingPlacement?.block?.cache_strategy ??
                            'inherit',
                    },
                },
            ],
        },
    ];
}

function customCodeSection(zone, blockType) {
    return (form.sections?.[zone] ?? []).find((section) =>
        (section.placements ?? []).some(
            (placement) => placement.block?.type === blockType,
        ),
    );
}

function customCodePlacement(zone, blockType) {
    return (form.sections?.[zone] ?? [])
        .flatMap((section) => section.placements ?? [])
        .find((placement) => placement.block?.type === blockType);
}

function headStackFromSections(sections) {
    const placements = sections.flatMap((section) =>
        (section.placements ?? []).map((placement) => ({
            section,
            placement,
        })),
    );
    const existingByType = new Map();
    const customEntries = [];

    placements.forEach(({ section, placement }) => {
        const type = placement.block?.type;

        if (lockedHeadTypes.includes(type)) {
            existingByType.set(type, { section, placement });
        }

        if (type === 'custom_head_code') {
            customEntries.push(headCustomEntry(section, placement));
        }
    });

    const orderedEntries = placements
        .map(({ section, placement }) => {
            const type = placement.block?.type;

            if (lockedHeadTypes.includes(type)) {
                return headLockedEntry(type, section, placement);
            }

            if (type === 'custom_head_code') {
                return headCustomEntry(section, placement);
            }

            return null;
        })
        .filter(Boolean);

    if (orderedEntries.some((entry) => entry.locked)) {
        return ensureLockedHeadEntries(orderedEntries, existingByType);
    }

    return ensureLockedHeadEntries(customEntries, existingByType);
}

function ensureLockedHeadEntries(entries, existingByType) {
    const result = [...entries];

    lockedHeadTypes.forEach((type, fallbackIndex) => {
        if (result.some((entry) => entry.type === type)) {
            return;
        }

        const existing = existingByType.get(type) ?? {};
        result.splice(
            Math.min(fallbackIndex, result.length),
            0,
            headLockedEntry(type, existing.section, existing.placement),
        );
    });

    return result;
}

function headLockedEntry(type, section = null, placement = null) {
    return {
        key: `locked-${type}`,
        type,
        locked: true,
        sectionId: section?.id ?? null,
        placementId: placement?.id ?? null,
        blockId: placement?.block?.id ?? null,
        placementCacheStrategy: placement?.cache_strategy ?? 'inherit',
        blockCacheStrategy: placement?.block?.cache_strategy ?? 'inherit',
    };
}

function headCustomEntry(section = null, placement = null) {
    const key = placement?.id
        ? `custom-${placement.id}`
        : `custom-new-${Date.now()}-${Math.random().toString(16).slice(2)}`;

    return {
        key,
        type: 'custom_head_code',
        locked: false,
        code: placement?.block?.code ?? '',
        sectionId: section?.id ?? null,
        placementId: placement?.id ?? null,
        blockId: placement?.block?.id ?? null,
        placementCacheStrategy: placement?.cache_strategy ?? 'inherit',
        blockCacheStrategy: placement?.block?.cache_strategy ?? 'inherit',
    };
}

function headEntryTitle(entry) {
    if (entry.type === 'custom_head_code') {
        const customIndex = headStack.value
            .filter((item) => item.type === 'custom_head_code')
            .findIndex((item) => item.key === entry.key);

        return t(
            'layouts.sections.head_snippet_title',
            'Custom snippet :number',
            {
                number: customIndex + 1,
            },
        );
    }

    return t(`layouts.sections.${entry.type}`, entry.type);
}

function addHeadSnippet() {
    headStack.value.push(headCustomEntry());
}

function removeHeadEntry(index) {
    if (headStack.value[index]?.locked) {
        return;
    }

    headStack.value.splice(index, 1);
}

function isHeadEntryMoveable(entry) {
    return Boolean(entry && !entry.locked);
}

function moveHeadEntry(index, direction) {
    if (!isHeadEntryMoveable(headStack.value[index])) {
        return;
    }

    const targetIndex = index + direction;

    if (targetIndex < 0 || targetIndex >= headStack.value.length) {
        return;
    }

    const entries = [...headStack.value];
    const [entry] = entries.splice(index, 1);
    entries.splice(targetIndex, 0, entry);
    headStack.value = entries;
}

function moveHeadEntryToTarget(sourceKey, targetKey) {
    if (!sourceKey || !targetKey || sourceKey === targetKey) {
        return;
    }

    const fromIndex = headStack.value.findIndex(
        (entry) => entry.key === sourceKey,
    );
    const toIndex = headStack.value.findIndex(
        (entry) => entry.key === targetKey,
    );

    if (fromIndex < 0 || toIndex < 0 || fromIndex === toIndex) {
        return;
    }

    if (!isHeadEntryMoveable(headStack.value[fromIndex])) {
        return;
    }

    const entries = [...headStack.value];
    const [entry] = entries.splice(fromIndex, 1);
    entries.splice(toIndex, 0, entry);
    headStack.value = entries;
}

function setHeadEntryDragPreview(event) {
    if (
        typeof document === 'undefined' ||
        !event?.dataTransfer ||
        !event?.currentTarget
    ) {
        return;
    }

    const source = event.currentTarget;
    const rowElement =
        source?.closest?.('[data-drag-preview-row="true"]') || source;

    if (!(rowElement instanceof HTMLElement)) {
        return;
    }

    const preview = rowElement.cloneNode(true);

    if (!(preview instanceof HTMLElement)) {
        return;
    }

    const rect = rowElement.getBoundingClientRect();

    preview.style.position = 'fixed';
    preview.style.top = '-9999px';
    preview.style.left = '-9999px';
    preview.style.width = `${Math.max(260, Math.round(rect.width))}px`;
    preview.style.pointerEvents = 'none';
    preview.style.background = '#ffffff';
    preview.style.border = '1px solid rgb(148 163 184)';
    preview.style.borderRadius = '12px';
    preview.style.boxShadow = '0 12px 28px rgba(15, 23, 42, 0.18)';
    preview.style.opacity = '0.96';
    preview.style.transform = 'none';
    preview.style.zIndex = '2147483647';

    document.body.appendChild(preview);

    try {
        event.dataTransfer.setDragImage(preview, 20, 16);
    } catch {
        preview.remove();
        return;
    }

    requestAnimationFrame(() => {
        preview.remove();
    });
}

function onHeadEntryDragStart(entryKey, event) {
    const entry = headStack.value.find((item) => item.key === entryKey);

    if (!isHeadEntryMoveable(entry)) {
        return;
    }

    draggedHeadEntryKey.value = entryKey;

    if (event?.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';

        try {
            event.dataTransfer.setData('text/plain', String(entryKey));
            setHeadEntryDragPreview(event);
        } catch {
            return;
        }
    }
}

function onHeadEntryDragOver(entryKey, event) {
    if (!draggedHeadEntryKey.value) {
        return;
    }

    dragOverHeadEntryKey.value = entryKey;

    if (event?.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }

    moveHeadEntryToTarget(draggedHeadEntryKey.value, entryKey);
}

function onHeadEntryDrop() {
    onHeadEntryDragEnd();
}

function onHeadEntryDragEnd() {
    draggedHeadEntryKey.value = null;
    dragOverHeadEntryKey.value = null;
}

function toggleHeadPreview(type) {
    expandedHeadPreviewTypes.value = {
        ...expandedHeadPreviewTypes.value,
        [type]: !expandedHeadPreviewTypes.value[type],
    };
}

function isHeadPreviewOpen(type) {
    return Boolean(expandedHeadPreviewTypes.value[type]);
}

function headSystemPreview(type) {
    return props.headSystemBlockPreviews?.[type] || '';
}

function placeableBlockDefinitionByRenderer(rendererKey) {
    return props.placeableBlocks.find(
        (block) =>
            block.renderer_key === rendererKey || block.key === rendererKey,
    );
}

function headStackSections() {
    const activeEntries = headStack.value.filter(
        (entry) => entry.locked || String(entry.code ?? '').trim() !== '',
    );

    if (activeEntries.length === 0) {
        return [];
    }

    return [
        {
            id:
                activeEntries.find((entry) => entry.sectionId)?.sectionId ??
                null,
            name: t('layouts.sections.head_tab', 'Head'),
            is_active: true,
            visible_mobile: true,
            visible_tablet: true,
            visible_desktop: true,
            settings: {
                layout_type: 'standard',
                width_mode: 'content',
                spacing: 'none',
                background_color: null,
            },
            placements: activeEntries.map((entry) => headPlacement(entry)),
        },
    ];
}

function headPlacement(entry) {
    const placeableBlock = placeableBlockDefinitionByRenderer(entry.type);

    return {
        id: entry.placementId ?? null,
        is_active: true,
        visible_mobile: true,
        visible_tablet: true,
        visible_desktop: true,
        mobile_span: 12,
        tablet_span: 12,
        desktop_span: 12,
        height_mode: 'auto',
        height_value: null,
        cache_strategy: entry.placementCacheStrategy ?? 'inherit',
        settings: {},
        block: {
            id: entry.blockId ?? null,
            cms_placeable_block_id: placeableBlock?.id ?? null,
            placeable_block_revision_id:
                placeableBlock?.latest_revision?.id ?? null,
            type: entry.type,
            name: null,
            code: entry.locked ? null : String(entry.code ?? ''),
            cache_strategy: entry.blockCacheStrategy ?? 'inherit',
        },
    };
}

function openTranslationDialog(locale = '') {
    if (!props.layoutItem?.id) {
        return;
    }

    translationForm.clearErrors();
    translationForm.target_locale =
        locale || missingLanguages.value[0]?.locale || '';
    translationForm.use_ai = true;
    showTranslationDialog.value = true;
}

function createTranslation(useAi) {
    if (!props.layoutItem?.id) {
        return;
    }

    translationForm.use_ai = useAi;
    translationForm.post(
        route('admin.cms.layouts.translations.store', {
            id: props.layoutItem.id,
        }),
        {
            preserveState: false,
        },
    );
}

function languageLabel(language) {
    const label = language.native_name || language.name || language.locale;

    return `${label} (${language.locale})`;
}

function handleTranslationChipClick(item) {
    if (item.type === 'translation' && item.id) {
        router.visit(route('admin.cms.layouts.edit', { id: item.id }));

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
        return t('content_form.create_translation', 'Vertaling maken');
    }

    return t('content_form.current', 'Huidig');
}

function translationStatusClass(item) {
    const currentClass = item.isCurrent
        ? ' ring-2 ring-blue-500 ring-offset-1'
        : '';

    if (item.status === 'success') {
        return `border-green-200 bg-green-50 text-green-800${currentClass}`;
    }

    if (item.status === 'warning') {
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
