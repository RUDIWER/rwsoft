<template>
    <Head :title="toolbarTitle" />

    <AdminLayout>
        <RwFormTemplate
            :title="toolbarTitle"
            :subtitle="
                t('table_form.subtitle', 'Tabel: :table', {
                    table: tableName,
                })
            "
        >
            <template #back>
                <Button as-child variant="outline" type="button">
                    <Link :href="backRoute">{{
                        t('actions.back', 'Terug')
                    }}</Link>
                </Button>
            </template>

            <template #actions>
                <RwActionButton
                    :label="t('actions.save', 'Bewaren')"
                    icon="mdi-content-save"
                    tone="save"
                    :loading="form.processing"
                    @click="submit"
                />
            </template>

            <div class="grid gap-4">
                <RwFlashMessage type="warning" :message="warningMessage" />
                <RwFlashMessage
                    v-if="validationSummary"
                    type="warning"
                    :message="validationSummary"
                />

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">{{
                            t('table_form.record_details', 'Record details')
                        }}</CardTitle>
                        <CardDescription>
                            {{ modeLabel }}
                            <span v-if="recordKey !== null">
                                ({{ primaryKey }}: {{ recordKey }})
                            </span>
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form class="grid gap-4" @submit.prevent="submit">
                            <div
                                v-for="field in formFields"
                                :key="field.key"
                                class="grid gap-2"
                            >
                                <Label :for="`field-${field.key}`">
                                    {{ field.label }}
                                    <span
                                        v-if="field.required"
                                        class="text-red-600"
                                        >*</span
                                    >
                                </Label>

                                <label
                                    v-if="field.type === 'boolean'"
                                    class="flex items-center gap-2 text-sm text-slate-700"
                                >
                                    <input
                                        :id="`field-${field.key}`"
                                        v-model="form.values[field.key]"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-slate-300"
                                        :class="
                                            hasFieldError(field.key)
                                                ? 'border-red-500'
                                                : ''
                                        "
                                        @change="onFieldChange(field.key)"
                                    />
                                    {{ t('table_form.active', 'Actief') }}
                                </label>

                                <select
                                    v-else-if="
                                        hasRelationshipOptions(field.key)
                                    "
                                    :id="`field-${field.key}`"
                                    v-model="form.values[field.key]"
                                    class="h-10 rounded-md border border-slate-300 px-3 text-sm"
                                    :class="
                                        hasFieldError(field.key)
                                            ? 'border-red-500 bg-red-50'
                                            : ''
                                    "
                                    @change="onFieldChange(field.key)"
                                    @blur="onFieldBlur(field.key)"
                                >
                                    <option :value="null">
                                        {{
                                            t(
                                                'table_form.select_placeholder',
                                                '-- selecteer --',
                                            )
                                        }}
                                    </option>
                                    <option
                                        v-for="option in relationshipOptions[
                                            field.key
                                        ]"
                                        :key="`${field.key}-${option.value}`"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>

                                <textarea
                                    v-else-if="isTextarea(field)"
                                    :id="`field-${field.key}`"
                                    v-model="form.values[field.key]"
                                    class="min-h-[120px] rounded-md border border-slate-300 px-3 py-2 text-sm"
                                    :class="
                                        hasFieldError(field.key)
                                            ? 'border-red-500 bg-red-50'
                                            : ''
                                    "
                                    @input="clearFieldErrors(field.key)"
                                    @blur="onFieldBlur(field.key)"
                                />

                                <Input
                                    v-else
                                    :id="`field-${field.key}`"
                                    v-model="form.values[field.key]"
                                    :type="resolveInputType(field)"
                                    :class="
                                        hasFieldError(field.key)
                                            ? 'border-red-500 bg-red-50'
                                            : ''
                                    "
                                    @input="clearFieldErrors(field.key)"
                                    @blur="onFieldBlur(field.key)"
                                />

                                <p
                                    v-if="fieldError(field.key)"
                                    class="text-sm text-red-600"
                                >
                                    {{ fieldError(field.key) }}
                                </p>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </RwFormTemplate>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RwActionButton from '@/Components/RwActionButton.vue';
import RwFlashMessage from '@/Components/RwFlashMessage.vue';
import RwFormTemplate from '@/Components/RwFormTemplate.vue';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
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
import { validateValueWithExtendedRules } from '@/validation/validate_with_extended_rules';
import { normalizeRuleTokens } from '@/Components/RwTable/validation/rules';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    tableName: { type: String, required: true },
    primaryKey: { type: String, required: true },
    mode: { type: String, required: true },
    recordKey: { type: [String, Number, null], default: null },
    warningMessage: { type: String, required: true },
    formFields: { type: Array, required: true },
    formValues: { type: Object, required: true },
    relationshipOptions: {
        type: Object,
        default: () => ({}),
    },
    backRoute: { type: String, required: true },
    submitRoute: { type: String, required: true },
});

const page = usePage();
const { t } = useAdminTranslations('db_diagram_ui');

const form = useForm({
    values: { ...props.formValues },
});

const clientErrors = ref({});
const validationSummary = ref('');

const modeLabel = computed(() =>
    props.mode === 'edit'
        ? t('table_form.mode_edit', 'Record bewerken')
        : t('table_form.mode_add', 'Record toevoegen'),
);

const toolbarTitle = computed(() =>
    props.mode === 'edit'
        ? t('table_form.title_edit', 'Record bewerken: :table', {
              table: props.tableName,
          })
        : t('table_form.title_add', 'Record toevoegen: :table', {
              table: props.tableName,
          }),
);

const rwtableMessages = computed(() => {
    const messages = page.props?.rwtable?.translations?.vue;

    if (messages && typeof messages === 'object') {
        return messages;
    }

    return {};
});

function resolveInputType(field) {
    if (field.type === 'number') {
        return 'number';
    }

    if (field.type === 'date') {
        return 'date';
    }

    if (field.type === 'datetime') {
        return 'datetime-local';
    }

    return 'text';
}

function isTextarea(field) {
    const dataType = String(field?.data_type ?? '').toLowerCase();

    return ['text', 'tinytext', 'mediumtext', 'longtext', 'json'].includes(
        dataType,
    );
}

function hasRelationshipOptions(fieldKey) {
    return Array.isArray(props.relationshipOptions?.[fieldKey]);
}

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

function interpolateTranslation(template, replacements = {}) {
    return Object.entries(replacements).reduce(
        (carry, [token, replacement]) => {
            return carry.replaceAll(`:${token}`, String(replacement ?? ''));
        },
        String(template || ''),
    );
}

function rwtableT(key, fallback = '', replacements = {}) {
    const translated = getNestedTranslation(rwtableMessages.value, key);
    const resolved =
        typeof translated === 'string' && translated.trim() !== ''
            ? translated
            : fallback || key;

    return interpolateTranslation(resolved, replacements);
}

function mergedValidationRules(field) {
    const modelRules = normalizeRuleTokens(field?.validation_rule || '');
    const extraClientRules = normalizeRuleTokens(
        field?.client_validation_rules || '',
    );
    const merged = [...modelRules, ...extraClientRules];

    if (merged.length === 0 && field?.required) {
        return 'required';
    }

    return merged.join('|');
}

function clearFieldErrors(fieldKey) {
    const normalized = String(fieldKey || '').trim();

    if (normalized === '') {
        return;
    }

    delete clientErrors.value[normalized];
    form.clearErrors(`values.${normalized}`, normalized);
}

function fieldError(fieldKey) {
    const normalized = String(fieldKey || '').trim();

    if (normalized === '') {
        return '';
    }

    if (clientErrors.value[normalized]) {
        return String(clientErrors.value[normalized]);
    }

    return form.errors[`values.${normalized}`] || form.errors[normalized] || '';
}

function hasFieldError(fieldKey) {
    return fieldError(fieldKey) !== '';
}

function validateField(field) {
    if (!field || field.type === 'divider') {
        return null;
    }

    const fieldKey = String(field.key || '').trim();

    if (fieldKey === '') {
        return null;
    }

    const rules = mergedValidationRules(field);

    if (rules === '') {
        delete clientErrors.value[fieldKey];
        return null;
    }

    const message = validateValueWithExtendedRules(
        form.values[fieldKey],
        rules,
        {
            field: fieldKey,
            fieldLabel: field.label || fieldKey,
            values: form.values,
            translate: rwtableT,
        },
    );

    if (message) {
        clientErrors.value[fieldKey] = message;
    } else {
        delete clientErrors.value[fieldKey];
    }

    return message;
}

function validateFieldByKey(fieldKey) {
    const field = props.formFields.find(
        (candidate) => String(candidate?.key || '') === String(fieldKey || ''),
    );

    if (!field) {
        return null;
    }

    return validateField(field);
}

function validateAllFields() {
    const errors = {};

    props.formFields.forEach((field) => {
        const message = validateField(field);

        if (message) {
            errors[field.key] = message;
        }
    });

    return errors;
}

function onFieldChange(fieldKey) {
    clearFieldErrors(fieldKey);
    validateFieldByKey(fieldKey);
}

function onFieldBlur(fieldKey) {
    validateFieldByKey(fieldKey);
}

function submit() {
    validationSummary.value = '';
    clientErrors.value = {};

    const clientValidationErrors = validateAllFields();

    if (Object.keys(clientValidationErrors).length > 0) {
        const summary = Object.entries(clientValidationErrors)
            .map(([fieldKey, message]) => {
                const field = props.formFields.find(
                    (candidate) => String(candidate?.key || '') === fieldKey,
                );
                const label = field?.label || fieldKey;

                return `${label}: ${message}`;
            })
            .join(' | ');

        validationSummary.value = t(
            'table_form.validation_failed',
            'Validatie mislukt. Controleer: :summary',
            { summary },
        );
        return;
    }

    form.post(props.submitRoute, {
        preserveScroll: true,
        onError: (errors) => {
            const summary = Object.entries(errors || {})
                .map(([fieldKey, message]) => {
                    const normalizedField = String(fieldKey)
                        .replace(/^values\./, '')
                        .trim();
                    const field = props.formFields.find(
                        (candidate) =>
                            String(candidate?.key || '') === normalizedField,
                    );
                    const label = field?.label || normalizedField;

                    return `${label}: ${String(message || '')}`;
                })
                .filter((entry) => entry !== ': ')
                .join(' | ');

            if (summary !== '') {
                validationSummary.value = t(
                    'table_form.validation_failed',
                    'Validatie mislukt. Controleer: :summary',
                    { summary },
                );
            }
        },
    });
}
</script>
