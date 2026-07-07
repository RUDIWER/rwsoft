<template>
    <Head
        :title="
            isEditMode
                ? t('roles.edit_title', 'Edit role')
                : t('roles.create_title', 'Add role')
        "
    />

    <AdminLayout :suppress-flash="true">
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
                        >
                            <span class="mdi mdi-account-key text-2xl" />
                        </div>
                        <div class="min-w-0">
                            <CardTitle class="text-lg">
                                {{
                                    isEditMode
                                        ? t('roles.edit_title', 'Edit role')
                                        : t('roles.form_title_new', 'New role')
                                }}
                            </CardTitle>
                            <CardDescription class="mt-1">
                                {{
                                    t(
                                        'roles.form_subtitle',
                                        'Define user types and assign route-based rights.',
                                    )
                                }}
                            </CardDescription>
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2">
                        <AdminFormBackButton
                            :href="route('admin.roles')"
                            :dirty="form.isDirty"
                            :processing="form.processing"
                            :label="commonT('actions.back', 'Back')"
                            @save="submit"
                        />
                        <AdminFormSaveButton
                            form="role-form"
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

            <div v-if="cardFlash.message" class="shrink-0 px-4 pt-4 sm:px-5">
                <RwFlashMessage
                    :type="cardFlash.type"
                    :message="cardFlash.message"
                />
            </div>

            <CardContent class="flex min-h-0 flex-1 flex-col p-4 sm:p-5">
                <FormValidationSummary
                    class="mb-5 shrink-0"
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
                    id="role-form"
                    class="flex min-h-0 flex-1 flex-col gap-5"
                    @submit.prevent="submit"
                >
                    <div class="grid shrink-0 gap-5 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="key" class="flex items-center gap-1">
                                <span class="text-red-600" aria-hidden="true"
                                    >*</span
                                >
                                {{ t('roles.key', 'Key') }}
                            </Label>
                            <Input
                                id="key"
                                v-model="form.key"
                                required
                                class="bg-yellow-50"
                                @blur="touchAndClear('key')"
                            />
                            <FieldValidationMessage
                                :message="validationMessage('key')"
                                :warning="validationWarning('key')"
                                :value="form.key"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="name" class="flex items-center gap-1">
                                <span class="text-red-600" aria-hidden="true"
                                    >*</span
                                >
                                {{ t('roles.name', 'Name') }}
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
                    </div>

                    <div class="grid shrink-0 gap-2">
                        <Label for="description">
                            {{ t('roles.description', 'Description') }}
                        </Label>
                        <Input
                            id="description"
                            v-model="form.description"
                            @blur="touchAndClear('description')"
                        />
                        <FieldValidationMessage
                            :message="validationMessage('description')"
                            :warning="validationWarning('description')"
                            :value="form.description"
                        />
                    </div>

                    <div class="flex min-h-0 flex-1 flex-col gap-2">
                        <Label for="permission_ids">
                            {{ t('roles.permissions', 'Rights') }}
                        </Label>
                        <div
                            id="permission_ids"
                            class="grid min-h-40 flex-1 content-start gap-2 overflow-auto rounded-lg border border-slate-200 p-3"
                        >
                            <label
                                v-for="permission in permissions"
                                :key="permission.id"
                                class="flex items-start gap-2 rounded-md border border-transparent px-1 py-1 text-sm text-slate-700 hover:border-slate-200"
                            >
                                <input
                                    v-model="form.permission_ids"
                                    type="checkbox"
                                    :value="permission.id"
                                    class="mt-1 h-4 w-4 rounded border-slate-300"
                                    @change="touchAndClear('permission_ids')"
                                />
                                <span class="min-w-0">
                                    <span class="block font-mono text-xs">
                                        {{ permission.route_name }}
                                    </span>
                                    <span class="block text-xs text-slate-500">
                                        {{ permissionDescription(permission) }}
                                    </span>
                                </span>
                            </label>
                        </div>
                        <FieldValidationMessage
                            class="shrink-0"
                            :message="validationMessage('permission_ids')"
                            :warning="validationWarning('permission_ids')"
                        />
                    </div>
                </form>
            </CardContent>
        </Card>
    </AdminLayout>
</template>

<script setup>
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { useCmsFormValidation } from '@/composables/useCmsFormValidation';
import { useSecurityTranslations } from '@/composables/useSecurityTranslations';
import clientRules from '@/ValidationRules/Rules';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    role: {
        type: Object,
        default: null,
    },
    permissions: {
        type: Array,
        required: true,
    },
});

const { t } = useAdminTranslations('admin_security_ui');
const { t: commonT } = useAdminTranslations('admin_common_ui');
const { permissionDescription } = useSecurityTranslations();
const page = usePage();

const form = useForm({
    key: props.role?.key ?? '',
    name: props.role?.name ?? '',
    description: props.role?.description ?? '',
    permission_ids: Array.isArray(props.role?.permission_ids)
        ? [...props.role.permission_ids]
        : [],
});

const isEditMode = computed(() => Boolean(props.role?.id));
const recordIdLabel = computed(() => props.role?.id ?? '-');
const updatedAtLabel = computed(() => formatRecordDate(props.role?.updated_at));
const createdAtLabel = computed(() => formatRecordDate(props.role?.created_at));
const permissionIds = computed(() =>
    props.permissions.map((permission) => Number(permission.id)),
);
const requiredMessage = computed(() =>
    t('validation.required', 'This field is required.'),
);
const roleValidationFields = {
    key: {
        label: t('roles.key', 'Key'),
        elementId: 'key',
        value: () => form.key,
        rules: [
            (value) => clientRules.required(value, requiredMessage.value),
            (value) => validateRoleKey(value),
            (value) =>
                clientRules.max(
                    100,
                    value,
                    t(
                        'validation.max_chars',
                        ':field is too long (:current/:max).',
                        {
                            field: t('roles.key', 'Key'),
                            current: String(value ?? '').length,
                            max: '100',
                        },
                    ),
                ),
        ],
    },
    name: {
        label: t('roles.name', 'Name'),
        elementId: 'name',
        value: () => form.name,
        rules: [
            (value) => clientRules.required(value, requiredMessage.value),
            (value) =>
                clientRules.max(
                    255,
                    value,
                    t(
                        'validation.max_chars',
                        ':field is too long (:current/:max).',
                        {
                            field: t('roles.name', 'Name'),
                            current: String(value ?? '').length,
                            max: '255',
                        },
                    ),
                ),
        ],
    },
    description: {
        label: t('roles.description', 'Description'),
        elementId: 'description',
        value: () => form.description,
        rules: [
            (value) =>
                clientRules.max(
                    500,
                    value,
                    t(
                        'validation.max_chars',
                        ':field is too long (:current/:max).',
                        {
                            field: t('roles.description', 'Description'),
                            current: String(value ?? '').length,
                            max: '500',
                        },
                    ),
                ),
        ],
    },
    permission_ids: {
        label: t('roles.permissions', 'Rights'),
        elementId: 'permission_ids',
        value: () => form.permission_ids,
        rules: [(value) => validateArrayValues(value, permissionIds.value)],
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
    fields: roleValidationFields,
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

    form.post(route('admin.roles.store', { id: props.role?.id ?? 0 }));
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

function validateRoleKey(value) {
    const text = String(value ?? '').trim();

    if (text === '') {
        return true;
    }

    return /^[a-z0-9_-]+$/.test(text)
        ? true
        : t(
              'validation.role_key',
              'Use only lowercase letters, numbers, underscores and dashes.',
          );
}

function validateArrayValues(value, allowedValues) {
    if (!Array.isArray(value)) {
        return t('validation.invalid_choice', 'Choose a valid value.');
    }

    const allowed = new Set(allowedValues.map((item) => String(item)));
    const invalid = value.some((item) => !allowed.has(String(item)));

    return invalid
        ? t('validation.invalid_choice', 'Choose a valid value.')
        : true;
}
</script>
