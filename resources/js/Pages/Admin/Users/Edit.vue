<template>
    <Head
        :title="
            isEditMode
                ? t('users.edit_title', 'Gebruiker bewerken')
                : t('users.create_title', 'Gebruiker toevoegen')
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
                            <span class="mdi mdi-account-multiple text-2xl" />
                        </div>
                        <div class="min-w-0">
                            <CardTitle class="text-lg">
                                {{
                                    isEditMode
                                        ? t(
                                              'users.edit_title',
                                              'Gebruiker bewerken',
                                          )
                                        : t(
                                              'users.form_title_new',
                                              'Nieuwe gebruiker',
                                          )
                                }}
                            </CardTitle>
                            <CardDescription class="mt-1">
                                {{
                                    t(
                                        'users.form_subtitle',
                                        'Koppel hier rollen en beheer accounttoegang tot het backoffice.',
                                    )
                                }}
                            </CardDescription>
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2">
                        <AdminFormBackButton
                            :href="route('admin.users')"
                            :dirty="form.isDirty"
                            :processing="form.processing"
                            :label="commonT('actions.back', 'Terug')"
                            @save="submit"
                        />
                        <AdminFormSaveButton
                            form="user-form"
                            :dirty="form.isDirty"
                            :processing="form.processing"
                            :label="commonT('actions.save', 'Bewaren')"
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
            <CardContent class="min-h-0 flex-1 overflow-y-auto p-4 sm:p-5">
                <FormValidationSummary
                    class="mb-5"
                    :visible="showSummary"
                    :errors="validationErrors"
                    :title="
                        t('validation.summary_title', 'Bewaren is geblokkeerd')
                    "
                    :description="
                        t(
                            'validation.summary_description',
                            'Los onderstaande velden op en probeer opnieuw.',
                        )
                    "
                    @select="scrollToIssue"
                />

                <form
                    id="user-form"
                    class="grid gap-5"
                    @submit.prevent="submit"
                >
                    <div class="grid gap-2">
                        <Label for="name" class="flex items-center gap-1">
                            <span class="text-red-600" aria-hidden="true"
                                >*</span
                            >
                            {{ t('users.name', 'Naam') }}
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
                        <Label for="email" class="flex items-center gap-1">
                            <span class="text-red-600" aria-hidden="true"
                                >*</span
                            >
                            {{ t('users.email', 'E-mail') }}
                        </Label>
                        <Input
                            id="email"
                            type="email"
                            v-model="form.email"
                            required
                            class="bg-yellow-50"
                            @blur="touchAndClear('email')"
                        />
                        <FieldValidationMessage
                            :message="validationMessage('email')"
                            :warning="validationWarning('email')"
                            :value="form.email"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="allowed_content_locales">
                            {{
                                t(
                                    'users.allowed_content_locales',
                                    'Toegelaten contenttalen',
                                )
                            }}
                        </Label>
                        <DropdownMenu :modal="false">
                            <DropdownMenuTrigger as-child>
                                <div
                                    id="allowed_content_locales"
                                    role="button"
                                    tabindex="0"
                                    class="flex h-10 w-full items-center justify-between gap-3 rounded-md border border-slate-300 bg-white px-3 text-left text-sm outline-none hover:bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                >
                                    <span
                                        v-if="
                                            selectedContentLocaleOptions.length ===
                                            0
                                        "
                                        class="truncate text-slate-500"
                                    >
                                        {{
                                            t(
                                                'users.no_allowed_content_locales',
                                                'Geen talen geselecteerd',
                                            )
                                        }}
                                    </span>
                                    <span
                                        v-else
                                        class="flex min-w-0 flex-1 items-center gap-1 overflow-hidden"
                                    >
                                        <span
                                            v-for="locale in selectedContentLocaleOptions"
                                            :key="locale.value"
                                            class="inline-flex max-w-36 shrink-0 items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-blue-200"
                                        >
                                            <span class="truncate">
                                                {{
                                                    `${locale.label} (${locale.value})`
                                                }}
                                            </span>
                                            <span
                                                role="button"
                                                tabindex="-1"
                                                :aria-label="
                                                    t(
                                                        'users.remove_allowed_content_locale',
                                                        'Contenttaal verwijderen',
                                                    )
                                                "
                                                class="mdi mdi-trash-can-outline cursor-pointer text-sm text-blue-500 hover:text-red-600"
                                                @click.stop.prevent="
                                                    removeContentLocale(
                                                        locale.value,
                                                    )
                                                "
                                            ></span>
                                        </span>
                                    </span>
                                    <span
                                        class="mdi mdi-chevron-down shrink-0 text-lg text-slate-500"
                                    ></span>
                                </div>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start" class="w-72">
                                <DropdownMenuLabel>
                                    {{
                                        t(
                                            'users.allowed_content_locales',
                                            'Toegelaten contenttalen',
                                        )
                                    }}
                                </DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuCheckboxItem
                                    v-for="locale in props.content_locale_options"
                                    :key="locale.value"
                                    :model-value="
                                        isContentLocaleSelected(locale.value)
                                    "
                                    @update:model-value="
                                        (selected) =>
                                            setContentLocaleSelected(
                                                locale.value,
                                                selected,
                                            )
                                    "
                                    @select.prevent
                                >
                                    {{ locale.label }} ({{ locale.value }})
                                </DropdownMenuCheckboxItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <p class="text-xs text-slate-500">
                            {{
                                t(
                                    'users.allowed_content_locales_help',
                                    'Alleen deze openbare talen mogen door deze gebruiker bewerkt worden.',
                                )
                            }}
                        </p>
                        <FieldValidationMessage
                            :message="
                                validationMessage('allowed_content_locales')
                            "
                            :warning="
                                validationWarning('allowed_content_locales')
                            "
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password" class="flex items-center gap-1">
                            <span
                                v-if="!isEditMode"
                                class="text-red-600"
                                aria-hidden="true"
                                >*</span
                            >{{ t('users.password', 'Wachtwoord') }}
                            {{
                                isEditMode
                                    ? t(
                                          'users.password_keep',
                                          '(leeg laten om te behouden)',
                                      )
                                    : ''
                            }}</Label
                        >
                        <Input
                            id="password"
                            type="password"
                            v-model="form.password"
                            :required="!isEditMode"
                            :class="{ 'bg-yellow-50': !isEditMode }"
                            @blur="touchAndClear('password')"
                        />
                        <FieldValidationMessage
                            :message="validationMessage('password')"
                            :warning="validationWarning('password')"
                            :value="form.password"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="admin_locale">
                            {{ commonT('locale.admin_language', 'Admin taal') }}
                        </Label>
                        <RwAutoCompleteInput
                            id="admin_locale"
                            v-model="form.admin_locale"
                            :items="adminLocaleOptions"
                            item-title="label"
                            item-value="value"
                            :search-fields="['label', 'value']"
                            :aria-label="
                                commonT('locale.admin_language', 'Admin taal')
                            "
                            @blur="touchAndClear('admin_locale')"
                        />
                        <FieldValidationMessage
                            :message="validationMessage('admin_locale')"
                            :warning="validationWarning('admin_locale')"
                            :value="form.admin_locale"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label>{{ t('users.roles', 'Rollen') }}</Label>
                        <div
                            class="grid gap-2 rounded-lg border border-slate-200 p-3"
                        >
                            <label
                                v-for="role in roles"
                                :key="role.id"
                                class="flex items-center gap-2 text-sm text-slate-700"
                            >
                                <input
                                    v-model="form.role_ids"
                                    type="checkbox"
                                    :value="role.id"
                                    class="h-4 w-4 rounded border-slate-300"
                                    @change="touchAndClear('role_ids')"
                                />
                                {{ roleLabel(role) }}
                            </label>
                        </div>
                        <FieldValidationMessage
                            :message="validationMessage('role_ids')"
                            :warning="validationWarning('role_ids')"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label id="database_access">{{
                            t(
                                'users.database_access',
                                'Database toegang (DB Diagram)',
                            )
                        }}</Label>
                        <div
                            class="grid gap-3 rounded-lg border border-slate-200 p-3"
                        >
                            <label
                                class="flex items-center gap-2 text-sm text-slate-700"
                            >
                                <input
                                    v-model="form.database_view_access"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300"
                                    @change="touchAndClear('database_access')"
                                />
                                {{
                                    t(
                                        'users.database_view_access',
                                        'Database inhoud bekijken',
                                    )
                                }}
                            </label>
                            <label
                                class="flex items-center gap-2 text-sm text-slate-700"
                            >
                                <input
                                    v-model="form.database_edit_access"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300"
                                    @change="touchAndClear('database_access')"
                                />
                                {{
                                    t(
                                        'users.database_edit_access',
                                        'Database records bewerken',
                                    )
                                }}
                            </label>
                            <label
                                class="flex items-center gap-2 text-sm text-slate-700"
                            >
                                <input
                                    v-model="form.database_add_access"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300"
                                    @change="touchAndClear('database_access')"
                                />
                                {{
                                    t(
                                        'users.database_add_access',
                                        'Database records toevoegen',
                                    )
                                }}
                            </label>
                            <label
                                class="flex items-center gap-2 text-sm text-slate-700"
                            >
                                <input
                                    v-model="form.database_delete_access"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300"
                                    @change="touchAndClear('database_access')"
                                />
                                {{
                                    t(
                                        'users.database_delete_access',
                                        'Database records verwijderen',
                                    )
                                }}
                            </label>
                            <label
                                class="flex items-center gap-2 text-sm text-slate-700"
                            >
                                <input
                                    v-model="form.database_export_access"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300"
                                    @change="touchAndClear('database_access')"
                                />
                                {{
                                    t(
                                        'users.database_export_access',
                                        'Tabel SQL export uitvoeren',
                                    )
                                }}
                            </label>
                            <label
                                class="flex items-center gap-2 text-sm text-slate-700"
                            >
                                <input
                                    v-model="form.database_sql_query_access"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300"
                                    @change="touchAndClear('database_access')"
                                />
                                {{
                                    t(
                                        'users.database_sql_query_access',
                                        'SQL editor readonly uitvoeren',
                                    )
                                }}
                            </label>
                            <label
                                class="flex items-center gap-2 text-sm text-slate-700"
                            >
                                <input
                                    v-model="
                                        form.database_sql_destructive_access
                                    "
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300"
                                    @change="touchAndClear('database_access')"
                                />
                                {{
                                    t(
                                        'users.database_sql_destructive_access',
                                        'SQL editor destructief uitvoeren',
                                    )
                                }}
                            </label>
                            <label
                                class="flex items-center gap-2 text-sm text-slate-700"
                            >
                                <input
                                    v-model="form.database_full_backup_access"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300"
                                    @change="touchAndClear('database_access')"
                                />
                                {{
                                    t(
                                        'users.database_full_backup_access',
                                        'Volledige manuele backup uitvoeren',
                                    )
                                }}
                            </label>
                        </div>
                        <FieldValidationMessage
                            :message="validationMessage('database_access')"
                            :warning="validationWarning('database_access')"
                        />
                    </div>
                </form>
            </CardContent>
        </Card>
    </AdminLayout>
</template>

<script setup>
import RwAutoCompleteInput from '@/Components/RwAutoCompleteInput.vue';
import AdminFormBackButton from '@/Components/Admin/Form/AdminFormBackButton.vue';
import AdminFormSaveButton from '@/Components/Admin/Form/AdminFormSaveButton.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RwFlashMessage from '@/Components/RwFlashMessage.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { useCmsFormValidation } from '@/composables/useCmsFormValidation';
import { useSecurityTranslations } from '@/composables/useSecurityTranslations';
import clientRules from '@/ValidationRules/Rules';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    user: {
        type: Object,
        default: null,
    },
    roles: {
        type: Array,
        required: true,
    },
    locale_options: {
        type: Array,
        default: () => [],
    },
    content_locale_options: {
        type: Array,
        default: () => [],
    },
});

const { t } = useAdminTranslations('admin_security_ui');
const { t: commonT } = useAdminTranslations('admin_common_ui');
const { roleLabel } = useSecurityTranslations();
const page = usePage();

const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    password: '',
    admin_locale: props.user?.admin_locale ?? '',
    allowed_content_locales: Array.isArray(props.user?.allowed_content_locales)
        ? [...props.user.allowed_content_locales]
        : props.content_locale_options.map((locale) => String(locale.value)),
    role_ids: props.user?.role_ids ?? [],
    database_view_access: Boolean(props.user?.database_view_access ?? false),
    database_edit_access: Boolean(props.user?.database_edit_access ?? false),
    database_add_access: Boolean(props.user?.database_add_access ?? false),
    database_delete_access: Boolean(
        props.user?.database_delete_access ?? false,
    ),
    database_export_access: Boolean(
        props.user?.database_export_access ?? false,
    ),
    database_sql_query_access: Boolean(
        props.user?.database_sql_query_access ?? false,
    ),
    database_sql_destructive_access: Boolean(
        props.user?.database_sql_destructive_access ?? false,
    ),
    database_full_backup_access: Boolean(
        props.user?.database_full_backup_access ?? false,
    ),
});

const isEditMode = computed(() => Boolean(props.user?.id));
const recordIdLabel = computed(() => props.user?.id ?? '-');
const updatedAtLabel = computed(() => formatRecordDate(props.user?.updated_at));
const createdAtLabel = computed(() => formatRecordDate(props.user?.created_at));
const activeAdminLocales = computed(() =>
    props.locale_options.map((locale) => String(locale.value)),
);
const adminLocaleOptions = computed(() => [
    {
        label: commonT('locale.default_admin_language', 'Standaardtaal admin'),
        value: '',
    },
    ...props.locale_options,
]);
const activeContentLocales = computed(() =>
    props.content_locale_options.map((locale) => String(locale.value)),
);
const roleIds = computed(() => props.roles.map((role) => Number(role.id)));
const requiredMessage = computed(() =>
    t('validation.required', 'Dit veld is verplicht.'),
);
const userValidationFields = {
    name: {
        label: t('users.name', 'Naam'),
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
                        ':field is te lang (:current/:max).',
                        {
                            field: t('users.name', 'Naam'),
                            current: String(value ?? '').length,
                            max: '255',
                        },
                    ),
                ),
        ],
    },
    email: {
        label: t('users.email', 'E-mail'),
        elementId: 'email',
        value: () => form.email,
        rules: [
            (value) => clientRules.required(value, requiredMessage.value),
            (value) => validateEmail(value),
            (value) =>
                clientRules.max(
                    255,
                    value,
                    t(
                        'validation.max_chars',
                        ':field is te lang (:current/:max).',
                        {
                            field: t('users.email', 'E-mail'),
                            current: String(value ?? '').length,
                            max: '255',
                        },
                    ),
                ),
        ],
    },
    password: {
        label: t('users.password', 'Wachtwoord'),
        elementId: 'password',
        value: () => form.password,
        rules: [
            (value) =>
                isEditMode.value
                    ? true
                    : clientRules.required(value, requiredMessage.value),
            (value) =>
                clientRules.min(
                    8,
                    value,
                    t(
                        'validation.min_chars',
                        ':field is te kort (:current/:min).',
                        {
                            field: t('users.password', 'Wachtwoord'),
                            current: String(value ?? '').length,
                            min: '8',
                        },
                    ),
                ),
            (value) =>
                clientRules.max(
                    255,
                    value,
                    t(
                        'validation.max_chars',
                        ':field is te lang (:current/:max).',
                        {
                            field: t('users.password', 'Wachtwoord'),
                            current: String(value ?? '').length,
                            max: '255',
                        },
                    ),
                ),
        ],
    },
    admin_locale: {
        label: commonT('locale.admin_language', 'Admin taal'),
        elementId: 'admin_locale',
        value: () => form.admin_locale,
        rules: [
            (value) => validateOptionalValue(value, activeAdminLocales.value),
        ],
    },
    allowed_content_locales: {
        label: t('users.allowed_content_locales', 'Toegelaten contenttalen'),
        elementId: 'allowed_content_locales',
        value: () => form.allowed_content_locales,
        rules: [
            (value) => validateArrayValues(value, activeContentLocales.value),
        ],
    },
    role_ids: {
        label: t('users.roles', 'Rollen'),
        elementId: 'role_ids',
        value: () => form.role_ids,
        rules: [(value) => validateArrayValues(value, roleIds.value)],
    },
    database_access: {
        label: t('users.database_access', 'Database toegang (DB Diagram)'),
        elementId: 'database_access',
        value: () => [
            form.database_view_access,
            form.database_edit_access,
            form.database_add_access,
            form.database_delete_access,
            form.database_export_access,
            form.database_sql_query_access,
            form.database_sql_destructive_access,
            form.database_full_backup_access,
        ],
        rules: [(value) => validateBooleanArray(value)],
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
    fields: userValidationFields,
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
const selectedContentLocaleOptions = computed(() => {
    const selected = new Set(
        form.allowed_content_locales.map((locale) => String(locale)),
    );

    return props.content_locale_options.filter((locale) =>
        selected.has(String(locale.value)),
    );
});

const submit = async () => {
    if (!(await validateBeforeSubmit())) {
        return;
    }

    form.post(route('admin.users.store', { id: props.user?.id ?? 0 }));
};

function isContentLocaleSelected(locale) {
    return form.allowed_content_locales
        .map((value) => String(value))
        .includes(String(locale));
}

function setContentLocaleSelected(locale, selected) {
    const value = String(locale);
    const current = form.allowed_content_locales.map((item) => String(item));

    if (selected && !current.includes(value)) {
        form.allowed_content_locales = [...current, value];
        touchAndClear('allowed_content_locales');
        return;
    }

    if (!selected) {
        form.allowed_content_locales = current.filter((item) => item !== value);
        touchAndClear('allowed_content_locales');
    }
}

function removeContentLocale(locale) {
    setContentLocaleSelected(locale, false);
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

function validateEmail(value) {
    const text = String(value ?? '').trim();

    if (text === '') {
        return true;
    }

    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(text)
        ? true
        : t('validation.email', 'Gebruik een geldig e-mailadres.');
}

function validateOptionalValue(value, allowedValues) {
    const text = String(value ?? '').trim();

    if (text === '') {
        return true;
    }

    return allowedValues.map((item) => String(item)).includes(text)
        ? true
        : t('validation.invalid_choice', 'Kies een geldige waarde.');
}

function validateArrayValues(value, allowedValues) {
    if (!Array.isArray(value)) {
        return t('validation.invalid_choice', 'Kies een geldige waarde.');
    }

    const allowed = new Set(allowedValues.map((item) => String(item)));
    const invalid = value.some((item) => !allowed.has(String(item)));

    return invalid
        ? t('validation.invalid_choice', 'Kies een geldige waarde.')
        : true;
}

function validateBooleanArray(value) {
    return Array.isArray(value) &&
        value.every((item) => typeof item === 'boolean')
        ? true
        : t('validation.invalid_choice', 'Kies een geldige waarde.');
}
</script>
