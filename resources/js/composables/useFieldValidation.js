import { computed, reactive, watch } from 'vue';

export function useFieldValidation(fields) {
    const state = reactive({});

    Object.entries(fields).forEach(([name, config]) => {
        state[name] = {
            label: config.label ?? name,
            tab: config.tab ?? null,
            touched: false,
            error: '',
            warning: '',
            elementId: config.elementId ?? name,
        };

        watch(
            () => config.value(),
            () => {
                if (state[name].touched) {
                    validateField(name);
                }
            },
        );
    });

    const errors = computed(() =>
        Object.entries(state)
            .filter(([, field]) => field.error)
            .map(([name, field]) => ({ name, ...field })),
    );

    const warnings = computed(() =>
        Object.entries(state)
            .filter(([, field]) => field.warning)
            .map(([name, field]) => ({ name, ...field })),
    );

    const isValid = computed(() => errors.value.length === 0);

    function touch(name) {
        if (!state[name]) {
            return true;
        }

        state[name].touched = true;

        return validateField(name);
    }

    function touchAll() {
        Object.keys(state).forEach((name) => touch(name));

        return isValid.value;
    }

    function validateField(name) {
        const config = fields[name];

        if (!config) {
            return true;
        }

        const value = config.value();
        state[name].error = '';
        state[name].warning = '';

        for (const rule of config.rules ?? []) {
            const result = rule(value);

            if (result !== true) {
                state[name].error = String(result);
                return false;
            }
        }

        for (const rule of config.warnings ?? []) {
            const result = rule(value);

            if (result !== true) {
                state[name].warning = String(result);
                break;
            }
        }

        return true;
    }

    function message(name, serverError = '') {
        if (serverError) {
            return serverError;
        }

        return state[name]?.touched ? state[name]?.error || '' : '';
    }

    function warning(name) {
        return state[name]?.touched ? state[name]?.warning || '' : '';
    }

    return {
        state,
        errors,
        warnings,
        isValid,
        touch,
        touchAll,
        message,
        warning,
    };
}
