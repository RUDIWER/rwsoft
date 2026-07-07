<template>
    <Head :title="pageTitle" />

    <AdminLayout :title="pageTitle" :suppress-flash="true">
        <form id="cms-language-form" @submit.prevent="submit">
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
                                <span class="mdi mdi-translate text-2xl" />
                            </div>
                            <div class="min-w-0">
                                <CardTitle class="text-lg">
                                    {{ cardTitle }}
                                </CardTitle>
                                <CardDescription class="mt-1">
                                    {{
                                        t(
                                            'languages.form.description',
                                            'Languages determine which translations are available in the admin and public site.',
                                        )
                                    }}
                                </CardDescription>
                            </div>
                        </div>

                        <div class="flex flex-wrap justify-end gap-2">
                            <AdminFormBackButton
                                :href="route('admin.cms.languages.index')"
                                :dirty="form.isDirty"
                                :processing="form.processing"
                                :label="commonT('actions.back', 'Back')"
                                @save="submit"
                            />
                            <AdminFormSaveButton
                                :dirty="form.isDirty"
                                :processing="form.processing"
                                :label="commonT('actions.save', 'Save')"
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
                    class="border-b border-slate-200 px-4 py-3 sm:px-5"
                >
                    <RwFlashMessage
                        :type="pageFlash.type"
                        :message="pageFlash.message"
                        :details="pageFlash.details"
                    />
                </div>

                <div
                    v-if="validationFlash.message"
                    class="border-b border-slate-200 px-4 py-3 sm:px-5"
                >
                    <RwFlashMessage
                        :type="validationFlash.type"
                        :message="validationFlash.message"
                        :details="validationFlash.details"
                        @select="scrollToIssue"
                    />
                </div>

                <CardContent class="p-4 sm:p-5">
                    <FormValidationSummary
                        class="mb-5"
                        :visible="showValidationSummary"
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

                    <section class="grid gap-5">
                        <div class="grid gap-4 lg:grid-cols-2">
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
                                            'languages.form.locale',
                                            'Language code',
                                        )
                                    }}
                                </Label>
                                <Input
                                    id="locale"
                                    v-model="form.locale"
                                    class="bg-yellow-50"
                                    v-bind="requiredAttrs('locale')"
                                    @blur="touchAndClear('locale')"
                                />
                                <p class="text-xs text-slate-500">
                                    {{
                                        t(
                                            'languages.form.locale_help',
                                            'For example nl, en, fr or nl_BE.',
                                        )
                                    }}
                                </p>
                                <FieldValidationMessage
                                    :message="validationMessage('locale')"
                                    :value="form.locale"
                                />
                            </div>

                            <div class="grid content-start gap-2 pt-7">
                                <label
                                    class="flex items-center gap-2 text-sm text-slate-700"
                                >
                                    <input
                                        v-model="form.is_active"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                        @change="touchAndClear('is_active')"
                                    />
                                    {{
                                        t(
                                            'languages.form.active',
                                            'Active on site and in translation status',
                                        )
                                    }}
                                </label>
                                <FieldValidationMessage
                                    :message="validationMessage('is_active')"
                                />
                            </div>
                        </div>

                        <div class="grid gap-4 lg:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="flag_media_asset_id">
                                    {{ t('languages.form.flag', 'Flag image') }}
                                </Label>
                                <RwAutoCompleteInput
                                    id="flag_media_asset_id"
                                    v-model="form.flag_media_asset_id"
                                    :items="localMediaOptions"
                                    :item-title="mediaOptionTitle"
                                    item-value="id"
                                    :search-fields="[
                                        'filename',
                                        'original_filename',
                                        'alt_text',
                                    ]"
                                    :placeholder="
                                        t(
                                            'languages.form.flag_placeholder',
                                            'Choose a flag image',
                                        )
                                    "
                                    :invalid="
                                        Boolean(
                                            validationMessage(
                                                'flag_media_asset_id',
                                            ),
                                        )
                                    "
                                    :error-message="
                                        validationMessage('flag_media_asset_id')
                                    "
                                    @blur="touchAndClear('flag_media_asset_id')"
                                >
                                    <template #selection="{ item }">
                                        <span
                                            class="flex min-w-0 flex-1 items-center gap-2"
                                        >
                                            <img
                                                v-if="item.url"
                                                :src="item.url"
                                                :alt="
                                                    item.alt_text ||
                                                    mediaOptionTitle(item)
                                                "
                                                class="h-5 w-7 shrink-0 rounded-sm border border-slate-200 object-cover"
                                            />
                                            <span class="truncate">
                                                {{ mediaOptionTitle(item) }}
                                            </span>
                                        </span>
                                    </template>

                                    <template #option="{ item, selected }">
                                        <span
                                            class="flex min-w-0 flex-1 items-center gap-2"
                                        >
                                            <img
                                                v-if="item.url"
                                                :src="item.url"
                                                :alt="
                                                    item.alt_text ||
                                                    mediaOptionTitle(item)
                                                "
                                                class="h-7 w-10 shrink-0 rounded-sm border border-slate-200 object-cover"
                                            />
                                            <span class="grid min-w-0 flex-1">
                                                <span
                                                    class="truncate font-medium"
                                                >
                                                    {{ mediaOptionTitle(item) }}
                                                </span>
                                                <span
                                                    class="truncate text-xs text-slate-500"
                                                >
                                                    {{ item.mime_type }}
                                                </span>
                                            </span>
                                            <span
                                                v-if="selected"
                                                class="mdi mdi-check text-base text-blue-600"
                                                aria-hidden="true"
                                            />
                                        </span>
                                    </template>
                                </RwAutoCompleteInput>
                                <FieldValidationMessage
                                    :message="
                                        validationMessage('flag_media_asset_id')
                                    "
                                    :value="form.flag_media_asset_id"
                                />
                                <p class="text-xs text-slate-500">
                                    {{
                                        t(
                                            'languages.form.flag_help',
                                            'Select a public media image to use as this language flag in language menus.',
                                        )
                                    }}
                                </p>

                                <div
                                    class="mt-3 grid gap-3 rounded-md border border-slate-200 bg-slate-50 p-3"
                                >
                                    <div class="grid gap-1">
                                        <span
                                            class="text-sm font-medium text-slate-900"
                                        >
                                            {{
                                                t(
                                                    'languages.form.system_flag_title',
                                                    'System country flag',
                                                )
                                            }}
                                        </span>
                                        <p class="text-xs text-slate-500">
                                            {{
                                                t(
                                                    'languages.form.system_flag_help',
                                                    'Choose a standardized system flag. It will be copied into the Countries media folder and selected for this language.',
                                                )
                                            }}
                                        </p>
                                    </div>

                                    <div
                                        v-if="systemCountryFlags.length > 0"
                                        class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start"
                                    >
                                        <RwAutoCompleteInput
                                            id="system_country_flag"
                                            v-model="selectedSystemFlagCode"
                                            :items="systemCountryFlags"
                                            :item-title="systemFlagTitle"
                                            item-value="code"
                                            :search-fields="[
                                                'code',
                                                'name',
                                                'continent',
                                            ]"
                                            :placeholder="
                                                t(
                                                    'languages.form.system_flag_placeholder',
                                                    'Search country flag',
                                                )
                                            "
                                            :disabled="systemFlagSaving"
                                        >
                                            <template #selection="{ item }">
                                                <span
                                                    class="flex min-w-0 flex-1 items-center gap-2"
                                                >
                                                    <img
                                                        v-if="item.preview_url"
                                                        :src="item.preview_url"
                                                        :alt="
                                                            systemFlagTitle(
                                                                item,
                                                            )
                                                        "
                                                        class="h-5 w-7 shrink-0 rounded-sm border border-slate-200 object-cover"
                                                    />
                                                    <span class="truncate">
                                                        {{
                                                            systemFlagTitle(
                                                                item,
                                                            )
                                                        }}
                                                    </span>
                                                </span>
                                            </template>

                                            <template
                                                #option="{ item, selected }"
                                            >
                                                <span
                                                    class="flex min-w-0 flex-1 items-center gap-2"
                                                >
                                                    <img
                                                        v-if="item.preview_url"
                                                        :src="item.preview_url"
                                                        :alt="
                                                            systemFlagTitle(
                                                                item,
                                                            )
                                                        "
                                                        class="h-7 w-10 shrink-0 rounded-sm border border-slate-200 object-cover"
                                                    />
                                                    <span
                                                        class="grid min-w-0 flex-1"
                                                    >
                                                        <span
                                                            class="truncate font-medium"
                                                        >
                                                            {{ item.name }}
                                                        </span>
                                                        <span
                                                            class="truncate text-xs text-slate-500"
                                                        >
                                                            {{ item.continent }}
                                                            ·
                                                            {{
                                                                String(
                                                                    item.code ||
                                                                        '',
                                                                ).toUpperCase()
                                                            }}
                                                        </span>
                                                    </span>
                                                    <span
                                                        v-if="selected"
                                                        class="mdi mdi-check text-base text-blue-600"
                                                        aria-hidden="true"
                                                    />
                                                </span>
                                            </template>
                                        </RwAutoCompleteInput>

                                        <Button
                                            type="button"
                                            variant="outline"
                                            class="gap-2 shadow-none"
                                            :disabled="
                                                !selectedSystemFlagCode ||
                                                systemFlagSaving
                                            "
                                            @click="copySystemFlag"
                                        >
                                            <span
                                                v-if="systemFlagSaving"
                                                class="mdi mdi-loading animate-spin text-base"
                                                aria-hidden="true"
                                            />
                                            <span
                                                v-else
                                                class="mdi mdi-content-copy text-base"
                                                aria-hidden="true"
                                            />
                                            {{
                                                t(
                                                    'languages.form.use_system_flag',
                                                    'Use flag',
                                                )
                                            }}
                                        </Button>
                                    </div>

                                    <p
                                        v-else
                                        class="rounded border border-orange-200 bg-orange-50 px-3 py-2 text-xs text-orange-700"
                                    >
                                        {{
                                            t(
                                                'languages.form.system_flags_empty',
                                                'No system flags are available yet. Run php artisan cms:sync-country-flags first.',
                                            )
                                        }}
                                    </p>

                                    <p
                                        v-if="systemFlagMessage"
                                        class="text-xs"
                                        :class="
                                            systemFlagError
                                                ? 'text-red-700'
                                                : 'text-green-700'
                                        "
                                    >
                                        {{ systemFlagMessage }}
                                    </p>
                                </div>
                            </div>

                            <div
                                class="grid content-start gap-2 rounded-md border border-slate-200 bg-slate-50 p-3"
                            >
                                <span
                                    class="text-sm font-medium text-slate-900"
                                >
                                    {{
                                        t(
                                            'languages.form.flag_preview',
                                            'Flag preview',
                                        )
                                    }}
                                </span>
                                <div
                                    class="flex min-h-16 items-center gap-3 rounded-md border border-slate-200 bg-white px-3 py-2"
                                >
                                    <img
                                        v-if="selectedFlagMedia?.url"
                                        :src="selectedFlagMedia.url"
                                        :alt="
                                            selectedFlagMedia.alt_text ||
                                            selectedFlagMedia.filename ||
                                            form.native_name ||
                                            form.name
                                        "
                                        class="h-8 w-12 rounded-sm border border-slate-200 object-cover"
                                    />
                                    <span
                                        v-else
                                        class="inline-flex h-8 w-12 items-center justify-center rounded-sm border border-dashed border-slate-300 text-xs font-semibold text-slate-400"
                                    >
                                        {{ form.locale || '--' }}
                                    </span>
                                    <span class="grid min-w-0 text-sm">
                                        <span
                                            class="font-medium text-slate-900"
                                        >
                                            {{
                                                form.native_name ||
                                                form.name ||
                                                '-'
                                            }}
                                        </span>
                                        <span class="text-xs text-slate-500">
                                            {{ form.locale || '-' }}
                                        </span>
                                    </span>
                                </div>
                            </div>
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
                                    {{ t('languages.columns.name', 'Name') }}
                                </Label>
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    class="bg-yellow-50"
                                    v-bind="requiredAttrs('name')"
                                    @blur="touchAndClear('name')"
                                />
                                <FieldValidationMessage
                                    :message="validationMessage('name')"
                                    :value="form.name"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label
                                    for="native_name"
                                    class="flex items-center gap-1"
                                >
                                    <span
                                        class="text-red-600"
                                        aria-hidden="true"
                                        >*</span
                                    >
                                    {{
                                        t(
                                            'languages.columns.native_name',
                                            'Native name',
                                        )
                                    }}
                                </Label>
                                <Input
                                    id="native_name"
                                    v-model="form.native_name"
                                    class="bg-yellow-50"
                                    v-bind="requiredAttrs('native_name')"
                                    @blur="touchAndClear('native_name')"
                                />
                                <FieldValidationMessage
                                    :message="validationMessage('native_name')"
                                    :value="form.native_name"
                                />
                            </div>
                        </div>

                        <div class="grid gap-4 lg:grid-cols-2">
                            <div class="grid gap-2">
                                <Label
                                    for="direction"
                                    class="flex items-center gap-1"
                                >
                                    <span
                                        class="text-red-600"
                                        aria-hidden="true"
                                        >*</span
                                    >
                                    {{
                                        t(
                                            'languages.columns.direction',
                                            'Direction',
                                        )
                                    }}
                                </Label>
                                <RwAutoCompleteInput
                                    id="direction"
                                    v-model="form.direction"
                                    :items="directionOptions"
                                    item-title="label"
                                    item-value="value"
                                    :search-fields="['label', 'value']"
                                    :aria-label="
                                        t(
                                            'languages.columns.direction',
                                            'Direction',
                                        )
                                    "
                                    :required="isRequired('direction')"
                                    :invalid="
                                        Boolean(validationMessage('direction'))
                                    "
                                    :error-message="
                                        validationMessage('direction')
                                    "
                                    @blur="touchAndClear('direction')"
                                />
                                <FieldValidationMessage
                                    :message="validationMessage('direction')"
                                    :value="form.direction"
                                />
                            </div>
                        </div>
                    </section>
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
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { useCmsFormValidation } from '@/composables/useCmsFormValidation';
import baseRules from '@/ValidationRules/Rules';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    language: { type: Object, default: null },
    directionOptions: { type: Array, required: true },
    mediaOptions: { type: Array, default: () => [] },
    systemCountryFlags: { type: Array, default: () => [] },
    defaultFlagFolderId: { type: [Number, String, null], default: null },
});

const { t } = useAdminTranslations('cms_admin_ui');
const { t: commonT } = useAdminTranslations('admin_common_ui');
const page = usePage();

const form = useForm({
    locale: props.language?.locale ?? '',
    name: props.language?.name ?? '',
    native_name: props.language?.native_name ?? '',
    flag_media_asset_id: props.language?.flag_media_asset_id ?? '',
    direction: props.language?.direction ?? 'ltr',
    is_active: Boolean(props.language?.is_active ?? true),
});
const localMediaOptions = ref([...props.mediaOptions]);
const selectedSystemFlagCode = ref('');
const systemFlagSaving = ref(false);
const systemFlagMessage = ref('');
const systemFlagError = ref(false);

const isEditMode = computed(() => Boolean(props.language?.id));
const pageTitle = computed(() =>
    isEditMode.value
        ? t('languages.form.edit_title', 'Edit language')
        : t('languages.form.create_title', 'Add language'),
);
const cardTitle = computed(() =>
    isEditMode.value
        ? t('languages.form.edit_title', 'Edit language')
        : t('languages.form.new_title', 'New language'),
);
const recordIdLabel = computed(() => props.language?.id ?? '-');
const updatedAtLabel = computed(() =>
    formatRecordDate(props.language?.updated_at),
);
const createdAtLabel = computed(() =>
    formatRecordDate(props.language?.created_at),
);
const directionValues = computed(() =>
    props.directionOptions.map((option) => String(option.value)),
);
const selectedFlagMedia = computed(() =>
    localMediaOptions.value.find(
        (asset) => String(asset.id) === String(form.flag_media_asset_id || ''),
    ),
);
const requiredMessage = computed(() =>
    t('validation.required', 'This field is required.'),
);

const languageValidationFields = {
    locale: {
        label: t('languages.form.locale', 'Language code'),
        elementId: 'locale',
        required: true,
        value: () => form.locale,
        rules: [
            (value) => baseRules.required(value, requiredMessage.value),
            (value) =>
                validateMax(
                    'languages.form.locale',
                    'Language code',
                    12,
                    value,
                ),
            (value) =>
                localeCode(
                    value,
                    t(
                        'validation.locale_code',
                        'Use a valid language code, for example nl or nl_BE.',
                    ),
                ),
        ],
    },
    name: {
        label: t('languages.columns.name', 'Name'),
        elementId: 'name',
        required: true,
        value: () => form.name,
        rules: [
            (value) => baseRules.required(value, requiredMessage.value),
            (value) =>
                validateMax('languages.columns.name', 'Name', 255, value),
        ],
    },
    native_name: {
        label: t('languages.columns.native_name', 'Native name'),
        elementId: 'native_name',
        required: true,
        value: () => form.native_name,
        rules: [
            (value) => baseRules.required(value, requiredMessage.value),
            (value) =>
                validateMax(
                    'languages.columns.native_name',
                    'Native name',
                    255,
                    value,
                ),
        ],
    },
    flag_media_asset_id: {
        label: t('languages.form.flag', 'Flag image'),
        elementId: 'flag_media_asset_id',
        value: () => form.flag_media_asset_id,
        rules: [(value) => validateFlagMedia(value)],
    },
    direction: {
        label: t('languages.columns.direction', 'Direction'),
        elementId: 'direction',
        required: true,
        value: () => form.direction,
        rules: [
            (value) => baseRules.required(value, requiredMessage.value),
            (value) => validateDirection(value),
        ],
    },
    is_active: {
        label: t(
            'languages.form.active',
            'Active on site and in translation status',
        ),
        elementId: 'is_active',
        value: () => form.is_active,
        rules: [(value) => validateBoolean(value)],
    },
};

const {
    FieldValidationMessage,
    FormValidationSummary,
    allValidationErrors,
    formValidation,
    isRequired,
    message: validationMessage,
    requiredAttrs,
    showValidationSummary,
    touchAndClear,
    validationFlash,
} = useCmsFormValidation(form, {
    fields: languageValidationFields,
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
        locale: String(data.locale ?? '').trim(),
        name: String(data.name ?? '').trim(),
        native_name: String(data.native_name ?? '').trim(),
        flag_media_asset_id: data.flag_media_asset_id || null,
    })).post(
        route('admin.cms.languages.store', { id: props.language?.id ?? 0 }),
    );
}

function validateMax(labelKey, fallbackLabel, max, value) {
    return baseRules.max(
        max,
        value,
        t('validation.max_chars', ':field is too long (:current/:max).', {
            field: t(labelKey, fallbackLabel),
            current: String(value ?? '').length,
            max: String(max),
        }),
    );
}

function localeCode(value, message) {
    const text = String(value ?? '').trim();

    if (text === '') {
        return true;
    }

    return /^[a-z]{2}([_-][A-Z]{2})?$/.test(text) || message;
}

function mediaOptionTitle(asset) {
    return (
        asset?.original_filename || asset?.filename || String(asset?.id || '')
    );
}

function systemFlagTitle(flag) {
    const code = String(flag?.code || '').toUpperCase();
    const name = String(flag?.name || '').trim();

    return code && name ? `${name} (${code})` : name || code;
}

async function copySystemFlag() {
    const countryCode = String(selectedSystemFlagCode.value || '').trim();

    if (!countryCode || systemFlagSaving.value) {
        return;
    }

    systemFlagSaving.value = true;
    systemFlagMessage.value = '';
    systemFlagError.value = false;

    try {
        const response = await window.fetch(
            route('admin.cms.country-flags.copy'),
            {
                method: 'POST',
                headers: requestHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({ country_code: countryCode }),
            },
        );

        if (!response.ok) {
            systemFlagError.value = true;
            systemFlagMessage.value = t(
                'languages.form.system_flag_copy_failed',
                'The system flag could not be copied.',
            );

            return;
        }

        const payload = await response.json();
        const asset = payload.asset || null;

        if (!asset?.id) {
            systemFlagError.value = true;
            systemFlagMessage.value = t(
                'languages.form.system_flag_copy_failed',
                'The system flag could not be copied.',
            );

            return;
        }

        const index = localMediaOptions.value.findIndex(
            (item) => String(item.id) === String(asset.id),
        );

        if (index >= 0) {
            localMediaOptions.value[index] = asset;
        } else {
            localMediaOptions.value = [asset, ...localMediaOptions.value];
        }

        form.flag_media_asset_id = asset.id;
        touchAndClear('flag_media_asset_id');
        systemFlagMessage.value = t(
            'languages.form.system_flag_copied',
            'The system flag was copied and selected.',
        );
    } catch {
        systemFlagError.value = true;
        systemFlagMessage.value = t(
            'languages.form.system_flag_copy_failed',
            'The system flag could not be copied.',
        );
    } finally {
        systemFlagSaving.value = false;
    }
}

function validateFlagMedia(value) {
    if (value === null || value === undefined || value === '') {
        return true;
    }

    return localMediaOptions.value.some(
        (asset) => String(asset.id) === String(value),
    )
        ? true
        : t('validation.invalid_choice', 'Choose a valid value.');
}

function requestHeaders() {
    const tokens = csrfTokens();

    return {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': tokens.meta,
        'X-XSRF-TOKEN': tokens.cookie,
    };
}

function csrfTokens() {
    const metaToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');
    const cookieToken = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

    return {
        meta: metaToken || '',
        cookie: cookieToken ? decodeURIComponent(cookieToken) : '',
    };
}

function validateDirection(value) {
    return directionValues.value.includes(String(value))
        ? true
        : t('validation.language_direction', 'Choose a valid direction.');
}

function validateBoolean(value) {
    return typeof value === 'boolean'
        ? true
        : t('validation.invalid_choice', 'Choose a valid value.');
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
