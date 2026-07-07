<script setup>
import RwActionButton from '@/Components/RwActionButton.vue';
import RwAutoCompleteInput from '@/Components/RwAutoCompleteInput.vue';
import { useAdminTranslations } from '@/composables/useAdminTranslations';

/**
 * Editor voor QueryForm binding_rows configuratie.
 * Houdt volgorde/sort_order consistent via v-model updates.
 */

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
    },
    bindingTypeOptions: {
        type: Array,
        default: () => [],
    },
    bindingSourceOptions: {
        type: Array,
        default: () => [],
    },
    bindingSourceLoading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue']);
const { t } = useAdminTranslations('query_builder_ui');

function rows() {
    return Array.isArray(props.modelValue) ? [...props.modelValue] : [];
}

function commit(nextRows) {
    emit(
        'update:modelValue',
        nextRows.map((row, index) => ({
            ...row,
            sort_order: index + 1,
        })),
    );
}

function addRow() {
    const nextRows = rows();

    nextRows.push({
        type: 'text',
        parameter: '',
        parameter_to: '',
        source_table_id: null,
        title: '',
        title_key: '',
        prompt: '',
        prompt_key: '',
        sort_order: nextRows.length + 1,
    });

    commit(nextRows);
}

function removeRow(index) {
    const nextRows = rows();

    nextRows.splice(index, 1);
    commit(nextRows);
}

function moveRow(index, direction) {
    const nextRows = rows();
    const targetIndex = index + direction;

    if (targetIndex < 0 || targetIndex >= nextRows.length) {
        return;
    }

    const current = nextRows[index];
    nextRows[index] = nextRows[targetIndex];
    nextRows[targetIndex] = current;
    commit(nextRows);
}

function updateField(index, field, value) {
    const nextRows = rows();

    if (!nextRows[index]) {
        return;
    }

    nextRows[index] = {
        ...nextRows[index],
        [field]: value,
    };

    commit(nextRows);
}

function isRangeType(type) {
    return (
        String(type || '').trim() === 'number_range' ||
        String(type || '').trim() === 'date_range'
    );
}

function isSourceSelectType(type) {
    return String(type || '').trim() === 'source_select';
}
</script>

<template>
    <div class="space-y-3 p-4">
        <div class="flex items-center justify-end">
            <RwActionButton
                :label="t('form.binding_editor.actions.add', 'Toevoegen')"
                icon="mdi mdi-plus-circle"
                tone="new"
                @click="addRow"
            />
        </div>

        <div
            v-if="!Array.isArray(modelValue) || modelValue.length === 0"
            class="rounded-md border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500"
        >
            {{
                t(
                    'form.binding_editor.empty',
                    'Nog geen variabelen toegevoegd.',
                )
            }}
        </div>

        <div
            v-for="(row, index) in modelValue"
            :key="`binding-row-${index}`"
            class="rounded-md border border-slate-200 p-3"
        >
            <div class="mb-3 flex items-center justify-between">
                <p class="text-xs text-slate-500">
                    {{
                        t(
                            'form.binding_editor.variable_label',
                            'Variabele #:number',
                            {
                                number: index + 1,
                            },
                        )
                    }}
                </p>
                <div class="flex items-center gap-2">
                    <RwActionButton
                        :label="t('form.binding_editor.actions.up', 'Omhoog')"
                        icon="mdi mdi-arrow-up"
                        tone="neutral"
                        :icon-only="true"
                        :disabled="index === 0"
                        @click="moveRow(index, -1)"
                    />
                    <RwActionButton
                        :label="t('form.binding_editor.actions.down', 'Omlaag')"
                        icon="mdi mdi-arrow-down"
                        tone="neutral"
                        :icon-only="true"
                        :disabled="index >= modelValue.length - 1"
                        @click="moveRow(index, 1)"
                    />
                    <RwActionButton
                        :label="t('actions.delete', 'Verwijderen')"
                        icon="mdi mdi-delete"
                        tone="delete"
                        :icon-only="true"
                        @click="removeRow(index)"
                    />
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-3">
                <div class="grid gap-1">
                    <label class="text-xs text-slate-600">{{
                        t('form.builder.fields.type', 'Type')
                    }}</label>
                    <RwAutoCompleteInput
                        :model-value="row.type"
                        :items="bindingTypeOptions"
                        item-title="label"
                        item-value="value"
                        :search-fields="['label']"
                        @update:model-value="
                            (value) => updateField(index, 'type', value)
                        "
                    />
                </div>

                <div class="grid gap-1">
                    <label class="text-xs text-slate-600">{{
                        t('form.builder.fields.parameter', 'Parameter')
                    }}</label>
                    <input
                        :value="row.parameter"
                        type="text"
                        class="h-9 rounded-md border border-slate-300 bg-sky-50 px-3 text-sm"
                        :placeholder="
                            t(
                                'form.binding_editor.placeholders.parameter',
                                'bijv. school_year_id',
                            )
                        "
                        @input="
                            (event) =>
                                updateField(
                                    index,
                                    'parameter',
                                    event.target.value,
                                )
                        "
                    />
                </div>

                <div v-if="isRangeType(row.type)" class="grid gap-1">
                    <label class="text-xs text-slate-600">{{
                        t('form.builder.fields.parameter_to', 'Parameter tot')
                    }}</label>
                    <input
                        :value="row.parameter_to"
                        type="text"
                        class="h-9 rounded-md border border-slate-300 bg-sky-50 px-3 text-sm"
                        :placeholder="
                            t(
                                'form.binding_editor.placeholders.parameter_to',
                                'bijv. school_year_id_to',
                            )
                        "
                        @input="
                            (event) =>
                                updateField(
                                    index,
                                    'parameter_to',
                                    event.target.value,
                                )
                        "
                    />
                </div>

                <div v-if="isSourceSelectType(row.type)" class="grid gap-1">
                    <label class="text-xs text-slate-600">{{
                        t('form.builder.fields.source_table', 'Bron tabel')
                    }}</label>
                    <RwAutoCompleteInput
                        :model-value="row.source_table_id"
                        :items="bindingSourceOptions"
                        item-title="title"
                        item-value="value"
                        :search-fields="['title']"
                        :disabled="bindingSourceLoading"
                        @update:model-value="
                            (value) =>
                                updateField(index, 'source_table_id', value)
                        "
                    />
                </div>

                <div class="grid gap-1 md:col-span-2">
                    <label class="text-xs text-slate-600">{{
                        t('form.binding_editor.fields.title', 'Titel')
                    }}</label>
                    <input
                        :value="row.title"
                        type="text"
                        class="h-9 rounded-md border border-slate-300 bg-white px-3 text-sm"
                        @input="
                            (event) =>
                                updateField(index, 'title', event.target.value)
                        "
                    />
                </div>

                <div class="grid gap-1 md:col-span-3">
                    <label class="text-xs text-slate-600">{{
                        t('form.binding_editor.fields.prompt', 'Prompt')
                    }}</label>
                    <input
                        :value="row.prompt"
                        type="text"
                        class="h-9 rounded-md border border-slate-300 bg-white px-3 text-sm"
                        @input="
                            (event) =>
                                updateField(index, 'prompt', event.target.value)
                        "
                    />
                </div>

                <div class="grid gap-1 md:col-span-3 md:grid-cols-2 md:gap-3">
                    <div class="grid gap-1">
                        <label class="text-xs text-slate-600">{{
                            t(
                                'form.binding_editor.fields.title_key',
                                'Titel key (geavanceerd)',
                            )
                        }}</label>
                        <input
                            :value="row.title_key || row.titleKey || ''"
                            type="text"
                            class="h-9 rounded-md border border-slate-300 bg-white px-3 text-sm"
                            :placeholder="
                                t(
                                    'form.binding_editor.placeholders.title_key',
                                    'queries.{id}.bindings.parameter.title',
                                )
                            "
                            @input="
                                (event) =>
                                    updateField(
                                        index,
                                        'title_key',
                                        event.target.value,
                                    )
                            "
                        />
                    </div>

                    <div class="grid gap-1">
                        <label class="text-xs text-slate-600">{{
                            t(
                                'form.binding_editor.fields.prompt_key',
                                'Prompt key (geavanceerd)',
                            )
                        }}</label>
                        <input
                            :value="row.prompt_key || row.promptKey || ''"
                            type="text"
                            class="h-9 rounded-md border border-slate-300 bg-white px-3 text-sm"
                            :placeholder="
                                t(
                                    'form.binding_editor.placeholders.prompt_key',
                                    'queries.{id}.bindings.parameter.prompt',
                                )
                            "
                            @input="
                                (event) =>
                                    updateField(
                                        index,
                                        'prompt_key',
                                        event.target.value,
                                    )
                            "
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
