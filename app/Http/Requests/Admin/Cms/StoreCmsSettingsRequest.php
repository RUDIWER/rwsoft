<?php

namespace App\Http\Requests\Admin\Cms;

use App\Models\Cms\CmsPage;
use App\Support\Cms\CmsMediaSettings;
use App\Support\Localization\AdminLocaleResolver;
use App\Support\PublicSite\CmsLanguageSettings;
use App\Support\PublicSite\CmsSearchConsoleSettings;
use App\Support\PublicSite\CmsVisitorTrackingSettings;
use App\Support\PublicSite\PublicSiteLocaleDetector;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCmsSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $activeLocales = app(CmsLanguageSettings::class)->activeLocales();
        $providers = collect(array_keys((array) config('translation_editor.ai.providers', [])))
            ->map(static fn (mixed $provider): string => trim((string) $provider))
            ->filter(static fn (string $provider): bool => $provider !== '')
            ->values()
            ->all();

        return [
            'site_name' => ['required', 'string', 'max:160'],
            'site_tagline' => ['nullable', 'string', 'max:255'],
            'default_locale' => ['required', 'string', 'max:12', Rule::in($activeLocales)],
            'multilingual_enabled' => ['sometimes', 'boolean'],
            'homepage_id' => ['nullable', 'integer', 'exists:cms_pages,id'],
            'auto_locale_detection_enabled' => ['sometimes', 'boolean'],
            'auto_locale_detection_strategy' => ['required', 'string', Rule::in([
                PublicSiteLocaleDetector::STRATEGY_BROWSER,
                PublicSiteLocaleDetector::STRATEGY_IP,
                PublicSiteLocaleDetector::STRATEGY_BROWSER_THEN_IP,
            ])],
            'auto_locale_redirect_enabled' => ['sometimes', 'boolean'],
            'auto_locale_remember_choice' => ['sometimes', 'boolean'],
            'auto_locale_cookie_days' => ['required', 'integer', 'min:1', 'max:730'],
            'auto_locale_country_map' => ['nullable', 'string', 'max:5000'],
            'visitor_tracking_enabled' => ['sometimes', 'boolean'],
            'visitor_tracking_retention_mode' => ['required', 'string', Rule::in([
                CmsVisitorTrackingSettings::RETENTION_DAYS,
                CmsVisitorTrackingSettings::RETENTION_ALWAYS,
            ])],
            'visitor_tracking_retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'visitor_tracking_cookie_days' => ['required', 'integer', 'min:1', 'max:730'],
            'visitor_tracking_store_ip' => ['sometimes', 'boolean'],
            'visitor_tracking_store_ip_hash' => ['sometimes', 'boolean'],
            'visitor_tracking_ignore_bots' => ['sometimes', 'boolean'],
            'visitor_tracking_excluded_paths' => ['nullable', 'string', 'max:5000'],
            'visitor_tracking_geo_enabled' => ['sometimes', 'boolean'],
            'visitor_tracking_geo_provider' => ['required', 'string', Rule::in(array_keys(app(CmsVisitorTrackingSettings::class)->geoProviderOptions()))],
            'visitor_tracking_geo_allowed_countries' => ['nullable', 'string', 'max:2000'],
            'visitor_tracking_geo_delete_disallowed_countries' => ['sometimes', 'boolean'],
            'search_console_enabled' => ['sometimes', 'boolean'],
            'search_console_property_type' => ['required', 'string', Rule::in(array_keys(app(CmsSearchConsoleSettings::class)->propertyTypeOptions()))],
            'search_console_site_url' => ['nullable', 'string', 'max:255'],
            'search_console_analytics_cache_seconds' => ['required', 'integer', 'min:60', 'max:604800'],
            'search_console_inspection_cache_seconds' => ['required', 'integer', 'min:60', 'max:604800'],
            'search_console_query_limit' => ['required', 'integer', 'min:1', 'max:25'],
            'contact_company_name' => ['nullable', 'string', 'max:160'],
            'contact_street' => ['nullable', 'string', 'max:180'],
            'contact_postal_code' => ['nullable', 'string', 'max:40'],
            'contact_city' => ['nullable', 'string', 'max:120'],
            'contact_country' => ['nullable', 'string', 'max:120'],
            'contact_country_code' => ['nullable', 'string', 'max:12'],
            'contact_phone_1_label' => ['nullable', 'string', 'max:80'],
            'contact_phone_1' => ['nullable', 'string', 'max:80'],
            'contact_phone_2_label' => ['nullable', 'string', 'max:80'],
            'contact_phone_2' => ['nullable', 'string', 'max:80'],
            'contact_phone_3_label' => ['nullable', 'string', 'max:80'],
            'contact_phone_3' => ['nullable', 'string', 'max:80'],
            'contact_email_1_label' => ['nullable', 'string', 'max:80'],
            'contact_email_1' => ['nullable', 'email:rfc', 'max:160'],
            'contact_email_2_label' => ['nullable', 'string', 'max:80'],
            'contact_email_2' => ['nullable', 'email:rfc', 'max:160'],
            'contact_vat_number' => ['nullable', 'string', 'max:80'],
            'contact_image_media_asset_id' => ['nullable', 'integer', 'exists:cms_media_assets,id'],
            'company_logo_media_asset_id' => ['nullable', 'integer', 'exists:cms_media_assets,id'],
            'public_text_cache_enabled' => ['sometimes', 'boolean'],
            'public_text_cache_ttl' => ['required', 'integer', 'min:0', 'max:86400'],
            CmsMediaSettings::MAX_IMAGE_UPLOAD_FIELD => [
                'required',
                'integer',
                'min:'.(int) config('cms_media.max_image_upload_mb_min', 1),
                'max:'.(int) config('cms_media.max_image_upload_mb_max', 100),
            ],
            'seo_default_title' => ['nullable', 'string', 'max:255'],
            'seo_default_description' => ['nullable', 'string', 'max:500'],
            'seo_h1_min_length' => ['required', 'integer', 'min:1', 'max:160'],
            'seo_h1_max_length' => ['required', 'integer', 'min:1', 'max:220'],
            'seo_h2_max_length' => ['required', 'integer', 'min:1', 'max:220'],
            'seo_h3_max_length' => ['required', 'integer', 'min:1', 'max:220'],
            'seo_meta_title_min_length' => ['required', 'integer', 'min:1', 'max:160'],
            'seo_meta_title_max_length' => ['required', 'integer', 'min:1', 'max:220'],
            'seo_meta_description_min_length' => ['required', 'integer', 'min:1', 'max:320'],
            'seo_meta_description_max_length' => ['required', 'integer', 'min:1', 'max:500'],
            'seo_slug_min_length' => ['required', 'integer', 'min:1', 'max:80'],
            'seo_slug_max_length' => ['required', 'integer', 'min:1', 'max:180'],
            'seo_url_max_length' => ['required', 'integer', 'min:120', 'max:5000'],
            'seo_content_min_words' => ['required', 'integer', 'min:0', 'max:5000'],
            'seo_require_meta_title_on_publish' => ['sometimes', 'boolean'],
            'seo_require_meta_description_on_publish' => ['sometimes', 'boolean'],
            'seo_require_single_h1' => ['sometimes', 'boolean'],
            'seo_require_valid_heading_hierarchy' => ['sometimes', 'boolean'],
            'seo_require_json_ld' => ['sometimes', 'boolean'],
            'seo_require_og_image_for_posts' => ['sometimes', 'boolean'],
            'setting_translations' => ['nullable', 'array'],
            'setting_translations.*.site_name' => ['nullable', 'string', 'max:160'],
            'setting_translations.*.site_tagline' => ['nullable', 'string', 'max:255'],
            'setting_translations.*.seo_default_title' => ['nullable', 'string', 'max:255'],
            'setting_translations.*.seo_default_description' => ['nullable', 'string', 'max:500'],
            'global_noindex' => ['sometimes', 'boolean'],
            'robots_extra_rules' => ['nullable', 'string', 'max:10000'],
            'logo_show_tagline' => ['sometimes', 'boolean'],
            'favicon_file' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png',
                'max:4096',
                'dimensions:min_width=64,min_height=64,max_width=4096,max_height=4096',
            ],
            'logo_file' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png',
                'max:4096',
                'dimensions:min_width=64,min_height=32,max_width=4096,max_height=4096',
            ],
            'translation_ai' => ['required', 'array'],
            'translation_ai.provider' => ['required', 'string', Rule::in($providers)],
            'translation_ai.model' => ['required', 'string', 'max:160'],
            'translation_ai.api_key' => ['nullable', 'string', 'max:5000'],
            'translation_ai.clear_api_key' => ['nullable', 'boolean'],
            'translation_ai.fill_limit_default' => ['required', 'integer', 'min:1', 'max:5000'],
            'translation_ai.fill_limit_max' => ['required', 'integer', 'min:1', 'max:5000'],
            'visitor_tracking' => ['required', 'array'],
            'visitor_tracking.geo_api_key' => ['nullable', 'string', 'max:5000'],
            'visitor_tracking.clear_geo_api_key' => ['nullable', 'boolean'],
            'search_console' => ['required', 'array'],
            'search_console.oauth_client_id' => ['nullable', 'string', 'max:500'],
            'search_console.oauth_client_secret' => ['nullable', 'string', 'max:500'],
            'search_console.clear_oauth_client_secret' => ['nullable', 'boolean'],
            'admin_settings' => ['required', 'array'],
            'admin_settings.admin_default_locale' => ['required', 'string', Rule::in(config('app.available_locales', [config('app.locale', 'en')]))],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateHomepageLocale($validator);
                $this->validateDefaultSettingTranslations($validator);
                $this->validateSeoSettingRanges($validator);
                $this->validateRobotsExtraRules($validator);
                $this->validateAutoLocaleCountryMap($validator);
                $this->validateVisitorTrackingExcludedPaths($validator);
                $this->validateVisitorTrackingCountryList($validator);
                $this->validateSearchConsoleSettings($validator);
                $this->validateTranslationAiSettings($validator);
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $translationAi = (array) $this->input('translation_ai', []);
        $visitorTracking = (array) $this->input('visitor_tracking', []);
        $searchConsole = (array) $this->input('search_console', []);
        $adminDefaultLocale = trim((string) $this->input('admin_settings.admin_default_locale', AdminLocaleResolver::DEFAULT_ADMIN_LOCALE));

        $this->merge([
            'admin_settings' => [
                'admin_default_locale' => $adminDefaultLocale,
            ],
            'translation_ai' => [
                'provider' => trim((string) Arr::get($translationAi, 'provider', '')),
                'model' => trim((string) Arr::get($translationAi, 'model', '')),
                'api_key' => trim((string) Arr::get($translationAi, 'api_key', '')) ?: null,
                'clear_api_key' => (bool) filter_var(
                    Arr::get($translationAi, 'clear_api_key', false),
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE,
                ),
                'fill_limit_default' => (int) Arr::get($translationAi, 'fill_limit_default', 100),
                'fill_limit_max' => (int) Arr::get($translationAi, 'fill_limit_max', 500),
            ],
            'visitor_tracking' => [
                'geo_api_key' => trim((string) Arr::get($visitorTracking, 'geo_api_key', '')) ?: null,
                'clear_geo_api_key' => (bool) filter_var(
                    Arr::get($visitorTracking, 'clear_geo_api_key', false),
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE,
                ),
            ],
            'search_console' => [
                'oauth_client_id' => trim((string) Arr::get($searchConsole, 'oauth_client_id', '')) ?: null,
                'oauth_client_secret' => trim((string) Arr::get($searchConsole, 'oauth_client_secret', '')) ?: null,
                'clear_oauth_client_secret' => (bool) filter_var(
                    Arr::get($searchConsole, 'clear_oauth_client_secret', false),
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE,
                ),
            ],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'site_name.required' => 'Vul een sitenaam in.',
            'default_locale.required' => 'Vul een standaardtaal in.',
            'default_locale.in' => 'Kies een actieve taal als standaardtaal.',
            'homepage_id.exists' => 'De gekozen homepage bestaat niet.',
            'auto_locale_detection_strategy.in' => __('cms_admin_ui.settings.form.auto_locale_detection_strategy_invalid'),
            'auto_locale_cookie_days.required' => __('cms_admin_ui.settings.form.auto_locale_cookie_days_required'),
            'auto_locale_cookie_days.integer' => __('cms_admin_ui.settings.form.auto_locale_cookie_days_integer'),
            'auto_locale_cookie_days.min' => __('cms_admin_ui.settings.form.auto_locale_cookie_days_range'),
            'auto_locale_cookie_days.max' => __('cms_admin_ui.settings.form.auto_locale_cookie_days_range'),
            'visitor_tracking_retention_mode.in' => __('cms_admin_ui.settings.form.visitor_tracking_retention_mode_invalid'),
            'visitor_tracking_retention_days.required' => __('cms_admin_ui.settings.form.visitor_tracking_retention_days_required'),
            'visitor_tracking_retention_days.integer' => __('cms_admin_ui.settings.form.visitor_tracking_retention_days_integer'),
            'visitor_tracking_retention_days.min' => __('cms_admin_ui.settings.form.visitor_tracking_retention_days_range'),
            'visitor_tracking_retention_days.max' => __('cms_admin_ui.settings.form.visitor_tracking_retention_days_range'),
            'visitor_tracking_cookie_days.required' => __('cms_admin_ui.settings.form.visitor_tracking_cookie_days_required'),
            'visitor_tracking_cookie_days.integer' => __('cms_admin_ui.settings.form.visitor_tracking_cookie_days_integer'),
            'visitor_tracking_cookie_days.min' => __('cms_admin_ui.settings.form.visitor_tracking_cookie_days_range'),
            'visitor_tracking_cookie_days.max' => __('cms_admin_ui.settings.form.visitor_tracking_cookie_days_range'),
            'visitor_tracking_geo_provider.in' => __('cms_admin_ui.settings.form.visitor_tracking_geo_provider_invalid'),
            'visitor_tracking.geo_api_key.max' => __('cms_admin_ui.settings.form.visitor_tracking_geo_api_key_max'),
            'search_console_property_type.in' => __('cms_admin_ui.settings.form.search_console_property_type_invalid'),
            'search_console_site_url.max' => __('cms_admin_ui.settings.form.search_console_site_url_max'),
            'search_console.oauth_client_id.max' => __('cms_admin_ui.settings.form.search_console_oauth_client_id_max'),
            'search_console.oauth_client_secret.max' => __('cms_admin_ui.settings.form.search_console_oauth_client_secret_max'),
            'robots_extra_rules.max' => 'Robots.txt extra regels mogen maximaal 10 KB bevatten.',
            'favicon_file.image' => 'De favicon moet een geldige JPG of PNG afbeelding zijn.',
            'favicon_file.mimes' => 'De favicon moet een JPG of PNG afbeelding zijn.',
            'favicon_file.max' => 'De favicon mag maximaal 4MB zijn.',
            'favicon_file.dimensions' => 'De favicon moet minstens 64x64 pixels zijn en maximaal 4096x4096 pixels.',
            'logo_file.image' => 'Het logo moet een geldige JPG of PNG afbeelding zijn.',
            'logo_file.mimes' => 'Het logo moet een JPG of PNG afbeelding zijn.',
            'logo_file.max' => 'Het logo mag maximaal 4MB zijn.',
            'logo_file.dimensions' => 'Het logo moet minstens 64x32 pixels zijn en maximaal 4096x4096 pixels.',
            CmsMediaSettings::MAX_IMAGE_UPLOAD_FIELD.'.required' => __('cms_admin_ui.settings.form.media_max_image_upload_mb_required'),
            CmsMediaSettings::MAX_IMAGE_UPLOAD_FIELD.'.integer' => __('cms_admin_ui.settings.form.media_max_image_upload_mb_integer'),
            CmsMediaSettings::MAX_IMAGE_UPLOAD_FIELD.'.min' => __('cms_admin_ui.settings.form.media_max_image_upload_mb_range'),
            CmsMediaSettings::MAX_IMAGE_UPLOAD_FIELD.'.max' => __('cms_admin_ui.settings.form.media_max_image_upload_mb_range'),
            'admin_settings.admin_default_locale.required' => __('cms_admin_ui.settings.form.admin_default_locale_required'),
            'admin_settings.admin_default_locale.in' => __('cms_admin_ui.settings.form.admin_default_locale_invalid'),
        ];
    }

    private function validateRobotsExtraRules(Validator $validator): void
    {
        $value = $this->input('robots_extra_rules');

        if (! is_string($value) || trim($value) === '') {
            return;
        }

        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
            $validator->errors()->add('robots_extra_rules', 'Robots.txt mag geen ongeldige control characters bevatten.');
        }

        if (preg_match('/<\/?[a-z][^>]*>/i', $value) || str_contains($value, '<?')) {
            $validator->errors()->add('robots_extra_rules', 'Robots.txt mag geen HTML, script of PHP bevatten.');
        }

        $allowedDirectives = [
            'user-agent',
            'allow',
            'disallow',
            'sitemap',
            'crawl-delay',
            'clean-param',
            'host',
        ];

        foreach (preg_split('/\R/u', $value) ?: [] as $lineNumber => $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (! str_contains($line, ':')) {
                $validator->errors()->add('robots_extra_rules', 'Robots.txt regel '.($lineNumber + 1).' mist een dubbelepunt.');

                continue;
            }

            [$directive] = explode(':', $line, 2);

            if (! in_array(strtolower(trim($directive)), $allowedDirectives, true)) {
                $validator->errors()->add('robots_extra_rules', 'Robots.txt regel '.($lineNumber + 1).' bevat een niet-toegelaten directive.');
            }
        }
    }

    private function validateAutoLocaleCountryMap(Validator $validator): void
    {
        $value = $this->input('auto_locale_country_map');

        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $activeLocales = app(CmsLanguageSettings::class)->activeLocales();

        foreach (preg_split('/\R/u', $value) ?: [] as $lineNumber => $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (! preg_match('/^([A-Za-z]{2})\s*[=:]\s*([A-Za-z]{2}(?:[_-][A-Za-z]{2})?)$/', $line, $matches)) {
                $validator->errors()->add('auto_locale_country_map', __('cms_admin_ui.settings.form.auto_locale_country_map_invalid_line', ['line' => $lineNumber + 1]));

                continue;
            }

            $locale = str_replace('-', '_', trim($matches[2]));

            if (! in_array($locale, $activeLocales, true)) {
                $validator->errors()->add('auto_locale_country_map', __('cms_admin_ui.settings.form.auto_locale_country_map_invalid_locale', ['line' => $lineNumber + 1]));
            }
        }
    }

    private function validateDefaultSettingTranslations(Validator $validator): void
    {
        $defaultLocale = (string) $this->input('default_locale', app(CmsLanguageSettings::class)->defaultLocale());
        $siteName = trim((string) ($this->input("setting_translations.{$defaultLocale}.site_name") ?? $this->input('site_name', '')));

        if ($siteName === '') {
            $validator->errors()->add("setting_translations.{$defaultLocale}.site_name", 'Vul een sitenaam in voor de standaardtaal.');
        }
    }

    private function validateHomepageLocale(Validator $validator): void
    {
        $homepageId = (int) $this->input('homepage_id');

        if ($homepageId <= 0) {
            return;
        }

        $homepage = CmsPage::query()->find($homepageId, ['id', 'locale']);

        if (! $homepage instanceof CmsPage) {
            return;
        }

        if ((string) $homepage->locale !== (string) $this->input('default_locale')) {
            $validator->errors()->add('homepage_id', 'De homepage moet in de standaardtaal staan.');
        }
    }

    private function validateSeoSettingRanges(Validator $validator): void
    {
        $ranges = [
            ['seo_h1_min_length', 'seo_h1_max_length'],
            ['seo_meta_title_min_length', 'seo_meta_title_max_length'],
            ['seo_meta_description_min_length', 'seo_meta_description_max_length'],
            ['seo_slug_min_length', 'seo_slug_max_length'],
        ];

        foreach ($ranges as [$minField, $maxField]) {
            if ((int) $this->input($minField) > (int) $this->input($maxField)) {
                $validator->errors()->add($maxField, __('cms_admin_ui.validation.seo_min_max_invalid'));
            }
        }
    }

    private function validateTranslationAiSettings(Validator $validator): void
    {
        $provider = trim((string) $this->input('translation_ai.provider', ''));
        $model = trim((string) $this->input('translation_ai.model', ''));

        if ($provider === '' || $model === '') {
            return;
        }

        $configuredModels = collect((array) data_get(config('translation_editor.ai.providers', []), $provider.'.models', []))
            ->map(static function (mixed $item): string {
                if (is_array($item)) {
                    return trim((string) Arr::get($item, 'value', ''));
                }

                return trim((string) $item);
            })
            ->filter(static fn (string $value): bool => $value !== '')
            ->values();

        if ($configuredModels->isNotEmpty() && ! $configuredModels->contains($model)) {
            $validator->errors()->add('translation_ai.model', 'Ongeldig model voor de gekozen provider.');
        }

        $fillLimitDefault = (int) $this->input('translation_ai.fill_limit_default', 0);
        $fillLimitMax = (int) $this->input('translation_ai.fill_limit_max', 0);

        if ($fillLimitDefault > 0 && $fillLimitMax > 0 && $fillLimitDefault > $fillLimitMax) {
            $validator->errors()->add('translation_ai.fill_limit_default', 'Standaard limiet mag niet groter zijn dan maximum limiet.');
        }
    }

    private function validateSearchConsoleSettings(Validator $validator): void
    {
        if (! (bool) $this->boolean('search_console_enabled')) {
            return;
        }

        $settings = app(CmsSearchConsoleSettings::class);
        $propertyType = (string) $this->input('search_console_property_type', CmsSearchConsoleSettings::PROPERTY_TYPE_URL_PREFIX);
        $siteUrl = trim((string) $this->input('search_console_site_url', ''));
        $clientId = trim((string) $this->input('search_console.oauth_client_id', ''));
        $clientSecret = trim((string) $this->input('search_console.oauth_client_secret', ''));
        $clearClientSecret = (bool) $this->boolean('search_console.clear_oauth_client_secret');

        if ($siteUrl === '') {
            $validator->errors()->add('search_console_site_url', __('cms_admin_ui.settings.form.search_console_site_url_required'));
        } elseif ($propertyType === CmsSearchConsoleSettings::PROPERTY_TYPE_URL_PREFIX && ! $this->isValidSearchConsoleUrlPrefix($siteUrl)) {
            $validator->errors()->add('search_console_site_url', __('cms_admin_ui.settings.form.search_console_site_url_invalid'));
        } elseif ($propertyType === CmsSearchConsoleSettings::PROPERTY_TYPE_DOMAIN && ! $this->isValidSearchConsoleDomainProperty($siteUrl)) {
            $validator->errors()->add('search_console_site_url', __('cms_admin_ui.settings.form.search_console_site_url_domain_invalid'));
        }

        if ($clientId === '') {
            $validator->errors()->add('search_console.oauth_client_id', __('cms_admin_ui.settings.form.search_console_oauth_client_id_required'));
        }

        if ($clientSecret === '' && ($clearClientSecret || blank($settings->clientSecret()))) {
            $validator->errors()->add('search_console.oauth_client_secret', __('cms_admin_ui.settings.form.search_console_oauth_client_secret_required'));
        }
    }

    private function isValidSearchConsoleDomainProperty(string $siteUrl): bool
    {
        $domain = str_starts_with($siteUrl, 'sc-domain:')
            ? mb_substr($siteUrl, 10)
            : $siteUrl;

        return preg_match('/^(?!-)(?:[A-Za-z0-9-]{1,63}\.)+[A-Za-z]{2,63}$/', trim($domain)) === 1;
    }

    private function isValidSearchConsoleUrlPrefix(string $siteUrl): bool
    {
        if (! filter_var($siteUrl, FILTER_VALIDATE_URL)) {
            return false;
        }

        return in_array(parse_url($siteUrl, PHP_URL_SCHEME), ['http', 'https'], true);
    }

    private function validateVisitorTrackingExcludedPaths(Validator $validator): void
    {
        $value = $this->input('visitor_tracking_excluded_paths');

        if (! is_string($value) || trim($value) === '') {
            return;
        }

        foreach (preg_split('/\R/u', $value) ?: [] as $lineNumber => $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (! str_starts_with($line, '/') || str_contains($line, '..') || preg_match('/[<>]/', $line)) {
                $validator->errors()->add('visitor_tracking_excluded_paths', __('cms_admin_ui.settings.form.visitor_tracking_excluded_paths_invalid_line', ['line' => $lineNumber + 1]));
            }
        }
    }

    private function validateVisitorTrackingCountryList(Validator $validator): void
    {
        $value = $this->input('visitor_tracking_geo_allowed_countries');

        if (! is_string($value) || trim($value) === '') {
            return;
        }

        foreach (preg_split('/[\s,;]+/', $value) ?: [] as $country) {
            if ($country === '') {
                continue;
            }

            if (preg_match('/^[A-Za-z]{2}$/', $country) !== 1) {
                $validator->errors()->add('visitor_tracking_geo_allowed_countries', __('cms_admin_ui.settings.form.visitor_tracking_geo_allowed_countries_invalid'));

                return;
            }
        }
    }
}
