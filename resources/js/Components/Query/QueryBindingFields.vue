<script setup>
import RwAutoCompleteInput from '@/Components/RwAutoCompleteInput.vue';
import { useDynamicTranslations } from '@/composables/useDynamicTranslations';

/**
 * Generieke rendering van binding inputvelden.
 * Wordt gedeeld door QueryRun en de run-dialog in QueryForm.
 */

const props = defineProps({
    rows: {
        type: Array,
        default: () => [],
    },
    values: {
        type: Object,
        required: true,
    },
    hasError: {
        type: Function,
        default: () => false,
    },
    onValueChange: {
        type: Function,
        default: () => {},
    },
    isSourceSelectType: {
        type: Function,
        default: () => false,
    },
    isRangeType: {
        type: Function,
        default: () => false,
    },
    inputTypeForBinding: {
        type: Function,
        default: () => 'text',
    },
    sourceOptionsFor: {
        type: Function,
        default: () => [],
    },
    sourceLoadingFor: {
        type: Function,
        default: () => false,
    },
    showRangeParameterTo: {
        type: Function,
        default: (row) => String(row?.parameter_to || '').trim() !== '',
    },
});

const { resolveText: resolveDynamicPromptText } = useDynamicTranslations();

function bindingValue(parameter) {
    const key = String(parameter || '').trim();

    if (key === '') {
        return '';
    }

    return props.values?.[key] ?? '';
}

function emitValue(parameter, value) {
    const key = String(parameter || '').trim();

    if (key === '') {
        return;
    }

    props.onValueChange(key, value);
}

function bindingTitle(row) {
    const parameter = String(row?.parameter || '').trim();
    const fallback = String(row?.title || parameter || 'Parameter');
    const key = String(row?.title_key || row?.titleKey || '').trim();

    return resolveDynamicPromptText(key, fallback);
}

function bindingPrompt(row) {
    const key = String(row?.prompt_key || row?.promptKey || '').trim();
    const fallback = String(row?.prompt || '').trim();

    if (key === '' && fallback === '') {
        return '';
    }

    return resolveDynamicPromptText(key, fallback, {
        parameter: String(row?.parameter || '').trim(),
        title: bindingTitle(row),
    });
}

function requiredBindingMessage() {
    return resolveDynamicPromptText(
        'query.binding.required',
        'Deze variabele is verplicht.',
    );
}
</script>

<template>
    <div class="grid gap-3 p-4 md:grid-cols-2">
        <div
            v-for="(row, index) in rows"
            :key="`${row.parameter || 'binding'}-${index}`"
            class="grid gap-1"
        >
            <label class="text-xs text-slate-600">
                {{ bindingTitle(row) }}
            </label>

            <RwAutoCompleteInput
                v-if="isSourceSelectType(row.type)"
                :model-value="bindingValue(row.parameter)"
                :items="sourceOptionsFor(row, index)"
                item-title="label"
                item-value="value"
                :search-fields="['label']"
                :disabled="sourceLoadingFor(row, index)"
                @update:model-value="(value) => emitValue(row.parameter, value)"
            />
            <input
                v-else
                :value="bindingValue(row.parameter)"
                :type="inputTypeForBinding(row.type)"
                class="h-9 rounded-md border bg-sky-50 px-3 text-sm"
                :class="
                    hasError(row.parameter)
                        ? 'border-red-400 ring-1 ring-red-200'
                        : 'border-slate-300'
                "
                :placeholder="bindingPrompt(row)"
                @input="emitValue(row.parameter, $event.target.value)"
            />

            <p v-if="hasError(row.parameter)" class="text-[11px] text-red-600">
                {{ requiredBindingMessage() }}
            </p>

            <template v-if="isRangeType(row.type) && showRangeParameterTo(row)">
                <label class="mt-1 text-xs text-slate-600">
                    {{ `Tot (${row.parameter_to})` }}
                </label>
                <input
                    :value="bindingValue(row.parameter_to)"
                    :type="inputTypeForBinding(row.type)"
                    class="h-9 rounded-md border bg-sky-50 px-3 text-sm"
                    :class="
                        hasError(row.parameter_to)
                            ? 'border-red-400 ring-1 ring-red-200'
                            : 'border-slate-300'
                    "
                    :placeholder="bindingPrompt(row)"
                    @input="emitValue(row.parameter_to, $event.target.value)"
                />
                <p
                    v-if="hasError(row.parameter_to)"
                    class="text-[11px] text-red-600"
                >
                    {{ requiredBindingMessage() }}
                </p>
            </template>
        </div>
    </div>
</template>
