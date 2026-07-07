<template>
    <AdminLayout :suppress-flash="true">
        <Head
            :title="
                isNew
                    ? t('form.meta.create_title', 'New query')
                    : `${t('form.meta.edit_prefix', 'Query')}: ${form.description}`
            "
        />

        <Card
            class="flex h-[calc(100vh-8rem)] flex-col overflow-hidden rounded-none shadow-none"
        >
            <CardHeader class="shrink-0 gap-0 border-b border-slate-200 p-0">
                <div
                    class="flex flex-wrap items-start justify-between gap-3 px-4 py-4 sm:px-5"
                >
                    <div class="flex min-w-0 items-start gap-3">
                        <div
                            class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-blue-50 text-blue-700 ring-1 ring-blue-100"
                            aria-hidden="true"
                        >
                            <span class="mdi mdi-database-search text-2xl" />
                        </div>
                        <div class="min-w-0">
                            <CardTitle class="text-lg">
                                {{
                                    isNew
                                        ? t(
                                              'form.page.create_title',
                                              'New query',
                                          )
                                        : t(
                                              'form.page.edit_title',
                                              'Edit query',
                                          )
                                }}
                            </CardTitle>
                            <CardDescription class="mt-1">
                                {{
                                    t(
                                        'form.page.subtitle',
                                        'Manage query builder and output settings.',
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
                            @save="submitForm"
                        />

                        <DropdownMenu v-if="canDeleteQuery">
                            <DropdownMenuTrigger as-child>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    class="h-9 w-9 shadow-none"
                                    :aria-label="
                                        t('actions.more', 'More actions')
                                    "
                                    :title="t('actions.more', 'More actions')"
                                >
                                    <i
                                        class="mdi mdi-dots-vertical text-base"
                                        aria-hidden="true"
                                    />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="w-40">
                                <DropdownMenuItem as-child>
                                    <button
                                        type="button"
                                        class="flex w-full items-center gap-2 text-red-700"
                                        @click="openDeleteQueryDialog"
                                    >
                                        <i
                                            class="mdi mdi-delete"
                                            aria-hidden="true"
                                        />
                                        {{ t('actions.delete', 'Delete') }}
                                    </button>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <RwActionButton
                            :label="t('actions.run', 'Run')"
                            icon="mdi mdi-play-circle-outline"
                            tone="neutral"
                            :disabled="
                                isNew ||
                                runDialogPreparing ||
                                runDialogSubmitting
                            "
                            :loading="runDialogPreparing || runDialogSubmitting"
                            @click="openRunDialogOrNavigate"
                        />
                        <AdminFormSaveButton
                            type="button"
                            :dirty="form.isDirty"
                            :processing="form.processing"
                            :label="t('actions.save', 'Save')"
                            @click="submitForm"
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
                </div>
                <div class="flex flex-wrap items-center gap-x-5 gap-y-1">
                    <div class="font-medium text-slate-700">
                        {{ commonT('record_meta.updated_at', 'Updated') }}:
                        <span class="ml-1 text-base font-bold text-slate-950">
                            {{ updatedAtLabel }}
                        </span>
                    </div>
                    <div class="font-medium text-slate-700">
                        {{ commonT('record_meta.created_at', 'Created') }}:
                        <span class="ml-1 text-base font-bold text-slate-950">
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

                <div class="space-y-4 p-4 sm:p-5">
                    <section
                        v-if="activeTab === 'omschrijving'"
                        class="space-y-3"
                    >
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">
                                {{ t('form.sections.basic', 'Basis') }}
                            </h2>
                        </div>
                        <div class="grid items-start gap-3 md:grid-cols-2">
                            <div
                                class="grid items-start gap-3 md:col-span-2 md:grid-cols-[minmax(0,28rem)_auto]"
                            >
                                <div class="grid gap-1">
                                    <label
                                        class="flex items-center gap-1 text-xs text-slate-600"
                                    >
                                        <span
                                            class="text-red-600"
                                            aria-hidden="true"
                                            >*</span
                                        >
                                        {{
                                            t(
                                                'form.fields.description',
                                                'Omschrijving',
                                            )
                                        }}
                                    </label>
                                    <input
                                        id="description"
                                        v-model="form.description"
                                        type="text"
                                        required
                                        :class="[
                                            'h-9 rounded-md border border-slate-300 px-3 text-sm',
                                            requiredClass('description'),
                                        ]"
                                        @blur="touchAndNormalize('description')"
                                    />
                                    <FieldValidationMessage
                                        :message="
                                            validationMessage('description')
                                        "
                                        :warning="
                                            validationWarning('description')
                                        "
                                        :value="form.description"
                                    />
                                </div>

                                <div class="pt-6">
                                    <label
                                        class="inline-flex items-center gap-2 text-sm text-slate-700"
                                    >
                                        <input
                                            v-model="form.is_active"
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-slate-300"
                                        />
                                        {{ t('status.active', 'Active') }}
                                    </label>
                                </div>
                            </div>

                            <div id="slug" class="grid gap-1">
                                <label
                                    class="flex items-center gap-1 text-xs text-slate-600"
                                >
                                    <span
                                        class="text-red-600"
                                        aria-hidden="true"
                                        >*</span
                                    >
                                    {{ t('form.fields.slug', 'Slug') }}
                                </label>
                                <input
                                    v-model="form.slug"
                                    type="text"
                                    required
                                    :class="[
                                        'h-9 rounded-md border border-slate-300 px-3 text-sm',
                                        requiredClass('slug'),
                                    ]"
                                    @blur="touchAndNormalize('slug')"
                                />
                                <FieldValidationMessage
                                    :message="validationMessage('slug')"
                                    :warning="validationWarning('slug')"
                                    :value="form.slug"
                                />
                            </div>

                            <div id="memo" class="grid gap-1 md:col-span-2">
                                <label class="text-xs text-slate-600">{{
                                    t('form.fields.memo', 'Memo')
                                }}</label>
                                <textarea
                                    v-model="form.memo"
                                    rows="6"
                                    class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm"
                                />
                            </div>
                        </div>
                    </section>

                    <section v-if="activeTab === 'query'" class="space-y-3">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">
                                {{
                                    t('form.sections.query_mode', 'Query mode')
                                }}
                            </h2>
                        </div>
                        <div class="grid items-start gap-3 md:grid-cols-2">
                            <div class="grid gap-1">
                                <label class="text-xs text-slate-600">{{
                                    t(
                                        'form.fields.query_editor_type',
                                        'Type query editor',
                                    )
                                }}</label>
                                <div class="flex flex-wrap gap-2">
                                    <label
                                        v-for="option in modeOptions"
                                        :key="`query-mode-${option.value}`"
                                        class="inline-flex items-center gap-2 rounded border px-3 py-2 text-sm"
                                        :class="
                                            form.query_mode === option.value
                                                ? 'border-blue-500 bg-blue-50 text-blue-700'
                                                : 'border-slate-300 bg-white text-slate-700'
                                        "
                                    >
                                        <input
                                            v-model="form.query_mode"
                                            type="radio"
                                            :value="option.value"
                                            class="h-4 w-4 border-slate-300 text-blue-600"
                                        />
                                        {{ option.label }}
                                    </label>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section v-if="activeTab === 'uitvoer'" class="space-y-3">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">
                                {{
                                    t(
                                        'form.sections.output_mode',
                                        'Uitvoer mode',
                                    )
                                }}
                            </h2>
                        </div>
                        <div class="grid gap-3 md:grid-cols-2">
                            <div class="grid gap-1">
                                <label class="text-xs text-slate-600">{{
                                    t('form.fields.output_mode', 'Output mode')
                                }}</label>
                                <div class="flex flex-wrap gap-2">
                                    <label
                                        v-for="option in outputModeOptions"
                                        :key="`output-mode-${option.value}`"
                                        class="inline-flex items-center gap-2 rounded border px-3 py-2 text-sm"
                                        :class="
                                            form.output_mode === option.value
                                                ? 'border-blue-500 bg-blue-50 text-blue-700'
                                                : 'border-slate-300 bg-white text-slate-700'
                                        "
                                    >
                                        <input
                                            v-model="form.output_mode"
                                            type="radio"
                                            :value="option.value"
                                            class="h-4 w-4 border-slate-300 text-blue-600"
                                        />
                                        {{ option.label }}
                                    </label>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        v-if="activeTab === 'uitvoer' && isTableOutputMode"
                        class="space-y-3"
                    >
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">
                                {{
                                    t(
                                        'form.sections.table_settings',
                                        'Tabel instellingen',
                                    )
                                }}
                            </h2>
                        </div>
                        <div>
                            <p class="text-sm text-slate-600">
                                {{
                                    t(
                                        'form.output.table_help',
                                        'Deze query wordt als tabel uitgevoerd in de run-weergave.',
                                    )
                                }}
                            </p>
                        </div>
                    </section>

                    <section
                        v-if="
                            activeTab === 'uitvoer' &&
                            String(form.output_mode) === 'excel'
                        "
                        class="space-y-3"
                    >
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">
                                {{
                                    t(
                                        'form.sections.excel_settings',
                                        'Excel instellingen',
                                    )
                                }}
                            </h2>
                        </div>
                        <div>
                            <p class="text-sm text-slate-600">
                                {{
                                    t(
                                        'form.output.excel_help',
                                        'Deze query wordt als Excel-export uitgevoerd.',
                                    )
                                }}
                            </p>
                        </div>
                    </section>

                    <section
                        v-if="activeTab === 'uitvoer' && isReportOutputMode"
                        class="space-y-3"
                    >
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">
                                {{
                                    t(
                                        'form.sections.report_settings',
                                        'Rapport instellingen',
                                    )
                                }}
                            </h2>
                        </div>
                        <div class="grid gap-3 md:grid-cols-2">
                            <div id="report_data_source" class="grid gap-1">
                                <label
                                    class="flex items-center gap-1 text-xs text-slate-600"
                                >
                                    <span
                                        class="text-red-600"
                                        aria-hidden="true"
                                        >*</span
                                    >
                                    {{
                                        t(
                                            'form.report.data_source',
                                            'Rapport data',
                                        )
                                    }}
                                </label>
                                <RwAutoCompleteInput
                                    v-model="form.report_data_source"
                                    :items="reportDataSourceOptions"
                                    item-title="label"
                                    item-value="value"
                                    :search-fields="['label']"
                                    :required-missing="true"
                                    required-highlight-color="#fefce8"
                                />
                                <p
                                    v-if="form.errors.report_data_source"
                                    class="text-[11px] text-red-600"
                                >
                                    {{ form.errors.report_data_source }}
                                </p>
                            </div>

                            <div id="report_output_format" class="grid gap-1">
                                <label
                                    class="flex items-center gap-1 text-xs text-slate-600"
                                >
                                    <span
                                        class="text-red-600"
                                        aria-hidden="true"
                                        >*</span
                                    >
                                    {{
                                        t(
                                            'form.report.output_format',
                                            'Rapport output',
                                        )
                                    }}
                                </label>
                                <RwAutoCompleteInput
                                    v-model="form.report_output_format"
                                    :items="reportOutputFormatOptions"
                                    item-title="label"
                                    item-value="value"
                                    :search-fields="['label']"
                                    :required-missing="true"
                                    required-highlight-color="#fefce8"
                                />
                                <p
                                    v-if="form.errors.report_output_format"
                                    class="text-[11px] text-red-600"
                                >
                                    {{ form.errors.report_output_format }}
                                </p>
                                <p class="text-[11px] text-slate-500">
                                    {{
                                        t(
                                            'form.report.pdf_tip',
                                            'Tip: PDF output werkt momenteel voor xlsx/ods/docx/odt templates.',
                                        )
                                    }}
                                </p>
                            </div>

                            <div
                                id="report_template_upload"
                                class="grid gap-1 md:col-span-2"
                            >
                                <div class="flex items-center gap-1">
                                    <label class="text-xs text-slate-600">{{
                                        t(
                                            'form.report.template_upload',
                                            'Template upload (xlsx/ods/docx/odt)',
                                        )
                                    }}</label>
                                    <RwHelpDialogButton
                                        :title="
                                            t(
                                                'form.report.template_help_title',
                                                'Template cheatsheet',
                                            )
                                        "
                                        :subtitle="
                                            t(
                                                'form.report.template_help_subtitle',
                                                'Gebruik deze placeholders in xlsx/ods/docx/odt templates.',
                                            )
                                        "
                                        :tooltip="
                                            t(
                                                'form.report.template_help_tooltip',
                                                'Toon template cheatsheet',
                                            )
                                        "
                                        :aria-label="
                                            t(
                                                'form.report.template_help_aria',
                                                'Open template cheatsheet',
                                            )
                                        "
                                        max-width-class="sm:max-w-3xl"
                                    >
                                        <div
                                            v-if="props.template_help_html"
                                            class="max-h-[65vh] overflow-y-auto pr-1 text-sm text-slate-700 [&_code]:rounded [&_code]:bg-slate-100 [&_code]:px-1 [&_code]:py-0.5 [&_h1]:mb-3 [&_h1]:text-base [&_h1]:font-semibold [&_h2]:mb-2 [&_h2]:mt-4 [&_h2]:text-sm [&_h2]:font-semibold [&_li]:mb-1 [&_p]:mb-2 [&_pre]:mb-3 [&_pre]:overflow-x-auto [&_pre]:rounded [&_pre]:border [&_pre]:border-slate-200 [&_pre]:bg-slate-50 [&_pre]:px-3 [&_pre]:py-2 [&_pre]:text-xs [&_table]:mb-3 [&_table]:w-full [&_td]:border-b [&_td]:border-slate-100 [&_td]:px-2 [&_td]:py-1 [&_td]:align-top [&_th]:border-b [&_th]:border-slate-200 [&_th]:px-2 [&_th]:py-1 [&_th]:text-left [&_th]:text-xs [&_th]:font-semibold [&_ul]:mb-2 [&_ul]:list-disc [&_ul]:pl-5"
                                            v-html="props.template_help_html"
                                        />

                                        <p
                                            v-else
                                            class="text-xs text-slate-500"
                                        >
                                            {{
                                                t(
                                                    'form.report.template_help_empty',
                                                    'Geen helpinhoud beschikbaar.',
                                                )
                                            }}
                                        </p>
                                    </RwHelpDialogButton>
                                </div>
                                <input
                                    type="file"
                                    accept=".xlsx,.ods,.docx,.odt"
                                    class="block w-full cursor-pointer rounded-md border border-slate-300 bg-white px-3 py-2 text-sm"
                                    @change="onReportTemplateChange"
                                />
                                <p
                                    v-if="form.errors.report_template_upload"
                                    class="text-[11px] text-red-600"
                                >
                                    {{ form.errors.report_template_upload }}
                                </p>
                                <p
                                    v-if="props.query.report_template_filename"
                                    class="text-[11px] text-slate-500"
                                >
                                    {{
                                        t(
                                            'form.report.current_template',
                                            'Huidige template:',
                                        )
                                    }}
                                    <span class="font-medium text-slate-700">{{
                                        props.query.report_template_filename
                                    }}</span>
                                    <span
                                        v-if="
                                            props.query.report_template_size_kb
                                        "
                                    >
                                        ({{
                                            props.query.report_template_size_kb
                                        }}
                                        KB)
                                    </span>
                                </p>
                                <div
                                    v-if="props.query.report_template_filename"
                                >
                                    <RwActionButton
                                        :label="
                                            t(
                                                'form.report.download_template',
                                                'Download template',
                                            )
                                        "
                                        icon="mdi mdi-download"
                                        tone="neutral"
                                        @click="openReportTemplateDownload"
                                    />
                                </div>
                            </div>

                            <div
                                v-if="form.report_data_source === 'external'"
                                class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 md:col-span-2"
                            >
                                {{
                                    t(
                                        'form.report.external_warning',
                                        'Externe data (workflow) rapporten worden enkel via workflow uitgevoerd.',
                                    )
                                }}
                            </div>
                        </div>
                    </section>

                    <section
                        v-if="activeTab === 'uitvoer' && isChartOutputMode"
                        class="space-y-3"
                    >
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">
                                {{
                                    t(
                                        'form.sections.chart_settings',
                                        'Grafiek instellingen',
                                    )
                                }}
                            </h2>
                        </div>
                        <div class="grid gap-3 md:grid-cols-2">
                            <div class="grid gap-1">
                                <label class="text-xs text-slate-600">{{
                                    t(
                                        'form.chart.title_label',
                                        'Grafiek titel (optioneel)',
                                    )
                                }}</label>
                                <input
                                    v-model="inlineChartBuilder.title"
                                    type="text"
                                    class="h-9 rounded-md border border-slate-300 bg-white px-3 text-sm"
                                />
                            </div>

                            <div class="grid gap-1">
                                <label class="text-xs text-slate-600">{{
                                    t(
                                        'form.chart.subtitle_label',
                                        'Grafiek subtitel (optioneel)',
                                    )
                                }}</label>
                                <input
                                    v-model="inlineChartBuilder.subtitle"
                                    type="text"
                                    class="h-9 rounded-md border border-slate-300 bg-white px-3 text-sm"
                                />
                            </div>

                            <div class="grid gap-1">
                                <label class="text-xs text-slate-600">{{
                                    t('form.chart.type', 'Grafiek type')
                                }}</label>
                                <RwAutoCompleteInput
                                    v-model="inlineChartBuilder.chartType"
                                    :items="chartTypeOptions"
                                    item-title="label"
                                    item-value="value"
                                    :search-fields="['label']"
                                />
                            </div>

                            <div class="grid gap-1">
                                <label class="text-xs text-slate-600">{{
                                    t('form.chart.sorting', 'Sortering')
                                }}</label>
                                <RwAutoCompleteInput
                                    v-model="inlineChartBuilder.sortDirection"
                                    :items="chartSortDirectionOptions"
                                    item-title="label"
                                    item-value="value"
                                    :search-fields="['label']"
                                />
                            </div>

                            <div class="grid gap-1">
                                <label class="text-xs text-slate-600">{{
                                    t(
                                        'form.chart.orientation_label',
                                        'Orientatie',
                                    )
                                }}</label>
                                <RwAutoCompleteInput
                                    v-model="inlineChartBuilder.orientation"
                                    :items="chartOrientationOptions"
                                    item-title="label"
                                    item-value="value"
                                    :search-fields="['label']"
                                />
                            </div>

                            <div class="grid gap-1">
                                <label
                                    class="flex items-center gap-1 text-xs text-slate-600"
                                >
                                    <span
                                        class="text-red-600"
                                        aria-hidden="true"
                                        >*</span
                                    >
                                    {{ t('form.chart.x_field', 'X-veld') }}
                                </label>
                                <RwAutoCompleteInput
                                    v-model="inlineChartBuilder.xField"
                                    :items="selectFieldItems"
                                    item-title="title"
                                    item-value="value"
                                    :search-fields="['title']"
                                    :required-missing="true"
                                    required-highlight-color="#fefce8"
                                />
                            </div>

                            <div class="grid gap-1">
                                <label class="text-xs text-slate-600">{{
                                    t('form.chart.aggregate', 'Aggregatie')
                                }}</label>
                                <RwAutoCompleteInput
                                    v-model="inlineChartBuilder.aggregate"
                                    :items="chartAggregateOptions"
                                    item-title="label"
                                    item-value="value"
                                    :search-fields="['label']"
                                />
                            </div>

                            <div class="grid gap-1">
                                <label class="text-xs text-slate-600">{{
                                    t('form.chart.metric_field', 'Metric veld')
                                }}</label>
                                <RwAutoCompleteInput
                                    v-model="inlineChartBuilder.metricField"
                                    :items="selectFieldItems"
                                    item-title="title"
                                    item-value="value"
                                    :search-fields="['title']"
                                    :disabled="
                                        String(inlineChartBuilder.aggregate) ===
                                        'count'
                                    "
                                />
                                <p
                                    v-if="
                                        String(inlineChartBuilder.aggregate) ===
                                        'count'
                                    "
                                    class="text-[11px] text-slate-500"
                                >
                                    {{
                                        t(
                                            'form.chart.metric_not_required_count',
                                            'Niet nodig bij aggregatie "Aantal".',
                                        )
                                    }}
                                </p>
                            </div>

                            <div class="grid gap-1">
                                <label class="text-xs text-slate-600">{{
                                    t(
                                        'form.chart.series_field',
                                        'Series veld (optioneel)',
                                    )
                                }}</label>
                                <RwAutoCompleteInput
                                    v-model="inlineChartBuilder.seriesField"
                                    :items="selectFieldItems"
                                    item-title="title"
                                    item-value="value"
                                    :search-fields="['title']"
                                />
                            </div>

                            <div class="grid gap-1">
                                <label class="text-xs text-slate-600">{{
                                    t('form.chart.limit', 'Top N (1 - 500)')
                                }}</label>
                                <input
                                    v-model.number="inlineChartBuilder.limit"
                                    type="number"
                                    min="1"
                                    max="500"
                                    class="h-9 rounded-md border border-slate-300 bg-white px-3 text-sm"
                                />
                            </div>

                            <div
                                class="rounded-md border border-slate-200 bg-slate-50 p-3 md:col-span-2"
                            >
                                <p class="text-sm font-medium text-slate-800">
                                    {{
                                        t(
                                            'form.chart.viewer_settings',
                                            'Viewer instellingen',
                                        )
                                    }}
                                </p>
                                <div class="mt-2 grid gap-2 md:grid-cols-2">
                                    <label
                                        class="inline-flex items-center gap-2 text-sm text-slate-700"
                                    >
                                        <input
                                            v-model="
                                                inlineChartBuilder.showLegend
                                            "
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-slate-300"
                                        />
                                        {{
                                            t(
                                                'form.chart.show_legend',
                                                'Toon legenda',
                                            )
                                        }}
                                    </label>
                                    <label
                                        class="inline-flex items-center gap-2 text-sm text-slate-700"
                                    >
                                        <input
                                            v-model="inlineChartBuilder.stacked"
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-slate-300"
                                        />
                                        {{
                                            t(
                                                'form.chart.stacked',
                                                'Reeksen stapelen',
                                            )
                                        }}
                                    </label>
                                    <label
                                        class="inline-flex items-center gap-2 text-sm text-slate-700"
                                    >
                                        <input
                                            v-model="
                                                inlineChartBuilder.showSourceTableButton
                                            "
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-slate-300"
                                        />
                                        {{
                                            t(
                                                'form.chart.show_source_table_button',
                                                'Toon knop "Tabel brondata"',
                                            )
                                        }}
                                    </label>
                                    <label
                                        class="inline-flex items-center gap-2 text-sm text-slate-700"
                                    >
                                        <input
                                            v-model="
                                                inlineChartBuilder.allowViewerChartTypeChange
                                            "
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-slate-300"
                                        />
                                        {{
                                            t(
                                                'form.chart.allow_type_change',
                                                'Laat grafiektype wijzigen',
                                            )
                                        }}
                                    </label>
                                    <label
                                        class="inline-flex items-center gap-2 text-sm text-slate-700"
                                    >
                                        <input
                                            v-model="
                                                inlineChartBuilder.showPdfPrintButton
                                            "
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-slate-300"
                                        />
                                        {{
                                            t(
                                                'form.chart.show_pdf_print_button',
                                                'Toon knop "PDF afdrukken"',
                                            )
                                        }}
                                    </label>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        v-if="activeTab === 'query' && isBuilderMode"
                        class="space-y-3"
                    >
                        <div>
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <h2
                                    class="text-base font-semibold text-slate-900"
                                >
                                    {{
                                        t('form.builder.title', 'Builder query')
                                    }}
                                </h2>
                                <div class="flex items-center gap-2">
                                    <RwActionButton
                                        :label="
                                            t(
                                                'form.builder.actions.join',
                                                'Join',
                                            )
                                        "
                                        icon="mdi mdi-link-variant"
                                        tone="neutral"
                                        @click="queryBuilderAddJoinRow"
                                    />
                                    <RwActionButton
                                        :label="
                                            t(
                                                'form.builder.actions.where',
                                                'Where',
                                            )
                                        "
                                        icon="mdi mdi-filter-plus"
                                        tone="neutral"
                                        @click="queryBuilderAddWhereRow"
                                    />
                                </div>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div class="grid gap-3 md:grid-cols-4">
                                <div
                                    id="table_name"
                                    class="grid gap-1 md:col-span-2"
                                >
                                    <label
                                        class="flex items-center gap-1 text-xs text-slate-600"
                                    >
                                        <span
                                            v-if="isBuilderMode"
                                            class="text-red-600"
                                            aria-hidden="true"
                                            >*</span
                                        >
                                        {{
                                            t(
                                                'form.builder.table_from',
                                                'Table (FROM)',
                                            )
                                        }}
                                    </label>
                                    <RwAutoCompleteInput
                                        v-model="form.table_name"
                                        :items="tableOptions"
                                        item-title="title"
                                        item-value="value"
                                        :search-fields="['title']"
                                        :required="isBuilderMode"
                                        :invalid="
                                            Boolean(
                                                validationMessage('table_name'),
                                            )
                                        "
                                        :error-message="
                                            validationMessage('table_name')
                                        "
                                        @blur="touchAndClear('table_name')"
                                    />
                                    <FieldValidationMessage
                                        :message="
                                            validationMessage('table_name')
                                        "
                                        :warning="
                                            validationWarning('table_name')
                                        "
                                        :value="form.table_name"
                                    />
                                </div>

                                <label
                                    class="inline-flex items-center gap-2 text-sm text-slate-700 md:pt-6"
                                >
                                    <input
                                        v-model="form.all_fields"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-slate-300"
                                    />
                                    {{
                                        t(
                                            'form.builder.select_all_fields',
                                            'Alle velden selecteren',
                                        )
                                    }}
                                </label>

                                <label
                                    class="inline-flex items-center gap-2 text-sm text-slate-700 md:pt-6"
                                >
                                    <input
                                        v-model="form.distinct_select"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-slate-300"
                                    />
                                    DISTINCT
                                </label>
                            </div>

                            <div
                                id="selected_fields"
                                v-if="!form.all_fields"
                                class="grid gap-1 rounded-md border border-slate-200 p-3"
                            >
                                <label class="text-xs text-slate-600">{{
                                    t(
                                        'form.builder.select_fields',
                                        'Select velden',
                                    )
                                }}</label>
                                <RwAutoCompleteInput
                                    v-model="form.selected_fields"
                                    :items="selectFieldItems"
                                    item-title="title"
                                    item-value="value"
                                    :search-fields="['title']"
                                    :multiple="true"
                                />
                            </div>

                            <div
                                id="where_rows"
                                class="space-y-3 rounded-md border border-slate-200 p-3"
                            >
                                <div class="flex items-center justify-between">
                                    <p
                                        class="text-sm font-medium text-slate-800"
                                    >
                                        {{ t('form.builder.joins', 'JOINs') }}
                                    </p>
                                    <RwActionButton
                                        :label="
                                            t('form.builder.actions.row', 'Rij')
                                        "
                                        icon="mdi mdi-plus-circle"
                                        tone="new"
                                        @click="queryBuilderAddJoinRow"
                                    />
                                </div>

                                <div
                                    v-if="form.join_rows.length === 0"
                                    class="text-xs text-slate-500"
                                >
                                    {{
                                        t(
                                            'form.builder.empty_joins',
                                            'Geen joins toegevoegd.',
                                        )
                                    }}
                                </div>

                                <div
                                    v-for="(row, index) in form.join_rows"
                                    :key="`join-${index}`"
                                    class="grid gap-2 rounded border border-slate-200 p-3 md:grid-cols-12"
                                    :style="{
                                        paddingLeft: `${Number(row.paddingLeft || 0) + 12}px`,
                                    }"
                                >
                                    <div
                                        class="flex items-center md:col-span-1"
                                        :class="
                                            row.subRow
                                                ? 'text-blue-700'
                                                : 'text-slate-400'
                                        "
                                    >
                                        <i
                                            v-if="row.subRow"
                                            class="mdi mdi-arrow-right-bottom-bold text-lg"
                                        />
                                    </div>
                                    <div class="grid gap-1 md:col-span-2">
                                        <label class="text-xs text-slate-600">{{
                                            t(
                                                'form.builder.fields.type',
                                                'Type',
                                            )
                                        }}</label>
                                        <RwAutoCompleteInput
                                            v-model="row.joinType"
                                            :items="joinTypeOptions"
                                            item-title="label"
                                            item-value="value"
                                            :search-fields="['label']"
                                        />
                                    </div>
                                    <div class="grid gap-1 md:col-span-2">
                                        <label class="text-xs text-slate-600">{{
                                            t(
                                                'form.builder.fields.source_table',
                                                'Bron tabel',
                                            )
                                        }}</label>
                                        <RwAutoCompleteInput
                                            v-model="row.originTable"
                                            :items="tableOptions"
                                            item-title="title"
                                            item-value="value"
                                            :search-fields="['title']"
                                        />
                                    </div>
                                    <div class="grid gap-1 md:col-span-2">
                                        <label class="text-xs text-slate-600">{{
                                            t(
                                                'form.builder.fields.relation_table',
                                                'Relatie tabel',
                                            )
                                        }}</label>
                                        <RwAutoCompleteInput
                                            v-model="row.relTable"
                                            :items="
                                                relationshipOptionsForOrigin(
                                                    row.originTable,
                                                )
                                            "
                                            item-title="title"
                                            item-value="value"
                                            :search-fields="['title']"
                                        />
                                    </div>
                                    <div class="grid gap-1 md:col-span-2">
                                        <label class="text-xs text-slate-600">{{
                                            t(
                                                'form.builder.fields.source_field',
                                                'Veld bron',
                                            )
                                        }}</label>
                                        <RwAutoCompleteInput
                                            v-model="row.relFieldT1"
                                            :items="
                                                joinFieldOptionsForTable(
                                                    row.originTable ||
                                                        form.table_name,
                                                )
                                            "
                                            item-title="title"
                                            item-value="value"
                                            :search-fields="['title']"
                                        />
                                    </div>
                                    <div class="grid gap-1 md:col-span-2">
                                        <label class="text-xs text-slate-600">{{
                                            t(
                                                'form.builder.fields.relation_field',
                                                'Veld relatie',
                                            )
                                        }}</label>
                                        <RwAutoCompleteInput
                                            v-model="row.relFieldT2"
                                            :items="
                                                joinFieldOptionsForTable(
                                                    row.relTable,
                                                )
                                            "
                                            item-title="title"
                                            item-value="value"
                                            :search-fields="['title']"
                                        />
                                    </div>
                                    <div
                                        class="flex items-end justify-end md:col-span-1"
                                    >
                                        <RwActionButton
                                            :label="
                                                t(
                                                    'form.builder.actions.subrow',
                                                    'Subrij',
                                                )
                                            "
                                            icon="mdi mdi-chevron-right-circle"
                                            tone="neutral"
                                            :icon-only="true"
                                            @click="
                                                queryBuilderAddJoinSubRow(index)
                                            "
                                        />
                                        <RwActionButton
                                            :label="
                                                t(
                                                    'actions.delete',
                                                    'Verwijderen',
                                                )
                                            "
                                            icon="mdi mdi-delete"
                                            tone="delete"
                                            :icon-only="true"
                                            @click="
                                                queryBuilderRemoveJoinRow(index)
                                            "
                                        />
                                    </div>
                                </div>
                            </div>

                            <div
                                id="group_rows"
                                class="space-y-3 rounded-md border border-slate-200 p-3"
                            >
                                <div class="flex items-center justify-between">
                                    <p
                                        class="text-sm font-medium text-slate-800"
                                    >
                                        {{
                                            t(
                                                'form.builder.where_filters',
                                                'WHERE filters',
                                            )
                                        }}
                                    </p>
                                    <RwActionButton
                                        :label="
                                            t('form.builder.actions.row', 'Rij')
                                        "
                                        icon="mdi mdi-plus-circle"
                                        tone="new"
                                        @click="queryBuilderAddWhereRow"
                                    />
                                </div>

                                <div
                                    v-if="form.where_rows.length === 0"
                                    class="text-xs text-slate-500"
                                >
                                    {{
                                        t(
                                            'form.builder.empty_where_filters',
                                            'Geen where-filters toegevoegd.',
                                        )
                                    }}
                                </div>

                                <p
                                    v-if="formError('where_rows')"
                                    class="text-[11px] text-red-600"
                                >
                                    {{ formError('where_rows') }}
                                </p>

                                <div
                                    v-for="(row, index) in form.where_rows"
                                    :key="`where-${index}`"
                                    class="grid gap-2 rounded border border-slate-200 p-3 md:grid-cols-12"
                                    :style="{
                                        paddingLeft: `${Number(row.paddingLeft || 0) + 12}px`,
                                    }"
                                >
                                    <div
                                        class="flex items-center md:col-span-1"
                                        :class="
                                            row.subRow
                                                ? 'text-blue-700'
                                                : 'text-slate-400'
                                        "
                                    >
                                        <i
                                            v-if="row.subRow"
                                            class="mdi mdi-arrow-right-bottom-bold text-lg"
                                        />
                                    </div>
                                    <div class="grid gap-1 md:col-span-1">
                                        <label class="text-xs text-slate-600">{{
                                            t(
                                                'form.builder.fields.and_or',
                                                'AND/OR',
                                            )
                                        }}</label>
                                        <RwAutoCompleteInput
                                            v-model="row.whereFieldAndOr"
                                            :items="andOrOptions"
                                            item-title="value"
                                            item-value="value"
                                            :search-fields="['value']"
                                        />
                                    </div>
                                    <div class="grid gap-1 md:col-span-3">
                                        <label class="text-xs text-slate-600">{{
                                            t(
                                                'form.builder.fields.field',
                                                'Veld',
                                            )
                                        }}</label>
                                        <RwAutoCompleteInput
                                            v-model="row.whereField"
                                            :items="selectFieldItems"
                                            item-title="title"
                                            item-value="value"
                                            :search-fields="['title']"
                                        />
                                    </div>
                                    <div class="grid gap-1 md:col-span-2">
                                        <label class="text-xs text-slate-600">{{
                                            t(
                                                'form.builder.fields.condition',
                                                'Conditie',
                                            )
                                        }}</label>
                                        <RwAutoCompleteInput
                                            v-model="row.whereFieldCondition"
                                            :items="whereConditionOptions"
                                            item-title="value"
                                            item-value="value"
                                            :search-fields="['value']"
                                        />
                                        <p class="text-[11px] text-slate-500">
                                            {{ conditionHelpText(row) }}
                                        </p>
                                    </div>
                                    <div class="grid gap-1 md:col-span-2">
                                        <label class="text-xs text-slate-600">{{
                                            t(
                                                'form.builder.fields.value_type',
                                                'Waarde type',
                                            )
                                        }}</label>
                                        <RwAutoCompleteInput
                                            v-model="row.varOrValue"
                                            :items="whereValueTypeOptions"
                                            item-title="label"
                                            item-value="value"
                                            :search-fields="['label']"
                                        />
                                    </div>
                                    <div
                                        v-if="
                                            row.varOrValue === 'Parameter' &&
                                            !rowIsNullCondition(row)
                                        "
                                        class="grid gap-1 md:col-span-2"
                                    >
                                        <label class="text-xs text-slate-600">
                                            {{
                                                rowIsRangeCondition(row)
                                                    ? t(
                                                          'form.builder.fields.parameter_from',
                                                          'Parameter vanaf',
                                                      )
                                                    : t(
                                                          'form.builder.fields.parameter',
                                                          'Parameter',
                                                      )
                                            }}
                                        </label>
                                        <input
                                            v-model="row.variabele"
                                            type="text"
                                            class="h-9 rounded-md border border-slate-300 bg-white px-3 text-sm"
                                            :placeholder="
                                                rowIsRangeCondition(row)
                                                    ? t(
                                                          'form.builder.placeholders.parameter_from',
                                                          'bijv. user_id_from',
                                                      )
                                                    : t(
                                                          'form.builder.placeholders.parameter',
                                                          'bijv. user_id',
                                                      )
                                            "
                                        />
                                    </div>
                                    <div
                                        v-if="
                                            row.varOrValue === 'Parameter' &&
                                            rowIsRangeCondition(row)
                                        "
                                        class="grid gap-1 md:col-span-2"
                                    >
                                        <label class="text-xs text-slate-600">{{
                                            t(
                                                'form.builder.fields.parameter_to',
                                                'Parameter tot',
                                            )
                                        }}</label>
                                        <input
                                            v-model="row.variabele_to"
                                            type="text"
                                            class="h-9 rounded-md border border-slate-300 bg-white px-3 text-sm"
                                            :placeholder="
                                                t(
                                                    'form.builder.placeholders.parameter_to',
                                                    'bijv. user_id_to',
                                                )
                                            "
                                        />
                                    </div>
                                    <div
                                        v-if="
                                            row.varOrValue ===
                                                'Systeemvariabele' &&
                                            !rowIsNullCondition(row)
                                        "
                                        class="grid gap-1 md:col-span-2"
                                    >
                                        <label class="text-xs text-slate-600">
                                            {{
                                                rowIsRangeCondition(row)
                                                    ? t(
                                                          'form.builder.fields.system_variable_from',
                                                          'Systeemvariabele vanaf',
                                                      )
                                                    : t(
                                                          'form.builder.fields.system_variable',
                                                          'Systeemvariabele',
                                                      )
                                            }}
                                        </label>
                                        <RwAutoCompleteInput
                                            v-model="row.value"
                                            :items="systemVariableOptions"
                                            item-title="label"
                                            item-value="value"
                                            :search-fields="['label']"
                                        />
                                    </div>
                                    <div
                                        v-if="
                                            row.varOrValue !==
                                                'Systeemvariabele' &&
                                            !rowIsNullCondition(row)
                                        "
                                        class="grid gap-1 md:col-span-2"
                                    >
                                        <label class="text-xs text-slate-600">
                                            {{
                                                rowIsRangeCondition(row)
                                                    ? t(
                                                          'form.builder.fields.value_from',
                                                          'Waarde vanaf',
                                                      )
                                                    : t(
                                                          'form.builder.fields.value',
                                                          'Waarde',
                                                      )
                                            }}
                                        </label>
                                        <input
                                            v-model="row.value"
                                            type="text"
                                            class="h-9 rounded-md border border-slate-300 bg-white px-3 text-sm"
                                        />
                                    </div>
                                    <div
                                        v-if="
                                            row.varOrValue ===
                                                'Systeemvariabele' &&
                                            rowIsRangeCondition(row)
                                        "
                                        class="grid gap-1 md:col-span-2"
                                    >
                                        <label class="text-xs text-slate-600">{{
                                            t(
                                                'form.builder.fields.system_variable_to',
                                                'Systeemvariabele tot',
                                            )
                                        }}</label>
                                        <RwAutoCompleteInput
                                            v-model="row.value_to"
                                            :items="systemVariableOptions"
                                            item-title="label"
                                            item-value="value"
                                            :search-fields="['label']"
                                        />
                                    </div>
                                    <div
                                        v-if="
                                            row.varOrValue !==
                                                'Systeemvariabele' &&
                                            rowIsRangeCondition(row) &&
                                            !rowIsNullCondition(row)
                                        "
                                        class="grid gap-1 md:col-span-2"
                                    >
                                        <label class="text-xs text-slate-600">{{
                                            t(
                                                'form.builder.fields.value_to',
                                                'Waarde tot',
                                            )
                                        }}</label>
                                        <input
                                            v-model="row.value_to"
                                            type="text"
                                            class="h-9 rounded-md border border-slate-300 bg-white px-3 text-sm"
                                        />
                                    </div>
                                    <div
                                        v-if="
                                            row.varOrValue === 'Parameter' &&
                                            !rowIsNullCondition(row)
                                        "
                                        class="grid gap-1 md:col-span-1"
                                    >
                                        <label class="text-xs text-slate-600">
                                            {{
                                                rowIsRangeCondition(row)
                                                    ? t(
                                                          'form.builder.fields.test_from',
                                                          'Test vanaf',
                                                      )
                                                    : t(
                                                          'form.builder.fields.test',
                                                          'Test',
                                                      )
                                            }}
                                        </label>
                                        <input
                                            v-model="row.testValue"
                                            type="text"
                                            class="h-9 rounded-md border border-slate-300 bg-white px-3 text-sm"
                                        />
                                    </div>
                                    <div
                                        v-if="
                                            row.varOrValue === 'Parameter' &&
                                            rowIsRangeCondition(row)
                                        "
                                        class="grid gap-1 md:col-span-1"
                                    >
                                        <label class="text-xs text-slate-600">{{
                                            t(
                                                'form.builder.fields.test_to',
                                                'Test tot',
                                            )
                                        }}</label>
                                        <input
                                            v-model="row.testValueTo"
                                            type="text"
                                            class="h-9 rounded-md border border-slate-300 bg-white px-3 text-sm"
                                        />
                                    </div>
                                    <div
                                        class="flex items-end justify-end md:col-span-1"
                                    >
                                        <RwActionButton
                                            :label="
                                                t(
                                                    'form.builder.actions.subrow',
                                                    'Subrij',
                                                )
                                            "
                                            icon="mdi mdi-chevron-right-circle"
                                            tone="neutral"
                                            :icon-only="true"
                                            :disabled="Boolean(row.subRow)"
                                            @click="
                                                queryBuilderAddWhereSubRow(
                                                    index,
                                                )
                                            "
                                        />
                                        <RwActionButton
                                            :label="
                                                t(
                                                    'actions.delete',
                                                    'Verwijderen',
                                                )
                                            "
                                            icon="mdi mdi-delete"
                                            tone="delete"
                                            :icon-only="true"
                                            @click="
                                                queryBuilderRemoveWhereRow(
                                                    index,
                                                )
                                            "
                                        />
                                    </div>

                                    <div
                                        v-if="
                                            builderRowErrors(
                                                'where_rows',
                                                index,
                                            ).length > 0
                                        "
                                        class="md:col-span-12"
                                    >
                                        <p
                                            v-for="(
                                                message, messageIndex
                                            ) in builderRowErrors(
                                                'where_rows',
                                                index,
                                            )"
                                            :key="`where-error-${index}-${messageIndex}`"
                                            class="text-[11px] text-red-600"
                                        >
                                            {{ message }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="space-y-3 rounded-md border border-slate-200 p-3"
                            >
                                <label
                                    class="inline-flex items-center gap-2 text-sm text-slate-700"
                                >
                                    <input
                                        v-model="form.group_by"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-slate-300"
                                    />
                                    {{
                                        t(
                                            'form.builder.use_group_by',
                                            'Group by gebruiken',
                                        )
                                    }}
                                </label>

                                <div v-if="form.group_by" class="grid gap-3">
                                    <div class="grid gap-1">
                                        <label class="text-xs text-slate-600">{{
                                            t(
                                                'form.builder.group_fields',
                                                'Group velden',
                                            )
                                        }}</label>
                                        <RwAutoCompleteInput
                                            v-model="form.group_rows"
                                            :items="selectFieldItems"
                                            item-title="title"
                                            item-value="value"
                                            :search-fields="['title']"
                                            :multiple="true"
                                        />
                                    </div>

                                    <div
                                        id="aggregate_rows"
                                        class="space-y-3 rounded-md border border-slate-200 p-3"
                                    >
                                        <div
                                            class="flex items-center justify-between"
                                        >
                                            <p
                                                class="text-sm font-medium text-slate-800"
                                            >
                                                {{
                                                    t(
                                                        'form.builder.aggregates',
                                                        'Aggregates',
                                                    )
                                                }}
                                            </p>
                                            <RwActionButton
                                                :label="
                                                    t(
                                                        'form.builder.actions.row',
                                                        'Rij',
                                                    )
                                                "
                                                icon="mdi mdi-plus-circle"
                                                tone="new"
                                                @click="
                                                    queryBuilderAddAggregateRow
                                                "
                                            />
                                        </div>

                                        <p
                                            v-if="formError('aggregate_rows')"
                                            class="text-[11px] text-red-600"
                                        >
                                            {{ formError('aggregate_rows') }}
                                        </p>

                                        <div
                                            v-for="(
                                                row, index
                                            ) in form.aggregate_rows"
                                            :key="`agg-${index}`"
                                            class="grid gap-2 rounded border border-slate-200 p-3 md:grid-cols-12"
                                        >
                                            <div
                                                class="grid gap-1 md:col-span-2"
                                            >
                                                <label
                                                    class="text-xs text-slate-600"
                                                    >{{
                                                        t(
                                                            'form.builder.fields.function',
                                                            'Functie',
                                                        )
                                                    }}</label
                                                >
                                                <RwAutoCompleteInput
                                                    v-model="row.func"
                                                    :items="
                                                        aggregateFunctionOptions
                                                    "
                                                    item-title="value"
                                                    item-value="value"
                                                    :search-fields="['value']"
                                                />
                                            </div>
                                            <div
                                                v-if="
                                                    !aggregateRowIsFormula(row)
                                                "
                                                class="grid gap-1 md:col-span-4"
                                            >
                                                <label
                                                    class="text-xs text-slate-600"
                                                >
                                                    {{
                                                        aggregateRowIsConcat(
                                                            row,
                                                        )
                                                            ? t(
                                                                  'form.builder.fields.fields_concat',
                                                                  'Velden (samenvoegen)',
                                                              )
                                                            : t(
                                                                  'form.builder.fields.field',
                                                                  'Veld',
                                                              )
                                                    }}
                                                </label>
                                                <RwAutoCompleteInput
                                                    v-if="
                                                        !aggregateRowIsConcat(
                                                            row,
                                                        )
                                                    "
                                                    v-model="row.field"
                                                    :items="selectFieldItems"
                                                    item-title="title"
                                                    item-value="value"
                                                    :search-fields="['title']"
                                                />
                                                <RwAutoCompleteInput
                                                    v-else
                                                    v-model="row.fields"
                                                    :items="selectFieldItems"
                                                    item-title="title"
                                                    item-value="value"
                                                    :search-fields="['title']"
                                                    :multiple="true"
                                                />
                                            </div>
                                            <div
                                                v-else
                                                class="grid gap-1 md:col-span-4"
                                            >
                                                <label
                                                    class="text-xs text-slate-600"
                                                    >{{
                                                        t(
                                                            'form.builder.fields.formula',
                                                            'Formule',
                                                        )
                                                    }}</label
                                                >
                                                <input
                                                    v-model="row.formula"
                                                    type="text"
                                                    class="h-9 rounded-md border border-slate-300 bg-white px-3 text-sm"
                                                    :placeholder="
                                                        t(
                                                            'form.builder.placeholders.formula',
                                                            'bijv. SUM(users.amount) / NULLIF(COUNT(users.id), 0)',
                                                        )
                                                    "
                                                />
                                            </div>
                                            <div
                                                class="grid gap-1 md:col-span-3"
                                            >
                                                <label
                                                    class="text-xs text-slate-600"
                                                    >{{
                                                        t(
                                                            'form.builder.fields.alias',
                                                            'Alias',
                                                        )
                                                    }}</label
                                                >
                                                <input
                                                    v-model="row.alias"
                                                    type="text"
                                                    class="h-9 rounded-md border border-slate-300 bg-white px-3 text-sm"
                                                />
                                            </div>
                                            <div
                                                class="grid gap-1 md:col-span-2"
                                            >
                                                <label
                                                    class="text-xs text-slate-600"
                                                    >{{
                                                        t(
                                                            'form.builder.fields.separator',
                                                            'Separator',
                                                        )
                                                    }}</label
                                                >
                                                <input
                                                    v-model="row.separator"
                                                    type="text"
                                                    class="h-9 rounded-md border border-slate-300 bg-white px-3 text-sm"
                                                    :disabled="
                                                        !aggregateRowSupportsSeparator(
                                                            row,
                                                        )
                                                    "
                                                />
                                            </div>
                                            <div
                                                class="flex items-end justify-end md:col-span-1"
                                            >
                                                <RwActionButton
                                                    :label="
                                                        t(
                                                            'actions.delete',
                                                            'Verwijderen',
                                                        )
                                                    "
                                                    icon="mdi mdi-delete"
                                                    tone="delete"
                                                    :icon-only="true"
                                                    @click="
                                                        queryBuilderRemoveAggregateRow(
                                                            index,
                                                        )
                                                    "
                                                />
                                            </div>
                                            <div class="md:col-span-12">
                                                <label
                                                    class="inline-flex items-center gap-2 text-xs text-slate-700"
                                                >
                                                    <input
                                                        v-model="row.distinct"
                                                        type="checkbox"
                                                        class="h-4 w-4 rounded border-slate-300"
                                                        :disabled="
                                                            !aggregateRowSupportsDistinct(
                                                                row,
                                                            )
                                                        "
                                                    />
                                                    {{
                                                        t(
                                                            'form.builder.distinct_in_aggregate',
                                                            'DISTINCT binnen aggregate',
                                                        )
                                                    }}
                                                </label>
                                            </div>

                                            <div
                                                v-if="
                                                    builderRowErrors(
                                                        'aggregate_rows',
                                                        index,
                                                    ).length > 0
                                                "
                                                class="md:col-span-12"
                                            >
                                                <p
                                                    v-for="(
                                                        message, messageIndex
                                                    ) in builderRowErrors(
                                                        'aggregate_rows',
                                                        index,
                                                    )"
                                                    :key="`agg-error-${index}-${messageIndex}`"
                                                    class="text-[11px] text-red-600"
                                                >
                                                    {{ message }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        id="having_rows"
                                        class="space-y-3 rounded-md border border-slate-200 p-3"
                                    >
                                        <div
                                            class="flex items-center justify-between"
                                        >
                                            <p
                                                class="text-sm font-medium text-slate-800"
                                            >
                                                HAVING
                                            </p>
                                            <RwActionButton
                                                :label="
                                                    t(
                                                        'form.builder.actions.row',
                                                        'Rij',
                                                    )
                                                "
                                                icon="mdi mdi-plus-circle"
                                                tone="new"
                                                @click="
                                                    queryBuilderAddHavingRow
                                                "
                                            />
                                        </div>

                                        <p
                                            v-if="formError('having_rows')"
                                            class="text-[11px] text-red-600"
                                        >
                                            {{ formError('having_rows') }}
                                        </p>

                                        <div
                                            v-for="(
                                                row, index
                                            ) in form.having_rows"
                                            :key="`having-${index}`"
                                            class="grid gap-2 rounded border border-slate-200 p-3 md:grid-cols-12"
                                            :style="{
                                                paddingLeft: `${Number(row.paddingLeft || 0) + 12}px`,
                                            }"
                                        >
                                            <div
                                                class="flex items-center md:col-span-1"
                                                :class="
                                                    row.subRow
                                                        ? 'text-blue-700'
                                                        : 'text-slate-400'
                                                "
                                            >
                                                <i
                                                    v-if="row.subRow"
                                                    class="mdi mdi-arrow-right-bottom-bold text-lg"
                                                />
                                            </div>
                                            <div
                                                class="grid gap-1 md:col-span-1"
                                            >
                                                <label
                                                    class="text-xs text-slate-600"
                                                    >{{
                                                        t(
                                                            'form.builder.fields.and_or',
                                                            'AND/OR',
                                                        )
                                                    }}</label
                                                >
                                                <RwAutoCompleteInput
                                                    v-model="
                                                        row.whereFieldAndOr
                                                    "
                                                    :items="andOrOptions"
                                                    item-title="value"
                                                    item-value="value"
                                                    :search-fields="['value']"
                                                />
                                            </div>
                                            <div
                                                class="grid gap-1 md:col-span-3"
                                            >
                                                <label
                                                    class="text-xs text-slate-600"
                                                    >{{
                                                        t(
                                                            'form.builder.fields.field_alias',
                                                            'Veld/Alias',
                                                        )
                                                    }}</label
                                                >
                                                <RwAutoCompleteInput
                                                    v-model="row.whereField"
                                                    :items="havingFieldItems"
                                                    item-title="title"
                                                    item-value="value"
                                                    :search-fields="['title']"
                                                />
                                            </div>
                                            <div
                                                class="grid gap-1 md:col-span-2"
                                            >
                                                <label
                                                    class="text-xs text-slate-600"
                                                    >{{
                                                        t(
                                                            'form.builder.fields.condition',
                                                            'Conditie',
                                                        )
                                                    }}</label
                                                >
                                                <RwAutoCompleteInput
                                                    v-model="
                                                        row.whereFieldCondition
                                                    "
                                                    :items="
                                                        whereConditionOptions
                                                    "
                                                    item-title="value"
                                                    item-value="value"
                                                    :search-fields="['value']"
                                                />
                                                <p
                                                    class="text-[11px] text-slate-500"
                                                >
                                                    {{ conditionHelpText(row) }}
                                                </p>
                                            </div>
                                            <div
                                                class="grid gap-1 md:col-span-2"
                                            >
                                                <label
                                                    class="text-xs text-slate-600"
                                                    >{{
                                                        t(
                                                            'form.builder.fields.value_type',
                                                            'Waarde type',
                                                        )
                                                    }}</label
                                                >
                                                <RwAutoCompleteInput
                                                    v-model="row.varOrValue"
                                                    :items="
                                                        whereValueTypeOptions
                                                    "
                                                    item-title="label"
                                                    item-value="value"
                                                    :search-fields="['label']"
                                                />
                                            </div>
                                            <div
                                                v-if="
                                                    row.varOrValue ===
                                                        'Parameter' &&
                                                    !rowIsNullCondition(row)
                                                "
                                                class="grid gap-1 md:col-span-2"
                                            >
                                                <label
                                                    class="text-xs text-slate-600"
                                                >
                                                    {{
                                                        rowIsRangeCondition(row)
                                                            ? t(
                                                                  'form.builder.fields.parameter_from',
                                                                  'Parameter vanaf',
                                                              )
                                                            : t(
                                                                  'form.builder.fields.parameter',
                                                                  'Parameter',
                                                              )
                                                    }}
                                                </label>
                                                <input
                                                    v-model="row.variabele"
                                                    type="text"
                                                    class="h-9 rounded-md border border-slate-300 bg-white px-3 text-sm"
                                                    :placeholder="
                                                        rowIsRangeCondition(row)
                                                            ? t(
                                                                  'form.builder.placeholders.having_parameter_from',
                                                                  'bijv. min_total',
                                                              )
                                                            : t(
                                                                  'form.builder.placeholders.having_parameter',
                                                                  'bijv. total_param',
                                                              )
                                                    "
                                                />
                                            </div>
                                            <div
                                                v-if="
                                                    row.varOrValue ===
                                                        'Parameter' &&
                                                    rowIsRangeCondition(row)
                                                "
                                                class="grid gap-1 md:col-span-2"
                                            >
                                                <label
                                                    class="text-xs text-slate-600"
                                                    >{{
                                                        t(
                                                            'form.builder.fields.parameter_to',
                                                            'Parameter tot',
                                                        )
                                                    }}</label
                                                >
                                                <input
                                                    v-model="row.variabele_to"
                                                    type="text"
                                                    class="h-9 rounded-md border border-slate-300 bg-white px-3 text-sm"
                                                    :placeholder="
                                                        t(
                                                            'form.builder.placeholders.having_parameter_to',
                                                            'bijv. max_total',
                                                        )
                                                    "
                                                />
                                            </div>
                                            <div
                                                v-if="
                                                    row.varOrValue ===
                                                        'Systeemvariabele' &&
                                                    !rowIsNullCondition(row)
                                                "
                                                class="grid gap-1 md:col-span-2"
                                            >
                                                <label
                                                    class="text-xs text-slate-600"
                                                >
                                                    {{
                                                        rowIsRangeCondition(row)
                                                            ? t(
                                                                  'form.builder.fields.system_variable_from',
                                                                  'Systeemvariabele vanaf',
                                                              )
                                                            : t(
                                                                  'form.builder.fields.system_variable',
                                                                  'Systeemvariabele',
                                                              )
                                                    }}
                                                </label>
                                                <RwAutoCompleteInput
                                                    v-model="row.value"
                                                    :items="
                                                        systemVariableOptions
                                                    "
                                                    item-title="label"
                                                    item-value="value"
                                                    :search-fields="['label']"
                                                />
                                            </div>
                                            <div
                                                v-if="
                                                    row.varOrValue !==
                                                        'Systeemvariabele' &&
                                                    !rowIsNullCondition(row)
                                                "
                                                class="grid gap-1 md:col-span-2"
                                            >
                                                <label
                                                    class="text-xs text-slate-600"
                                                >
                                                    {{
                                                        rowIsRangeCondition(row)
                                                            ? t(
                                                                  'form.builder.fields.value_from',
                                                                  'Waarde vanaf',
                                                              )
                                                            : t(
                                                                  'form.builder.fields.value',
                                                                  'Waarde',
                                                              )
                                                    }}
                                                </label>
                                                <input
                                                    v-model="row.value"
                                                    type="text"
                                                    class="h-9 rounded-md border border-slate-300 bg-white px-3 text-sm"
                                                />
                                            </div>
                                            <div
                                                v-if="
                                                    row.varOrValue ===
                                                        'Systeemvariabele' &&
                                                    rowIsRangeCondition(row)
                                                "
                                                class="grid gap-1 md:col-span-2"
                                            >
                                                <label
                                                    class="text-xs text-slate-600"
                                                    >{{
                                                        t(
                                                            'form.builder.fields.system_variable_to',
                                                            'Systeemvariabele tot',
                                                        )
                                                    }}</label
                                                >
                                                <RwAutoCompleteInput
                                                    v-model="row.value_to"
                                                    :items="
                                                        systemVariableOptions
                                                    "
                                                    item-title="label"
                                                    item-value="value"
                                                    :search-fields="['label']"
                                                />
                                            </div>
                                            <div
                                                v-if="
                                                    row.varOrValue !==
                                                        'Systeemvariabele' &&
                                                    rowIsRangeCondition(row) &&
                                                    !rowIsNullCondition(row)
                                                "
                                                class="grid gap-1 md:col-span-2"
                                            >
                                                <label
                                                    class="text-xs text-slate-600"
                                                    >{{
                                                        t(
                                                            'form.builder.fields.value_to',
                                                            'Waarde tot',
                                                        )
                                                    }}</label
                                                >
                                                <input
                                                    v-model="row.value_to"
                                                    type="text"
                                                    class="h-9 rounded-md border border-slate-300 bg-white px-3 text-sm"
                                                />
                                            </div>
                                            <div
                                                v-if="
                                                    row.varOrValue ===
                                                        'Parameter' &&
                                                    !rowIsNullCondition(row)
                                                "
                                                class="grid gap-1 md:col-span-1"
                                            >
                                                <label
                                                    class="text-xs text-slate-600"
                                                >
                                                    {{
                                                        rowIsRangeCondition(row)
                                                            ? t(
                                                                  'form.builder.fields.test_from',
                                                                  'Test vanaf',
                                                              )
                                                            : t(
                                                                  'form.builder.fields.test',
                                                                  'Test',
                                                              )
                                                    }}
                                                </label>
                                                <input
                                                    v-model="row.testValue"
                                                    type="text"
                                                    class="h-9 rounded-md border border-slate-300 bg-white px-3 text-sm"
                                                />
                                            </div>
                                            <div
                                                v-if="
                                                    row.varOrValue ===
                                                        'Parameter' &&
                                                    rowIsRangeCondition(row)
                                                "
                                                class="grid gap-1 md:col-span-1"
                                            >
                                                <label
                                                    class="text-xs text-slate-600"
                                                    >{{
                                                        t(
                                                            'form.builder.fields.test_to',
                                                            'Test tot',
                                                        )
                                                    }}</label
                                                >
                                                <input
                                                    v-model="row.testValueTo"
                                                    type="text"
                                                    class="h-9 rounded-md border border-slate-300 bg-white px-3 text-sm"
                                                />
                                            </div>
                                            <div
                                                class="flex items-end justify-end md:col-span-1"
                                            >
                                                <RwActionButton
                                                    :label="
                                                        t(
                                                            'form.builder.actions.subrow',
                                                            'Subrij',
                                                        )
                                                    "
                                                    icon="mdi mdi-chevron-right-circle"
                                                    tone="neutral"
                                                    :icon-only="true"
                                                    :disabled="
                                                        Boolean(row.subRow)
                                                    "
                                                    @click="
                                                        queryBuilderAddHavingSubRow(
                                                            index,
                                                        )
                                                    "
                                                />
                                                <RwActionButton
                                                    :label="
                                                        t(
                                                            'actions.delete',
                                                            'Verwijderen',
                                                        )
                                                    "
                                                    icon="mdi mdi-delete"
                                                    tone="delete"
                                                    :icon-only="true"
                                                    @click="
                                                        queryBuilderRemoveHavingRow(
                                                            index,
                                                        )
                                                    "
                                                />
                                            </div>

                                            <div
                                                v-if="
                                                    builderRowErrors(
                                                        'having_rows',
                                                        index,
                                                    ).length > 0
                                                "
                                                class="md:col-span-12"
                                            >
                                                <p
                                                    v-for="(
                                                        message, messageIndex
                                                    ) in builderRowErrors(
                                                        'having_rows',
                                                        index,
                                                    )"
                                                    :key="`having-error-${index}-${messageIndex}`"
                                                    class="text-[11px] text-red-600"
                                                >
                                                    {{ message }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="grid gap-2 rounded-md border border-slate-200 p-3"
                            >
                                <label class="text-xs text-slate-600">{{
                                    t(
                                        'form.builder.generated_sql',
                                        'Gegenereerde SQL',
                                    )
                                }}</label>
                                <textarea
                                    v-model="form.query"
                                    rows="8"
                                    class="rounded-md border border-slate-300 bg-slate-50 px-3 py-2 font-mono text-xs"
                                    readonly
                                />
                                <label class="text-xs text-slate-600">{{
                                    t(
                                        'form.builder.test_sql',
                                        'Test SQL (met testwaarden)',
                                    )
                                }}</label>
                                <textarea
                                    v-model="form.test_query"
                                    rows="6"
                                    class="rounded-md border border-slate-300 bg-slate-50 px-3 py-2 font-mono text-xs"
                                    readonly
                                />
                            </div>
                        </div>
                    </section>

                    <QuerySqlInspectorCard
                        v-else-if="activeTab === 'query'"
                        v-model="form.query"
                        :error-message="form.errors.query"
                        :inspect-result="inspectResult"
                        :sql-structure="props.db_structure"
                        @inspect="inspectSql"
                        @import-bindings="importBindingsFromInspect"
                    />

                    <section v-if="activeTab === 'selecties'" class="space-y-3">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">
                                {{
                                    t(
                                        'form.sections.selections',
                                        'Selecties / variabelen',
                                    )
                                }}
                            </h2>
                        </div>
                        <div class="space-y-3">
                            <div
                                class="grid gap-2 rounded-md border border-slate-200 p-3"
                            >
                                <p class="text-xs font-semibold text-slate-700">
                                    {{
                                        t(
                                            'form.selections.detected_parameters',
                                            'Gedetecteerde SQL parameters',
                                        )
                                    }}
                                </p>
                                <p
                                    v-if="requiredBindingNames.length === 0"
                                    class="text-xs text-slate-500"
                                >
                                    {{
                                        t(
                                            'form.selections.no_parameters',
                                            'Geen parameters gedetecteerd in de huidige SQL.',
                                        )
                                    }}
                                </p>
                                <p v-else class="text-xs text-slate-600">
                                    {{ requiredBindingNames.join(', ') }}
                                </p>

                                <p
                                    v-if="
                                        missingBindingNamesInConfig.length > 0
                                    "
                                    class="text-xs text-amber-700"
                                >
                                    {{
                                        t(
                                            'form.selections.not_configured',
                                            'Nog niet geconfigureerd:',
                                        )
                                    }}
                                    {{ missingBindingNamesInConfig.join(', ') }}
                                </p>

                                <p
                                    v-if="extraBindingNamesInConfig.length > 0"
                                    class="text-xs text-slate-500"
                                >
                                    {{
                                        t(
                                            'form.selections.extra_configuration',
                                            'Extra configuratie (niet gebruikt in SQL):',
                                        )
                                    }}
                                    {{ extraBindingNamesInConfig.join(', ') }}
                                </p>
                            </div>

                            <div
                                id="binding_rows"
                                class="rounded-md border border-slate-200 p-0"
                            >
                                <QueryBindingRowsEditor
                                    v-model="form.binding_rows"
                                    :binding-type-options="bindingTypeOptions"
                                    :binding-source-options="
                                        bindingSourceOptions
                                    "
                                    :binding-source-loading="
                                        bindingSourceLoading
                                    "
                                />
                            </div>
                        </div>
                    </section>
                </div>
            </CardContent>
        </Card>

        <Dialog v-model:open="deleteQueryDialogOpen">
            <DialogContent
                :disable-outside-pointer-events="false"
                class="flex max-h-[calc(100vh-1.5rem)] flex-col gap-0 overflow-hidden p-0 sm:max-w-xl"
            >
                <div class="px-4 py-4 pr-12 sm:px-5">
                    <DialogTitle class="text-lg font-semibold text-slate-900">
                        {{ t('form.delete.title', 'Delete query') }}
                    </DialogTitle>
                    <DialogDescription class="mt-1 text-sm text-slate-500">
                        {{
                            t(
                                'form.delete.subtitle',
                                'This permanently deletes the query if no active references exist.',
                            )
                        }}
                    </DialogDescription>
                </div>

                <div class="border-t border-slate-200" />

                <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-5">
                    <div class="space-y-2 text-sm text-slate-700">
                        <p>
                            {{
                                t(
                                    'form.delete.hint',
                                    'We first check if the query is still used in menus, permissions, or screens.',
                                )
                            }}
                        </p>
                        <p class="font-medium text-slate-900">
                            {{ t('form.delete.label', 'Query:') }}
                            {{ String(form.description || '-').trim() || '-' }}
                            <span class="text-slate-500"
                                >(#{{ recordIdLabel }})</span
                            >
                        </p>
                    </div>
                </div>

                <div
                    class="flex justify-end gap-2 border-t border-slate-200 px-4 py-3 sm:px-5"
                >
                    <RwActionButton
                        :label="t('actions.delete', 'Delete')"
                        icon="mdi mdi-delete"
                        tone="delete"
                        :loading="deleteQueryForm.processing"
                        @click="submitDeleteQuery"
                    />
                </div>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="runDialogOpen">
            <DialogContent
                :disable-outside-pointer-events="false"
                class="flex max-h-[calc(100vh-1.5rem)] flex-col gap-0 overflow-hidden p-0 sm:max-w-xl"
            >
                <div class="px-4 py-4 pr-12 sm:px-5">
                    <DialogTitle class="text-lg font-semibold text-slate-900">
                        {{ t('form.run_dialog.title', 'Enter variables') }}
                    </DialogTitle>
                    <DialogDescription class="mt-1 text-sm text-slate-500">
                        {{
                            t(
                                'form.run_dialog.subtitle',
                                'Enter the missing query variables to go directly to the run view.',
                            )
                        }}
                    </DialogDescription>
                </div>

                <div class="border-t border-slate-200" />

                <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-5">
                    <RwFlashMessage
                        type="warning"
                        :message="runBindingWarning"
                    />

                    <div class="mt-3">
                        <QueryBindingFields
                            :rows="runDialogBindingsMeta"
                            :values="runBindingValues"
                            :has-error="queryBindingHasError"
                            :on-value-change="queryBindingOnValueChange"
                            :is-source-select-type="
                                queryBindingIsSourceSelectType
                            "
                            :is-range-type="queryBindingIsRangeType"
                            :input-type-for-binding="queryBindingInputType"
                            :source-options-for="queryBindingSourceOptionsFor"
                            :source-loading-for="queryBindingSourceLoadingFor"
                            :show-range-parameter-to="
                                queryBindingShowRangeParameterTo
                            "
                        />
                    </div>
                </div>

                <div
                    class="flex justify-end gap-2 border-t border-slate-200 px-4 py-3 sm:px-5"
                >
                    <RwActionButton
                        :label="t('actions.run', 'Run')"
                        icon="mdi mdi-play-circle-outline"
                        tone="save"
                        :loading="runDialogSubmitting"
                        @click="submitRunDialog"
                    />
                </div>
            </DialogContent>
        </Dialog>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import QueryBindingFields from '@/Components/Query/QueryBindingFields.vue';
import QueryBindingRowsEditor from '@/Components/Query/QueryBindingRowsEditor.vue';
import QuerySqlInspectorCard from '@/Components/Query/QuerySqlInspectorCard.vue';
import AdminFormBackButton from '@/Components/Admin/Form/AdminFormBackButton.vue';
import AdminFormSaveButton from '@/Components/Admin/Form/AdminFormSaveButton.vue';
import RwActionButton from '@/Components/RwActionButton.vue';
import RwAutoCompleteInput from '@/Components/RwAutoCompleteInput.vue';
import RwFlashMessage from '@/Components/RwFlashMessage.vue';
import RwHelpDialogButton from '@/Components/RwHelpDialogButton.vue';
import {
    buildBindingMeta,
    extractBindingNamesFromSql,
} from '@/composables/useQueryBindings';
import {
    bindingInputType as resolveBindingInputType,
    isRangeBindingType as isQueryBindingRangeType,
    isSourceSelectBindingType as isQueryBindingSourceSelectType,
    useQueryBindingInputState,
} from '@/composables/useQueryBindingInputState';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { useCmsFormValidation } from '@/composables/useCmsFormValidation';
import { useQueryRunDialogFlow } from '@/composables/useQueryRunDialogFlow';
import clientRules from '@/ValidationRules/Rules';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
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
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

const props = defineProps({
    query: { type: Object, required: true },
    store_url: { type: String, default: '' },
    db_structure: { type: Object, default: () => ({}) },
    table_options: { type: Array, default: () => [] },
    template_help_html: { type: String, default: '' },
});

const page = usePage();
const { t: commonT } = useAdminTranslations('admin_common_ui');

const uiMessages = computed(() => {
    const messages = page.props?.app?.translations?.query_builder_ui ?? {};

    return messages && typeof messages === 'object' ? messages : {};
});

function getNestedTranslation(source, key) {
    if (!source || typeof source !== 'object') {
        return null;
    }

    return String(key || '')
        .split('.')
        .filter((segment) => segment !== '')
        .reduce((carry, segment) => {
            if (!carry || typeof carry !== 'object') {
                return null;
            }

            if (!Object.prototype.hasOwnProperty.call(carry, segment)) {
                return null;
            }

            return carry[segment];
        }, source);
}

function t(key, fallback = '') {
    const translated = getNestedTranslation(uiMessages.value, key);

    if (typeof translated === 'string' && translated.trim() !== '') {
        return translated;
    }

    return fallback || key;
}
const bindingSourceOptions = ref([]);
const bindingSourceLoading = ref(false);
const runDialogState = useQueryBindingInputState(async (sourceTableId) => {
    const response = await window.axios.get(
        route('admin.run.queries.binding-source-options'),
        {
            params: {
                source_table_id: Number(sourceTableId),
                limit: 100,
            },
        },
    );

    return Array.isArray(response?.data?.options) ? response.data.options : [];
});
const runBindingWarning = runDialogState.warning;
const runBindingValues = runDialogState.values;

const form = useForm({
    description: String(props.query.description || ''),
    slug: String(props.query.slug || ''),
    memo: String(props.query.memo || ''),
    query_mode: String(props.query.query_mode || 'builder'),
    output_mode: String(props.query.output_mode || 'table'),
    report_data_source: String(props.query.report_data_source || 'query'),
    report_output_format: String(
        props.query.report_output_format || 'same_format',
    ),
    report_template_upload: null,
    table_name: String(props.query.table_name || ''),
    all_fields: Boolean(props.query.all_fields ?? false),
    distinct_select: Boolean(props.query.distinct_select ?? false),
    query: String(props.query.query || ''),
    test_query: String(props.query.test_query || ''),
    selected_fields: Array.isArray(props.query.selected_fields)
        ? props.query.selected_fields
        : [],
    join_rows: Array.isArray(props.query.join_rows)
        ? props.query.join_rows
        : [],
    where_rows: Array.isArray(props.query.where_rows)
        ? props.query.where_rows
        : [],
    group_by: Boolean(props.query.group_by ?? false),
    group_rows: Array.isArray(props.query.group_rows)
        ? props.query.group_rows
        : [],
    aggregate_rows: Array.isArray(props.query.aggregate_rows)
        ? props.query.aggregate_rows
        : [],
    having_rows: Array.isArray(props.query.having_rows)
        ? props.query.having_rows
        : [],
    binding_rows: Array.isArray(props.query.binding_rows)
        ? props.query.binding_rows
        : [],
    chart_config:
        props.query.chart_config && typeof props.query.chart_config === 'object'
            ? props.query.chart_config
            : null,
    is_active: Boolean(props.query.is_active ?? true),
});
const deleteQueryForm = useForm({});
const deleteQueryDialogOpen = ref(false);

const outputModeOptions = [
    { value: 'table', label: t('form.output_modes.table', 'Tabel') },
    { value: 'report', label: t('form.output_modes.report', 'Rapport') },
    { value: 'excel', label: 'Excel' },
    { value: 'chart', label: t('form.output_modes.chart', 'Grafiek') },
];

const modeOptions = [
    { value: 'builder', label: t('form.query_modes.builder', 'Builder') },
    { value: 'sql', label: t('form.query_modes.sql', 'SQL editor') },
];

const bindingTypeOptions = [
    { value: 'text', label: t('form.binding_types.text', 'Tekst') },
    { value: 'number', label: t('form.binding_types.number', 'Nummer') },
    {
        value: 'number_range',
        label: t('form.binding_types.number_range', 'Nummer range'),
    },
    { value: 'date', label: t('form.binding_types.date', 'Datum') },
    {
        value: 'date_range',
        label: t('form.binding_types.date_range', 'Datum range'),
    },
    {
        value: 'source_select',
        label: t('form.binding_types.source_select', 'Bron selectie'),
    },
];

const whereConditionOptions = [
    '=',
    '!=',
    '>',
    '<',
    '>=',
    '<=',
    'LIKE',
    'NOT LIKE',
    'IN',
    'NOT IN',
    'BETWEEN',
    'NOT BETWEEN',
    'IS',
    'IS NOT',
    'IS NULL',
    'IS NOT NULL',
];

const andOrOptions = ['AND', 'OR'];

const whereValueTypeOptions = [
    { value: 'Waarde', label: t('form.value_types.value', 'Waarde') },
    {
        value: 'Vaste waarde',
        label: t('form.value_types.fixed_value', 'Vaste waarde'),
    },
    { value: 'Parameter', label: t('form.value_types.parameter', 'Parameter') },
    {
        value: 'Systeemvariabele',
        label: t('form.value_types.system_variable', 'Systeemvariabele'),
    },
    {
        value: 'Json Array',
        label: t('form.value_types.json_array', 'Json Array'),
    },
];

const aggregateFunctionOptions = [
    'COUNT',
    'SUM',
    'MIN',
    'MAX',
    'AVG',
    'GROUP_CONCAT',
    'CONCAT',
    'FORMULA',
];

const joinTypeOptions = [
    { value: 'LEFT', label: 'LEFT JOIN' },
    { value: 'RIGHT', label: 'RIGHT JOIN' },
    { value: 'INNER', label: 'INNER JOIN' },
];

const systemVariableOptions = [
    { value: 'CURRENTSCHOOLYEAR', label: 'CURRENTSCHOOLYEAR' },
    { value: 'USERSCHOOLIDS', label: 'USERSCHOOLIDS' },
    { value: 'USERWISASCHOOLIDS', label: 'USERWISASCHOOLIDS' },
    { value: 'USERWISAVIRTSCHOOLIDS', label: 'USERWISAVIRTSCHOOLIDS' },
];

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

const isNew = computed(() => Number(props.query.id || 0) <= 0);
const backHref = computed(() => route('admin.queries.builder.index'));
const recordIdLabel = computed(() => (isNew.value ? '-' : props.query.id));
const updatedAtLabel = computed(() => formatRecordDate(props.query.updated_at));
const createdAtLabel = computed(() => formatRecordDate(props.query.created_at));
const canDeleteQuery = computed(() => {
    if (isNew.value) {
        return false;
    }

    const acl = page.props?.acl ?? {};

    if (acl?.is_super_admin ?? false) {
        return true;
    }

    const allowedRoutes = Array.isArray(acl?.allowed_routes)
        ? acl.allowed_routes
        : [];

    return allowedRoutes.includes('admin.queries.builder.delete');
});
const isTableOutputMode = computed(() => String(form.output_mode) === 'table');
const isChartOutputMode = computed(() => String(form.output_mode) === 'chart');
const isBuilderMode = computed(
    () => String(form.query_mode || 'sql') === 'builder',
);
const isReportOutputMode = computed(
    () => String(form.output_mode) === 'report',
);
const activeTab = ref('omschrijving');

const tabOptions = [
    {
        value: 'omschrijving',
        label: t('form.tabs.description', 'Omschrijving'),
    },
    { value: 'query', label: t('form.tabs.query', 'Query') },
    { value: 'selecties', label: t('form.tabs.selections', 'Selecties') },
    { value: 'uitvoer', label: t('form.tabs.output', 'Uitvoer') },
];

const queryValidationFields = {
    description: {
        label: t('form.fields.description', 'Description'),
        tab: 'omschrijving',
        elementId: 'description',
        required: true,
        value: () => form.description,
        rules: [
            (value) =>
                clientRules.required(
                    value,
                    t('validation.required', 'This field is required.'),
                ),
            (value) =>
                clientRules.max(
                    255,
                    value,
                    t(
                        'validation.description_max',
                        'Description is too long (maximum 255 characters).',
                    ),
                ),
        ],
    },
    slug: {
        label: t('form.fields.slug', 'Slug'),
        tab: 'omschrijving',
        elementId: 'slug',
        required: true,
        value: () => form.slug,
        rules: [
            (value) =>
                clientRules.required(
                    value,
                    t('validation.required', 'This field is required.'),
                ),
            (value) =>
                clientRules.slug(
                    value,
                    t(
                        'validation.slug',
                        'Use lowercase letters, numbers and hyphens.',
                    ),
                ),
            (value) =>
                clientRules.max(
                    160,
                    value,
                    t(
                        'validation.slug_max',
                        'Slug is too long (maximum 160 characters).',
                    ),
                ),
        ],
    },
    table_name: {
        label: t('form.builder.table_from', 'Table (FROM)'),
        tab: 'query',
        elementId: 'table_name',
        required: () => isBuilderMode.value,
        value: () => form.table_name,
        rules: [
            (value) => {
                if (!isBuilderMode.value) {
                    return true;
                }

                return clientRules.required(
                    value,
                    t(
                        'validation.table_name_required',
                        'Choose a valid base table for the builder query.',
                    ),
                );
            },
        ],
    },
};
const queryServerFields = {
    report_data_source: {
        label: t('form.fields.report_data_source', 'Report data'),
        tab: 'uitvoer',
        elementId: 'report_data_source',
    },
    report_output_format: {
        label: t('form.fields.report_output_format', 'Report output'),
        tab: 'uitvoer',
        elementId: 'report_output_format',
    },
    report_template_upload: {
        label: t('form.fields.report_template', 'Report template'),
        tab: 'uitvoer',
        elementId: 'report_template_upload',
    },
    selected_fields: {
        label: t('form.builder.select_fields', 'Select fields'),
        tab: 'query',
        elementId: 'selected_fields',
    },
    'selected_fields.*': {
        label: t('form.builder.select_fields', 'Select fields'),
        tab: 'query',
        elementId: 'selected_fields',
    },
    where_rows: {
        label: t('form.builder.where_filters', 'WHERE filters'),
        tab: 'query',
        elementId: 'where_rows',
    },
    'where_rows.*': {
        label: t('form.builder.where_filters', 'WHERE filters'),
        tab: 'query',
        elementId: 'where_rows',
    },
    group_rows: {
        label: t('form.builder.group_fields', 'Group fields'),
        tab: 'query',
        elementId: 'group_rows',
    },
    'group_rows.*': {
        label: t('form.builder.group_fields', 'Group fields'),
        tab: 'query',
        elementId: 'group_rows',
    },
    aggregate_rows: {
        label: t('form.builder.aggregates', 'Aggregates'),
        tab: 'query',
        elementId: 'aggregate_rows',
    },
    'aggregate_rows.*': {
        label: t('form.builder.aggregates', 'Aggregates'),
        tab: 'query',
        elementId: 'aggregate_rows',
    },
    having_rows: {
        label: 'HAVING',
        tab: 'query',
        elementId: 'having_rows',
    },
    'having_rows.*': {
        label: 'HAVING',
        tab: 'query',
        elementId: 'having_rows',
    },
    binding_rows: {
        label: t('form.sections.selections', 'Selections / variables'),
        tab: 'selecties',
        elementId: 'binding_rows',
    },
    'binding_rows.*': {
        label: t('form.sections.selections', 'Selections / variables'),
        tab: 'selecties',
        elementId: 'binding_rows',
    },
    query: {
        label: t('form.tabs.query', 'Query'),
        tab: 'query',
        elementId: 'query',
    },
};
const {
    FieldValidationMessage,
    formValidation,
    message: validationMessage,
    requiredClass,
    validationFlash,
    warning: validationWarning,
    touchAndClear,
} = useCmsFormValidation(form, {
    fields: queryValidationFields,
    serverFields: queryServerFields,
    messages: {
        blocked: commonT(
            'validation.client_error_flash',
            'Saving is blocked. Check the validation messages below.',
        ),
        client: commonT(
            'validation.client_error_flash',
            'Saving is blocked. Check the validation messages below.',
        ),
        server: commonT(
            'validation.server_error_flash',
            'Saving failed. Check the validation messages below.',
        ),
    },
    activateTab: (tab) => {
        activeTab.value = tab;
    },
});
const { validateBeforeSubmit, scrollToIssue } = formValidation;

const reportDataSourceOptions = [
    {
        value: 'query',
        label: t('form.report.data_sources.query', 'Query data'),
    },
    {
        value: 'external',
        label: t(
            'form.report.data_sources.external',
            'Externe data (workflow)',
        ),
    },
];

const reportOutputFormatOptions = [
    {
        value: 'same_format',
        label: t(
            'form.report.output_formats.same_format',
            'Zelfde formaat als template',
        ),
    },
    { value: 'pdf', label: 'PDF' },
    {
        value: 'csv',
        label: t('form.report.output_formats.csv', 'CSV (tijdelijk)'),
    },
];

const chartTypeOptions = [
    { value: 'bar', label: t('chart_view.types.bar', 'Staaf') },
    { value: 'line', label: t('chart_view.types.line', 'Lijn') },
    { value: 'bar3d', label: t('chart_view.types.bar3d', 'Staaf 3D') },
    { value: 'line3d', label: t('chart_view.types.line3d', 'Lijn 3D') },
    {
        value: 'bar3d_webgl',
        label: t('chart_view.types.bar3d_webgl', 'Staaf 3D (WebGL)'),
    },
    {
        value: 'line3d_webgl',
        label: t('chart_view.types.line3d_webgl', 'Lijn 3D (WebGL)'),
    },
    { value: 'pie', label: t('chart_view.types.pie', 'Taart') },
    { value: 'doughnut', label: t('chart_view.types.doughnut', 'Donut') },
];

const chartAggregateOptions = [
    { value: 'count', label: t('form.chart.aggregates.count', 'Aantal') },
    { value: 'sum', label: t('form.chart.aggregates.sum', 'Som') },
    { value: 'avg', label: t('form.chart.aggregates.avg', 'Gemiddelde') },
    { value: 'min', label: t('form.chart.aggregates.min', 'Minimum') },
    { value: 'max', label: t('form.chart.aggregates.max', 'Maximum') },
];

const chartSortDirectionOptions = [
    { value: 'desc', label: t('form.chart.sort.desc', 'Hoog naar laag') },
    { value: 'asc', label: t('form.chart.sort.asc', 'Laag naar hoog') },
];

const chartOrientationOptions = [
    {
        value: 'vertical',
        label: t('form.chart.orientation.vertical', 'Verticaal'),
    },
    {
        value: 'horizontal',
        label: t('form.chart.orientation.horizontal', 'Horizontaal'),
    },
];

function defaultChartBuilderState() {
    return {
        title: '',
        subtitle: '',
        chartType: 'bar',
        aggregate: 'count',
        orientation: 'vertical',
        xField: '',
        metricField: '',
        seriesField: '',
        sortDirection: 'desc',
        limit: 25,
        showLegend: true,
        stacked: false,
        showSourceTableButton: true,
        allowViewerChartTypeChange: true,
        showPdfPrintButton: false,
    };
}

function toIntegerInRange(value, fallback, min, max) {
    const numeric = Number(value);

    if (!Number.isFinite(numeric)) {
        return fallback;
    }

    return Math.max(min, Math.min(max, Math.trunc(numeric)));
}

function createChartBuilderStateFromConfig(config) {
    const state = defaultChartBuilderState();

    if (!config || typeof config !== 'object') {
        return state;
    }

    const builder =
        config.builder && typeof config.builder === 'object'
            ? config.builder
            : {};
    const dataset =
        builder.dataset && typeof builder.dataset === 'object'
            ? builder.dataset
            : {};
    const chart =
        builder.chart && typeof builder.chart === 'object' ? builder.chart : {};
    const presentation =
        builder.presentation && typeof builder.presentation === 'object'
            ? builder.presentation
            : {};

    state.title = String(presentation.title || '').trim();
    state.subtitle = String(presentation.subtitle || '').trim();
    state.chartType = String(chart.type || state.chartType);
    state.aggregate = String(dataset.aggregate || state.aggregate);
    state.orientation = String(chart.orientation || state.orientation);
    state.xField = String(dataset.x_field || '').trim();
    state.metricField = String(dataset.metric_field || '').trim();
    state.seriesField = String(dataset.series_field || '').trim();
    state.sortDirection = String(dataset.sort_direction || state.sortDirection);
    state.limit = toIntegerInRange(dataset.limit, state.limit, 1, 500);
    state.showLegend = chart.show_legend !== false;
    state.stacked = Boolean(chart.stacked);
    state.showSourceTableButton =
        presentation.show_source_table_button !== false;
    state.allowViewerChartTypeChange =
        presentation.allow_chart_type_change !== false;
    state.showPdfPrintButton = Boolean(presentation.show_pdf_print_button);

    if (state.aggregate === 'count') {
        state.metricField = '';
    }

    return state;
}

function buildChartConfigFromState(state) {
    const safeLimit = toIntegerInRange(state.limit, 25, 1, 500);

    return {
        builder: {
            dataset: {
                x_field: String(state.xField || '').trim(),
                metric_field:
                    String(state.aggregate || 'count') === 'count'
                        ? ''
                        : String(state.metricField || '').trim(),
                aggregate: String(state.aggregate || 'count').trim(),
                series_field: String(state.seriesField || '').trim(),
                sort_direction: String(state.sortDirection || 'desc').trim(),
                limit: safeLimit,
            },
            chart: {
                type: String(state.chartType || 'bar').trim(),
                orientation: String(state.orientation || 'vertical').trim(),
                stacked: Boolean(state.stacked),
                show_legend: Boolean(state.showLegend),
            },
            presentation: {
                title: String(state.title || '').trim(),
                subtitle: String(state.subtitle || '').trim(),
                show_source_table_button: Boolean(state.showSourceTableButton),
                allow_chart_type_change: Boolean(
                    state.allowViewerChartTypeChange,
                ),
                show_pdf_print_button: Boolean(state.showPdfPrintButton),
            },
        },
    };
}

const inlineChartBuilder = ref(
    createChartBuilderStateFromConfig(form.chart_config),
);

const dbStructure = computed(() => {
    return props.db_structure && typeof props.db_structure === 'object'
        ? props.db_structure
        : {};
});

const tableOptions = computed(() => {
    if (Array.isArray(props.table_options) && props.table_options.length > 0) {
        return props.table_options;
    }

    return Object.keys(dbStructure.value)
        .sort((a, b) => a.localeCompare(b))
        .map((table) => ({
            value: table,
            title: table,
        }));
});

const selectFieldItems = computed(() => {
    const rows = Array.isArray(form.join_rows) ? form.join_rows : [];
    const joinTables = rows
        .map((row) => String(row?.relTable || '').trim())
        .filter((table) => table !== '');
    const candidateTables = Array.from(
        new Set(
            [String(form.table_name || '').trim(), ...joinTables].filter(
                Boolean,
            ),
        ),
    );
    const output = [];

    candidateTables.forEach((table) => {
        const fields = Array.isArray(dbStructure.value?.[table]?.fields)
            ? dbStructure.value[table].fields
            : [];

        fields.forEach((column) => {
            output.push({
                value: `${table}.${column}`,
                title: `${table}.${column}`,
            });
        });
    });

    return output;
});

const havingFieldItems = computed(() => {
    const selected = Array.isArray(selectFieldItems.value)
        ? [...selectFieldItems.value]
        : [];
    const aggregates = Array.isArray(form.aggregate_rows)
        ? form.aggregate_rows
        : [];

    aggregates.forEach((row) => {
        const alias = String(row?.alias || '').trim();

        if (alias !== '') {
            selected.push({
                value: alias,
                title: alias,
            });
        }
    });

    const map = new Map();

    selected.forEach((item) => {
        if (!map.has(item.value)) {
            map.set(item.value, item);
        }
    });

    return Array.from(map.values());
});

const requiredBindingNames = computed(() => {
    return extractBindingNamesFromSql(form.query);
});

const configuredBindingNames = computed(() => {
    if (!Array.isArray(form.binding_rows)) {
        return [];
    }

    const names = [];

    form.binding_rows.forEach((row) => {
        const parameter = String(row?.parameter || '').trim();
        const parameterTo = String(row?.parameter_to || '').trim();

        if (parameter !== '') {
            names.push(parameter);
        }

        if (parameterTo !== '') {
            names.push(parameterTo);
        }
    });

    return Array.from(new Set(names)).sort((a, b) => a.localeCompare(b));
});

const missingBindingNamesInConfig = computed(() => {
    return requiredBindingNames.value.filter(
        (name) => !configuredBindingNames.value.includes(name),
    );
});

const extraBindingNamesInConfig = computed(() => {
    return configuredBindingNames.value.filter(
        (name) => !requiredBindingNames.value.includes(name),
    );
});

function buildRunBindingsMeta(bindingNames) {
    return buildBindingMeta(form.binding_rows, bindingNames);
}

function openDeleteQueryDialog() {
    if (!canDeleteQuery.value) {
        return;
    }

    deleteQueryDialogOpen.value = true;
}

function closeDeleteQueryDialog() {
    if (deleteQueryForm.processing) {
        return;
    }

    deleteQueryDialogOpen.value = false;
}

function submitDeleteQuery() {
    if (!canDeleteQuery.value) {
        return;
    }

    deleteQueryForm.post(
        route('admin.queries.builder.delete', {
            query: props.query.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                closeDeleteQueryDialog();
            },
        },
    );
}

function openRunPageWithBindings(bindings = {}, onFinish = null) {
    const payload = {
        query: props.query.id,
    };

    Object.entries(bindings).forEach(([key, value]) => {
        const parameter = String(key || '').trim();

        if (parameter === '') {
            return;
        }

        if (value === null || value === undefined) {
            return;
        }

        const normalizedValue = String(value).trim();

        if (normalizedValue === '') {
            return;
        }

        payload[parameter] = normalizedValue;
    });

    router.visit(route('admin.run.queries.show', payload), {
        onFinish: () => {
            if (typeof onFinish === 'function') {
                onFinish();
            }
        },
    });
}

const {
    inspectResult,
    inspectSql,
    runDialogOpen,
    runDialogPreparing,
    runDialogSubmitting,
    runDialogBindingsMeta,
    openRunDialogOrNavigate,
    submitRunDialog,
} = useQueryRunDialogFlow({
    isNew,
    getQuerySql: () => String(form.query || ''),
    requiredBindingNames,
    buildRunBindingsMeta,
    runDialogState,
    openRunPageWithBindings,
});

function queryBindingInputType(type) {
    return resolveBindingInputType(type);
}

function queryBindingIsSourceSelectType(type) {
    return isQueryBindingSourceSelectType(type);
}

function queryBindingIsRangeType(type) {
    return isQueryBindingRangeType(type);
}

function queryBindingSourceOptionsFor(row) {
    return runDialogState.sourceOptionsFor(row, 0);
}

function queryBindingSourceLoadingFor(row) {
    return runDialogState.sourceLoadingFor(row, 0);
}

function queryBindingHasError(parameter) {
    return runDialogState.hasError(parameter);
}

function queryBindingOnValueChange(parameter, value) {
    runDialogState.setValue(parameter, value);
}

function queryBindingShowRangeParameterTo(row) {
    return String(row?.parameter_to || '').trim() !== '';
}

async function submitForm() {
    normalizeBasicFields('submit');

    if (!(await validateBeforeSubmit())) {
        return;
    }

    const targetRoute =
        props.store_url ||
        (isNew.value
            ? route('admin.queries.builder.store-new')
            : route('admin.queries.builder.store', {
                  query: props.query.id,
              }));

    const hasTemplateUpload = form.report_template_upload instanceof File;

    form.transform((data) => ({
        ...data,
        report_data_source:
            String(data.output_mode) === 'report'
                ? data.report_data_source || 'query'
                : null,
        report_output_format:
            String(data.output_mode) === 'report'
                ? data.report_output_format || 'same_format'
                : null,
        chart_config:
            String(data.output_mode) === 'chart'
                ? buildChartConfigFromState(inlineChartBuilder.value)
                : null,
    })).post(targetRoute, {
        preserveScroll: true,
        forceFormData: hasTemplateUpload,
    });
}

function formError(path) {
    return String(form.errors?.[path] || '').trim();
}

function touchAndNormalize(field) {
    normalizeBasicFields(field);
    touchAndClear(field);
}

function normalizeBasicFields(source) {
    if (source === 'description' || source === 'submit') {
        if (String(form.slug || '').trim() === '') {
            form.slug = slugify(form.description);
        }
    }

    if (source === 'slug' || source === 'submit') {
        form.slug = slugify(form.slug);
    }
}

function slugify(value) {
    return String(value || '')
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

function formatRecordDate(value) {
    if (!value) {
        return '-';
    }

    const isoDateMatch = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/);

    if (isoDateMatch) {
        return `${isoDateMatch[3]}/${isoDateMatch[2]}/${isoDateMatch[1]}`;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '-';
    }

    return [
        String(date.getDate()).padStart(2, '0'),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getFullYear()),
    ].join('/');
}

function builderRowErrors(section, index) {
    const prefix = `${section}.${index}.`;

    return Object.entries(form.errors || {})
        .filter(([key, value]) => {
            return key.startsWith(prefix) && String(value || '').trim() !== '';
        })
        .map(([, value]) => String(value || '').trim())
        .filter(
            (value, idx, arr) => value !== '' && arr.indexOf(value) === idx,
        );
}

function onReportTemplateChange(event) {
    const [file] = Array.from(event?.target?.files || []);
    form.report_template_upload = file || null;
}

function openReportTemplateDownload() {
    if (isNew.value || !props.query.report_template_filename) {
        return;
    }

    window.location.href = route('admin.queries.builder.template', {
        query: props.query.id,
    });
}

function normalizeBindingSortOrder() {
    if (!Array.isArray(form.binding_rows)) {
        form.binding_rows = [];
    }

    form.binding_rows = form.binding_rows.map((row, index) => ({
        ...row,
        sort_order: index + 1,
    }));
}

function queryBuilderEnsureRows() {
    if (!Array.isArray(form.join_rows)) {
        form.join_rows = [];
    } else {
        form.join_rows = form.join_rows.map((row, index) => ({
            ...row,
            id: Number(row?.id ?? index),
            subRow: Boolean(row?.subRow),
            parentId:
                row?.parentId !== null && row?.parentId !== undefined
                    ? Number(row.parentId)
                    : null,
            paddingLeft: Number(row?.paddingLeft ?? (row?.subRow ? 20 : 0)),
            joinType: ['LEFT', 'RIGHT', 'INNER'].includes(
                String(row?.joinType || '').toUpperCase(),
            )
                ? String(row?.joinType || '').toUpperCase()
                : 'LEFT',
            originTable: String(
                row?.originTable || form.table_name || '',
            ).trim(),
            relTable: String(row?.relTable || '').trim(),
            relFieldT1: String(row?.relFieldT1 || '').trim(),
            relFieldT2: String(row?.relFieldT2 || '').trim(),
        }));
    }

    if (!Array.isArray(form.where_rows)) {
        form.where_rows = [];
    } else {
        form.where_rows = form.where_rows.map((row, index) => ({
            ...row,
            id: Number(row?.id ?? index),
            subRow: Boolean(row?.subRow),
            parentId:
                row?.parentId !== null && row?.parentId !== undefined
                    ? Number(row.parentId)
                    : null,
            paddingLeft: Number(row?.paddingLeft ?? (row?.subRow ? 20 : 0)),
            whereFieldAndOr: String(row?.whereFieldAndOr || 'AND')
                .trim()
                .toUpperCase(),
            whereFieldCondition: normalizeCondition(row?.whereFieldCondition),
            varOrValue: String(row?.varOrValue || 'Vaste waarde'),
            whereField: String(row?.whereField || ''),
            value: row?.value ?? '',
            value_to: row?.value_to ?? '',
            variabele: String(row?.variabele || ''),
            variabele_to: String(row?.variabele_to || ''),
            testValue: String(row?.testValue || ''),
            testValueTo: String(row?.testValueTo || ''),
        }));
    }

    if (!Array.isArray(form.group_rows)) {
        form.group_rows = [];
    }

    if (!Array.isArray(form.aggregate_rows)) {
        form.aggregate_rows = [];
    } else {
        form.aggregate_rows = form.aggregate_rows.map((row) => ({
            func: String(row?.func || 'COUNT')
                .trim()
                .toUpperCase(),
            field: String(row?.field || '').trim(),
            fields: Array.isArray(row?.fields)
                ? row.fields
                      .map((field) => String(field || '').trim())
                      .filter((field) => field !== '')
                : [],
            formula: String(row?.formula || '').trim(),
            alias: String(row?.alias || '').trim(),
            distinct: Boolean(row?.distinct),
            separator: String(row?.separator || ','),
        }));
    }

    if (!Array.isArray(form.having_rows)) {
        form.having_rows = [];
    } else {
        form.having_rows = form.having_rows.map((row, index) => ({
            ...row,
            id: Number(row?.id ?? index),
            subRow: Boolean(row?.subRow),
            parentId:
                row?.parentId !== null && row?.parentId !== undefined
                    ? Number(row.parentId)
                    : null,
            paddingLeft: Number(row?.paddingLeft ?? (row?.subRow ? 20 : 0)),
            whereFieldAndOr: String(row?.whereFieldAndOr || 'AND')
                .trim()
                .toUpperCase(),
            whereField: String(row?.whereField || '').trim(),
            whereFieldCondition: normalizeCondition(row?.whereFieldCondition),
            varOrValue: String(row?.varOrValue || 'Waarde'),
            value: row?.value ?? '',
            value_to: row?.value_to ?? '',
            variabele: String(row?.variabele || '').trim(),
            variabele_to: String(row?.variabele_to || '').trim(),
            testValue: String(row?.testValue || '').trim(),
            testValueTo: String(row?.testValueTo || '').trim(),
        }));
    }
}

function queryBuilderAddJoinRow() {
    queryBuilderEnsureRows();

    const nextId =
        form.join_rows.length === 0
            ? 0
            : Math.max(...form.join_rows.map((row) => Number(row?.id || 0))) +
              1;

    form.join_rows.push({
        id: nextId,
        subRow: false,
        parentId: null,
        paddingLeft: 0,
        joinType: 'LEFT',
        originTable: String(form.table_name || ''),
        relTable: '',
        relFieldT1: '',
        relFieldT2: '',
    });
}

function queryBuilderAddJoinSubRow(index) {
    queryBuilderEnsureRows();

    const parent = form.join_rows[index];

    if (!parent) {
        return;
    }

    const parentRelTable = String(parent?.relTable || '').trim();

    if (parentRelTable === '') {
        return;
    }

    const nextId =
        form.join_rows.length === 0
            ? 0
            : Math.max(...form.join_rows.map((row) => Number(row?.id || 0))) +
              1;

    form.join_rows.splice(index + 1, 0, {
        id: nextId,
        subRow: true,
        parentId: Number(parent?.id || 0),
        paddingLeft: Number(parent?.paddingLeft || 0) + 20,
        joinType: 'LEFT',
        originTable: parentRelTable,
        relTable: '',
        relFieldT1: '',
        relFieldT2: '',
    });
}

function queryBuilderRemoveJoinRow(index) {
    queryBuilderEnsureRows();
    const row = form.join_rows[index];

    if (!row) {
        return;
    }

    const rowId = Number(row?.id || -1);
    form.join_rows = form.join_rows.filter((item, itemIndex) => {
        if (itemIndex === index) {
            return false;
        }

        if (Number(item?.parentId || -2) === rowId) {
            return false;
        }

        return true;
    });
}

function queryBuilderAddWhereRow() {
    queryBuilderEnsureRows();

    const nextId =
        form.where_rows.length === 0
            ? 0
            : Math.max(...form.where_rows.map((row) => Number(row?.id || 0))) +
              1;

    form.where_rows.push({
        id: nextId,
        subRow: false,
        parentId: null,
        paddingLeft: 0,
        whereFieldAndOr: form.where_rows.length === 0 ? 'AND' : 'AND',
        whereField: '',
        whereFieldCondition: '=',
        varOrValue: 'Vaste waarde',
        value: '',
        value_to: '',
        variabele: '',
        variabele_to: '',
        testValue: '',
        testValueTo: '',
    });
}

function queryBuilderAddWhereSubRow(index) {
    queryBuilderEnsureRows();

    const parent = form.where_rows[index];

    if (!parent || parent.subRow) {
        return;
    }

    const nextId =
        form.where_rows.length === 0
            ? 0
            : Math.max(...form.where_rows.map((row) => Number(row?.id || 0))) +
              1;

    const subRow = {
        id: nextId,
        subRow: true,
        parentId: Number(parent?.id || 0),
        paddingLeft: Number(parent?.paddingLeft || 0) + 20,
        whereFieldAndOr: 'AND',
        whereField: '',
        whereFieldCondition: '=',
        varOrValue: 'Vaste waarde',
        value: '',
        value_to: '',
        variabele: '',
        variabele_to: '',
        testValue: '',
        testValueTo: '',
    };

    form.where_rows.splice(index + 1, 0, subRow);
}

function aggregateRowFunction(row) {
    return String(row?.func || '')
        .trim()
        .toUpperCase();
}

function aggregateRowIsFormula(row) {
    return aggregateRowFunction(row) === 'FORMULA';
}

function aggregateRowIsConcat(row) {
    return aggregateRowFunction(row) === 'CONCAT';
}

function aggregateRowSupportsDistinct(row) {
    return !['FORMULA', 'CONCAT'].includes(aggregateRowFunction(row));
}

function aggregateRowSupportsSeparator(row) {
    return ['GROUP_CONCAT', 'CONCAT'].includes(aggregateRowFunction(row));
}

function queryBuilderRemoveWhereRow(index) {
    queryBuilderEnsureRows();
    const row = form.where_rows[index];

    if (!row) {
        return;
    }

    const rowId = Number(row?.id || -1);
    form.where_rows = form.where_rows.filter((item, itemIndex) => {
        if (itemIndex === index) {
            return false;
        }

        if (Number(item?.parentId || -2) === rowId) {
            return false;
        }

        return true;
    });
}

function queryBuilderAddAggregateRow() {
    queryBuilderEnsureRows();
    form.aggregate_rows.push({
        func: 'COUNT',
        field: '',
        fields: [],
        formula: '',
        alias: '',
        distinct: false,
        separator: ',',
    });
}

function queryBuilderRemoveAggregateRow(index) {
    queryBuilderEnsureRows();
    form.aggregate_rows.splice(index, 1);
}

function queryBuilderAddHavingRow() {
    queryBuilderEnsureRows();

    const nextId =
        form.having_rows.length === 0
            ? 0
            : Math.max(...form.having_rows.map((row) => Number(row?.id || 0))) +
              1;

    form.having_rows.push({
        id: nextId,
        subRow: false,
        parentId: null,
        paddingLeft: 0,
        whereFieldAndOr: form.having_rows.length === 0 ? 'AND' : 'AND',
        whereField: '',
        whereFieldCondition: '=',
        varOrValue: 'Waarde',
        value: '',
        value_to: '',
        variabele: '',
        variabele_to: '',
        testValue: '',
        testValueTo: '',
    });
}

function queryBuilderAddHavingSubRow(index) {
    queryBuilderEnsureRows();

    const parent = form.having_rows[index];

    if (!parent || parent.subRow) {
        return;
    }

    const nextId =
        form.having_rows.length === 0
            ? 0
            : Math.max(...form.having_rows.map((row) => Number(row?.id || 0))) +
              1;

    form.having_rows.splice(index + 1, 0, {
        id: nextId,
        subRow: true,
        parentId: Number(parent?.id || 0),
        paddingLeft: Number(parent?.paddingLeft || 0) + 20,
        whereFieldAndOr: 'AND',
        whereField: '',
        whereFieldCondition: '=',
        varOrValue: 'Waarde',
        value: '',
        value_to: '',
        variabele: '',
        variabele_to: '',
        testValue: '',
        testValueTo: '',
    });
}

function queryBuilderRemoveHavingRow(index) {
    queryBuilderEnsureRows();
    const row = form.having_rows[index];

    if (!row) {
        return;
    }

    const rowId = Number(row?.id || -1);
    form.having_rows = form.having_rows.filter((item, itemIndex) => {
        if (itemIndex === index) {
            return false;
        }

        if (Number(item?.parentId || -2) === rowId) {
            return false;
        }

        return true;
    });
}

function joinFieldOptionsForTable(tableName) {
    const table = String(tableName || '').trim();
    const fields = Array.isArray(dbStructure.value?.[table]?.fields)
        ? dbStructure.value[table].fields
        : [];

    return fields.map((column) => ({
        value: column,
        title: column,
    }));
}

function relationshipOptionsForOrigin(originTable) {
    const table = String(originTable || '').trim();
    const rels = Array.isArray(dbStructure.value?.[table]?.relationships)
        ? dbStructure.value[table].relationships
        : [];

    return rels.map((relTable) => ({
        value: relTable,
        title: relTable,
    }));
}

function quoteSqlValue(value) {
    if (value === null || value === undefined) {
        return 'NULL';
    }

    const text = String(value).trim();

    const dbFunction = dbFunctionExpression(text);

    if (dbFunction !== null) {
        return dbFunction;
    }

    if (text === '') {
        return "''";
    }

    const upper = text.toUpperCase();

    if (upper === 'NULL' || upper === 'TRUE' || upper === 'FALSE') {
        return upper;
    }

    if (/^-?\d+(\.\d+)?$/.test(text)) {
        return text;
    }

    return `'${text.replaceAll("'", "''")}'`;
}

function dbFunctionExpression(value) {
    const text = String(value || '')
        .trim()
        .toUpperCase();

    if (text === '') {
        return null;
    }

    const allowed = new Set([
        'CURDATE()',
        'CURRENT_DATE',
        'CURRENT_DATE()',
        'CURRENT_TIMESTAMP',
        'CURRENT_TIMESTAMP()',
        'NOW()',
        'CURTIME()',
        'UTC_DATE()',
        'UTC_TIMESTAMP()',
    ]);

    return allowed.has(text) ? text : null;
}

function parseListValues(value) {
    if (Array.isArray(value)) {
        return value
            .map((item) => String(item || '').trim())
            .filter((item) => item !== '');
    }

    return String(value || '')
        .split(',')
        .map((item) => item.trim())
        .filter((item) => item !== '');
}

function normalizeCondition(condition) {
    const output = String(condition || '=')
        .trim()
        .toUpperCase();

    return whereConditionOptions.includes(output) ? output : '=';
}

function rowCondition(row) {
    return normalizeCondition(row?.whereFieldCondition);
}

function rowIsNullCondition(row) {
    return ['IS NULL', 'IS NOT NULL'].includes(rowCondition(row));
}

function rowIsRangeCondition(row) {
    return ['BETWEEN', 'NOT BETWEEN'].includes(rowCondition(row));
}

function conditionHelpText(row) {
    const condition = rowCondition(row);

    if (condition === 'BETWEEN' || condition === 'NOT BETWEEN') {
        return t(
            'form.builder.condition_help.range',
            'Gebruik een vanaf- en tot-waarde (of twee parameters).',
        );
    }

    if (condition === 'IS NULL' || condition === 'IS NOT NULL') {
        return t(
            'form.builder.condition_help.null',
            'Geen waarde nodig voor deze conditie.',
        );
    }

    if (condition === 'IN' || condition === 'NOT IN') {
        return t(
            'form.builder.condition_help.list',
            'Gebruik een kommagestuurde lijst of Json Array.',
        );
    }

    if (condition === 'LIKE' || condition === 'NOT LIKE') {
        return t(
            'form.builder.condition_help.like',
            'Zoekt op bevat; wildcards worden automatisch toegevoegd.',
        );
    }

    if (condition === 'IS' || condition === 'IS NOT') {
        return t(
            'form.builder.condition_help.is',
            'Gebruik NULL/TRUE/FALSE/UNKNOWN of een gewone waarde.',
        );
    }

    return t(
        'form.builder.condition_help.default',
        'Vergelijkt met een enkele waarde of parameter.',
    );
}

function secondListValue(value) {
    const values = parseListValues(value);

    if (!values[1]) {
        return '';
    }

    return String(values[1]).trim();
}

function whereRowValueSql(row, testMode = false, useToValue = false) {
    const type = String(row?.varOrValue || 'Vaste waarde').trim();
    const valueKey = useToValue ? 'value_to' : 'value';
    const parameterKey = useToValue ? 'variabele_to' : 'variabele';
    const testValueKey = useToValue ? 'testValueTo' : 'testValue';

    const rawValue =
        useToValue && String(row?.[valueKey] ?? '').trim() === ''
            ? secondListValue(row?.value)
            : row?.[valueKey];
    const rawParameter =
        useToValue && String(row?.[parameterKey] ?? '').trim() === ''
            ? secondListValue(row?.variabele)
            : row?.[parameterKey];
    const rawTestValue =
        useToValue && String(row?.[testValueKey] ?? '').trim() === ''
            ? secondListValue(row?.testValue)
            : row?.[testValueKey];

    if (type === 'Parameter') {
        const parameterName = String(rawParameter || '').trim();
        const fallbackTest = String(rawTestValue || '').trim();

        if (parameterName === '') {
            return testMode ? quoteSqlValue(fallbackTest) : 'NULL';
        }

        return testMode
            ? quoteSqlValue(fallbackTest !== '' ? fallbackTest : null)
            : `:${parameterName}`;
    }

    if (type === 'Systeemvariabele') {
        const systemVariable = String(rawValue || '').trim();

        if (systemVariable === '') {
            return 'NULL';
        }

        return quoteSqlValue(systemVariable);
    }

    if (type === 'Json Array') {
        return quoteSqlValue(String(rawValue || '').trim());
    }

    return quoteSqlValue(rawValue ?? null);
}

function whereRowExpression(row, testMode = false) {
    const field = String(row?.whereField || '').trim();
    const condition = normalizeCondition(row?.whereFieldCondition);

    if (field === '') {
        return '';
    }

    const valueSql = whereRowValueSql(row, testMode, false);
    const valueType = typeForRow(row);

    if (condition === 'IN' || condition === 'NOT IN') {
        if (valueType === 'Json Array') {
            const rawValues = parseListValues(row?.value);

            if (rawValues.length === 0) {
                return '';
            }

            const predicates = rawValues.map((item) => {
                if (/^-?\d+(\.\d+)?$/.test(item)) {
                    return `JSON_CONTAINS(${field}, '${item}') OR JSON_CONTAINS(${field}, JSON_QUOTE('${item.replaceAll("'", "''")}'))`;
                }

                return `JSON_CONTAINS(${field}, JSON_QUOTE('${item.replaceAll("'", "''")}'))`;
            });

            if (condition === 'IN') {
                return `(${predicates
                    .map((predicate) =>
                        predicate.includes(' OR ')
                            ? `(${predicate})`
                            : predicate,
                    )
                    .join(' OR ')})`;
            }

            return predicates
                .map((predicate) => `NOT (${predicate})`)
                .join(' AND ');
        }

        const normalized = String(valueSql || '').trim();

        if (normalized.startsWith('(')) {
            return `${field} ${condition} ${normalized}`;
        }

        const raw = parseListValues(row?.value).map((item) =>
            quoteSqlValue(item),
        );
        const listSql = raw.length > 0 ? raw.join(', ') : valueSql;

        return `${field} ${condition} (${listSql})`;
    }

    if (condition === 'BETWEEN' || condition === 'NOT BETWEEN') {
        const valueToSql = whereRowValueSql(row, testMode, true);

        if (
            String(valueSql).trim() === '' ||
            String(valueToSql).trim() === '' ||
            String(valueSql).trim().toUpperCase() === 'NULL' ||
            String(valueToSql).trim().toUpperCase() === 'NULL'
        ) {
            return '';
        }

        return `${field} ${condition} ${valueSql} AND ${valueToSql}`;
    }

    if (condition === 'IS NULL' || condition === 'IS NOT NULL') {
        return `${field} ${condition}`;
    }

    if (condition === 'IS' || condition === 'IS NOT') {
        const upperValue = String(valueSql || '')
            .trim()
            .toUpperCase();

        if (['NULL', 'TRUE', 'FALSE', 'UNKNOWN'].includes(upperValue)) {
            return `${field} ${condition} ${upperValue}`;
        }

        const fallbackCondition = condition === 'IS' ? '=' : '!=';

        return `${field} ${fallbackCondition} ${valueSql}`;
    }

    if (condition === 'LIKE' || condition === 'NOT LIKE') {
        const base = String(row?.value ?? '').trim();
        const likeValue = testMode
            ? quoteSqlValue(`%${base}%`)
            : typeForRow(row) === 'Parameter'
              ? `CONCAT('%', ${valueSql}, '%')`
              : quoteSqlValue(`%${base}%`);

        return `${field} ${condition} ${likeValue}`;
    }

    return `${field} ${condition} ${valueSql}`;
}

function typeForRow(row) {
    return String(row?.varOrValue || '').trim();
}

function buildLogicalClauseLines(rows, firstKeyword, testMode = false) {
    const normalizedRows = Array.isArray(rows)
        ? rows.map((row, index) => ({
              ...row,
              id: Number(row?.id ?? index),
          }))
        : [];
    const childMap = new Map();
    const topRows = [];

    normalizedRows.forEach((row) => {
        if (
            row?.subRow &&
            row?.parentId !== null &&
            row?.parentId !== undefined
        ) {
            const key = Number(row.parentId);

            if (!childMap.has(key)) {
                childMap.set(key, []);
            }

            childMap.get(key).push(row);

            return;
        }

        topRows.push(row);
    });

    const lines = [];

    topRows.forEach((parentRow, parentIndex) => {
        const parentExpression = whereRowExpression(parentRow, testMode);
        const childRows = childMap.get(Number(parentRow?.id ?? -1)) || [];
        const childExpressions = childRows
            .map((childRow) => ({
                boolean:
                    String(childRow?.whereFieldAndOr || 'AND')
                        .trim()
                        .toUpperCase() === 'OR'
                        ? 'OR'
                        : 'AND',
                expression: whereRowExpression(childRow, testMode),
            }))
            .filter((item) => item.expression !== '');

        if (parentExpression === '' && childExpressions.length === 0) {
            return;
        }

        let groupedExpression = parentExpression;

        childExpressions.forEach((item, index) => {
            if (index === 0 && groupedExpression === '') {
                groupedExpression = item.expression;

                return;
            }

            groupedExpression = `${groupedExpression} ${item.boolean} ${item.expression}`;
        });

        const groupPrefix =
            parentIndex === 0
                ? firstKeyword
                : String(parentRow?.whereFieldAndOr || 'AND')
                        .trim()
                        .toUpperCase() === 'OR'
                  ? 'OR'
                  : 'AND';

        if (childExpressions.length > 0) {
            lines.push(`${groupPrefix} (${groupedExpression})`);

            return;
        }

        lines.push(`${groupPrefix} ${groupedExpression}`);
    });

    return lines;
}

function queryBuilderBuildSql(testMode = false) {
    if (!isBuilderMode.value) {
        return;
    }

    const baseTable = String(form.table_name || '').trim();

    if (baseTable === '') {
        form.query = '';
        form.test_query = '';

        return;
    }

    const joins = Array.isArray(form.join_rows) ? form.join_rows : [];
    const whereRows = Array.isArray(form.where_rows) ? form.where_rows : [];
    const aggregateRows = Array.isArray(form.aggregate_rows)
        ? form.aggregate_rows
        : [];
    const groupRows = Array.isArray(form.group_rows) ? form.group_rows : [];
    const havingRows = Array.isArray(form.having_rows) ? form.having_rows : [];
    const selectedFields = Array.isArray(form.selected_fields)
        ? form.selected_fields
        : [];

    const selectParts = [];
    const useGrouping = Boolean(form.group_by);

    if (useGrouping) {
        groupRows.forEach((field) => {
            const normalizedField = String(field || '').trim();

            if (
                normalizedField !== '' &&
                !selectParts.includes(normalizedField)
            ) {
                selectParts.push(normalizedField);
            }
        });

        selectedFields.forEach((field) => {
            const normalizedField = String(field || '').trim();

            if (
                normalizedField !== '' &&
                !selectParts.includes(normalizedField)
            ) {
                selectParts.push(normalizedField);
            }
        });
    } else if (form.all_fields) {
        selectParts.push(`${baseTable}.*`);
    } else {
        selectedFields.forEach((field) => {
            const normalizedField = String(field || '').trim();

            if (normalizedField !== '') {
                selectParts.push(normalizedField);
            }
        });
    }

    aggregateRows.forEach((row) => {
        const func = aggregateRowFunction(row);
        const alias = String(row?.alias || '').trim();

        if (func === '' || alias === '') {
            return;
        }

        if (aggregateRowIsFormula(row)) {
            const formula = String(row?.formula || row?.field || '').trim();

            if (formula === '') {
                return;
            }

            selectParts.push(`${formula} AS ${alias}`);

            return;
        }

        if (aggregateRowIsConcat(row)) {
            const concatFields = Array.isArray(row?.fields)
                ? row.fields
                      .map((field) => String(field || '').trim())
                      .filter((field) => field !== '')
                : String(row?.field || '')
                      .split(',')
                      .map((field) => field.trim())
                      .filter((field) => field !== '');

            if (concatFields.length === 0) {
                return;
            }

            const separator = String(row?.separator || ' ').replaceAll(
                "'",
                "''",
            );

            const concatSql =
                separator === ''
                    ? `CONCAT(${concatFields.join(', ')})`
                    : `CONCAT_WS('${separator}', ${concatFields.join(', ')})`;

            selectParts.push(`${concatSql} AS ${alias}`);

            return;
        }

        const field = String(row?.field || '').trim();

        if (field === '') {
            return;
        }

        if (func === 'GROUP_CONCAT') {
            const distinct = row?.distinct ? 'DISTINCT ' : '';
            const separator = String(row?.separator || ',').replaceAll(
                "'",
                "''",
            );
            selectParts.push(
                `${func}(${distinct}${field} SEPARATOR '${separator}') AS ${alias}`,
            );

            return;
        }

        const distinct = row?.distinct ? 'DISTINCT ' : '';
        selectParts.push(`${func}(${distinct}${field}) AS ${alias}`);
    });

    if (selectParts.length === 0) {
        selectParts.push(`${baseTable}.*`);
    }

    const lines = [];
    const distinctPrefix = form.distinct_select ? 'DISTINCT ' : '';
    lines.push(`SELECT ${distinctPrefix}${selectParts.join(', ')}`);
    lines.push(`FROM ${baseTable}`);

    joins.forEach((row) => {
        const joinType = String(row?.joinType || 'LEFT')
            .trim()
            .toUpperCase();
        const relTable = String(row?.relTable || '').trim();
        const originTable = String(row?.originTable || baseTable).trim();
        const relFieldT1 = String(row?.relFieldT1 || '').trim();
        const relFieldT2 = String(row?.relFieldT2 || '').trim();

        if (
            relTable === '' ||
            originTable === '' ||
            relFieldT1 === '' ||
            relFieldT2 === ''
        ) {
            return;
        }

        const safeJoinType = ['LEFT', 'RIGHT', 'INNER'].includes(joinType)
            ? joinType
            : 'LEFT';

        lines.push(
            `${safeJoinType} JOIN ${relTable} ON ${originTable}.${relFieldT1} = ${relTable}.${relFieldT2}`,
        );
    });

    lines.push(...buildLogicalClauseLines(whereRows, 'WHERE', testMode));

    if (useGrouping && groupRows.length > 0) {
        const validGroupRows = groupRows
            .map((field) => String(field || '').trim())
            .filter((field) => field !== '');

        if (validGroupRows.length > 0) {
            lines.push(`GROUP BY ${validGroupRows.join(', ')}`);
        }
    }

    lines.push(...buildLogicalClauseLines(havingRows, 'HAVING', testMode));

    const sql = lines.join('\n');

    if (testMode) {
        form.test_query = sql;

        return;
    }

    form.query = sql;
}

function importBindingsFromInspect() {
    if (!Array.isArray(inspectResult.value.bindings)) {
        return;
    }

    if (!Array.isArray(form.binding_rows)) {
        form.binding_rows = [];
    }

    const existingParameters = new Set(
        form.binding_rows
            .map((row) => String(row?.parameter || '').trim())
            .filter((parameter) => parameter !== ''),
    );

    inspectResult.value.bindings.forEach((binding) => {
        const parameter = String(binding || '').trim();

        if (parameter === '' || existingParameters.has(parameter)) {
            return;
        }

        form.binding_rows.push({
            type: 'text',
            parameter,
            parameter_to: '',
            source_table_id: null,
            title: parameter,
            title_key: '',
            prompt: '',
            prompt_key: '',
            sort_order: form.binding_rows.length + 1,
        });

        existingParameters.add(parameter);
    });

    normalizeBindingSortOrder();
}

async function loadBindingSources() {
    bindingSourceLoading.value = true;

    try {
        const response = await window.axios.get(
            route('admin.queries.builder.binding-source-options'),
        );

        const sources = Array.isArray(response?.data?.sources)
            ? response.data.sources
            : [];

        bindingSourceOptions.value = sources.map((source) => ({
            value: Number(source.id),
            title: `${String(source.name || '')} (${String(source.table_name || '')})`,
        }));
    } catch {
        bindingSourceOptions.value = [];
    } finally {
        bindingSourceLoading.value = false;
    }
}

onMounted(() => {
    queryBuilderEnsureRows();
    normalizeBindingSortOrder();
    loadBindingSources();
    inlineChartBuilder.value = createChartBuilderStateFromConfig(
        form.chart_config,
    );
    if (isChartOutputMode.value) {
        form.chart_config = buildChartConfigFromState(inlineChartBuilder.value);
    }
    queryBuilderBuildSql(false);
    queryBuilderBuildSql(true);
});

watch(
    () => form.output_mode,
    (outputMode) => {
        const normalizedOutputMode = String(outputMode || 'table');

        if (normalizedOutputMode === 'table') {
            form.chart_config = null;

            return;
        }

        if (normalizedOutputMode === 'chart') {
            form.report_data_source = null;
            form.report_output_format = null;
            form.chart_config = buildChartConfigFromState(
                inlineChartBuilder.value,
            );

            return;
        }

        if (normalizedOutputMode === 'report') {
            if (!String(form.report_data_source || '').trim()) {
                form.report_data_source = 'query';
            }

            if (!String(form.report_output_format || '').trim()) {
                form.report_output_format = 'same_format';
            }
        }

        if (normalizedOutputMode === 'excel') {
            form.report_data_source = null;
            form.report_output_format = null;
        }

        form.chart_config = null;
    },
);

watch(
    () => inlineChartBuilder.value.aggregate,
    (aggregate) => {
        if (String(aggregate || 'count') === 'count') {
            inlineChartBuilder.value.metricField = '';
        }
    },
);

watch(
    () => inlineChartBuilder.value,
    (state) => {
        if (!isChartOutputMode.value) {
            return;
        }

        form.chart_config = buildChartConfigFromState(state);
    },
    { deep: true },
);

watch(
    () => form.table_name,
    (tableName) => {
        const baseTable = String(tableName || '').trim();

        if (baseTable === '' || !Array.isArray(form.join_rows)) {
            return;
        }

        form.join_rows = form.join_rows.map((row) => {
            if (row?.subRow) {
                return row;
            }

            if (String(row?.originTable || '').trim() !== '') {
                return row;
            }

            return {
                ...row,
                originTable: baseTable,
            };
        });
    },
);

watch(
    () => [
        form.query_mode,
        form.table_name,
        form.all_fields,
        form.distinct_select,
        form.selected_fields,
        form.join_rows,
        form.where_rows,
        form.group_by,
        form.group_rows,
        form.aggregate_rows,
        form.having_rows,
    ],
    () => {
        if (!isBuilderMode.value) {
            return;
        }

        queryBuilderBuildSql(false);
        queryBuilderBuildSql(true);
    },
    { deep: true },
);
</script>
