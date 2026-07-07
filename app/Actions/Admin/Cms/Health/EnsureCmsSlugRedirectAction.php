<?php

namespace App\Actions\Admin\Cms\Health;

use App\Models\Cms\CmsRedirect;
use App\Support\Audit\AuditLogger;
use App\Support\PublicSite\CmsLanguageSettings;
use Illuminate\Http\Request;

class EnsureCmsSlugRedirectAction
{
    public function __construct(
        private readonly CmsLanguageSettings $languageSettings,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(
        string $type,
        ?string $locale,
        ?string $oldSlug,
        ?string $newSlug,
        ?string $oldStatus,
        ?string $newStatus,
        ?int $recordId,
        ?Request $request = null,
    ): ?CmsRedirect {
        $oldSlug = trim((string) $oldSlug);
        $newSlug = trim((string) $newSlug);

        if ($oldSlug === '' || $newSlug === '' || $oldSlug === $newSlug || ! in_array('published', [$oldStatus, $newStatus], true)) {
            return null;
        }

        $sourcePath = $this->pathFor($type, $locale, $oldSlug);
        $targetPath = $this->pathFor($type, $locale, $newSlug);

        if ($sourcePath === $targetPath || CmsRedirect::query()->where('source_path', $sourcePath)->where('locale', $locale)->exists()) {
            return null;
        }

        $redirect = CmsRedirect::query()->create([
            'source_path' => $sourcePath,
            'target_url' => $targetPath,
            'status_code' => 301,
            'locale' => $locale,
            'is_active' => true,
        ]);

        $this->auditLogger->success(
            action: 'cms.redirect.auto_create_from_slug',
            module: 'cms',
            subjectType: 'cms_redirect',
            subjectKey: (string) $redirect->id,
            message: __('cms_admin_ui.health.redirect_created'),
            meta: [
                'content_type' => $type,
                'content_id' => $recordId,
                'source_path' => $sourcePath,
                'target_url' => $targetPath,
            ],
            request: $request,
        );

        return $redirect;
    }

    private function pathFor(string $type, ?string $locale, string $slug): string
    {
        $prefix = $this->localePrefix($locale);

        return match ($type) {
            'post' => $prefix.'/posts/'.$slug,
            'category' => $prefix.'/posts/category/'.$slug,
            'tag' => $prefix.'/posts/tag/'.$slug,
            default => $prefix.'/'.$slug,
        };
    }

    private function localePrefix(?string $locale): string
    {
        $locale = trim((string) $locale);

        if ($locale === '' || $locale === $this->languageSettings->defaultLocale()) {
            return '';
        }

        return '/'.$locale;
    }
}
