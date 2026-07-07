<template>
    <Head
        :title="
            isEditMode
                ? t('permissions.edit_title', 'Edit right')
                : t('permissions.create_title', 'Add right')
        "
    />

    <AdminLayout :suppress-flash="true">
        <Card class="rounded-none shadow-none">
            <CardHeader class="gap-0 border-b border-slate-200 p-0">
                <div
                    class="flex flex-wrap items-start justify-between gap-3 px-4 py-4 sm:px-5"
                >
                    <div class="flex min-w-0 items-start gap-3">
                        <div
                            class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-blue-50 text-blue-700 ring-1 ring-blue-100"
                        >
                            <span class="mdi mdi-shield-key text-2xl" />
                        </div>
                        <div class="min-w-0">
                            <CardTitle class="text-lg">
                                {{
                                    isEditMode
                                        ? t(
                                              'permissions.edit_title',
                                              'Edit right',
                                          )
                                        : t(
                                              'permissions.form_title_new',
                                              'New right',
                                          )
                                }}
                            </CardTitle>
                            <CardDescription class="mt-1">
                                {{
                                    t(
                                        'permissions.form_subtitle',
                                        'Manage route rights for modules and actions.',
                                    )
                                }}
                            </CardDescription>
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2">
                        <AdminFormBackButton
                            :href="route('admin.permissions')"
                            :dirty="form.isDirty"
                            :processing="form.processing"
                            :label="commonT('actions.back', 'Back')"
                            @save="submit"
                        />
                        <AdminFormSaveButton
                            form="permission-form"
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

            <div v-if="cardFlash.message" class="px-4 pt-4 sm:px-5">
                <RwFlashMessage
                    :type="cardFlash.type"
                    :message="cardFlash.message"
                />
            </div>

            <CardContent class="p-4 sm:p-5">
                <FormValidationSummary
                    class="mb-5"
                    :visible="showSummary"
                    :errors="validationErrors"
                    :title="t('validation.summary_title', 'Saving is blocked')"
                    :description="
                        t(
                            'validation.summary_description',
                            'Resolve the fields below and try again.',
                        )
                    "
                    @select="scrollToIssue"
                />

                <form
                    id="permission-form"
                    class="grid gap-5"
                    @submit.prevent="submit"
                >
                    <div class="grid gap-2">
                        <Label for="route_name" class="flex items-center gap-1">
                            <span class="text-red-600" aria-hidden="true"
                                >*</span
                            >
                            {{ t('permissions.route_name', 'Route name') }}
                        </Label>
                        <Input
                            id="route_name"
                            v-model="form.route_name"
                            required
                            class="bg-yellow-50"
                            @blur="touchAndClear('route_name')"
                        />
                        <FieldValidationMessage
                            :message="validationMessage('route_name')"
                            :warning="validationWarning('route_name')"
                            :value="form.route_name"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label
                            for="description"
                            class="flex items-center gap-1"
                        >
                            <span class="text-red-600" aria-hidden="true"
                                >*</span
                            >
                            {{ t('permissions.description', 'Description') }}
                        </Label>
                        <Input
                            id="description"
                            v-model="form.description"
                            required
                            class="bg-yellow-50"
                            @blur="touchAndClear('description')"
                        />
                        <FieldValidationMessage
                            :message="validationMessage('description')"
                            :warning="validationWarning('description')"
                            :value="form.description"
                        />
                    </div>

                    <label
                        class="flex items-center gap-2 text-sm text-slate-700"
                    >
                        <input
                            id="menu"
                            v-model="form.menu"
                            type="checkbox"
                            class="h-4 w-4 rounded border-slate-300"
                            @change="touchAndClear('menu')"
                        />
                        {{ t('permissions.menu', 'Show in menu') }}
                    </label>
                    <FieldValidationMessage
                        :message="validationMessage('menu')"
                        :warning="validationWarning('menu')"
                    />

                    <div
                        class="grid gap-4 rounded-lg border border-slate-200 p-3"
                    >
                        <div class="grid max-w-md gap-2">
                            <Label for="module_id">
                                {{ t('permissions.module', 'Module') }}
                            </Label>
                            <RwAutoCompleteInput
                                id="module_id"
                                v-model="form.module_id"
                                :items="moduleOptions"
                                item-title="label"
                                item-value="id"
                                :search-fields="['label', 'key', 'name']"
                                :aria-label="t('permissions.module', 'Module')"
                                @blur="touchAndClear('module_id')"
                            />
                            <FieldValidationMessage
                                :message="validationMessage('module_id')"
                                :warning="validationWarning('module_id')"
                            />
                        </div>
                        <div class="grid max-w-md gap-2">
                            <Label for="action_id">
                                {{ t('permissions.action', 'Action') }}
                            </Label>
                            <RwAutoCompleteInput
                                id="action_id"
                                v-model="form.action_id"
                                :items="actionOptions"
                                item-title="label"
                                item-value="id"
                                :search-fields="['label', 'key', 'name']"
                                :aria-label="t('permissions.action', 'Action')"
                                @blur="touchAndClear('action_id')"
                            />
                            <FieldValidationMessage
                                :message="validationMessage('action_id')"
                                :warning="validationWarning('action_id')"
                            />
                        </div>
                        <div class="grid max-w-md gap-2">
                            <Label for="type_id">
                                {{ t('permissions.type', 'Type') }}
                            </Label>
                            <RwAutoCompleteInput
                                id="type_id"
                                v-model="form.type_id"
                                :items="typeOptions"
                                item-title="label"
                                item-value="id"
                                :search-fields="['label', 'key', 'name']"
                                :aria-label="t('permissions.type', 'Type')"
                                @blur="touchAndClear('type_id')"
                            />
                            <FieldValidationMessage
                                :message="validationMessage('type_id')"
                                :warning="validationWarning('type_id')"
                            />
                        </div>
                    </div>

                    <div
                        class="grid gap-4 rounded-lg border border-slate-200 p-3"
                    >
                        <div class="grid gap-2">
                            <Label for="query_id">
                                {{ t('permissions.query_id', 'Query ID') }}
                            </Label>
                            <Input
                                id="query_id"
                                v-model="form.query_id"
                                type="number"
                                @blur="touchAndClear('query_id')"
                            />
                            <FieldValidationMessage
                                :message="validationMessage('query_id')"
                                :warning="validationWarning('query_id')"
                                :value="form.query_id"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="url">
                                {{ t('permissions.url', 'URL') }}
                            </Label>
                            <Input
                                id="url"
                                v-model="form.url"
                                @blur="touchAndClear('url')"
                            />
                            <FieldValidationMessage
                                :message="validationMessage('url')"
                                :warning="validationWarning('url')"
                                :value="form.url"
                            />
                        </div>
                    </div>
                </form>
            </CardContent>
        </Card>
    </AdminLayout>
</template>

<script setup>
import RwFlashMessage from '@/Components/RwFlashMessage.vue';
import RwAutoCompleteInput from '@/Components/RwAutoCompleteInput.vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { useCmsFormValidation } from '@/composables/useCmsFormValidation';
import { useSecurityTranslations } from '@/composables/useSecurityTranslations';
import clientRules from '@/ValidationRules/Rules';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    permission: {
        type: Object,
        default: null,
    },
    modules: {
        type: Array,
        default: () => [],
    },
    actions: {
        type: Array,
        default: () => [],
    },
    types: {
        type: Array,
        default: () => [],
    },
});

const { t } = useAdminTranslations('admin_security_ui');
const { t: commonT } = useAdminTranslations('admin_common_ui');
const { permissionAction, permissionModule, permissionType } =
    useSecurityTranslations();
const page = usePage();

const form = useForm({
    route_name: props.permission?.route_name ?? '',
    description: props.permission?.description ?? '',
    module_id: props.permission?.module_id ?? null,
    action_id: props.permission?.action_id ?? null,
    type_id: props.permission?.type_id ?? 1,
    query_id: props.permission?.query_id ?? '',
    menu: Boolean(props.permission?.menu ?? false),
    url: props.permission?.url ?? '',
});

const isEditMode = computed(() => Boolean(props.permission?.id));
const recordIdLabel = computed(() => props.permission?.id ?? '-');
const updatedAtLabel = computed(() =>
    formatRecordDate(props.permission?.updated_at),
);
const createdAtLabel = computed(() =>
    formatRecordDate(props.permission?.created_at),
);
const moduleIds = computed(() =>
    props.modules.map((module) => Number(module.id)),
);
const actionIds = computed(() =>
    props.actions.map((action) => Number(action.id)),
);
const typeIds = computed(() => props.types.map((type) => Number(type.id)));
const moduleOptions = computed(() => [
    emptySelectOption(),
    ...props.modules.map((module) => ({
        ...module,
        label: moduleLabel(module),
    })),
]);
const actionOptions = computed(() => [
    emptySelectOption(),
    ...props.actions.map((action) => ({
        ...action,
        label: actionLabel(action),
    })),
]);
const typeOptions = computed(() => [
    emptySelectOption(),
    ...props.types.map((type) => ({
        ...type,
        label: typeLabel(type),
    })),
]);
const requiredMessage = computed(() =>
    t('validation.required', 'This field is required.'),
);
const permissionValidationFields = {
    route_name: {
        label: t('permissions.route_name', 'Route name'),
        elementId: 'route_name',
        value: () => form.route_name,
        rules: [
            (value) => clientRules.required(value, requiredMessage.value),
            (value) =>
                validateMax('permissions.route_name', 'Route name', 255, value),
        ],
    },
    description: {
        label: t('permissions.description', 'Description'),
        elementId: 'description',
        value: () => form.description,
        rules: [
            (value) => clientRules.required(value, requiredMessage.value),
            (value) =>
                validateMax(
                    'permissions.description',
                    'Description',
                    255,
                    value,
                ),
        ],
    },
    module_id: {
        label: t('permissions.module', 'Module'),
        elementId: 'module_id',
        value: () => form.module_id,
        rules: [(value) => validateOptionalId(value, moduleIds.value)],
    },
    action_id: {
        label: t('permissions.action', 'Action'),
        elementId: 'action_id',
        value: () => form.action_id,
        rules: [(value) => validateOptionalId(value, actionIds.value)],
    },
    type_id: {
        label: t('permissions.type', 'Type'),
        elementId: 'type_id',
        value: () => form.type_id,
        rules: [(value) => validateOptionalId(value, typeIds.value)],
    },
    query_id: {
        label: t('permissions.query_id', 'Query ID'),
        elementId: 'query_id',
        value: () => form.query_id,
        rules: [(value) => validateOptionalInteger(value)],
    },
    url: {
        label: t('permissions.url', 'URL'),
        elementId: 'url',
        value: () => form.url,
        rules: [(value) => validateMax('permissions.url', 'URL', 255, value)],
    },
    menu: {
        label: t('permissions.menu', 'Show in menu'),
        elementId: 'menu',
        value: () => form.menu,
        rules: [(value) => validateBoolean(value)],
    },
};
const {
    FieldValidationMessage,
    FormValidationSummary,
    validation: fieldValidation,
    formValidation,
    message: validationMessage,
    warning: validationWarning,
    touchAndClear,
} = useCmsFormValidation(form, {
    fields: permissionValidationFields,
});
const { errors: validationErrors } = fieldValidation;
const { showSummary, validateBeforeSubmit, scrollToIssue } = formValidation;
const cardFlash = computed(() => {
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

    return { type: 'info', message: '' };
});

const submit = async () => {
    if (!(await validateBeforeSubmit())) {
        return;
    }

    form.post(
        route('admin.permissions.store', { id: props.permission?.id ?? 0 }),
    );
};

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

function validateOptionalInteger(value) {
    const text = String(value ?? '').trim();

    if (text === '') {
        return true;
    }

    return Number.isInteger(Number(text))
        ? true
        : t('validation.integer', 'Use a whole number.');
}

function validateOptionalId(value, allowedValues) {
    const text = String(value ?? '').trim();

    if (text === '') {
        return true;
    }

    return allowedValues.map((item) => Number(item)).includes(Number(text))
        ? true
        : t('validation.invalid_choice', 'Choose a valid value.');
}

function validateBoolean(value) {
    return typeof value === 'boolean'
        ? true
        : t('validation.invalid_choice', 'Choose a valid value.');
}

function emptySelectOption() {
    return {
        id: null,
        key: '',
        name: commonT('select.none', 'Geen keuze'),
        label: commonT('select.none', 'Geen keuze'),
    };
}

function moduleLabel(module) {
    return permissionModule({ module });
}

function actionLabel(action) {
    return permissionAction({ action });
}

function typeLabel(type) {
    return permissionType({ type });
}
</script>
