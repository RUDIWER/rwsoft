<template>
    <Head :title="pageTitle" />

    <AdminLayout :title="pageTitle" :suppress-flash="true">
        <form @submit.prevent="submit(false)">
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
                                <span class="mdi mdi-cube-outline text-2xl" />
                            </div>
                            <div class="min-w-0">
                                <CardTitle class="text-lg">
                                    {{
                                        blockItem
                                            ? t(
                                                  'blocks.edit_title',
                                                  'Edit block',
                                              )
                                            : t('blocks.new', 'New block')
                                    }}
                                </CardTitle>
                                <CardDescription class="mt-1">
                                    {{
                                        t(
                                            'blocks.form_description',
                                            'Manage the block definition, allowed zones, SafeBlade HTML and CSS.',
                                        )
                                    }}
                                </CardDescription>
                            </div>
                        </div>
                        <div class="flex flex-wrap justify-end gap-2">
                            <AdminFormBackButton
                                :href="route('admin.cms.blocks.index')"
                                :dirty="form.isDirty"
                                :processing="form.processing"
                                :label="commonT('actions.back', 'Back')"
                                @save="submit(false)"
                            />
                            <AdminFormSaveButton
                                :dirty="saveRequiresAttention"
                                :processing="form.processing"
                                :label="commonT('actions.save', 'Save')"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                class="gap-2 border-blue-200 text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800"
                                :disabled="form.processing"
                                @click="submit(true)"
                            >
                                <span
                                    class="mdi mdi-cloud-upload-outline text-base"
                                    aria-hidden="true"
                                />
                                {{
                                    t('blocks.save_publish', 'Save and publish')
                                }}
                            </Button>
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
                    />
                </div>

                <div
                    v-if="showValidationSummary"
                    class="shrink-0 border-b border-slate-200 px-4 py-3 sm:px-5"
                >
                    <FormValidationSummary
                        :visible="showValidationSummary"
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
                        <section
                            v-if="activeTab === 'basis'"
                            class="grid gap-5"
                        >
                            <div class="grid gap-1">
                                <h2
                                    class="text-base font-semibold text-slate-900"
                                >
                                    {{
                                        t(
                                            'blocks.basic_title',
                                            'Basic settings',
                                        )
                                    }}
                                </h2>
                                <p class="text-sm text-slate-500">
                                    {{
                                        t(
                                            'blocks.basic_description',
                                            'Set the visible identity and management status for this block.',
                                        )
                                    }}
                                </p>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label
                                        class="flex items-center gap-1"
                                        for="name"
                                    >
                                        <span
                                            class="text-red-600"
                                            aria-hidden="true"
                                            >*</span
                                        >
                                        {{ t('common.columns.name', 'Name') }}
                                    </Label>
                                    <Input
                                        id="name"
                                        v-model="form.name"
                                        required
                                        class="bg-yellow-50"
                                        @blur="touchAndClear('name')"
                                    />
                                    <FieldValidationMessage
                                        :message="validationMessage('name')"
                                        :warning="validationWarning('name')"
                                        :value="form.name"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label
                                        class="flex items-center gap-1"
                                        for="key"
                                    >
                                        <span
                                            class="text-red-600"
                                            aria-hidden="true"
                                            >*</span
                                        >
                                        {{ t('common.columns.key', 'Key') }}
                                    </Label>
                                    <Input
                                        id="key"
                                        v-model="form.key"
                                        required
                                        class="bg-yellow-50 font-mono"
                                        @blur="touchAndClear('key')"
                                    />
                                    <p class="text-xs text-slate-500">
                                        {{
                                            t(
                                                'blocks.key_help',
                                                'Lowercase letters, numbers, underscores and dashes. Unique per block.',
                                            )
                                        }}
                                    </p>
                                    <FieldValidationMessage
                                        :message="validationMessage('key')"
                                        :warning="validationWarning('key')"
                                        :value="form.key"
                                    />
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label class="flex items-center gap-1">
                                        <span
                                            class="text-red-600"
                                            aria-hidden="true"
                                            >*</span
                                        >
                                        {{
                                            t(
                                                'common.columns.category',
                                                'Category',
                                            )
                                        }}
                                    </Label>
                                    <RwAutoCompleteInput
                                        id="category"
                                        v-model="form.category"
                                        class="bg-yellow-50"
                                        :items="categoryOptions"
                                        item-title="label"
                                        item-value="value"
                                        :search-fields="['label', 'value']"
                                        required-highlight-color="#fefce8"
                                        @blur="touchAndClear('category')"
                                    />
                                    <p class="text-xs leading-5 text-slate-500">
                                        {{
                                            t(
                                                'blocks.category_help',
                                                'System and code blocks receive a clear label in the block picker.',
                                            )
                                        }}
                                    </p>
                                    <FieldValidationMessage
                                        :message="validationMessage('category')"
                                        :warning="validationWarning('category')"
                                        :value="form.category"
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
                                            t('common.columns.status', 'Status')
                                        }}
                                    </Label>
                                    <RwAutoCompleteInput
                                        id="status"
                                        v-model="form.status"
                                        class="bg-yellow-50"
                                        :items="statusOptions"
                                        item-title="label"
                                        item-value="value"
                                        :search-fields="['label', 'value']"
                                        required-highlight-color="#fefce8"
                                        @blur="touchAndClear('status')"
                                    />
                                    <FieldValidationMessage
                                        :message="validationMessage('status')"
                                        :warning="validationWarning('status')"
                                        :value="form.status"
                                    />
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <Label for="description">
                                    {{
                                        t(
                                            'common.columns.description',
                                            'Description',
                                        )
                                    }}
                                </Label>
                                <textarea
                                    id="description"
                                    v-model="form.description"
                                    rows="3"
                                    class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                    @blur="touchAndClear('description')"
                                ></textarea>
                                <FieldValidationMessage
                                    :message="validationMessage('description')"
                                    :warning="validationWarning('description')"
                                    :value="form.description"
                                />
                            </div>
                        </section>

                        <section
                            v-else-if="activeTab === 'fields'"
                            class="grid gap-4"
                        >
                            <CmsFieldDefinitionEditor
                                v-model="blockFields"
                                :languages="fieldLanguages"
                                :field-types="formOptions.editor_field_types"
                                :title="t('blocks.fields_title', 'Fields')"
                                :description="
                                    t(
                                        'blocks.fields_description',
                                        'Define the content fields editors fill in when this block is used in a template.',
                                    )
                                "
                                :empty-text="
                                    t(
                                        'blocks.no_fields',
                                        'This block does not define input fields yet.',
                                    )
                                "
                            />
                        </section>

                        <section
                            v-else-if="activeTab === 'slots'"
                            class="grid gap-4"
                        >
                            <CmsSlotDefinitionEditor
                                v-model="slotDefinitions"
                                :layout-options="formOptions.slot_layouts || []"
                                :responsive-options="
                                    formOptions.slot_responsive_modes || []
                                "
                                :slot-block-options="
                                    formOptions.slot_block_options || []
                                "
                                :title="t('blocks.slots_title', 'Slots')"
                                :description="
                                    t(
                                        'blocks.slots_description',
                                        'Define explicit child block slots for this block definition.',
                                    )
                                "
                                :empty-text="
                                    t(
                                        'blocks.no_slots',
                                        'This block does not define child block slots yet.',
                                    )
                                "
                            />
                        </section>

                        <section
                            v-else-if="activeTab === 'placement'"
                            class="grid gap-4"
                        >
                            <div class="grid gap-1">
                                <h2
                                    class="text-base font-semibold text-slate-900"
                                >
                                    {{
                                        t('blocks.placement_title', 'Placement')
                                    }}
                                </h2>
                                <p class="text-sm text-slate-500">
                                    {{
                                        t(
                                            'blocks.placement_description',
                                            'Choose where this block can be selected.',
                                        )
                                    }}
                                </p>
                            </div>
                            <div
                                id="allowed_zones"
                                class="flex flex-wrap gap-2"
                            >
                                <label
                                    v-for="zone in formOptions.zones"
                                    :key="zone"
                                    class="inline-flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-sm"
                                >
                                    <input
                                        v-model="form.allowed_zones"
                                        type="checkbox"
                                        :value="zone"
                                        class="h-4 w-4 rounded border-slate-300 text-blue-600"
                                        @change="touchAndClear('allowed_zones')"
                                    />
                                    {{ optionLabel('zone', zone) }}
                                </label>
                            </div>
                            <FieldValidationMessage
                                :message="validationMessage('allowed_zones')"
                                :warning="validationWarning('allowed_zones')"
                            />
                        </section>

                        <section
                            v-else-if="activeTab === 'technical'"
                            class="grid gap-5"
                        >
                            <div class="grid gap-1">
                                <h2
                                    class="text-base font-semibold text-slate-900"
                                >
                                    {{
                                        t('blocks.technical_title', 'Technical')
                                    }}
                                </h2>
                                <p class="text-sm text-slate-500">
                                    {{
                                        t(
                                            'blocks.technical_description',
                                            'Internal renderer and source information. Normal users rarely need to change this.',
                                        )
                                    }}
                                </p>
                            </div>
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label class="flex items-center gap-1">
                                        <span
                                            class="text-red-600"
                                            aria-hidden="true"
                                            >*</span
                                        >
                                        {{
                                            t(
                                                'blocks.rendering_mode',
                                                'Rendering mode',
                                            )
                                        }}
                                    </Label>
                                    <RwAutoCompleteInput
                                        id="rendering_mode"
                                        v-model="form.rendering_mode"
                                        class="bg-yellow-50"
                                        :items="renderingModeOptions"
                                        item-title="label"
                                        item-value="value"
                                        :search-fields="['label', 'value']"
                                        required-highlight-color="#fefce8"
                                        @blur="touchAndClear('rendering_mode')"
                                    />
                                    <FieldValidationMessage
                                        :message="
                                            validationMessage('rendering_mode')
                                        "
                                        :warning="
                                            validationWarning('rendering_mode')
                                        "
                                        :value="form.rendering_mode"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label
                                        class="flex items-center gap-1"
                                        for="renderer_key"
                                    >
                                        <span
                                            class="text-red-600"
                                            aria-hidden="true"
                                            >*</span
                                        >
                                        {{
                                            t(
                                                'blocks.renderer_key',
                                                'Renderer key',
                                            )
                                        }}
                                    </Label>
                                    <Input
                                        id="renderer_key"
                                        v-model="form.renderer_key"
                                        class="bg-yellow-50 font-mono"
                                        @blur="touchAndClear('renderer_key')"
                                    />
                                    <p class="text-xs text-slate-500">
                                        {{
                                            t(
                                                'blocks.renderer_key_help',
                                                'SafeBlade may use a new key. Platform blocks use a registered renderer.',
                                            )
                                        }}
                                    </p>
                                    <FieldValidationMessage
                                        :message="
                                            validationMessage('renderer_key')
                                        "
                                        :warning="
                                            validationWarning('renderer_key')
                                        "
                                        :value="form.renderer_key"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label>{{
                                        t('blocks.source', 'Source')
                                    }}</Label>
                                    <RwAutoCompleteInput
                                        id="source"
                                        v-model="form.source"
                                        :items="sourceOptions"
                                        item-title="label"
                                        item-value="value"
                                        :search-fields="['label', 'value']"
                                        @blur="touchAndClear('source')"
                                    />
                                    <FieldValidationMessage
                                        :message="validationMessage('source')"
                                        :warning="validationWarning('source')"
                                        :value="form.source"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="package_key">
                                        {{
                                            t(
                                                'blocks.package_key',
                                                'Package key',
                                            )
                                        }}
                                    </Label>
                                    <Input
                                        id="package_key"
                                        v-model="form.package_key"
                                        class="font-mono"
                                        @blur="touchAndClear('package_key')"
                                    />
                                    <FieldValidationMessage
                                        :message="
                                            validationMessage('package_key')
                                        "
                                        :warning="
                                            validationWarning('package_key')
                                        "
                                        :value="form.package_key"
                                    />
                                </div>
                            </div>
                        </section>

                        <section
                            v-else-if="activeTab === 'template'"
                            class="grid gap-3"
                        >
                            <div class="grid gap-1">
                                <h2
                                    class="text-base font-semibold text-slate-900"
                                >
                                    {{
                                        t(
                                            'blocks.template_source',
                                            'SafeBlade template',
                                        )
                                    }}
                                </h2>
                                <p class="text-sm text-slate-500">
                                    {{
                                        t(
                                            'blocks.template_description',
                                            'Use only SafeBlade syntax with dot notation and responsive directives.',
                                        )
                                    }}
                                </p>
                            </div>
                            <div
                                v-if="validationMessage('template_source')"
                                class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800"
                            >
                                {{ validationMessage('template_source') }}
                            </div>
                            <RwCodeEditor
                                v-model="form.template_source"
                                language="html"
                                height="420px"
                                :line-wrapping="true"
                                @update:model-value="
                                    touchAndClear('template_source')
                                "
                                :placeholder="
                                    t(
                                        'blocks.template_placeholder',
                                        '<div>{{ block.title }}</div>',
                                    )
                                "
                            />
                        </section>

                        <section
                            v-else-if="activeTab === 'css'"
                            class="grid gap-3"
                        >
                            <div class="grid gap-1">
                                <h2
                                    class="text-base font-semibold text-slate-900"
                                >
                                    {{ t('blocks.css_source', 'Block CSS') }}
                                </h2>
                                <p class="text-sm text-slate-500">
                                    {{
                                        t(
                                            'blocks.css_description',
                                            'CSS is applied when this published block definition is used.',
                                        )
                                    }}
                                </p>
                            </div>
                            <div
                                v-if="validationMessage('css_source')"
                                class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800"
                            >
                                {{ validationMessage('css_source') }}
                            </div>
                            <RwCodeEditor
                                v-model="form.css_source"
                                language="css"
                                height="320px"
                                :line-wrapping="true"
                                @update:model-value="
                                    touchAndClear('css_source')
                                "
                                :placeholder="
                                    t(
                                        'blocks.css_placeholder',
                                        '.cms-block { }',
                                    )
                                "
                            />
                        </section>

                        <section
                            v-else-if="activeTab === 'publication' && blockItem"
                            class="grid gap-4"
                        >
                            <div
                                class="flex flex-wrap items-start justify-between gap-3"
                            >
                                <div class="grid gap-1">
                                    <h2
                                        class="text-base font-semibold text-slate-900"
                                    >
                                        {{
                                            t(
                                                'content_form.publication',
                                                'Publication',
                                            )
                                        }}
                                    </h2>
                                    <p class="text-sm text-slate-500">
                                        {{
                                            t(
                                                'blocks.publication_description',
                                                'Only published revisions are used by the public runtime.',
                                            )
                                        }}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="gap-2 border-blue-200 text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800"
                                    :disabled="publishProcessing"
                                    @click="publishBlock"
                                >
                                    <span
                                        v-if="publishProcessing"
                                        class="mdi mdi-loading animate-spin text-base"
                                        aria-hidden="true"
                                    />
                                    <span
                                        v-else
                                        class="mdi mdi-cloud-upload-outline text-base"
                                        aria-hidden="true"
                                    />
                                    {{ t('blocks.publish', 'Publish') }}
                                </Button>
                            </div>
                            <div class="grid gap-2 text-sm text-slate-600">
                                <p>
                                    {{ t('common.columns.status', 'Status') }}:
                                    <strong>{{
                                        statusLabel(blockItem.status)
                                    }}</strong>
                                </p>
                                <p>
                                    {{ t('blocks.blocks_count', 'Instances') }}:
                                    <strong>{{
                                        blockItem.blocks_count
                                    }}</strong>
                                </p>
                            </div>
                        </section>

                        <section
                            v-else-if="activeTab === 'usage' && blockItem"
                            class="grid gap-4"
                        >
                            <div class="grid gap-1">
                                <h2
                                    class="text-base font-semibold text-slate-900"
                                >
                                    {{ t('blocks.usage', 'Usage') }}
                                </h2>
                                <p class="text-sm text-slate-500">
                                    {{
                                        t(
                                            'blocks.usage_description',
                                            'Overview of block instances and placements using this definition.',
                                        )
                                    }}
                                </p>
                            </div>
                            <div class="grid gap-3">
                                <div
                                    v-for="usage in blockUsages"
                                    :key="usageKey(usage)"
                                    class="rounded-md border border-slate-200 p-3"
                                >
                                    <div
                                        class="flex flex-wrap items-start justify-between gap-3"
                                    >
                                        <div class="grid gap-1 text-sm">
                                            <div
                                                class="font-medium text-slate-900"
                                            >
                                                {{ usage.block_name }}
                                                <span
                                                    class="font-mono text-xs text-slate-500"
                                                >
                                                    #{{ usage.block_id }}
                                                </span>
                                            </div>
                                            <div class="text-slate-600">
                                                {{
                                                    usage.section_name ||
                                                    t(
                                                        'blocks.unplaced_block',
                                                        'Not placed',
                                                    )
                                                }}
                                                <span v-if="usage.section_zone">
                                                    · {{ usage.section_zone }}
                                                </span>
                                            </div>
                                            <div class="text-xs text-slate-500">
                                                {{
                                                    usage.placeable_block_revision_id
                                                        ? t(
                                                              'blocks.linked_revision',
                                                              'Linked revision ID #:number',
                                                              {
                                                                  number: usage.placeable_block_revision_id,
                                                              },
                                                          )
                                                        : t(
                                                              'blocks.no_linked_revision',
                                                              'No fixed revision linked',
                                                          )
                                                }}
                                            </div>
                                        </div>
                                        <div
                                            class="grid justify-items-end gap-1 text-right text-sm"
                                        >
                                            <div
                                                v-if="usage.owner_name"
                                                class="text-slate-700"
                                            >
                                                {{ usage.owner_type }}:
                                                {{ usage.owner_name }}
                                            </div>
                                            <Button
                                                v-if="usage.owner_edit_url"
                                                as-child
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                            >
                                                <Link
                                                    :href="usage.owner_edit_url"
                                                >
                                                    {{
                                                        t(
                                                            'blocks.open_owner',
                                                            'Open',
                                                        )
                                                    }}
                                                </Link>
                                            </Button>
                                        </div>
                                    </div>
                                </div>

                                <p
                                    v-if="blockUsages.length === 0"
                                    class="text-sm text-slate-500"
                                >
                                    {{
                                        t(
                                            'blocks.no_usage',
                                            'This block is not used yet.',
                                        )
                                    }}
                                </p>
                            </div>
                        </section>

                        <section
                            v-else-if="activeTab === 'revisions' && blockItem"
                            class="grid gap-4"
                        >
                            <div class="grid gap-1">
                                <h2
                                    class="text-base font-semibold text-slate-900"
                                >
                                    {{ t('blocks.revisions', 'Revisions') }}
                                </h2>
                                <p class="text-sm text-slate-500">
                                    {{
                                        t(
                                            'blocks.revisions_description',
                                            'Every publication creates an immutable snapshot for the public runtime.',
                                        )
                                    }}
                                </p>
                            </div>
                            <div class="grid gap-3">
                                <div
                                    v-for="revision in revisions"
                                    :key="revision.id"
                                    class="rounded-md border border-slate-200 p-3"
                                >
                                    <div
                                        class="flex flex-wrap items-start justify-between gap-3"
                                    >
                                        <div class="grid gap-1 text-sm">
                                            <div
                                                class="font-medium text-slate-900"
                                            >
                                                #{{ revision.revision_number }}
                                                -
                                                {{
                                                    statusLabel(revision.status)
                                                }}
                                            </div>
                                            <div class="text-slate-600">
                                                {{
                                                    revision.published_at ||
                                                    revision.created_at
                                                }}
                                            </div>
                                            <div
                                                class="font-mono text-xs text-slate-500"
                                            >
                                                {{ revision.snapshot_hash }}
                                            </div>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                class="shadow-none"
                                                :disabled="
                                                    restoringRevisionId ===
                                                    revision.id
                                                "
                                                @click="
                                                    restoreRevision(revision)
                                                "
                                            >
                                                {{
                                                    t(
                                                        'blocks.restore_revision',
                                                        'Restore',
                                                    )
                                                }}
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                                <p
                                    v-if="revisions.length === 0"
                                    class="text-sm text-slate-500"
                                >
                                    {{
                                        t(
                                            'blocks.no_revisions',
                                            'No revisions yet. Publish this block first.',
                                        )
                                    }}
                                </p>
                            </div>
                        </section>
                    </div>
                </CardContent>
            </Card>
        </form>
    </AdminLayout>
</template>

<script setup>
import RwCodeEditor from '@/Components/RwCodeEditor.vue';
import RwFlashMessage from '@/Components/RwFlashMessage.vue';
import RwAutoCompleteInput from '@/Components/RwAutoCompleteInput.vue';
import AdminFormBackButton from '@/Components/Admin/Form/AdminFormBackButton.vue';
import AdminFormSaveButton from '@/Components/Admin/Form/AdminFormSaveButton.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CmsFieldDefinitionEditor from '@/Pages/Admin/Cms/Components/CmsFieldDefinitionEditor.vue';
import CmsSlotDefinitionEditor from '@/Pages/Admin/Cms/Components/CmsSlotDefinitionEditor.vue';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { useCmsFormValidation } from '@/composables/useCmsFormValidation';
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
import clientRules from '@/ValidationRules/Rules';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const { t } = useAdminTranslations('cms_admin_ui');
const { t: commonT } = useAdminTranslations('admin_common_ui');
const page = usePage();

const props = defineProps({
    blockItem: { type: Object, default: null },
    formOptions: { type: Object, required: true },
    revisions: { type: Array, required: true },
    blockUsages: { type: Array, required: true },
    activeLanguages: { type: Array, default: () => [] },
    availableLocales: { type: Array, default: () => [] },
});

const publishProcessing = ref(false);
const restoringRevisionId = ref(null);
const activeTab = ref('basis');
const pageTitle = computed(() =>
    props.blockItem
        ? `${t('blocks.block', 'Block')} ${props.blockItem.name}`
        : t('blocks.new', 'New block'),
);

const tabOptions = computed(() => {
    const tabs = [
        { value: 'basis', label: t('blocks.basic_title', 'Basic') },
        { value: 'fields', label: t('blocks.fields_title', 'Fields') },
        { value: 'slots', label: t('blocks.slots_title', 'Slots') },
        { value: 'placement', label: t('blocks.placement_title', 'Placement') },
        { value: 'technical', label: t('blocks.technical_title', 'Technical') },
        {
            value: 'template',
            label: t('blocks.template_source', 'SafeBlade template'),
        },
        { value: 'css', label: t('blocks.css_source', 'Block CSS') },
    ];

    if (props.blockItem) {
        tabs.push(
            {
                value: 'publication',
                label: t('content_form.publication', 'Publication'),
            },
            { value: 'usage', label: t('blocks.usage', 'Usage') },
            { value: 'revisions', label: t('blocks.revisions', 'Revisions') },
        );
    }

    return tabs;
});

const recordIdLabel = computed(() => props.blockItem?.id ?? '-');
const updatedAtLabel = computed(() => formatDate(props.blockItem?.updated_at));
const createdAtLabel = computed(() => formatDate(props.blockItem?.created_at));
const categoryOptions = computed(() =>
    (props.formOptions.categories || []).map((value) => ({
        value,
        label: optionLabel('category', value),
    })),
);
const statusOptions = computed(() =>
    ['draft', 'published', 'archived'].map((value) => ({
        value,
        label: t(`common.status.${value}`, value),
    })),
);
const renderingModeOptions = computed(() =>
    (props.formOptions.rendering_modes || []).map((value) => ({
        value,
        label: optionLabel('rendering_mode', value),
    })),
);
const sourceOptions = computed(() =>
    (props.formOptions.sources || []).map((value) => ({
        value,
        label: optionLabel('source', value),
    })),
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

const form = useForm({
    key: props.blockItem?.key ?? '',
    name: props.blockItem?.name ?? '',
    description: props.blockItem?.description ?? '',
    category: props.blockItem?.category ?? 'content',
    source: props.blockItem?.source ?? 'user',
    status: props.blockItem?.status ?? 'draft',
    allowed_zones: props.blockItem?.allowed_zones ?? ['content'],
    rendering_mode: props.blockItem?.rendering_mode ?? 'safe_blade',
    renderer_key: props.blockItem?.renderer_key ?? 'text',
    template_source: props.blockItem?.template_source ?? '',
    css_source: props.blockItem?.css_source ?? '',
    schema: props.blockItem?.schema ?? {
        fields: [],
        editor_fields: [],
        preview: {},
    },
    capabilities: props.blockItem?.capabilities ?? {},
    admin_component_key: props.blockItem?.admin_component_key ?? '',
    package_key: props.blockItem?.package_key ?? '',
    sort_order: props.blockItem?.sort_order ?? 0,
    is_locked: props.blockItem?.is_locked ?? false,
    requires_permission: props.blockItem?.requires_permission ?? '',
    publish: false,
});

const blockDefaults = ref(props.blockItem?.defaults ?? {});
const blockFields = ref(
    fieldsFromBlockDefinition(form.schema, blockDefaults.value),
);
const slotDefinitions = ref(slotsFromBlockDefinition(form.schema));
const fieldLanguages = computed(() => {
    if (props.activeLanguages.length > 0) {
        return props.activeLanguages;
    }

    return props.availableLocales.map((locale) => ({
        locale,
        name: locale,
        native_name: locale,
    }));
});
const requiredMessage = computed(() =>
    t('validation.required', 'This field is required.'),
);
const requiredFieldsMissing = computed(
    () =>
        [
            'key',
            'name',
            'category',
            'status',
            'rendering_mode',
            'renderer_key',
        ].some((field) => String(form[field] ?? '').trim() === '') ||
        !Array.isArray(form.allowed_zones) ||
        form.allowed_zones.length === 0,
);
const categoryValues = computed(() =>
    (props.formOptions.categories || []).map((value) => String(value)),
);
const renderingModeValues = computed(() =>
    (props.formOptions.rendering_modes || []).map((value) => String(value)),
);
const sourceValues = computed(() =>
    (props.formOptions.sources || []).map((value) => String(value)),
);
const zoneValues = computed(() =>
    (props.formOptions.zones || []).map((value) => String(value)),
);
const blockValidationFields = {
    key: {
        label: t('common.columns.key', 'Key'),
        tab: 'basis',
        elementId: 'key',
        value: () => form.key,
        rules: [
            (value) => clientRules.required(value, requiredMessage.value),
            (value) =>
                validatePattern(
                    value,
                    /^[a-z0-9_-]+$/,
                    t(
                        'validation.block_key_format',
                        'Use lowercase letters, numbers, underscores and dashes.',
                    ),
                    true,
                ),
            (value) => validateMax('common.columns.key', 'Key', 120, value),
        ],
    },
    name: {
        label: t('common.columns.name', 'Name'),
        tab: 'basis',
        elementId: 'name',
        value: () => form.name,
        rules: [
            (value) => clientRules.required(value, requiredMessage.value),
            (value) => validateMax('common.columns.name', 'Name', 255, value),
        ],
    },
    description: {
        label: t('common.columns.description', 'Description'),
        tab: 'basis',
        elementId: 'description',
        value: () => form.description,
        rules: [
            (value) =>
                validateMax(
                    'common.columns.description',
                    'Description',
                    1000,
                    value,
                ),
        ],
    },
    category: {
        label: t('common.columns.category', 'Category'),
        tab: 'basis',
        elementId: 'category',
        value: () => form.category,
        rules: [
            (value) => clientRules.required(value, requiredMessage.value),
            (value) => validateChoice(value, categoryValues.value),
        ],
    },
    status: {
        label: t('common.columns.status', 'Status'),
        tab: 'basis',
        elementId: 'status',
        value: () => form.status,
        rules: [
            (value) => clientRules.required(value, requiredMessage.value),
            (value) =>
                validateChoice(value, ['draft', 'published', 'archived']),
        ],
    },
    allowed_zones: {
        label: t('blocks.allowed_zones', 'Places'),
        tab: 'placement',
        elementId: 'allowed_zones',
        value: () => form.allowed_zones,
        rules: [(value) => validateRequiredChoices(value, zoneValues.value)],
    },
    rendering_mode: {
        label: t('blocks.rendering_mode', 'Rendering mode'),
        tab: 'technical',
        elementId: 'rendering_mode',
        value: () => form.rendering_mode,
        rules: [
            (value) => clientRules.required(value, requiredMessage.value),
            (value) => validateChoice(value, renderingModeValues.value),
        ],
    },
    renderer_key: {
        label: t('blocks.renderer_key', 'Renderer key'),
        tab: 'technical',
        elementId: 'renderer_key',
        value: () => form.renderer_key,
        rules: [
            (value) => clientRules.required(value, requiredMessage.value),
            (value) =>
                validatePattern(
                    value,
                    /^[a-zA-Z0-9_.-]+$/,
                    t(
                        'validation.renderer_key_format',
                        'Use letters, numbers, dots, underscores and dashes.',
                    ),
                    true,
                ),
            (value) =>
                validateMax('blocks.renderer_key', 'Renderer key', 120, value),
        ],
    },
    source: {
        label: t('blocks.source', 'Source'),
        tab: 'technical',
        elementId: 'source',
        value: () => form.source,
        rules: [(value) => validateChoice(value, sourceValues.value)],
    },
    package_key: {
        label: t('blocks.package_key', 'Package key'),
        tab: 'technical',
        elementId: 'package_key',
        value: () => form.package_key,
        rules: [
            (value) =>
                validatePattern(
                    value,
                    /^[A-Za-z0-9_.-]+$/,
                    t(
                        'validation.package_key_format',
                        'Use letters, numbers, dots, underscores and dashes.',
                    ),
                    false,
                ),
            (value) =>
                validateMax('blocks.package_key', 'Package key', 120, value),
        ],
    },
    template_source: {
        label: t('blocks.template_source', 'SafeBlade template'),
        tab: 'template',
        elementId: 'template_source',
        value: () => form.template_source,
        rules: [
            (value) =>
                form.publish && form.rendering_mode === 'safe_blade'
                    ? clientRules.required(
                          value,
                          t(
                              'validation.block_template_required',
                              'This SafeBlade block needs a template before it can be published.',
                          ),
                      )
                    : true,
        ],
    },
    css_source: {
        label: t('blocks.css_source', 'Block CSS'),
        tab: 'css',
        elementId: 'css_source',
        value: () => form.css_source,
        rules: [
            (value) =>
                String(value ?? '').includes('</style')
                    ? t(
                          'validation.layout_css_style_tag_forbidden',
                          'CSS may not contain a closing style tag.',
                      )
                    : true,
        ],
    },
};
const {
    FieldValidationMessage,
    FormValidationSummary,
    allValidationErrors: validationErrors,
    showValidationSummary,
    formValidation,
    message: validationMessage,
    warning: validationWarning,
    touchAndClear,
} = useCmsFormValidation(form, {
    fields: blockValidationFields,
    serverFields: {
        defaults: { label: t('blocks.defaults_source', 'Defaults JSON') },
        'schema.*': { label: t('blocks.schema_fields', 'Fields') },
        'schema.slots.*': { label: t('blocks.slots_title', 'Slots') },
        'capabilities.*': {
            label: t('blocks.capabilities', 'Capabilities'),
            tab: 'technical',
        },
    },
    activateTab: (tab) => {
        activeTab.value = tab;
    },
});
const { validateBeforeSubmit, scrollToIssue } = formValidation;
const saveRequiresAttention = computed(
    () =>
        form.isDirty ||
        requiredFieldsMissing.value ||
        validationErrors.value.length > 0,
);

async function submit(publish) {
    syncSchemaInputs();
    form.publish = publish;

    if (!(await validateBeforeSubmit())) {
        return;
    }

    const target = props.blockItem
        ? route('admin.cms.blocks.store', {
              block: props.blockItem.id,
          })
        : route('admin.cms.blocks.store-new');

    form.transform((data) => ({
        ...data,
        defaults: blockDefaults.value,
    })).post(target, {
        onFinish: () => {
            form.publish = false;
            form.transform((data) => data);
        },
    });
}

function syncSchemaInputs() {
    const normalizedFields = blockFields.value
        .map((field, index) => normalizeBlockField(field, index))
        .filter((field) => field.key !== '');

    form.schema = {
        ...(form.schema || {}),
        fields: normalizedFields.map((field) => field.key),
        editor_fields: normalizedFields.map((field) =>
            editorFieldPayload(field),
        ),
        preview: form.schema?.preview || {},
        slots: slotDefinitions.value.map((slot, index) =>
            slotDefinitionPayload(slot, index),
        ),
    };
    blockDefaults.value = Object.fromEntries(
        normalizedFields.map((field) => [
            field.key,
            defaultValueForField(field),
        ]),
    );
}

function slotsFromBlockDefinition(schema) {
    const slots = Array.isArray(schema?.slots) ? schema.slots : [];

    return slots.map((slot, index) => normalizeSlotDefinition(slot, index));
}

function normalizeSlotDefinition(slot = {}, index = 0) {
    return {
        _uid: slot._uid || uniqueFieldId(),
        key: normalizeFieldKey(slot.key || ''),
        label: slot.label || '',
        allowed_block_keys: Array.isArray(slot.allowed_block_keys)
            ? slot.allowed_block_keys.map(String)
            : [],
        min_items: nullableNumber(slot.min_items),
        max_items: nullableNumber(slot.max_items),
        layout: slot.layout || 'stack',
        responsive: slot.responsive || 'stack_mobile',
        sort_order: Number(slot.sort_order ?? (index + 1) * 10),
    };
}

function slotDefinitionPayload(slot, index) {
    const normalized = normalizeSlotDefinition(slot, index);

    return {
        key: normalized.key,
        label: normalized.label || normalized.key,
        allowed_block_keys: normalized.allowed_block_keys,
        min_items: normalized.min_items,
        max_items: normalized.max_items,
        layout: normalized.layout,
        responsive: normalized.responsive,
        sort_order: normalized.sort_order,
    };
}

function nullableNumber(value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const number = Number(value);

    return Number.isFinite(number) ? Math.max(0, Math.round(number)) : null;
}

function fieldsFromBlockDefinition(schema, defaults) {
    const schemaFields = Array.isArray(schema?.fields) ? schema.fields : [];
    const editorFields = Array.isArray(schema?.editor_fields)
        ? schema.editor_fields
        : [];

    return schemaFields.map((key, index) => {
        const editorField =
            editorFields.find((field) => field.name === key) || {};

        return normalizeBlockField(
            {
                ...editorField,
                key,
                default: defaults?.[key] ?? '',
            },
            index,
        );
    });
}

function normalizeBlockField(field = {}, index = 0) {
    return {
        _uid: field._uid || uniqueFieldId(),
        key: normalizeFieldKey(field.key || field.name || ''),
        type: field.type || 'text',
        required: Boolean(field.required),
        sort_order: Number(field.sort_order ?? (index + 1) * 10),
        default: field.default ?? '',
        options: Array.isArray(field.options) ? field.options : [],
        translations: field.translations || {},
        fields: Array.isArray(field.fields)
            ? field.fields.map((childField, childIndex) =>
                  normalizeBlockField(
                      {
                          ...childField,
                          key: childField.key || childField.name,
                      },
                      childIndex,
                  ),
              )
            : [],
    };
}

function editorFieldPayload(field) {
    const payload = {
        name: field.key,
        type: field.type,
        required: Boolean(field.required),
        sort_order: Number(field.sort_order || 0),
        translations: field.translations || {},
    };

    if (field.type === 'select') {
        payload.options = field.options || [];
    }

    if (field.type === 'repeater') {
        payload.fields = (field.fields || [])
            .filter((childField) => childField.key)
            .map((childField) => editorFieldPayload(childField));
    }

    return payload;
}

function defaultValueForField(field) {
    if (field.type === 'checkbox') {
        return Boolean(field.default);
    }

    if (field.type === 'media_list' || field.type === 'repeater') {
        return Array.isArray(field.default) ? field.default : [];
    }

    return field.default ?? null;
}

function normalizeFieldKey(value) {
    return String(value || '')
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9_]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .replace(/_{2,}/g, '_');
}

function uniqueFieldId() {
    if (
        typeof crypto !== 'undefined' &&
        typeof crypto.randomUUID === 'function'
    ) {
        return crypto.randomUUID();
    }

    return `field_${Date.now()}_${Math.random().toString(36).slice(2)}`;
}

function publishBlock() {
    if (!props.blockItem) {
        return;
    }

    publishProcessing.value = true;
    router.post(
        route('admin.cms.blocks.publish', {
            block: props.blockItem.id,
        }),
        {},
        {
            onFinish: () => {
                publishProcessing.value = false;
            },
        },
    );
}

function restoreRevision(revision) {
    if (!props.blockItem || !revision?.id) {
        return;
    }

    restoringRevisionId.value = revision.id;
    router.post(
        route('admin.cms.blocks.restore-revision', {
            block: props.blockItem.id,
            revision: revision.id,
        }),
        {},
        {
            onFinish: () => {
                restoringRevisionId.value = null;
            },
        },
    );
}

function statusLabel(status) {
    return t(`common.status.${status}`, status);
}

function formatDate(value) {
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

function usageKey(usage) {
    return `${usage.block_id}-${usage.placement_id || 'unplaced'}`;
}

function optionLabel(type, value) {
    return t(`blocks.options.${type}.${value}`, value);
}

function validateMax(labelKey, fallbackLabel, max, value) {
    return clientRules.max(
        max,
        value,
        t('validation.max_chars', ':field is too long (:current/:max).', {
            field: t(labelKey, fallbackLabel),
            current: String(value ?? '').length,
            max: String(max),
        }),
    );
}

function validateChoice(value, allowedValues) {
    const text = String(value ?? '').trim();

    if (text === '') {
        return true;
    }

    return allowedValues.map((item) => String(item)).includes(text)
        ? true
        : t('validation.invalid_choice', 'Choose a valid value.');
}

function validateRequiredChoices(value, allowedValues) {
    if (!Array.isArray(value) || value.length === 0) {
        return requiredMessage.value;
    }

    const allowed = new Set(allowedValues.map((item) => String(item)));
    const invalid = value.some((item) => !allowed.has(String(item)));

    return invalid
        ? t('validation.invalid_choice', 'Choose a valid value.')
        : true;
}

function validatePattern(value, pattern, message, required) {
    const text = String(value ?? '').trim();

    if (text === '') {
        return required ? message : true;
    }

    return pattern.test(text) ? true : message;
}
</script>
