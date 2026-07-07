<template>
    <AdminLayout :suppress-flash="true">
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
                                <span class="mdi mdi-email-outline text-2xl" />
                            </div>
                            <div class="min-w-0">
                                <CardTitle class="text-lg">
                                    {{ pageTitle }}
                                </CardTitle>
                                <CardDescription class="mt-1">
                                    {{
                                        t(
                                            'mail.email_description',
                                            'Edit subject, preheader and text content for this email.',
                                        )
                                    }}
                                </CardDescription>
                            </div>
                        </div>

                        <div class="flex flex-wrap justify-end gap-2">
                            <AdminFormBackButton
                                :href="route('admin.cms.emails.index')"
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
                                :disabled="previewLoading"
                                @click="loadPreview"
                            >
                                <span
                                    :class="[
                                        previewLoading
                                            ? 'mdi-loading animate-spin'
                                            : 'mdi-eye-outline',
                                        'mdi text-base',
                                    ]"
                                    aria-hidden="true"
                                />
                                {{ t('mail.preview', 'Preview') }}
                            </Button>

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

                            <div
                                v-if="isEditMode"
                                class="grid justify-items-end gap-1 text-right"
                            >
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="gap-2 border-blue-200 text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800"
                                    :disabled="
                                        form.processing || !testEmailRecipient
                                    "
                                    :title="testEmailTitle"
                                    @click="sendTestEmail"
                                >
                                    <span
                                        class="mdi mdi-send-outline text-base"
                                        aria-hidden="true"
                                    />
                                    {{ t('mail.test_email', 'Send test') }}
                                </Button>
                                <p class="max-w-72 text-xs text-slate-600">
                                    {{ testEmailRecipientLabel }}
                                    <template v-if="mailTestDeliveryUrl">
                                        <span aria-hidden="true"> · </span>
                                        <a
                                            :href="mailTestDeliveryUrl"
                                            target="_blank"
                                            rel="noreferrer"
                                            class="font-medium text-blue-700 underline-offset-2 hover:underline"
                                        >
                                            {{
                                                t(
                                                    'mail.test_email_mailpit_hint',
                                                    'Open Mailpit',
                                                )
                                            }}
                                        </a>
                                    </template>
                                </p>
                            </div>

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

                <CardContent class="min-h-0 flex-1 overflow-y-auto p-4 sm:p-5">
                    <div class="grid gap-5">
                        <section class="grid gap-2">
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
                                        :class="translationStatusClass(item)"
                                        :disabled="item.type === 'current'"
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
                        </section>

                        <div
                            class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]"
                        >
                            <div class="grid gap-5">
                                <section class="grid gap-4">
                                    <div class="grid gap-4 md:grid-cols-2">
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
                                                    commonT(
                                                        'columns.title',
                                                        'Title',
                                                    )
                                                }}
                                            </Label>
                                            <Input
                                                id="title"
                                                v-model="form.title"
                                                class="bg-yellow-50"
                                                required
                                            />
                                            <p
                                                v-if="form.errors.title"
                                                class="text-sm text-red-600"
                                            >
                                                {{ form.errors.title }}
                                            </p>
                                        </div>

                                        <div class="grid gap-2">
                                            <Label
                                                class="flex items-center gap-1"
                                            >
                                                <span
                                                    class="text-red-600"
                                                    aria-hidden="true"
                                                    >*</span
                                                >
                                                {{
                                                    t(
                                                        'mail.fields.template',
                                                        'Mail template',
                                                    )
                                                }}
                                            </Label>
                                            <RwAutoCompleteInput
                                                v-model="
                                                    form.cms_mail_template_id
                                                "
                                                class="bg-yellow-50"
                                                :items="mailTemplates"
                                                item-title="name"
                                                item-value="id"
                                                :search-fields="['name', 'key']"
                                                required-highlight-color="#fefce8"
                                            />
                                        </div>
                                    </div>

                                    <div class="grid gap-4 md:grid-cols-3">
                                        <div class="grid gap-2">
                                            <Label>{{
                                                commonT(
                                                    'columns.locale',
                                                    'Language',
                                                )
                                            }}</Label>
                                            <RwAutoCompleteInput
                                                v-model="form.locale"
                                                class="bg-yellow-50"
                                                :items="activeLanguages"
                                                item-title="name"
                                                item-value="locale"
                                                :search-fields="[
                                                    'name',
                                                    'native_name',
                                                    'locale',
                                                ]"
                                                required-highlight-color="#fefce8"
                                            />
                                        </div>

                                        <div class="grid gap-2">
                                            <Label>{{
                                                t(
                                                    'mail.fields.email_type',
                                                    'Email type',
                                                )
                                            }}</Label>
                                            <RwAutoCompleteInput
                                                v-model="form.email_type"
                                                :items="emailTypeOptions"
                                                item-title="label"
                                                item-value="value"
                                                :search-fields="[
                                                    'label',
                                                    'value',
                                                ]"
                                            />
                                        </div>

                                        <div class="grid gap-2">
                                            <Label>{{
                                                t(
                                                    'mail.fields.system_key',
                                                    'System key',
                                                )
                                            }}</Label>
                                            <RwAutoCompleteInput
                                                v-if="
                                                    form.email_type === 'system'
                                                "
                                                v-model="form.system_key"
                                                :items="systemMailOptions"
                                                item-title="label"
                                                item-value="value"
                                                :search-fields="[
                                                    'label',
                                                    'value',
                                                ]"
                                            />
                                            <Input
                                                v-else
                                                v-model="form.system_key"
                                                disabled
                                            />
                                        </div>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label
                                            for="subject"
                                            class="flex items-center gap-1"
                                        >
                                            <span
                                                class="text-red-600"
                                                aria-hidden="true"
                                                >*</span
                                            >
                                            {{
                                                t(
                                                    'mail.fields.subject',
                                                    'Subject',
                                                )
                                            }}
                                        </Label>
                                        <Input
                                            id="subject"
                                            v-model="form.subject"
                                            class="bg-yellow-50"
                                            required
                                        />
                                        <p
                                            v-if="form.errors.subject"
                                            class="text-sm text-red-600"
                                        >
                                            {{ form.errors.subject }}
                                        </p>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="preheader">
                                            {{
                                                t(
                                                    'mail.fields.preheader',
                                                    'Preheader',
                                                )
                                            }}
                                        </Label>
                                        <Input
                                            id="preheader"
                                            v-model="form.preheader"
                                        />
                                    </div>

                                    <section
                                        class="grid gap-4 rounded border border-slate-200 bg-slate-50 p-3"
                                    >
                                        <div>
                                            <h2
                                                class="text-sm font-semibold text-slate-900"
                                            >
                                                {{
                                                    t(
                                                        'mail.deliverability_settings',
                                                        'Deliverability settings',
                                                    )
                                                }}
                                            </h2>
                                            <p
                                                class="mt-1 text-sm text-slate-600"
                                            >
                                                {{
                                                    t(
                                                        'mail.deliverability_settings_help',
                                                        'Optionally override sender and reply-to metadata for this email. Empty fields use the platform mail defaults.',
                                                    )
                                                }}
                                            </p>
                                        </div>

                                        <div class="grid gap-4 md:grid-cols-2">
                                            <div class="grid gap-2">
                                                <Label for="from_name">
                                                    {{
                                                        t(
                                                            'mail.fields.from_name',
                                                            'From name',
                                                        )
                                                    }}
                                                </Label>
                                                <Input
                                                    id="from_name"
                                                    v-model="
                                                        form.settings.from_name
                                                    "
                                                />
                                                <p
                                                    v-if="
                                                        form.errors[
                                                            'settings.from_name'
                                                        ]
                                                    "
                                                    class="text-sm text-red-600"
                                                >
                                                    {{
                                                        form.errors[
                                                            'settings.from_name'
                                                        ]
                                                    }}
                                                </p>
                                            </div>

                                            <div class="grid gap-2">
                                                <Label for="from_email">
                                                    {{
                                                        t(
                                                            'mail.fields.from_email',
                                                            'From email',
                                                        )
                                                    }}
                                                </Label>
                                                <Input
                                                    id="from_email"
                                                    v-model="
                                                        form.settings.from_email
                                                    "
                                                    type="email"
                                                />
                                                <p
                                                    v-if="
                                                        form.errors[
                                                            'settings.from_email'
                                                        ]
                                                    "
                                                    class="text-sm text-red-600"
                                                >
                                                    {{
                                                        form.errors[
                                                            'settings.from_email'
                                                        ]
                                                    }}
                                                </p>
                                            </div>

                                            <div class="grid gap-2">
                                                <Label for="reply_to_name">
                                                    {{
                                                        t(
                                                            'mail.fields.reply_to_name',
                                                            'Reply-to name',
                                                        )
                                                    }}
                                                </Label>
                                                <Input
                                                    id="reply_to_name"
                                                    v-model="
                                                        form.settings
                                                            .reply_to_name
                                                    "
                                                />
                                                <p
                                                    v-if="
                                                        form.errors[
                                                            'settings.reply_to_name'
                                                        ]
                                                    "
                                                    class="text-sm text-red-600"
                                                >
                                                    {{
                                                        form.errors[
                                                            'settings.reply_to_name'
                                                        ]
                                                    }}
                                                </p>
                                            </div>

                                            <div class="grid gap-2">
                                                <Label for="reply_to_email">
                                                    {{
                                                        t(
                                                            'mail.fields.reply_to_email',
                                                            'Reply-to email',
                                                        )
                                                    }}
                                                </Label>
                                                <Input
                                                    id="reply_to_email"
                                                    v-model="
                                                        form.settings
                                                            .reply_to_email
                                                    "
                                                    type="email"
                                                />
                                                <p
                                                    v-if="
                                                        form.errors[
                                                            'settings.reply_to_email'
                                                        ]
                                                    "
                                                    class="text-sm text-red-600"
                                                >
                                                    {{
                                                        form.errors[
                                                            'settings.reply_to_email'
                                                        ]
                                                    }}
                                                </p>
                                            </div>
                                        </div>
                                    </section>

                                    <label
                                        class="flex items-center gap-2 text-sm text-slate-700"
                                    >
                                        <Checkbox
                                            v-model:checked="form.is_active"
                                        />
                                        {{
                                            commonT('columns.active', 'Active')
                                        }}
                                    </label>
                                </section>

                                <section
                                    class="grid gap-3 border-t border-slate-200 pt-5"
                                >
                                    <div>
                                        <h2
                                            class="text-base font-semibold text-slate-900"
                                        >
                                            {{
                                                t(
                                                    'mail.content_blocks',
                                                    'Email content',
                                                )
                                            }}
                                        </h2>
                                        <p class="mt-1 text-sm text-slate-600">
                                            {{
                                                t(
                                                    'mail.content_blocks_help',
                                                    'Fill the content blocks defined by the selected mail template.',
                                                )
                                            }}
                                        </p>
                                    </div>

                                    <div
                                        v-if="placeholderWarnings.length > 0"
                                        class="rounded-md border border-orange-200 bg-orange-50 px-3 py-2 text-sm text-orange-800"
                                    >
                                        <div
                                            class="flex items-start gap-2 font-semibold"
                                        >
                                            <span
                                                class="mdi mdi-alert-circle-outline mt-0.5 text-base"
                                                aria-hidden="true"
                                            />
                                            <span>
                                                {{
                                                    t(
                                                        'mail.placeholder_warnings_title',
                                                        'Placeholder warnings',
                                                    )
                                                }}
                                            </span>
                                        </div>
                                        <p class="mt-1 text-orange-700">
                                            {{
                                                t(
                                                    'mail.placeholder_warnings_help',
                                                    'These placeholders are not available for the selected mail context and will remain visible unless corrected.',
                                                )
                                            }}
                                        </p>
                                        <ul class="mt-2 grid gap-1">
                                            <li
                                                v-for="warning in placeholderWarnings"
                                                :key="warning.key"
                                                class="flex flex-wrap gap-1"
                                            >
                                                <span class="font-medium">
                                                    {{ warning.label }}:
                                                </span>
                                                <code>{{
                                                    warning.placeholder
                                                }}</code>
                                            </li>
                                        </ul>
                                    </div>

                                    <div
                                        v-if="contractWarnings.length > 0"
                                        class="rounded-md border border-orange-200 bg-orange-50 px-3 py-2 text-sm text-orange-800"
                                    >
                                        <div
                                            class="flex items-start gap-2 font-semibold"
                                        >
                                            <span
                                                class="mdi mdi-alert-circle-outline mt-0.5 text-base"
                                                aria-hidden="true"
                                            />
                                            <span>
                                                {{
                                                    t(
                                                        'mail.contract_warnings_title',
                                                        'Template contract warnings',
                                                    )
                                                }}
                                            </span>
                                        </div>
                                        <p class="mt-1 text-orange-700">
                                            {{
                                                t(
                                                    'mail.contract_warnings_help',
                                                    'The selected mail template marks these fields as required. Fill them before using this email in production.',
                                                )
                                            }}
                                        </p>
                                        <ul class="mt-2 grid gap-1">
                                            <li
                                                v-for="warning in contractWarnings"
                                                :key="warning.key"
                                                class="flex flex-wrap gap-1"
                                            >
                                                <span class="font-medium">
                                                    {{ warning.blockLabel }}:
                                                </span>
                                                <span>{{
                                                    warning.fieldLabel
                                                }}</span>
                                            </li>
                                        </ul>
                                    </div>

                                    <div class="grid gap-4">
                                        <div
                                            v-for="block in selectedTemplateBlocks"
                                            :key="block.key"
                                            class="grid gap-2 rounded border border-slate-200 bg-slate-50 p-3"
                                        >
                                            <div
                                                class="flex flex-wrap items-center justify-between gap-2"
                                            >
                                                <Label
                                                    class="font-semibold text-slate-800"
                                                >
                                                    {{
                                                        block.label || block.key
                                                    }}
                                                </Label>
                                                <span
                                                    class="text-xs text-slate-500"
                                                >
                                                    {{
                                                        t(
                                                            `mail.block_types.${block.type}`,
                                                            block.type,
                                                        )
                                                    }}
                                                </span>
                                            </div>

                                            <div
                                                v-if="block.fields.length > 0"
                                                class="grid gap-3"
                                            >
                                                <div
                                                    v-for="field in block.fields"
                                                    :key="`${block.key}:${field.name}`"
                                                    class="grid gap-1"
                                                >
                                                    <Label
                                                        :class="{
                                                            'flex items-center gap-1':
                                                                field.required,
                                                        }"
                                                    >
                                                        <span
                                                            v-if="
                                                                field.required
                                                            "
                                                            class="text-red-600"
                                                            aria-hidden="true"
                                                            >*</span
                                                        >
                                                        {{ fieldLabel(field) }}
                                                    </Label>

                                                    <textarea
                                                        v-if="
                                                            field.type ===
                                                            'textarea'
                                                        "
                                                        v-model="
                                                            contentBlock(
                                                                block.key,
                                                            )[field.name]
                                                        "
                                                        :rows="
                                                            block.type ===
                                                            'mail_heading'
                                                                ? 2
                                                                : 4
                                                        "
                                                        :class="[
                                                            'rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100',
                                                            field.required
                                                                ? 'bg-yellow-50'
                                                                : 'bg-white',
                                                        ]"
                                                        @focus="
                                                            activeTextTarget =
                                                                $event.target
                                                        "
                                                    />

                                                    <RwAutoCompleteInput
                                                        v-else-if="
                                                            field.type ===
                                                            'media'
                                                        "
                                                        v-model="
                                                            contentBlock(
                                                                block.key,
                                                            )[field.name]
                                                        "
                                                        :items="mediaOptions"
                                                        item-title="label"
                                                        item-value="id"
                                                        :search-fields="[
                                                            'label',
                                                            'filename',
                                                            'alt_text',
                                                        ]"
                                                    />

                                                    <Input
                                                        v-else
                                                        v-model="
                                                            contentBlock(
                                                                block.key,
                                                            )[field.name]
                                                        "
                                                        :class="
                                                            field.required
                                                                ? 'bg-yellow-50'
                                                                : ''
                                                        "
                                                        @focus="
                                                            activeTextTarget =
                                                                $event.target
                                                        "
                                                    />
                                                </div>
                                            </div>

                                            <p
                                                v-else
                                                class="text-sm text-slate-600"
                                            >
                                                {{
                                                    t(
                                                        'mail.no_editable_fields',
                                                        'This block is generated automatically from the mail context.',
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                </section>

                                <section
                                    class="grid gap-2 border-t border-slate-200 pt-5"
                                >
                                    <Label for="plain_text">
                                        {{
                                            t(
                                                'mail.fields.plain_text',
                                                'Plain text fallback',
                                            )
                                        }}
                                    </Label>
                                    <textarea
                                        id="plain_text"
                                        v-model="form.plain_text"
                                        rows="5"
                                        class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                    />
                                </section>
                            </div>

                            <aside
                                class="grid content-start gap-3 rounded border border-slate-200 bg-slate-50 p-3"
                            >
                                <h2
                                    class="text-sm font-semibold text-slate-900"
                                >
                                    {{
                                        t(
                                            'mail.placeholders',
                                            'Available placeholders',
                                        )
                                    }}
                                </h2>
                                <div class="grid gap-2 text-xs text-slate-700">
                                    <div
                                        v-for="placeholder in activePlaceholders"
                                        :key="placeholder.key"
                                        class="rounded border border-slate-200 bg-white p-2"
                                    >
                                        <code
                                            v-text="
                                                placeholderSyntax(
                                                    placeholder.key,
                                                )
                                            "
                                        />
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                class="h-7 px-2 text-xs shadow-none"
                                                @click="
                                                    copyPlaceholder(
                                                        placeholder.key,
                                                    )
                                                "
                                            >
                                                {{
                                                    commonT(
                                                        'actions.copy',
                                                        'Copy',
                                                    )
                                                }}
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                class="h-7 px-2 text-xs shadow-none"
                                                @click="
                                                    pastePlaceholder(
                                                        placeholder.key,
                                                    )
                                                "
                                            >
                                                {{
                                                    commonT(
                                                        'actions.paste',
                                                        'Paste',
                                                    )
                                                }}
                                            </Button>
                                        </div>
                                        <div class="mt-1 text-slate-500">
                                            {{ placeholder.label }}
                                        </div>
                                    </div>
                                </div>
                            </aside>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </form>

        <Dialog v-model:open="previewDialogOpen">
            <DialogContent
                class="flex max-h-[calc(100vh-2rem)] max-w-4xl flex-col overflow-hidden p-0 shadow-none"
            >
                <DialogHeader
                    class="shrink-0 border-b border-slate-200 px-6 py-4"
                >
                    <DialogTitle>{{
                        t('mail.preview', 'Preview')
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            t(
                                'mail.preview_description',
                                'Preview the saved email with sample data.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div
                    class="min-h-0 flex-1 overflow-y-auto bg-slate-100 px-4 py-5 sm:px-6"
                >
                    <div
                        v-if="previewContextOptions.length > 1"
                        class="mx-auto mb-3 grid max-w-[640px] gap-2 rounded border border-slate-200 bg-white p-3"
                    >
                        <Label>
                            {{
                                t('mail.preview_context_label', 'Preview data')
                            }}
                        </Label>
                        <RwAutoCompleteInput
                            v-model="selectedPreviewFormSubmissionId"
                            :items="previewContextOptions"
                            item-title="label"
                            item-value="id"
                            :search-fields="['label']"
                        />
                    </div>

                    <div
                        v-if="previewError"
                        class="mx-auto max-w-[640px] rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
                    >
                        {{ previewError }}
                    </div>

                    <div
                        v-else-if="previewLoading"
                        class="mx-auto grid h-[34rem] max-w-[640px] place-items-center rounded border border-slate-200 bg-white text-sm text-slate-600"
                    >
                        <span
                            class="mdi mdi-loading animate-spin text-2xl text-blue-700"
                            aria-hidden="true"
                        />
                        <span class="sr-only">
                            {{ t('mail.preview_loading', 'Loading preview') }}
                        </span>
                    </div>

                    <div v-else class="mx-auto grid max-w-[640px] gap-3">
                        <div
                            v-if="hasPreviewContent"
                            class="flex flex-wrap gap-2"
                            role="tablist"
                            :aria-label="t('mail.preview', 'Preview')"
                        >
                            <button
                                type="button"
                                role="tab"
                                :aria-selected="previewMode === 'html'"
                                class="rounded border px-3 py-1.5 text-sm font-medium shadow-none transition"
                                :class="previewTabClass('html')"
                                @click="previewMode = 'html'"
                            >
                                {{ t('mail.preview_html', 'HTML') }}
                            </button>
                            <button
                                type="button"
                                role="tab"
                                :aria-selected="previewMode === 'text'"
                                class="rounded border px-3 py-1.5 text-sm font-medium shadow-none transition"
                                :class="previewTabClass('text')"
                                @click="previewMode = 'text'"
                            >
                                {{ t('mail.preview_text', 'Text') }}
                            </button>
                        </div>

                        <iframe
                            v-if="previewMode === 'html' && previewHtml"
                            class="h-[70vh] w-full rounded border border-slate-200 bg-white"
                            :srcdoc="previewHtml"
                            :title="t('mail.preview_html', 'HTML')"
                        />

                        <pre
                            v-else-if="previewMode === 'text' && previewText"
                            class="h-[70vh] overflow-auto whitespace-pre-wrap rounded border border-slate-200 bg-white p-4 font-mono text-sm leading-6 text-slate-800"
                            >{{ previewText }}</pre
                        >

                        <div
                            v-else
                            class="rounded border border-slate-200 bg-white px-4 py-6 text-sm text-slate-600"
                        >
                            {{
                                t(
                                    'mail.preview_empty',
                                    'No preview content is available.',
                                )
                            }}
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <CmsRevisionHistoryDialog
            v-if="isEditMode"
            v-model:open="showRevisionDialog"
            subject-type="email"
            restore-route-name="admin.cms.emails.revisions.restore"
            :restore-route-params="{ email: emailItem.id }"
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
import CmsRevisionHistoryDialog from '@/Pages/Admin/Cms/Components/CmsRevisionHistoryDialog.vue';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    emailItem: { type: Object, default: null },
    revisions: { type: Array, default: () => [] },
    translations: { type: Array, default: () => [] },
    missingLanguages: { type: Array, default: () => [] },
    mailTemplates: { type: Array, required: true },
    activeLanguages: { type: Array, required: true },
    systemMailOptions: { type: Array, required: true },
    placeholdersByContext: { type: Object, required: true },
    mediaOptions: { type: Array, default: () => [] },
    mailTestDeliveryUrl: { type: String, default: '' },
    previewFormSubmissions: { type: Array, default: () => [] },
});

const page = usePage();
const { t } = useAdminTranslations('cms_admin_ui');
const { t: commonT } = useAdminTranslations('admin_common_ui');
const locale = computed(() => page.props?.app?.locale || 'nl-BE');
const isEditMode = computed(() => Number(props.emailItem?.id || 0) > 0);
const previewHtml = ref('');
const previewText = ref('');
const previewMode = ref('html');
const selectedPreviewFormSubmissionId = ref(null);
const previewDialogOpen = ref(false);
const showRevisionDialog = ref(false);
const previewError = ref('');
const previewLoading = ref(false);
const activeTextTarget = ref(null);

const form = useForm({
    cms_mail_template_id:
        props.emailItem?.cms_mail_template_id ||
        props.mailTemplates[0]?.id ||
        null,
    title: props.emailItem?.title || '',
    locale: props.emailItem?.locale || props.activeLanguages[0]?.locale || 'en',
    email_type: props.emailItem?.email_type || 'custom',
    system_key: props.emailItem?.system_key || null,
    subject: props.emailItem?.subject || '',
    preheader: props.emailItem?.preheader || '',
    content_blocks: { ...(props.emailItem?.content_blocks || {}) },
    plain_text: props.emailItem?.plain_text || '',
    settings: {
        from_name: props.emailItem?.settings?.from_name || '',
        from_email: props.emailItem?.settings?.from_email || '',
        reply_to_name: props.emailItem?.settings?.reply_to_name || '',
        reply_to_email: props.emailItem?.settings?.reply_to_email || '',
    },
    is_active: props.emailItem?.is_active ?? true,
});

const pageTitle = computed(() =>
    isEditMode.value
        ? t('mail.email_edit_title', 'Edit email')
        : t('mail.email_create_title', 'Create email'),
);

const selectedTemplate = computed(() =>
    props.mailTemplates.find(
        (template) => Number(template.id) === Number(form.cms_mail_template_id),
    ),
);

const selectedTemplateBlocks = computed(() => {
    const contract = selectedTemplate.value?.content_contract;

    if (Array.isArray(contract) && contract.length > 0) {
        return contract.map((block) => ({
            ...block,
            fields: Array.isArray(block.fields) ? block.fields : [],
        }));
    }

    return (selectedTemplate.value?.body_blocks || []).map((block) => ({
        ...block,
        fields: legacyFieldsForBlock(block.type),
    }));
});

const activePlaceholders = computed(
    () =>
        props.placeholdersByContext[selectedTemplate.value?.context_key] || [],
);

const emailTypeOptions = computed(() => [
    { value: 'custom', label: t('mail.email_types.custom', 'Custom') },
    { value: 'system', label: t('mail.email_types.system', 'System') },
]);

const recordIdLabel = computed(() => props.emailItem?.id ?? '-');
const updatedAtLabel = computed(() => formatDate(props.emailItem?.updated_at));
const createdAtLabel = computed(() => formatDate(props.emailItem?.created_at));
const mailTestDeliveryUrl = computed(() => props.mailTestDeliveryUrl || '');
const testEmailRecipient = computed(() => page.props?.auth?.user?.email || '');
const testEmailRecipientLabel = computed(() =>
    testEmailRecipient.value
        ? t('mail.test_email_recipient', 'Test recipient: :email').replace(
              ':email',
              testEmailRecipient.value,
          )
        : t(
              'mail.test_email_missing_recipient',
              'Your user account has no email address.',
          ),
);
const testEmailTitle = computed(() =>
    testEmailRecipient.value
        ? testEmailRecipientLabel.value
        : t(
              'mail.test_email_missing_recipient',
              'Your user account has no email address.',
          ),
);
const hasPreviewContent = computed(
    () => previewHtml.value !== '' || previewText.value !== '',
);
const previewContextOptions = computed(() => [
    {
        id: null,
        label: t('mail.preview_context_sample', 'Sample data'),
    },
    ...props.previewFormSubmissions,
]);
const allowedPlaceholderKeys = computed(
    () =>
        new Set(activePlaceholders.value.map((placeholder) => placeholder.key)),
);
const placeholderWarnings = computed(() => {
    const warnings = new Map();
    const fields = [
        {
            label: t('mail.fields.subject', 'Subject'),
            value: form.subject,
        },
        {
            label: t('mail.fields.preheader', 'Preheader'),
            value: form.preheader,
        },
        {
            label: t('mail.fields.plain_text', 'Plain text fallback'),
            value: form.plain_text,
        },
    ];

    selectedTemplateBlocks.value.forEach((block) => {
        block.fields.forEach((field) => {
            fields.push({
                label: `${block.label || block.key} / ${fieldLabel(field)}`,
                value: form.content_blocks?.[block.key]?.[field.name],
            });
        });
    });

    fields.forEach((field) => {
        extractPlaceholders(field.value).forEach((placeholder) => {
            if (allowedPlaceholderKeys.value.has(placeholder)) {
                return;
            }

            const key = `${field.label}:${placeholder}`;
            warnings.set(key, {
                key,
                label: field.label,
                placeholder: placeholderSyntax(placeholder),
            });
        });
    });

    return Array.from(warnings.values());
});
const contractWarnings = computed(() => {
    const warnings = [];

    selectedTemplateBlocks.value.forEach((block) => {
        block.fields.forEach((field) => {
            if (!field.required) {
                return;
            }

            const value = form.content_blocks?.[block.key]?.[field.name];

            if (!isBlankContentValue(value)) {
                return;
            }

            warnings.push({
                key: `${block.key}:${field.name}`,
                blockLabel: block.label || block.key,
                fieldLabel: fieldLabel(field),
            });
        });
    });

    return warnings;
});

const pageFlash = computed(() => {
    const flash = page.props?.flash || {};
    if (flash.error) return { type: 'danger', message: flash.error };
    if (flash.warning) return { type: 'warning', message: flash.warning };
    if (flash.status) return { type: 'success', message: flash.status };
    return { type: '', message: '' };
});

const normalizedTranslations = computed(() =>
    Array.isArray(props.translations) ? props.translations : [],
);

const normalizedMissingLanguages = computed(() =>
    Array.isArray(props.missingLanguages) ? props.missingLanguages : [],
);

const normalizedActiveLanguages = computed(() =>
    Array.isArray(props.activeLanguages) ? props.activeLanguages : [],
);

const translationsByLocale = computed(() => {
    const map = new Map();

    normalizedTranslations.value.forEach((translation) => {
        if (translation?.locale) {
            map.set(translation.locale, translation);
        }
    });

    return map;
});

const selectableLanguages = computed(() =>
    normalizedActiveLanguages.value.length > 0
        ? normalizedActiveLanguages.value
        : normalizedTranslations.value.map((translation) => ({
              locale: translation.locale,
              name: translation.locale,
              native_name: translation.locale,
          })),
);

const missingLanguageItems = computed(() =>
    normalizedMissingLanguages.value.filter(
        (language) => language.locale !== form.locale,
    ),
);

const translationStatusItems = computed(() => {
    const items = [];
    const missingLocaleSet = new Set(
        missingLanguageItems.value.map((language) => language.locale),
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

watch(
    () => form.email_type,
    (type) => {
        if (type !== 'system') {
            form.system_key = null;
        }
    },
);

watch(selectedPreviewFormSubmissionId, () => {
    if (previewDialogOpen.value && !previewLoading.value) {
        loadPreview();
    }
});

function contentBlock(key) {
    if (!form.content_blocks[key]) {
        form.content_blocks[key] = {};
    }

    return form.content_blocks[key];
}

function placeholderSyntax(key) {
    return `{{ ${key} }}`;
}

function extractPlaceholders(value) {
    if (typeof value !== 'string' || value === '') {
        return [];
    }

    const matches = value.matchAll(/{{\s*([^{}]+?)\s*}}/g);

    return Array.from(matches)
        .map((match) => match[1]?.trim())
        .filter(Boolean);
}

function isBlankContentValue(value) {
    if (Array.isArray(value)) {
        return value.length === 0;
    }

    return value === null || value === undefined || String(value).trim() === '';
}

function legacyFieldsForBlock(type) {
    if (type === 'button') {
        return [
            { name: 'label', type: 'text', required: true },
            { name: 'url', type: 'text', required: false },
        ];
    }

    if (['divider', 'spacer', 'form_answers'].includes(type)) {
        return [];
    }

    return [{ name: 'text', type: 'textarea', required: true }];
}

function fieldLabel(field) {
    return t(`mail.fields.${field.name}`, field.name.replaceAll('_', ' '));
}

async function copyPlaceholder(key) {
    const value = placeholderSyntax(key);

    if (navigator?.clipboard?.writeText) {
        await navigator.clipboard.writeText(value);
    }
}

function pastePlaceholder(key) {
    const target = activeTextTarget.value;
    const value = placeholderSyntax(key);

    if (!target || typeof target.selectionStart !== 'number') {
        return;
    }

    const start = target.selectionStart;
    const end = target.selectionEnd;
    target.setRangeText(value, start, end, 'end');
    target.dispatchEvent(new Event('input', { bubbles: true }));
    target.focus();
}

async function loadPreview() {
    previewDialogOpen.value = true;
    previewHtml.value = '';
    previewText.value = '';
    previewMode.value = 'html';
    previewError.value = '';
    previewLoading.value = true;

    try {
        const previewRouteParams = { id: props.emailItem.id };

        if (selectedPreviewFormSubmissionId.value) {
            previewRouteParams.form_submission_id =
                selectedPreviewFormSubmissionId.value;
        }

        const response = await fetch(
            route('admin.cms.emails.preview', previewRouteParams),
            { headers: { Accept: 'application/json' } },
        );

        if (!response.ok) {
            throw new Error('Preview request failed');
        }

        const data = await response.json();
        previewHtml.value = data.html || '';
        previewText.value = data.text || '';
        previewMode.value = previewHtml.value ? 'html' : 'text';
    } catch {
        previewError.value = t(
            'mail.preview_failed',
            'The email preview could not be loaded.',
        );
    } finally {
        previewLoading.value = false;
    }
}

function previewTabClass(mode) {
    if (previewMode.value === mode) {
        return 'border-blue-600 bg-blue-50 text-blue-700';
    }

    return 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-900';
}

function submit() {
    const id = props.emailItem?.id || 0;
    form.post(route('admin.cms.emails.store', { id }), {
        preserveScroll: true,
    });
}

function sendTestEmail() {
    router.post(
        route('admin.cms.emails.test-send', { id: props.emailItem.id }),
        {},
        { preserveScroll: true },
    );
}

function handleTranslationChipClick(item) {
    if (item.type === 'translation' && item.id) {
        router.visit(route('admin.cms.emails.edit', { id: item.id }));

        return;
    }

    if (item.type === 'missing' && item.locale && props.emailItem?.id) {
        router.post(
            route('admin.cms.emails.translations.store', {
                id: props.emailItem.id,
            }),
            { target_locale: item.locale },
            { preserveScroll: true },
        );
    }
}

function translationChipTitle(item) {
    if (item.type === 'translation') {
        return t('content_form.open', 'Open');
    }

    if (item.type === 'missing') {
        return t('mail.create_translation', 'Create translation');
    }

    return t('content_form.current', 'Current');
}

function translationStatusClass(item) {
    const currentClass = item?.isCurrent
        ? ' ring-2 ring-blue-500 ring-offset-1'
        : '';
    const unavailableClass = item?.type === 'current' ? ' cursor-default' : '';

    if (item?.status === 'success') {
        return `border-green-200 bg-green-50 text-green-800${currentClass}${unavailableClass}`;
    }

    if (item?.status === 'warning') {
        return `border-orange-200 bg-orange-50 text-orange-800${currentClass}${unavailableClass}`;
    }

    return `border-red-200 bg-red-50 text-red-800${currentClass}${unavailableClass}`;
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

function formatDate(value) {
    if (!value) return '-';
    return new Intl.DateTimeFormat(locale.value).format(new Date(value));
}
</script>
