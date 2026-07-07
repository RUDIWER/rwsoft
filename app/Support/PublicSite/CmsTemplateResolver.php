<?php

namespace App\Support\PublicSite;

use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsTemplate;
use App\Support\Cms\CmsTemplateRegistry;
use Illuminate\Database\Eloquent\Model;

class CmsTemplateResolver
{
    public function __construct(private readonly CmsTemplateRegistry $templateRegistry) {}

    public function resolve(string $templateKey, string $locale, ?Model $contentItem = null): ?CmsTemplate
    {
        $assignedTemplate = $this->assignedTemplate($templateKey, $locale, $contentItem);

        if ($assignedTemplate instanceof CmsTemplate && $assignedTemplate->is_active) {
            return $assignedTemplate;
        }

        return CmsTemplate::query()
            ->active()
            ->defaultFor($templateKey, $locale)
            ->first();
    }

    private function assignedTemplate(string $templateKey, string $locale, ?Model $contentItem): ?CmsTemplate
    {
        if (! $contentItem instanceof Model) {
            return null;
        }

        $relation = $this->templateRegistry->relationFor($templateKey);

        if (! is_string($relation) || ! method_exists($contentItem, $relation)) {
            return null;
        }

        $contentItem->loadMissing($relation);

        $template = $contentItem->getRelation($relation);

        if (! $template instanceof CmsTemplate) {
            return null;
        }

        if ($template->locale !== $locale) {
            return null;
        }

        if ($template->template_key === $templateKey) {
            return $template;
        }

        if ($contentItem instanceof CmsPage && $templateKey === 'page.detail' && str_starts_with((string) $template->template_key, 'system.account.')) {
            return $template;
        }

        return null;
    }
}
