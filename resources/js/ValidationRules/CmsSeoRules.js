import rules from './Rules';

export function defaultSeoSettings(overrides = {}) {
    return {
        seo_h1_min_length: 20,
        seo_h1_max_length: 70,
        seo_h2_max_length: 90,
        seo_h3_max_length: 100,
        seo_meta_title_min_length: 30,
        seo_meta_title_max_length: 60,
        seo_meta_description_min_length: 120,
        seo_meta_description_max_length: 160,
        seo_slug_min_length: 3,
        seo_slug_max_length: 80,
        seo_url_max_length: 2000,
        seo_content_min_words: 80,
        seo_require_meta_title_on_publish: true,
        seo_require_meta_description_on_publish: true,
        seo_require_json_ld: false,
        ...overrides,
    };
}

export function cmsSeoFieldRules(settings, messages, context = {}) {
    const seo = defaultSeoSettings(settings);
    const isPublishing = () => context.status?.() === 'published';
    const labels = messages.fields ?? {};

    return {
        title: [
            (value) => rules.required(value, messages.required),
            (value) => rules.max(seo.seo_h1_max_length, value, messages.max(labels.h1 ?? 'H1', seo.seo_h1_max_length, value)),
        ],
        slug: [
            (value) => rules.required(value, messages.required),
            (value) => rules.min(seo.seo_slug_min_length, value, messages.min(labels.slug ?? 'Slug', seo.seo_slug_min_length, value)),
            (value) => rules.max(seo.seo_slug_max_length, value, messages.max(labels.slug ?? 'Slug', seo.seo_slug_max_length, value)),
            (value) => rules.slug(value, messages.slug),
        ],
        seo_title: [
            (value) => !isPublishing() || !seo.seo_require_meta_title_on_publish || rules.required(value, messages.required),
            (value) => rules.min(seo.seo_meta_title_min_length, value, messages.min(labels.seoTitle ?? 'SEO titel', seo.seo_meta_title_min_length, value)),
            (value) => rules.max(seo.seo_meta_title_max_length, value, messages.max(labels.seoTitle ?? 'SEO titel', seo.seo_meta_title_max_length, value)),
        ],
        seo_description: [
            (value) => !isPublishing() || !seo.seo_require_meta_description_on_publish || rules.required(value, messages.required),
            (value) => rules.min(seo.seo_meta_description_min_length, value, messages.min(labels.seoDescription ?? 'SEO omschrijving', seo.seo_meta_description_min_length, value)),
            (value) => rules.max(seo.seo_meta_description_max_length, value, messages.max(labels.seoDescription ?? 'SEO omschrijving', seo.seo_meta_description_max_length, value)),
        ],
        canonical_url: [
            (value) => rules.max(seo.seo_url_max_length, value, messages.max(labels.canonicalUrl ?? 'Canonical URL', seo.seo_url_max_length, value)),
            (value) => rules.urlOrPath(value, messages.urlOrPath),
        ],
        structured_data_extra: [
            (value) => !isPublishing() || !seo.seo_require_json_ld || rules.required(value, messages.required),
            (value) => rules.json(value, messages.json),
        ],
    };
}
