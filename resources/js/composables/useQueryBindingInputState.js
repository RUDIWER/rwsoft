import {
    isMissingBindingValue,
    normalizeBindingName,
} from '@/composables/useQueryBindings';
import { reactive, ref } from 'vue';

/**
 * Gedeelde state-machine voor binding inputflows (QueryForm run-dialog + QueryRun pagina).
 *
 * Beheert centraal:
 * - waarden per binding
 * - ontbrekende/error state
 * - source_select opties + loading state
 */

export function isSourceSelectBindingType(type) {
    return String(type || '').trim() === 'source_select';
}

export function isRangeBindingType(type) {
    const normalizedType = String(type || '').trim();

    return normalizedType === 'number_range' || normalizedType === 'date_range';
}

export function bindingInputType(type) {
    const normalizedType = String(type || '').trim();

    if (normalizedType === 'number' || normalizedType === 'number_range') {
        return 'number';
    }

    if (normalizedType === 'date' || normalizedType === 'date_range') {
        return 'date';
    }

    return 'text';
}

export function useQueryBindingInputState(fetchSourceOptions) {
    const values = reactive({});
    const missing = ref([]);
    const warning = ref('');
    const optionsByBinding = reactive({});
    const loadingByBinding = reactive({});

    function cacheKeyForRow(row, index = 0) {
        const parameter = normalizeBindingName(row?.parameter);

        if (parameter !== '') {
            return parameter;
        }

        return `binding_${Number(index || 0)}`;
    }

    function ensureValue(parameter, defaultValue = '') {
        const bindingKey = normalizeBindingName(parameter);

        if (bindingKey === '') {
            return;
        }

        if (!Object.prototype.hasOwnProperty.call(values, bindingKey)) {
            values[bindingKey] = defaultValue;
        }
    }

    function setValue(parameter, value) {
        const bindingKey = normalizeBindingName(parameter);

        if (bindingKey === '') {
            return;
        }

        values[bindingKey] = value;
        clearError(bindingKey);
    }

    function getValue(parameter) {
        const bindingKey = normalizeBindingName(parameter);

        if (bindingKey === '') {
            return '';
        }

        return values[bindingKey] ?? '';
    }

    function setMissingBindings(bindings) {
        missing.value = Array.isArray(bindings)
            ? bindings
                  .map((value) => normalizeBindingName(value))
                  .filter((value) => value !== '')
            : [];
    }

    function hasError(parameter) {
        const bindingKey = normalizeBindingName(parameter);

        if (bindingKey === '') {
            return false;
        }

        return missing.value.includes(bindingKey);
    }

    function clearError(parameter) {
        const bindingKey = normalizeBindingName(parameter);

        if (bindingKey === '') {
            return;
        }

        missing.value = missing.value.filter((key) => key !== bindingKey);
    }

    function sourceOptionsFor(row, index = 0) {
        return optionsByBinding[cacheKeyForRow(row, index)] || [];
    }

    function sourceLoadingFor(row, index = 0) {
        return loadingByBinding[cacheKeyForRow(row, index)] === true;
    }

    function clearSourceState() {
        warning.value = '';

        Object.keys(optionsByBinding).forEach((key) => {
            delete optionsByBinding[key];
        });

        Object.keys(loadingByBinding).forEach((key) => {
            delete loadingByBinding[key];
        });
    }

    async function loadSourceOptionsForRow(row, index = 0) {
        if (!isSourceSelectBindingType(row?.type)) {
            return;
        }

        const sourceTableId = Number(row?.source_table_id || 0);
        const cacheKey = cacheKeyForRow(row, index);

        if (sourceTableId <= 0) {
            optionsByBinding[cacheKey] = [];

            return;
        }

        loadingByBinding[cacheKey] = true;

        try {
            const options = await fetchSourceOptions(sourceTableId);

            optionsByBinding[cacheKey] = Array.isArray(options) ? options : [];
        } catch (error) {
            optionsByBinding[cacheKey] = [];
            warning.value =
                String(error?.message || '').trim() ||
                'Bronselectie-opties konden niet geladen worden.';
        } finally {
            loadingByBinding[cacheKey] = false;
        }
    }

    async function loadSourceOptionsForRows(rows) {
        const sourceRows = Array.isArray(rows) ? rows : [];

        await Promise.all(
            sourceRows.map((row, index) => loadSourceOptionsForRow(row, index)),
        );
    }

    function collectMissingParameters(rows) {
        return (Array.isArray(rows) ? rows : [])
            .flatMap((row) => {
                const parameter = normalizeBindingName(row?.parameter);
                const parameterTo = normalizeBindingName(row?.parameter_to);
                const keys = [];

                if (
                    parameter !== '' &&
                    isMissingBindingValue(getValue(parameter))
                ) {
                    keys.push(parameter);
                }

                if (
                    parameterTo !== '' &&
                    isMissingBindingValue(getValue(parameterTo))
                ) {
                    keys.push(parameterTo);
                }

                return keys;
            })
            .filter((value, index, array) => array.indexOf(value) === index);
    }

    return {
        values,
        missing,
        warning,
        ensureValue,
        setValue,
        getValue,
        setMissingBindings,
        hasError,
        clearError,
        sourceOptionsFor,
        sourceLoadingFor,
        clearSourceState,
        loadSourceOptionsForRow,
        loadSourceOptionsForRows,
        collectMissingParameters,
    };
}
