<?php

namespace App\Support\Cms\Blocks;

use App\Support\Cms\CmsBlockRegistry;
use App\Support\Cms\CmsCssSourceValidator;
use App\Support\Cms\SafeBladeRenderer;

class CmsBlockManifestValidator
{
    public function __construct(
        private readonly CmsBlockRegistry $blockRegistry,
        private readonly CmsCssSourceValidator $cssSourceValidator,
        private readonly SafeBladeRenderer $safeBladeRenderer,
    ) {}

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<int, string>
     */
    public function errors(array $manifest): array
    {
        $errors = [];

        if (($manifest['manifest_version'] ?? null) !== 1) {
            $errors[] = 'Manifest version must be 1.';
        }

        if (! is_string($manifest['package_key'] ?? null) || ! preg_match('/^[a-z0-9][a-z0-9_.-]*$/', (string) $manifest['package_key'])) {
            $errors[] = 'Manifest package_key is invalid.';
        }

        if (! is_array($manifest['blocks'] ?? null) || $manifest['blocks'] === []) {
            $errors[] = 'Manifest must contain at least one block.';

            return $errors;
        }

        foreach (array_values($manifest['blocks']) as $index => $block) {
            if (! is_array($block)) {
                $errors[] = "Block [{$index}] must be an object.";

                continue;
            }

            array_push($errors, ...$this->blockErrors($block, $index));
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<int, string>
     */
    private function blockErrors(array $block, int $index): array
    {
        $errors = [];
        $renderingMode = (string) ($block['rendering_mode'] ?? '');

        foreach (['key', 'name', 'category', 'source', 'rendering_mode', 'renderer_key'] as $field) {
            if (! is_string($block[$field] ?? null) || trim((string) $block[$field]) === '') {
                $errors[] = "Block [{$index}] field [{$field}] is required.";
            }
        }

        if (! preg_match('/^[a-z0-9][a-z0-9_-]*$/', (string) ($block['key'] ?? ''))) {
            $errors[] = "Block [{$index}] key is invalid.";
        }

        if (! preg_match('/^[a-z0-9][a-z0-9_]*$/', (string) ($block['renderer_key'] ?? ''))) {
            $errors[] = "Block [{$index}] renderer_key is invalid.";
        }

        if (! in_array($block['category'] ?? null, ['content', 'header', 'navigation', 'system', 'code'], true)) {
            $errors[] = "Block [{$index}] category is unsupported.";
        }

        if (! in_array($block['source'] ?? null, ['user', 'system', 'package'], true)) {
            $errors[] = "Block [{$index}] source is unsupported.";
        }

        if (! in_array($renderingMode, $this->blockRegistry->renderingModes(), true)) {
            $errors[] = "Block [{$index}] rendering_mode is unsupported.";
        }

        if (! is_array($block['allowed_zones'] ?? null) || $block['allowed_zones'] === []) {
            $errors[] = "Block [{$index}] allowed_zones must contain at least one zone.";
        } else {
            $allowedZones = array_values(array_unique(array_merge($this->blockRegistry->contentZones(), $this->blockRegistry->layoutZones())));

            foreach ($block['allowed_zones'] as $zone) {
                if (! in_array($zone, $allowedZones, true)) {
                    $errors[] = "Block [{$index}] zone [{$zone}] is unsupported.";
                }
            }
        }

        if ($renderingMode !== 'safe_blade' && ! in_array($block['renderer_key'] ?? null, $this->blockRegistry->typeKeys(), true)) {
            $errors[] = "Block [{$index}] renderer_key is not registered.";
        }

        if ($renderingMode === 'safe_blade') {
            $template = (string) ($block['template_source'] ?? '');

            if (trim($template) === '') {
                $errors[] = "Block [{$index}] SafeBlade template is required.";
            } else {
                try {
                    $this->safeBladeRenderer->render($template, []);
                } catch (\InvalidArgumentException) {
                    $errors[] = "Block [{$index}] SafeBlade template is invalid.";
                }
            }
        }

        if (! $this->cssSourceValidator->isSafe((string) ($block['css_source'] ?? ''))) {
            $errors[] = "Block [{$index}] css_source contains forbidden CSS syntax.";
        }

        return $errors;
    }
}
