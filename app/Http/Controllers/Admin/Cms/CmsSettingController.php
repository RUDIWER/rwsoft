<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Actions\Admin\Cms\SitePackages\ActivateCmsSitePackageAction;
use App\Actions\Admin\Cms\SitePackages\BuildCmsSitePackageZipAction;
use App\Actions\Admin\Cms\SitePackages\ImportCmsSitePackageZipAction;
use App\Actions\Admin\Cms\SitePackages\PreviewCmsSitePackageZipAction;
use App\Actions\Admin\Cms\Starters\BuildCmsStarterZipFromSelectionAction;
use App\Actions\Admin\Cms\Starters\BuildExampleCmsStarterZipAction;
use App\Actions\Admin\Cms\Starters\ImportCmsStarterZipAction;
use App\Actions\Admin\Cms\StoreCmsFaviconAction;
use App\Actions\Admin\Cms\StoreCmsLogoAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cms\ActivateCmsSitePackageRequest;
use App\Http\Requests\Admin\Cms\ExportCmsSitePackageRequest;
use App\Http\Requests\Admin\Cms\ExportCmsStarterRequest;
use App\Http\Requests\Admin\Cms\ImportCmsSitePackageRequest;
use App\Http\Requests\Admin\Cms\ImportCmsStarterRequest;
use App\Http\Requests\Admin\Cms\StoreCmsSettingsRequest;
use App\Models\Cms\CmsLayout;
use App\Models\Cms\CmsMenu;
use App\Models\Cms\CmsModule;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsSetting;
use App\Models\Cms\CmsTemplate;
use App\Support\Ai\AiProviderSettings;
use App\Support\Audit\AuditLogger;
use App\Support\Cms\CmsMediaLibraryPayload;
use App\Support\Cms\CmsMediaSettings;
use App\Support\Cms\CmsModuleRegistry;
use App\Support\Cms\Seo\CmsSeoSettings;
use App\Support\Localization\AdminLocaleResolver;
use App\Support\PublicSite\CmsLanguageSettings;
use App\Support\PublicSite\CmsPublicTextCache;
use App\Support\PublicSite\CmsRobotsTxtBuilder;
use App\Support\PublicSite\CmsSearchConsoleSettings;
use App\Support\PublicSite\CmsVisitorTrackingSettings;
use App\Support\PublicSite\PublicSiteLocaleDetector;
use App\Support\Settings\AppSettingStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CmsSettingController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly CmsRobotsTxtBuilder $robotsTxtBuilder,
        private readonly CmsLanguageSettings $languageSettings,
        private readonly AiProviderSettings $providerSettings,
        private readonly CmsPublicTextCache $publicTextCache,
        private readonly AppSettingStore $settingStore,
        private readonly AdminLocaleResolver $adminLocaleResolver,
        private readonly CmsMediaLibraryPayload $mediaLibraryPayload,
        private readonly CmsVisitorTrackingSettings $visitorTrackingSettings,
        private readonly CmsSearchConsoleSettings $searchConsoleSettings,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('Admin/Cms/Settings/Edit', [
            'settings' => $this->settingsPayload(),
            'settingMeta' => $this->settingMetaPayload(),
            'adminSettings' => $this->adminSettingsPayload(),
            'seoSettings' => app(CmsSeoSettings::class)->values(),
            'translationAi' => $this->translationAiPayload(),
            'visitorTracking' => $this->visitorTrackingPayload(),
            'searchConsole' => $this->searchConsolePayload(),
            'modules' => $this->modulesPayload(),
            'activeLanguages' => $this->languageSettings->languages(true),
            'robotsDefaultDisallowPaths' => $this->robotsTxtBuilder->defaultDisallowPaths(),
            'robotsSitemapUrl' => url('/sitemap.xml'),
            'layoutOptions' => CmsLayout::query()
                ->orderBy('locale')
                ->orderBy('name')
                ->get(['id', 'name', 'locale', 'is_active']),
            'templateOptions' => CmsTemplate::query()
                ->orderBy('locale')
                ->orderBy('name')
                ->get(['id', 'name', 'locale', 'layout_id', 'template_class', 'template_key', 'is_active']),
            'pageOptions' => CmsPage::query()
                ->orderBy('locale')
                ->orderBy('title')
                ->get(['id', 'title', 'slug', 'locale', 'status', 'detail_template_id']),
            'menuOptions' => CmsMenu::query()
                ->orderBy('title')
                ->get(['id', 'title', 'placements', 'is_active']),
            'mediaOptions' => $this->mediaLibraryPayload->assets(),
            'mediaFolders' => $this->mediaLibraryPayload->folders(),
        ]);
    }

    public function installModule(
        Request $request,
        string $module,
        CmsModuleRegistry $moduleRegistry,
    ): RedirectResponse {
        $definition = $moduleRegistry->module($module);
        abort_unless(is_array($definition), 404);

        $installer = app((string) $definition['installer']);
        $result = $installer->handle();

        $this->auditLogger->success(
            action: 'cms.module.install',
            module: 'cms',
            subjectType: 'cms_module',
            subjectKey: (string) $definition['key'],
            message: __('cms_admin_ui.flash.cms_module_installed'),
            meta: [
                'module' => (string) $definition['key'],
                'result' => $result,
            ],
            request: $request,
        );

        return redirect()
            ->route('admin.cms.settings.edit', ['tab' => 'modules'])
            ->with('status', __('cms_admin_ui.flash.cms_module_installed'))
            ->with('flash_details', [
                'cms_module_install' => [
                    'module' => (string) $definition['key'],
                    'result' => $result,
                ],
            ]);
    }

    public function installModuleDemoData(
        Request $request,
        string $module,
        CmsModuleRegistry $moduleRegistry,
    ): RedirectResponse {
        $definition = $moduleRegistry->module($module);
        abort_unless(is_array($definition), 404);

        $demoInstaller = $definition['demo_installer'] ?? null;
        abort_unless(is_string($demoInstaller) && $demoInstaller !== '', 404);

        $installer = app($demoInstaller);
        $result = $installer->handle($request->user()?->id);

        $this->auditLogger->success(
            action: 'cms.module.demo-data.install',
            module: 'cms',
            subjectType: 'cms_module',
            subjectKey: (string) $definition['key'],
            message: __('cms_admin_ui.flash.cms_module_demo_data_installed'),
            meta: [
                'module' => (string) $definition['key'],
                'result' => $result,
            ],
            request: $request,
        );

        return redirect()
            ->route('admin.cms.settings.edit', ['tab' => 'modules'])
            ->with('status', __('cms_admin_ui.flash.cms_module_demo_data_installed'))
            ->with('flash_details', [
                'cms_module_install' => [
                    'module' => (string) $definition['key'],
                    'result' => $result,
                ],
            ]);
    }

    public function store(
        StoreCmsSettingsRequest $request,
        StoreCmsFaviconAction $storeCmsFavicon,
        StoreCmsLogoAction $storeCmsLogo,
    ): RedirectResponse {
        $validated = $request->validated();
        $adminSettings = (array) ($validated['admin_settings'] ?? []);
        $translationAi = (array) ($validated['translation_ai'] ?? []);
        $visitorTracking = (array) ($validated['visitor_tracking'] ?? []);
        $searchConsole = (array) ($validated['search_console'] ?? []);
        $currentAiSettings = $this->providerSettings->translationSettings();
        $booleanFields = [
            'global_noindex',
            'multilingual_enabled',
            'auto_locale_detection_enabled',
            'auto_locale_redirect_enabled',
            'auto_locale_remember_choice',
            'logo_show_tagline',
            'public_text_cache_enabled',
            'seo_require_meta_title_on_publish',
            'seo_require_meta_description_on_publish',
            'seo_require_single_h1',
            'seo_require_valid_heading_hierarchy',
            'seo_require_json_ld',
            'seo_require_og_image_for_posts',
            'visitor_tracking_enabled',
            'visitor_tracking_store_ip',
            'visitor_tracking_store_ip_hash',
            'visitor_tracking_ignore_bots',
            'visitor_tracking_geo_enabled',
            'visitor_tracking_geo_delete_disallowed_countries',
            'search_console_enabled',
        ];

        foreach ($this->definitions() as $field => $definition) {
            $value = in_array($field, $booleanFields, true)
                ? (bool) ($validated[$field] ?? false)
                : ($validated[$field] ?? null);

            CmsSetting::query()->updateOrCreate(
                ['group' => $definition['group'], 'key' => $definition['key']],
                [
                    'label' => $definition['label'],
                    'type' => $definition['type'],
                    'value' => ['value' => $value],
                    'is_public' => true,
                    'sort_order' => $definition['sort_order'],
                ]
            );
        }

        $this->saveSettingTranslations((array) ($validated['setting_translations'] ?? []));

        $faviconFile = $request->file('favicon_file');

        if ($faviconFile instanceof UploadedFile) {
            foreach ($storeCmsFavicon->handle($faviconFile) as $key => $value) {
                $this->upsertSetting('branding', $key, $this->brandingLabel($key), 'text', $value, $this->brandingSortOrder($key));
            }
        }

        $logoFile = $request->file('logo_file');

        if ($logoFile instanceof UploadedFile) {
            foreach ($storeCmsLogo->handle($logoFile) as $key => $value) {
                $this->upsertSetting('branding', $key, $this->brandingLabel($key), 'text', $value, $this->brandingSortOrder($key));
            }
        }

        $apiKey = isset($translationAi['api_key'])
            ? trim((string) $translationAi['api_key'])
            : '';
        $clearApiKey = (bool) ($translationAi['clear_api_key'] ?? false);
        $resolvedApiKey = match (true) {
            $clearApiKey => null,
            $apiKey !== '' => $apiKey,
            default => isset($currentAiSettings['api_key']) ? (string) $currentAiSettings['api_key'] : null,
        };

        $this->providerSettings->saveTranslationSettings(
            provider: (string) ($translationAi['provider'] ?? 'gemini'),
            model: (string) ($translationAi['model'] ?? ''),
            apiKey: $resolvedApiKey,
            fillLimitDefault: (int) ($translationAi['fill_limit_default'] ?? 100),
            fillLimitMax: (int) ($translationAi['fill_limit_max'] ?? 500),
        );

        $geoApiKey = isset($visitorTracking['geo_api_key'])
            ? trim((string) $visitorTracking['geo_api_key'])
            : '';
        $clearGeoApiKey = (bool) ($visitorTracking['clear_geo_api_key'] ?? false);

        if ($clearGeoApiKey || $geoApiKey !== '') {
            $this->settingStore->put(
                CmsVisitorTrackingSettings::GEO_API_KEY_SETTING,
                $clearGeoApiKey ? null : $geoApiKey,
                true,
            );
        }

        $oauthClientId = isset($searchConsole['oauth_client_id'])
            ? trim((string) $searchConsole['oauth_client_id'])
            : '';
        $oauthClientSecret = isset($searchConsole['oauth_client_secret'])
            ? trim((string) $searchConsole['oauth_client_secret'])
            : '';
        $clearOAuthClientSecret = (bool) ($searchConsole['clear_oauth_client_secret'] ?? false);
        $currentOAuthClientId = $this->searchConsoleSettings->clientId();

        $this->settingStore->put(
            CmsSearchConsoleSettings::OAUTH_CLIENT_ID_SETTING,
            $oauthClientId !== '' ? $oauthClientId : null,
            true,
        );

        if ($clearOAuthClientSecret || $oauthClientSecret !== '') {
            $this->settingStore->put(
                CmsSearchConsoleSettings::OAUTH_CLIENT_SECRET_SETTING,
                $clearOAuthClientSecret ? null : $oauthClientSecret,
                true,
            );
        }

        if ($currentOAuthClientId !== ($oauthClientId !== '' ? $oauthClientId : null) || $clearOAuthClientSecret || $oauthClientSecret !== '') {
            $this->searchConsoleSettings->clearOauthToken();
        }

        $this->settingStore->put(
            AdminLocaleResolver::ADMIN_DEFAULT_SETTING_KEY,
            (string) ($adminSettings['admin_default_locale'] ?? AdminLocaleResolver::DEFAULT_ADMIN_LOCALE),
        );

        $this->publicTextCache->flush();

        $this->auditLogger->success(
            action: 'cms.settings.update',
            module: 'cms',
            subjectType: 'cms_settings',
            subjectKey: 'public',
            message: __('cms_admin_ui.flash.saved.settings'),
            meta: ['site_name' => (string) $validated['site_name']],
            request: $request,
        );

        return redirect()
            ->route('admin.cms.settings.edit')
            ->with('status', __('cms_admin_ui.flash.saved.settings'));
    }

    public function importStarter(
        ImportCmsStarterRequest $request,
        ImportCmsStarterZipAction $importCmsStarterZip,
    ): RedirectResponse {
        try {
            $result = $importCmsStarterZip->handle($request->file('starter_zip'));
        } catch (ValidationException $exception) {
            $this->auditLogger->failure(
                action: 'cms.starter.import',
                module: 'cms',
                subjectType: 'cms_starter',
                subjectKey: null,
                message: __('cms_admin_ui.flash.starter_import_failed'),
                meta: ['errors' => $exception->errors()],
                request: $request,
            );

            throw $exception;
        }

        $manifest = (array) ($result['manifest'] ?? []);
        $imported = (array) ($result['imported'] ?? []);
        $starterKey = trim((string) ($manifest['key'] ?? ''));
        $starterName = trim((string) ($manifest['name'] ?? $starterKey));

        $this->auditLogger->success(
            action: 'cms.starter.import',
            module: 'cms',
            subjectType: 'cms_starter',
            subjectKey: $starterKey !== '' ? $starterKey : null,
            message: __('cms_admin_ui.flash.starter_imported'),
            meta: [
                'starter_key' => $starterKey,
                'starter_name' => $starterName,
                'modules' => (array) ($result['modules'] ?? []),
                'imported' => $imported,
            ],
            request: $request,
        );

        return redirect()
            ->route('admin.cms.settings.edit', ['tab' => 'starter'])
            ->with('status', __('cms_admin_ui.flash.starter_imported'))
            ->with('flash_details', [
                'starter_import' => [
                    'name' => $starterName,
                    'imported' => $imported,
                ],
            ]);
    }

    public function downloadExampleStarter(
        BuildExampleCmsStarterZipAction $buildExampleCmsStarterZip,
    ): BinaryFileResponse {
        $export = $buildExampleCmsStarterZip->handle();

        $this->auditLogger->success(
            action: 'cms.starter.example.download',
            module: 'cms',
            subjectType: 'cms_starter',
            subjectKey: $export['key'],
            message: __('cms_admin_ui.flash.starter_example_downloaded'),
            meta: ['filename' => $export['filename']],
        );

        return response()
            ->download($export['path'], $export['filename'], ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    public function exportStarter(
        ExportCmsStarterRequest $request,
        BuildCmsStarterZipFromSelectionAction $buildCmsStarterZipFromSelection,
    ): BinaryFileResponse {
        $export = $buildCmsStarterZipFromSelection->handle($request->validated());

        $this->auditLogger->success(
            action: 'cms.starter.export.download',
            module: 'cms',
            subjectType: 'cms_starter',
            subjectKey: $export['key'],
            message: __('cms_admin_ui.flash.starter_export_downloaded'),
            meta: ['filename' => $export['filename']],
            request: $request,
        );

        return response()
            ->download($export['path'], $export['filename'], ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    public function exportSitePackage(
        ExportCmsSitePackageRequest $request,
        BuildCmsSitePackageZipAction $buildCmsSitePackageZip,
    ): BinaryFileResponse {
        $export = $buildCmsSitePackageZip->handle($request->validated());

        $this->auditLogger->success(
            action: 'cms.site-package.export.download',
            module: 'cms',
            subjectType: 'cms_site_package',
            subjectKey: $export['key'],
            message: __('cms_admin_ui.flash.site_package_export_downloaded'),
            meta: ['filename' => $export['filename']],
            request: $request,
        );

        return response()
            ->download($export['path'], $export['filename'], ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    public function importSitePackage(
        ImportCmsSitePackageRequest $request,
        ImportCmsSitePackageZipAction $importCmsSitePackageZip,
    ): RedirectResponse {
        try {
            $result = $importCmsSitePackageZip->handle($request->file('site_package_zip'));
        } catch (ValidationException $exception) {
            $this->auditLogger->failure(
                action: 'cms.site-package.import',
                module: 'cms',
                subjectType: 'cms_site_package',
                subjectKey: null,
                message: __('cms_admin_ui.flash.site_package_import_failed'),
                meta: ['errors' => $exception->errors()],
                request: $request,
            );

            throw $exception;
        }

        $manifest = (array) ($result['manifest'] ?? []);
        $imported = (array) ($result['imported'] ?? []);
        $packageKey = trim((string) ($manifest['key'] ?? ''));
        $packageName = trim((string) ($manifest['name'] ?? $packageKey));

        $this->auditLogger->success(
            action: 'cms.site-package.import',
            module: 'cms',
            subjectType: 'cms_site_package',
            subjectKey: $packageKey !== '' ? $packageKey : null,
            message: __('cms_admin_ui.flash.site_package_imported'),
            meta: [
                'package_key' => $packageKey,
                'package_name' => $packageName,
                'modules' => (array) ($result['modules'] ?? []),
                'imported' => $imported,
            ],
            request: $request,
        );

        return redirect()
            ->route('admin.cms.settings.edit', ['tab' => 'starter'])
            ->with('status', __('cms_admin_ui.flash.site_package_imported'))
            ->with('flash_details', [
                'site_package_import' => [
                    'key' => $packageKey,
                    'name' => $packageName,
                    'imported' => $imported,
                ],
            ]);
    }

    public function previewSitePackage(
        ImportCmsSitePackageRequest $request,
        PreviewCmsSitePackageZipAction $previewCmsSitePackageZip,
    ): RedirectResponse {
        try {
            $result = $previewCmsSitePackageZip->handle($request->file('site_package_zip'));
        } catch (ValidationException $exception) {
            $this->auditLogger->failure(
                action: 'cms.site-package.preview',
                module: 'cms',
                subjectType: 'cms_site_package',
                subjectKey: null,
                message: __('cms_admin_ui.flash.site_package_preview_failed'),
                meta: ['errors' => $exception->errors()],
                request: $request,
            );

            throw $exception;
        }

        $manifest = (array) ($result['manifest'] ?? []);
        $modules = (array) ($result['modules'] ?? []);
        $packageKey = trim((string) ($manifest['key'] ?? ''));
        $packageName = trim((string) ($manifest['name'] ?? $packageKey));

        $this->auditLogger->success(
            action: 'cms.site-package.preview',
            module: 'cms',
            subjectType: 'cms_site_package',
            subjectKey: $packageKey !== '' ? $packageKey : null,
            message: __('cms_admin_ui.flash.site_package_previewed'),
            meta: [
                'package_key' => $packageKey,
                'package_name' => $packageName,
                'modules' => $modules,
                'warnings' => (array) ($result['warnings'] ?? []),
            ],
            request: $request,
        );

        return redirect()
            ->route('admin.cms.settings.edit', ['tab' => 'starter'])
            ->with('status', __('cms_admin_ui.flash.site_package_previewed'))
            ->with('flash_details', [
                'site_package_preview' => [
                    'key' => $packageKey,
                    'name' => $packageName,
                    'modules' => $modules,
                    'warnings' => (array) ($result['warnings'] ?? []),
                ],
            ]);
    }

    public function activateSitePackage(
        ActivateCmsSitePackageRequest $request,
        ActivateCmsSitePackageAction $activateCmsSitePackage,
    ): RedirectResponse {
        $validated = $request->validated();
        $activated = $activateCmsSitePackage->handle($validated);
        $packageKey = trim((string) ($validated['package_key'] ?? ''));

        $this->auditLogger->success(
            action: 'cms.site-package.activate',
            module: 'cms',
            subjectType: 'cms_site_package',
            subjectKey: $packageKey !== '' ? $packageKey : null,
            message: __('cms_admin_ui.flash.site_package_activated'),
            meta: [
                'package_key' => $packageKey,
                'modules' => (array) ($validated['modules'] ?? []),
                'publish_pages' => (bool) ($validated['publish_pages'] ?? false),
                'publish_blogs' => (bool) ($validated['publish_blogs'] ?? false),
                'set_homepage' => (bool) ($validated['set_homepage'] ?? false),
                'set_default_layouts' => (bool) ($validated['set_default_layouts'] ?? false),
                'set_default_templates' => (bool) ($validated['set_default_templates'] ?? false),
                'activate_theme_import_key' => (string) ($validated['activate_theme_import_key'] ?? ''),
                'activated' => $activated,
            ],
            request: $request,
        );

        return redirect()
            ->route('admin.cms.settings.edit', ['tab' => 'starter'])
            ->with('status', __('cms_admin_ui.flash.site_package_activated'))
            ->with('flash_details', [
                'site_package_activation' => [
                    'key' => $packageKey,
                    'activated' => $activated,
                ],
            ]);
    }

    /**
     * @return array{admin_default_locale:string,locale_options:array<int, array<string, mixed>>}
     */
    private function adminSettingsPayload(): array
    {
        $adminDefaultLocale = $this->settingStore->get(
            AdminLocaleResolver::ADMIN_DEFAULT_SETTING_KEY,
            AdminLocaleResolver::DEFAULT_ADMIN_LOCALE,
        );

        return [
            'admin_default_locale' => $this->adminLocaleResolver->isAllowedLocale($adminDefaultLocale)
                ? $adminDefaultLocale
                : AdminLocaleResolver::DEFAULT_ADMIN_LOCALE,
            'locale_options' => $this->adminLocaleResolver->localeOptions(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsPayload(): array
    {
        $settings = CmsSetting::query()
            ->with('translations')
            ->whereIn('group', collect($this->definitions())->pluck('group')->unique()->push('branding')->values())
            ->get()
            ->keyBy(fn (CmsSetting $setting): string => $setting->group.'.'.$setting->key);

        $payload = [];

        foreach ($this->definitions() as $field => $definition) {
            $setting = $settings->get($definition['group'].'.'.$definition['key']);
            $payload[$field] = $setting instanceof CmsSetting
                ? ($setting->value['value'] ?? $definition['default'])
                : $definition['default'];
        }

        $payload['favicon'] = $this->faviconPayload($settings);
        $payload['logo'] = $this->logoPayload($settings);
        $payload['setting_translations'] = $this->settingTranslationsPayload($settings);

        return $payload;
    }

    /**
     * @return array{id: ?int, created_at: ?string, updated_at: ?string}
     */
    private function settingMetaPayload(): array
    {
        $settings = CmsSetting::query()
            ->whereIn('group', collect($this->definitions())->pluck('group')->unique()->push('branding')->values())
            ->get(['id', 'created_at', 'updated_at']);

        return [
            'id' => $settings->min('id'),
            'created_at' => $settings->min('created_at')?->toISOString(),
            'updated_at' => $settings->max('updated_at')?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function translationAiPayload(): array
    {
        $settings = $this->providerSettings->translationSettings();

        return [
            'provider' => (string) ($settings['provider'] ?? 'gemini'),
            'model' => (string) ($settings['model'] ?? ''),
            'api_key' => '',
            'clear_api_key' => false,
            'has_api_key' => (bool) ($settings['has_api_key'] ?? false),
            'has_config_api_key' => (bool) ($settings['has_config_api_key'] ?? false),
            'has_usable_api_key' => (bool) ($settings['has_usable_api_key'] ?? false),
            'fill_limit_default' => (int) ($settings['fill_limit_default'] ?? 100),
            'fill_limit_max' => (int) ($settings['fill_limit_max'] ?? 500),
            'providers' => $this->providerSettings->providerOptions(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function visitorTrackingPayload(): array
    {
        return [
            'geo_api_key' => '',
            'clear_geo_api_key' => false,
            'has_geo_api_key' => $this->visitorTrackingSettings->hasGeoApiKey(),
            'geo_provider_options' => collect($this->visitorTrackingSettings->geoProviderOptions())
                ->map(fn (string $label, string $value): array => [
                    'value' => $value,
                    'label' => $label,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function searchConsolePayload(): array
    {
        return [
            'oauth_client_id' => $this->searchConsoleSettings->clientId() ?? '',
            'oauth_client_secret' => '',
            'clear_oauth_client_secret' => false,
            'has_oauth_client_secret' => filled($this->searchConsoleSettings->clientSecret()),
            'has_oauth_token' => $this->searchConsoleSettings->hasOauthToken(),
            'connected_email' => $this->searchConsoleSettings->connectedEmail(),
            'last_success_at' => $this->searchConsoleSettings->lastSuccessAt(),
            'last_error' => $this->searchConsoleSettings->lastError(),
            'callback_url' => route('admin.cms.search-console.callback'),
            'property_type_options' => collect($this->searchConsoleSettings->propertyTypeOptions())
                ->map(fn (string $label, string $value): array => [
                    'value' => $value,
                    'label' => $label,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function modulesPayload(): array
    {
        $registry = app(CmsModuleRegistry::class);
        $moduleRecords = CmsModule::query()
            ->whereIn('key', $registry->keys())
            ->get()
            ->keyBy('key');
        $items = collect($registry->modules())
            ->map(function (array $definition) use ($moduleRecords): array {
                $record = $moduleRecords->get($definition['key']);
                $installedVersion = (int) data_get($record?->settings, 'module_version', $record instanceof CmsModule ? 1 : 0);
                $registryVersion = (int) $definition['version'];
                $manageRoute = $definition['manage_route'];

                return [
                    'key' => (string) $definition['key'],
                    'slug' => str_replace('-', '_', (string) $definition['key']),
                    'name_key' => (string) $definition['name_key'],
                    'name_fallback' => (string) $definition['name_fallback'],
                    'description_key' => (string) $definition['description_key'],
                    'description_fallback' => (string) $definition['description_fallback'],
                    'icon' => (string) $definition['icon'],
                    'installed' => $record instanceof CmsModule,
                    'outdated' => $record instanceof CmsModule && $installedVersion < $registryVersion,
                    'status' => $record?->status,
                    'module_version' => $registryVersion,
                    'installed_version' => $installedVersion,
                    'installed_at' => $record?->installed_at?->toISOString(),
                    'installed_at_display' => $record?->installed_at?->format('d/m/Y H:i'),
                    'synced_at_display' => data_get($record?->settings, 'synced_at'),
                    'has_demo_data' => is_string($definition['demo_installer'] ?? null) && $definition['demo_installer'] !== '',
                    'demo_label_key' => (string) ($definition['demo_label_key'] ?? 'settings.form.module_demo_data_button'),
                    'demo_label_fallback' => (string) ($definition['demo_label_fallback'] ?? 'Install demo data'),
                    'manage_url' => is_string($manageRoute) && $manageRoute !== '' && Route::has($manageRoute)
                        ? route($manageRoute)
                        : null,
                ];
            })
            ->values()
            ->all();

        return [
            'items' => $items,
            ...collect($items)->mapWithKeys(fn (array $item): array => [$item['slug'] => $item])->all(),
        ];
    }

    /**
     * @return array<string, array{group: string, key: string, label: string, type: string, default: mixed, sort_order: int}>
     */
    private function definitions(): array
    {
        return [
            'site_name' => ['group' => 'general', 'key' => 'site_name', 'label' => __('cms_admin_ui.settings.form.site_name'), 'type' => 'text', 'default' => config('app.name', 'RwSoft'), 'sort_order' => 10],
            'site_tagline' => ['group' => 'general', 'key' => 'site_tagline', 'label' => __('cms_admin_ui.settings.form.tagline'), 'type' => 'text', 'default' => null, 'sort_order' => 20],
            'default_locale' => ['group' => 'general', 'key' => 'default_locale', 'label' => __('cms_admin_ui.settings.form.default_locale'), 'type' => 'text', 'default' => config('app.locale', 'nl'), 'sort_order' => 30],
            'multilingual_enabled' => ['group' => 'general', 'key' => 'multilingual_enabled', 'label' => __('cms_admin_ui.settings.form.enable_multilingual'), 'type' => 'boolean', 'default' => true, 'sort_order' => 35],
            'homepage_id' => ['group' => 'general', 'key' => 'homepage_id', 'label' => __('cms_admin_ui.settings.form.homepage'), 'type' => 'number', 'default' => null, 'sort_order' => 40],
            'auto_locale_detection_enabled' => ['group' => 'localization', 'key' => 'auto_locale_detection_enabled', 'label' => __('cms_admin_ui.settings.form.auto_locale_detection_enabled'), 'type' => 'boolean', 'default' => false, 'sort_order' => 10],
            'auto_locale_detection_strategy' => ['group' => 'localization', 'key' => 'auto_locale_detection_strategy', 'label' => __('cms_admin_ui.settings.form.auto_locale_detection_strategy'), 'type' => 'text', 'default' => PublicSiteLocaleDetector::STRATEGY_BROWSER_THEN_IP, 'sort_order' => 20],
            'auto_locale_redirect_enabled' => ['group' => 'localization', 'key' => 'auto_locale_redirect_enabled', 'label' => __('cms_admin_ui.settings.form.auto_locale_redirect_enabled'), 'type' => 'boolean', 'default' => true, 'sort_order' => 30],
            'auto_locale_remember_choice' => ['group' => 'localization', 'key' => 'auto_locale_remember_choice', 'label' => __('cms_admin_ui.settings.form.auto_locale_remember_choice'), 'type' => 'boolean', 'default' => true, 'sort_order' => 40],
            'auto_locale_cookie_days' => ['group' => 'localization', 'key' => 'auto_locale_cookie_days', 'label' => __('cms_admin_ui.settings.form.auto_locale_cookie_days'), 'type' => 'number', 'default' => 180, 'sort_order' => 50],
            'auto_locale_country_map' => ['group' => 'localization', 'key' => 'auto_locale_country_map', 'label' => __('cms_admin_ui.settings.form.auto_locale_country_map'), 'type' => 'textarea', 'default' => null, 'sort_order' => 60],
            'visitor_tracking_enabled' => ['group' => 'visitor_tracking', 'key' => 'enabled', 'label' => __('cms_admin_ui.settings.form.visitor_tracking_enabled'), 'type' => 'boolean', 'default' => false, 'sort_order' => 10],
            'visitor_tracking_retention_mode' => ['group' => 'visitor_tracking', 'key' => 'retention_mode', 'label' => __('cms_admin_ui.settings.form.visitor_tracking_retention_mode'), 'type' => 'text', 'default' => CmsVisitorTrackingSettings::RETENTION_DAYS, 'sort_order' => 20],
            'visitor_tracking_retention_days' => ['group' => 'visitor_tracking', 'key' => 'retention_days', 'label' => __('cms_admin_ui.settings.form.visitor_tracking_retention_days'), 'type' => 'number', 'default' => 90, 'sort_order' => 30],
            'visitor_tracking_cookie_days' => ['group' => 'visitor_tracking', 'key' => 'cookie_days', 'label' => __('cms_admin_ui.settings.form.visitor_tracking_cookie_days'), 'type' => 'number', 'default' => 90, 'sort_order' => 40],
            'visitor_tracking_store_ip' => ['group' => 'visitor_tracking', 'key' => 'store_ip', 'label' => __('cms_admin_ui.settings.form.visitor_tracking_store_ip'), 'type' => 'boolean', 'default' => true, 'sort_order' => 50],
            'visitor_tracking_store_ip_hash' => ['group' => 'visitor_tracking', 'key' => 'store_ip_hash', 'label' => __('cms_admin_ui.settings.form.visitor_tracking_store_ip_hash'), 'type' => 'boolean', 'default' => true, 'sort_order' => 60],
            'visitor_tracking_ignore_bots' => ['group' => 'visitor_tracking', 'key' => 'ignore_bots', 'label' => __('cms_admin_ui.settings.form.visitor_tracking_ignore_bots'), 'type' => 'boolean', 'default' => true, 'sort_order' => 70],
            'visitor_tracking_excluded_paths' => ['group' => 'visitor_tracking', 'key' => 'excluded_paths', 'label' => __('cms_admin_ui.settings.form.visitor_tracking_excluded_paths'), 'type' => 'textarea', 'default' => null, 'sort_order' => 80],
            'visitor_tracking_geo_enabled' => ['group' => 'visitor_tracking', 'key' => 'geo_enabled', 'label' => __('cms_admin_ui.settings.form.visitor_tracking_geo_enabled'), 'type' => 'boolean', 'default' => false, 'sort_order' => 90],
            'visitor_tracking_geo_provider' => ['group' => 'visitor_tracking', 'key' => 'geo_provider', 'label' => __('cms_admin_ui.settings.form.visitor_tracking_geo_provider'), 'type' => 'text', 'default' => CmsVisitorTrackingSettings::GEO_PROVIDER_IP_API, 'sort_order' => 100],
            'visitor_tracking_geo_allowed_countries' => ['group' => 'visitor_tracking', 'key' => 'geo_allowed_countries', 'label' => __('cms_admin_ui.settings.form.visitor_tracking_geo_allowed_countries'), 'type' => 'textarea', 'default' => null, 'sort_order' => 110],
            'visitor_tracking_geo_delete_disallowed_countries' => ['group' => 'visitor_tracking', 'key' => 'geo_delete_disallowed_countries', 'label' => __('cms_admin_ui.settings.form.visitor_tracking_geo_delete_disallowed_countries'), 'type' => 'boolean', 'default' => false, 'sort_order' => 120],
            'search_console_enabled' => ['group' => 'search_console', 'key' => 'enabled', 'label' => __('cms_admin_ui.settings.form.search_console_enabled'), 'type' => 'boolean', 'default' => false, 'sort_order' => 10],
            'search_console_property_type' => ['group' => 'search_console', 'key' => 'property_type', 'label' => __('cms_admin_ui.settings.form.search_console_property_type'), 'type' => 'text', 'default' => CmsSearchConsoleSettings::PROPERTY_TYPE_URL_PREFIX, 'sort_order' => 20],
            'search_console_site_url' => ['group' => 'search_console', 'key' => 'site_url', 'label' => __('cms_admin_ui.settings.form.search_console_site_url'), 'type' => 'text', 'default' => null, 'sort_order' => 30],
            'search_console_analytics_cache_seconds' => ['group' => 'search_console', 'key' => 'analytics_cache_seconds', 'label' => __('cms_admin_ui.settings.form.search_console_analytics_cache_seconds'), 'type' => 'number', 'default' => 43200, 'sort_order' => 40],
            'search_console_inspection_cache_seconds' => ['group' => 'search_console', 'key' => 'inspection_cache_seconds', 'label' => __('cms_admin_ui.settings.form.search_console_inspection_cache_seconds'), 'type' => 'number', 'default' => 86400, 'sort_order' => 50],
            'search_console_query_limit' => ['group' => 'search_console', 'key' => 'query_limit', 'label' => __('cms_admin_ui.settings.form.search_console_query_limit'), 'type' => 'number', 'default' => 10, 'sort_order' => 60],
            'contact_company_name' => ['group' => 'contact', 'key' => 'company_name', 'label' => __('cms_admin_ui.settings.form.contact_company_name'), 'type' => 'text', 'default' => null, 'sort_order' => 10],
            'contact_street' => ['group' => 'contact', 'key' => 'street', 'label' => __('cms_admin_ui.settings.form.contact_street'), 'type' => 'text', 'default' => null, 'sort_order' => 20],
            'contact_postal_code' => ['group' => 'contact', 'key' => 'postal_code', 'label' => __('cms_admin_ui.settings.form.contact_postal_code'), 'type' => 'text', 'default' => null, 'sort_order' => 30],
            'contact_city' => ['group' => 'contact', 'key' => 'city', 'label' => __('cms_admin_ui.settings.form.contact_city'), 'type' => 'text', 'default' => null, 'sort_order' => 40],
            'contact_country' => ['group' => 'contact', 'key' => 'country', 'label' => __('cms_admin_ui.settings.form.contact_country'), 'type' => 'text', 'default' => null, 'sort_order' => 50],
            'contact_country_code' => ['group' => 'contact', 'key' => 'country_code', 'label' => __('cms_admin_ui.settings.form.contact_country_code'), 'type' => 'text', 'default' => null, 'sort_order' => 60],
            'contact_phone_1_label' => ['group' => 'contact', 'key' => 'phone_1_label', 'label' => __('cms_admin_ui.settings.form.contact_phone_1_label'), 'type' => 'text', 'default' => null, 'sort_order' => 70],
            'contact_phone_1' => ['group' => 'contact', 'key' => 'phone_1', 'label' => __('cms_admin_ui.settings.form.contact_phone_1'), 'type' => 'text', 'default' => null, 'sort_order' => 80],
            'contact_phone_2_label' => ['group' => 'contact', 'key' => 'phone_2_label', 'label' => __('cms_admin_ui.settings.form.contact_phone_2_label'), 'type' => 'text', 'default' => null, 'sort_order' => 90],
            'contact_phone_2' => ['group' => 'contact', 'key' => 'phone_2', 'label' => __('cms_admin_ui.settings.form.contact_phone_2'), 'type' => 'text', 'default' => null, 'sort_order' => 100],
            'contact_phone_3_label' => ['group' => 'contact', 'key' => 'phone_3_label', 'label' => __('cms_admin_ui.settings.form.contact_phone_3_label'), 'type' => 'text', 'default' => null, 'sort_order' => 110],
            'contact_phone_3' => ['group' => 'contact', 'key' => 'phone_3', 'label' => __('cms_admin_ui.settings.form.contact_phone_3'), 'type' => 'text', 'default' => null, 'sort_order' => 120],
            'contact_email_1_label' => ['group' => 'contact', 'key' => 'email_1_label', 'label' => __('cms_admin_ui.settings.form.contact_email_1_label'), 'type' => 'text', 'default' => null, 'sort_order' => 130],
            'contact_email_1' => ['group' => 'contact', 'key' => 'email_1', 'label' => __('cms_admin_ui.settings.form.contact_email_1'), 'type' => 'text', 'default' => null, 'sort_order' => 140],
            'contact_email_2_label' => ['group' => 'contact', 'key' => 'email_2_label', 'label' => __('cms_admin_ui.settings.form.contact_email_2_label'), 'type' => 'text', 'default' => null, 'sort_order' => 150],
            'contact_email_2' => ['group' => 'contact', 'key' => 'email_2', 'label' => __('cms_admin_ui.settings.form.contact_email_2'), 'type' => 'text', 'default' => null, 'sort_order' => 160],
            'contact_vat_number' => ['group' => 'contact', 'key' => 'vat_number', 'label' => __('cms_admin_ui.settings.form.contact_vat_number'), 'type' => 'text', 'default' => null, 'sort_order' => 170],
            'contact_image_media_asset_id' => ['group' => 'contact', 'key' => 'image_media_asset_id', 'label' => __('cms_admin_ui.settings.form.contact_image_media_asset_id'), 'type' => 'number', 'default' => null, 'sort_order' => 180],
            'company_logo_media_asset_id' => ['group' => 'branding', 'key' => 'company_logo_media_asset_id', 'label' => __('cms_admin_ui.settings.form.company_logo_media_asset_id'), 'type' => 'number', 'default' => null, 'sort_order' => 60],
            'public_text_cache_enabled' => ['group' => 'performance', 'key' => 'public_text_cache_enabled', 'label' => __('cms_admin_ui.settings.form.public_text_cache_enabled'), 'type' => 'boolean', 'default' => true, 'sort_order' => 45],
            'public_text_cache_ttl' => ['group' => 'performance', 'key' => 'public_text_cache_ttl', 'label' => __('cms_admin_ui.settings.form.public_text_cache_ttl'), 'type' => 'number', 'default' => 3600, 'sort_order' => 46],
            'seo_default_title' => ['group' => 'seo', 'key' => 'default_title', 'label' => __('cms_admin_ui.settings.form.seo_default_title'), 'type' => 'text', 'default' => null, 'sort_order' => 50],
            'seo_default_description' => ['group' => 'seo', 'key' => 'default_description', 'label' => __('cms_admin_ui.settings.form.seo_default_description'), 'type' => 'textarea', 'default' => null, 'sort_order' => 60],
            'global_noindex' => ['group' => 'seo', 'key' => 'global_noindex', 'label' => __('cms_admin_ui.settings.form.global_noindex'), 'type' => 'boolean', 'default' => false, 'sort_order' => 70],
            'seo_h1_min_length' => ['group' => 'seo', 'key' => 'h1_min_length', 'label' => __('cms_admin_ui.settings.form.seo_h1_min_length'), 'type' => 'number', 'default' => 20, 'sort_order' => 90],
            'seo_h1_max_length' => ['group' => 'seo', 'key' => 'h1_max_length', 'label' => __('cms_admin_ui.settings.form.seo_h1_max_length'), 'type' => 'number', 'default' => 70, 'sort_order' => 100],
            'seo_h2_max_length' => ['group' => 'seo', 'key' => 'h2_max_length', 'label' => __('cms_admin_ui.settings.form.seo_h2_max_length'), 'type' => 'number', 'default' => 90, 'sort_order' => 110],
            'seo_h3_max_length' => ['group' => 'seo', 'key' => 'h3_max_length', 'label' => __('cms_admin_ui.settings.form.seo_h3_max_length'), 'type' => 'number', 'default' => 100, 'sort_order' => 120],
            'seo_meta_title_min_length' => ['group' => 'seo', 'key' => 'meta_title_min_length', 'label' => __('cms_admin_ui.settings.form.seo_meta_title_min_length'), 'type' => 'number', 'default' => 30, 'sort_order' => 130],
            'seo_meta_title_max_length' => ['group' => 'seo', 'key' => 'meta_title_max_length', 'label' => __('cms_admin_ui.settings.form.seo_meta_title_max_length'), 'type' => 'number', 'default' => 60, 'sort_order' => 140],
            'seo_meta_description_min_length' => ['group' => 'seo', 'key' => 'meta_description_min_length', 'label' => __('cms_admin_ui.settings.form.seo_meta_description_min_length'), 'type' => 'number', 'default' => 120, 'sort_order' => 150],
            'seo_meta_description_max_length' => ['group' => 'seo', 'key' => 'meta_description_max_length', 'label' => __('cms_admin_ui.settings.form.seo_meta_description_max_length'), 'type' => 'number', 'default' => 160, 'sort_order' => 160],
            'seo_slug_min_length' => ['group' => 'seo', 'key' => 'slug_min_length', 'label' => __('cms_admin_ui.settings.form.seo_slug_min_length'), 'type' => 'number', 'default' => 3, 'sort_order' => 170],
            'seo_slug_max_length' => ['group' => 'seo', 'key' => 'slug_max_length', 'label' => __('cms_admin_ui.settings.form.seo_slug_max_length'), 'type' => 'number', 'default' => 80, 'sort_order' => 180],
            'seo_url_max_length' => ['group' => 'seo', 'key' => 'url_max_length', 'label' => __('cms_admin_ui.settings.form.seo_url_max_length'), 'type' => 'number', 'default' => 2000, 'sort_order' => 190],
            'seo_content_min_words' => ['group' => 'seo', 'key' => 'content_min_words', 'label' => __('cms_admin_ui.settings.form.seo_content_min_words'), 'type' => 'number', 'default' => 80, 'sort_order' => 195],
            'seo_require_meta_title_on_publish' => ['group' => 'seo', 'key' => 'require_meta_title_on_publish', 'label' => __('cms_admin_ui.settings.form.seo_require_meta_title_on_publish'), 'type' => 'boolean', 'default' => true, 'sort_order' => 200],
            'seo_require_meta_description_on_publish' => ['group' => 'seo', 'key' => 'require_meta_description_on_publish', 'label' => __('cms_admin_ui.settings.form.seo_require_meta_description_on_publish'), 'type' => 'boolean', 'default' => true, 'sort_order' => 210],
            'seo_require_single_h1' => ['group' => 'seo', 'key' => 'require_single_h1', 'label' => __('cms_admin_ui.settings.form.seo_require_single_h1'), 'type' => 'boolean', 'default' => true, 'sort_order' => 220],
            'seo_require_valid_heading_hierarchy' => ['group' => 'seo', 'key' => 'require_valid_heading_hierarchy', 'label' => __('cms_admin_ui.settings.form.seo_require_valid_heading_hierarchy'), 'type' => 'boolean', 'default' => true, 'sort_order' => 230],
            'seo_require_json_ld' => ['group' => 'seo', 'key' => 'require_json_ld', 'label' => __('cms_admin_ui.settings.form.seo_require_json_ld'), 'type' => 'boolean', 'default' => false, 'sort_order' => 240],
            'seo_require_og_image_for_posts' => ['group' => 'seo', 'key' => 'require_og_image_for_posts', 'label' => __('cms_admin_ui.settings.form.seo_require_og_image_for_posts'), 'type' => 'boolean', 'default' => false, 'sort_order' => 250],
            'robots_extra_rules' => ['group' => 'seo', 'key' => 'robots_extra_rules', 'label' => __('cms_admin_ui.settings.form.robots_extra_rules'), 'type' => 'textarea', 'default' => null, 'sort_order' => 80],
            'logo_show_tagline' => ['group' => 'branding', 'key' => 'logo_show_tagline', 'label' => __('cms_admin_ui.settings.form.show_tagline_under_logo'), 'type' => 'boolean', 'default' => false, 'sort_order' => 70],
            CmsMediaSettings::MAX_IMAGE_UPLOAD_FIELD => ['group' => CmsMediaSettings::MAX_IMAGE_UPLOAD_GROUP, 'key' => CmsMediaSettings::MAX_IMAGE_UPLOAD_KEY, 'label' => __('cms_admin_ui.settings.form.media_max_image_upload_mb'), 'type' => 'number', 'default' => config('cms_media.default_max_image_upload_mb', 20), 'sort_order' => 10],
        ];
    }

    /**
     * @param  Collection<string, CmsSetting>  $settings
     * @return array{favicon_32_url: ?string, favicon_192_url: ?string, apple_touch_icon_url: ?string, version: ?int}
     */
    private function faviconPayload($settings): array
    {
        $version = $settings->get('branding.favicon_version')?->value['value'] ?? null;

        return [
            'favicon_32_url' => $this->versionedPublicUrl($settings->get('branding.favicon_32_path')?->value['value'] ?? null, $version),
            'favicon_192_url' => $this->versionedPublicUrl($settings->get('branding.favicon_192_path')?->value['value'] ?? null, $version),
            'apple_touch_icon_url' => $this->versionedPublicUrl($settings->get('branding.apple_touch_icon_path')?->value['value'] ?? null, $version),
            'version' => is_numeric($version) ? (int) $version : null,
        ];
    }

    /**
     * @param  Collection<string, CmsSetting>  $settings
     * @return array{url: ?string, version: ?int}
     */
    private function logoPayload($settings): array
    {
        $version = $settings->get('branding.logo_version')?->value['value'] ?? null;

        return [
            'url' => $this->versionedPublicUrl($settings->get('branding.logo_path')?->value['value'] ?? null, $version),
            'version' => is_numeric($version) ? (int) $version : null,
        ];
    }

    private function versionedPublicUrl(mixed $path, mixed $version): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        $url = Storage::disk('public')->url($path);

        return is_numeric($version) ? $url.'?v='.(int) $version : $url;
    }

    private function upsertSetting(string $group, string $key, string $label, string $type, mixed $value, int $sortOrder): void
    {
        CmsSetting::query()->updateOrCreate(
            ['group' => $group, 'key' => $key],
            [
                'label' => $label,
                'type' => $type,
                'value' => ['value' => $value],
                'is_public' => true,
                'sort_order' => $sortOrder,
            ],
        );
    }

    /**
     * @param  Collection<string, CmsSetting>  $settings
     * @return array<string, array<string, mixed>>
     */
    private function settingTranslationsPayload($settings): array
    {
        $payload = [];

        foreach ($this->languageSettings->languages(true) as $language) {
            $locale = (string) $language['locale'];
            $payload[$locale] = [];

            foreach ($this->translatableSettingFields() as $field => $path) {
                $setting = $settings->get($path);
                $translation = $setting?->translations->firstWhere('locale', $locale);
                $payload[$locale][$field] = $translation?->value['value']
                    ?? $setting?->value['value']
                    ?? $this->definitions()[$field]['default']
                    ?? null;
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, array<string, mixed>>  $translations
     */
    private function saveSettingTranslations(array $translations): void
    {
        $settings = CmsSetting::query()
            ->whereIn('group', ['general', 'seo'])
            ->get()
            ->keyBy(fn (CmsSetting $setting): string => $setting->group.'.'.$setting->key);

        foreach ($this->languageSettings->languages(true) as $language) {
            $locale = (string) $language['locale'];
            $values = (array) ($translations[$locale] ?? []);

            foreach ($this->translatableSettingFields() as $field => $path) {
                $setting = $settings->get($path);

                if (! $setting instanceof CmsSetting) {
                    continue;
                }

                $value = trim((string) ($values[$field] ?? ''));

                if ($value === '' && $locale !== $this->languageSettings->defaultLocale()) {
                    continue;
                }

                $setting->translations()->updateOrCreate(
                    ['locale' => $locale],
                    ['value' => ['value' => $value !== '' ? $value : null]]
                );
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function translatableSettingFields(): array
    {
        return [
            'site_name' => 'general.site_name',
            'site_tagline' => 'general.site_tagline',
            'seo_default_title' => 'seo.default_title',
            'seo_default_description' => 'seo.default_description',
        ];
    }

    private function brandingLabel(string $key): string
    {
        return match ($key) {
            'favicon_32_path' => 'Favicon 32x32',
            'favicon_192_path' => 'Favicon 192x192',
            'apple_touch_icon_path' => 'Apple touch icon',
            'favicon_version' => 'Favicon versie',
            'logo_path' => 'Logo',
            'logo_version' => 'Logo versie',
            default => $key,
        };
    }

    private function brandingSortOrder(string $key): int
    {
        return match ($key) {
            'favicon_32_path' => 10,
            'favicon_192_path' => 20,
            'apple_touch_icon_path' => 30,
            'favicon_version' => 40,
            'logo_path' => 50,
            'logo_version' => 60,
            default => 100,
        };
    }
}
