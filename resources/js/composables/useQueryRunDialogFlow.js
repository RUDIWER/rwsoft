import {
    isMissingBindingValue,
    isSystemBindingName,
} from '@/composables/useQueryBindings';
import { ref } from 'vue';

/**
 * Orkestreert de volledige run-flow vanuit QueryForm:
 * - SQL inspectie
 * - enkel ontbrekende bindings opvragen
 * - submit + navigatie naar runtime query
 */

export function useQueryRunDialogFlow({
    isNew,
    getQuerySql,
    requiredBindingNames,
    buildRunBindingsMeta,
    runDialogState,
    openRunPageWithBindings,
    inspectRouteName = 'admin.queries.builder.inspect',
}) {
    const inspectResult = ref({ valid: null, message: '', bindings: [] });
    const runDialogOpen = ref(false);
    const runDialogPreparing = ref(false);
    const runDialogSubmitting = ref(false);
    const runDialogBindingsMeta = ref([]);
    const runAllBindingNames = ref([]);

    function openRunPage(payload = {}) {
        openRunPageWithBindings(payload, () => {
            runDialogPreparing.value = false;
            runDialogSubmitting.value = false;
        });
    }

    async function inspectSql() {
        inspectResult.value = { valid: null, message: '', bindings: [] };

        try {
            const response = await window.axios.post(route(inspectRouteName), {
                query: String(getQuerySql() || ''),
            });

            inspectResult.value = {
                valid: Boolean(response?.data?.valid ?? false),
                message: String(response?.data?.message || ''),
                bindings: Array.isArray(response?.data?.bindings)
                    ? response.data.bindings
                          .map((binding) => String(binding || '').trim())
                          .filter((binding) => binding !== '')
                    : [],
            };
        } catch (error) {
            inspectResult.value = {
                valid: false,
                message:
                    String(error?.response?.data?.message || '').trim() ||
                    'SQL inspectie mislukt.',
                bindings: [],
            };
        }

        return inspectResult.value;
    }

    async function openRunDialogOrNavigate() {
        if (isNew?.value) {
            return;
        }

        runDialogPreparing.value = true;
        runDialogState.warning.value = '';
        runDialogBindingsMeta.value = [];
        runDialogState.setMissingBindings([]);
        runDialogState.clearSourceState();

        let bindingNames = Array.isArray(requiredBindingNames?.value)
            ? [...requiredBindingNames.value]
            : [];
        const sql = String(getQuerySql() || '').trim();

        if (sql !== '') {
            const inspection = await inspectSql();

            if (inspection.valid === false) {
                const message = String(inspection.message || '').trim();

                runDialogState.warning.value =
                    message !== ''
                        ? `Query inspectie fout: ${message}`
                        : 'Query inspectie fout. Controleer eerst de SQL.';
                runDialogPreparing.value = false;

                return;
            }

            if (inspection.bindings.length > 0) {
                bindingNames = inspection.bindings;
            }
        }

        runAllBindingNames.value = bindingNames;
        const allMeta = buildRunBindingsMeta(bindingNames);

        if (allMeta.length === 0) {
            openRunPage();

            return;
        }

        allMeta.forEach((binding) => {
            runDialogState.ensureValue(binding.parameter, '');
        });

        const missingMeta = allMeta.filter((binding) =>
            isMissingBindingValue(runDialogState.getValue(binding.parameter)),
        );

        if (missingMeta.length === 0) {
            const payload = {};

            runAllBindingNames.value.forEach((bindingName) => {
                if (isSystemBindingName(bindingName)) {
                    return;
                }

                payload[bindingName] = String(
                    runDialogState.getValue(bindingName) || '',
                ).trim();
            });

            openRunPage(payload);

            return;
        }

        runDialogBindingsMeta.value = missingMeta;
        try {
            await runDialogState.loadSourceOptionsForRows(missingMeta);
            runDialogOpen.value = true;
        } finally {
            runDialogPreparing.value = false;
        }
    }

    function submitRunDialog() {
        runDialogSubmitting.value = true;
        runDialogState.warning.value = '';
        let startedNavigation = false;

        try {
            const payload = {};
            const missing = runDialogState.collectMissingParameters(
                runDialogBindingsMeta.value,
            );

            runDialogBindingsMeta.value.forEach((binding) => {
                const value = runDialogState.getValue(binding.parameter);
                payload[binding.parameter] = String(value || '').trim();
            });

            if (missing.length > 0) {
                runDialogState.setMissingBindings(missing);
                runDialogState.warning.value = `Vul alle variabelen in: ${missing.join(', ')}.`;

                return;
            }

            runAllBindingNames.value.forEach((bindingName) => {
                if (
                    Object.prototype.hasOwnProperty.call(payload, bindingName)
                ) {
                    return;
                }

                if (isSystemBindingName(bindingName)) {
                    return;
                }

                const currentValue = runDialogState.getValue(bindingName);
                const normalized = String(currentValue || '').trim();

                if (normalized !== '') {
                    payload[bindingName] = normalized;
                }
            });

            startedNavigation = true;
            openRunPage(payload);
            runDialogOpen.value = false;
        } finally {
            if (!startedNavigation) {
                runDialogSubmitting.value = false;
            }
        }
    }

    function closeRunDialog() {
        runDialogState.setMissingBindings([]);
        runDialogState.warning.value = '';
        runDialogState.clearSourceState();
        runDialogOpen.value = false;
    }

    return {
        inspectResult,
        inspectSql,
        runDialogOpen,
        runDialogPreparing,
        runDialogSubmitting,
        runDialogBindingsMeta,
        openRunDialogOrNavigate,
        submitRunDialog,
        closeRunDialog,
    };
}
