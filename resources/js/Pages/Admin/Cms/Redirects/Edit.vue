<template>
    <AdminLayout :title="pageTitle" :suppress-flash="true">
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
                                <span class="mdi mdi-routes text-2xl" />
                            </div>
                            <div class="min-w-0">
                                <CardTitle class="text-lg">
                                    {{ pageTitle }}
                                </CardTitle>
                                <CardDescription class="mt-1">
                                    {{
                                        t(
                                            'redirects.form.description',
                                            'Use redirects to safely send old URLs to new destinations.',
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
                            <AdminFormSaveButton
                                :dirty="form.isDirty"
                                :processing="form.processing"
                                :label="t('actions.save', 'Save')"
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
                    <div class="space-y-5 p-4 sm:p-5">
                        <section class="space-y-5">
                            <div class="grid gap-4 lg:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label
                                        for="source_path"
                                        class="flex items-center gap-1"
                                    >
                                        <span
                                            class="text-red-600"
                                            aria-hidden="true"
                                            >*</span
                                        >
                                        {{
                                            t(
                                                'redirects.form.source_path',
                                                'Source path',
                                            )
                                        }}
                                    </Label>
                                    <Input
                                        id="source_path"
                                        v-model="form.source_path"
                                        class="bg-yellow-50"
                                        v-bind="requiredAttrs('source_path')"
                                        :placeholder="
                                            t(
                                                'redirects.form.source_placeholder',
                                                '/old-page',
                                            )
                                        "
                                        @blur="touchAndClear('source_path')"
                                    />
                                    <p class="text-xs text-slate-500">
                                        {{
                                            t(
                                                'redirects.form.source_help',
                                                'Must start with / and may not contain spaces.',
                                            )
                                        }}
                                    </p>
                                    <FieldValidationMessage
                                        :message="
                                            validationMessage('source_path')
                                        "
                                        :value="form.source_path"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label
                                        for="target_url"
                                        class="flex items-center gap-1"
                                    >
                                        <span
                                            class="text-red-600"
                                            aria-hidden="true"
                                            >*</span
                                        >
                                        {{
                                            t(
                                                'redirects.form.target_url',
                                                'Target URL',
                                            )
                                        }}
                                    </Label>
                                    <Input
                                        id="target_url"
                                        v-model="form.target_url"
                                        class="bg-yellow-50"
                                        v-bind="requiredAttrs('target_url')"
                                        :placeholder="
                                            t(
                                                'redirects.form.target_placeholder',
                                                '/new-page or https://...',
                                            )
                                        "
                                        @blur="touchAndClear('target_url')"
                                    />
                                    <FieldValidationMessage
                                        :message="
                                            validationMessage('target_url')
                                        "
                                        :value="form.target_url"
                                    />
                                </div>
                            </div>

                            <div class="grid gap-4 lg:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label
                                        for="status_code"
                                        class="flex items-center gap-1"
                                    >
                                        <span
                                            class="text-red-600"
                                            aria-hidden="true"
                                            >*</span
                                        >
                                        {{
                                            t(
                                                'redirects.form.status_code',
                                                'Status code',
                                            )
                                        }}
                                    </Label>
                                    <RwAutoCompleteInput
                                        id="status_code"
                                        v-model="form.status_code"
                                        :items="statusOptions"
                                        item-title="label"
                                        item-value="value"
                                        :search-fields="['label', 'value']"
                                        :aria-label="
                                            t(
                                                'redirects.form.status_code',
                                                'Status code',
                                            )
                                        "
                                        :required="isRequired('status_code')"
                                        :invalid="
                                            Boolean(
                                                validationMessage(
                                                    'status_code',
                                                ),
                                            )
                                        "
                                        :error-message="
                                            validationMessage('status_code')
                                        "
                                        @blur="touchAndClear('status_code')"
                                    />
                                    <FieldValidationMessage
                                        :message="
                                            validationMessage('status_code')
                                        "
                                        :value="form.status_code"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="locale">
                                        {{
                                            t(
                                                'common.columns.locale',
                                                'Language',
                                            )
                                        }}
                                    </Label>
                                    <Input
                                        id="locale"
                                        v-model="form.locale"
                                        :placeholder="
                                            t(
                                                'redirects.form.locale_placeholder',
                                                'Empty = all languages',
                                            )
                                        "
                                        @blur="touchAndClear('locale')"
                                    />
                                    <FieldValidationMessage
                                        :message="validationMessage('locale')"
                                        :value="form.locale"
                                    />
                                </div>
                            </div>

                            <div class="grid gap-4 lg:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="starts_at">
                                        {{
                                            t(
                                                'redirects.form.starts_at',
                                                'Active from',
                                            )
                                        }}
                                    </Label>
                                    <Input
                                        id="starts_at"
                                        v-model="form.starts_at"
                                        type="datetime-local"
                                        @blur="touchAndClear('starts_at')"
                                    />
                                    <FieldValidationMessage
                                        :message="
                                            validationMessage('starts_at')
                                        "
                                        :value="form.starts_at"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="ends_at">
                                        {{
                                            t(
                                                'redirects.form.ends_at',
                                                'Active until',
                                            )
                                        }}
                                    </Label>
                                    <Input
                                        id="ends_at"
                                        v-model="form.ends_at"
                                        type="datetime-local"
                                        @blur="touchAndClear('ends_at')"
                                    />
                                    <FieldValidationMessage
                                        :message="validationMessage('ends_at')"
                                        :value="form.ends_at"
                                    />
                                </div>
                            </div>

                            <label class="flex items-center gap-2 text-sm">
                                <input
                                    v-model="form.is_active"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                />
                                {{ t('common.columns.active', 'Active') }}
                            </label>
                        </section>
                    </div>
                </CardContent>
            </Card>
        </form>
    </AdminLayout>
</template>

<script setup>
import RwAutoCompleteInput from '@/Components/RwAutoCompleteInput.vue';
import RwFlashMessage from '@/Components/RwFlashMessage.vue';
import AdminFormBackButton from '@/Components/Admin/Form/AdminFormBackButton.vue';
import AdminFormSaveButton from '@/Components/Admin/Form/AdminFormSaveButton.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { resolveReturnToUrl } from '@/composables/useReturnToUrl';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { useCmsFormValidation } from '@/composables/useCmsFormValidation';
import baseRules from '@/ValidationRules/Rules';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    redirectItem: { type: Object, default: null },
    statusOptions: { type: Array, required: true },
});

const { t } = useAdminTranslations('cms_admin_ui');
const { t: commonT } = useAdminTranslations('admin_common_ui');
const page = usePage();

const form = useForm({
    source_path: props.redirectItem?.source_path ?? '',
    target_url: props.redirectItem?.target_url ?? '',
    status_code: props.redirectItem?.status_code ?? 301,
    locale: props.redirectItem?.locale ?? '',
    starts_at: props.redirectItem?.starts_at ?? '',
    ends_at: props.redirectItem?.ends_at ?? '',
    is_active: Boolean(props.redirectItem?.is_active ?? true),
});

const isEditMode = computed(() => Boolean(props.redirectItem?.id));
const recordIdLabel = computed(() => props.redirectItem?.id ?? '-');
const updatedAtLabel = computed(() =>
    formatRecordDate(props.redirectItem?.updated_at),
);
const createdAtLabel = computed(() =>
    formatRecordDate(props.redirectItem?.created_at),
);
const pageTitle = computed(() =>
    isEditMode.value
        ? t('redirects.form.edit_title', 'Edit redirect')
        : t('redirects.form.create_title', 'Add redirect'),
);
const backHref = computed(() =>
    resolveReturnToUrl(route('admin.cms.redirects.index')),
);

const redirectValidationFields = {
    source_path: {
        label: t('redirects.form.source_path', 'Source path'),
        elementId: 'source_path',
        required: true,
        value: () => form.source_path,
        rules: [
            (value) =>
                baseRules.required(
                    value,
                    t('validation.required', 'This field is required.'),
                ),
            (value) =>
                sourcePath(
                    value,
                    t(
                        'validation.redirect_source_path',
                        'The source path must start with / and may not contain spaces.',
                    ),
                ),
            (value) =>
                baseRules.max(
                    2048,
                    value,
                    t(
                        'validation.max_chars',
                        ':field is too long (:current/:max).',
                        {
                            field: t(
                                'redirects.form.source_path',
                                'Source path',
                            ),
                            current: String(value ?? '').length,
                            max: 2048,
                        },
                    ),
                ),
        ],
    },
    target_url: {
        label: t('redirects.form.target_url', 'Target URL'),
        elementId: 'target_url',
        required: true,
        value: () => form.target_url,
        rules: [
            (value) =>
                baseRules.required(
                    value,
                    t('validation.required', 'This field is required.'),
                ),
            (value) =>
                redirectTarget(
                    value,
                    t(
                        'validation.redirect_target_url',
                        'The target URL must be relative or start with http(s).',
                    ),
                ),
            (value) =>
                baseRules.max(
                    2048,
                    value,
                    t(
                        'validation.max_chars',
                        ':field is too long (:current/:max).',
                        {
                            field: t('redirects.form.target_url', 'Target URL'),
                            current: String(value ?? '').length,
                            max: 2048,
                        },
                    ),
                ),
        ],
    },
    status_code: {
        label: t('redirects.form.status_code', 'Status code'),
        elementId: 'status_code',
        required: true,
        value: () => form.status_code,
        rules: [
            (value) =>
                baseRules.required(
                    value,
                    t('validation.required', 'This field is required.'),
                ),
            (value) =>
                validRedirectStatus(
                    value,
                    t(
                        'validation.redirect_status_code',
                        'Choose a valid redirect status code.',
                    ),
                ),
        ],
    },
    locale: {
        label: t('common.columns.locale', 'Language'),
        elementId: 'locale',
        value: () => form.locale,
        rules: [
            (value) =>
                localeCode(
                    value,
                    t(
                        'validation.locale_code',
                        'Use a valid language code, for example nl or nl_BE.',
                    ),
                ),
            (value) =>
                baseRules.max(
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
    starts_at: {
        label: t('redirects.form.starts_at', 'Active from'),
        elementId: 'starts_at',
        value: () => form.starts_at,
        rules: [(value) => validDateTime(value, invalidDateMessage())],
    },
    ends_at: {
        label: t('redirects.form.ends_at', 'Active until'),
        elementId: 'ends_at',
        value: () => form.ends_at,
        rules: [
            (value) => validDateTime(value, invalidDateMessage()),
            () =>
                endDateAfterStart(
                    form.starts_at,
                    form.ends_at,
                    t(
                        'validation.redirect_end_after_start',
                        'The end date must be after the start date.',
                    ),
                ),
        ],
    },
};

const {
    FieldValidationMessage,
    formValidation,
    isRequired,
    message: validationMessage,
    requiredAttrs,
    validationFlash,
    touchAndClear,
} = useCmsFormValidation(form, {
    fields: redirectValidationFields,
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

async function submit() {
    if (!(await validateBeforeSubmit())) {
        return;
    }

    form.transform((data) => ({
        ...data,
        status_code: Number(data.status_code),
        locale: emptyToNull(data.locale),
        starts_at: emptyToNull(data.starts_at),
        ends_at: emptyToNull(data.ends_at),
    })).post(
        route('admin.cms.redirects.store', { id: props.redirectItem?.id ?? 0 }),
    );
}

function sourcePath(value, message) {
    const text = String(value ?? '').trim();

    if (text === '') {
        return true;
    }

    return /^\/[^\s]*$/.test(text) || message;
}

function redirectTarget(value, message) {
    const text = String(value ?? '').trim();

    if (text === '') {
        return true;
    }

    return /^(\/[^\s]*|https?:\/\/[^\s]+)$/i.test(text) || message;
}

function validRedirectStatus(value, message) {
    return [301, 302, 307, 308].includes(Number(value)) || message;
}

function localeCode(value, message) {
    const text = String(value ?? '').trim();

    if (text === '') {
        return true;
    }

    return /^[a-z]{2}([_-][A-Z]{2})?$/.test(text) || message;
}

function validDateTime(value, message) {
    const text = String(value ?? '').trim();

    if (text === '') {
        return true;
    }

    return Number.isNaN(Date.parse(text)) ? message : true;
}

function endDateAfterStart(start, end, message) {
    const startText = String(start ?? '').trim();
    const endText = String(end ?? '').trim();

    if (startText === '' || endText === '') {
        return true;
    }

    const startDate = Date.parse(startText);
    const endDate = Date.parse(endText);

    if (Number.isNaN(startDate) || Number.isNaN(endDate)) {
        return true;
    }

    return endDate >= startDate || message;
}

function invalidDateMessage() {
    return t('validation.date', 'Use a valid date.');
}

function emptyToNull(value) {
    const text = String(value ?? '').trim();

    return text === '' ? null : text;
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
