<template>
    <section class="grid gap-3">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="grid gap-1">
                <h3 class="text-sm font-semibold text-slate-900">
                    {{ title }}
                </h3>
                <p class="text-sm text-slate-500">
                    {{ description }}
                </p>
            </div>
            <Button
                type="button"
                variant="outline"
                class="gap-2 border-blue-200 text-blue-700 shadow-none hover:bg-blue-50 hover:text-blue-800"
                @click="addField"
            >
                <span
                    class="mdi mdi-plus-circle text-base"
                    aria-hidden="true"
                />
                {{ t('fields.add_field', 'Add field') }}
            </Button>
        </div>

        <div
            v-if="fields.length === 0"
            class="rounded border border-dashed border-slate-300 p-4 text-sm text-slate-500"
        >
            {{ emptyText }}
        </div>

        <div v-else class="grid gap-3">
            <div
                v-for="(field, index) in fields"
                :key="field._uid"
                class="grid gap-4 rounded-lg border border-slate-200 bg-slate-50 p-4"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="grid gap-1">
                        <div class="text-sm font-semibold text-slate-900">
                            {{ fieldDisplayLabel(field) }}
                        </div>
                        <div class="font-mono text-xs text-slate-500">
                            {{ field.key || '-' }}
                        </div>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="border-red-200 text-red-700 shadow-none hover:bg-red-50 hover:text-red-800"
                        @click="removeField(index)"
                    >
                        {{ t('common.actions.delete', 'Delete') }}
                    </Button>
                </div>

                <div class="grid gap-3 md:grid-cols-[1.4fr_1fr_0.7fr_0.7fr]">
                    <div class="grid gap-2">
                        <Label>{{ t('fields.field_key', 'Field key') }}</Label>
                        <Input
                            :model-value="field.key"
                            class="font-mono"
                            :placeholder="
                                t('fields.field_key_placeholder', 'title')
                            "
                            @update:model-value="
                                updateField(index, { key: $event })
                            "
                            @blur="normalizeFieldKeyAt(index)"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label>{{
                            t('fields.field_type', 'Field type')
                        }}</Label>
                        <RwAutoCompleteInput
                            :model-value="field.type"
                            :items="fieldTypeOptions"
                            item-title="label"
                            item-value="value"
                            :search-fields="['label', 'value']"
                            @update:model-value="updateFieldType(index, $event)"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label>{{
                            t('fields.sort_order', 'Sort order')
                        }}</Label>
                        <Input
                            :model-value="field.sort_order"
                            type="number"
                            min="0"
                            @update:model-value="
                                updateField(index, {
                                    sort_order: Number($event),
                                })
                            "
                        />
                    </div>

                    <label
                        class="mt-7 flex items-center gap-2 text-sm font-medium text-slate-700"
                    >
                        <input
                            :checked="field.required"
                            type="checkbox"
                            class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                            @change="
                                updateField(index, {
                                    required: $event.target.checked,
                                })
                            "
                        />
                        {{ t('fields.required', 'Required') }}
                    </label>
                </div>

                <div
                    v-if="field.type !== 'repeater'"
                    class="grid gap-2 md:max-w-md"
                >
                    <Label>{{
                        t('fields.default_value', 'Default value')
                    }}</Label>
                    <label
                        v-if="field.type === 'checkbox'"
                        class="flex items-center gap-2 text-sm text-slate-700"
                    >
                        <input
                            :checked="Boolean(field.default)"
                            type="checkbox"
                            class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                            @change="
                                updateField(index, {
                                    default: $event.target.checked,
                                })
                            "
                        />
                        {{ t('fields.default_checked', 'Checked by default') }}
                    </label>
                    <textarea
                        v-else-if="
                            [
                                'textarea',
                                'code',
                                'rich_text',
                                'markdown',
                            ].includes(field.type)
                        "
                        :value="field.default"
                        rows="3"
                        class="min-h-20 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        @input="
                            updateField(index, { default: $event.target.value })
                        "
                    />
                    <Input
                        v-else
                        :model-value="field.default"
                        :type="field.type === 'number' ? 'number' : 'text'"
                        @update:model-value="
                            updateField(index, { default: $event })
                        "
                    />
                </div>

                <div v-if="field.type === 'select'" class="grid gap-2">
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <Label>{{ t('fields.options', 'Options') }}</Label>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="shadow-none"
                            @click="addOption(index)"
                        >
                            {{ t('fields.add_option', 'Add option') }}
                        </Button>
                    </div>
                    <div
                        v-for="(option, optionIndex) in field.options"
                        :key="`${field._uid}:option:${optionIndex}`"
                        class="grid gap-2 md:grid-cols-[1fr_1fr_auto]"
                    >
                        <Input
                            :model-value="option.value"
                            :placeholder="
                                t('fields.option_value_placeholder', 'value')
                            "
                            @update:model-value="
                                updateOption(index, optionIndex, {
                                    value: $event,
                                })
                            "
                        />
                        <Input
                            :model-value="option.label"
                            :placeholder="
                                t('fields.option_label_placeholder', 'Label')
                            "
                            @update:model-value="
                                updateOption(index, optionIndex, {
                                    label: $event,
                                })
                            "
                        />
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="border-red-200 text-red-700 shadow-none hover:bg-red-50 hover:text-red-800"
                            @click="removeOption(index, optionIndex)"
                        >
                            {{ t('common.actions.delete', 'Delete') }}
                        </Button>
                    </div>
                </div>

                <div v-if="field.type === 'repeater'" class="grid gap-2">
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <Label>{{
                            t('fields.repeater_fields', 'Repeater fields')
                        }}</Label>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="shadow-none"
                            @click="addChildField(index)"
                        >
                            {{ t('fields.add_child_field', 'Add child field') }}
                        </Button>
                    </div>
                    <div
                        v-for="(childField, childIndex) in field.fields"
                        :key="childField._uid"
                        class="grid gap-2 rounded border border-slate-200 bg-white p-3 md:grid-cols-[1fr_1fr_auto]"
                    >
                        <Input
                            :model-value="childField.key"
                            class="font-mono"
                            :placeholder="
                                t('fields.field_key_placeholder', 'title')
                            "
                            @update:model-value="
                                updateChildField(index, childIndex, {
                                    key: $event,
                                })
                            "
                            @blur="normalizeChildFieldKeyAt(index, childIndex)"
                        />
                        <RwAutoCompleteInput
                            :model-value="childField.type"
                            :items="repeaterFieldTypeOptions"
                            item-title="label"
                            item-value="value"
                            :search-fields="['label', 'value']"
                            @update:model-value="
                                updateChildField(index, childIndex, {
                                    type: $event,
                                })
                            "
                        />
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="border-red-200 text-red-700 shadow-none hover:bg-red-50 hover:text-red-800"
                            @click="removeChildField(index, childIndex)"
                        >
                            {{ t('common.actions.delete', 'Delete') }}
                        </Button>
                    </div>
                </div>

                <div
                    v-for="language in languages"
                    :key="`${field._uid}-${language.locale}`"
                    class="grid gap-3 rounded border border-slate-200 bg-white p-3 md:grid-cols-3"
                >
                    <div class="grid gap-2">
                        <Label>
                            {{ languageLabel(language) }} ·
                            {{ t('fields.field_label', 'Label') }}
                        </Label>
                        <Input
                            :model-value="
                                field.translations[language.locale]?.label
                            "
                            @update:model-value="
                                updateTranslation(index, language.locale, {
                                    label: $event,
                                })
                            "
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>{{ t('fields.field_help', 'Help text') }}</Label>
                        <Input
                            :model-value="
                                field.translations[language.locale]?.help
                            "
                            @update:model-value="
                                updateTranslation(index, language.locale, {
                                    help: $event,
                                })
                            "
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>{{
                            t('fields.field_placeholder', 'Placeholder')
                        }}</Label>
                        <Input
                            :model-value="
                                field.translations[language.locale]?.placeholder
                            "
                            @update:model-value="
                                updateTranslation(index, language.locale, {
                                    placeholder: $event,
                                })
                            "
                        />
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import RwAutoCompleteInput from '@/Components/RwAutoCompleteInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { computed } from 'vue';

const { t } = useAdminTranslations('cms_admin_ui');

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    languages: { type: Array, default: () => [] },
    fieldTypes: { type: Array, default: () => [] },
    title: { type: String, required: true },
    description: { type: String, required: true },
    emptyText: { type: String, required: true },
});

const emit = defineEmits(['update:modelValue']);

const fallbackFieldTypes = [
    'text',
    'textarea',
    'rich_text',
    'markdown',
    'number',
    'checkbox',
    'select',
    'media_select',
    'media_list',
    'download_select',
    'download_list',
    'download_folder_select',
    'download_folder_list',
    'form_select',
    'menu_select',
    'code',
    'repeater',
];

const fields = computed(() =>
    props.modelValue.map((field, index) => normalizeField(field, index)),
);

const fieldTypeOptions = computed(() =>
    (props.fieldTypes.length ? props.fieldTypes : fallbackFieldTypes).map(
        (type) => ({
            value: type,
            label: t(`fields.types.${type}`, type),
        }),
    ),
);

const repeaterFieldTypeOptions = computed(() =>
    fieldTypeOptions.value.filter((option) =>
        [
            'text',
            'textarea',
            'number',
            'checkbox',
            'select',
            'media_select',
            'download_select',
            'download_folder_select',
        ].includes(option.value),
    ),
);

function addField() {
    const index = fields.value.length;
    emitFields([
        ...fields.value,
        normalizeField(
            {
                key: `field_${index + 1}`,
                type: 'text',
                sort_order: (index + 1) * 10,
            },
            index,
        ),
    ]);
}

function removeField(index) {
    emitFields(fields.value.filter((_, fieldIndex) => fieldIndex !== index));
}

function updateField(index, patch) {
    emitFields(
        fields.value.map((field, fieldIndex) =>
            fieldIndex === index
                ? normalizeField({ ...field, ...patch }, index)
                : field,
        ),
    );
}

function updateFieldType(index, type) {
    const patch = { type };

    if (type === 'checkbox') {
        patch.default = false;
    }

    if (type === 'repeater') {
        patch.default = [];
        patch.fields = fields.value[index]?.fields?.length
            ? fields.value[index].fields
            : [normalizeChildField({ key: 'title', type: 'text' }, 0)];
    }

    updateField(index, patch);
}

function normalizeFieldKeyAt(index) {
    updateField(index, { key: normalizeFieldKey(fields.value[index]?.key) });
}

function addOption(index) {
    const field = fields.value[index];
    const options = [...(field.options || []), { value: '', label: '' }];
    updateField(index, { options });
}

function updateOption(index, optionIndex, patch) {
    const field = fields.value[index];
    const options = (field.options || []).map((option, currentIndex) =>
        currentIndex === optionIndex ? { ...option, ...patch } : option,
    );
    updateField(index, { options });
}

function removeOption(index, optionIndex) {
    const field = fields.value[index];
    updateField(index, {
        options: (field.options || []).filter(
            (_, currentIndex) => currentIndex !== optionIndex,
        ),
    });
}

function addChildField(index) {
    const field = fields.value[index];
    const childIndex = field.fields.length;
    updateField(index, {
        fields: [
            ...field.fields,
            normalizeChildField(
                {
                    key: `field_${childIndex + 1}`,
                    type: 'text',
                    sort_order: (childIndex + 1) * 10,
                },
                childIndex,
            ),
        ],
    });
}

function updateChildField(index, childIndex, patch) {
    const field = fields.value[index];
    updateField(index, {
        fields: field.fields.map((childField, currentIndex) =>
            currentIndex === childIndex
                ? normalizeChildField({ ...childField, ...patch }, childIndex)
                : childField,
        ),
    });
}

function normalizeChildFieldKeyAt(index, childIndex) {
    const field = fields.value[index];
    updateChildField(index, childIndex, {
        key: normalizeFieldKey(field.fields[childIndex]?.key),
    });
}

function removeChildField(index, childIndex) {
    const field = fields.value[index];
    updateField(index, {
        fields: field.fields.filter(
            (_, currentIndex) => currentIndex !== childIndex,
        ),
    });
}

function updateTranslation(index, locale, patch) {
    const field = fields.value[index];
    updateField(index, {
        translations: {
            ...field.translations,
            [locale]: {
                label: '',
                help: '',
                placeholder: '',
                ...(field.translations[locale] || {}),
                ...patch,
            },
        },
    });
}

function emitFields(nextFields) {
    emit(
        'update:modelValue',
        nextFields.map((field, index) => normalizeField(field, index)),
    );
}

function normalizeField(field = {}, index = 0) {
    const translations = normalizeTranslations(field.translations || {});

    return {
        _uid: field._uid || uniqueFieldId(),
        key: normalizeFieldKey(field.key || field.name || ''),
        type: field.type || 'text',
        required: Boolean(field.required),
        sort_order: Number(field.sort_order ?? (index + 1) * 10),
        default: field.default ?? '',
        options: normalizeOptions(field.options || []),
        fields: Array.isArray(field.fields)
            ? field.fields.map((childField, childIndex) =>
                  normalizeChildField(childField, childIndex),
              )
            : [],
        translations,
    };
}

function normalizeChildField(field = {}, index = 0) {
    return {
        _uid: field._uid || uniqueFieldId(),
        key: normalizeFieldKey(field.key || field.name || ''),
        type: field.type || 'text',
        required: Boolean(field.required),
        sort_order: Number(field.sort_order ?? (index + 1) * 10),
        options: normalizeOptions(field.options || []),
        translations: normalizeTranslations(field.translations || {}),
    };
}

function normalizeTranslations(translations) {
    const nextTranslations = { ...(translations || {}) };
    const languages = props.languages.length
        ? props.languages
        : [{ locale: 'en' }];

    languages.forEach((language) => {
        if (!language.locale) {
            return;
        }

        nextTranslations[language.locale] = {
            label: nextTranslations[language.locale]?.label || '',
            help: nextTranslations[language.locale]?.help || '',
            placeholder: nextTranslations[language.locale]?.placeholder || '',
        };
    });

    return nextTranslations;
}

function normalizeOptions(options) {
    return (Array.isArray(options) ? options : [])
        .map((option) => ({
            value:
                typeof option === 'object'
                    ? option.value || ''
                    : String(option),
            label:
                typeof option === 'object'
                    ? option.label || option.value || ''
                    : String(option),
        }))
        .filter((option) => option.value !== '' || option.label !== '');
}

function normalizeFieldKey(value) {
    return String(value || '')
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9_]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .replace(/_{2,}/g, '_');
}

function fieldDisplayLabel(field) {
    const adminLocale = String(fieldLocale() || 'en').split(/[-_]/)[0];

    return (
        field.translations?.[fieldLocale()]?.label ||
        field.translations?.[adminLocale]?.label ||
        field.translations?.en?.label ||
        field.key ||
        t('fields.untitled_field', 'Untitled field')
    );
}

function fieldLocale() {
    return props.languages[0]?.locale || 'en';
}

function languageLabel(language) {
    return language.native_name || language.name || language.locale;
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
</script>
