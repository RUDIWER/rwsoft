<?php

namespace App\Support\PublicSite;

use App\Actions\Admin\Base\RenderPlaceholdersAction;

class CmsJsonLdTemplateValidator
{
    /**
     * @return array<int, string>
     */
    public function errors(?string $template, string $context): array
    {
        if (! is_string($template) || trim($template) === '') {
            return [];
        }

        $errors = [];

        if (preg_match('/<\/?[a-z][^>]*>/i', $template) || str_contains($template, '<?')) {
            $errors[] = 'JSON-LD mag geen HTML, script of PHP bevatten.';
        }

        $unknownPlaceholders = RenderPlaceholdersAction::unknownPlaceholders($template, $context);

        if ($unknownPlaceholders !== []) {
            $errors[] = 'JSON-LD bevat niet-toegelaten placeholders: '.implode(', ', $unknownPlaceholders).'.';
        }

        $decoded = json_decode($template, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            $errors[] = 'JSON-LD moet geldige JSON zijn.';
        }

        return $errors;
    }
}
