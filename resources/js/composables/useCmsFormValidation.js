import FieldValidationMessage from '@/Components/Validation/FieldValidationMessage.vue';
import FormValidationStatus from '@/Components/Validation/FormValidationStatus.vue';
import FormValidationSummary from '@/Components/Validation/FormValidationSummary.vue';
import { useFieldValidation } from '@/composables/useFieldValidation';
import { useFormValidationState } from '@/composables/useFormValidationState';
import { cmsSeoFieldRules } from '@/ValidationRules/CmsSeoRules';
import rules from '@/ValidationRules/Rules';
import { computed } from 'vue';

export function useCmsFormValidation(form, options) {
    const {
        fields,
        activateTab = null,
        serverFields = {},
        messages = {},
    } = options;
    const validation = useFieldValidation(fields);
    const formValidation = useFormValidationState(validation, { activateTab });
    const serverValidationErrors = computed(() => {
        return Object.entries(form.errors || {})
            .map(([name, error]) => {
                const message = normalizeErrorMessage(error);

                if (message === '') {
                    return null;
                }

                const field = resolveFieldMeta(name);

                return {
                    name,
                    label: field.label,
                    tab: field.tab,
                    elementId: field.elementId,
                    error: message,
                    source: 'server',
                };
            })
            .filter(Boolean);
    });
    const allValidationErrors = computed(() => {
        const serverNames = new Set(
            serverValidationErrors.value.map((issue) => issue.name),
        );
        const clientErrors = validation.errors.value
            .filter((issue) => !serverNames.has(issue.name))
            .map((issue) => ({ ...issue, source: 'client' }));

        return [...clientErrors, ...serverValidationErrors.value];
    });
    const showValidationSummary = computed(
        () =>
            formValidation.showSummary.value ||
            serverValidationErrors.value.length > 0,
    );
    const validationFlash = computed(() => {
        const details = formValidation.showSummary.value
            ? allValidationErrors.value
            : serverValidationErrors.value;

        if (details.length > 0) {
            const hasClientErrors = details.some(
                (issue) => issue.source !== 'server',
            );

            return {
                type: 'danger',
                message: hasClientErrors
                    ? messages.blocked ||
                      messages.client ||
                      'Saving is blocked. Check the validation messages below.'
                    : messages.server ||
                      'Saving failed. Check the validation messages below.',
                details,
            };
        }

        return { type: 'info', message: '', details: [] };
    });

    function message(name) {
        return validation.message(name, form.errors?.[name] ?? '');
    }

    function warning(name) {
        return validation.warning(name);
    }

    function counterMax(name) {
        const max = fields[name]?.counterMax;

        return typeof max === 'function' ? max() : (max ?? null);
    }

    function touch(name) {
        validation.touch(name);
    }

    function clearServerError(name) {
        if (form.errors?.[name]) {
            form.clearErrors(name);
        }
    }

    function touchAndClear(name) {
        clearServerError(name);
        touch(name);
    }

    function isRequired(name) {
        const required = fields[name]?.required;

        if (typeof required === 'function') {
            return Boolean(required());
        }

        return Boolean(required);
    }

    function requiredClass(name) {
        return isRequired(name) ? 'bg-yellow-50' : '';
    }

    function requiredAttrs(name) {
        return isRequired(name)
            ? { required: true, 'aria-required': 'true' }
            : { required: false, 'aria-required': 'false' };
    }

    function resolveFieldMeta(name) {
        const field = fields[name] || matchingFieldMeta(name);

        return {
            label:
                resolveMetaValue(field?.label, name) || humanizeFieldName(name),
            tab: field?.tab ?? null,
            elementId: resolveMetaValue(field?.elementId, name) || name,
        };
    }

    function matchingFieldMeta(name) {
        if (serverFields[name]) {
            return serverFields[name];
        }

        return Object.entries(serverFields).find(([pattern]) => {
            return wildcardMatches(pattern, name);
        })?.[1];
    }

    return {
        validation,
        formValidation,
        allValidationErrors,
        FieldValidationMessage,
        FormValidationStatus,
        FormValidationSummary,
        serverValidationErrors,
        showValidationSummary,
        validationFlash,
        message,
        warning,
        counterMax,
        touch,
        touchAndClear,
        isRequired,
        requiredAttrs,
        requiredClass,
        rules,
    };
}

function resolveMetaValue(value, name) {
    return typeof value === 'function' ? value(name) : value;
}

function normalizeErrorMessage(error) {
    if (Array.isArray(error)) {
        return String(error[0] || '').trim();
    }

    return String(error || '').trim();
}

function wildcardMatches(pattern, name) {
    if (!pattern.includes('*')) {
        return pattern === name;
    }

    const escaped = pattern
        .split('*')
        .map((part) => part.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'))
        .join('[^.]+');

    return new RegExp(`^${escaped}(?:\\..*)?$`).test(name);
}

function humanizeFieldName(name) {
    return String(name || '')
        .replace(/\.\d+(?=\.|$)/g, '')
        .replace(/[_.]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

export function createCmsSeoFields(form, options) {
    const { t, seoSettings, structuredDataField = true } = options;
    const seo = computed(() => seoSettings?.value ?? seoSettings ?? {});
    const messageFactory = {
        fields: {
            h1: t('seo.fields.h1', 'H1'),
            slug: t('seo.fields.slug', 'Slug'),
            seoTitle: t('seo.fields.meta_title', 'SEO titel'),
            seoDescription: t(
                'seo.fields.meta_description',
                'SEO omschrijving',
            ),
            canonicalUrl: t('seo.fields.url', 'URL'),
        },
        required: t('validation.required', 'Dit veld is verplicht.'),
        slug: t(
            'validation.slug',
            'Gebruik alleen kleine letters, cijfers en streepjes.',
        ),
        urlOrPath: t(
            'validation.url_or_path',
            'Gebruik een relatieve URL of volledige http(s)-URL.',
        ),
        json: t('validation.json', 'Gebruik geldige JSON.'),
        min: (field, min, value) =>
            t('validation.min_chars', ':field is te kort (:current/:min).', {
                field,
                min,
                current: String(value ?? '').length,
            }),
        max: (field, max, value) =>
            t('validation.max_chars', ':field is te lang (:current/:max).', {
                field,
                max,
                current: String(value ?? '').length,
            }),
    };
    const fieldRules = computed(() =>
        cmsSeoFieldRules(seo.value, messageFactory, {
            status: () => form.status,
        }),
    );

    const fields = {
        title: {
            label: t('content_form.title', 'Titel'),
            elementId: 'title',
            value: () => form.title,
            rules: [(value) => fieldRules.value.title[0](value)],
            warnings: [(value) => fieldRules.value.title[1](value)],
            counterMax: () => seo.value.seo_h1_max_length,
        },
        slug: {
            label: t('content_form.slug', 'Slug'),
            elementId: 'slug',
            value: () => form.slug,
            rules: [
                (value) => fieldRules.value.slug[0](value),
                (value) => fieldRules.value.slug[1](value),
                (value) => fieldRules.value.slug[2](value),
                (value) => fieldRules.value.slug[3](value),
            ],
            counterMax: () => seo.value.seo_slug_max_length,
        },
        seo_title: {
            label: t('content_form.seo_title', 'SEO titel'),
            tab: 'seo',
            elementId: 'seo_title',
            value: () => form.seo_title,
            rules: [(value) => fieldRules.value.seo_title[0](value)],
            warnings: [
                (value) => fieldRules.value.seo_title[1](value),
                (value) => fieldRules.value.seo_title[2](value),
            ],
            counterMax: () => seo.value.seo_meta_title_max_length,
        },
        seo_description: {
            label: t('content_form.seo_description_label', 'SEO omschrijving'),
            tab: 'seo',
            elementId: 'seo_description',
            value: () => form.seo_description,
            rules: [(value) => fieldRules.value.seo_description[0](value)],
            warnings: [
                (value) => fieldRules.value.seo_description[1](value),
                (value) => fieldRules.value.seo_description[2](value),
            ],
            counterMax: () => seo.value.seo_meta_description_max_length,
        },
        canonical_url: {
            label: t('content_form.canonical_url', 'Canonical URL'),
            tab: 'seo',
            elementId: 'canonical_url',
            value: () => form.canonical_url,
            rules: [(value) => fieldRules.value.canonical_url[1](value)],
            warnings: [(value) => fieldRules.value.canonical_url[0](value)],
            counterMax: () => seo.value.seo_url_max_length,
        },
    };

    if (structuredDataField) {
        fields.structured_data_extra = {
            label: t('content_form.json_ld', 'JSON-LD'),
            tab: 'json_ld',
            elementId: 'structured_data_extra',
            value: () => form.structured_data_extra,
            rules: [
                (value) => fieldRules.value.structured_data_extra[0](value),
                (value) => fieldRules.value.structured_data_extra[1](value),
            ],
        };
    }

    return fields;
}
