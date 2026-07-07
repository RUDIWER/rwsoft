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
                                <span class="mdi mdi-form-select text-2xl" />
                            </div>
                            <div class="min-w-0">
                                <CardTitle class="text-lg">
                                    {{ pageTitle }}
                                </CardTitle>
                                <CardDescription class="mt-1">
                                    {{
                                        t(
                                            'forms.form.basic_description',
                                            'Basisgegevens, vertalingen en velden van het formulier.',
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
                            type="form"
                            :record-id="formItem.id"
                            :review="formItem.ai_translation_review"
                        />

                        <div
                            v-if="isSystemForm"
                            class="rounded-md border border-orange-200 bg-orange-50 px-3 py-2 text-sm text-orange-900"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="mdi mdi-shield-lock-outline text-base"
                                    aria-hidden="true"
                                />
                                <span class="font-semibold">
                                    {{ t('forms.system.badge', 'System form') }}
                                </span>
                                <span class="text-orange-800">
                                    {{ systemFormLabel }}
                                </span>
                            </div>
                            <p class="mt-1 text-orange-800">
                                {{
                                    t(
                                        'forms.system.locked_description',
                                        'Required account fields are protected because they are handled by secure public account flows.',
                                    )
                                }}
                            </p>
                        </div>

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
                                            'forms.form.save_before_translations',
                                            'Bewaar het formulier eerst om vertalingen te beheren.',
                                        )
                                    }}
                                </p>
                            </div>

                            <div class="grid gap-4">
                                <div
                                    class="grid items-start gap-3 md:grid-cols-[minmax(0,28rem)_auto]"
                                >
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
                                                t('content_form.title', 'Titel')
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
                                            :value="form.title"
                                        />
                                    </div>

                                    <label
                                        class="flex items-center gap-2 pt-8 text-sm font-medium text-slate-700 md:pt-8"
                                    >
                                        <input
                                            v-model="form.is_active"
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-slate-300"
                                        />
                                        {{
                                            t('common.columns.active', 'Actief')
                                        }}
                                    </label>
                                </div>

                                <div class="grid gap-2">
                                    <Label for="description">{{
                                        t(
                                            'common.columns.description',
                                            'Omschrijving',
                                        )
                                    }}</Label>
                                    <textarea
                                        id="description"
                                        v-model="form.description"
                                        rows="4"
                                        class="min-h-24 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        @blur="touchAndClear('description')"
                                    ></textarea>
                                    <FieldValidationMessage
                                        :message="
                                            validationMessage('description')
                                        "
                                        :value="form.description"
                                    />
                                </div>

                                <section
                                    class="grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-3"
                                >
                                    <h2
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        {{
                                            t(
                                                'forms.form.settings',
                                                'Instellingen',
                                            )
                                        }}
                                    </h2>

                                    <div class="grid gap-4 md:grid-cols-2">
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
                                                        'Taal',
                                                    )
                                                }}
                                            </Label>
                                            <Input
                                                id="locale"
                                                v-model="form.locale"
                                                required
                                                class="bg-yellow-50"
                                                @blur="touchAndClear('locale')"
                                            />
                                            <FieldValidationMessage
                                                :message="
                                                    validationMessage('locale')
                                                "
                                                :value="form.locale"
                                            />
                                        </div>

                                        <div class="grid gap-2">
                                            <Label for="notification_email">{{
                                                t(
                                                    'forms.form.notification_email',
                                                    'Notificatie e-mail',
                                                )
                                            }}</Label>
                                            <Input
                                                id="notification_email"
                                                v-model="
                                                    form.notification_email
                                                "
                                                type="email"
                                                @blur="
                                                    touchAndClear(
                                                        'notification_email',
                                                    )
                                                "
                                            />
                                            <FieldValidationMessage
                                                :message="
                                                    validationMessage(
                                                        'notification_email',
                                                    )
                                                "
                                                :value="form.notification_email"
                                            />
                                        </div>

                                        <div class="grid gap-2">
                                            <Label for="submit_button_label">{{
                                                t(
                                                    'forms.form.submit_button_label',
                                                    'Knoplabel',
                                                )
                                            }}</Label>
                                            <Input
                                                id="submit_button_label"
                                                v-model="
                                                    form.submit_button_label
                                                "
                                                :placeholder="
                                                    t(
                                                        'forms.form.submit_button_placeholder',
                                                        'Verzenden',
                                                    )
                                                "
                                                @blur="
                                                    touchAndClear(
                                                        'submit_button_label',
                                                    )
                                                "
                                            />
                                            <FieldValidationMessage
                                                :message="
                                                    validationMessage(
                                                        'submit_button_label',
                                                    )
                                                "
                                                :value="
                                                    form.submit_button_label
                                                "
                                            />
                                        </div>
                                    </div>
                                </section>

                                <div class="grid gap-2">
                                    <Label for="success_message">{{
                                        t(
                                            'forms.form.success_message',
                                            'Succesmelding',
                                        )
                                    }}</Label>
                                    <textarea
                                        id="success_message"
                                        v-model="form.success_message"
                                        rows="3"
                                        class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        @blur="touchAndClear('success_message')"
                                    ></textarea>
                                    <FieldValidationMessage
                                        :message="
                                            validationMessage('success_message')
                                        "
                                        :value="form.success_message"
                                    />
                                </div>
                            </div>
                        </section>

                        <section
                            id="mail"
                            v-if="activeTab === 'mail'"
                            class="space-y-5"
                        >
                            <div>
                                <h2
                                    class="text-base font-semibold text-slate-900"
                                >
                                    {{
                                        t(
                                            'forms.form.mail_title',
                                            'Mail after submission',
                                        )
                                    }}
                                </h2>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{
                                        t(
                                            'forms.form.mail_description',
                                            'Send an additional CMS email to the person who submitted the form. The existing admin notification stays available separately.',
                                        )
                                    }}
                                </p>
                            </div>

                            <div
                                v-if="!isEditMode"
                                class="rounded-md border border-orange-200 bg-orange-50 px-3 py-2 text-sm text-orange-900"
                            >
                                {{
                                    t(
                                        'forms.form.mail_save_first',
                                        'Save the form first before linking mail recipients to form fields.',
                                    )
                                }}
                            </div>

                            <div class="grid gap-5">
                                <label
                                    id="submission_email_enabled"
                                    class="flex items-center gap-2 text-sm font-medium text-slate-700"
                                >
                                    <input
                                        v-model="form.submission_email_enabled"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-slate-300"
                                        :disabled="!isEditMode"
                                    />
                                    {{
                                        t(
                                            'forms.form.submission_email_enabled',
                                            'Send email after submit',
                                        )
                                    }}
                                </label>

                                <section
                                    class="grid gap-4 rounded-md border border-slate-200 bg-slate-50 p-3"
                                >
                                    <div>
                                        <h3
                                            class="text-sm font-semibold text-slate-900"
                                        >
                                            {{
                                                t(
                                                    'forms.form.submission_email_settings',
                                                    'Email delivery settings',
                                                )
                                            }}
                                        </h3>
                                        <p class="mt-1 text-sm text-slate-600">
                                            {{
                                                t(
                                                    'forms.form.submission_email_settings_help',
                                                    'Choose an active CMS email for this form language and link the TO recipient to an email field in the form.',
                                                )
                                            }}
                                        </p>
                                    </div>

                                    <div class="grid gap-4 md:grid-cols-2">
                                        <div
                                            id="submission_cms_email_id"
                                            class="grid gap-2"
                                        >
                                            <Label
                                                class="flex items-center gap-1"
                                            >
                                                <span
                                                    v-if="
                                                        form.submission_email_enabled
                                                    "
                                                    class="text-red-600"
                                                    aria-hidden="true"
                                                    >*</span
                                                >
                                                {{
                                                    t(
                                                        'forms.form.submission_email',
                                                        'Email template',
                                                    )
                                                }}
                                            </Label>
                                            <RwAutoCompleteInput
                                                v-model="
                                                    form.submission_cms_email_id
                                                "
                                                :items="
                                                    filteredSubmissionEmailOptions
                                                "
                                                item-title="label"
                                                item-value="id"
                                                :search-fields="[
                                                    'title',
                                                    'locale',
                                                    'label',
                                                ]"
                                                :class="
                                                    form.submission_email_enabled
                                                        ? 'bg-yellow-50'
                                                        : ''
                                                "
                                                required-highlight-color="#fefce8"
                                                :disabled="!isEditMode"
                                            />
                                            <p
                                                v-if="
                                                    filteredSubmissionEmailOptions.length ===
                                                    0
                                                "
                                                class="text-xs text-orange-700"
                                            >
                                                {{
                                                    t(
                                                        'forms.form.submission_email_empty',
                                                        'No active form-submission email is available for this language.',
                                                    )
                                                }}
                                            </p>
                                            <FieldValidationMessage
                                                :message="
                                                    validationMessage(
                                                        'submission_cms_email_id',
                                                    )
                                                "
                                                :value="
                                                    form.submission_cms_email_id
                                                "
                                            />
                                        </div>

                                        <div
                                            id="submission_to_cms_form_field_id"
                                            class="grid gap-2"
                                        >
                                            <Label
                                                class="flex items-center gap-1"
                                            >
                                                <span
                                                    v-if="
                                                        form.submission_email_enabled
                                                    "
                                                    class="text-red-600"
                                                    aria-hidden="true"
                                                    >*</span
                                                >
                                                {{
                                                    t(
                                                        'forms.form.submission_to_field',
                                                        'Recipient email field',
                                                    )
                                                }}
                                            </Label>
                                            <RwAutoCompleteInput
                                                v-model="
                                                    form.submission_to_cms_form_field_id
                                                "
                                                :items="emailFieldOptions"
                                                item-title="label"
                                                item-value="id"
                                                :search-fields="[
                                                    'label',
                                                    'translation_key',
                                                ]"
                                                :class="
                                                    form.submission_email_enabled
                                                        ? 'bg-yellow-50'
                                                        : ''
                                                "
                                                required-highlight-color="#fefce8"
                                                :disabled="!isEditMode"
                                            />
                                            <p
                                                v-if="
                                                    emailFieldOptions.length ===
                                                    0
                                                "
                                                class="text-xs text-orange-700"
                                            >
                                                {{
                                                    t(
                                                        'forms.form.submission_email_fields_empty',
                                                        'Add and save at least one active email field before enabling this mail.',
                                                    )
                                                }}
                                            </p>
                                            <FieldValidationMessage
                                                :message="
                                                    validationMessage(
                                                        'submission_to_cms_form_field_id',
                                                    )
                                                "
                                                :value="
                                                    form.submission_to_cms_form_field_id
                                                "
                                            />
                                        </div>
                                    </div>
                                </section>

                                <section
                                    id="submission_cc_recipients"
                                    class="grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-3"
                                >
                                    <div
                                        class="flex flex-wrap items-start justify-between gap-3"
                                    >
                                        <div>
                                            <h3
                                                class="text-sm font-semibold text-slate-900"
                                            >
                                                {{
                                                    t(
                                                        'forms.form.submission_cc_recipients',
                                                        'CC recipients',
                                                    )
                                                }}
                                            </h3>
                                            <p
                                                class="mt-1 text-sm text-slate-600"
                                            >
                                                {{
                                                    t(
                                                        'forms.form.recipient_rows_help',
                                                        'Use fixed email addresses or link recipients to email fields in this form.',
                                                    )
                                                }}
                                            </p>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            class="shadow-none"
                                            :disabled="!isEditMode"
                                            @click="
                                                addRecipientRow(
                                                    'submission_cc_recipients',
                                                )
                                            "
                                        >
                                            {{
                                                t(
                                                    'forms.form.add_recipient',
                                                    'Add recipient',
                                                )
                                            }}
                                        </Button>
                                    </div>

                                    <div class="grid gap-3">
                                        <div
                                            v-for="(
                                                row, index
                                            ) in form.submission_cc_recipients"
                                            :key="`cc-${index}`"
                                            class="grid gap-3 rounded border border-slate-200 bg-white p-3 md:grid-cols-[12rem_minmax(0,1fr)_auto]"
                                        >
                                            <RwAutoCompleteInput
                                                v-model="row.type"
                                                :items="recipientTypeOptions"
                                                item-title="label"
                                                item-value="value"
                                                :search-fields="[
                                                    'label',
                                                    'value',
                                                ]"
                                                :disabled="!isEditMode"
                                            />
                                            <div class="grid gap-1">
                                                <Input
                                                    v-if="row.type === 'static'"
                                                    v-model="row.email"
                                                    type="email"
                                                    :placeholder="
                                                        t(
                                                            'forms.form.recipient_email_placeholder',
                                                            'name@example.com',
                                                        )
                                                    "
                                                    :disabled="!isEditMode"
                                                />
                                                <RwAutoCompleteInput
                                                    v-else
                                                    v-model="row.field_id"
                                                    :items="emailFieldOptions"
                                                    item-title="label"
                                                    item-value="id"
                                                    :search-fields="[
                                                        'label',
                                                        'translation_key',
                                                    ]"
                                                    :disabled="!isEditMode"
                                                />
                                                <p
                                                    v-if="
                                                        recipientRowError(
                                                            'submission_cc_recipients',
                                                            index,
                                                            row.type ===
                                                                'static'
                                                                ? 'email'
                                                                : 'field_id',
                                                        )
                                                    "
                                                    class="text-sm text-red-600"
                                                >
                                                    {{
                                                        recipientRowError(
                                                            'submission_cc_recipients',
                                                            index,
                                                            row.type ===
                                                                'static'
                                                                ? 'email'
                                                                : 'field_id',
                                                        )
                                                    }}
                                                </p>
                                            </div>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon"
                                                class="h-10 w-10 text-slate-700 shadow-none hover:bg-slate-50"
                                                :title="
                                                    t(
                                                        'forms.form.remove_recipient',
                                                        'Remove recipient',
                                                    )
                                                "
                                                :aria-label="
                                                    t(
                                                        'forms.form.remove_recipient',
                                                        'Remove recipient',
                                                    )
                                                "
                                                :disabled="!isEditMode"
                                                @click="
                                                    removeRecipientRow(
                                                        'submission_cc_recipients',
                                                        index,
                                                    )
                                                "
                                            >
                                                <span
                                                    class="mdi mdi-trash-can-outline text-base"
                                                    aria-hidden="true"
                                                />
                                            </Button>
                                        </div>
                                    </div>
                                </section>

                                <section
                                    id="submission_bcc_recipients"
                                    class="grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-3"
                                >
                                    <div
                                        class="flex flex-wrap items-start justify-between gap-3"
                                    >
                                        <div>
                                            <h3
                                                class="text-sm font-semibold text-slate-900"
                                            >
                                                {{
                                                    t(
                                                        'forms.form.submission_bcc_recipients',
                                                        'BCC recipients',
                                                    )
                                                }}
                                            </h3>
                                            <p
                                                class="mt-1 text-sm text-slate-600"
                                            >
                                                {{
                                                    t(
                                                        'forms.form.recipient_rows_help',
                                                        'Use fixed email addresses or link recipients to email fields in this form.',
                                                    )
                                                }}
                                            </p>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            class="shadow-none"
                                            :disabled="!isEditMode"
                                            @click="
                                                addRecipientRow(
                                                    'submission_bcc_recipients',
                                                )
                                            "
                                        >
                                            {{
                                                t(
                                                    'forms.form.add_recipient',
                                                    'Add recipient',
                                                )
                                            }}
                                        </Button>
                                    </div>

                                    <div class="grid gap-3">
                                        <div
                                            v-for="(
                                                row, index
                                            ) in form.submission_bcc_recipients"
                                            :key="`bcc-${index}`"
                                            class="grid gap-3 rounded border border-slate-200 bg-white p-3 md:grid-cols-[12rem_minmax(0,1fr)_auto]"
                                        >
                                            <RwAutoCompleteInput
                                                v-model="row.type"
                                                :items="recipientTypeOptions"
                                                item-title="label"
                                                item-value="value"
                                                :search-fields="[
                                                    'label',
                                                    'value',
                                                ]"
                                                :disabled="!isEditMode"
                                            />
                                            <div class="grid gap-1">
                                                <Input
                                                    v-if="row.type === 'static'"
                                                    v-model="row.email"
                                                    type="email"
                                                    :placeholder="
                                                        t(
                                                            'forms.form.recipient_email_placeholder',
                                                            'name@example.com',
                                                        )
                                                    "
                                                    :disabled="!isEditMode"
                                                />
                                                <RwAutoCompleteInput
                                                    v-else
                                                    v-model="row.field_id"
                                                    :items="emailFieldOptions"
                                                    item-title="label"
                                                    item-value="id"
                                                    :search-fields="[
                                                        'label',
                                                        'translation_key',
                                                    ]"
                                                    :disabled="!isEditMode"
                                                />
                                                <p
                                                    v-if="
                                                        recipientRowError(
                                                            'submission_bcc_recipients',
                                                            index,
                                                            row.type ===
                                                                'static'
                                                                ? 'email'
                                                                : 'field_id',
                                                        )
                                                    "
                                                    class="text-sm text-red-600"
                                                >
                                                    {{
                                                        recipientRowError(
                                                            'submission_bcc_recipients',
                                                            index,
                                                            row.type ===
                                                                'static'
                                                                ? 'email'
                                                                : 'field_id',
                                                        )
                                                    }}
                                                </p>
                                            </div>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon"
                                                class="h-10 w-10 text-slate-700 shadow-none hover:bg-slate-50"
                                                :title="
                                                    t(
                                                        'forms.form.remove_recipient',
                                                        'Remove recipient',
                                                    )
                                                "
                                                :aria-label="
                                                    t(
                                                        'forms.form.remove_recipient',
                                                        'Remove recipient',
                                                    )
                                                "
                                                :disabled="!isEditMode"
                                                @click="
                                                    removeRecipientRow(
                                                        'submission_bcc_recipients',
                                                        index,
                                                    )
                                                "
                                            >
                                                <span
                                                    class="mdi mdi-trash-can-outline text-base"
                                                    aria-hidden="true"
                                                />
                                            </Button>
                                        </div>
                                    </div>
                                </section>
                            </div>
                        </section>

                        <section
                            id="fields"
                            v-if="activeTab === 'fields'"
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
                                                'forms.form.fields_title',
                                                'Velden',
                                            )
                                        }}
                                    </h2>
                                    <p class="mt-1 text-sm text-slate-500">
                                        {{
                                            t(
                                                'forms.form.fields_description',
                                                'Voeg formulierregels toe. Verwijderen gebeurt door een veld inactief te zetten.',
                                            )
                                        }}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="shadow-none"
                                    :disabled="isSystemForm"
                                    :title="
                                        isSystemForm
                                            ? t(
                                                  'forms.system.add_field_disabled',
                                                  'System forms cannot receive custom fields here.',
                                              )
                                            : undefined
                                    "
                                    @click="addField"
                                >
                                    {{
                                        t(
                                            'forms.form.add_field',
                                            'Veld toevoegen',
                                        )
                                    }}
                                </Button>
                            </div>

                            <div class="grid gap-4">
                                <div
                                    v-for="(field, index) in form.fields"
                                    :key="field.uid"
                                    data-drag-preview-row="true"
                                    class="grid gap-4 rounded-xl border border-slate-300 bg-slate-50 p-4 transition"
                                    :class="
                                        dragOverFieldUid === field.uid
                                            ? 'border-blue-300 bg-blue-50/60'
                                            : ''
                                    "
                                    @dragenter.prevent="
                                        onFieldDragOver(field.uid, $event)
                                    "
                                    @dragover.prevent="
                                        onFieldDragOver(field.uid, $event)
                                    "
                                    @drop.prevent="
                                        onFieldDrop(field.uid, $event)
                                    "
                                >
                                    <div
                                        class="flex flex-wrap items-center justify-between gap-2"
                                    >
                                        <div
                                            class="flex flex-wrap items-center gap-4"
                                        >
                                            <div
                                                class="font-medium text-slate-900"
                                            >
                                                <button
                                                    type="button"
                                                    class="field-drag-handle mr-2 inline-flex h-8 w-8 cursor-grab items-center justify-center rounded-md border border-slate-300 bg-white text-slate-600 shadow-none transition hover:bg-slate-100 active:cursor-grabbing"
                                                    draggable="true"
                                                    :title="
                                                        t(
                                                            'forms.form.drag_field',
                                                            'Drag to reorder',
                                                        )
                                                    "
                                                    :aria-label="
                                                        t(
                                                            'forms.form.drag_field',
                                                            'Drag to reorder',
                                                        )
                                                    "
                                                    @dragstart="
                                                        onFieldDragStart(
                                                            field.uid,
                                                            $event,
                                                        )
                                                    "
                                                    @dragend="onFieldDragEnd"
                                                >
                                                    <span
                                                        class="mdi mdi-drag-vertical text-xl"
                                                        aria-hidden="true"
                                                    />
                                                </button>
                                                {{
                                                    t(
                                                        'forms.form.field_number',
                                                        'Veld :number',
                                                        {
                                                            number: index + 1,
                                                        },
                                                    )
                                                }}
                                                <span
                                                    v-if="
                                                        field.is_system_locked
                                                    "
                                                    class="ml-2 inline-flex items-center rounded-full border border-orange-200 bg-orange-50 px-2 py-0.5 text-xs font-semibold text-orange-800"
                                                >
                                                    {{
                                                        t(
                                                            'forms.system.locked_field_badge',
                                                            'Locked',
                                                        )
                                                    }}
                                                </span>
                                            </div>
                                            <label
                                                class="flex items-center gap-2 text-sm font-medium text-slate-700"
                                            >
                                                <input
                                                    v-model="field.is_active"
                                                    type="checkbox"
                                                    class="h-4 w-4 rounded border-slate-300"
                                                    :disabled="
                                                        field.is_system_locked
                                                    "
                                                />
                                                {{
                                                    t(
                                                        'common.columns.active',
                                                        'Actief',
                                                    )
                                                }}
                                            </label>
                                            <label
                                                class="flex items-center gap-2 text-sm font-medium text-slate-700"
                                            >
                                                <input
                                                    v-model="field.is_required"
                                                    type="checkbox"
                                                    class="h-4 w-4 rounded border-slate-300"
                                                    :disabled="
                                                        field.is_system_locked
                                                    "
                                                />
                                                {{
                                                    t(
                                                        'forms.form.required',
                                                        'Verplicht',
                                                    )
                                                }}
                                            </label>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon"
                                                class="h-8 w-8 border-slate-300 bg-white text-slate-700 shadow-none hover:bg-slate-100"
                                                :disabled="index === 0"
                                                :title="
                                                    t(
                                                        'forms.form.up',
                                                        'Move up',
                                                    )
                                                "
                                                :aria-label="
                                                    t(
                                                        'forms.form.up',
                                                        'Move up',
                                                    )
                                                "
                                                @click="moveField(index, -1)"
                                            >
                                                <span
                                                    class="mdi mdi-chevron-up text-xl"
                                                    aria-hidden="true"
                                                />
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon"
                                                class="h-8 w-8 border-slate-300 bg-white text-slate-700 shadow-none hover:bg-slate-100"
                                                :disabled="
                                                    index ===
                                                    form.fields.length - 1
                                                "
                                                :title="
                                                    t(
                                                        'forms.form.down',
                                                        'Move down',
                                                    )
                                                "
                                                :aria-label="
                                                    t(
                                                        'forms.form.down',
                                                        'Move down',
                                                    )
                                                "
                                                @click="moveField(index, 1)"
                                            >
                                                <span
                                                    class="mdi mdi-chevron-down text-xl"
                                                    aria-hidden="true"
                                                />
                                            </Button>
                                        </div>
                                    </div>

                                    <div
                                        class="grid grid-cols-1 gap-4 md:grid-cols-2"
                                    >
                                        <div class="grid gap-2">
                                            <Label
                                                :for="
                                                    formFieldElementId(
                                                        index,
                                                        'label',
                                                    )
                                                "
                                                class="flex items-center gap-1"
                                            >
                                                <span
                                                    class="text-red-600"
                                                    aria-hidden="true"
                                                    >*</span
                                                >
                                                {{
                                                    t(
                                                        'forms.form.label',
                                                        'Label',
                                                    )
                                                }}
                                            </Label>
                                            <Input
                                                :id="
                                                    formFieldElementId(
                                                        index,
                                                        'label',
                                                    )
                                                "
                                                v-model="field.label"
                                                required
                                                class="bg-yellow-50"
                                            />
                                            <FieldValidationMessage
                                                :message="
                                                    fieldRowError(
                                                        index,
                                                        'label',
                                                    )
                                                "
                                                :value="field.label"
                                            />
                                        </div>
                                        <div class="grid gap-2">
                                            <Label
                                                :for="
                                                    formFieldElementId(
                                                        index,
                                                        'type',
                                                    )
                                                "
                                                >{{
                                                    t(
                                                        'common.columns.type',
                                                        'Type',
                                                    )
                                                }}</Label
                                            >
                                            <select
                                                :id="
                                                    formFieldElementId(
                                                        index,
                                                        'type',
                                                    )
                                                "
                                                v-model="field.type"
                                                :disabled="
                                                    field.is_system_locked
                                                "
                                                class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                            >
                                                <option
                                                    v-for="option in fieldTypeOptions"
                                                    :key="option.value"
                                                    :value="option.value"
                                                >
                                                    {{ option.label }}
                                                </option>
                                            </select>
                                            <FieldValidationMessage
                                                :message="
                                                    fieldRowError(index, 'type')
                                                "
                                                :value="field.type"
                                            />
                                        </div>
                                        <div class="grid gap-2">
                                            <Label
                                                :for="
                                                    formFieldElementId(
                                                        index,
                                                        'width',
                                                    )
                                                "
                                                >{{
                                                    t(
                                                        'forms.form.width',
                                                        'Breedte',
                                                    )
                                                }}</Label
                                            >
                                            <select
                                                :id="
                                                    formFieldElementId(
                                                        index,
                                                        'width',
                                                    )
                                                "
                                                v-model="field.width"
                                                class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                            >
                                                <option
                                                    v-for="option in widthOptions"
                                                    :key="option.value"
                                                    :value="option.value"
                                                >
                                                    {{ option.label }}
                                                </option>
                                            </select>
                                            <FieldValidationMessage
                                                :message="
                                                    fieldRowError(
                                                        index,
                                                        'width',
                                                    )
                                                "
                                                :value="field.width"
                                            />
                                        </div>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label
                                            :for="
                                                formFieldElementId(
                                                    index,
                                                    'placeholder',
                                                )
                                            "
                                            >{{
                                                t(
                                                    'forms.form.placeholder',
                                                    'Placeholder',
                                                )
                                            }}</Label
                                        >
                                        <Input
                                            :id="
                                                formFieldElementId(
                                                    index,
                                                    'placeholder',
                                                )
                                            "
                                            v-model="field.placeholder"
                                        />
                                        <FieldValidationMessage
                                            :message="
                                                fieldRowError(
                                                    index,
                                                    'placeholder',
                                                )
                                            "
                                            :value="field.placeholder"
                                        />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label
                                            :for="
                                                formFieldElementId(
                                                    index,
                                                    'help_text',
                                                )
                                            "
                                            >{{
                                                t(
                                                    'forms.form.help_text',
                                                    'Helptekst',
                                                )
                                            }}</Label
                                        >
                                        <Input
                                            :id="
                                                formFieldElementId(
                                                    index,
                                                    'help_text',
                                                )
                                            "
                                            v-model="field.help_text"
                                        />
                                        <FieldValidationMessage
                                            :message="
                                                fieldRowError(
                                                    index,
                                                    'help_text',
                                                )
                                            "
                                            :value="field.help_text"
                                        />
                                    </div>

                                    <div
                                        v-if="hasChoiceOptions(field.type)"
                                        class="grid gap-2"
                                    >
                                        <Label>{{
                                            t('forms.form.options', 'Opties')
                                        }}</Label>
                                        <textarea
                                            :id="
                                                formFieldElementId(
                                                    index,
                                                    'options',
                                                )
                                            "
                                            v-model="field.options_text"
                                            :disabled="field.is_system_locked"
                                            rows="4"
                                            class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                            :placeholder="
                                                t(
                                                    'forms.form.options_placeholder',
                                                    'option_key=Label per regel',
                                                )
                                            "
                                        ></textarea>
                                        <p class="text-xs text-slate-500">
                                            {{
                                                t(
                                                    'forms.form.options_help',
                                                    'Gebruik stabiele technische keys, bijvoorbeeld yes=Ja.',
                                                )
                                            }}
                                        </p>
                                        <FieldValidationMessage
                                            :message="
                                                fieldRowError(index, 'options')
                                            "
                                            :value="field.options_text"
                                        />
                                    </div>
                                </div>

                                <div
                                    v-if="form.fields.length === 0"
                                    class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-5 text-sm text-slate-500"
                                >
                                    {{
                                        t(
                                            'forms.form.empty_fields',
                                            'Nog geen velden. Voeg minstens een veld toe voor publiek gebruik.',
                                        )
                                    }}
                                </div>

                                <p
                                    v-if="form.errors.fields"
                                    class="text-sm text-red-600"
                                >
                                    {{ form.errors.fields }}
                                </p>
                            </div>
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
                                'forms.form.translation_dialog_description',
                                'Kies of het formulier met AI vertaald wordt of eerst als kopie wordt aangemaakt.',
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
                                    'forms.form.ai_help',
                                    'Met AI vertalen maakt direct een vertaalde inactieve formulierkopie.',
                                )
                            }}
                        </p>
                        <p>
                            {{
                                t(
                                    'forms.form.copy_help',
                                    'Origineel kopieren maakt een inactieve kopie met dezelfde teksten.',
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
                        {{ t('content_form.translate_ai', 'Met AI vertalen') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <CmsRevisionHistoryDialog
            v-if="isEditMode"
            v-model:open="showRevisionDialog"
            subject-type="form"
            restore-route-name="admin.cms.forms.revisions.restore"
            :restore-route-params="{ form: formItem.id }"
            :revisions="revisions"
        />
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RwFlashMessage from '@/Components/RwFlashMessage.vue';
import RwAutoCompleteInput from '@/Components/RwAutoCompleteInput.vue';
import AdminFormBackButton from '@/Components/Admin/Form/AdminFormBackButton.vue';
import AdminFormSaveButton from '@/Components/Admin/Form/AdminFormSaveButton.vue';
import CmsRevisionHistoryDialog from '@/Pages/Admin/Cms/Components/CmsRevisionHistoryDialog.vue';
import AiTranslationReviewBanner from '@/Pages/Admin/Cms/Partials/AiTranslationReviewBanner.vue';
import { resolveReturnToUrl } from '@/composables/useReturnToUrl';
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
import { computed, nextTick, ref } from 'vue';

const { t } = useAdminTranslations('cms_admin_ui');
const { t: commonT } = useAdminTranslations('admin_common_ui');
const page = usePage();

const props = defineProps({
    formItem: { type: Object, default: null },
    translations: { type: Array, default: () => [] },
    revisions: { type: Array, default: () => [] },
    missingLanguages: { type: Array, default: () => [] },
    activeLanguages: { type: Array, default: () => [] },
    availableLocales: { type: Array, default: () => [] },
    multilingualEnabled: { type: Boolean, default: true },
    fieldTypeOptions: { type: Array, required: true },
    widthOptions: { type: Array, required: true },
    submissionEmailOptions: { type: Array, default: () => [] },
});

const form = useForm({
    title: props.formItem?.title ?? '',
    locale: props.formItem?.locale ?? 'nl',
    description: props.formItem?.description ?? '',
    notification_email: props.formItem?.notification_email ?? '',
    submission_email_enabled: Boolean(
        props.formItem?.submission_email_enabled ?? false,
    ),
    submission_cms_email_id: props.formItem?.submission_cms_email_id ?? null,
    submission_to_cms_form_field_id:
        props.formItem?.submission_to_cms_form_field_id ?? null,
    submission_cc_recipients: normalizeRecipientRows(
        props.formItem?.submission_cc_recipients ?? [],
    ),
    submission_bcc_recipients: normalizeRecipientRows(
        props.formItem?.submission_bcc_recipients ?? [],
    ),
    submit_button_label:
        props.formItem?.submit_button_label ??
        t('forms.form.default_submit_button', 'Verzenden'),
    success_message:
        props.formItem?.success_message ??
        t(
            'forms.form.default_success_message',
            'Bedankt. Je formulier is verzonden.',
        ),
    is_active: Boolean(props.formItem?.is_active ?? true),
    fields: (props.formItem?.fields ?? []).map((field) =>
        normalizeField(field),
    ),
});

const isEditMode = computed(() => Boolean(props.formItem?.id));
const isSystemForm = computed(() => props.formItem?.form_kind === 'system');
const systemFormLabel = computed(() => props.formItem?.system_key || '');
const recordIdLabel = computed(() => props.formItem?.id ?? '-');
const currentRevision = computed(() => props.revisions[0] ?? null);
const currentRevisionLabel = computed(() => {
    const revisionNumber = Number(currentRevision.value?.revision_number ?? 0);

    if (revisionNumber <= 0) {
        return t('revisions.current_version_empty', 'Version -');
    }

    return t('revisions.current_version_number', 'Version #:number', {
        number: revisionNumber,
    });
});
const currentRevisionTitle = computed(
    () =>
        currentRevision.value?.title ||
        t('revisions.current_version_tooltip', 'Latest saved version'),
);
const updatedAtLabel = computed(() =>
    formatRecordDate(props.formItem?.updated_at),
);
const createdAtLabel = computed(() =>
    formatRecordDate(props.formItem?.created_at),
);
const pageTitle = computed(() =>
    isEditMode.value
        ? t('forms.form.edit_title', 'Formulier bewerken')
        : t('forms.form.create_title', 'Formulier toevoegen'),
);
const backHref = computed(() =>
    resolveReturnToUrl(route('admin.cms.forms.index')),
);
const activeTab = ref('basis');
const showTranslationDialog = ref(false);
const showRevisionDialog = ref(false);
const draggedFieldUid = ref(null);
const dragOverFieldUid = ref(null);
const tabOptions = computed(() => [
    { value: 'basis', label: t('forms.form.tabs.basic', 'Basis') },
    { value: 'fields', label: t('forms.form.tabs.fields', 'Velden') },
    { value: 'mail', label: t('forms.form.tabs.mail', 'Mail') },
]);
const emailFieldOptions = computed(() =>
    form.fields
        .filter(
            (field) =>
                Number(field.id || 0) > 0 &&
                field.type === 'email' &&
                Boolean(field.is_active),
        )
        .map((field) => ({
            id: Number(field.id),
            label: field.label || field.translation_key || `#${field.id}`,
            translation_key: field.translation_key,
        })),
);
const filteredSubmissionEmailOptions = computed(() =>
    props.submissionEmailOptions.filter(
        (email) => String(email.locale || '') === String(form.locale || ''),
    ),
);
const recipientTypeOptions = computed(() => [
    {
        value: 'static',
        label: t('forms.form.recipient_type_static', 'Fixed email address'),
    },
    {
        value: 'field',
        label: t('forms.form.recipient_type_field', 'Form email field'),
    },
]);
const cmsFormValidationFields = {
    title: {
        label: t('content_form.title', 'Title'),
        tab: 'basis',
        elementId: 'title',
        required: true,
        value: () => form.title,
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
                        'validation.max_chars',
                        ':field is too long (:current/:max).',
                        {
                            field: t('content_form.title', 'Title'),
                            current: String(value ?? '').length,
                            max: 255,
                        },
                    ),
                ),
        ],
    },
    locale: {
        label: t('common.columns.locale', 'Language'),
        tab: 'basis',
        elementId: 'locale',
        required: true,
        value: () => form.locale,
        rules: [
            (value) =>
                clientRules.required(
                    value,
                    t('validation.required', 'This field is required.'),
                ),
            (value) =>
                localeCode(
                    value,
                    t(
                        'validation.locale_code',
                        'Use a valid language code, for example nl or nl_BE.',
                    ),
                ),
            (value) =>
                clientRules.max(
                    12,
                    value,
                    t(
                        'validation.max_chars',
                        ':field is too long (:current/:max).',
                        {
                            field: t('common.columns.locale', 'Language'),
                            current: String(value ?? '').length,
                            max: 12,
                        },
                    ),
                ),
        ],
    },
    description: {
        label: t('common.columns.description', 'Description'),
        tab: 'basis',
        elementId: 'description',
        value: () => form.description,
        rules: [
            (value) =>
                clientRules.max(
                    5000,
                    value,
                    t(
                        'validation.max_chars',
                        ':field is too long (:current/:max).',
                        {
                            field: t(
                                'common.columns.description',
                                'Description',
                            ),
                            current: String(value ?? '').length,
                            max: 5000,
                        },
                    ),
                ),
        ],
    },
    notification_email: {
        label: t('forms.form.notification_email', 'Notification email'),
        tab: 'basis',
        elementId: 'notification_email',
        value: () => form.notification_email,
        rules: [
            (value) =>
                emailAddress(
                    value,
                    t('validation.email', 'Use a valid email address.'),
                ),
            (value) =>
                clientRules.max(
                    255,
                    value,
                    t(
                        'validation.max_chars',
                        ':field is too long (:current/:max).',
                        {
                            field: t(
                                'forms.form.notification_email',
                                'Notification email',
                            ),
                            current: String(value ?? '').length,
                            max: 255,
                        },
                    ),
                ),
        ],
    },
    submission_cms_email_id: {
        label: t('forms.form.submission_email', 'Email template'),
        tab: 'mail',
        elementId: 'submission_cms_email_id',
        required: () => form.submission_email_enabled,
        value: () => form.submission_cms_email_id,
        rules: [
            (value) =>
                !form.submission_email_enabled ||
                clientRules.required(
                    value,
                    t('validation.required', 'This field is required.'),
                ),
        ],
    },
    submission_to_cms_form_field_id: {
        label: t('forms.form.submission_to_field', 'Recipient email field'),
        tab: 'mail',
        elementId: 'submission_to_cms_form_field_id',
        required: () => form.submission_email_enabled,
        value: () => form.submission_to_cms_form_field_id,
        rules: [
            (value) =>
                !form.submission_email_enabled ||
                clientRules.required(
                    value,
                    t('validation.required', 'This field is required.'),
                ),
        ],
    },
    submit_button_label: {
        label: t('forms.form.submit_button_label', 'Button label'),
        tab: 'basis',
        elementId: 'submit_button_label',
        value: () => form.submit_button_label,
        rules: [
            (value) =>
                clientRules.max(
                    120,
                    value,
                    t(
                        'validation.max_chars',
                        ':field is too long (:current/:max).',
                        {
                            field: t(
                                'forms.form.submit_button_label',
                                'Button label',
                            ),
                            current: String(value ?? '').length,
                            max: 120,
                        },
                    ),
                ),
        ],
    },
    success_message: {
        label: t('forms.form.success_message', 'Success message'),
        tab: 'basis',
        elementId: 'success_message',
        value: () => form.success_message,
        rules: [
            (value) =>
                clientRules.max(
                    1000,
                    value,
                    t(
                        'validation.max_chars',
                        ':field is too long (:current/:max).',
                        {
                            field: t(
                                'forms.form.success_message',
                                'Success message',
                            ),
                            current: String(value ?? '').length,
                            max: 1000,
                        },
                    ),
                ),
        ],
    },
};
const cmsFormServerFields = {
    submission_email_enabled: {
        label: t(
            'forms.form.submission_email_enabled',
            'Send email after submit',
        ),
        tab: 'mail',
        elementId: 'submission_email_enabled',
    },
    submission_cms_email_id: {
        label: t('forms.form.submission_email', 'Email template'),
        tab: 'mail',
        elementId: 'submission_cms_email_id',
    },
    submission_to_cms_form_field_id: {
        label: t('forms.form.submission_to_field', 'Recipient email field'),
        tab: 'mail',
        elementId: 'submission_to_cms_form_field_id',
    },
    'submission_cc_recipients.*': {
        label: t('forms.form.submission_cc_recipients', 'CC recipients'),
        tab: 'mail',
        elementId: 'submission_cc_recipients',
    },
    'submission_bcc_recipients.*': {
        label: t('forms.form.submission_bcc_recipients', 'BCC recipients'),
        tab: 'mail',
        elementId: 'submission_bcc_recipients',
    },
    fields: {
        label: t('forms.form.fields_title', 'Fields'),
        tab: 'fields',
        elementId: 'fields',
    },
    'fields.*': {
        label: t('forms.form.fields_title', 'Fields'),
        tab: 'fields',
        elementId: 'fields',
    },
    'fields.*.label': {
        label: t('forms.form.label', 'Label'),
        tab: 'fields',
        elementId: fieldRowElementIdFromErrorName,
    },
    'fields.*.type': {
        label: t('common.columns.type', 'Type'),
        tab: 'fields',
        elementId: fieldRowElementIdFromErrorName,
    },
    'fields.*.placeholder': {
        label: t('forms.form.placeholder', 'Placeholder'),
        tab: 'fields',
        elementId: fieldRowElementIdFromErrorName,
    },
    'fields.*.help_text': {
        label: t('forms.form.help_text', 'Help text'),
        tab: 'fields',
        elementId: fieldRowElementIdFromErrorName,
    },
    'fields.*.width': {
        label: t('forms.form.width', 'Width'),
        tab: 'fields',
        elementId: fieldRowElementIdFromErrorName,
    },
    'fields.*.options': {
        label: t('forms.form.options', 'Options'),
        tab: 'fields',
        elementId: fieldRowElementIdFromErrorName,
    },
    'fields.*.options.*.key': {
        label: t('forms.form.options', 'Options'),
        tab: 'fields',
        elementId: fieldRowElementIdFromErrorName,
    },
    'fields.*.options.*.label': {
        label: t('forms.form.options', 'Options'),
        tab: 'fields',
        elementId: fieldRowElementIdFromErrorName,
    },
};
const {
    FieldValidationMessage,
    formValidation,
    message: validationMessage,
    serverValidationErrors,
    validationFlash,
    touchAndClear,
    rules: clientRules,
} = useCmsFormValidation(form, {
    fields: cmsFormValidationFields,
    serverFields: cmsFormServerFields,
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
const { validateBeforeSubmit, scrollToIssue } = formValidation;
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
const selectableLanguages = computed(() =>
    props.activeLanguages.length > 0
        ? props.activeLanguages
        : props.availableLocales.map((locale) => ({
              locale,
              name: locale,
              native_name: locale,
          })),
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
const selectedTranslationLanguageLabel = computed(() => {
    const language = missingLanguages.value.find(
        (item) => item.locale === translationForm.target_locale,
    );

    return language
        ? languageLabel(language)
        : t('content_form.no_language_selected', 'Geen taal gekozen');
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

const translationForm = useForm({
    target_locale: '',
    use_ai: true,
});
const translationAction = ref(null);

function addField() {
    if (isSystemForm.value) {
        return;
    }

    form.fields.push(
        normalizeField({
            type: 'text',
            key: '',
            label: '',
            sort_order: (form.fields.length + 1) * 10,
            is_required: false,
            is_active: true,
            width: 'full',
        }),
    );
}

function moveField(index, direction) {
    const nextIndex = index + direction;

    if (nextIndex < 0 || nextIndex >= form.fields.length) {
        return;
    }

    const [field] = form.fields.splice(index, 1);
    form.fields.splice(nextIndex, 0, field);
}

function setFieldDragPreview(event) {
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

function moveFieldToTarget(sourceUid, targetUid) {
    if (!sourceUid || !targetUid || sourceUid === targetUid) {
        return;
    }

    const fromIndex = form.fields.findIndex((field) => field.uid === sourceUid);
    const toIndex = form.fields.findIndex((field) => field.uid === targetUid);

    if (fromIndex < 0 || toIndex < 0 || fromIndex === toIndex) {
        return;
    }

    const [field] = form.fields.splice(fromIndex, 1);
    form.fields.splice(toIndex, 0, field);
}

function onFieldDragStart(fieldUid, event) {
    draggedFieldUid.value = fieldUid;

    if (event?.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';

        try {
            event.dataTransfer.setData('text/plain', String(fieldUid));
            setFieldDragPreview(event);
        } catch {
            return;
        }
    }
}

function onFieldDragOver(fieldUid, event) {
    if (!draggedFieldUid.value) {
        return;
    }

    dragOverFieldUid.value = fieldUid;

    if (event?.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }

    moveFieldToTarget(draggedFieldUid.value, fieldUid);
}

function onFieldDrop() {
    onFieldDragEnd();
}

function onFieldDragEnd() {
    draggedFieldUid.value = null;
    dragOverFieldUid.value = null;
}

function normalizeField(field) {
    return {
        uid:
            field.uid ||
            `field-${field.id || Date.now()}-${Math.random().toString(36).slice(2)}`,
        id: field.id ?? null,
        type: field.type ?? 'text',
        translation_key: field.translation_key ?? '',
        translated_from_form_field_id:
            field.translated_from_form_field_id ?? null,
        label: field.label ?? '',
        placeholder: field.placeholder ?? '',
        help_text: field.help_text ?? '',
        options_text: Array.isArray(field.options)
            ? field.options
                  .map((option) => `${option.key}=${option.label}`)
                  .join('\n')
            : '',
        sort_order: field.sort_order ?? 0,
        is_required: Boolean(field.is_required ?? false),
        is_active: Boolean(field.is_active ?? true),
        is_system_locked: Boolean(field.is_system_locked ?? false),
        width: field.width ?? 'full',
    };
}

function normalizeRecipientRows(rows) {
    return Array.isArray(rows)
        ? rows.map((row) => ({
              type: ['static', 'field'].includes(row?.type)
                  ? row.type
                  : 'static',
              email: row?.email ?? '',
              field_id: row?.field_id ?? null,
          }))
        : [];
}

function addRecipientRow(field) {
    form[field].push({ type: 'static', email: '', field_id: null });
}

function removeRecipientRow(field, index) {
    form[field].splice(index, 1);
}

function recipientRowError(field, index, key) {
    return form.errors?.[`${field}.${index}.${key}`] || '';
}

function touchTitle() {
    touchAndClear('title');
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

async function submit() {
    if (!(await validateBeforeSubmit())) {
        return;
    }

    if (!validateFieldRowsBeforeSubmit()) {
        return;
    }

    form.fields = preparedFieldRows();

    form.post(route('admin.cms.forms.store', { id: props.formItem?.id ?? 0 }), {
        preserveScroll: true,
        onError: () => {
            nextTick(() => {
                scrollToIssue(serverValidationErrors.value[0]);
            });
        },
    });
}

function preparedFieldRows() {
    return form.fields.map((field, index) => ({
        ...field,
        options: field.options_text
            ? field.options_text
                  .split('\n')
                  .map((option) => optionRow(option))
                  .filter(Boolean)
            : [],
        sort_order: (index + 1) * 10,
    }));
}

function optionRow(value) {
    const trimmed = value.trim();

    if (!trimmed) {
        return null;
    }

    const separatorIndex = trimmed.indexOf('=');
    const key =
        separatorIndex >= 0
            ? trimmed.slice(0, separatorIndex).trim()
            : slugify(trimmed);
    const label =
        separatorIndex >= 0
            ? trimmed.slice(separatorIndex + 1).trim()
            : trimmed;

    if (!key || !label) {
        return null;
    }

    return { key, label };
}

function hasChoiceOptions(type) {
    return ['select', 'combobox'].includes(String(type));
}

function validateFieldRowsBeforeSubmit() {
    clearFieldRowErrors();

    const errors = {};

    form.fields.forEach((field, index) => {
        const label = String(field.label ?? '').trim();

        if (label === '') {
            errors[`fields.${index}.label`] = t(
                'validation.required',
                'This field is required.',
            );
        }

        if (hasChoiceOptions(field.type)) {
            const optionLines = String(field.options_text ?? '')
                .split('\n')
                .map((line) => line.trim())
                .filter(Boolean);
            const options = optionLines.map((line) => optionRow(line));
            const validOptions = options.filter(Boolean);

            if (String(field.type) === 'select' && validOptions.length === 0) {
                errors[`fields.${index}.options`] = t(
                    'validation.form_select_options_required',
                    'A select field must have at least one option.',
                );
            } else if (validOptions.length !== optionLines.length) {
                errors[`fields.${index}.options`] = t(
                    'validation.form_option_format',
                    'Use option_key=Label per line.',
                );
            } else if (hasDuplicateOptionKeys(validOptions)) {
                errors[`fields.${index}.options`] = t(
                    'validation.form_option_keys_unique',
                    'Option keys must be unique within a field.',
                );
            }
        }
    });

    if (
        form.is_active &&
        !form.fields.some((field) => Boolean(field.is_active))
    ) {
        errors.fields = t(
            'health.publish_errors.form_no_active_fields',
            'Active forms must have at least one active field.',
        );
    }

    Object.entries(errors).forEach(([field, message]) => {
        form.setError(field, message);
    });

    if (Object.keys(errors).length === 0) {
        return true;
    }

    const firstError = Object.keys(errors)[0];
    const match = firstError.match(/^fields\.(\d+)\.(\w+)$/);

    scrollToIssue({
        tab: 'fields',
        elementId: match ? formFieldElementId(match[1], match[2]) : 'fields',
        name: firstError,
    });

    return false;
}

function clearFieldRowErrors() {
    const keys = Object.keys(form.errors || {}).filter(
        (key) => key === 'fields' || key.startsWith('fields.'),
    );

    if (keys.length > 0) {
        form.clearErrors(...keys);
    }
}

function hasDuplicateOptionKeys(options) {
    const keys = options.map((option) => option.key).filter(Boolean);

    return new Set(keys).size !== keys.length;
}

function formFieldElementId(index, field) {
    return `fields-${index}-${field}`;
}

function fieldRowError(index, field) {
    const directKey = `fields.${index}.${field}`;

    if (form.errors?.[directKey]) {
        return form.errors[directKey];
    }

    const nestedPrefix = `${directKey}.`;
    const nestedKey = Object.keys(form.errors || {}).find((key) =>
        key.startsWith(nestedPrefix),
    );

    return nestedKey ? form.errors[nestedKey] : '';
}

function fieldRowElementIdFromErrorName(name) {
    const match = String(name).match(/^fields\.(\d+)\.(\w+)/);

    if (!match) {
        return 'fields';
    }

    return formFieldElementId(
        match[1],
        match[2] === 'options' ? 'options' : match[2],
    );
}

function localeCode(value, message) {
    const text = String(value ?? '').trim();

    if (text === '') {
        return true;
    }

    return /^[a-z]{2}([_-][A-Z]{2})?$/.test(text) || message;
}

function emailAddress(value, message) {
    const text = String(value ?? '').trim();

    if (text === '') {
        return true;
    }

    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(text) || message;
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

function openTranslationDialog(locale = '') {
    translationForm.clearErrors();
    translationAction.value = null;
    translationForm.target_locale =
        locale || missingLanguages.value[0]?.locale || '';
    translationForm.use_ai = true;
    showTranslationDialog.value = true;
}

function createTranslation(useAi) {
    if (!props.formItem?.id) {
        return;
    }

    translationForm.use_ai = useAi;
    translationAction.value = useAi ? 'ai' : 'copy';
    translationForm.post(
        route('admin.cms.forms.translations.store', { id: props.formItem.id }),
        {
            preserveState: 'errors',
            onSuccess: () => {
                showTranslationDialog.value = false;
            },
            onFinish: () => {
                translationAction.value = null;
            },
        },
    );
}

function languageLabel(language) {
    return `${language.native_name || language.name || language.locale} (${language.locale})`;
}

function handleTranslationChipClick(item) {
    if (item.type === 'translation' && item.id) {
        router.visit(route('admin.cms.forms.edit', { id: item.id }));

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
</script>
