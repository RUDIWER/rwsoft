<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogScrollContent :class="dialogContentClass">
            <DialogHeader
                class="shrink-0 border-b border-slate-200 px-6 pb-4 pr-12 pt-6"
            >
                <DialogTitle>
                    {{ settingsTitle }}
                </DialogTitle>
                <DialogDescription class="space-y-1">
                    <span
                        v-if="placementTitle"
                        class="block font-medium text-slate-700"
                    >
                        {{ placementTitle }}
                    </span>
                    <span class="block">
                        {{ settingsDescription }}
                    </span>
                </DialogDescription>
            </DialogHeader>

            <div
                v-if="dialogFlash.message"
                class="shrink-0 border-b border-slate-200 px-6 py-3"
            >
                <RwFlashMessage
                    :type="dialogFlash.type"
                    :message="dialogFlash.message"
                    :details="dialogFlash.details"
                />
            </div>

            <div
                v-if="placement"
                class="min-h-0 flex-1 overflow-y-auto px-6 py-5"
            >
                <div class="grid gap-4">
                    <FormValidationSummary
                        :visible="
                            showValidationSummary && validationErrors.length > 0
                        "
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
                    />

                    <div
                        v-if="placement.settings?.html_anchor"
                        class="grid max-w-md gap-1 rounded-md border border-slate-200 bg-slate-50 p-3 text-sm"
                    >
                        <Label :for="fieldId('html-anchor')">
                            {{
                                t(
                                    'components.block_editor.css_anchor',
                                    'CSS anchor',
                                )
                            }}
                        </Label>
                        <Input
                            :id="fieldId('html-anchor')"
                            :model-value="placement.settings.html_anchor"
                            readonly
                            class="bg-white font-mono text-xs"
                        />
                        <p class="text-xs text-slate-600">
                            {{
                                t(
                                    'components.block_editor.css_anchor_help',
                                    'Use this stable ID only for custom site-specific CSS. Platform and theme CSS should keep using classes and tokens.',
                                )
                            }}
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-4 border-b border-slate-200">
                        <button
                            v-for="tab in tabs"
                            :key="tab.value"
                            type="button"
                            :class="tabClasses(tab.value)"
                            @click="emit('update:activeTab', tab.value)"
                        >
                            {{ tab.label }}
                        </button>
                    </div>

                    <div v-if="activeTab === 'content'" class="grid gap-4">
                        <div
                            v-if="isPageOverrideEligible"
                            class="grid gap-4 rounded-lg border border-blue-100 bg-blue-50 p-4"
                        >
                            <div class="grid gap-1">
                                <h3
                                    class="text-sm font-semibold text-slate-900"
                                >
                                    {{
                                        t(
                                            'components.block_editor.page_override_settings',
                                            'Page override fields',
                                        )
                                    }}
                                </h3>
                                <p class="text-xs text-slate-600">
                                    {{
                                        t(
                                            'components.block_editor.page_override_settings_help',
                                            'When a key is set, this block can be filled on each page. Filled page values always override the values entered on this block.',
                                        )
                                    }}
                                </p>
                            </div>

                            <label
                                class="flex items-center gap-2 text-sm font-semibold text-slate-800"
                            >
                                <input
                                    v-model="placement.settings.page_editable"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                />
                                {{
                                    t(
                                        'components.block_editor.page_editable',
                                        'Editable on pages',
                                    )
                                }}
                            </label>

                            <div
                                v-if="placement.settings.page_editable"
                                class="grid gap-3 md:grid-cols-2"
                            >
                                <div class="grid gap-2">
                                    <Label :for="fieldId('content-key')">
                                        {{
                                            t(
                                                'components.block_editor.content_key',
                                                'Page data key',
                                            )
                                        }}
                                    </Label>
                                    <Input
                                        :id="fieldId('content-key')"
                                        v-model="placement.settings.content_key"
                                        :name="fieldName('content_key')"
                                        class="font-mono"
                                        :placeholder="
                                            t(
                                                'components.block_editor.content_key_placeholder',
                                                'feature_card',
                                            )
                                        "
                                        @blur="normalizePlacementContentKey"
                                    />
                                    <p class="text-xs text-slate-600">
                                        {{
                                            t(
                                                'components.block_editor.content_key_help',
                                                'Use lowercase letters, numbers and underscores. Example: feature_card.',
                                            )
                                        }}
                                    </p>
                                </div>

                                <div class="grid gap-2">
                                    <Label :for="fieldId('editor-label')">
                                        {{
                                            t(
                                                'components.block_editor.editor_label',
                                                'Page editor label',
                                            )
                                        }}
                                    </Label>
                                    <Input
                                        :id="fieldId('editor-label')"
                                        v-model="
                                            placement.settings.editor_label
                                        "
                                        :name="fieldName('editor_label')"
                                        :placeholder="placementTitle"
                                    />
                                    <p class="text-xs text-slate-600">
                                        {{
                                            t(
                                                'components.block_editor.editor_label_help',
                                                'This label groups the fields in the page editor.',
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div
                                v-if="placement.settings.page_editable"
                                class="grid gap-2"
                            >
                                <span
                                    class="text-xs font-semibold text-slate-700"
                                >
                                    {{
                                        t(
                                            'components.block_editor.page_editable_fields',
                                            'Editable fields',
                                        )
                                    }}
                                </span>
                                <div
                                    class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3"
                                >
                                    <label
                                        class="flex items-center gap-2 text-sm text-slate-700"
                                    >
                                        <input
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                            :checked="
                                                pageEditableMetaEnabled(
                                                    'is_active',
                                                )
                                            "
                                            @change="
                                                togglePageEditableMeta(
                                                    'is_active',
                                                    $event.target.checked,
                                                )
                                            "
                                        />
                                        {{
                                            t('common.columns.active', 'Active')
                                        }}
                                    </label>
                                    <label
                                        v-for="field in pageEditableContentFields"
                                        :key="`page-editable-${field.name}`"
                                        class="flex items-center gap-2 text-sm text-slate-700"
                                    >
                                        <input
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                            :checked="
                                                pageEditableFieldEnabled(
                                                    field.name,
                                                )
                                            "
                                            @change="
                                                togglePageEditableField(
                                                    field.name,
                                                    $event.target.checked,
                                                )
                                            "
                                        />
                                        {{ helpers.editorFieldLabel(field) }}
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="isLanguagePlacement"
                            class="grid gap-4 rounded-md border border-slate-200 bg-slate-50 p-3"
                        >
                            <div class="grid gap-1">
                                <h3
                                    class="text-sm font-semibold text-slate-900"
                                >
                                    {{
                                        t(
                                            'components.block_editor.language_content_group',
                                            'Language output',
                                        )
                                    }}
                                </h3>
                                <p class="text-xs text-slate-600">
                                    {{
                                        t(
                                            'components.block_editor.language_content_help',
                                            'Choose how each language is shown. Optional labels and icons are configured per device in the style tab.',
                                        )
                                    }}
                                </p>
                            </div>

                            <div class="grid gap-3 md:grid-cols-3">
                                <div class="grid gap-2 md:col-span-3">
                                    <Label
                                        :for="fieldId('language-label-display')"
                                    >
                                        {{
                                            t(
                                                'components.block_editor.language_label_display',
                                                'Language display',
                                            )
                                        }}
                                    </Label>
                                    <RwAutoCompleteInput
                                        :id="fieldId('language-label-display')"
                                        v-model="placement.block.label_display"
                                        :items="languageLabelDisplayOptions"
                                        item-title="label"
                                        item-value="value"
                                        :search-fields="['label', 'value']"
                                        :name="
                                            fieldName('language_label_display')
                                        "
                                    />
                                </div>

                                <label class="flex items-center gap-2 text-sm">
                                    <input
                                        v-model="placement.block.show_current"
                                        :name="
                                            fieldName('language_show_current')
                                        "
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-slate-300"
                                    />
                                    {{
                                        t(
                                            'components.block_editor.language_show_current',
                                            'Show current language',
                                        )
                                    }}
                                </label>

                                <label
                                    class="flex items-center gap-2 text-sm md:col-span-2"
                                >
                                    <input
                                        v-model="
                                            placement.block
                                                .hide_missing_translations
                                        "
                                        :name="
                                            fieldName(
                                                'language_hide_missing_translations',
                                            )
                                        "
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-slate-300"
                                    />
                                    {{
                                        t(
                                            'components.block_editor.language_hide_missing_translations',
                                            'Hide languages without a translation',
                                        )
                                    }}
                                </label>
                            </div>

                            <div class="grid gap-3 md:grid-cols-3">
                                <div class="grid gap-2">
                                    <Label
                                        :for="fieldId('language-flag-position')"
                                    >
                                        {{
                                            t(
                                                'components.block_editor.language_flag_position',
                                                'Flag position',
                                            )
                                        }}
                                    </Label>
                                    <RwAutoCompleteInput
                                        :id="fieldId('language-flag-position')"
                                        v-model="placement.block.flag_position"
                                        :items="languageFlagPositionOptions"
                                        item-title="label"
                                        item-value="value"
                                        :search-fields="['label', 'value']"
                                        :name="
                                            fieldName('language_flag_position')
                                        "
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label
                                        :for="fieldId('language-flag-shape')"
                                    >
                                        {{
                                            t(
                                                'components.block_editor.language_flag_shape',
                                                'Flag shape',
                                            )
                                        }}
                                    </Label>
                                    <RwAutoCompleteInput
                                        :id="fieldId('language-flag-shape')"
                                        v-model="placement.block.flag_shape"
                                        :items="languageFlagShapeOptions"
                                        item-title="label"
                                        item-value="value"
                                        :search-fields="['label', 'value']"
                                        :name="fieldName('language_flag_shape')"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label :for="fieldId('language-flag-size')">
                                        {{
                                            t(
                                                'components.block_editor.language_flag_size',
                                                'Flag size',
                                            )
                                        }}
                                    </Label>
                                    <RwAutoCompleteInput
                                        :id="fieldId('language-flag-size')"
                                        v-model="placement.block.flag_size"
                                        :items="languageFlagSizeOptions"
                                        item-title="label"
                                        item-value="value"
                                        :search-fields="['label', 'value']"
                                        :name="fieldName('language_flag_size')"
                                    />
                                </div>
                            </div>
                        </div>

                        <div
                            v-else-if="
                                helpers.isSystemBlock(placement.block) &&
                                !helpers.hasEditorFields(placement.block)
                            "
                            class="rounded-md border border-blue-100 bg-blue-50 p-3 text-sm text-blue-700"
                        >
                            {{
                                t(
                                    'layouts.sections.system_block_help',
                                    'Dit systeemblok gebruikt de bestaande site-instellingen en navigatie.',
                                )
                            }}
                        </div>

                        <div
                            v-else-if="isAddressBlock(placement.block)"
                            class="grid gap-4"
                        >
                            <div
                                class="grid gap-4 rounded-lg border border-slate-200 bg-white p-4"
                            >
                                <div class="grid gap-1">
                                    <h3
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        {{
                                            t(
                                                'components.block_editor.address_section_general',
                                                'General',
                                            )
                                        }}
                                    </h3>
                                    <p class="text-xs text-slate-500">
                                        {{
                                            t(
                                                'components.block_editor.address_section_general_help',
                                                'Set an optional title and the image for this address block.',
                                            )
                                        }}
                                    </p>
                                </div>

                                <div class="grid gap-3 md:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label :for="fieldId('block-title')">
                                            {{
                                                addressFieldLabel(
                                                    placement.block,
                                                    'title',
                                                )
                                            }}
                                        </Label>
                                        <Input
                                            :id="fieldId('block-title')"
                                            v-model="placement.block.title"
                                            :name="fieldName('block_title')"
                                            :placeholder="
                                                addressFieldPlaceholder(
                                                    placement.block,
                                                    'title',
                                                )
                                            "
                                        />
                                    </div>
                                </div>
                            </div>

                            <div
                                class="grid gap-4 rounded-lg border border-slate-200 bg-slate-50 p-4"
                            >
                                <div class="grid gap-1">
                                    <h3
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        {{
                                            t(
                                                'components.block_editor.address_section_image',
                                                'Image',
                                            )
                                        }}
                                    </h3>
                                    <p class="text-xs text-slate-500">
                                        {{
                                            t(
                                                'components.block_editor.address_section_image_help',
                                                'Choose a block-specific image and where it should appear.',
                                            )
                                        }}
                                    </p>
                                </div>

                                <div class="grid gap-3 md:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label
                                            :for="
                                                fieldId('block-media-asset-id')
                                            "
                                        >
                                            {{
                                                addressFieldLabel(
                                                    placement.block,
                                                    'media_asset_id',
                                                )
                                            }}
                                        </Label>
                                        <select
                                            :id="
                                                fieldId('block-media-asset-id')
                                            "
                                            v-model="
                                                placement.block.media_asset_id
                                            "
                                            :name="
                                                fieldName(
                                                    'block_media_asset_id',
                                                )
                                            "
                                            class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        >
                                            <option value="">
                                                {{
                                                    t(
                                                        'components.media_picker.choose',
                                                        'Choose image',
                                                    )
                                                }}
                                            </option>
                                            <option
                                                v-for="asset in mediaOptions"
                                                :key="asset.id"
                                                :value="asset.id"
                                            >
                                                {{
                                                    asset.original_filename ||
                                                    asset.filename
                                                }}
                                            </option>
                                        </select>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label
                                            :for="
                                                fieldId('block-image-position')
                                            "
                                        >
                                            {{
                                                addressFieldLabel(
                                                    placement.block,
                                                    'image_position',
                                                )
                                            }}
                                        </Label>
                                        <select
                                            :id="
                                                fieldId('block-image-position')
                                            "
                                            v-model="
                                                placement.block.image_position
                                            "
                                            :name="
                                                fieldName(
                                                    'block_image_position',
                                                )
                                            "
                                            class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        >
                                            <option
                                                v-for="option in addressFieldOptions(
                                                    placement.block,
                                                    'image_position',
                                                )"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{
                                                    helpers.editorFieldOptionLabel(
                                                        option,
                                                    )
                                                }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div
                                    class="grid gap-4 rounded-lg border border-slate-200 bg-white p-4"
                                >
                                    <div
                                        class="flex flex-wrap items-start justify-between gap-3"
                                    >
                                        <div class="grid gap-1">
                                            <h3
                                                class="text-sm font-semibold text-slate-900"
                                            >
                                                {{
                                                    t(
                                                        'components.block_editor.address_section_company',
                                                        'Company',
                                                    )
                                                }}
                                            </h3>
                                            <p class="text-xs text-slate-500">
                                                {{
                                                    t(
                                                        'components.block_editor.address_section_company_help',
                                                        'Control the organization name shown in this block.',
                                                    )
                                                }}
                                            </p>
                                        </div>
                                        <label
                                            class="flex items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700"
                                        >
                                            <input
                                                v-model="
                                                    placement.block
                                                        .show_company_name
                                                "
                                                :name="
                                                    fieldName(
                                                        'block_show_company_name',
                                                    )
                                                "
                                                type="checkbox"
                                                class="h-4 w-4 rounded border-slate-300"
                                            />
                                            {{
                                                addressFieldLabel(
                                                    placement.block,
                                                    'show_company_name',
                                                )
                                            }}
                                        </label>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label
                                            :for="fieldId('block-company-name')"
                                        >
                                            {{
                                                addressFieldLabel(
                                                    placement.block,
                                                    'company_name',
                                                )
                                            }}
                                        </Label>
                                        <Input
                                            :id="fieldId('block-company-name')"
                                            v-model="
                                                placement.block.company_name
                                            "
                                            :name="
                                                fieldName('block_company_name')
                                            "
                                            :placeholder="
                                                addressFieldPlaceholder(
                                                    placement.block,
                                                    'company_name',
                                                )
                                            "
                                        />
                                    </div>
                                </div>

                                <div
                                    class="grid gap-4 rounded-lg border border-slate-200 bg-white p-4"
                                >
                                    <div
                                        class="flex flex-wrap items-start justify-between gap-3"
                                    >
                                        <div class="grid gap-1">
                                            <h3
                                                class="text-sm font-semibold text-slate-900"
                                            >
                                                {{
                                                    t(
                                                        'components.block_editor.address_section_vat',
                                                        'VAT',
                                                    )
                                                }}
                                            </h3>
                                            <p class="text-xs text-slate-500">
                                                {{
                                                    t(
                                                        'components.block_editor.address_section_vat_help',
                                                        'Show or hide the VAT number.',
                                                    )
                                                }}
                                            </p>
                                        </div>
                                        <label
                                            class="flex items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700"
                                        >
                                            <input
                                                v-model="
                                                    placement.block
                                                        .show_vat_number
                                                "
                                                :name="
                                                    fieldName(
                                                        'block_show_vat_number',
                                                    )
                                                "
                                                type="checkbox"
                                                class="h-4 w-4 rounded border-slate-300"
                                            />
                                            {{
                                                addressFieldLabel(
                                                    placement.block,
                                                    'show_vat_number',
                                                )
                                            }}
                                        </label>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label
                                            :for="fieldId('block-vat-number')"
                                        >
                                            {{
                                                addressFieldLabel(
                                                    placement.block,
                                                    'vat_number',
                                                )
                                            }}
                                        </Label>
                                        <Input
                                            :id="fieldId('block-vat-number')"
                                            v-model="placement.block.vat_number"
                                            :name="
                                                fieldName('block_vat_number')
                                            "
                                            :placeholder="
                                                addressFieldPlaceholder(
                                                    placement.block,
                                                    'vat_number',
                                                )
                                            "
                                        />
                                    </div>
                                </div>
                            </div>

                            <div
                                class="grid gap-4 rounded-lg border border-slate-200 bg-white p-4"
                            >
                                <div
                                    class="flex flex-wrap items-start justify-between gap-3"
                                >
                                    <div class="grid gap-1">
                                        <h3
                                            class="text-sm font-semibold text-slate-900"
                                        >
                                            {{
                                                t(
                                                    'components.block_editor.address_section_address',
                                                    'Address',
                                                )
                                            }}
                                        </h3>
                                        <p class="text-xs text-slate-500">
                                            {{
                                                t(
                                                    'components.block_editor.address_section_address_help',
                                                    'Manage the address fields and decide whether the address is shown.',
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <label
                                        class="flex items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700"
                                    >
                                        <input
                                            v-model="
                                                placement.block.show_address
                                            "
                                            :name="
                                                fieldName('block_show_address')
                                            "
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-slate-300"
                                        />
                                        {{
                                            addressFieldLabel(
                                                placement.block,
                                                'show_address',
                                            )
                                        }}
                                    </label>
                                </div>

                                <div class="grid gap-3 md:grid-cols-6">
                                    <div class="grid gap-2 md:col-span-3">
                                        <Label :for="fieldId('block-street')">
                                            {{
                                                addressFieldLabel(
                                                    placement.block,
                                                    'street',
                                                )
                                            }}
                                        </Label>
                                        <Input
                                            :id="fieldId('block-street')"
                                            v-model="placement.block.street"
                                            :name="fieldName('block_street')"
                                            :placeholder="
                                                addressFieldPlaceholder(
                                                    placement.block,
                                                    'street',
                                                )
                                            "
                                        />
                                    </div>
                                    <div class="grid gap-2 md:col-span-1">
                                        <Label
                                            :for="fieldId('block-postal-code')"
                                        >
                                            {{
                                                addressFieldLabel(
                                                    placement.block,
                                                    'postal_code',
                                                )
                                            }}
                                        </Label>
                                        <Input
                                            :id="fieldId('block-postal-code')"
                                            v-model="
                                                placement.block.postal_code
                                            "
                                            :name="
                                                fieldName('block_postal_code')
                                            "
                                            :placeholder="
                                                addressFieldPlaceholder(
                                                    placement.block,
                                                    'postal_code',
                                                )
                                            "
                                        />
                                    </div>
                                    <div class="grid gap-2 md:col-span-2">
                                        <Label :for="fieldId('block-city')">
                                            {{
                                                addressFieldLabel(
                                                    placement.block,
                                                    'city',
                                                )
                                            }}
                                        </Label>
                                        <Input
                                            :id="fieldId('block-city')"
                                            v-model="placement.block.city"
                                            :name="fieldName('block_city')"
                                            :placeholder="
                                                addressFieldPlaceholder(
                                                    placement.block,
                                                    'city',
                                                )
                                            "
                                        />
                                    </div>
                                    <div class="grid gap-2 md:col-span-4">
                                        <Label :for="fieldId('block-country')">
                                            {{
                                                addressFieldLabel(
                                                    placement.block,
                                                    'country',
                                                )
                                            }}
                                        </Label>
                                        <Input
                                            :id="fieldId('block-country')"
                                            v-model="placement.block.country"
                                            :name="fieldName('block_country')"
                                            :placeholder="
                                                addressFieldPlaceholder(
                                                    placement.block,
                                                    'country',
                                                )
                                            "
                                        />
                                    </div>
                                    <div class="grid gap-2 md:col-span-2">
                                        <Label
                                            :for="fieldId('block-country-code')"
                                        >
                                            {{
                                                addressFieldLabel(
                                                    placement.block,
                                                    'country_code',
                                                )
                                            }}
                                        </Label>
                                        <Input
                                            :id="fieldId('block-country-code')"
                                            v-model="
                                                placement.block.country_code
                                            "
                                            :name="
                                                fieldName('block_country_code')
                                            "
                                            :placeholder="
                                                addressFieldPlaceholder(
                                                    placement.block,
                                                    'country_code',
                                                )
                                            "
                                        />
                                    </div>
                                </div>
                            </div>

                            <div
                                class="grid gap-4 rounded-lg border border-slate-200 bg-white p-4"
                            >
                                <div
                                    class="flex flex-wrap items-start justify-between gap-3"
                                >
                                    <div class="grid gap-1">
                                        <h3
                                            class="text-sm font-semibold text-slate-900"
                                        >
                                            {{
                                                t(
                                                    'components.block_editor.address_section_phones',
                                                    'Phone numbers',
                                                )
                                            }}
                                        </h3>
                                        <p class="text-xs text-slate-500">
                                            {{
                                                t(
                                                    'components.block_editor.address_section_phones_help',
                                                    'Set labels and values for up to three phone numbers.',
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <label
                                        class="flex items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700"
                                    >
                                        <input
                                            v-model="
                                                placement.block.show_phones
                                            "
                                            :name="
                                                fieldName('block_show_phones')
                                            "
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-slate-300"
                                        />
                                        {{
                                            addressFieldLabel(
                                                placement.block,
                                                'show_phones',
                                            )
                                        }}
                                    </label>
                                </div>

                                <div class="grid gap-3">
                                    <div
                                        v-for="row in addressPhoneRows"
                                        :key="row.value"
                                        class="grid gap-3 rounded-md border border-slate-100 bg-slate-50 p-3 md:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]"
                                    >
                                        <div class="grid gap-2">
                                            <Label
                                                :for="
                                                    fieldId(
                                                        `block-${row.label}`,
                                                    )
                                                "
                                            >
                                                {{
                                                    addressFieldLabel(
                                                        placement.block,
                                                        row.label,
                                                    )
                                                }}
                                            </Label>
                                            <Input
                                                :id="
                                                    fieldId(
                                                        `block-${row.label}`,
                                                    )
                                                "
                                                v-model="
                                                    placement.block[row.label]
                                                "
                                                :name="
                                                    fieldName(
                                                        `block_${row.label}`,
                                                    )
                                                "
                                                :placeholder="
                                                    addressFieldPlaceholder(
                                                        placement.block,
                                                        row.label,
                                                    )
                                                "
                                            />
                                        </div>
                                        <div class="grid gap-2">
                                            <Label
                                                :for="
                                                    fieldId(
                                                        `block-${row.value}`,
                                                    )
                                                "
                                            >
                                                {{
                                                    addressFieldLabel(
                                                        placement.block,
                                                        row.value,
                                                    )
                                                }}
                                            </Label>
                                            <Input
                                                :id="
                                                    fieldId(
                                                        `block-${row.value}`,
                                                    )
                                                "
                                                v-model="
                                                    placement.block[row.value]
                                                "
                                                :name="
                                                    fieldName(
                                                        `block_${row.value}`,
                                                    )
                                                "
                                                type="tel"
                                                :placeholder="
                                                    addressFieldPlaceholder(
                                                        placement.block,
                                                        row.value,
                                                    )
                                                "
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="grid gap-4 rounded-lg border border-slate-200 bg-white p-4"
                            >
                                <div
                                    class="flex flex-wrap items-start justify-between gap-3"
                                >
                                    <div class="grid gap-1">
                                        <h3
                                            class="text-sm font-semibold text-slate-900"
                                        >
                                            {{
                                                t(
                                                    'components.block_editor.address_section_emails',
                                                    'Email addresses',
                                                )
                                            }}
                                        </h3>
                                        <p class="text-xs text-slate-500">
                                            {{
                                                t(
                                                    'components.block_editor.address_section_emails_help',
                                                    'Set labels and values for up to two email addresses.',
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <label
                                        class="flex items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700"
                                    >
                                        <input
                                            v-model="
                                                placement.block.show_emails
                                            "
                                            :name="
                                                fieldName('block_show_emails')
                                            "
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-slate-300"
                                        />
                                        {{
                                            addressFieldLabel(
                                                placement.block,
                                                'show_emails',
                                            )
                                        }}
                                    </label>
                                </div>

                                <div class="grid gap-3">
                                    <div
                                        v-for="row in addressEmailRows"
                                        :key="row.value"
                                        class="grid gap-3 rounded-md border border-slate-100 bg-slate-50 p-3 md:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]"
                                    >
                                        <div class="grid gap-2">
                                            <Label
                                                :for="
                                                    fieldId(
                                                        `block-${row.label}`,
                                                    )
                                                "
                                            >
                                                {{
                                                    addressFieldLabel(
                                                        placement.block,
                                                        row.label,
                                                    )
                                                }}
                                            </Label>
                                            <Input
                                                :id="
                                                    fieldId(
                                                        `block-${row.label}`,
                                                    )
                                                "
                                                v-model="
                                                    placement.block[row.label]
                                                "
                                                :name="
                                                    fieldName(
                                                        `block_${row.label}`,
                                                    )
                                                "
                                                :placeholder="
                                                    addressFieldPlaceholder(
                                                        placement.block,
                                                        row.label,
                                                    )
                                                "
                                            />
                                        </div>
                                        <div class="grid gap-2">
                                            <Label
                                                :for="
                                                    fieldId(
                                                        `block-${row.value}`,
                                                    )
                                                "
                                            >
                                                {{
                                                    addressFieldLabel(
                                                        placement.block,
                                                        row.value,
                                                    )
                                                }}
                                            </Label>
                                            <Input
                                                :id="
                                                    fieldId(
                                                        `block-${row.value}`,
                                                    )
                                                "
                                                v-model="
                                                    placement.block[row.value]
                                                "
                                                :name="
                                                    fieldName(
                                                        `block_${row.value}`,
                                                    )
                                                "
                                                type="email"
                                                :placeholder="
                                                    addressFieldPlaceholder(
                                                        placement.block,
                                                        row.value,
                                                    )
                                                "
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="grid gap-4 rounded-lg border border-slate-200 bg-white p-4"
                            >
                                <div
                                    class="flex flex-wrap items-start justify-between gap-3"
                                >
                                    <div class="grid gap-1">
                                        <h3
                                            class="text-sm font-semibold text-slate-900"
                                        >
                                            {{
                                                t(
                                                    'components.block_editor.address_section_custom_fields',
                                                    'Custom fields',
                                                )
                                            }}
                                        </h3>
                                        <p class="text-xs text-slate-500">
                                            {{
                                                t(
                                                    'components.block_editor.address_section_custom_fields_help',
                                                    'Add optional extra rows such as opening hours or registration details.',
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <label
                                        class="flex items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700"
                                    >
                                        <input
                                            v-model="
                                                placement.block
                                                    .show_custom_fields
                                            "
                                            :name="
                                                fieldName(
                                                    'block_show_custom_fields',
                                                )
                                            "
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-slate-300"
                                        />
                                        {{
                                            addressFieldLabel(
                                                placement.block,
                                                'show_custom_fields',
                                            )
                                        }}
                                    </label>
                                </div>

                                <div class="grid gap-3">
                                    <div
                                        v-for="row in addressCustomFieldRows"
                                        :key="row.value"
                                        class="grid gap-3 rounded-md border border-slate-100 bg-slate-50 p-3 md:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]"
                                    >
                                        <div class="grid gap-2">
                                            <Label
                                                :for="
                                                    fieldId(
                                                        `block-${row.label}`,
                                                    )
                                                "
                                            >
                                                {{
                                                    addressFieldLabel(
                                                        placement.block,
                                                        row.label,
                                                    )
                                                }}
                                            </Label>
                                            <Input
                                                :id="
                                                    fieldId(
                                                        `block-${row.label}`,
                                                    )
                                                "
                                                v-model="
                                                    placement.block[row.label]
                                                "
                                                :name="
                                                    fieldName(
                                                        `block_${row.label}`,
                                                    )
                                                "
                                            />
                                        </div>
                                        <div class="grid gap-2">
                                            <Label
                                                :for="
                                                    fieldId(
                                                        `block-${row.value}`,
                                                    )
                                                "
                                            >
                                                {{
                                                    addressFieldLabel(
                                                        placement.block,
                                                        row.value,
                                                    )
                                                }}
                                            </Label>
                                            <Input
                                                :id="
                                                    fieldId(
                                                        `block-${row.value}`,
                                                    )
                                                "
                                                v-model="
                                                    placement.block[row.value]
                                                "
                                                :name="
                                                    fieldName(
                                                        `block_${row.value}`,
                                                    )
                                                "
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            v-else-if="helpers.hasEditorFields(placement.block)"
                            :class="
                                helpers.blockEditorGridClasses(placement.block)
                            "
                        >
                            <p
                                v-if="
                                    helpers.hasCodeEditorField(placement.block)
                                "
                                class="text-sm text-orange-800 md:col-span-2"
                            >
                                {{
                                    t(
                                        'layouts.sections.custom_code_warning',
                                        'Deze code wordt ongeescaped in de publieke site geplaatst. Gebruik dit alleen voor vertrouwde snippets.',
                                    )
                                }}
                            </p>

                            <template
                                v-for="field in contentEditorFields(
                                    placement.block,
                                )"
                                :key="field.name"
                            >
                                <label
                                    v-if="field.type === 'checkbox'"
                                    class="flex items-center gap-2 text-sm"
                                >
                                    <input
                                        :id="fieldId(`block-${field.name}`)"
                                        v-model="placement.block[field.name]"
                                        :name="fieldName(`block_${field.name}`)"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-slate-300"
                                    />
                                    <span
                                        v-if="isRequiredField(field)"
                                        class="text-red-600"
                                        aria-hidden="true"
                                        >*</span
                                    >
                                    {{ helpers.editorFieldLabel(field) }}
                                </label>

                                <CmsRepeaterFieldEditor
                                    v-else-if="field.type === 'repeater'"
                                    :field="field"
                                    :items="
                                        helpers.repeaterItems(
                                            placement.block,
                                            field,
                                        )
                                    "
                                    :label="helpers.editorFieldLabel(field)"
                                    :item-preview-title="
                                        (item) =>
                                            helpers.repeaterItemPreviewTitle(
                                                item,
                                                field,
                                            )
                                    "
                                    @add="
                                        helpers.addRepeaterItem(
                                            placement.block,
                                            field,
                                        )
                                    "
                                    @update:items="
                                        placement.block[field.name] = $event
                                    "
                                >
                                    <template #default="{ item, childField }">
                                        <div class="grid gap-2">
                                            <Label>
                                                {{
                                                    helpers.editorFieldLabel(
                                                        childField,
                                                    )
                                                }}
                                            </Label>
                                            <textarea
                                                v-if="
                                                    childField.type ===
                                                    'textarea'
                                                "
                                                v-model="item[childField.name]"
                                                :rows="childField.rows || 3"
                                                class="min-h-20 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                                :placeholder="
                                                    helpers.editorFieldPlaceholder(
                                                        childField,
                                                    )
                                                "
                                            ></textarea>
                                            <Input
                                                v-else
                                                v-model="item[childField.name]"
                                                :type="
                                                    childField.type || 'text'
                                                "
                                                :placeholder="
                                                    helpers.editorFieldPlaceholder(
                                                        childField,
                                                    )
                                                "
                                            />
                                        </div>
                                    </template>
                                </CmsRepeaterFieldEditor>

                                <div
                                    v-else-if="field.type === 'media_select'"
                                    :class="
                                        helpers.editorFieldWrapperClasses(field)
                                    "
                                >
                                    <Label
                                        :for="fieldId(`block-${field.name}`)"
                                        class="flex items-center gap-1"
                                    >
                                        <span
                                            v-if="isRequiredField(field)"
                                            class="text-red-600"
                                            aria-hidden="true"
                                            >*</span
                                        >
                                        {{ helpers.editorFieldLabel(field) }}
                                    </Label>
                                    <CmsMediaPicker
                                        :model-value="
                                            placement.block[field.name]
                                        "
                                        :assets="mediaOptions"
                                        :folders="mediaFolders"
                                        uploaded-from="placement_media_field"
                                        @update:model-value="
                                            placement.block[field.name] = $event
                                        "
                                        @update:assets="updateMediaOptions"
                                        @update:folders="updateMediaFolders"
                                    />
                                </div>

                                <div
                                    v-else-if="field.type === 'media_list'"
                                    :class="
                                        helpers.editorFieldWrapperClasses(field)
                                    "
                                >
                                    <Label
                                        :for="fieldId(`block-${field.name}`)"
                                        class="flex items-center gap-1"
                                    >
                                        <span
                                            v-if="isRequiredField(field)"
                                            class="text-red-600"
                                            aria-hidden="true"
                                            >*</span
                                        >
                                        {{ helpers.editorFieldLabel(field) }}
                                    </Label>
                                    <select
                                        :id="fieldId(`block-${field.name}`)"
                                        value=""
                                        :name="fieldName(`block_${field.name}`)"
                                        :class="editorSelectClasses(field)"
                                        @change="
                                            helpers.addMediaListItem(
                                                placement.block,
                                                field,
                                                $event,
                                            )
                                        "
                                    >
                                        <option value="">
                                            {{
                                                t(
                                                    'components.block_editor.add_media_item',
                                                    'Media toevoegen',
                                                )
                                            }}
                                        </option>
                                        <option
                                            v-for="asset in helpers.availableMediaListOptions(
                                                placement.block,
                                                field,
                                            )"
                                            :key="asset.id"
                                            :value="asset.id"
                                        >
                                            {{
                                                asset.original_filename ||
                                                asset.filename
                                            }}
                                        </option>
                                    </select>

                                    <div
                                        v-if="
                                            helpers.mediaListItems(
                                                placement.block,
                                                field,
                                            ).length > 0
                                        "
                                        class="grid gap-2"
                                    >
                                        <div
                                            v-for="asset in helpers.mediaListItems(
                                                placement.block,
                                                field,
                                            )"
                                            :key="asset.id"
                                            class="flex items-center justify-between gap-3 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm"
                                        >
                                            <span class="truncate">
                                                {{
                                                    asset.original_filename ||
                                                    asset.filename
                                                }}
                                            </span>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                @click="
                                                    helpers.removeMediaListItem(
                                                        placement.block,
                                                        field,
                                                        asset.id,
                                                    )
                                                "
                                            >
                                                {{
                                                    t(
                                                        'components.block_editor.delete',
                                                        'Verwijderen',
                                                    )
                                                }}
                                            </Button>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-else-if="field.type === 'download_select'"
                                    :class="
                                        helpers.editorFieldWrapperClasses(field)
                                    "
                                >
                                    <Label
                                        :for="fieldId(`block-${field.name}`)"
                                        class="flex items-center gap-1"
                                    >
                                        <span
                                            v-if="isRequiredField(field)"
                                            class="text-red-600"
                                            aria-hidden="true"
                                            >*</span
                                        >
                                        {{ helpers.editorFieldLabel(field) }}
                                    </Label>
                                    <RwAutoCompleteInput
                                        :id="fieldId(`block-${field.name}`)"
                                        v-model="placement.block[field.name]"
                                        :items="downloadOptions"
                                        item-title="title"
                                        item-value="id"
                                        :search-fields="[
                                            'title',
                                            'filename',
                                            'original_filename',
                                        ]"
                                        :placeholder="
                                            t(
                                                'components.block_editor.choose_download',
                                                'Choose a download',
                                            )
                                        "
                                        :required="isRequiredField(field)"
                                        :required-missing="
                                            isRequiredField(field) &&
                                            !placement.block[field.name]
                                        "
                                    />
                                </div>

                                <div
                                    v-else-if="field.type === 'download_list'"
                                    :class="
                                        helpers.editorFieldWrapperClasses(field)
                                    "
                                >
                                    <Label
                                        :for="fieldId(`block-${field.name}`)"
                                        class="flex items-center gap-1"
                                    >
                                        <span
                                            v-if="isRequiredField(field)"
                                            class="text-red-600"
                                            aria-hidden="true"
                                            >*</span
                                        >
                                        {{ helpers.editorFieldLabel(field) }}
                                    </Label>
                                    <RwAutoCompleteInput
                                        :id="fieldId(`block-${field.name}`)"
                                        v-model="placement.block[field.name]"
                                        :items="downloadOptions"
                                        item-title="title"
                                        item-value="id"
                                        :search-fields="[
                                            'title',
                                            'filename',
                                            'original_filename',
                                        ]"
                                        multiple
                                        :selection-chips="true"
                                        :required="isRequiredField(field)"
                                        :required-missing="
                                            isRequiredField(field) &&
                                            !placement.block[field.name]?.length
                                        "
                                    />
                                </div>

                                <div
                                    v-else-if="
                                        field.type === 'download_folder_select'
                                    "
                                    :class="
                                        helpers.editorFieldWrapperClasses(field)
                                    "
                                >
                                    <Label
                                        :for="fieldId(`block-${field.name}`)"
                                        class="flex items-center gap-1"
                                    >
                                        <span
                                            v-if="isRequiredField(field)"
                                            class="text-red-600"
                                            aria-hidden="true"
                                            >*</span
                                        >
                                        {{ helpers.editorFieldLabel(field) }}
                                    </Label>
                                    <RwAutoCompleteInput
                                        :id="fieldId(`block-${field.name}`)"
                                        v-model="placement.block[field.name]"
                                        :items="downloadFolders"
                                        item-title="name"
                                        item-value="id"
                                        :search-fields="['name']"
                                        :placeholder="
                                            t(
                                                'components.block_editor.choose_download_folder',
                                                'Choose a download folder',
                                            )
                                        "
                                        :required="isRequiredField(field)"
                                        :required-missing="
                                            isRequiredField(field) &&
                                            !placement.block[field.name]
                                        "
                                    />
                                </div>

                                <div
                                    v-else-if="
                                        field.type === 'download_folder_list'
                                    "
                                    :class="
                                        helpers.editorFieldWrapperClasses(field)
                                    "
                                >
                                    <Label
                                        :for="fieldId(`block-${field.name}`)"
                                        class="flex items-center gap-1"
                                    >
                                        <span
                                            v-if="isRequiredField(field)"
                                            class="text-red-600"
                                            aria-hidden="true"
                                            >*</span
                                        >
                                        {{ helpers.editorFieldLabel(field) }}
                                    </Label>
                                    <RwAutoCompleteInput
                                        :id="fieldId(`block-${field.name}`)"
                                        v-model="placement.block[field.name]"
                                        :items="downloadFolders"
                                        item-title="name"
                                        item-value="id"
                                        :search-fields="['name']"
                                        multiple
                                        :selection-chips="true"
                                        :required="isRequiredField(field)"
                                        :required-missing="
                                            isRequiredField(field) &&
                                            !placement.block[field.name]?.length
                                        "
                                    />
                                </div>

                                <div
                                    v-else-if="field.type === 'menu_select'"
                                    :class="
                                        helpers.editorFieldWrapperClasses(field)
                                    "
                                >
                                    <Label
                                        :for="fieldId(`block-${field.name}`)"
                                        class="flex items-center gap-1"
                                    >
                                        <span
                                            v-if="isRequiredField(field)"
                                            class="text-red-600"
                                            aria-hidden="true"
                                            >*</span
                                        >
                                        {{ helpers.editorFieldLabel(field) }}
                                    </Label>
                                    <RwAutoCompleteInput
                                        :id="fieldId(`block-${field.name}`)"
                                        v-model="placement.block[field.name]"
                                        :items="filteredMenuOptions"
                                        item-title="title"
                                        item-value="id"
                                        :search-fields="['title']"
                                        :placeholder="
                                            t(
                                                'components.block_editor.choose_menu',
                                                'Choose a menu',
                                            )
                                        "
                                        :required="isRequiredField(field)"
                                        :required-missing="
                                            isRequiredField(field) &&
                                            !placement.block[field.name]
                                        "
                                    />
                                    <p
                                        v-if="filteredMenuOptions.length === 0"
                                        class="text-xs text-orange-700"
                                    >
                                        {{
                                            t(
                                                'components.block_editor.no_menus_for_place',
                                                'No active menus are available for this place.',
                                            )
                                        }}
                                    </p>
                                </div>

                                <div
                                    v-else-if="field.type === 'form_select'"
                                    :class="
                                        helpers.editorFieldWrapperClasses(field)
                                    "
                                >
                                    <Label
                                        :for="fieldId(`block-${field.name}`)"
                                        class="flex items-center gap-1"
                                    >
                                        <span
                                            v-if="isRequiredField(field)"
                                            class="text-red-600"
                                            aria-hidden="true"
                                            >*</span
                                        >
                                        {{ helpers.editorFieldLabel(field) }}
                                    </Label>
                                    <select
                                        :id="fieldId(`block-${field.name}`)"
                                        v-model="placement.block[field.name]"
                                        :name="fieldName(`block_${field.name}`)"
                                        :class="editorSelectClasses(field)"
                                    >
                                        <option value="">
                                            {{
                                                t(
                                                    'components.block_editor.choose_form',
                                                    'Kies een formulier',
                                                )
                                            }}
                                        </option>
                                        <option
                                            v-for="formItem in formOptions"
                                            :key="`${formItem.translation_key}-${formItem.locale}`"
                                            :value="formItem.translation_key"
                                        >
                                            {{ formItem.title }} ({{
                                                formItem.locale
                                            }})
                                        </option>
                                    </select>
                                </div>

                                <div
                                    v-else-if="field.type === 'rich_text'"
                                    class="grid gap-2 md:col-span-2"
                                >
                                    <Label
                                        :for="fieldId(`block-${field.name}`)"
                                        class="flex items-center gap-1"
                                    >
                                        <span
                                            v-if="isRequiredField(field)"
                                            class="text-red-600"
                                            aria-hidden="true"
                                            >*</span
                                        >
                                        {{ helpers.editorFieldLabel(field) }}
                                    </Label>
                                    <CmsRichTextEditor
                                        :model-value="
                                            placement.block[field.name]
                                        "
                                        :media-options="mediaOptions"
                                        :media-folders="mediaFolders"
                                        :placeholder="
                                            helpers.editorFieldPlaceholder(
                                                field,
                                            )
                                        "
                                        :required="isRequiredField(field)"
                                        @update:model-value="
                                            placement.block[field.name] = $event
                                        "
                                        @update:media-options="
                                            updateMediaOptions
                                        "
                                        @update:media-folders="
                                            updateMediaFolders
                                        "
                                    />
                                </div>

                                <div
                                    v-else-if="field.type === 'markdown'"
                                    class="grid gap-2 md:col-span-2"
                                >
                                    <Label
                                        :for="fieldId(`block-${field.name}`)"
                                        class="flex items-center gap-1"
                                    >
                                        <span
                                            v-if="isRequiredField(field)"
                                            class="text-red-600"
                                            aria-hidden="true"
                                            >*</span
                                        >
                                        {{ helpers.editorFieldLabel(field) }}
                                    </Label>
                                    <RwCodeEditor
                                        :model-value="
                                            placement.block[field.name]
                                        "
                                        language="markdown"
                                        theme="graphite"
                                        height="260px"
                                        :line-wrapping="true"
                                        :placeholder="
                                            helpers.editorFieldPlaceholder(
                                                field,
                                            )
                                        "
                                        @update:model-value="
                                            placement.block[field.name] = $event
                                        "
                                    />
                                </div>

                                <div
                                    v-else-if="
                                        ['textarea', 'code'].includes(
                                            field.type,
                                        )
                                    "
                                    class="grid gap-2 md:col-span-2"
                                >
                                    <Label
                                        :for="fieldId(`block-${field.name}`)"
                                        class="flex items-center gap-1"
                                    >
                                        <span
                                            v-if="isRequiredField(field)"
                                            class="text-red-600"
                                            aria-hidden="true"
                                            >*</span
                                        >
                                        {{ helpers.editorFieldLabel(field) }}
                                    </Label>
                                    <textarea
                                        :id="fieldId(`block-${field.name}`)"
                                        v-model="placement.block[field.name]"
                                        :name="fieldName(`block_${field.name}`)"
                                        :rows="field.rows || 4"
                                        :class="[
                                            helpers.editorTextareaClasses(
                                                field,
                                            ),
                                            requiredFieldControlClass(field),
                                        ]"
                                        :placeholder="
                                            helpers.editorFieldPlaceholder(
                                                field,
                                            )
                                        "
                                    ></textarea>
                                </div>

                                <div
                                    v-else-if="field.type === 'select'"
                                    class="grid gap-2"
                                >
                                    <Label
                                        :for="fieldId(`block-${field.name}`)"
                                        class="flex items-center gap-1"
                                    >
                                        <span
                                            v-if="isRequiredField(field)"
                                            class="text-red-600"
                                            aria-hidden="true"
                                            >*</span
                                        >
                                        {{ helpers.editorFieldLabel(field) }}
                                    </Label>
                                    <select
                                        :id="fieldId(`block-${field.name}`)"
                                        v-model="placement.block[field.name]"
                                        :name="fieldName(`block_${field.name}`)"
                                        :class="editorSelectClasses(field)"
                                    >
                                        <option
                                            v-for="option in field.options ||
                                            []"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{
                                                helpers.editorFieldOptionLabel(
                                                    option,
                                                )
                                            }}
                                        </option>
                                    </select>
                                </div>

                                <div
                                    v-else-if="field.type === 'number'"
                                    class="grid gap-2"
                                >
                                    <Label
                                        :for="fieldId(`block-${field.name}`)"
                                        class="flex items-center gap-1"
                                    >
                                        <span
                                            v-if="isRequiredField(field)"
                                            class="text-red-600"
                                            aria-hidden="true"
                                            >*</span
                                        >
                                        {{ helpers.editorFieldLabel(field) }}
                                    </Label>
                                    <Input
                                        :id="fieldId(`block-${field.name}`)"
                                        v-model.number="
                                            placement.block[field.name]
                                        "
                                        :name="fieldName(`block_${field.name}`)"
                                        type="number"
                                        :min="field.min"
                                        :max="field.max"
                                        :class="
                                            requiredFieldControlClass(field)
                                        "
                                        :placeholder="
                                            helpers.editorFieldPlaceholder(
                                                field,
                                            )
                                        "
                                    />
                                </div>

                                <div v-else class="grid gap-2">
                                    <Label
                                        :for="fieldId(`block-${field.name}`)"
                                        class="flex items-center gap-1"
                                    >
                                        <span
                                            v-if="isRequiredField(field)"
                                            class="text-red-600"
                                            aria-hidden="true"
                                            >*</span
                                        >
                                        {{ helpers.editorFieldLabel(field) }}
                                    </Label>
                                    <Input
                                        :id="fieldId(`block-${field.name}`)"
                                        v-model="placement.block[field.name]"
                                        :name="fieldName(`block_${field.name}`)"
                                        :type="field.type || 'text'"
                                        :class="
                                            requiredFieldControlClass(field)
                                        "
                                        :placeholder="
                                            helpers.editorFieldPlaceholder(
                                                field,
                                            )
                                        "
                                    />
                                    <p
                                        v-if="isLogoAltField(field)"
                                        class="text-xs leading-5 text-slate-500"
                                    >
                                        {{
                                            t(
                                                'components.block_editor.logo_alt_media_fallback_help',
                                                'Leave empty to use the media library alt text for this layout language.',
                                            )
                                        }}
                                    </p>
                                    <p
                                        v-if="
                                            isLogoAltField(field) &&
                                            logoAltMediaFallback
                                        "
                                        class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs leading-5 text-slate-600"
                                    >
                                        <span
                                            class="font-medium text-slate-700"
                                        >
                                            {{
                                                t(
                                                    'components.block_editor.logo_alt_media_fallback_label',
                                                    'Media library fallback',
                                                )
                                            }}:
                                        </span>
                                        {{ logoAltMediaFallback }}
                                    </p>
                                </div>
                            </template>
                        </div>

                        <div class="grid gap-2 md:max-w-sm">
                            <Label :for="fieldId('cache-strategy')">
                                {{
                                    t(
                                        'layouts.sections.cache_strategy',
                                        'Cache',
                                    )
                                }}
                            </Label>
                            <select
                                :id="fieldId('cache-strategy')"
                                v-model="placement.cache_strategy"
                                :name="fieldName('cache_strategy')"
                                class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            >
                                <option
                                    v-for="option in placementCacheOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <CmsPlacementSlotEditor
                        v-else-if="activeTab === 'slots'"
                        :model-value="placement?.slots"
                        :slot-definitions="slotDefinitions"
                        :placeable-blocks="placeableBlocks"
                        :media-options="mediaOptions"
                        :media-folders="mediaFolders"
                        :download-options="downloadOptions"
                        :download-folders="downloadFolders"
                        :parent-content-key="
                            placement?.settings?.content_key || ''
                        "
                        @update:model-value="updatePlacementSlots"
                        @settings-requested="
                            emit('slot-child-settings-requested', $event)
                        "
                    />

                    <div v-else-if="activeTab === 'style'" class="grid gap-4">
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="device in styleDeviceOptions"
                                :key="device.value"
                                type="button"
                                :class="styleDeviceChipClasses(device.value)"
                                @click="activeStyleDevice = device.value"
                            >
                                {{ device.label }}
                            </button>
                        </div>

                        <label
                            class="flex items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700"
                        >
                            <input
                                v-model="activeDeviceVisible"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300"
                            />
                            {{
                                t(
                                    'layouts.sections.visible_on_style_device',
                                    'Visible on :device',
                                    { device: activeDeviceLabel },
                                )
                            }}
                        </label>

                        <p
                            v-if="!activeDeviceVisible"
                            class="rounded-md border border-orange-200 bg-orange-50 px-3 py-2 text-sm text-orange-800"
                        >
                            {{
                                t(
                                    'layouts.sections.hidden_style_device_help',
                                    'This block is hidden on :device and does not take space in that grid.',
                                    { device: activeDeviceLabel },
                                )
                            }}
                        </p>

                        <div
                            class="grid gap-4"
                            :class="!activeDeviceVisible ? 'opacity-55' : ''"
                        >
                            <div class="grid gap-3 md:grid-cols-3">
                                <div class="grid gap-2">
                                    <Label :for="fieldId('active-device-span')">
                                        {{
                                            t(
                                                'layouts.sections.device_span',
                                                'Width',
                                            )
                                        }}
                                    </Label>
                                    <select
                                        :id="fieldId('active-device-span')"
                                        v-model.number="activeDeviceSpan"
                                        :name="fieldName('active_device_span')"
                                        :disabled="!activeDeviceVisible"
                                        class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-slate-100"
                                    >
                                        <option
                                            v-for="span in spanOptions"
                                            :key="span"
                                            :value="span"
                                        >
                                            {{ span }}/12
                                        </option>
                                    </select>
                                </div>
                                <div class="grid gap-2">
                                    <Label :for="fieldId('alignment')">
                                        {{
                                            t(
                                                'layouts.sections.placement_alignment',
                                                'Card position',
                                            )
                                        }}
                                    </Label>
                                    <select
                                        :id="fieldId('alignment')"
                                        v-model="activeDeviceStyle.alignment"
                                        :name="fieldName('alignment')"
                                        :disabled="!activeDeviceVisible"
                                        class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-slate-100"
                                    >
                                        <option
                                            v-for="option in alignmentOptions"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                </div>
                                <div class="grid gap-2">
                                    <Label :for="fieldId('z-index')">
                                        {{
                                            t(
                                                'layouts.sections.z_index',
                                                'Layer order',
                                            )
                                        }}
                                    </Label>
                                    <select
                                        :id="fieldId('z-index')"
                                        v-model="activeDeviceStyle.z_index"
                                        :name="fieldName('z_index')"
                                        :disabled="!activeDeviceVisible"
                                        class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-slate-100"
                                    >
                                        <option
                                            v-for="option in zIndexOptions"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div
                                v-if="
                                    !isLogoPlacement &&
                                    !isMenuPlacement &&
                                    !isLanguagePlacement
                                "
                                class="grid gap-3 lg:grid-cols-2"
                            >
                                <div
                                    class="grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-3"
                                >
                                    <h3
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        {{
                                            t(
                                                'layouts.sections.appearance_background',
                                                'Background',
                                            )
                                        }}
                                    </h3>
                                    <ColorPickerField
                                        v-model="
                                            activeDeviceStyle.appearance
                                                .background_color
                                        "
                                        v-model:token-value="
                                            activeDeviceStyle.appearance
                                                .background_color_token
                                        "
                                        :palette-items="paletteItems"
                                        :token-options="colorTokenOptions"
                                        :disabled="!activeDeviceVisible"
                                        :id-prefix="
                                            fieldId('appearance-background')
                                        "
                                        :label="
                                            t(
                                                'layouts.sections.appearance_background',
                                                'Background',
                                            )
                                        "
                                        @update:palette-items="
                                            emit('update:paletteItems', $event)
                                        "
                                    />
                                </div>

                                <div
                                    class="grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-3"
                                >
                                    <div class="grid gap-1">
                                        <h3
                                            class="text-sm font-semibold text-slate-900"
                                        >
                                            {{
                                                t(
                                                    'layouts.sections.foreground_title',
                                                    'Foreground',
                                                )
                                            }}
                                        </h3>
                                        <p class="text-xs text-slate-600">
                                            {{
                                                t(
                                                    'layouts.sections.foreground_description',
                                                    'Choose text styling through theme tokens. The theme CSS decides the actual font values.',
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <ColorPickerField
                                        v-model="
                                            activeDeviceStyle.appearance
                                                .foreground_color
                                        "
                                        v-model:token-value="
                                            activeDeviceStyle.appearance
                                                .foreground_color_token
                                        "
                                        :palette-items="paletteItems"
                                        :token-options="colorTokenOptions"
                                        :disabled="!activeDeviceVisible"
                                        :id-prefix="
                                            fieldId('appearance-foreground')
                                        "
                                        :label="
                                            t(
                                                'layouts.sections.appearance_foreground',
                                                'Text color',
                                            )
                                        "
                                        @update:palette-items="
                                            emit('update:paletteItems', $event)
                                        "
                                    />
                                </div>
                            </div>

                            <div
                                v-if="isLogoPlacement"
                                class="grid gap-3 lg:grid-cols-2"
                            >
                                <div
                                    class="grid content-start gap-3 rounded-md border border-slate-200 p-3"
                                >
                                    <h3
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        {{
                                            t(
                                                'layouts.sections.alignment_group',
                                                'Alignment',
                                            )
                                        }}
                                    </h3>
                                    <div class="grid gap-2">
                                        <Label
                                            :for="fieldId('content-alignment')"
                                        >
                                            {{
                                                t(
                                                    'layouts.sections.content_alignment',
                                                    'Content alignment',
                                                )
                                            }}
                                        </Label>
                                        <select
                                            :id="fieldId('content-alignment')"
                                            v-model="
                                                activeDeviceStyle.content_alignment
                                            "
                                            :name="
                                                fieldName('content_alignment')
                                            "
                                            :disabled="!activeDeviceVisible"
                                            class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-slate-100"
                                        >
                                            <option
                                                v-for="option in contentAlignmentOptions"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="grid gap-2">
                                        <Label
                                            :for="
                                                fieldId(
                                                    'content-vertical-alignment',
                                                )
                                            "
                                        >
                                            {{
                                                t(
                                                    'layouts.sections.content_vertical_alignment',
                                                    'Vertical content alignment',
                                                )
                                            }}
                                        </Label>
                                        <select
                                            :id="
                                                fieldId(
                                                    'content-vertical-alignment',
                                                )
                                            "
                                            v-model="
                                                activeDeviceStyle.content_vertical_alignment
                                            "
                                            :name="
                                                fieldName(
                                                    'content_vertical_alignment',
                                                )
                                            "
                                            :disabled="!activeDeviceVisible"
                                            class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-slate-100"
                                        >
                                            <option
                                                v-for="option in contentVerticalAlignmentOptions"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <div
                                    class="grid content-start gap-3 rounded-md border border-slate-200 p-3"
                                >
                                    <h3
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        {{
                                            t(
                                                'components.block_editor.logo_size',
                                                'Logo size',
                                            )
                                        }}
                                    </h3>
                                    <div class="grid gap-2">
                                        <Label
                                            :for="
                                                fieldId('appearance-logo-size')
                                            "
                                        >
                                            {{
                                                t(
                                                    'components.block_editor.logo_size',
                                                    'Logo size',
                                                )
                                            }}
                                        </Label>
                                        <select
                                            :id="
                                                fieldId('appearance-logo-size')
                                            "
                                            v-model="
                                                activeDeviceStyle.appearance
                                                    .logo_size
                                            "
                                            :name="fieldName('logo_size')"
                                            :disabled="!activeDeviceVisible"
                                            class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-slate-100"
                                        >
                                            <option
                                                v-for="option in logoSizeOptions"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-else-if="!isLanguagePlacement"
                                class="grid gap-3 lg:grid-cols-2"
                            >
                                <div
                                    class="grid content-start gap-3 rounded-md border border-slate-200 p-3"
                                >
                                    <h3
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        {{
                                            t(
                                                'layouts.sections.alignment_group',
                                                'Alignment',
                                            )
                                        }}
                                    </h3>
                                    <div class="grid gap-2">
                                        <Label
                                            :for="fieldId('content-alignment')"
                                        >
                                            {{
                                                t(
                                                    'layouts.sections.content_alignment',
                                                    'Content alignment',
                                                )
                                            }}
                                        </Label>
                                        <select
                                            :id="fieldId('content-alignment')"
                                            v-model="
                                                activeDeviceStyle.content_alignment
                                            "
                                            :name="
                                                fieldName('content_alignment')
                                            "
                                            :disabled="!activeDeviceVisible"
                                            class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-slate-100"
                                        >
                                            <option
                                                v-for="option in contentAlignmentOptions"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="grid gap-2">
                                        <Label
                                            :for="
                                                fieldId(
                                                    'content-vertical-alignment',
                                                )
                                            "
                                        >
                                            {{
                                                t(
                                                    'layouts.sections.content_vertical_alignment',
                                                    'Vertical content alignment',
                                                )
                                            }}
                                        </Label>
                                        <select
                                            :id="
                                                fieldId(
                                                    'content-vertical-alignment',
                                                )
                                            "
                                            v-model="
                                                activeDeviceStyle.content_vertical_alignment
                                            "
                                            :name="
                                                fieldName(
                                                    'content_vertical_alignment',
                                                )
                                            "
                                            :disabled="!activeDeviceVisible"
                                            class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-slate-100"
                                        >
                                            <option
                                                v-for="option in contentVerticalAlignmentOptions"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <div
                                    class="grid content-start gap-3 rounded-md border border-slate-200 p-3"
                                >
                                    <h3
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        {{
                                            t(
                                                'layouts.sections.typography_group',
                                                'Typography',
                                            )
                                        }}
                                    </h3>
                                    <div class="grid gap-2">
                                        <Label
                                            :for="
                                                fieldId(
                                                    'appearance-typography-preset',
                                                )
                                            "
                                        >
                                            {{
                                                t(
                                                    'layouts.sections.appearance_typography_preset',
                                                    'Typography style',
                                                )
                                            }}
                                        </Label>
                                        <select
                                            :id="
                                                fieldId(
                                                    'appearance-typography-preset',
                                                )
                                            "
                                            v-model="
                                                activeDeviceStyle.appearance
                                                    .typography_preset
                                            "
                                            :name="
                                                fieldName('typography_preset')
                                            "
                                            :disabled="!activeDeviceVisible"
                                            class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-slate-100"
                                        >
                                            <option
                                                v-for="option in typographyPresetOptions"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="grid gap-3 md:grid-cols-3">
                                        <div class="grid gap-2">
                                            <Label
                                                :for="
                                                    fieldId(
                                                        'appearance-font-family',
                                                    )
                                                "
                                            >
                                                {{
                                                    t(
                                                        'layouts.sections.appearance_font_family',
                                                        'Font',
                                                    )
                                                }}
                                            </Label>
                                            <RwAutoCompleteInput
                                                :id="
                                                    fieldId(
                                                        'appearance-font-family',
                                                    )
                                                "
                                                v-model="
                                                    activeDeviceStyle.appearance
                                                        .font_family_token
                                                "
                                                :items="fontFamilyTokenOptions"
                                                item-title="label"
                                                item-value="value"
                                                :search-fields="[
                                                    'label',
                                                    'value',
                                                ]"
                                                :name="
                                                    fieldName(
                                                        'font_family_token',
                                                    )
                                                "
                                                :disabled="!activeDeviceVisible"
                                                :placeholder="
                                                    t(
                                                        'layouts.sections.appearance_font_family',
                                                        'Font',
                                                    )
                                                "
                                            >
                                                <template #selection="{ item }">
                                                    <span
                                                        class="flex min-w-0 flex-1 items-center gap-2"
                                                    >
                                                        <span
                                                            class="mdi mdi-format-font shrink-0 text-base leading-none text-blue-700"
                                                            aria-hidden="true"
                                                        />
                                                        <span
                                                            class="min-w-0 flex-1 truncate"
                                                            :style="
                                                                fontFamilyPreviewStyle(
                                                                    item.value,
                                                                )
                                                            "
                                                        >
                                                            {{ item.label }}
                                                        </span>
                                                    </span>
                                                </template>

                                                <template
                                                    #option="{ item, selected }"
                                                >
                                                    <span
                                                        class="flex min-w-0 flex-1 items-center gap-2"
                                                    >
                                                        <span
                                                            class="mdi mdi-format-font shrink-0 text-base leading-none text-blue-700"
                                                            aria-hidden="true"
                                                        />
                                                        <span
                                                            class="grid min-w-0 flex-1 gap-0.5"
                                                        >
                                                            <span
                                                                class="truncate font-medium"
                                                                :style="
                                                                    fontFamilyPreviewStyle(
                                                                        item.value,
                                                                    )
                                                                "
                                                            >
                                                                {{ item.label }}
                                                            </span>
                                                            <span
                                                                class="truncate text-xs text-slate-500"
                                                            >
                                                                {{
                                                                    fontFamilyOptionDetail(
                                                                        item.value,
                                                                    )
                                                                }}
                                                            </span>
                                                        </span>
                                                        <span
                                                            v-if="selected"
                                                            class="mdi mdi-check shrink-0 text-base leading-none text-blue-600"
                                                            aria-hidden="true"
                                                        />
                                                    </span>
                                                </template>
                                            </RwAutoCompleteInput>
                                        </div>
                                        <div class="grid gap-2">
                                            <Label
                                                :for="
                                                    fieldId(
                                                        'appearance-font-size',
                                                    )
                                                "
                                            >
                                                {{
                                                    t(
                                                        'layouts.sections.appearance_font_size',
                                                        'Text size',
                                                    )
                                                }}
                                            </Label>
                                            <select
                                                :id="
                                                    fieldId(
                                                        'appearance-font-size',
                                                    )
                                                "
                                                v-model="
                                                    activeDeviceStyle.appearance
                                                        .font_size_token
                                                "
                                                :name="
                                                    fieldName('font_size_token')
                                                "
                                                :disabled="!activeDeviceVisible"
                                                class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-slate-100"
                                            >
                                                <option
                                                    v-for="option in fontSizeTokenOptions"
                                                    :key="option.value"
                                                    :value="option.value"
                                                >
                                                    {{ option.label }}
                                                </option>
                                            </select>
                                        </div>
                                        <div class="grid gap-2">
                                            <Label
                                                :for="
                                                    fieldId(
                                                        'appearance-font-weight',
                                                    )
                                                "
                                            >
                                                {{
                                                    t(
                                                        'layouts.sections.appearance_font_weight',
                                                        'Font weight',
                                                    )
                                                }}
                                            </Label>
                                            <select
                                                :id="
                                                    fieldId(
                                                        'appearance-font-weight',
                                                    )
                                                "
                                                v-model="
                                                    activeDeviceStyle.appearance
                                                        .font_weight
                                                "
                                                :name="fieldName('font_weight')"
                                                :disabled="!activeDeviceVisible"
                                                class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-slate-100"
                                            >
                                                <option
                                                    v-for="option in fontWeightOptions"
                                                    :key="option.value"
                                                    :value="option.value"
                                                >
                                                    {{ option.label }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="grid gap-3 rounded-md border border-slate-200 p-3 lg:col-span-2"
                                >
                                    <div class="grid gap-1">
                                        <h3
                                            class="text-sm font-semibold text-slate-900"
                                        >
                                            {{
                                                t(
                                                    'layouts.sections.card_style_group',
                                                    'Card styling',
                                                )
                                            }}
                                        </h3>
                                        <p class="text-xs text-slate-600">
                                            {{
                                                t(
                                                    'layouts.sections.card_style_description',
                                                    'Enable this when the block should render as a visual card or panel. When disabled, background, corners, border and shadow stay inactive.',
                                                )
                                            }}
                                        </p>
                                    </div>

                                    <label
                                        class="flex items-start gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700"
                                    >
                                        <input
                                            v-model="
                                                activeAppearanceContainer.enabled
                                            "
                                            type="checkbox"
                                            class="mt-0.5 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                        />
                                        <span class="grid gap-0.5">
                                            <span>
                                                {{
                                                    t(
                                                        'layouts.sections.card_style_enabled',
                                                        'Show as card or panel',
                                                    )
                                                }}
                                            </span>
                                            <span
                                                class="text-xs font-normal text-slate-600"
                                            >
                                                {{
                                                    t(
                                                        'layouts.sections.card_style_enabled_help',
                                                        'Leave disabled for plain content blocks such as images and rich text.',
                                                    )
                                                }}
                                            </span>
                                        </span>
                                    </label>
                                    <div class="grid gap-3 md:grid-cols-3">
                                        <div class="grid gap-2">
                                            <Label
                                                :for="
                                                    fieldId('appearance-radius')
                                                "
                                            >
                                                {{
                                                    t(
                                                        'layouts.sections.appearance_radius',
                                                        'Corners',
                                                    )
                                                }}
                                            </Label>
                                            <select
                                                :id="
                                                    fieldId('appearance-radius')
                                                "
                                                v-model="
                                                    activeDeviceStyle.appearance
                                                        .radius
                                                "
                                                :name="
                                                    fieldName(
                                                        'appearance_radius',
                                                    )
                                                "
                                                :disabled="!activeDeviceVisible"
                                                class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-slate-100"
                                            >
                                                <option
                                                    v-for="option in radiusStyleOptions"
                                                    :key="option.value"
                                                    :value="option.value"
                                                >
                                                    {{ option.label }}
                                                </option>
                                            </select>
                                        </div>
                                        <div class="grid gap-2">
                                            <Label
                                                :for="
                                                    fieldId('appearance-border')
                                                "
                                            >
                                                {{
                                                    t(
                                                        'layouts.sections.appearance_border',
                                                        'Border',
                                                    )
                                                }}
                                            </Label>
                                            <select
                                                :id="
                                                    fieldId('appearance-border')
                                                "
                                                v-model="
                                                    activeDeviceStyle.appearance
                                                        .border
                                                "
                                                :name="
                                                    fieldName(
                                                        'appearance_border',
                                                    )
                                                "
                                                :disabled="!activeDeviceVisible"
                                                class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-slate-100"
                                            >
                                                <option
                                                    v-for="option in borderStyleOptions"
                                                    :key="option.value"
                                                    :value="option.value"
                                                >
                                                    {{ option.label }}
                                                </option>
                                            </select>
                                        </div>
                                        <div class="grid gap-2">
                                            <Label
                                                :for="
                                                    fieldId('appearance-shadow')
                                                "
                                            >
                                                {{
                                                    t(
                                                        'layouts.sections.appearance_shadow',
                                                        'Shadow',
                                                    )
                                                }}
                                            </Label>
                                            <select
                                                :id="
                                                    fieldId('appearance-shadow')
                                                "
                                                v-model="
                                                    activeDeviceStyle.appearance
                                                        .shadow
                                                "
                                                :name="
                                                    fieldName(
                                                        'appearance_shadow',
                                                    )
                                                "
                                                :disabled="!activeDeviceVisible"
                                                class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-slate-100"
                                            >
                                                <option
                                                    v-for="option in shadowStyleOptions"
                                                    :key="option.value"
                                                    :value="option.value"
                                                >
                                                    {{ option.label }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="isFormPlacement"
                            class="grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-3"
                        >
                            <div class="grid gap-1">
                                <h3
                                    class="text-sm font-semibold text-slate-900"
                                >
                                    {{
                                        t(
                                            'layouts.sections.form_style_group',
                                            'Form styling',
                                        )
                                    }}
                                </h3>
                                <p class="text-xs text-slate-600">
                                    {{
                                        t(
                                            'layouts.sections.form_style_description',
                                            'Tune the form fields and submit button without adding a forced card around the form.',
                                        )
                                    }}
                                </p>
                            </div>

                            <div class="grid gap-3 md:grid-cols-3">
                                <div class="grid gap-2">
                                    <Label :for="fieldId('form-field-spacing')">
                                        {{
                                            t(
                                                'layouts.sections.form_field_spacing',
                                                'Field spacing',
                                            )
                                        }}
                                    </Label>
                                    <RwAutoCompleteInput
                                        :id="fieldId('form-field-spacing')"
                                        v-model="activeFormStyle.field_spacing"
                                        :items="formFieldSpacingOptions"
                                        item-title="label"
                                        item-value="value"
                                        :search-fields="['label', 'value']"
                                        :name="fieldName('form_field_spacing')"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label :for="fieldId('form-label-weight')">
                                        {{
                                            t(
                                                'layouts.sections.form_label_weight',
                                                'Label weight',
                                            )
                                        }}
                                    </Label>
                                    <RwAutoCompleteInput
                                        :id="fieldId('form-label-weight')"
                                        v-model="activeFormStyle.label_weight"
                                        :items="fontWeightOptions"
                                        item-title="label"
                                        item-value="value"
                                        :search-fields="['label', 'value']"
                                        :name="fieldName('form_label_weight')"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label :for="fieldId('form-input-radius')">
                                        {{
                                            t(
                                                'layouts.sections.form_input_radius',
                                                'Input corners',
                                            )
                                        }}
                                    </Label>
                                    <RwAutoCompleteInput
                                        :id="fieldId('form-input-radius')"
                                        v-model="activeFormStyle.input_radius"
                                        :items="formInputRadiusOptions"
                                        item-title="label"
                                        item-value="value"
                                        :search-fields="['label', 'value']"
                                        :name="fieldName('form_input_radius')"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label :for="fieldId('form-input-border')">
                                        {{
                                            t(
                                                'layouts.sections.form_input_border',
                                                'Input border',
                                            )
                                        }}
                                    </Label>
                                    <RwAutoCompleteInput
                                        :id="fieldId('form-input-border')"
                                        v-model="activeFormStyle.input_border"
                                        :items="formInputBorderOptions"
                                        item-title="label"
                                        item-value="value"
                                        :search-fields="['label', 'value']"
                                        :name="fieldName('form_input_border')"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label
                                        :for="fieldId('form-submit-alignment')"
                                    >
                                        {{
                                            t(
                                                'layouts.sections.form_submit_alignment',
                                                'Submit alignment',
                                            )
                                        }}
                                    </Label>
                                    <RwAutoCompleteInput
                                        :id="fieldId('form-submit-alignment')"
                                        v-model="
                                            activeFormStyle.submit_alignment
                                        "
                                        :items="formSubmitAlignmentOptions"
                                        item-title="label"
                                        item-value="value"
                                        :search-fields="['label', 'value']"
                                        :name="
                                            fieldName('form_submit_alignment')
                                        "
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label
                                        :for="fieldId('form-submit-variant')"
                                    >
                                        {{
                                            t(
                                                'layouts.sections.form_submit_variant',
                                                'Submit style',
                                            )
                                        }}
                                    </Label>
                                    <RwAutoCompleteInput
                                        :id="fieldId('form-submit-variant')"
                                        v-model="activeFormStyle.submit_variant"
                                        :items="formSubmitVariantOptions"
                                        item-title="label"
                                        item-value="value"
                                        :search-fields="['label', 'value']"
                                        :name="fieldName('form_submit_variant')"
                                    />
                                </div>
                            </div>

                            <div class="grid gap-3 lg:grid-cols-2">
                                <ColorPickerField
                                    v-for="colorField in formColorFields"
                                    :key="colorField.value"
                                    :model-value="
                                        activeFormStyle[colorField.value]
                                    "
                                    :token-value="
                                        activeFormStyle[
                                            `${colorField.value}_token`
                                        ]
                                    "
                                    :palette-items="paletteItems"
                                    :token-options="colorTokenOptions"
                                    :id-prefix="
                                        fieldId(`form-${colorField.value}`)
                                    "
                                    :label="colorField.label"
                                    allow-css-color
                                    @update:model-value="
                                        activeFormStyle[colorField.value] =
                                            $event
                                    "
                                    @update:token-value="
                                        activeFormStyle[
                                            `${colorField.value}_token`
                                        ] = $event
                                    "
                                    @update:palette-items="
                                        emit('update:paletteItems', $event)
                                    "
                                />
                            </div>
                        </div>

                        <div
                            v-if="isMenuPlacement"
                            class="grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-3"
                        >
                            <div class="grid gap-1">
                                <h3
                                    class="text-sm font-semibold text-slate-900"
                                >
                                    {{
                                        t(
                                            'layouts.sections.menu_style_group',
                                            'Menu styling',
                                        )
                                    }}
                                </h3>
                                <p class="text-xs text-slate-600">
                                    {{
                                        t(
                                            'layouts.sections.menu_style_description',
                                            'Choose how this menu behaves on the selected device.',
                                        )
                                    }}
                                </p>
                            </div>

                            <div class="grid gap-3 md:grid-cols-3">
                                <div class="grid gap-2">
                                    <Label :for="fieldId('menu-display')">
                                        {{
                                            t(
                                                'layouts.sections.menu_display',
                                                'Menu display',
                                            )
                                        }}
                                    </Label>
                                    <select
                                        :id="fieldId('menu-display')"
                                        v-model="activeMenuDeviceStyle.display"
                                        :name="fieldName('menu_display')"
                                        :disabled="!activeDeviceVisible"
                                        class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-slate-100"
                                    >
                                        <option
                                            v-for="option in menuDisplayOptions"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                </div>

                                <div class="grid gap-2">
                                    <Label :for="fieldId('menu-toggle-label')">
                                        {{
                                            t(
                                                'layouts.sections.menu_toggle_label',
                                                'Menu button label',
                                            )
                                        }}
                                    </Label>
                                    <Input
                                        :id="fieldId('menu-toggle-label')"
                                        v-model="
                                            activeMenuDeviceStyle.toggle_label
                                        "
                                        :name="fieldName('menu_toggle_label')"
                                        :disabled="!activeDeviceVisible"
                                        maxlength="120"
                                        class="bg-white disabled:cursor-not-allowed disabled:bg-slate-100"
                                        :placeholder="
                                            t(
                                                'layouts.sections.menu_toggle_label_placeholder',
                                                'Leave empty to show only the icon',
                                            )
                                        "
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label :for="fieldId('menu-alignment')">
                                        {{
                                            t(
                                                'layouts.sections.menu_alignment',
                                                'Menu alignment',
                                            )
                                        }}
                                    </Label>
                                    <select
                                        :id="fieldId('menu-alignment')"
                                        v-model="
                                            activeMenuDeviceStyle.alignment
                                        "
                                        :name="fieldName('menu_alignment')"
                                        :disabled="!activeDeviceVisible"
                                        class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-slate-100"
                                    >
                                        <option
                                            v-for="option in menuAlignmentOptions"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid gap-3 md:grid-cols-3">
                                <div class="grid gap-2">
                                    <Label :for="fieldId('menu-variant')">
                                        {{
                                            t(
                                                'layouts.sections.menu_variant',
                                                'Item style',
                                            )
                                        }}
                                    </Label>
                                    <select
                                        :id="fieldId('menu-variant')"
                                        v-model="
                                            placement.style_config.menu
                                                .item_variant
                                        "
                                        :name="fieldName('menu_variant')"
                                        class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                    >
                                        <option
                                            v-for="option in menuVariantOptions"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                </div>

                                <div class="grid gap-2">
                                    <Label :for="fieldId('menu-spacing')">
                                        {{
                                            t(
                                                'layouts.sections.menu_spacing',
                                                'Menu spacing',
                                            )
                                        }}
                                    </Label>
                                    <select
                                        :id="fieldId('menu-spacing')"
                                        v-model="
                                            placement.style_config.menu.spacing
                                        "
                                        :name="fieldName('menu_spacing')"
                                        class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                    >
                                        <option
                                            v-for="option in menuSpacingOptions"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                </div>

                                <div class="grid gap-2">
                                    <Label :for="fieldId('menu-drawer-side')">
                                        {{
                                            t(
                                                'layouts.sections.menu_drawer_side',
                                                'Drawer side',
                                            )
                                        }}
                                    </Label>
                                    <select
                                        :id="fieldId('menu-drawer-side')"
                                        v-model="
                                            placement.style_config.menu
                                                .drawer_side
                                        "
                                        :name="fieldName('menu_drawer_side')"
                                        class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                    >
                                        <option
                                            v-for="option in menuDrawerSideOptions"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                </div>

                                <div class="grid gap-2">
                                    <Label :for="fieldId('menu-drawer-top')">
                                        {{
                                            t(
                                                'layouts.sections.menu_drawer_top',
                                                'Drawer top',
                                            )
                                        }}
                                    </Label>
                                    <select
                                        :id="fieldId('menu-drawer-top')"
                                        v-model="
                                            placement.style_config.menu
                                                .drawer_top
                                        "
                                        :name="fieldName('menu_drawer_top')"
                                        class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                    >
                                        <option
                                            v-for="option in menuDrawerTopOptions"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                </div>

                                <div class="grid gap-2">
                                    <Label
                                        :for="fieldId('menu-submenu-behavior')"
                                    >
                                        {{
                                            t(
                                                'layouts.sections.menu_submenu_behavior',
                                                'Submenu behavior',
                                            )
                                        }}
                                    </Label>
                                    <select
                                        :id="fieldId('menu-submenu-behavior')"
                                        v-model="
                                            placement.style_config.menu
                                                .submenu_behavior
                                        "
                                        :name="
                                            fieldName('menu_submenu_behavior')
                                        "
                                        class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                    >
                                        <option
                                            v-for="option in menuSubmenuBehaviorOptions"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                </div>

                                <div class="grid gap-2">
                                    <Label :for="fieldId('menu-submenu-side')">
                                        {{
                                            t(
                                                'layouts.sections.menu_submenu_side',
                                                'Submenu side',
                                            )
                                        }}
                                    </Label>
                                    <select
                                        :id="fieldId('menu-submenu-side')"
                                        v-model="
                                            placement.style_config.menu
                                                .submenu_side
                                        "
                                        :name="fieldName('menu_submenu_side')"
                                        class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                    >
                                        <option
                                            v-for="option in menuSubmenuSideOptions"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div
                                v-if="
                                    activeMenuDeviceStyle.display ===
                                    'hamburger'
                                "
                                class="grid gap-3 rounded-md border border-slate-200 bg-white p-3"
                            >
                                <div class="grid gap-1">
                                    <h3
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        {{
                                            t(
                                                'layouts.sections.menu_toggle_group',
                                                'Hamburger button',
                                            )
                                        }}
                                    </h3>
                                    <p class="text-xs text-slate-600">
                                        {{
                                            t(
                                                'layouts.sections.menu_toggle_description',
                                                'Style the menu button for this device. Tablet and mobile inherit earlier device values unless you change them.',
                                            )
                                        }}
                                    </p>
                                </div>

                                <div class="grid gap-3 md:grid-cols-3">
                                    <div class="grid gap-2">
                                        <Label
                                            :for="fieldId('menu-toggle-icon')"
                                        >
                                            {{
                                                t(
                                                    'layouts.sections.menu_toggle_icon',
                                                    'Icon',
                                                )
                                            }}
                                        </Label>
                                        <select
                                            :id="fieldId('menu-toggle-icon')"
                                            v-model="activeMenuToggle.icon"
                                            :name="
                                                fieldName('menu_toggle_icon')
                                            "
                                            :disabled="!activeDeviceVisible"
                                            class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-slate-100"
                                        >
                                            <option
                                                v-for="option in menuToggleIconOptions"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label
                                            :for="fieldId('menu-toggle-shape')"
                                        >
                                            {{
                                                t(
                                                    'layouts.sections.menu_toggle_shape',
                                                    'Shape',
                                                )
                                            }}
                                        </Label>
                                        <select
                                            :id="fieldId('menu-toggle-shape')"
                                            v-model="activeMenuToggle.shape"
                                            :name="
                                                fieldName('menu_toggle_shape')
                                            "
                                            :disabled="!activeDeviceVisible"
                                            class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-slate-100"
                                        >
                                            <option
                                                v-for="option in menuToggleShapeOptions"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label
                                            :for="fieldId('menu-toggle-size')"
                                        >
                                            {{
                                                t(
                                                    'layouts.sections.menu_toggle_size',
                                                    'Size',
                                                )
                                            }}
                                        </Label>
                                        <select
                                            :id="fieldId('menu-toggle-size')"
                                            v-model="activeMenuToggle.size"
                                            :name="
                                                fieldName('menu_toggle_size')
                                            "
                                            :disabled="!activeDeviceVisible"
                                            class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-slate-100"
                                        >
                                            <option
                                                v-for="option in menuToggleSizeOptions"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid gap-3 lg:grid-cols-2">
                                    <ColorPickerField
                                        v-for="colorField in menuToggleColorFields"
                                        :key="colorField.value"
                                        :model-value="
                                            activeMenuToggle[colorField.value]
                                        "
                                        :token-value="
                                            activeMenuToggle[
                                                `${colorField.value}_token`
                                            ]
                                        "
                                        :palette-items="paletteItems"
                                        :token-options="colorTokenOptions"
                                        :disabled="!activeDeviceVisible"
                                        :id-prefix="
                                            fieldId(
                                                `menu-toggle-${colorField.value}`,
                                            )
                                        "
                                        :label="colorField.label"
                                        allow-css-color
                                        @update:model-value="
                                            activeMenuToggle[colorField.value] =
                                                $event
                                        "
                                        @update:token-value="
                                            activeMenuToggle[
                                                `${colorField.value}_token`
                                            ] = $event
                                        "
                                        @update:palette-items="
                                            emit('update:paletteItems', $event)
                                        "
                                    />
                                </div>
                            </div>

                            <div
                                class="grid gap-3 rounded-md border border-slate-200 bg-white p-3"
                            >
                                <h3
                                    class="text-sm font-semibold text-slate-900"
                                >
                                    {{
                                        t(
                                            'layouts.sections.menu_typography_group',
                                            'Menu typography',
                                        )
                                    }}
                                </h3>
                                <div class="grid gap-2">
                                    <Label
                                        :for="fieldId('menu-typography-preset')"
                                    >
                                        {{
                                            t(
                                                'layouts.sections.appearance_typography_preset',
                                                'Typography style',
                                            )
                                        }}
                                    </Label>
                                    <select
                                        :id="fieldId('menu-typography-preset')"
                                        v-model="
                                            activeMenuAppearance.typography_preset
                                        "
                                        :name="
                                            fieldName('menu_typography_preset')
                                        "
                                        class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                    >
                                        <option
                                            v-for="option in typographyPresetOptions"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                </div>
                                <div class="grid gap-3 md:grid-cols-3">
                                    <div class="grid gap-2">
                                        <Label
                                            :for="fieldId('menu-font-family')"
                                        >
                                            {{
                                                t(
                                                    'layouts.sections.appearance_font_family',
                                                    'Font',
                                                )
                                            }}
                                        </Label>
                                        <RwAutoCompleteInput
                                            :id="fieldId('menu-font-family')"
                                            v-model="
                                                activeMenuAppearance.font_family_token
                                            "
                                            :items="fontFamilyTokenOptions"
                                            item-title="label"
                                            item-value="value"
                                            :search-fields="['label', 'value']"
                                            :name="
                                                fieldName('menu_font_family')
                                            "
                                            :placeholder="
                                                t(
                                                    'layouts.sections.appearance_font_family',
                                                    'Font',
                                                )
                                            "
                                        >
                                            <template #selection="{ item }">
                                                <span
                                                    class="flex min-w-0 flex-1 items-center gap-2"
                                                >
                                                    <span
                                                        class="mdi mdi-format-font shrink-0 text-base leading-none text-blue-700"
                                                        aria-hidden="true"
                                                    />
                                                    <span
                                                        class="min-w-0 flex-1 truncate"
                                                        :style="
                                                            fontFamilyPreviewStyle(
                                                                item.value,
                                                            )
                                                        "
                                                    >
                                                        {{ item.label }}
                                                    </span>
                                                </span>
                                            </template>

                                            <template
                                                #option="{ item, selected }"
                                            >
                                                <span
                                                    class="flex min-w-0 flex-1 items-center gap-2"
                                                >
                                                    <span
                                                        class="mdi mdi-format-font shrink-0 text-base leading-none text-blue-700"
                                                        aria-hidden="true"
                                                    />
                                                    <span
                                                        class="grid min-w-0 flex-1 gap-0.5"
                                                    >
                                                        <span
                                                            class="truncate font-medium"
                                                            :style="
                                                                fontFamilyPreviewStyle(
                                                                    item.value,
                                                                )
                                                            "
                                                        >
                                                            {{ item.label }}
                                                        </span>
                                                        <span
                                                            class="truncate text-xs text-slate-500"
                                                        >
                                                            {{
                                                                fontFamilyOptionDetail(
                                                                    item.value,
                                                                )
                                                            }}
                                                        </span>
                                                    </span>
                                                    <span
                                                        v-if="selected"
                                                        class="mdi mdi-check shrink-0 text-base leading-none text-blue-600"
                                                        aria-hidden="true"
                                                    />
                                                </span>
                                            </template>
                                        </RwAutoCompleteInput>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label :for="fieldId('menu-font-size')">
                                            {{
                                                t(
                                                    'layouts.sections.appearance_font_size',
                                                    'Text size',
                                                )
                                            }}
                                        </Label>
                                        <select
                                            :id="fieldId('menu-font-size')"
                                            v-model="
                                                activeMenuAppearance.font_size_token
                                            "
                                            :name="fieldName('menu_font_size')"
                                            class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        >
                                            <option
                                                v-for="option in fontSizeTokenOptions"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label
                                            :for="fieldId('menu-font-weight')"
                                        >
                                            {{
                                                t(
                                                    'layouts.sections.appearance_font_weight',
                                                    'Font weight',
                                                )
                                            }}
                                        </Label>
                                        <select
                                            :id="fieldId('menu-font-weight')"
                                            v-model="
                                                activeMenuAppearance.font_weight
                                            "
                                            :name="
                                                fieldName('menu_font_weight')
                                            "
                                            class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        >
                                            <option
                                                v-for="option in fontWeightOptions"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="grid gap-3 rounded-md border border-slate-200 bg-white p-3"
                            >
                                <div class="grid gap-1">
                                    <h3
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        {{
                                            t(
                                                'layouts.sections.menu_colors_group',
                                                'Menu colors',
                                            )
                                        }}
                                    </h3>
                                    <p class="text-xs text-slate-600">
                                        {{
                                            t(
                                                'layouts.sections.menu_colors_description',
                                                'Use theme tokens, hex colors, rgb/hsl values, transparent, currentColor or safe public CSS variables.',
                                            )
                                        }}
                                    </p>
                                </div>
                                <div class="grid gap-3 lg:grid-cols-2">
                                    <ColorPickerField
                                        v-for="colorField in menuColorFields"
                                        :key="colorField.value"
                                        :model-value="
                                            activeMenuAppearance[
                                                colorField.value
                                            ]
                                        "
                                        :token-value="
                                            activeMenuAppearance[
                                                `${colorField.value}_token`
                                            ]
                                        "
                                        :palette-items="paletteItems"
                                        :token-options="colorTokenOptions"
                                        :id-prefix="
                                            fieldId(`menu-${colorField.value}`)
                                        "
                                        :label="colorField.label"
                                        allow-css-color
                                        @update:model-value="
                                            activeMenuAppearance[
                                                colorField.value
                                            ] = $event
                                        "
                                        @update:token-value="
                                            activeMenuAppearance[
                                                `${colorField.value}_token`
                                            ] = $event
                                        "
                                        @update:palette-items="
                                            emit('update:paletteItems', $event)
                                        "
                                    />
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="isLanguagePlacement"
                            class="grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-3"
                        >
                            <div class="grid gap-1">
                                <h3
                                    class="text-sm font-semibold text-slate-900"
                                >
                                    {{
                                        t(
                                            'layouts.sections.language_style_group',
                                            'Language menu styling',
                                        )
                                    }}
                                </h3>
                                <p class="text-xs text-slate-600">
                                    {{
                                        t(
                                            'layouts.sections.language_style_description',
                                            'Choose how the language switcher behaves on the selected device.',
                                        )
                                    }}
                                </p>
                            </div>

                            <div class="grid gap-3 md:grid-cols-3">
                                <div class="grid gap-2">
                                    <Label :for="fieldId('language-display')">
                                        {{
                                            t(
                                                'layouts.sections.language_display',
                                                'Language display',
                                            )
                                        }}
                                    </Label>
                                    <RwAutoCompleteInput
                                        :id="fieldId('language-display')"
                                        v-model="
                                            activeLanguageDeviceStyle.display
                                        "
                                        :items="languageDisplayOptions"
                                        item-title="label"
                                        item-value="value"
                                        :search-fields="['label', 'value']"
                                        :name="fieldName('language_display')"
                                        :disabled="!activeDeviceVisible"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label :for="fieldId('language-alignment')">
                                        {{
                                            t(
                                                'layouts.sections.language_alignment',
                                                'Language alignment',
                                            )
                                        }}
                                    </Label>
                                    <RwAutoCompleteInput
                                        :id="fieldId('language-alignment')"
                                        v-model="
                                            activeLanguageDeviceStyle.alignment
                                        "
                                        :items="menuAlignmentOptions"
                                        item-title="label"
                                        item-value="value"
                                        :search-fields="['label', 'value']"
                                        :name="fieldName('language_alignment')"
                                        :disabled="!activeDeviceVisible"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label :for="fieldId('language-variant')">
                                        {{
                                            t(
                                                'layouts.sections.language_variant',
                                                'Item style',
                                            )
                                        }}
                                    </Label>
                                    <RwAutoCompleteInput
                                        :id="fieldId('language-variant')"
                                        v-model="
                                            placement.style_config.language
                                                .item_variant
                                        "
                                        :items="menuVariantOptions"
                                        item-title="label"
                                        item-value="value"
                                        :search-fields="['label', 'value']"
                                        :name="fieldName('language_variant')"
                                    />
                                </div>
                            </div>

                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label
                                        :for="fieldId('language-device-label')"
                                    >
                                        {{
                                            t(
                                                'layouts.sections.language_device_label',
                                                'Device label',
                                            )
                                        }}
                                    </Label>
                                    <Input
                                        :id="fieldId('language-device-label')"
                                        v-model="
                                            activeLanguageDeviceStyle.label
                                        "
                                        :name="
                                            fieldName('language_device_label')
                                        "
                                        :disabled="!activeDeviceVisible"
                                        :placeholder="
                                            t(
                                                'layouts.sections.language_device_label_placeholder',
                                                'Leave empty to show only the language choice',
                                            )
                                        "
                                    />
                                    <p class="text-xs text-slate-600">
                                        {{
                                            t(
                                                'layouts.sections.language_device_label_help',
                                                'Shown before the language choice on the selected device.',
                                            )
                                        }}
                                    </p>
                                </div>

                                <div class="grid gap-2">
                                    <Label
                                        :for="fieldId('language-device-icon')"
                                    >
                                        {{
                                            t(
                                                'layouts.sections.language_device_icon',
                                                'Optional icon',
                                            )
                                        }}
                                    </Label>
                                    <RwAutoCompleteInput
                                        :id="fieldId('language-device-icon')"
                                        v-model="activeLanguageDeviceStyle.icon"
                                        :items="languageIconOptions"
                                        item-title="label"
                                        item-value="value"
                                        :search-fields="['label', 'value']"
                                        :name="
                                            fieldName('language_device_icon')
                                        "
                                        :disabled="!activeDeviceVisible"
                                        allow-custom
                                        :placeholder="
                                            t(
                                                'layouts.sections.language_device_icon_placeholder',
                                                'none or mdi-earth',
                                            )
                                        "
                                    >
                                        <template #selection="{ item }">
                                            <span
                                                class="flex min-w-0 flex-1 items-center gap-2"
                                            >
                                                <span
                                                    v-if="
                                                        languageIconClass(
                                                            item.value,
                                                        )
                                                    "
                                                    :class="[
                                                        'mdi shrink-0 text-base leading-none text-blue-700',
                                                        languageIconClass(
                                                            item.value,
                                                        ),
                                                    ]"
                                                    aria-hidden="true"
                                                />
                                                <span class="truncate">
                                                    {{ item.label }}
                                                </span>
                                            </span>
                                        </template>

                                        <template #option="{ item, selected }">
                                            <span
                                                class="flex min-w-0 flex-1 items-center gap-2"
                                            >
                                                <span
                                                    v-if="
                                                        languageIconClass(
                                                            item.value,
                                                        )
                                                    "
                                                    :class="[
                                                        'mdi shrink-0 text-base leading-none text-blue-700',
                                                        languageIconClass(
                                                            item.value,
                                                        ),
                                                    ]"
                                                    aria-hidden="true"
                                                />
                                                <span class="truncate">
                                                    {{ item.label }}
                                                </span>
                                                <span
                                                    v-if="selected"
                                                    class="mdi mdi-check ml-auto shrink-0 text-base leading-none text-blue-600"
                                                    aria-hidden="true"
                                                />
                                            </span>
                                        </template>
                                    </RwAutoCompleteInput>
                                    <p class="text-xs text-slate-600">
                                        {{
                                            t(
                                                'layouts.sections.language_device_icon_help',
                                                'Default is no icon. Custom values must be safe MDI classes like mdi-earth.',
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid gap-3 md:grid-cols-4">
                                <div class="grid gap-2">
                                    <Label :for="fieldId('language-spacing')">
                                        {{
                                            t(
                                                'layouts.sections.language_spacing',
                                                'Spacing',
                                            )
                                        }}
                                    </Label>
                                    <RwAutoCompleteInput
                                        :id="fieldId('language-spacing')"
                                        v-model="
                                            placement.style_config.language
                                                .spacing
                                        "
                                        :items="menuSpacingOptions"
                                        item-title="label"
                                        item-value="value"
                                        :search-fields="['label', 'value']"
                                        :name="fieldName('language_spacing')"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label
                                        :for="fieldId('language-flag-position')"
                                    >
                                        {{
                                            t(
                                                'layouts.sections.language_flag_position',
                                                'Flag position',
                                            )
                                        }}
                                    </Label>
                                    <RwAutoCompleteInput
                                        :id="fieldId('language-flag-position')"
                                        v-model="
                                            placement.style_config.language
                                                .flag_position
                                        "
                                        :items="languageFlagPositionOptions"
                                        item-title="label"
                                        item-value="value"
                                        :search-fields="['label', 'value']"
                                        :name="
                                            fieldName('language_flag_position')
                                        "
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label
                                        :for="fieldId('language-flag-shape')"
                                    >
                                        {{
                                            t(
                                                'layouts.sections.language_flag_shape',
                                                'Flag shape',
                                            )
                                        }}
                                    </Label>
                                    <RwAutoCompleteInput
                                        :id="fieldId('language-flag-shape')"
                                        v-model="
                                            placement.style_config.language
                                                .flag_shape
                                        "
                                        :items="languageFlagShapeOptions"
                                        item-title="label"
                                        item-value="value"
                                        :search-fields="['label', 'value']"
                                        :name="fieldName('language_flag_shape')"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label :for="fieldId('language-flag-size')">
                                        {{
                                            t(
                                                'layouts.sections.language_flag_size',
                                                'Flag size',
                                            )
                                        }}
                                    </Label>
                                    <RwAutoCompleteInput
                                        :id="fieldId('language-flag-size')"
                                        v-model="
                                            placement.style_config.language
                                                .flag_size
                                        "
                                        :items="languageFlagSizeOptions"
                                        item-title="label"
                                        item-value="value"
                                        :search-fields="['label', 'value']"
                                        :name="fieldName('language_flag_size')"
                                    />
                                </div>
                            </div>

                            <div
                                class="grid gap-3 rounded-md border border-slate-200 bg-white p-3"
                            >
                                <h3
                                    class="text-sm font-semibold text-slate-900"
                                >
                                    {{
                                        t(
                                            'layouts.sections.language_typography_group',
                                            'Language menu typography',
                                        )
                                    }}
                                </h3>
                                <div class="grid gap-2">
                                    <Label
                                        :for="
                                            fieldId(
                                                'language-typography-preset',
                                            )
                                        "
                                    >
                                        {{
                                            t(
                                                'layouts.sections.appearance_typography_preset',
                                                'Typography style',
                                            )
                                        }}
                                    </Label>
                                    <RwAutoCompleteInput
                                        :id="
                                            fieldId(
                                                'language-typography-preset',
                                            )
                                        "
                                        v-model="
                                            activeLanguageAppearance.typography_preset
                                        "
                                        :items="typographyPresetOptions"
                                        item-title="label"
                                        item-value="value"
                                        :search-fields="['label', 'value']"
                                        :name="
                                            fieldName(
                                                'language_typography_preset',
                                            )
                                        "
                                    />
                                </div>
                                <div class="grid gap-3 md:grid-cols-3">
                                    <div class="grid gap-2">
                                        <Label
                                            :for="
                                                fieldId('language-font-family')
                                            "
                                        >
                                            {{
                                                t(
                                                    'layouts.sections.appearance_font_family',
                                                    'Font',
                                                )
                                            }}
                                        </Label>
                                        <RwAutoCompleteInput
                                            :id="
                                                fieldId('language-font-family')
                                            "
                                            v-model="
                                                activeLanguageAppearance.font_family_token
                                            "
                                            :items="fontFamilyTokenOptions"
                                            item-title="label"
                                            item-value="value"
                                            :search-fields="['label', 'value']"
                                            :name="
                                                fieldName(
                                                    'language_font_family',
                                                )
                                            "
                                        />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label
                                            :for="fieldId('language-font-size')"
                                        >
                                            {{
                                                t(
                                                    'layouts.sections.appearance_font_size',
                                                    'Text size',
                                                )
                                            }}
                                        </Label>
                                        <RwAutoCompleteInput
                                            :id="fieldId('language-font-size')"
                                            v-model="
                                                activeLanguageAppearance.font_size_token
                                            "
                                            :items="fontSizeTokenOptions"
                                            item-title="label"
                                            item-value="value"
                                            :search-fields="['label', 'value']"
                                            :name="
                                                fieldName('language_font_size')
                                            "
                                        />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label
                                            :for="
                                                fieldId('language-font-weight')
                                            "
                                        >
                                            {{
                                                t(
                                                    'layouts.sections.appearance_font_weight',
                                                    'Font weight',
                                                )
                                            }}
                                        </Label>
                                        <RwAutoCompleteInput
                                            :id="
                                                fieldId('language-font-weight')
                                            "
                                            v-model="
                                                activeLanguageAppearance.font_weight
                                            "
                                            :items="fontWeightOptions"
                                            item-title="label"
                                            item-value="value"
                                            :search-fields="['label', 'value']"
                                            :name="
                                                fieldName(
                                                    'language_font_weight',
                                                )
                                            "
                                        />
                                    </div>
                                </div>
                            </div>

                            <div
                                class="grid gap-3 rounded-md border border-slate-200 bg-white p-3"
                            >
                                <div class="grid gap-1">
                                    <h3
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        {{
                                            t(
                                                'layouts.sections.language_colors_group',
                                                'Language menu colors',
                                            )
                                        }}
                                    </h3>
                                    <p class="text-xs text-slate-600">
                                        {{
                                            t(
                                                'layouts.sections.menu_colors_description',
                                                'Use theme tokens, hex colors, rgb/hsl values, transparent, currentColor or safe public CSS variables.',
                                            )
                                        }}
                                    </p>
                                </div>
                                <div class="grid gap-3 lg:grid-cols-2">
                                    <ColorPickerField
                                        v-for="colorField in menuColorFields"
                                        :key="colorField.value"
                                        :model-value="
                                            activeLanguageAppearance[
                                                colorField.value
                                            ]
                                        "
                                        :token-value="
                                            activeLanguageAppearance[
                                                `${colorField.value}_token`
                                            ]
                                        "
                                        :palette-items="paletteItems"
                                        :token-options="colorTokenOptions"
                                        :id-prefix="
                                            fieldId(
                                                `language-${colorField.value}`,
                                            )
                                        "
                                        :label="colorField.label"
                                        allow-css-color
                                        @update:model-value="
                                            activeLanguageAppearance[
                                                colorField.value
                                            ] = $event
                                        "
                                        @update:token-value="
                                            activeLanguageAppearance[
                                                `${colorField.value}_token`
                                            ] = $event
                                        "
                                        @update:palette-items="
                                            emit('update:paletteItems', $event)
                                        "
                                    />
                                </div>
                            </div>
                        </div>

                        <BoxSpacingEditor
                            v-model="placement.style_config.box"
                            :fixed-device="activeStyleDevice"
                            :id-prefix="fieldId('box')"
                            :title="t('layouts.box.block_title', 'Spacing')"
                            :description="
                                t(
                                    'layouts.box.block_description',
                                    'Stel marge en padding per device en per zijde in.',
                                )
                            "
                        />
                    </div>

                    <div
                        v-else-if="activeTab === 'code' && canManageCodeBlocks"
                        class="grid gap-4"
                    >
                        <div
                            v-if="codeEditorField(placement.block)"
                            class="grid gap-2"
                        >
                            <Label :for="fieldId('block-code')">
                                {{
                                    helpers.editorFieldLabel(
                                        codeEditorField(placement.block),
                                    )
                                }}
                            </Label>
                            <RwCodeEditor
                                :id="fieldId('block-code')"
                                v-model="
                                    placement.block[
                                        codeEditorField(placement.block).name
                                    ]
                                "
                                :language="
                                    codeEditorLanguage(
                                        codeEditorField(placement.block),
                                    )
                                "
                                :height="
                                    codeEditorHeight(
                                        codeEditorField(placement.block),
                                    )
                                "
                                :line-wrapping="true"
                                :placeholder="
                                    helpers.editorFieldPlaceholder(
                                        codeEditorField(placement.block),
                                    )
                                "
                            />
                        </div>

                        <p
                            v-if="!codeEditorField(placement.block)"
                            class="rounded-md border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600"
                        >
                            {{
                                t(
                                    'components.block_editor.developer_code_empty',
                                    'Geen codevelden beschikbaar voor dit blok.',
                                )
                            }}
                        </p>
                    </div>

                    <div
                        v-else-if="activeTab === 'css' && canManageCodeBlocks"
                        class="grid gap-4"
                    >
                        <div class="grid gap-2">
                            <Label :for="fieldId('developer-css-source')">
                                {{
                                    t(
                                        'components.block_editor.developer_css_draft',
                                        'Developer CSS concept',
                                    )
                                }}
                            </Label>
                            <RwCodeEditor
                                :id="fieldId('developer-css-source')"
                                v-model="
                                    placement.style_config.developer.css_source
                                "
                                language="css"
                                height="360px"
                                :line-wrapping="true"
                                :placeholder="
                                    t(
                                        'components.block_editor.developer_css_draft_placeholder',
                                        '/* CSS voor dit geplaatste block */',
                                    )
                                "
                            />
                            <p class="text-xs leading-5 text-orange-800">
                                {{
                                    t(
                                        'components.block_editor.developer_css_draft_description',
                                        'Deze instance CSS wordt bewaard als conceptconfiguratie en wordt nog niet publiek gerenderd. Publicatie volgt via de style revision-flow.',
                                    )
                                }}
                            </p>
                            <div
                                v-if="placement.id"
                                class="flex flex-wrap items-center gap-2"
                            >
                                <Button
                                    type="button"
                                    variant="outline"
                                    :aria-busy="
                                        isStyleRevisionProcessing('publish')
                                    "
                                    :disabled="
                                        !canPublishPlacementStyle() ||
                                        isStyleRevisionProcessing()
                                    "
                                    @click="publishPlacementStyleRevision"
                                >
                                    {{
                                        isStyleRevisionProcessing('publish')
                                            ? t(
                                                  'components.block_editor.publish_developer_css_processing',
                                                  'Publiceren...',
                                              )
                                            : t(
                                                  'components.block_editor.publish_developer_css',
                                                  'Publiceer CSS',
                                              )
                                    }}
                                </Button>
                                <Button
                                    v-if="placement.published_style_revision"
                                    type="button"
                                    variant="outline"
                                    :disabled="isStyleRevisionProcessing()"
                                    @click="restorePublishedStyleRevisionDraft"
                                >
                                    {{
                                        t(
                                            'components.block_editor.restore_published_css_draft',
                                            'Gebruik gepubliceerde CSS als concept',
                                        )
                                    }}
                                </Button>
                                <span
                                    v-if="placement.published_style_revision"
                                    class="text-xs text-slate-600"
                                >
                                    {{
                                        t(
                                            'components.block_editor.published_style_revision_label',
                                            'Gepubliceerde stijlrevisie #:number',
                                            {
                                                number: placement
                                                    .published_style_revision
                                                    .revision_number,
                                            },
                                        )
                                    }}
                                </span>
                            </div>
                            <p v-else class="text-xs leading-5 text-slate-600">
                                {{
                                    t(
                                        'components.block_editor.publish_css_after_save',
                                        'Bewaar dit block eerst voordat je instance CSS publiceert.',
                                    )
                                }}
                            </p>
                        </div>

                        <div class="grid gap-3">
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <h3
                                    class="text-sm font-semibold text-slate-900"
                                >
                                    {{
                                        t(
                                            'components.block_editor.style_revisions_title',
                                            'Stijlrevisies',
                                        )
                                    }}
                                </h3>
                                <span class="text-xs text-slate-500">
                                    {{ styleRevisions().length }}
                                </span>
                            </div>

                            <div
                                v-if="styleRevisions().length === 0"
                                class="rounded-md border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600"
                            >
                                {{
                                    t(
                                        'components.block_editor.style_revisions_empty',
                                        'Er zijn nog geen gepubliceerde stijlrevisies voor dit block.',
                                    )
                                }}
                            </div>

                            <div v-else class="grid gap-2">
                                <div
                                    v-for="revision in styleRevisions()"
                                    :key="revision.id"
                                    class="grid gap-2 rounded-md border border-slate-200 bg-white p-3"
                                >
                                    <div
                                        class="flex flex-wrap items-center justify-between gap-2"
                                    >
                                        <div
                                            class="flex flex-wrap items-center gap-2"
                                        >
                                            <span
                                                class="font-mono text-xs font-semibold text-slate-900"
                                            >
                                                #{{ revision.revision_number }}
                                            </span>
                                            <span
                                                v-if="revision.is_current"
                                                class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700 ring-1 ring-green-100"
                                            >
                                                {{
                                                    t(
                                                        'components.block_editor.style_revision_current',
                                                        'Live',
                                                    )
                                                }}
                                            </span>
                                            <span
                                                class="text-xs text-slate-500"
                                            >
                                                {{
                                                    revisionDateLabel(revision)
                                                }}
                                            </span>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                :disabled="
                                                    isStyleRevisionProcessing()
                                                "
                                                @click="
                                                    restoreStyleRevisionDraft(
                                                        revision,
                                                    )
                                                "
                                            >
                                                {{
                                                    t(
                                                        'components.block_editor.use_style_revision_as_draft',
                                                        'Gebruik als concept',
                                                    )
                                                }}
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                :aria-busy="
                                                    isStyleRevisionProcessing(
                                                        `republish:${revision.id}`,
                                                    )
                                                "
                                                :disabled="
                                                    revision.is_current ||
                                                    isStyleRevisionProcessing()
                                                "
                                                @click="
                                                    republishPlacementStyleRevision(
                                                        revision,
                                                    )
                                                "
                                            >
                                                {{
                                                    isStyleRevisionProcessing(
                                                        `republish:${revision.id}`,
                                                    )
                                                        ? t(
                                                              'components.block_editor.republish_style_revision_processing',
                                                              'Herpubliceren...',
                                                          )
                                                        : t(
                                                              'components.block_editor.republish_style_revision',
                                                              'Herpubliceer',
                                                          )
                                                }}
                                            </Button>
                                        </div>
                                    </div>
                                    <pre
                                        class="max-h-24 overflow-auto whitespace-pre-wrap rounded bg-slate-950 p-2 font-mono text-xs text-slate-100"
                                        >{{
                                            revision.css_preview ||
                                            revision.css_source
                                        }}</pre
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter
                class="shrink-0 flex-row items-center justify-end gap-2 border-t border-slate-200 px-6 py-4"
            >
                <DropdownMenu v-if="placement && canDelete" :modal="false">
                    <DropdownMenuTrigger as-child>
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            class="h-9 w-9 shrink-0 shadow-none"
                            :aria-label="t('actions.more', 'More actions')"
                            :title="t('actions.more', 'More actions')"
                        >
                            <span
                                class="mdi mdi-dots-vertical text-base"
                                aria-hidden="true"
                            />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-44">
                        <DropdownMenuItem as-child>
                            <button
                                type="button"
                                class="flex w-full items-center gap-2 text-red-700"
                                @click="emit('delete-requested')"
                            >
                                <span
                                    class="mdi mdi-delete"
                                    aria-hidden="true"
                                />
                                {{
                                    t(
                                        'components.block_editor.delete',
                                        'Delete',
                                    )
                                }}
                            </button>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="saving"
                    class="gap-2 border-green-200 text-green-700 shadow-none hover:bg-green-50 hover:text-green-800"
                    @click="handleSave"
                >
                    <span
                        v-if="saving"
                        class="mdi mdi-loading animate-spin text-base text-green-700"
                        aria-hidden="true"
                    />
                    <span
                        v-else
                        class="mdi mdi-content-save text-base"
                        :class="saveIconClass"
                        aria-hidden="true"
                    />
                    {{ t('actions.save', 'Bewaren') }}
                </Button>
            </DialogFooter>
        </DialogScrollContent>
    </Dialog>
</template>

<script setup>
import ColorPickerField from '@/Pages/Admin/Cms/Layouts/Partials/ColorPickerField.vue';
import BoxSpacingEditor from '@/Pages/Admin/Cms/Layouts/Partials/BoxSpacingEditor.vue';
import CmsPlacementSlotEditor from '@/Pages/Admin/Cms/Layouts/Partials/CmsPlacementSlotEditor.vue';
import RwCodeEditor from '@/Components/RwCodeEditor.vue';
import CmsRepeaterFieldEditor from '@/Pages/Admin/Cms/Components/CmsRepeaterFieldEditor.vue';
import CmsMediaPicker from '@/Pages/Admin/Cms/Components/CmsMediaPicker.vue';
import CmsRichTextEditor from '@/Pages/Admin/Cms/Components/CmsRichTextEditor.vue';
import RwAutoCompleteInput from '@/Components/RwAutoCompleteInput.vue';
import RwFlashMessage from '@/Components/RwFlashMessage.vue';
import FormValidationSummary from '@/Components/Validation/FormValidationSummary.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
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
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    placement: { type: Object, default: null },
    activeTab: { type: String, default: 'content' },
    tabs: { type: Array, default: () => [] },
    zone: { type: String, required: true },
    canManageCodeBlocks: { type: Boolean, default: false },
    formOptions: { type: Array, default: () => [] },
    menuOptions: { type: Array, default: () => [] },
    contactSettings: { type: Object, default: () => ({}) },
    mediaOptions: { type: Array, default: () => [] },
    mediaFolders: { type: Array, default: () => [] },
    downloadOptions: { type: Array, default: () => [] },
    downloadFolders: { type: Array, default: () => [] },
    spanOptions: { type: Array, default: () => [] },
    placementCacheOptions: { type: Array, default: () => [] },
    placeableBlocks: { type: Array, default: () => [] },
    slotDefinitions: { type: Array, default: () => [] },
    alignmentOptions: { type: Array, default: () => [] },
    contentAlignmentOptions: { type: Array, default: () => [] },
    contentVerticalAlignmentOptions: { type: Array, default: () => [] },
    paletteItems: { type: Array, default: () => [] },
    styleTokenOptions: { type: Object, default: () => ({}) },
    layoutLocale: { type: String, default: '' },
    initialStyleDevice: { type: String, default: 'desktop' },
    dialogFlash: {
        type: Object,
        default: () => ({ type: '', message: '', details: [] }),
    },
    saving: { type: Boolean, default: false },
    isSlotChild: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: true },
    helpers: { type: Object, required: true },
});

const emit = defineEmits([
    'update:open',
    'update:activeTab',
    'update:paletteItems',
    'update:mediaOptions',
    'update:mediaFolders',
    'delete-requested',
    'save-requested',
    'slot-child-settings-requested',
]);
const { t } = useAdminTranslations('cms_admin_ui');
const placement = computed(() => props.placement);

function updateMediaOptions(items) {
    emit('update:mediaOptions', [...items]);
}

function updateMediaFolders(items) {
    emit('update:mediaFolders', [...items]);
}

const styleDeviceOptions = [
    { value: 'desktop', label: t('layouts.sections.desktop', 'Desktop') },
    { value: 'tablet', label: t('layouts.sections.tablet', 'Tablet') },
    { value: 'mobile', label: t('layouts.sections.mobile', 'Mobile') },
];
const menuDisplayValues = ['horizontal', 'vertical', 'hamburger'];
const languageDisplayValues = ['horizontal', 'vertical', 'dropdown'];
const menuAlignmentValues = ['left', 'center', 'right'];
const menuVariantValues = ['plain', 'pill', 'underline', 'button'];
const menuSpacingValues = ['compact', 'normal', 'spacious'];
const formFieldSpacingValues = ['compact', 'normal', 'spacious'];
const formInputRadiusValues = ['inherit', 'none', 'sm', 'md', 'lg', 'pill'];
const formInputBorderValues = [
    'default',
    'none',
    'subtle',
    'strong',
    'primary',
];
const formSubmitAlignmentValues = [
    'inherit',
    'left',
    'center',
    'right',
    'stretch',
];
const formSubmitVariantValues = ['default', 'outline', 'ghost'];
const menuDrawerSideValues = ['left', 'right'];
const menuDrawerTopValues = ['viewport', 'below_sticky_header'];
const menuSubmenuBehaviorValues = ['hover'];
const menuSubmenuSideValues = ['left', 'right'];
const menuToggleIconValues = ['hamburger', 'dots', 'grid'];
const menuToggleShapeValues = ['pill', 'rounded', 'square', 'circle'];
const menuToggleSizeValues = ['compact', 'normal', 'large'];
const languageLabelDisplayValues = [
    'code',
    'name',
    'native_name',
    'code_name',
    'code_native_name',
    'flag_only',
    'flag_code',
    'flag_name',
    'flag_native_name',
];
const addressPhoneRows = [
    { label: 'phone_1_label', value: 'phone_1' },
    { label: 'phone_2_label', value: 'phone_2' },
    { label: 'phone_3_label', value: 'phone_3' },
];
const addressEmailRows = [
    { label: 'email_1_label', value: 'email_1' },
    { label: 'email_2_label', value: 'email_2' },
];
const addressCustomFieldRows = [
    { label: 'custom_field_1_label', value: 'custom_field_1_value' },
    { label: 'custom_field_2_label', value: 'custom_field_2_value' },
    { label: 'custom_field_3_label', value: 'custom_field_3_value' },
];
const languageFlagPositionValues = ['before', 'after'];
const languageFlagShapeValues = ['rectangle', 'rounded', 'circle'];
const languageFlagSizeValues = ['small', 'normal', 'large'];
const languageIconValues = [
    'none',
    'mdi-earth',
    'mdi-translate',
    'mdi-web',
    'mdi-flag-outline',
];
const colorTokenValues = [
    'page',
    'surface',
    'surface-muted',
    'text',
    'muted',
    'border',
    'primary',
    'primary-strong',
    'primary-contrast',
    'success',
    'success-bg',
    'error',
    'error-bg',
];
const fontFamilyTokenValues = ['inherit', 'body', 'heading', 'brand', 'accent'];
const fontSizeTokenValues = [
    'inherit',
    'body',
    'small',
    'nav',
    'brand',
    'baseline',
];
const fontWeightValues = ['inherit', 'normal', 'medium', 'semibold', 'bold'];
const typographyPresetValues = [
    'inherit',
    'h1',
    'h2',
    'h3',
    'h4',
    'h5',
    'h6',
    'body',
    'lead',
    'small',
    'caption',
    'eyebrow',
];
const menuColorFieldNames = [
    'text_color',
    'background_color',
    'hover_text_color',
    'hover_background_color',
    'pressed_text_color',
    'pressed_background_color',
    'active_text_color',
    'active_background_color',
];
const menuToggleColorFieldNames = [
    'color',
    'background_color',
    'hover_color',
    'hover_background_color',
];
const formColorFieldNames = ['input_background_color', 'input_text_color'];
const contentOverrideExcludedRendererKeys = [
    'breadcrumb',
    'content_slot',
    'dynamic_field',
    'form',
    'list_grid',
    'list_rows',
];
const activeStyleDevice = ref('desktop');
const dialogContentClass = computed(() => [
    'flex flex-col overflow-hidden p-0',
    props.isSlotChild
        ? 'z-[70] max-h-[calc(100vh-7rem)] max-w-4xl ring-2 ring-blue-200 sm:ml-[28vw] sm:mt-14 sm:self-start sm:justify-self-start'
        : 'max-h-[calc(100vh-2rem)] max-w-5xl',
]);
const settingsTitle = computed(() =>
    props.isSlotChild
        ? t(
              'components.block_editor.slot_child_settings_title',
              'Slot block settings',
          )
        : t('components.block_editor.settings_title', 'Block settings'),
);
const settingsDescription = computed(() =>
    props.isSlotChild
        ? t(
              'components.block_editor.slot_child_settings_description',
              'Manage content, responsive visibility and styling for this block inside the slot.',
          )
        : t(
              'components.block_editor.settings_description',
              'Manage content, layout, responsive visibility and styling for this placed block.',
          ),
);
const placementTitle = computed(() =>
    props.placement
        ? props.helpers.placeableBlockLabel(props.placement.block)
        : '',
);
const isLogoPlacement = computed(
    () =>
        props.helpers.placeableBlockRendererKey(placement.value?.block) ===
        'site_logo',
);
const isMenuPlacement = computed(
    () =>
        props.helpers.placeableBlockRendererKey(placement.value?.block) ===
        'site_menu',
);
const isLanguagePlacement = computed(
    () =>
        props.helpers.placeableBlockRendererKey(placement.value?.block) ===
        'site_language_switcher',
);
const isFormPlacement = computed(
    () =>
        props.helpers.placeableBlockRendererKey(placement.value?.block) ===
        'form',
);
const isPageOverrideEligible = computed(
    () =>
        props.zone === 'content' &&
        placement.value?.settings &&
        !props.helpers.isSystemBlock(placement.value?.block) &&
        !contentOverrideExcludedRendererKeys.includes(
            props.helpers.placeableBlockRendererKey(placement.value?.block),
        ) &&
        props.helpers.hasEditorFields(placement.value?.block),
);
const pageEditableContentFields = computed(() =>
    contentEditorFields(placement.value?.block || {}).filter(
        (field) => field.type !== 'code',
    ),
);
const filteredMenuOptions = computed(() =>
    props.menuOptions.filter(
        (menu) =>
            Array.isArray(menu.placements) &&
            menu.placements.includes(props.zone),
    ),
);
const styleRevisionProcessingAction = ref(null);
const initialPlacementSnapshot = ref('');
const showValidationSummary = ref(false);
const placeableBlockError = computed(() => {
    if (Number(placement.value?.block?.cms_placeable_block_id || 0) > 0) {
        return '';
    }

    return t('validation.required', 'This field is required.');
});
const validationErrors = computed(() => {
    const errors = [];

    if (placeableBlockError.value) {
        errors.push({
            name: 'placeable_block',
            label: t('components.block_editor.catalog_block', 'Blok'),
            error: placeableBlockError.value,
        });
    }

    return errors;
});
const isDirty = computed(() => {
    if (!props.open || !placement.value) {
        return false;
    }

    return (
        placementSnapshot(placement.value) !== initialPlacementSnapshot.value
    );
});
const saveIconClass = computed(() =>
    isDirty.value || validationErrors.value.length > 0
        ? 'text-red-600'
        : 'text-green-700',
);
const activeDeviceLabel = computed(
    () =>
        styleDeviceOptions.find(
            (device) => device.value === activeStyleDevice.value,
        )?.label || styleDeviceOptions[0].label,
);
const activeDeviceVisible = computed({
    get: () =>
        Boolean(
            placement.value?.[`visible_${activeStyleDevice.value}`] ?? true,
        ),
    set: (value) => {
        if (!placement.value) {
            return;
        }

        placement.value[`visible_${activeStyleDevice.value}`] = Boolean(value);
    },
});
const activeDeviceSpan = computed({
    get: () =>
        Number(placement.value?.[`${activeStyleDevice.value}_span`] || 12),
    set: (value) => {
        if (!placement.value) {
            return;
        }

        placement.value[`${activeStyleDevice.value}_span`] = Number(
            value || 12,
        );
    },
});
const activeDeviceStyle = computed(() => {
    if (!placement.value) {
        return defaultDeviceStyle();
    }

    return (
        placement.value.style_config?.devices?.[activeStyleDevice.value] ||
        defaultDeviceStyle()
    );
});
const activeMenuDeviceStyle = computed(() => {
    if (!placement.value) {
        return defaultMenuDeviceStyle(activeStyleDevice.value);
    }

    return (
        placement.value.style_config?.menu?.devices?.[
            activeStyleDevice.value
        ] || defaultMenuDeviceStyle(activeStyleDevice.value)
    );
});
const activeMenuToggle = computed(() => {
    if (!placement.value) {
        return defaultMenuToggle();
    }

    const deviceStyle = activeMenuDeviceStyle.value;

    if (!deviceStyle.toggle || typeof deviceStyle.toggle !== 'object') {
        deviceStyle.toggle = defaultMenuToggle();
    }

    return deviceStyle.toggle;
});
const activeMenuAppearance = computed(() => {
    if (!placement.value) {
        return defaultMenuAppearance();
    }

    return (
        placement.value.style_config?.menu?.appearance ||
        defaultMenuAppearance()
    );
});
const activeLanguageDeviceStyle = computed(() => {
    if (!placement.value) {
        return defaultLanguageDeviceStyle(activeStyleDevice.value);
    }

    return (
        placement.value.style_config?.language?.devices?.[
            activeStyleDevice.value
        ] || defaultLanguageDeviceStyle(activeStyleDevice.value)
    );
});
const activeLanguageAppearance = computed(() => {
    if (!placement.value) {
        return defaultMenuAppearance();
    }

    return (
        placement.value.style_config?.language?.appearance ||
        defaultMenuAppearance()
    );
});
const activeFormStyle = computed(() => {
    if (!placement.value) {
        return defaultFormStyle();
    }

    return placement.value.style_config?.form || defaultFormStyle();
});
const activeAppearanceContainer = computed(() => {
    if (!placement.value) {
        return defaultAppearanceContainer();
    }

    return (
        placement.value.style_config?.appearance_container ||
        defaultAppearanceContainer()
    );
});
const selectedLogoMediaAsset = computed(() => {
    const mediaAssetId = Number(placement.value?.block?.media_asset_id || 0);

    if (mediaAssetId <= 0) {
        return null;
    }

    return (
        props.mediaOptions.find(
            (asset) => Number(asset?.id || 0) === mediaAssetId,
        ) || null
    );
});
const logoAltMediaFallback = computed(() => {
    const asset = selectedLogoMediaAsset.value;

    if (!asset) {
        return '';
    }

    const locale = String(props.layoutLocale || '').trim();
    const localizedAlt = locale
        ? String(asset.translations?.[locale]?.alt_text || '').trim()
        : '';

    return localizedAlt || String(asset.alt_text || '').trim();
});

watch(
    () => [props.open, props.placement?.uid],
    () => {
        if (!props.open || !props.placement) {
            initialPlacementSnapshot.value = '';
            showValidationSummary.value = false;

            return;
        }

        activeStyleDevice.value = safeStyleDevice(props.initialStyleDevice);
        ensurePlacementStyleConfig(props.placement);
        initialPlacementSnapshot.value = placementSnapshot(props.placement);
        showValidationSummary.value = false;
    },
    { immediate: true },
);

watch(
    () => props.initialStyleDevice,
    (device) => {
        if (!props.open) {
            return;
        }

        activeStyleDevice.value = safeStyleDevice(device);
    },
);

function safeStyleDevice(device) {
    return styleDeviceOptions.some((option) => option.value === device)
        ? device
        : 'desktop';
}

function defaultDeviceStyle() {
    return {
        alignment: '',
        content_alignment: '',
        content_vertical_alignment: '',
        z_index: 'auto',
        appearance: {
            background_color: null,
            background_color_token: '',
            foreground_color: null,
            foreground_color_token: '',
            typography_preset: 'inherit',
            font_family_token: 'inherit',
            font_size_token: 'inherit',
            font_weight: 'inherit',
            logo_size: 'default',
            padding: 'none',
            radius: 'inherit',
            border: 'none',
            shadow: 'none',
        },
        box: {},
    };
}

function ensurePlacementStyleConfig(value) {
    ensureLanguageBlockDefaults(value);
    value.style_config ||= {};
    value.style_config.devices = normalizeStyleDevices(
        value.style_config,
        value.settings || {},
    );

    value.style_config.box ||= {};
    value.style_config.menu = normalizeMenuStyle(value.style_config.menu);
    value.style_config.language = normalizeLanguageStyle(
        value.style_config.language,
    );
    value.style_config.form = normalizeFormStyle(value.style_config.form);
    value.style_config.appearance_container = normalizeAppearanceContainer(
        value.style_config.appearance_container,
    );
    value.style_config.developer ||= { css_source: '' };
}

function ensureLanguageBlockDefaults(value) {
    if (
        props.helpers.placeableBlockRendererKey(value?.block) !==
        'site_language_switcher'
    ) {
        return;
    }

    value.block ||= {};
    value.block.label_display = languageLabelDisplayValues.includes(
        value.block.label_display,
    )
        ? value.block.label_display
        : 'flag_code';
    value.block.show_current = value.block.show_current ?? true;
    value.block.hide_missing_translations =
        value.block.hide_missing_translations ?? true;
    value.block.flag_position = languageFlagPositionValues.includes(
        value.block.flag_position,
    )
        ? value.block.flag_position
        : 'before';
    value.block.flag_shape = languageFlagShapeValues.includes(
        value.block.flag_shape,
    )
        ? value.block.flag_shape
        : 'rounded';
    value.block.flag_size = languageFlagSizeValues.includes(
        value.block.flag_size,
    )
        ? value.block.flag_size
        : 'normal';
}

function normalizeLanguageStyle(language) {
    const source = language && typeof language === 'object' ? language : {};
    const sourceDevices =
        source.devices && typeof source.devices === 'object'
            ? source.devices
            : {};

    return {
        devices: styleDeviceOptions.reduce((devices, device) => {
            const deviceSource =
                sourceDevices[device.value] &&
                typeof sourceDevices[device.value] === 'object'
                    ? sourceDevices[device.value]
                    : {};

            devices[device.value] = {
                display: languageDisplayValues.includes(deviceSource.display)
                    ? deviceSource.display
                    : defaultLanguageDeviceStyle(device.value).display,
                alignment: menuAlignmentValues.includes(deviceSource.alignment)
                    ? deviceSource.alignment
                    : defaultLanguageDeviceStyle(device.value).alignment,
                label: normalizeDeviceLabel(deviceSource.label),
                icon: normalizeLanguageIcon(deviceSource.icon),
            };

            return devices;
        }, {}),
        item_variant: menuVariantValues.includes(source.item_variant)
            ? source.item_variant
            : 'pill',
        spacing: menuSpacingValues.includes(source.spacing)
            ? source.spacing
            : 'normal',
        appearance: normalizeMenuAppearance(source.appearance),
        flag_position: languageFlagPositionValues.includes(source.flag_position)
            ? source.flag_position
            : 'before',
        flag_shape: languageFlagShapeValues.includes(source.flag_shape)
            ? source.flag_shape
            : 'rounded',
        flag_size: languageFlagSizeValues.includes(source.flag_size)
            ? source.flag_size
            : 'normal',
    };
}

function normalizeMenuStyle(menu) {
    const source = menu && typeof menu === 'object' ? menu : {};
    const sourceDevices =
        source.devices && typeof source.devices === 'object'
            ? source.devices
            : {};

    let fallbackToggle = null;

    return {
        devices: styleDeviceOptions.reduce((devices, device) => {
            const deviceSource =
                sourceDevices[device.value] &&
                typeof sourceDevices[device.value] === 'object'
                    ? sourceDevices[device.value]
                    : {};

            devices[device.value] = {
                display: menuDisplayValues.includes(deviceSource.display)
                    ? deviceSource.display
                    : defaultMenuDeviceStyle(device.value).display,
                alignment: menuAlignmentValues.includes(deviceSource.alignment)
                    ? deviceSource.alignment
                    : defaultMenuDeviceStyle(device.value).alignment,
                toggle_label: normalizeMenuToggleLabel(
                    deviceSource.toggle_label,
                ),
                toggle: normalizeMenuToggle(
                    deviceSource.toggle,
                    fallbackToggle,
                ),
            };
            fallbackToggle = devices[device.value].toggle;

            return devices;
        }, {}),
        item_variant: menuVariantValues.includes(source.item_variant)
            ? source.item_variant
            : 'pill',
        spacing: menuSpacingValues.includes(source.spacing)
            ? source.spacing
            : 'normal',
        drawer_side: menuDrawerSideValues.includes(source.drawer_side)
            ? source.drawer_side
            : 'right',
        drawer_top: menuDrawerTopValues.includes(source.drawer_top)
            ? source.drawer_top
            : 'viewport',
        submenu_behavior: menuSubmenuBehaviorValues.includes(
            source.submenu_behavior,
        )
            ? source.submenu_behavior
            : 'hover',
        submenu_side: menuSubmenuSideValues.includes(source.submenu_side)
            ? source.submenu_side
            : 'right',
        appearance: normalizeMenuAppearance(source.appearance),
    };
}

function normalizeFormStyle(form) {
    const source = form && typeof form === 'object' ? form : {};
    const allowedColorTokens = configuredTokenValues(
        props.styleTokenOptions?.color,
        colorTokenValues,
    );
    const allowedFontWeightTokens = configuredTokenValues(
        props.styleTokenOptions?.fontWeight,
        fontWeightValues,
    );
    const normalized = {
        field_spacing: formFieldSpacingValues.includes(source.field_spacing)
            ? source.field_spacing
            : 'normal',
        label_weight: allowedFontWeightTokens.includes(source.label_weight)
            ? source.label_weight
            : 'inherit',
        input_radius: formInputRadiusValues.includes(source.input_radius)
            ? source.input_radius
            : 'inherit',
        input_border: formInputBorderValues.includes(source.input_border)
            ? source.input_border
            : 'default',
        submit_alignment: formSubmitAlignmentValues.includes(
            source.submit_alignment,
        )
            ? source.submit_alignment
            : 'inherit',
        submit_variant: formSubmitVariantValues.includes(source.submit_variant)
            ? source.submit_variant
            : 'default',
    };

    formColorFieldNames.forEach((field) => {
        normalized[field] = normalizeCssColor(source[field]);
        normalized[`${field}_token`] = allowedColorTokens.includes(
            source[`${field}_token`],
        )
            ? source[`${field}_token`]
            : '';
    });

    return normalized;
}

function normalizeMenuToggle(toggle, fallbackToggle = null) {
    const source = toggle && typeof toggle === 'object' ? toggle : {};
    const fallback = fallbackToggle || defaultMenuToggle();
    const allowedColorTokens = configuredTokenValues(
        props.styleTokenOptions?.color,
        colorTokenValues,
    );
    const normalized = {
        icon: menuToggleIconValues.includes(source.icon)
            ? source.icon
            : fallback.icon,
        shape: menuToggleShapeValues.includes(source.shape)
            ? source.shape
            : fallback.shape,
        size: menuToggleSizeValues.includes(source.size)
            ? source.size
            : fallback.size,
    };

    menuToggleColorFieldNames.forEach((field) => {
        normalized[field] = Object.prototype.hasOwnProperty.call(source, field)
            ? normalizeCssColor(source[field])
            : fallback[field] || null;
        normalized[`${field}_token`] = allowedColorTokens.includes(
            Object.prototype.hasOwnProperty.call(source, `${field}_token`)
                ? source[`${field}_token`]
                : fallback[`${field}_token`] || '',
        )
            ? Object.prototype.hasOwnProperty.call(source, `${field}_token`)
                ? source[`${field}_token`]
                : fallback[`${field}_token`] || ''
            : '';
    });

    return normalized;
}

function normalizeMenuAppearance(appearance) {
    const source =
        appearance && typeof appearance === 'object' ? appearance : {};
    const allowedColorTokens = configuredTokenValues(
        props.styleTokenOptions?.color,
        colorTokenValues,
    );
    const allowedFontFamilyTokens = configuredTokenValues(
        props.styleTokenOptions?.fontFamily,
        fontFamilyTokenValues,
    );
    const allowedTypographyPresetTokens = configuredTokenValues(
        props.styleTokenOptions?.typographyPreset,
        typographyPresetValues,
    );
    const allowedFontSizeTokens = configuredTokenValues(
        props.styleTokenOptions?.fontSize,
        fontSizeTokenValues,
    );
    const allowedFontWeightTokens = configuredTokenValues(
        props.styleTokenOptions?.fontWeight,
        fontWeightValues,
    );
    const normalized = {
        typography_preset: allowedTypographyPresetTokens.includes(
            source.typography_preset,
        )
            ? source.typography_preset
            : 'inherit',
        font_family_token: allowedFontFamilyTokens.includes(
            source.font_family_token,
        )
            ? source.font_family_token
            : 'inherit',
        font_size_token: allowedFontSizeTokens.includes(source.font_size_token)
            ? source.font_size_token
            : 'inherit',
        font_weight: allowedFontWeightTokens.includes(source.font_weight)
            ? source.font_weight
            : 'inherit',
    };

    menuColorFieldNames.forEach((field) => {
        normalized[field] = normalizeCssColor(source[field]);
        normalized[`${field}_token`] = allowedColorTokens.includes(
            source[`${field}_token`],
        )
            ? source[`${field}_token`]
            : '';
    });

    return normalized;
}

function configuredTokenValues(configuredOptions, fallbackValues) {
    return tokenOptions(
        configuredOptions,
        fallbackValues.map((value) => ({ value, label: value })),
    ).map((option) => option.value);
}

function normalizeHexColor(value) {
    const color = String(value || '').trim();
    const match = color.match(/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/);

    if (!match) {
        return null;
    }

    let hex = match[1].toLowerCase();

    if (hex.length === 3) {
        hex = hex
            .split('')
            .map((character) => character + character)
            .join('');
    }

    return `#${hex}`;
}

function normalizeCssColor(value) {
    const color = String(value || '').trim();

    if (color === '') {
        return null;
    }

    if (color.length > 120 || /[;{}<>]|url\s*\(|expression\s*\(/i.test(color)) {
        return null;
    }

    if (/^#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/.test(color)) {
        const hex = color.slice(1).toLowerCase();

        if (hex.length === 3 || hex.length === 4) {
            return `#${hex
                .split('')
                .map((character) => character + character)
                .join('')}`;
        }

        return `#${hex}`;
    }

    if (['transparent', 'currentColor'].includes(color)) {
        return color;
    }

    if (/^var\(--rw-public-[a-z0-9_-]+\)$/.test(color)) {
        return color;
    }

    if (/^(?:rgb|rgba|hsl|hsla)\([0-9.%\s,/+-]+\)$/.test(color)) {
        return color.replace(/\s+/g, ' ');
    }

    return null;
}

function defaultMenuDeviceStyle(device) {
    return {
        display: device === 'desktop' ? 'horizontal' : 'hamburger',
        alignment: 'right',
        toggle_label: '',
        toggle: defaultMenuToggle(),
    };
}

function defaultLanguageDeviceStyle(device) {
    return {
        display: device === 'desktop' ? 'horizontal' : 'dropdown',
        alignment: 'right',
        label: '',
        icon: 'none',
    };
}

function defaultMenuToggle() {
    return {
        icon: 'hamburger',
        shape: 'pill',
        size: 'normal',
        color: null,
        color_token: '',
        background_color: null,
        background_color_token: '',
        hover_color: null,
        hover_color_token: '',
        hover_background_color: null,
        hover_background_color_token: '',
    };
}

function normalizeMenuToggleLabel(value) {
    const label = String(value || '').trim();

    return label.length > 120 ? label.slice(0, 120) : label;
}

function normalizeDeviceLabel(value) {
    const label = String(value || '').trim();

    return label.length > 120 ? label.slice(0, 120) : label;
}

function normalizeLanguageIcon(value) {
    const icon = String(value || '').trim();

    if (languageIconValues.includes(icon)) {
        return icon;
    }

    return /^mdi-[a-z0-9-]+$/.test(icon) && icon.length <= 64 ? icon : 'none';
}

function languageIconClass(value) {
    const icon = normalizeLanguageIcon(value);

    return icon === 'none' ? '' : icon;
}

function defaultMenuAppearance() {
    return normalizeMenuAppearance({});
}

function defaultFormStyle() {
    return normalizeFormStyle({});
}

function defaultAppearanceContainer() {
    return normalizeAppearanceContainer({});
}

function normalizeAppearanceContainer(container) {
    const source = container && typeof container === 'object' ? container : {};

    return {
        enabled: source.enabled === true,
    };
}

function normalizeStyleDevices(styleConfig, settings = {}) {
    const legacyAppearance =
        styleConfig?.appearance && typeof styleConfig.appearance === 'object'
            ? styleConfig.appearance
            : {};
    const legacyDesktop = {
        alignment: settings.alignment || '',
        content_alignment: settings.content_alignment || '',
        content_vertical_alignment: settings.content_vertical_alignment || '',
        appearance: legacyAppearance,
    };

    return styleDeviceOptions.reduce((devices, device) => {
        const source =
            styleConfig?.devices?.[device.value] ||
            styleConfig?.[device.value] ||
            (device.value === 'desktop' ? legacyDesktop : {});

        devices[device.value] = normalizeDeviceStyle(
            source,
            device.value === 'desktop' ? null : devices.desktop,
        );

        return devices;
    }, {});
}

function normalizeDeviceStyle(style, fallbackStyle = null) {
    const normalized = defaultDeviceStyle();
    const source = style && typeof style === 'object' ? style : {};
    const appearance =
        source.appearance && typeof source.appearance === 'object'
            ? source.appearance
            : {};
    const fallback =
        fallbackStyle && typeof fallbackStyle === 'object'
            ? fallbackStyle
            : defaultDeviceStyle();

    normalized.alignment = ['left', 'center', 'right'].includes(
        source.alignment,
    )
        ? source.alignment
        : fallbackStyle
          ? fallback.alignment
          : '';
    normalized.content_alignment = ['left', 'center', 'right'].includes(
        source.content_alignment,
    )
        ? source.content_alignment
        : fallbackStyle
          ? fallback.content_alignment
          : '';
    normalized.content_vertical_alignment = [
        'top',
        'middle',
        'bottom',
    ].includes(source.content_vertical_alignment)
        ? source.content_vertical_alignment
        : fallbackStyle
          ? fallback.content_vertical_alignment
          : '';
    normalized.z_index = ['auto', '0', '10', '20', '30', '40', '50'].includes(
        source.z_index,
    )
        ? source.z_index
        : fallbackStyle
          ? fallback.z_index
          : 'auto';
    normalized.appearance = normalizeDeviceAppearance(
        appearance,
        fallbackStyle ? fallback.appearance : normalized.appearance,
    );
    normalized.box =
        source.box && typeof source.box === 'object' ? source.box : {};

    return normalized;
}

function normalizeDeviceAppearance(appearance, fallbackAppearance) {
    const defaultAppearance = defaultDeviceStyle().appearance;
    const fallback =
        fallbackAppearance && typeof fallbackAppearance === 'object'
            ? { ...defaultAppearance, ...fallbackAppearance }
            : defaultAppearance;
    const allowedColorTokens = configuredTokenValues(
        props.styleTokenOptions?.color,
        colorTokenValues,
    );
    const allowedFontFamilyTokens = configuredTokenValues(
        props.styleTokenOptions?.fontFamily,
        fontFamilyTokenValues,
    );
    const allowedTypographyPresetTokens = configuredTokenValues(
        props.styleTokenOptions?.typographyPreset,
        typographyPresetValues,
    );
    const allowedFontSizeTokens = configuredTokenValues(
        props.styleTokenOptions?.fontSize,
        fontSizeTokenValues,
    );
    const allowedFontWeightTokens = configuredTokenValues(
        props.styleTokenOptions?.fontWeight,
        fontWeightValues,
    );

    return {
        background_color:
            normalizeHexColor(appearance?.background_color) ??
            fallback.background_color,
        background_color_token: allowedColorTokens.includes(
            appearance?.background_color_token,
        )
            ? appearance.background_color_token
            : fallback.background_color_token,
        foreground_color:
            normalizeHexColor(appearance?.foreground_color) ??
            fallback.foreground_color,
        foreground_color_token: allowedColorTokens.includes(
            appearance?.foreground_color_token,
        )
            ? appearance.foreground_color_token
            : fallback.foreground_color_token,
        typography_preset: allowedTypographyPresetTokens.includes(
            appearance?.typography_preset,
        )
            ? appearance.typography_preset
            : fallback.typography_preset,
        font_family_token: allowedFontFamilyTokens.includes(
            appearance?.font_family_token,
        )
            ? appearance.font_family_token
            : fallback.font_family_token,
        font_size_token: allowedFontSizeTokens.includes(
            appearance?.font_size_token,
        )
            ? appearance.font_size_token
            : fallback.font_size_token,
        font_weight: allowedFontWeightTokens.includes(appearance?.font_weight)
            ? appearance.font_weight
            : fallback.font_weight,
        logo_size: ['small', 'default', 'large'].includes(appearance?.logo_size)
            ? appearance.logo_size
            : fallback.logo_size,
        padding: ['none', 'sm', 'md', 'lg'].includes(appearance?.padding)
            ? appearance.padding
            : fallback.padding,
        radius: ['inherit', 'none', 'sm', 'md', 'lg'].includes(
            appearance?.radius,
        )
            ? appearance.radius
            : fallback.radius,
        border: ['none', 'subtle', 'strong', 'primary'].includes(
            appearance?.border,
        )
            ? appearance.border
            : fallback.border,
        shadow: ['none', 'sm', 'md', 'lg'].includes(appearance?.shadow)
            ? appearance.shadow
            : fallback.shadow,
    };
}

function styleDeviceChipClasses(value) {
    const isActive = activeStyleDevice.value === value;

    return [
        'inline-flex items-center gap-1 rounded-full border px-3 py-1 text-xs font-medium transition focus:outline-none focus:ring-2 focus:ring-blue-200',
        isActive
            ? 'border-blue-300 bg-blue-50 text-blue-700'
            : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-900',
    ];
}

watch(
    () => [props.dialogFlash?.type, props.dialogFlash?.message],
    () => {
        if (props.dialogFlash?.type !== 'success' || !props.placement) {
            return;
        }

        initialPlacementSnapshot.value = placementSnapshot(props.placement);
        showValidationSummary.value = false;
    },
);

function handleSave() {
    showValidationSummary.value = true;

    if (validationErrors.value.length > 0) {
        return;
    }

    emit('save-requested');
}

function updatePlacementSlots(slots) {
    if (!placement.value) {
        return;
    }

    placement.value.slots = slots && typeof slots === 'object' ? slots : {};
}

function placementSnapshot(value) {
    return stableStringify({
        block: value?.block ?? {},
        cache_strategy: value?.cache_strategy ?? 'inherit',
        desktop_span: value?.desktop_span ?? 12,
        height_mode: value?.height_mode ?? 'auto',
        height_value: value?.height_value ?? null,
        is_active: Boolean(value?.is_active ?? true),
        layout_config: value?.layout_config ?? {},
        mobile_span: value?.mobile_span ?? 12,
        settings: value?.settings ?? {},
        slots: value?.slots ?? {},
        style_config: value?.style_config ?? {},
        tablet_span: value?.tablet_span ?? 12,
        visible_desktop: Boolean(value?.visible_desktop ?? true),
        visible_mobile: Boolean(value?.visible_mobile ?? true),
        visible_tablet: Boolean(value?.visible_tablet ?? true),
    });
}

function stableStringify(value) {
    if (Array.isArray(value)) {
        return `[${value.map((item) => stableStringify(item)).join(',')}]`;
    }

    if (value && typeof value === 'object') {
        return `{${Object.keys(value)
            .sort()
            .map(
                (key) =>
                    `${JSON.stringify(key)}:${stableStringify(value[key])}`,
            )
            .join(',')}}`;
    }

    return JSON.stringify(value);
}
const radiusStyleOptions = [
    {
        value: 'inherit',
        label: t('layouts.sections.appearance_inherit', 'Overerven'),
    },
    { value: 'none', label: t('layouts.sections.appearance_none', 'Geen') },
    { value: 'sm', label: t('layouts.sections.appearance_size_sm', 'Klein') },
    { value: 'md', label: t('layouts.sections.appearance_size_md', 'Normaal') },
    { value: 'lg', label: t('layouts.sections.appearance_size_lg', 'Ruim') },
];
const logoSizeOptions = [
    {
        value: 'small',
        label: t('components.block_editor.logo_size_small', 'Small'),
    },
    {
        value: 'default',
        label: t('components.block_editor.logo_size_default', 'Default'),
    },
    {
        value: 'large',
        label: t('components.block_editor.logo_size_large', 'Large'),
    },
];
const borderStyleOptions = [
    { value: 'none', label: t('layouts.sections.appearance_none', 'Geen') },
    {
        value: 'subtle',
        label: t('layouts.sections.appearance_border_subtle', 'Subtiel'),
    },
    {
        value: 'strong',
        label: t('layouts.sections.appearance_border_strong', 'Sterk'),
    },
    {
        value: 'primary',
        label: t('layouts.sections.appearance_border_primary', 'Accent'),
    },
];
const shadowStyleOptions = [
    { value: 'none', label: t('layouts.sections.appearance_none', 'Geen') },
    { value: 'sm', label: t('layouts.sections.appearance_size_sm', 'Klein') },
    { value: 'md', label: t('layouts.sections.appearance_size_md', 'Normaal') },
    { value: 'lg', label: t('layouts.sections.appearance_size_lg', 'Ruim') },
];
const zIndexOptions = [
    { value: 'auto', label: t('layouts.sections.z_index_auto', 'Automatic') },
    { value: '0', label: '0' },
    { value: '10', label: '10' },
    { value: '20', label: '20' },
    { value: '30', label: '30' },
    { value: '40', label: '40' },
    { value: '50', label: '50' },
];
const menuColorFields = computed(() => [
    {
        value: 'text_color',
        label: t('layouts.sections.menu_color_text', 'Text color'),
    },
    {
        value: 'background_color',
        label: t('layouts.sections.menu_color_background', 'Background color'),
    },
    {
        value: 'hover_text_color',
        label: t('layouts.sections.menu_color_hover_text', 'Hover text color'),
    },
    {
        value: 'hover_background_color',
        label: t(
            'layouts.sections.menu_color_hover_background',
            'Hover background color',
        ),
    },
    {
        value: 'pressed_text_color',
        label: t(
            'layouts.sections.menu_color_pressed_text',
            'Clicked text color',
        ),
    },
    {
        value: 'pressed_background_color',
        label: t(
            'layouts.sections.menu_color_pressed_background',
            'Clicked background color',
        ),
    },
    {
        value: 'active_text_color',
        label: t(
            'layouts.sections.menu_color_active_text',
            'Current page text color',
        ),
    },
    {
        value: 'active_background_color',
        label: t(
            'layouts.sections.menu_color_active_background',
            'Current page background color',
        ),
    },
]);
const menuToggleColorFields = computed(() => [
    {
        value: 'color',
        label: t('layouts.sections.menu_toggle_color', 'Button text color'),
    },
    {
        value: 'background_color',
        label: t(
            'layouts.sections.menu_toggle_background_color',
            'Button background color',
        ),
    },
    {
        value: 'hover_color',
        label: t(
            'layouts.sections.menu_toggle_hover_color',
            'Button hover text color',
        ),
    },
    {
        value: 'hover_background_color',
        label: t(
            'layouts.sections.menu_toggle_hover_background_color',
            'Button hover background color',
        ),
    },
]);
const formColorFields = computed(() => [
    {
        value: 'input_background_color',
        label: t(
            'layouts.sections.form_input_background_color',
            'Input background color',
        ),
    },
    {
        value: 'input_text_color',
        label: t('layouts.sections.form_input_text_color', 'Input text color'),
    },
]);
const menuDisplayOptions = [
    {
        value: 'horizontal',
        label: t('layouts.sections.menu_display_horizontal', 'Horizontal'),
    },
    {
        value: 'vertical',
        label: t('layouts.sections.menu_display_vertical', 'Vertical'),
    },
    {
        value: 'hamburger',
        label: t('layouts.sections.menu_display_hamburger', 'Hamburger'),
    },
];
const languageDisplayOptions = [
    {
        value: 'horizontal',
        label: t('layouts.sections.language_display_horizontal', 'Horizontal'),
    },
    {
        value: 'vertical',
        label: t('layouts.sections.language_display_vertical', 'Vertical'),
    },
    {
        value: 'dropdown',
        label: t('layouts.sections.language_display_dropdown', 'Dropdown'),
    },
];
const languageLabelDisplayOptions = [
    {
        value: 'code',
        label: t('components.block_editor.language_label_code', 'Code'),
    },
    {
        value: 'name',
        label: t(
            'components.block_editor.language_label_name',
            'Name in site language',
        ),
    },
    {
        value: 'native_name',
        label: t(
            'components.block_editor.language_label_native_name',
            'Native name',
        ),
    },
    {
        value: 'code_name',
        label: t(
            'components.block_editor.language_label_code_name',
            'Code and name',
        ),
    },
    {
        value: 'code_native_name',
        label: t(
            'components.block_editor.language_label_code_native_name',
            'Code and native name',
        ),
    },
    {
        value: 'flag_only',
        label: t(
            'components.block_editor.language_label_flag_only',
            'Flag only',
        ),
    },
    {
        value: 'flag_code',
        label: t(
            'components.block_editor.language_label_flag_code',
            'Flag and code',
        ),
    },
    {
        value: 'flag_name',
        label: t(
            'components.block_editor.language_label_flag_name',
            'Flag and name',
        ),
    },
    {
        value: 'flag_native_name',
        label: t(
            'components.block_editor.language_label_flag_native_name',
            'Flag and native name',
        ),
    },
];
const menuAlignmentOptions = [
    { value: 'left', label: t('layouts.sections.align_left', 'Left') },
    { value: 'center', label: t('layouts.sections.align_center', 'Center') },
    { value: 'right', label: t('layouts.sections.align_right', 'Right') },
];
const menuVariantOptions = [
    {
        value: 'plain',
        label: t('layouts.sections.menu_variant_plain', 'Plain'),
    },
    { value: 'pill', label: t('layouts.sections.menu_variant_pill', 'Pill') },
    {
        value: 'underline',
        label: t('layouts.sections.menu_variant_underline', 'Underline'),
    },
    {
        value: 'button',
        label: t('layouts.sections.menu_variant_button', 'Button'),
    },
];
const menuSpacingOptions = [
    {
        value: 'compact',
        label: t('layouts.sections.menu_spacing_compact', 'Compact'),
    },
    {
        value: 'normal',
        label: t('layouts.sections.menu_spacing_normal', 'Normal'),
    },
    {
        value: 'spacious',
        label: t('layouts.sections.menu_spacing_spacious', 'Spacious'),
    },
];
const formFieldSpacingOptions = [
    {
        value: 'compact',
        label: t('layouts.sections.form_spacing_compact', 'Compact'),
    },
    {
        value: 'normal',
        label: t('layouts.sections.form_spacing_normal', 'Normal'),
    },
    {
        value: 'spacious',
        label: t('layouts.sections.form_spacing_spacious', 'Spacious'),
    },
];
const formInputRadiusOptions = [
    {
        value: 'inherit',
        label: t('layouts.sections.appearance_inherit', 'Inherit'),
    },
    { value: 'none', label: t('layouts.sections.appearance_none', 'None') },
    { value: 'sm', label: t('layouts.sections.appearance_size_sm', 'Small') },
    { value: 'md', label: t('layouts.sections.appearance_size_md', 'Normal') },
    {
        value: 'lg',
        label: t('layouts.sections.appearance_size_lg', 'Spacious'),
    },
    { value: 'pill', label: t('layouts.sections.form_radius_pill', 'Pill') },
];
const formInputBorderOptions = [
    {
        value: 'default',
        label: t('layouts.sections.form_style_default', 'Default'),
    },
    { value: 'none', label: t('layouts.sections.appearance_none', 'None') },
    {
        value: 'subtle',
        label: t('layouts.sections.appearance_border_subtle', 'Subtle'),
    },
    {
        value: 'strong',
        label: t('layouts.sections.appearance_border_strong', 'Strong'),
    },
    {
        value: 'primary',
        label: t('layouts.sections.appearance_border_primary', 'Accent'),
    },
];
const formSubmitAlignmentOptions = [
    {
        value: 'inherit',
        label: t('layouts.sections.appearance_inherit', 'Inherit'),
    },
    { value: 'left', label: t('layouts.sections.align_left', 'Left') },
    { value: 'center', label: t('layouts.sections.align_center', 'Center') },
    { value: 'right', label: t('layouts.sections.align_right', 'Right') },
    {
        value: 'stretch',
        label: t('layouts.sections.form_submit_stretch', 'Full width'),
    },
];
const formSubmitVariantOptions = [
    {
        value: 'default',
        label: t('layouts.sections.form_style_default', 'Default'),
    },
    {
        value: 'outline',
        label: t('layouts.sections.form_submit_outline', 'Outline'),
    },
    {
        value: 'ghost',
        label: t('layouts.sections.form_submit_ghost', 'Ghost'),
    },
];
const menuDrawerSideOptions = [
    { value: 'left', label: t('layouts.sections.menu_drawer_left', 'Left') },
    { value: 'right', label: t('layouts.sections.menu_drawer_right', 'Right') },
];
const menuDrawerTopOptions = [
    {
        value: 'viewport',
        label: t(
            'layouts.sections.menu_drawer_top_viewport',
            'Top of viewport',
        ),
    },
    {
        value: 'below_sticky_header',
        label: t(
            'layouts.sections.menu_drawer_top_below_sticky_header',
            'Below sticky header',
        ),
    },
];
const menuSubmenuBehaviorOptions = [
    {
        value: 'hover',
        label: t('layouts.sections.menu_submenu_hover', 'Hover'),
    },
];
const menuSubmenuSideOptions = [
    { value: 'left', label: t('layouts.sections.menu_submenu_left', 'Left') },
    {
        value: 'right',
        label: t('layouts.sections.menu_submenu_right', 'Right'),
    },
];
const menuToggleIconOptions = [
    {
        value: 'hamburger',
        label: t('layouts.sections.menu_toggle_icon_hamburger', 'Hamburger'),
    },
    {
        value: 'dots',
        label: t('layouts.sections.menu_toggle_icon_dots', 'Dots'),
    },
    {
        value: 'grid',
        label: t('layouts.sections.menu_toggle_icon_grid', 'Grid'),
    },
];
const languageIconOptions = [
    {
        value: 'none',
        label: t('layouts.sections.language_device_icon_none', 'No icon'),
    },
    {
        value: 'mdi-earth',
        label: t('layouts.sections.language_device_icon_globe', 'Globe'),
    },
    {
        value: 'mdi-translate',
        label: t(
            'layouts.sections.language_device_icon_translate',
            'Translate',
        ),
    },
    {
        value: 'mdi-web',
        label: t('layouts.sections.language_device_icon_web', 'Web'),
    },
    {
        value: 'mdi-flag-outline',
        label: t('layouts.sections.language_device_icon_flag', 'Flag'),
    },
];
const menuToggleShapeOptions = [
    {
        value: 'pill',
        label: t('layouts.sections.menu_toggle_shape_pill', 'Pill'),
    },
    {
        value: 'rounded',
        label: t('layouts.sections.menu_toggle_shape_rounded', 'Rounded'),
    },
    {
        value: 'square',
        label: t('layouts.sections.menu_toggle_shape_square', 'Square'),
    },
    {
        value: 'circle',
        label: t('layouts.sections.menu_toggle_shape_circle', 'Circle'),
    },
];
const menuToggleSizeOptions = [
    {
        value: 'compact',
        label: t('layouts.sections.menu_toggle_size_compact', 'Compact'),
    },
    {
        value: 'normal',
        label: t('layouts.sections.menu_toggle_size_normal', 'Normal'),
    },
    {
        value: 'large',
        label: t('layouts.sections.menu_toggle_size_large', 'Large'),
    },
];
const languageFlagPositionOptions = [
    {
        value: 'before',
        label: t('layouts.sections.language_flag_before', 'Before label'),
    },
    {
        value: 'after',
        label: t('layouts.sections.language_flag_after', 'After label'),
    },
];
const languageFlagShapeOptions = [
    {
        value: 'rectangle',
        label: t('layouts.sections.language_flag_rectangle', 'Rectangle'),
    },
    {
        value: 'rounded',
        label: t('layouts.sections.language_flag_rounded', 'Rounded'),
    },
    {
        value: 'circle',
        label: t('layouts.sections.language_flag_circle', 'Circle'),
    },
];
const languageFlagSizeOptions = [
    {
        value: 'small',
        label: t('layouts.sections.language_flag_small', 'Small'),
    },
    {
        value: 'normal',
        label: t('layouts.sections.language_flag_normal', 'Normal'),
    },
    {
        value: 'large',
        label: t('layouts.sections.language_flag_large', 'Large'),
    },
];
const colorTokenOptions = computed(() =>
    tokenOptions(props.styleTokenOptions?.color, [
        { value: 'page', label: t('layouts.sections.color_page', 'Page') },
        {
            value: 'surface',
            label: t('layouts.sections.color_surface', 'Surface'),
        },
        {
            value: 'surface-muted',
            label: t('layouts.sections.color_surface_muted', 'Muted surface'),
        },
        { value: 'text', label: t('layouts.sections.color_text', 'Text') },
        {
            value: 'muted',
            label: t('layouts.sections.color_muted', 'Muted text'),
        },
        {
            value: 'border',
            label: t('layouts.sections.color_border', 'Border'),
        },
        {
            value: 'primary',
            label: t('layouts.sections.color_primary', 'Primary'),
        },
        {
            value: 'primary-strong',
            label: t('layouts.sections.color_primary_strong', 'Primary strong'),
        },
        {
            value: 'primary-contrast',
            label: t(
                'layouts.sections.color_primary_contrast',
                'Primary contrast',
            ),
        },
        {
            value: 'success',
            label: t('layouts.sections.color_success', 'Success'),
        },
        {
            value: 'success-bg',
            label: t('layouts.sections.color_success_bg', 'Success background'),
        },
        { value: 'error', label: t('layouts.sections.color_error', 'Error') },
        {
            value: 'error-bg',
            label: t('layouts.sections.color_error_bg', 'Error background'),
        },
    ]),
);
const fontFamilyTokenOptions = computed(() =>
    tokenOptions(props.styleTokenOptions?.fontFamily, [
        {
            value: 'inherit',
            label: t('layouts.sections.appearance_inherit', 'Inherit'),
        },
        {
            value: 'body',
            label: t('layouts.sections.font_family_body', 'Body'),
        },
        {
            value: 'heading',
            label: t('layouts.sections.font_family_heading', 'Heading'),
        },
        {
            value: 'brand',
            label: t('layouts.sections.font_family_brand', 'Brand'),
        },
        {
            value: 'accent',
            label: t('layouts.sections.font_family_accent', 'Accent'),
        },
    ]),
);

function fontFamilyPreviewStyle(value) {
    const fontFamily = fontFamilyPreviewValue(value);

    return fontFamily ? { fontFamily } : null;
}

function fontFamilyPreviewValue(value) {
    const token = String(value || 'inherit');
    const option = fontFamilyTokenOptions.value.find(
        (item) => item.value === token,
    );

    if (typeof option?.css_value === 'string' && option.css_value.trim()) {
        return option.css_value.trim();
    }

    if (token === 'inherit') {
        return 'inherit';
    }

    if (!fontFamilyTokenValues.includes(token)) {
        return 'inherit';
    }

    const fallback = {
        body: 'Inter, ui-sans-serif, system-ui, sans-serif',
        heading:
            'var(--rw-public-font-body, Inter, ui-sans-serif, system-ui, sans-serif)',
        brand: 'var(--rw-public-font-heading, var(--rw-public-font-body, Inter, ui-sans-serif, system-ui, sans-serif))',
        accent: 'var(--rw-public-font-heading, var(--rw-public-font-body, Inter, ui-sans-serif, system-ui, sans-serif))',
    }[token];

    return `var(--rw-public-font-${token}, ${fallback})`;
}

function fontFamilyOptionDetail(value) {
    const token = String(value || 'inherit');
    const option = fontFamilyTokenOptions.value.find(
        (item) => item.value === token,
    );

    if (token === 'inherit') {
        return t(
            'layouts.sections.font_family_inherit_help',
            'Inherits from the parent style',
        );
    }

    if (typeof option?.css_value === 'string' && option.css_value.trim()) {
        return option.css_value.trim();
    }

    return `--rw-public-font-${token}`;
}

const fontSizeTokenOptions = computed(() =>
    tokenOptions(props.styleTokenOptions?.fontSize, [
        {
            value: 'inherit',
            label: t('layouts.sections.appearance_inherit', 'Inherit'),
        },
        { value: 'body', label: t('layouts.sections.font_size_body', 'Body') },
        {
            value: 'small',
            label: t('layouts.sections.font_size_small', 'Small'),
        },
        {
            value: 'nav',
            label: t('layouts.sections.font_size_nav', 'Navigation'),
        },
        {
            value: 'brand',
            label: t('layouts.sections.font_size_brand', 'Brand'),
        },
        {
            value: 'baseline',
            label: t('layouts.sections.font_size_baseline', 'Baseline'),
        },
    ]),
);
const fontWeightOptions = computed(() =>
    tokenOptions(props.styleTokenOptions?.fontWeight, [
        {
            value: 'inherit',
            label: t('layouts.sections.appearance_inherit', 'Inherit'),
        },
        {
            value: 'normal',
            label: t('layouts.sections.font_weight_normal', 'Normal'),
        },
        {
            value: 'medium',
            label: t('layouts.sections.font_weight_medium', 'Medium'),
        },
        {
            value: 'semibold',
            label: t('layouts.sections.font_weight_semibold', 'Semibold'),
        },
        {
            value: 'bold',
            label: t('layouts.sections.font_weight_bold', 'Bold'),
        },
    ]),
);

const typographyPresetOptions = computed(() =>
    tokenOptions(props.styleTokenOptions?.typographyPreset, [
        {
            value: 'inherit',
            label: t('layouts.sections.appearance_inherit', 'Inherit'),
        },
        { value: 'h1', label: t('layouts.sections.typography_h1', 'H1') },
        { value: 'h2', label: t('layouts.sections.typography_h2', 'H2') },
        { value: 'h3', label: t('layouts.sections.typography_h3', 'H3') },
        { value: 'h4', label: t('layouts.sections.typography_h4', 'H4') },
        { value: 'h5', label: t('layouts.sections.typography_h5', 'H5') },
        { value: 'h6', label: t('layouts.sections.typography_h6', 'H6') },
        {
            value: 'body',
            label: t('layouts.sections.typography_body', 'Body'),
        },
        {
            value: 'lead',
            label: t('layouts.sections.typography_lead', 'Lead'),
        },
        {
            value: 'small',
            label: t('layouts.sections.typography_small', 'Small'),
        },
        {
            value: 'caption',
            label: t('layouts.sections.typography_caption', 'Caption'),
        },
        {
            value: 'eyebrow',
            label: t('layouts.sections.typography_eyebrow', 'Eyebrow'),
        },
    ]),
);

function tokenOptions(configuredOptions, fallbackOptions) {
    const fallbackByValue = new Map(
        fallbackOptions.map((option) => [option.value, option.label]),
    );
    const options = Array.isArray(configuredOptions)
        ? configuredOptions
              .filter((option) => typeof option?.value === 'string')
              .map((option) => ({
                  value: option.value,
                  label:
                      fallbackByValue.get(option.value) ||
                      option.label ||
                      option.value,
                  ...(typeof option.css_value === 'string' &&
                  option.css_value.trim()
                      ? { css_value: option.css_value.trim() }
                      : {}),
              }))
        : [];

    return options.length > 0 ? options : fallbackOptions;
}

function isAddressBlock(block) {
    return props.helpers.placeableBlockRendererKey(block) === 'address_block';
}

function addressEditorField(block, fieldName) {
    return (
        props.helpers
            .blockEditorFields(block)
            .find((field) => field.name === fieldName) || {
            name: fieldName,
            type: 'text',
        }
    );
}

function addressFieldLabel(block, fieldName) {
    return props.helpers.editorFieldLabel(addressEditorField(block, fieldName));
}

function addressFieldPlaceholder(block, fieldName) {
    return props.helpers.editorFieldPlaceholder(
        addressEditorField(block, fieldName),
    );
}

function addressFieldOptions(block, fieldName) {
    const options = addressEditorField(block, fieldName).options;

    return Array.isArray(options) ? options : [];
}

function contentEditorFields(block) {
    return props.helpers
        .blockEditorFields(block)
        .filter((field) => field.type !== 'code');
}

function pageEditableFieldEnabled(fieldName) {
    return Array.isArray(placement.value?.settings?.page_editable_fields)
        ? placement.value.settings.page_editable_fields.includes(fieldName)
        : true;
}

function togglePageEditableField(fieldName, enabled) {
    if (!placement.value?.settings) {
        return;
    }

    const fields = new Set(
        Array.isArray(placement.value.settings.page_editable_fields)
            ? placement.value.settings.page_editable_fields
            : [],
    );

    if (enabled) {
        fields.add(fieldName);
    } else {
        fields.delete(fieldName);
    }

    placement.value.settings.page_editable_fields = [...fields];
}

function pageEditableMetaEnabled(fieldName) {
    return Array.isArray(placement.value?.settings?.page_editable_meta)
        ? placement.value.settings.page_editable_meta.includes(fieldName)
        : false;
}

function togglePageEditableMeta(fieldName, enabled) {
    if (!placement.value?.settings) {
        return;
    }

    const fields = new Set(
        Array.isArray(placement.value.settings.page_editable_meta)
            ? placement.value.settings.page_editable_meta
            : [],
    );

    if (enabled) {
        fields.add(fieldName);
    } else {
        fields.delete(fieldName);
    }

    placement.value.settings.page_editable_meta = [...fields];
}

function codeEditorField(block) {
    return props.helpers
        .blockEditorFields(block)
        .find((field) => field.type === 'code');
}

function codeEditorLanguage(field) {
    const language = String(
        field?.language || field?.code_language || 'html',
    ).toLowerCase();

    return [
        'html',
        'blade',
        'safe_blade',
        'javascript',
        'js',
        'css',
        'php',
        'sql',
    ].includes(language)
        ? language
        : 'html';
}

function codeEditorHeight(field) {
    const rows = Number(field?.rows || 10);

    return `${Math.max(260, rows * 36)}px`;
}

function isRequiredField(field) {
    if (field?.required === true) {
        return true;
    }

    if (Array.isArray(field?.rules)) {
        return field.rules.includes('required');
    }

    return String(field?.rules || '')
        .split('|')
        .includes('required');
}

function isLogoAltField(field) {
    return isLogoPlacement.value && field?.name === 'alt_text';
}

function requiredFieldControlClass(field) {
    return isRequiredField(field)
        ? 'border-yellow-200 bg-yellow-50 focus:border-yellow-500 focus:ring-yellow-100'
        : '';
}

function editorSelectClasses(field) {
    return [
        'h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100',
        requiredFieldControlClass(field),
    ];
}

function canPublishPlacementStyle() {
    return (
        Boolean(placement.value?.id) &&
        String(
            placement.value?.style_config?.developer?.css_source || '',
        ).trim().length > 0
    );
}

function isStyleRevisionProcessing(action = null) {
    if (action === null) {
        return styleRevisionProcessingAction.value !== null;
    }

    return styleRevisionProcessingAction.value === action;
}

function publishPlacementStyleRevision() {
    if (!canPublishPlacementStyle()) {
        return;
    }

    router.post(
        route('admin.cms.block-placements.style-revisions.publish', {
            placement: placement.value.id,
        }),
        {
            css_source: placement.value.style_config.developer.css_source,
            style_config: placement.value.style_config,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                styleRevisionProcessingAction.value = 'publish';
            },
            onSuccess: () => {
                emit('update:activeTab', 'css');
                refreshPlacementStyleRevisions();
            },
            onFinish: () => {
                styleRevisionProcessingAction.value = null;
            },
        },
    );
}

function restorePublishedStyleRevisionDraft() {
    if (!placement.value?.published_style_revision) {
        return;
    }

    placement.value.style_config.developer.css_source =
        placement.value.published_style_revision.css_source || '';
}

function styleRevisions() {
    return Array.isArray(placement.value?.style_revisions)
        ? placement.value.style_revisions
        : [];
}

function revisionDateLabel(revision) {
    if (!revision?.published_at) {
        return t(
            'components.block_editor.style_revision_no_date',
            'Geen datum',
        );
    }

    const date = new Date(revision.published_at);

    if (Number.isNaN(date.getTime())) {
        return t(
            'components.block_editor.style_revision_no_date',
            'Geen datum',
        );
    }

    return new Intl.DateTimeFormat('nl-BE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

function restoreStyleRevisionDraft(revision) {
    if (!revision) {
        return;
    }

    placement.value.style_config.developer.css_source =
        revision.css_source || '';
}

function republishPlacementStyleRevision(revision) {
    if (!placement.value?.id || !revision?.id || revision.is_current) {
        return;
    }

    const confirmed = window.confirm(
        t(
            'components.block_editor.republish_style_revision_confirm',
            'Deze stijlrevisie opnieuw live zetten?',
            { number: revision.revision_number },
        ),
    );

    if (!confirmed) {
        return;
    }

    router.post(
        route('admin.cms.block-placements.style-revisions.republish', {
            placement: placement.value.id,
            revision: revision.id,
        }),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                styleRevisionProcessingAction.value = `republish:${revision.id}`;
            },
            onSuccess: () => {
                emit('update:activeTab', 'css');
                refreshPlacementStyleRevisions();
            },
            onFinish: () => {
                styleRevisionProcessingAction.value = null;
            },
        },
    );
}

async function refreshPlacementStyleRevisions() {
    if (!placement.value?.id) {
        return;
    }

    const response = await window.fetch(
        route('admin.cms.block-placements.style-revisions.index', {
            placement: placement.value.id,
        }),
        {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        },
    );

    if (!response.ok) {
        return;
    }

    const data = await response.json();
    const revisions = Array.isArray(data.revisions) ? data.revisions : [];
    const currentRevision = revisions.find((revision) => revision.is_current);

    placement.value.style_revisions = revisions;
    placement.value.published_style_revision_id = currentRevision?.id || null;
    placement.value.published_style_revision = currentRevision
        ? {
              id: currentRevision.id,
              revision_number: currentRevision.revision_number,
              css_source: currentRevision.css_source,
              published_at: currentRevision.published_at,
          }
        : null;

    if (currentRevision?.style_config) {
        placement.value.style_config = {
            ...placement.value.style_config,
            ...currentRevision.style_config,
            developer: {
                ...(currentRevision.style_config.developer || {}),
                css_source:
                    currentRevision.css_source ||
                    currentRevision.style_config.developer?.css_source ||
                    '',
            },
        };
    }
}

function tabClasses(value) {
    const isActive = props.activeTab === value;

    return [
        '-mb-px border-b-2 px-1 py-2 text-sm font-medium transition',
        isActive
            ? 'border-blue-600 text-blue-700'
            : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900',
    ];
}

function normalizePlacementContentKey() {
    if (!placement.value?.settings) {
        return;
    }

    placement.value.settings.content_key = String(
        placement.value.settings.content_key || '',
    )
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9_]+/g, '_')
        .replace(/^_+|_+$/g, '');
}

function fieldId(name) {
    return `cms-${props.zone}-block-settings-${sanitizeFieldIdentifier(name)}`;
}

function fieldName(name) {
    return `cms_${props.zone}_block_settings_${sanitizeFieldIdentifier(name)}`;
}

function sanitizeFieldIdentifier(value) {
    return String(value).replace(/[^A-Za-z0-9_-]/g, '_');
}
</script>
