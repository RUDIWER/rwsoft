<?php

namespace App\Actions\Admin\Cms;

use App\Models\Cms\CmsBlockPlacement;
use App\Models\Cms\CmsLayout;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsSection;
use App\Models\Cms\CmsTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GenerateCmsHtmlAnchorAction
{
    public const PATTERN = '/^[a-z][a-z0-9-]{1,63}$/';

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<int, mixed>  $parts
     * @return array<string, mixed>
     */
    public function handle(Model $model, array $settings, array $parts): array
    {
        $currentAnchor = $this->currentAnchor($model);
        $incomingAnchor = $this->incomingAnchor($settings);

        if ($model->exists && $currentAnchor !== null) {
            if ($incomingAnchor !== null && $incomingAnchor !== $currentAnchor) {
                throw ValidationException::withMessages([
                    'html_anchor' => __('cms_admin_ui.validation.html_anchor_immutable'),
                ]);
            }

            $settings['html_anchor'] = $currentAnchor;

            return $settings;
        }

        if ($incomingAnchor !== null && ! $this->exists($incomingAnchor, $model->exists ? $model : null)) {
            $settings['html_anchor'] = $incomingAnchor;

            return $settings;
        }

        $settings['html_anchor'] = $this->uniqueAnchor($this->baseAnchor($parts), $model->exists ? $model : null);

        return $settings;
    }

    public function isValid(mixed $anchor): bool
    {
        return is_string($anchor) && preg_match(self::PATTERN, $anchor) === 1;
    }

    /**
     * @param  array<int, mixed>  $parts
     */
    public function preview(array $parts): string
    {
        return $this->uniqueAnchor($this->baseAnchor($parts));
    }

    private function currentAnchor(Model $model): ?string
    {
        $settings = $model->exists && is_array($model->getAttribute('settings'))
            ? $model->getAttribute('settings')
            : [];
        $anchor = $settings['html_anchor'] ?? $settings['page_style']['html_anchor'] ?? null;

        return $this->isValid($anchor) ? $anchor : null;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function incomingAnchor(array $settings): ?string
    {
        if (! array_key_exists('html_anchor', $settings)) {
            return null;
        }

        $anchor = is_scalar($settings['html_anchor']) ? trim((string) $settings['html_anchor']) : '';

        if ($anchor === '') {
            return null;
        }

        if (! $this->isValid($anchor)) {
            throw ValidationException::withMessages([
                'html_anchor' => __('cms_admin_ui.validation.html_anchor_format'),
            ]);
        }

        return $anchor;
    }

    /**
     * @param  array<int, mixed>  $parts
     */
    private function baseAnchor(array $parts): string
    {
        $base = collect($parts)
            ->filter(fn (mixed $part): bool => is_scalar($part) && trim((string) $part) !== '')
            ->map(fn (mixed $part): string => trim((string) $part))
            ->implode(' ');

        $slug = Str::slug($base) ?: 'cms-anchor';

        if (preg_match('/^[a-z]/', $slug) !== 1) {
            $slug = 'cms-'.$slug;
        }

        return mb_substr($slug, 0, 64);
    }

    private function uniqueAnchor(string $base, ?Model $ignore = null): string
    {
        $base = mb_substr(rtrim($base, '-'), 0, 64) ?: 'cms-anchor';
        $anchor = $base;
        $counter = 2;

        while ($this->exists($anchor, $ignore)) {
            $suffix = '-'.$counter;
            $anchor = mb_substr($base, 0, 64 - mb_strlen($suffix)).$suffix;
            $counter++;
        }

        return $anchor;
    }

    private function exists(string $anchor, ?Model $ignore = null): bool
    {
        return $this->layoutExists($anchor, $ignore)
            || $this->templateExists($anchor, $ignore)
            || $this->pageExists($anchor, $ignore)
            || $this->sectionExists($anchor, $ignore)
            || $this->placementExists($anchor, $ignore);
    }

    private function layoutExists(string $anchor, ?Model $ignore): bool
    {
        return CmsLayout::withTrashed()
            ->where('settings->html_anchor', $anchor)
            ->when($ignore instanceof CmsLayout && $ignore->exists, fn ($query) => $query->where($ignore->getKeyName(), '!=', $ignore->getKey()))
            ->exists();
    }

    private function pageExists(string $anchor, ?Model $ignore): bool
    {
        return CmsPage::withTrashed()
            ->where('settings->page_style->html_anchor', $anchor)
            ->when($ignore instanceof CmsPage && $ignore->exists, fn ($query) => $query->where($ignore->getKeyName(), '!=', $ignore->getKey()))
            ->exists();
    }

    private function templateExists(string $anchor, ?Model $ignore): bool
    {
        return CmsTemplate::withTrashed()
            ->where('settings->html_anchor', $anchor)
            ->when($ignore instanceof CmsTemplate && $ignore->exists, fn ($query) => $query->where($ignore->getKeyName(), '!=', $ignore->getKey()))
            ->exists();
    }

    private function sectionExists(string $anchor, ?Model $ignore): bool
    {
        return CmsSection::query()
            ->where('settings->html_anchor', $anchor)
            ->when($ignore instanceof CmsSection && $ignore->exists, fn ($query) => $query->where($ignore->getKeyName(), '!=', $ignore->getKey()))
            ->exists();
    }

    private function placementExists(string $anchor, ?Model $ignore): bool
    {
        return CmsBlockPlacement::query()
            ->where('settings->html_anchor', $anchor)
            ->when($ignore instanceof CmsBlockPlacement && $ignore->exists, fn ($query) => $query->where($ignore->getKeyName(), '!=', $ignore->getKey()))
            ->exists();
    }
}
