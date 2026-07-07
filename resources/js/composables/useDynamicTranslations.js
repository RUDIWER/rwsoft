import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

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

export function useDynamicTranslations() {
    const page = usePage();

    const messages = computed(() => {
        const dynamicPrompts = page.props?.app?.translations?.dynamic_prompts;

        if (dynamicPrompts && typeof dynamicPrompts === 'object') {
            return dynamicPrompts;
        }

        return {};
    });

    function t(key, fallback = '', replacements = {}) {
        const translated = getNestedTranslation(messages.value, key);
        const resolved =
            typeof translated === 'string' && translated.trim() !== ''
                ? translated
                : fallback || key;

        return interpolateTranslation(resolved, replacements);
    }

    function resolveText(key, fallback = '', replacements = {}) {
        const normalizedKey = String(key || '').trim();

        if (normalizedKey === '') {
            return interpolateTranslation(fallback || '', replacements);
        }

        return t(normalizedKey, fallback, replacements);
    }

    return {
        messages,
        t,
        resolveText,
    };
}
