<template>
    <section class="grid gap-4">
        <div class="grid gap-1">
            <h2 class="text-base font-semibold text-slate-900">
                {{ title }}
            </h2>
            <p class="text-sm text-slate-500">
                {{ description }}
            </p>
        </div>

        <div
            v-if="groups.length === 0"
            class="rounded border border-dashed border-slate-300 p-4 text-sm text-slate-500"
        >
            {{ emptyText }}
        </div>

        <div v-else class="grid gap-4">
            <div
                v-for="group in groups"
                :key="group.key"
                class="grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4"
            >
                <div v-if="group.title" class="grid gap-1">
                    <h3 class="text-sm font-semibold text-slate-900">
                        {{ group.title }}
                    </h3>
                    <p class="font-mono text-xs text-slate-500">
                        {{ group.key }}
                    </p>
                </div>

                <div
                    v-for="field in group.fields"
                    :key="`${group.key}:${field.key}`"
                    class="grid gap-2"
                >
                    <Label :for="inputId(group, field)">
                        <span class="flex items-center gap-1">
                            <span
                                v-if="field.required"
                                class="text-red-600"
                                aria-hidden="true"
                                >*</span
                            >
                            <span>{{ fieldLabel(field) }}</span>
                        </span>
                    </Label>

                    <CmsRichTextEditor
                        v-if="field.type === 'rich_text'"
                        :model-value="fieldValue(group, field)"
                        :placeholder="fieldPlaceholder(field)"
                        :required="field.required"
                        :media-options="mediaOptions"
                        :media-folders="mediaFolders"
                        :upload-context-type="uploadContextType"
                        :upload-context-id="uploadContextId"
                        @update:model-value="
                            setFieldValue(group, field, $event)
                        "
                        @update:media-options="updateMediaOptions"
                        @update:media-folders="updateMediaFolders"
                        @blur="emit('blur', fieldErrorKey(group, field))"
                    />

                    <RwCodeEditor
                        v-else-if="field.type === 'markdown'"
                        :model-value="fieldValue(group, field)"
                        language="markdown"
                        theme="graphite"
                        height="260px"
                        :line-wrapping="true"
                        :placeholder="fieldPlaceholder(field)"
                        @update:model-value="
                            setFieldValue(group, field, $event)
                        "
                        @blur="emit('blur', fieldErrorKey(group, field))"
                    />

                    <textarea
                        v-else-if="
                            field.type === 'textarea' || field.type === 'code'
                        "
                        :id="inputId(group, field)"
                        :value="fieldValue(group, field)"
                        rows="4"
                        :placeholder="fieldPlaceholder(field)"
                        :class="[
                            'min-h-24 rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100',
                            field.required ? 'bg-yellow-50' : 'bg-white',
                        ]"
                        @input="
                            setFieldValue(group, field, $event.target.value)
                        "
                        @blur="emit('blur', fieldErrorKey(group, field))"
                    />

                    <Input
                        v-else-if="
                            ['text', 'url', 'number'].includes(field.type)
                        "
                        :id="inputId(group, field)"
                        :type="field.type === 'number' ? 'number' : 'text'"
                        :model-value="fieldValue(group, field)"
                        :placeholder="fieldPlaceholder(field)"
                        :class="field.required ? 'bg-yellow-50' : ''"
                        @update:model-value="
                            setFieldValue(group, field, $event)
                        "
                        @blur="emit('blur', fieldErrorKey(group, field))"
                    />

                    <label
                        v-else-if="
                            field.type === 'boolean' ||
                            field.type === 'checkbox'
                        "
                        class="flex items-center gap-2 text-sm text-slate-700"
                    >
                        <input
                            :id="inputId(group, field)"
                            type="checkbox"
                            class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                            :checked="Boolean(fieldValue(group, field))"
                            @change="
                                setFieldValue(
                                    group,
                                    field,
                                    $event.target.checked,
                                )
                            "
                            @blur="emit('blur', fieldErrorKey(group, field))"
                        />
                        {{ fieldHelp(field) }}
                    </label>

                    <RwAutoCompleteInput
                        v-else-if="field.type === 'select'"
                        :id="inputId(group, field)"
                        :model-value="fieldValue(group, field)"
                        :items="selectOptions(field)"
                        item-title="label"
                        item-value="value"
                        :search-fields="['label', 'value']"
                        :required-missing="
                            field.required && !fieldValue(group, field)
                        "
                        @update:model-value="
                            setFieldValue(group, field, $event)
                        "
                        @blur="emit('blur', fieldErrorKey(group, field))"
                    />

                    <CmsMediaPicker
                        v-else-if="
                            field.type === 'media' ||
                            field.type === 'media_select'
                        "
                        :model-value="fieldValue(group, field)"
                        :assets="mediaOptions"
                        :folders="mediaFolders"
                        uploaded-from="template_data_media"
                        :upload-context-type="uploadContextType"
                        :upload-context-id="uploadContextId"
                        @update:model-value="
                            setFieldValue(group, field, $event)
                        "
                        @update:assets="updateMediaOptions"
                        @update:folders="updateMediaFolders"
                    />

                    <RwAutoCompleteInput
                        v-else-if="field.type === 'media_list'"
                        :id="inputId(group, field)"
                        :model-value="fieldValue(group, field)"
                        :items="mediaOptions"
                        item-title="title"
                        item-value="id"
                        :search-fields="['title', 'filename', 'path']"
                        :multiple="true"
                        :selection-chips="true"
                        :required-missing="
                            field.required && !fieldValue(group, field)?.length
                        "
                        @update:model-value="
                            setFieldValue(group, field, $event)
                        "
                        @blur="emit('blur', fieldErrorKey(group, field))"
                    />

                    <RwAutoCompleteInput
                        v-else-if="field.type === 'download_select'"
                        :id="inputId(group, field)"
                        :model-value="fieldValue(group, field)"
                        :items="downloadOptions"
                        item-title="title"
                        item-value="id"
                        :search-fields="[
                            'title',
                            'filename',
                            'original_filename',
                        ]"
                        :required-missing="
                            field.required && !fieldValue(group, field)
                        "
                        @update:model-value="
                            setFieldValue(group, field, $event)
                        "
                        @blur="emit('blur', fieldErrorKey(group, field))"
                    />

                    <RwAutoCompleteInput
                        v-else-if="field.type === 'download_list'"
                        :id="inputId(group, field)"
                        :model-value="fieldValue(group, field)"
                        :items="downloadOptions"
                        item-title="title"
                        item-value="id"
                        :search-fields="[
                            'title',
                            'filename',
                            'original_filename',
                        ]"
                        :multiple="true"
                        :selection-chips="true"
                        :required-missing="
                            field.required && !fieldValue(group, field)?.length
                        "
                        @update:model-value="
                            setFieldValue(group, field, $event)
                        "
                        @blur="emit('blur', fieldErrorKey(group, field))"
                    />

                    <RwAutoCompleteInput
                        v-else-if="field.type === 'download_folder_select'"
                        :id="inputId(group, field)"
                        :model-value="fieldValue(group, field)"
                        :items="downloadFolders"
                        item-title="name"
                        item-value="id"
                        :search-fields="['name']"
                        :required-missing="
                            field.required && !fieldValue(group, field)
                        "
                        @update:model-value="
                            setFieldValue(group, field, $event)
                        "
                        @blur="emit('blur', fieldErrorKey(group, field))"
                    />

                    <RwAutoCompleteInput
                        v-else-if="field.type === 'download_folder_list'"
                        :id="inputId(group, field)"
                        :model-value="fieldValue(group, field)"
                        :items="downloadFolders"
                        item-title="name"
                        item-value="id"
                        :search-fields="['name']"
                        :multiple="true"
                        :selection-chips="true"
                        :required-missing="
                            field.required && !fieldValue(group, field)?.length
                        "
                        @update:model-value="
                            setFieldValue(group, field, $event)
                        "
                        @blur="emit('blur', fieldErrorKey(group, field))"
                    />

                    <p
                        v-if="
                            fieldHelp(field) &&
                            !['boolean', 'checkbox'].includes(field.type)
                        "
                        class="text-xs text-slate-500"
                    >
                        {{ fieldHelp(field) }}
                    </p>

                    <p
                        v-if="errors[fieldErrorKey(group, field)]"
                        class="text-sm text-red-600"
                    >
                        {{ errors[fieldErrorKey(group, field)] }}
                    </p>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import RwCodeEditor from '@/Components/RwCodeEditor.vue';
import RwAutoCompleteInput from '@/Components/RwAutoCompleteInput.vue';
import CmsMediaPicker from '@/Pages/Admin/Cms/Components/CmsMediaPicker.vue';
import CmsRichTextEditor from '@/Pages/Admin/Cms/Components/CmsRichTextEditor.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAdminTranslations } from '@/composables/useAdminTranslations';
import { computed } from 'vue';

const { t } = useAdminTranslations('cms_admin_ui');

const props = defineProps({
    modelValue: { type: Object, default: () => ({}) },
    contract: { type: Object, default: () => ({ blocks: [] }) },
    locale: { type: String, default: 'en' },
    mediaOptions: { type: Array, default: () => [] },
    mediaFolders: { type: Array, default: () => [] },
    downloadOptions: { type: Array, default: () => [] },
    downloadFolders: { type: Array, default: () => [] },
    uploadContextType: { type: String, default: '' },
    uploadContextId: { type: [Number, String], default: null },
    errors: { type: Object, default: () => ({}) },
    title: { type: String, required: true },
    description: { type: String, required: true },
    emptyText: { type: String, required: true },
});

const emit = defineEmits([
    'update:modelValue',
    'update:mediaOptions',
    'update:mediaFolders',
    'blur',
]);

const groups = computed(() => {
    if (Array.isArray(props.contract?.blocks)) {
        return props.contract.blocks
            .map((block) => ({
                key: block.content_key,
                title: block.editor_label || block.content_key,
                fields: sortedFields([
                    ...(block.fields || []),
                    ...metaFields(block.meta_fields || []),
                ]),
            }))
            .filter((group) => group.key && group.fields.length > 0);
    }

    return [];
});

function sortedFields(fields) {
    return [...fields].sort(
        (a, b) => Number(a.sort_order || 0) - Number(b.sort_order || 0),
    );
}

function metaFields(fields) {
    return fields.map((field) => ({
        ...field,
        key: `_meta.${field.key}`,
    }));
}

function inputId(group, field) {
    return `template_data_${fieldPath(group, field).replace(/[^A-Za-z0-9_-]/g, '_')}`;
}

function fieldErrorKey(group, field) {
    return `template_data.${fieldPath(group, field)}`;
}

function fieldPath(group, field) {
    return `blocks.${group.key}.${field.key}`;
}

function fieldValue(group, field) {
    return (
        fieldPath(group, field)
            .split('.')
            .reduce((value, segment) => value?.[segment], props.modelValue) ??
        emptyValueFor(field)
    );
}

function setFieldValue(group, field, value) {
    const nextValue = cloneObject(props.modelValue);
    const segments = fieldPath(group, field).split('.');
    let target = nextValue;

    segments.slice(0, -1).forEach((segment) => {
        target[segment] =
            target[segment] && typeof target[segment] === 'object'
                ? target[segment]
                : {};
        target = target[segment];
    });

    target[segments[segments.length - 1]] = value;
    emit('update:modelValue', nextValue);
}

function emptyValueFor(field) {
    if (
        ['media_list', 'download_list', 'download_folder_list'].includes(
            field.type,
        )
    ) {
        return [];
    }

    if (field.type === 'boolean' || field.type === 'checkbox') {
        return false;
    }

    return '';
}

function cloneObject(value) {
    const source =
        value && typeof value === 'object' && !Array.isArray(value)
            ? value
            : {};

    try {
        return JSON.parse(JSON.stringify(source));
    } catch {
        return { ...source };
    }
}

function fieldTranslation(field, name) {
    const locale = props.locale.split(/[-_]/)[0];

    return (
        field.translations?.[props.locale]?.[name] ||
        field.translations?.[locale]?.[name] ||
        field.translations?.en?.[name] ||
        ''
    );
}

function fieldLabel(field) {
    if (field.label_key) {
        return t(field.label_key, field.label || field.key);
    }

    return fieldTranslation(field, 'label') || field.label || field.key;
}

function fieldHelp(field) {
    return fieldTranslation(field, 'help');
}

function fieldPlaceholder(field) {
    return fieldTranslation(field, 'placeholder');
}

function selectOptions(field) {
    return (field.options || []).map((option) => ({
        value: typeof option === 'object' ? option.value : option,
        label:
            typeof option === 'object' ? option.label || option.value : option,
    }));
}

function updateMediaOptions(items) {
    emit('update:mediaOptions', [...items]);
}

function updateMediaFolders(items) {
    emit('update:mediaFolders', [...items]);
}
</script>
