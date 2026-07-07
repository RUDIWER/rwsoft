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
        (carry, [token, replacement]) =>
            carry.replaceAll(`:${token}`, String(replacement ?? '')),
        String(template || ''),
    );
}

export function useAdminTranslations(source = 'admin_common_ui') {
    const page = usePage();

    const messages = computed(() => {
        const translations = page.props?.app?.translations?.[source];

        return translations && typeof translations === 'object'
            ? translations
            : {};
    });

    const adminCommonMessages = computed(() => {
        const translations = page.props?.app?.translations?.admin_common_ui;

        return translations && typeof translations === 'object'
            ? translations
            : {};
    });

    function fallbackTranslation(key) {
        if (source === 'admin_common_ui') {
            return null;
        }

        const directFallback = getNestedTranslation(
            adminCommonMessages.value,
            key,
        );

        if (
            typeof directFallback === 'string' &&
            directFallback.trim() !== ''
        ) {
            return directFallback;
        }

        if (String(key || '').startsWith('common.columns.')) {
            return getNestedTranslation(
                adminCommonMessages.value,
                String(key).replace(/^common\./, ''),
            );
        }

        return null;
    }

    function t(key, fallback = '', replacements = {}) {
        const translated =
            getNestedTranslation(messages.value, key) ??
            fallbackTranslation(key);
        const resolved =
            typeof translated === 'string' && translated.trim() !== ''
                ? translated
                : fallback || key;

        return interpolateTranslation(resolved, replacements);
    }

    return {
        messages,
        t,
    };
}
