<?php

namespace App\Actions\PublicSite;

use App\Models\Cms\CmsSetting;
use App\Models\Cms\CmsTemplate;
use App\Models\Cms\CmsTheme;
use App\Models\Cms\CmsThemeVersion;
use App\Support\Cms\CmsTemplateDataContract;
use App\Support\PublicSite\CmsLanguageSettings;
use App\Support\PublicSite\CmsNavigationBuilder;
use App\Support\PublicSite\CmsPublicTextResolver;
use App\Support\PublicSite\CmsTemplateCompositionBuilder;
use App\Support\PublicSite\CmsTemplateResolver;
use App\Support\PublicSite\PublicStorageUrl;
use App\Support\PublicSite\PublicViewResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RenderCmsErrorPageAction
{
    /**
     * @var array<int, int>
     */
    private const SUPPORTED_STATUS_CODES = [403, 404, 419, 500, 503];

    public function __construct(
        private readonly CmsLanguageSettings $languageSettings,
        private readonly CmsTemplateResolver $templateResolver,
        private readonly CmsTemplateCompositionBuilder $templateCompositionBuilder,
        private readonly CmsNavigationBuilder $navigationBuilder,
        private readonly CmsPublicTextResolver $publicTextResolver,
        private readonly PublicStorageUrl $storageUrl,
        private readonly PublicViewResolver $viewResolver,
    ) {}

    public function handle(Request $request, int $statusCode, ?Throwable $exception = null): ?Response
    {
        $statusCode = in_array($statusCode, self::SUPPORTED_STATUS_CODES, true) ? $statusCode : 500;
        $locale = $this->resolveLocale($request);
        $template = $this->templateResolver->resolve("error.{$statusCode}", $locale)
            ?? $this->templateResolver->resolve('error.default', $locale);

        if (! $template instanceof CmsTemplate) {
            return null;
        }

        $error = $this->errorPayload($request, $statusCode, $locale, $exception);
        $contentItem = [
            'id' => 0,
            'title' => $error['title'],
            'short_description' => $error['message'],
            'slug' => "error-{$statusCode}",
            'locale' => $locale,
            'url' => $request->getPathInfo(),
            'template_class' => 'error',
            'template_key' => (string) $template->template_key,
            'template_data' => [],
            'settings' => [],
        ];
        $context = [
            'error' => $error,
            'template' => [],
            '__template' => [
                'template_class' => $template->template_class,
                'template_key' => $template->template_key,
                'locale' => $template->locale,
                'data_contract' => app(CmsTemplateDataContract::class)->normalize($template->data_contract, (string) $template->template_key),
            ],
        ];
        $composition = $this->templateCompositionBuilder->handle($template, $contentItem, $context);

        return response()->view($this->viewResolver->template(), [
            'pageItem' => $composition['page'],
            'composition' => $composition,
            'site' => $this->sitePayload($request, $locale),
            'navigation' => $this->navigationBuilder->handle($locale),
            'translations' => [],
            'seo' => $this->seoPayload($request, $error, $locale),
        ], $statusCode);
    }

    private function resolveLocale(Request $request): string
    {
        $firstSegment = trim((string) $request->segment(1));
        $normalizedSegment = str_replace('-', '_', $firstSegment);

        if (in_array($normalizedSegment, $this->languageSettings->activeLocales(), true)) {
            return $normalizedSegment;
        }

        return $this->languageSettings->defaultLocale();
    }

    /**
     * @return array{status_code: int, title: string, message: string, request_path: string, home_url: string}
     */
    private function errorPayload(Request $request, int $statusCode, string $locale, ?Throwable $exception): array
    {
        return [
            'status_code' => $statusCode,
            'title' => $this->errorTitle($statusCode, $locale),
            'message' => $this->errorMessage($statusCode, $locale, $exception),
            'request_path' => $request->getPathInfo(),
            'home_url' => $this->languageSettings->pathPrefix($locale) ?: '/',
        ];
    }

    private function errorTitle(int $statusCode, string $locale): string
    {
        return match ($statusCode) {
            403 => public_text('errors.403.title', 'Access denied', $locale),
            404 => public_text('errors.404.title', 'Page not found', $locale),
            419 => public_text('errors.419.title', 'Page expired', $locale),
            503 => public_text('errors.503.title', 'Temporarily unavailable', $locale),
            default => public_text('errors.500.title', 'Something went wrong', $locale),
        };
    }

    private function errorMessage(int $statusCode, string $locale, ?Throwable $exception): string
    {
        $message = trim((string) $exception?->getMessage());

        if ($statusCode < 500 && $message !== '') {
            return $message;
        }

        return match ($statusCode) {
            403 => public_text('errors.403.message', 'You do not have access to this page.', $locale),
            404 => public_text('errors.404.message', 'The page you are looking for could not be found.', $locale),
            419 => public_text('errors.419.message', 'This page has expired. Please go back and try again.', $locale),
            503 => public_text('errors.503.message', 'The site is temporarily unavailable. Please try again later.', $locale),
            default => public_text('errors.500.message', 'The site could not process your request right now.', $locale),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function sitePayload(Request $request, string $locale): array
    {
        return [
            'name' => $this->settingValue('general', 'site_name', config('app.name', 'RwSoft'), $locale),
            'tagline' => $this->settingValue('general', 'site_tagline', null, $locale),
            'default_locale' => $this->languageSettings->defaultLocale(),
            'current_locale' => $locale,
            'multilingual_enabled' => $this->languageSettings->multilingualEnabled(),
            'available_languages' => $this->languageSettings->languages(true),
            'available_locales' => $this->languageSettings->activeLocales(),
            'global_noindex' => true,
            'seo_default_title' => $this->settingValue('seo', 'default_title', null, $locale),
            'seo_default_description' => $this->settingValue('seo', 'default_description', null, $locale),
            'texts' => $this->publicTextResolver->all($locale),
            'active_theme_css_url' => $this->themeCssUrl($request),
            'favicon' => $this->faviconPayload(),
            'logo_url' => $this->versionedPublicUrl(
                $this->settingValue('branding', 'logo_path'),
                $this->settingValue('branding', 'logo_version'),
            ),
            'logo_show_tagline' => (bool) $this->settingValue('branding', 'logo_show_tagline', false),
        ];
    }

    /**
     * @param  array{status_code: int, title: string, message: string, request_path: string, home_url: string}  $error
     * @return array<string, mixed>
     */
    private function seoPayload(Request $request, array $error, string $locale): array
    {
        return [
            'title' => $error['title'],
            'description' => $error['message'],
            'robots' => 'noindex,nofollow',
            'canonical_url' => $request->url(),
            'og_type' => 'website',
            'og_locale' => str_replace('-', '_', $locale),
            'og_title' => $error['title'],
            'og_description' => $error['message'],
            'og_url' => $request->url(),
            'twitter_card' => 'summary',
            'twitter_title' => $error['title'],
            'twitter_description' => $error['message'],
        ];
    }

    /**
     * @return array{favicon_32_url: ?string, favicon_192_url: ?string, apple_touch_icon_url: ?string}
     */
    private function faviconPayload(): array
    {
        $version = $this->settingValue('branding', 'favicon_version');

        return [
            'favicon_32_url' => $this->versionedPublicUrl($this->settingValue('branding', 'favicon_32_path'), $version),
            'favicon_192_url' => $this->versionedPublicUrl($this->settingValue('branding', 'favicon_192_path'), $version),
            'apple_touch_icon_url' => $this->versionedPublicUrl($this->settingValue('branding', 'apple_touch_icon_path'), $version),
        ];
    }

    private function themeCssUrl(Request $request): ?string
    {
        if (! Schema::hasTable('cms_themes')) {
            return null;
        }

        if ($request->filled(['theme_preview', 'theme_version'])) {
            $theme = CmsTheme::query()->find((int) $request->query('theme_preview'));

            if ($theme instanceof CmsTheme) {
                return route('cms.theme.preview', [
                    'theme' => $theme->id,
                    'hash' => (string) $request->query('theme_version'),
                ]);
            }
        }

        $theme = CmsTheme::query()
            ->with('activeVersion')
            ->where('is_active', true)
            ->first();

        if (! $theme instanceof CmsTheme || ! $theme->activeVersion instanceof CmsThemeVersion) {
            return null;
        }

        return route('cms.theme.active', ['hash' => $theme->activeVersion->version_hash]);
    }

    private function versionedPublicUrl(mixed $path, mixed $version): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        return $this->storageUrl->versionedUrl($path, $version);
    }

    private function settingValue(string $group, string $key, mixed $default = null, ?string $locale = null): mixed
    {
        if (! Schema::hasTable('cms_settings')) {
            return $default;
        }

        $setting = CmsSetting::query()
            ->where('group', $group)
            ->where('key', $key)
            ->first();

        if (! $setting instanceof CmsSetting) {
            return $default;
        }

        $value = $setting->value;

        if ($locale !== null && is_array($value['translations'] ?? null) && array_key_exists($locale, $value['translations'])) {
            return $value['translations'][$locale];
        }

        return $value['value'] ?? $default;
    }
}
