<?php

namespace App\Support\Cms;

use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class CmsBlockRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return collect(config('cms_blocks.types', []))
            ->map(function (array $definition, string $type): array {
                $cssSource = $this->cssSourceFor($type, $definition);

                if ($cssSource !== null) {
                    $definition['css_source'] = $cssSource;
                }

                return $definition;
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function contract(): array
    {
        return config('cms_blocks.contract', []);
    }

    /**
     * @return array<int, string>
     */
    public function contentZones(): array
    {
        return array_values(Arr::get($this->contract(), 'zones.content', ['content']));
    }

    /**
     * @return array<int, string>
     */
    public function layoutZones(): array
    {
        return array_values(Arr::get($this->contract(), 'zones.layout', ['head', 'header', 'footer', 'body_end']));
    }

    /**
     * @return array<int, string>
     */
    public function editorFieldTypes(): array
    {
        return array_values(Arr::get($this->contract(), 'editor_field_types', []));
    }

    /**
     * @return array<string, mixed>
     */
    public function safeBladeContract(): array
    {
        return Arr::get($this->contract(), 'safe_blade', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function cssContract(): array
    {
        return Arr::get($this->contract(), 'css', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function behaviorContract(): array
    {
        return Arr::get($this->contract(), 'behaviors', []);
    }

    /**
     * @return array<int, string>
     */
    public function renderingModes(): array
    {
        return $this->stringList(Arr::get($this->contract(), 'rendering_modes', ['safe_blade', 'platform_blade', 'raw_code_permissioned']));
    }

    /**
     * @return array<string, mixed>
     */
    public function packageContract(): array
    {
        return Arr::get($this->contract(), 'packages', []);
    }

    /**
     * @return array<int, string>
     */
    public function typeKeys(): array
    {
        return array_keys($this->all());
    }

    /**
     * @return array<int, string>
     */
    public function contentTypeKeys(): array
    {
        return $this->typeKeysForCategory('content');
    }

    /**
     * @return array<int, string>
     */
    public function layoutTypeKeys(): array
    {
        return array_values(array_diff($this->typeKeys(), $this->contentTypeKeys()));
    }

    /**
     * @return array<int, string>
     */
    public function allowedForZone(string $zone): array
    {
        return collect($this->all())
            ->filter(fn (array $definition): bool => in_array($zone, $definition['zones'] ?? [], true))
            ->keys()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(string $type): array
    {
        return $this->all()[$type] ?? $this->all()['text'] ?? [];
    }

    public function viewFor(string $type): string
    {
        return (string) Arr::get($this->definition($type), 'view', 'public.system.blocks.text');
    }

    public function publicRuntimeViewFor(string $type): string
    {
        if (! array_key_exists($type, $this->all())) {
            return $this->safeBladeRuntimeView();
        }

        return $this->renderingModeFor($type) === 'safe_blade'
            ? $this->safeBladeRuntimeView()
            : $this->viewFor($type);
    }

    public function renderingModeFor(string $type): string
    {
        $mode = Arr::get($this->definition($type), 'rendering_mode');

        return is_string($mode) && in_array($mode, $this->renderingModes(), true)
            ? $mode
            : 'platform_blade';
    }

    public function hasSafeBladeTemplate(string $type): bool
    {
        return $this->safeBladeTemplateFor($type) !== null;
    }

    public function safeBladeTemplateFor(string $type): ?string
    {
        if (! (bool) Arr::get($this->safeBladeContract(), 'enabled', false)) {
            return null;
        }

        $templateField = (string) Arr::get($this->safeBladeContract(), 'template_definition_field', 'safe_blade_template');
        $template = Arr::get($this->definition($type), $templateField);

        return is_string($template) && trim($template) !== '' ? $template : null;
    }

    public function safeBladeRuntimeView(): string
    {
        return (string) Arr::get($this->safeBladeContract(), 'runtime_view', 'public.system.blocks.safe-blade');
    }

    /**
     * @param  array<string, mixed>|null  $definition
     */
    public function cssSourceFor(string $type, ?array $definition = null): ?string
    {
        $definition ??= config('cms_blocks.types.'.$type, []);
        $cssSource = $definition['css_source'] ?? config('cms_block_styles.'.$type);

        return is_string($cssSource) && trim($cssSource) !== '' ? trim($cssSource) : null;
    }

    /**
     * @return array<int, string>
     */
    public function fieldsFor(string $type): array
    {
        return Arr::get($this->definition($type), 'fields', []);
    }

    /**
     * @return array<string, string>
     */
    public function packageMappingsFor(string $type): array
    {
        return collect(Arr::get($this->definition($type), 'package_mappings', []))
            ->filter(fn (mixed $mapping, mixed $field): bool => is_string($field) && is_string($mapping) && $mapping !== '')
            ->all();
    }

    public function packageMappingForField(string $type, string $field): ?string
    {
        $explicitMapping = $this->packageMappingsFor($type)[$field] ?? null;

        if (is_string($explicitMapping) && $explicitMapping !== '') {
            return $explicitMapping;
        }

        $editorType = $this->editorTypeForField($type, $field);

        if ($editorType === null) {
            return null;
        }

        $mapping = Arr::get($this->packageFieldTypeMappings(), $editorType);

        return is_string($mapping) && $mapping !== '' ? $mapping : null;
    }

    /**
     * @return array<string, string>
     */
    public function packageFieldTypeMappings(): array
    {
        return collect(Arr::get($this->contract(), 'package_field_type_mappings', []))
            ->filter(fn (mixed $mapping, mixed $fieldType): bool => is_string($fieldType) && is_string($mapping) && $mapping !== '')
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultsFor(string $type): array
    {
        return Arr::get($this->definition($type), 'defaults', []);
    }

    public function labelFor(string $type): string
    {
        $labelKey = (string) Arr::get($this->definition($type), 'label_key', '');

        return $labelKey !== '' ? __('cms_admin_ui.'.$labelKey) : $type;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function repeaterFieldsFor(string $type, string $field): array
    {
        $editorField = collect(Arr::get($this->definition($type), 'editor.fields', []))
            ->first(fn (mixed $definitionField): bool => is_array($definitionField)
                && ($definitionField['type'] ?? null) === 'repeater'
                && ($definitionField['name'] ?? null) === $field);

        return is_array($editorField) && is_array($editorField['fields'] ?? null)
            ? $editorField['fields']
            : [];
    }

    /**
     * @return array<int, string>
     */
    public function repeaterFieldNamesFor(string $type, string $field): array
    {
        return collect($this->repeaterFieldsFor($type, $field))
            ->pluck('name')
            ->filter(fn (mixed $name): bool => is_string($name) && $name !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    public function normalizeRepeaterItems(string $type, string $field, mixed $value): array
    {
        $fieldNames = $this->repeaterFieldNamesFor($type, $field);

        if (! is_array($value) || $fieldNames === []) {
            return [];
        }

        return collect($value)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): array => collect($fieldNames)
                ->mapWithKeys(fn (string $fieldName): array => [
                    $fieldName => is_scalar($item[$fieldName] ?? null) ? (string) $item[$fieldName] : null,
                ])
                ->all())
            ->filter(fn (array $item): bool => collect($item)->contains(fn (mixed $fieldValue): bool => filled($fieldValue)))
            ->values()
            ->all();
    }

    public function isAllowedForZone(string $type, string $zone): bool
    {
        return in_array($type, $this->allowedForZone($zone), true);
    }

    /**
     * @return array{custom_class: string|null, css_variables: array<string, string>, behavior_key: string|null, behavior_options: array<string, mixed>}
     */
    public function runtimeMetadataFor(string $type): array
    {
        $definition = $this->definition($type);
        $behaviorKey = $definition['behavior_key'] ?? null;

        return [
            'custom_class' => $this->safeClassList($definition['custom_class'] ?? null),
            'css_variables' => $this->safeCssVariables($definition['css_variables'] ?? []),
            'behavior_key' => is_string($behaviorKey) && in_array($behaviorKey, Arr::get($this->behaviorContract(), 'keys', []), true)
                ? $behaviorKey
                : null,
            'behavior_options' => is_array($definition['behavior_options'] ?? null)
                ? $this->safeBehaviorOptions($definition['behavior_options'])
                : [],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>|null  $definitions
     * @return array<int, string>
     */
    public function validateDefinitions(?array $definitions = null): array
    {
        $errors = [];
        $definitions ??= $this->all();
        $allowedCategories = $this->stringList(Arr::get($this->contract(), 'categories', ['content', 'system', 'code']));
        $allowedZones = array_values(array_unique(array_merge($this->contentZones(), $this->layoutZones())));
        $allowedEditorFieldTypes = $this->editorFieldTypes();
        $allowedBehaviorKeys = Arr::get($this->behaviorContract(), 'keys', []);
        $allowedRenderingModes = $this->renderingModes();
        $supportedCssFields = Arr::get($this->cssContract(), 'supported_definition_fields', []);
        $safeBladeTemplateField = (string) Arr::get($this->safeBladeContract(), 'template_definition_field', 'safe_blade_template');
        $allowedPackageMappings = ['media.single', 'media.multiple', 'download.single', 'download.multiple', 'download_folder.single', 'download_folder.multiple', 'form.translation_key', 'menu.import_key', 'page.import_key', 'post.import_key', 'category.import_key', 'tag.import_key', 'scalar.portable', 'json.portable', 'repeater'];

        foreach ($this->packageFieldTypeMappings() as $fieldType => $mapping) {
            if (! in_array($mapping, $allowedPackageMappings, true)) {
                $errors[] = "Package field type mapping [{$fieldType}] uses unsupported mapping [{$mapping}].";
            }
        }

        foreach ($definitions as $type => $definition) {
            if (! is_string($type) || ! preg_match('/^[a-z0-9_]+$/', $type)) {
                $errors[] = "Block type [{$type}] must use snake_case alphanumeric keys.";
            }

            if (! is_array($definition)) {
                $errors[] = "Block type [{$type}] definition must be an array.";

                continue;
            }

            $category = $definition['category'] ?? null;
            $renderingMode = $definition['rendering_mode'] ?? null;
            $fields = $this->stringList($definition['fields'] ?? []);
            $zones = $this->stringList($definition['zones'] ?? []);
            $defaults = $definition['defaults'] ?? [];
            $editorFields = Arr::get($definition, 'editor.fields', []);
            $packageMappings = $definition['package_mappings'] ?? [];

            if (! is_string($definition['label_key'] ?? null) || trim((string) $definition['label_key']) === '') {
                $errors[] = "Block type [{$type}] must define a label_key.";
            }

            if (! is_string($category) || ! in_array($category, $allowedCategories, true)) {
                $errors[] = "Block type [{$type}] has an invalid category.";
            }

            if (! is_string($renderingMode) || ! in_array($renderingMode, $allowedRenderingModes, true)) {
                $errors[] = "Block type [{$type}] has an invalid rendering_mode.";
                $renderingMode = 'platform_blade';
            }

            if ($zones === []) {
                $errors[] = "Block type [{$type}] must define at least one zone.";
            }

            foreach ($zones as $zone) {
                if (! in_array($zone, $allowedZones, true)) {
                    $errors[] = "Block type [{$type}] uses unknown zone [{$zone}].";
                }
            }

            if (! is_string($definition['view'] ?? null) || trim((string) $definition['view']) === '') {
                $errors[] = "Block type [{$type}] must define a view.";
            }

            if (! is_array($defaults)) {
                $errors[] = "Block type [{$type}] defaults must be an array.";
                $defaults = [];
            }

            foreach ($fields as $field) {
                if (! array_key_exists($field, $defaults)) {
                    $errors[] = "Block type [{$type}] field [{$field}] is missing a default value.";
                }
            }

            if (! is_array($editorFields)) {
                $errors[] = "Block type [{$type}] editor fields must be an array.";
                $editorFields = [];
            }

            if (! is_array($packageMappings)) {
                $errors[] = "Block type [{$type}] package_mappings must be an array.";
                $packageMappings = [];
            }

            foreach ($packageMappings as $field => $mapping) {
                if (! is_string($field) || ! in_array($field, $fields, true)) {
                    $errors[] = "Block type [{$type}] package mapping references unknown field [{$field}].";
                }

                if (! is_string($mapping) || ! in_array($mapping, $allowedPackageMappings, true)) {
                    $errors[] = "Block type [{$type}] package mapping for field [{$field}] is unsupported.";
                }
            }

            foreach ($editorFields as $index => $editorField) {
                if (! is_array($editorField)) {
                    $errors[] = "Block type [{$type}] editor field [{$index}] must be an array.";

                    continue;
                }

                $fieldName = $editorField['name'] ?? null;
                $fieldType = $editorField['type'] ?? null;

                if (! is_string($fieldName) || ! in_array($fieldName, $fields, true)) {
                    $errors[] = "Block type [{$type}] editor field [{$index}] references an unknown field.";
                }

                if (! is_string($fieldType) || ! in_array($fieldType, $allowedEditorFieldTypes, true)) {
                    $errors[] = "Block type [{$type}] editor field [{$fieldName}] has an unsupported type.";
                }

                if ($fieldType === 'code' && $category !== 'code') {
                    $errors[] = "Block type [{$type}] can only use code editor fields in the code category.";
                }

                if ($fieldType === 'repeater') {
                    $repeaterFields = $editorField['fields'] ?? null;
                    $nestedFieldNames = [];

                    if (! is_array($repeaterFields) || $repeaterFields === []) {
                        $errors[] = "Block type [{$type}] repeater field [{$fieldName}] must define nested fields.";
                    } else {
                        foreach ($repeaterFields as $repeaterIndex => $repeaterField) {
                            if (! is_array($repeaterField)) {
                                $errors[] = "Block type [{$type}] repeater field [{$fieldName}] nested field [{$repeaterIndex}] must be an array.";

                                continue;
                            }

                            $nestedFieldName = $repeaterField['name'] ?? null;

                            if (! is_string($nestedFieldName) || ! preg_match('/^[a-z0-9_]+$/', $nestedFieldName)) {
                                $errors[] = "Block type [{$type}] repeater field [{$fieldName}] nested field [{$repeaterIndex}] references an unsupported field.";
                            } else {
                                $nestedFieldNames[] = $nestedFieldName;
                            }

                            if (! is_string($repeaterField['type'] ?? null) || ! in_array($repeaterField['type'], ['text', 'textarea'], true)) {
                                $errors[] = "Block type [{$type}] repeater field [{$fieldName}] nested field [{$repeaterIndex}] has an unsupported type.";
                            }
                        }

                        if (count($nestedFieldNames) !== count(array_unique($nestedFieldNames))) {
                            $errors[] = "Block type [{$type}] repeater field [{$fieldName}] nested fields must be unique.";
                        }
                    }
                }
            }

            $previewTitleField = Arr::get($definition, 'preview.title_field');

            if ($previewTitleField !== null && ! in_array($previewTitleField, $fields, true)) {
                $errors[] = "Block type [{$type}] preview title_field must reference a configured field.";
            }

            if ($renderingMode === 'raw_code_permissioned' && $category !== 'code') {
                $errors[] = "Block type [{$type}] raw code blocks must use the code category.";
            }

            if ($category === 'code' && $renderingMode !== 'raw_code_permissioned') {
                $errors[] = "Block type [{$type}] code category blocks must use raw_code_permissioned rendering.";
            }

            if ($category === 'code' && blank($definition['requires_permission'] ?? null)) {
                $errors[] = "Block type [{$type}] code blocks must define requires_permission.";
            }

            if ($renderingMode === 'raw_code_permissioned' && blank($definition['requires_permission'] ?? null)) {
                $errors[] = "Block type [{$type}] raw code blocks must define requires_permission.";
            }

            if ($renderingMode === 'safe_blade' && ! array_key_exists($safeBladeTemplateField, $definition)) {
                $errors[] = "Block type [{$type}] safe_blade blocks must define a SafeBlade template.";
            }

            if ($renderingMode !== 'safe_blade' && array_key_exists($safeBladeTemplateField, $definition)) {
                $errors[] = "Block type [{$type}] non-safe_blade blocks cannot define a SafeBlade template.";
            }

            if (array_key_exists($safeBladeTemplateField, $definition)) {
                $safeBladeTemplate = $definition[$safeBladeTemplateField];

                if ($category === 'code') {
                    $errors[] = "Block type [{$type}] code blocks cannot define a SafeBlade template.";
                }

                if (! is_string($safeBladeTemplate) || trim($safeBladeTemplate) === '') {
                    $errors[] = "Block type [{$type}] SafeBlade template must be a non-empty string.";
                } else {
                    try {
                        app(SafeBladeRenderer::class)->render($safeBladeTemplate, []);
                    } catch (\InvalidArgumentException $exception) {
                        $errors[] = "Block type [{$type}] SafeBlade template is invalid: {$exception->getMessage()}";
                    }
                }
            }

            if (array_key_exists('behavior_key', $definition) && ! in_array($definition['behavior_key'], $allowedBehaviorKeys, true)) {
                $errors[] = "Block type [{$type}] uses an unsupported behavior_key.";
            }

            if (array_key_exists('behavior_options', $definition) && ! is_array($definition['behavior_options'])) {
                $errors[] = "Block type [{$type}] behavior_options must be an array.";
            }

            if (array_key_exists('css_source', $definition)) {
                $cssSource = $definition['css_source'];

                if (! is_string($cssSource)) {
                    $errors[] = "Block type [{$type}] css_source must be a string.";
                } else {
                    $forbiddenFragments = app(CmsCssSourceValidator::class)->forbiddenFragments($cssSource);

                    if ($forbiddenFragments !== []) {
                        $errors[] = "Block type [{$type}] css_source contains forbidden CSS syntax.";
                    }
                }
            }

            foreach ($supportedCssFields as $cssField) {
                if (! array_key_exists($cssField, $definition)) {
                    continue;
                }

                if ($cssField === 'css_variables') {
                    if (! is_array($definition[$cssField])) {
                        $errors[] = "Block type [{$type}] css_variables must be an array.";
                    }

                    continue;
                }

                if (! is_string($definition[$cssField])) {
                    $errors[] = "Block type [{$type}] {$cssField} must be a string.";
                }
            }
        }

        return $errors;
    }

    /**
     * @param  array<int, string>|null  $allowedTypes
     * @return array<string, array<int, mixed>>
     */
    public function contentBlockRules(string $prefix, ?array $allowedTypes = null): array
    {
        return array_merge(
            [
                $prefix => ['nullable', 'array'],
                "{$prefix}.*.cms_placeable_block_id" => ['required', 'integer', Rule::exists('cms_placeable_blocks', 'id')->whereNull('deleted_at')],
                "{$prefix}.*.placeable_block_revision_id" => ['nullable', 'integer', Rule::exists('cms_placeable_block_revisions', 'id')],
                "{$prefix}.*.type" => ['prohibited'],
                "{$prefix}.*.renderer_key" => ['prohibited'],
                "{$prefix}.*.width_mode" => ['nullable', 'string', Rule::in(['content', 'display'])],
                "{$prefix}.*._contact_defaults_applied" => ['nullable', 'boolean'],
            ],
            $this->blockFieldRules("{$prefix}.*", false)
        );
    }

    /**
     * @param  array<int, string>|null  $allowedTypes
     * @return array<string, array<int, mixed>>
     */
    public function blockRules(string $prefix, ?array $allowedTypes = null): array
    {
        $types = $allowedTypes ?? $this->typeKeys();

        return array_merge(
            [
                $prefix => ['required', 'array'],
                "{$prefix}.id" => ['nullable', 'integer', Rule::exists('cms_blocks', 'id')],
                "{$prefix}.cms_placeable_block_id" => ['required', 'integer', Rule::exists('cms_placeable_blocks', 'id')->whereNull('deleted_at')],
                "{$prefix}.name" => ['nullable', 'string', 'max:255'],
                "{$prefix}.cache_strategy" => ['nullable', 'string', Rule::in(['inherit', 'none', 'block', 'layout'])],
                "{$prefix}._contact_defaults_applied" => ['nullable', 'boolean'],
            ],
            $this->blockFieldRules($prefix, $this->allowsCodeBlocks($types))
        );
    }

    /**
     * @return array<int, array{type: string, label_key: string, category: string|null, zones: array<int, string>, editor_visible: bool, rendering_mode: string, fields: array<int, string>, editor_fields: array<int, array<string, mixed>>, preview: array<string, mixed>}>
     */
    public function editorDefinitions(): array
    {
        return collect($this->all())
            ->map(fn (array $definition, string $type): array => [
                'type' => $type,
                'label_key' => (string) ($definition['label_key'] ?? $type),
                'category' => $definition['category'] ?? null,
                'zones' => array_values($definition['zones'] ?? []),
                'editor_visible' => (bool) ($definition['editor_visible'] ?? true),
                'rendering_mode' => $this->renderingModeFor($type),
                'fields' => array_values($definition['fields'] ?? []),
                'editor_fields' => array_values(Arr::get($definition, 'editor.fields', [])),
                'preview' => $definition['preview'] ?? [],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function typeKeysForCategory(string $category): array
    {
        return collect($this->all())
            ->filter(fn (array $definition): bool => ($definition['category'] ?? null) === $category)
            ->keys()
            ->values()
            ->all();
    }

    private function editorTypeForField(string $type, string $field): ?string
    {
        foreach (Arr::get($this->definition($type), 'editor.fields', []) as $editorField) {
            if (! is_array($editorField)) {
                continue;
            }

            if (($editorField['name'] ?? null) === $field && is_string($editorField['type'] ?? null)) {
                return $editorField['type'];
            }

            if (($editorField['type'] ?? null) !== 'repeater' || ! is_array($editorField['fields'] ?? null)) {
                continue;
            }

            foreach ($editorField['fields'] as $nestedField) {
                if (is_array($nestedField) && ($nestedField['name'] ?? null) === $field && is_string($nestedField['type'] ?? null)) {
                    return $nestedField['type'];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $types
     */
    private function allowsCodeBlocks(array $types): bool
    {
        return array_intersect($types, ['custom_head_code', 'custom_body_end_code']) !== [];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function blockFieldRules(string $prefix, bool $includeCode): array
    {
        $baseRules = [
            "{$prefix}.title" => ['nullable', 'string', 'max:255'],
            "{$prefix}.text" => ['nullable', 'string', 'max:20000'],
            "{$prefix}.second_title" => ['nullable', 'string', 'max:255'],
            "{$prefix}.second_text" => ['nullable', 'string', 'max:20000'],
            "{$prefix}.third_title" => ['nullable', 'string', 'max:255'],
            "{$prefix}.third_text" => ['nullable', 'string', 'max:20000'],
            "{$prefix}.previous_label" => ['nullable', 'string', 'max:120'],
            "{$prefix}.next_label" => ['nullable', 'string', 'max:120'],
            "{$prefix}.items" => ['nullable', 'array'],
            "{$prefix}.items.*" => ['array'],
            "{$prefix}.code" => $includeCode ? ['nullable', 'string', 'max:50000'] : null,
            "{$prefix}.source" => ['nullable', 'string', 'max:255'],
            "{$prefix}.value" => ['nullable', 'string', 'max:120'],
            "{$prefix}.suffix" => ['nullable', 'string', 'max:40'],
            "{$prefix}.video_url" => ['nullable', 'string', 'max:2048'],
            "{$prefix}.media_asset_id" => ['nullable', 'integer', Rule::exists('cms_media_assets', 'id')->whereNull('deleted_at')],
            "{$prefix}.media_asset_ids" => ['nullable', 'array'],
            "{$prefix}.media_asset_ids.*" => ['integer', Rule::exists('cms_media_assets', 'id')->whereNull('deleted_at')],
            "{$prefix}.download_asset_id" => ['nullable', 'integer', Rule::exists('cms_download_assets', 'id')->whereNull('deleted_at')],
            "{$prefix}.download_asset_ids" => ['nullable', 'array'],
            "{$prefix}.download_asset_ids.*" => ['integer', Rule::exists('cms_download_assets', 'id')->whereNull('deleted_at')],
            "{$prefix}.folder_ids" => ['nullable', 'array'],
            "{$prefix}.folder_ids.*" => ['integer', Rule::exists('cms_download_folders', 'id')],
            "{$prefix}.include_subfolders" => ['nullable', 'boolean'],
            "{$prefix}.show_descriptions" => ['nullable', 'boolean'],
            "{$prefix}.image_position" => ['nullable', 'string', Rule::in(['top', 'left', 'right', 'bottom'])],
            "{$prefix}.caption" => ['nullable', 'string', 'max:500'],
            "{$prefix}.label" => ['nullable', 'string', 'max:120'],
            "{$prefix}.url" => ['nullable', 'string', 'max:2048'],
            "{$prefix}.alt_text" => ['nullable', 'string', 'max:255'],
            "{$prefix}.link_url" => ['nullable', 'string', 'max:2048'],
            "{$prefix}.cms_menu_id" => ['nullable', 'integer', Rule::exists('cms_menus', 'id')->where('is_active', true)],
            "{$prefix}.login_label" => ['nullable', 'string', 'max:120'],
            "{$prefix}.account_label" => ['nullable', 'string', 'max:120'],
            "{$prefix}.link_label" => ['nullable', 'string', 'max:120'],
            "{$prefix}.target" => ['nullable', 'string', Rule::in(['_self', '_blank'])],
            "{$prefix}.rel" => ['nullable', 'string', 'max:255'],
            "{$prefix}.variant" => ['nullable', 'string', Rule::in(['primary', 'secondary'])],
            "{$prefix}.form_translation_key" => ['nullable', 'string', Rule::exists('cms_forms', 'translation_key')->where('is_active', true)],
            "{$prefix}.form_key" => ['nullable', 'string', Rule::exists('cms_forms', 'translation_key')->where('is_active', true)],
            "{$prefix}.source_type" => ['nullable', 'string', Rule::in(['category', 'tag'])],
            "{$prefix}.category_source" => ['nullable', 'string', Rule::in(['current', 'fixed', 'all'])],
            "{$prefix}.category_id" => ['nullable', 'integer', Rule::exists('cms_categories', 'id')],
            "{$prefix}.tag_source" => ['nullable', 'string', Rule::in(['current', 'fixed', 'all'])],
            "{$prefix}.tag_id" => ['nullable', 'integer', Rule::exists('cms_tags', 'id')],
            "{$prefix}.show_only_subcategories" => ['nullable', 'boolean'],
            "{$prefix}.limit" => ['nullable', 'integer', 'min:1', 'max:100'],
            "{$prefix}.sort_field" => ['nullable', 'string', Rule::in(['published_at', 'title', 'created_at'])],
            "{$prefix}.sort_direction" => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            "{$prefix}.show_search" => ['nullable', 'boolean'],
            "{$prefix}.show_excerpt" => ['nullable', 'boolean'],
            "{$prefix}.show_image" => ['nullable', 'boolean'],
            "{$prefix}.show_date" => ['nullable', 'boolean'],
            "{$prefix}.show_categories" => ['nullable', 'boolean'],
            "{$prefix}.empty_text" => ['nullable', 'string', 'max:500'],
            "{$prefix}.show_current" => ['nullable', 'boolean'],
            "{$prefix}.hide_missing_translations" => ['nullable', 'boolean'],
            "{$prefix}.label_display" => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::LANGUAGE_LABEL_DISPLAYS)],
            "{$prefix}.flag_position" => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::LANGUAGE_FLAG_POSITIONS)],
            "{$prefix}.flag_shape" => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::LANGUAGE_FLAG_SHAPES)],
            "{$prefix}.flag_size" => ['nullable', 'string', Rule::in(CmsResponsiveLayoutNormalizer::LANGUAGE_FLAG_SIZES)],
            "{$prefix}.compact" => ['nullable', 'boolean'],
            "{$prefix}.field_key" => ['nullable', 'string', 'max:120'],
            "{$prefix}.slot_key" => ['nullable', 'string', Rule::in(['content', 'before_list', 'after_list'])],
            "{$prefix}.heading_level" => ['nullable', 'string', Rule::in(['none', 'h1', 'h2', 'h3'])],
            "{$prefix}.show_company_name" => ['nullable', 'boolean'],
            "{$prefix}.company_name" => ['nullable', 'string', 'max:255'],
            "{$prefix}.show_address" => ['nullable', 'boolean'],
            "{$prefix}.street" => ['nullable', 'string', 'max:255'],
            "{$prefix}.postal_code" => ['nullable', 'string', 'max:40'],
            "{$prefix}.city" => ['nullable', 'string', 'max:120'],
            "{$prefix}.country" => ['nullable', 'string', 'max:120'],
            "{$prefix}.country_code" => ['nullable', 'string', 'max:40'],
            "{$prefix}.show_phones" => ['nullable', 'boolean'],
            "{$prefix}.phone_1_label" => ['nullable', 'string', 'max:120'],
            "{$prefix}.phone_1" => ['nullable', 'string', 'max:120'],
            "{$prefix}.phone_2_label" => ['nullable', 'string', 'max:120'],
            "{$prefix}.phone_2" => ['nullable', 'string', 'max:120'],
            "{$prefix}.phone_3_label" => ['nullable', 'string', 'max:120'],
            "{$prefix}.phone_3" => ['nullable', 'string', 'max:120'],
            "{$prefix}.show_emails" => ['nullable', 'boolean'],
            "{$prefix}.email_1_label" => ['nullable', 'string', 'max:120'],
            "{$prefix}.email_1" => ['nullable', 'string', 'max:255'],
            "{$prefix}.email_2_label" => ['nullable', 'string', 'max:120'],
            "{$prefix}.email_2" => ['nullable', 'string', 'max:255'],
            "{$prefix}.show_vat_number" => ['nullable', 'boolean'],
            "{$prefix}.vat_number" => ['nullable', 'string', 'max:120'],
            "{$prefix}.show_custom_fields" => ['nullable', 'boolean'],
            "{$prefix}.custom_field_1_label" => ['nullable', 'string', 'max:120'],
            "{$prefix}.custom_field_1_value" => ['nullable', 'string', 'max:255'],
            "{$prefix}.custom_field_2_label" => ['nullable', 'string', 'max:120'],
            "{$prefix}.custom_field_2_value" => ['nullable', 'string', 'max:255'],
            "{$prefix}.custom_field_3_label" => ['nullable', 'string', 'max:120'],
            "{$prefix}.custom_field_3_value" => ['nullable', 'string', 'max:255'],
        ];

        return array_filter(
            array_merge($this->editorFieldRules($prefix, $includeCode), $baseRules, $this->repeaterFieldRules($prefix)),
            fn (?array $rules): bool => $rules !== null
        );
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function editorFieldRules(string $prefix, bool $includeCode): array
    {
        $rules = [];

        foreach ($this->all() as $definition) {
            foreach (Arr::get($definition, 'editor.fields', []) as $editorField) {
                if (! is_array($editorField) || ! is_string($editorField['name'] ?? null)) {
                    continue;
                }

                $field = $editorField['name'];

                if (preg_match('/^[a-z0-9_]+$/', $field) !== 1) {
                    continue;
                }

                if (($editorField['type'] ?? null) === 'code' && ! $includeCode) {
                    continue;
                }

                $rules["{$prefix}.{$field}"] = $this->editorFieldRule($editorField);
            }
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $editorField
     * @return array<int, mixed>
     */
    private function editorFieldRule(array $editorField): array
    {
        $type = (string) ($editorField['type'] ?? 'text');

        return match ($type) {
            'checkbox' => ['nullable', 'boolean'],
            'number' => ['nullable', 'numeric'],
            'media_select' => ['nullable', 'integer', Rule::exists('cms_media_assets', 'id')->whereNull('deleted_at')],
            'media_list' => ['nullable', 'array'],
            'download_select' => ['nullable', 'integer', Rule::exists('cms_download_assets', 'id')->whereNull('deleted_at')],
            'download_list' => ['nullable', 'array'],
            'download_folder_select' => ['nullable', 'integer', Rule::exists('cms_download_folders', 'id')],
            'download_folder_list' => ['nullable', 'array'],
            'form_select' => ['nullable', 'string', Rule::exists('cms_forms', 'translation_key')->where('is_active', true)],
            'select' => $this->selectEditorFieldRule($editorField),
            'textarea', 'rich_text' => ['nullable', 'string', 'max:20000'],
            'markdown', 'code' => ['nullable', 'string', 'max:50000'],
            'repeater' => ['nullable', 'array'],
            default => ['nullable', 'string', 'max:255'],
        };
    }

    /**
     * @param  array<string, mixed>  $editorField
     * @return array<int, mixed>
     */
    private function selectEditorFieldRule(array $editorField): array
    {
        $values = collect($editorField['options'] ?? [])
            ->filter(fn (mixed $option): bool => is_array($option) && array_key_exists('value', $option))
            ->map(fn (array $option): string => (string) $option['value'])
            ->unique()
            ->values()
            ->all();

        return $values === []
            ? ['nullable', 'string', 'max:255']
            : ['nullable', 'string', Rule::in($values)];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function repeaterFieldRules(string $prefix): array
    {
        $rules = [];

        foreach ($this->all() as $definition) {
            foreach (Arr::get($definition, 'editor.fields', []) as $editorField) {
                if (! is_array($editorField) || ($editorField['type'] ?? null) !== 'repeater' || ! is_string($editorField['name'] ?? null)) {
                    continue;
                }

                $repeaterField = $editorField['name'];
                $rules["{$prefix}.{$repeaterField}"] = ['nullable', 'array'];
                $rules["{$prefix}.{$repeaterField}.*"] = ['array'];

                foreach ($editorField['fields'] ?? [] as $nestedField) {
                    if (! is_array($nestedField) || ! is_string($nestedField['name'] ?? null)) {
                        continue;
                    }

                    $max = ($nestedField['type'] ?? null) === 'textarea' ? 20000 : 255;
                    $rules["{$prefix}.{$repeaterField}.*.{$nestedField['name']}"] = ['nullable', 'string', "max:{$max}"];
                }
            }
        }

        return $rules;
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn (mixed $item): bool => is_string($item) && trim($item) !== '')
            ->values()
            ->all();
    }

    private function safeClassList(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $classes = collect(preg_split('/\s+/', trim($value)) ?: [])
            ->filter(fn (string $class): bool => preg_match('/^[A-Za-z0-9:_-]+$/', $class) === 1)
            ->values()
            ->all();

        return $classes === [] ? null : implode(' ', $classes);
    }

    /**
     * @return array<string, string>
     */
    private function safeCssVariables(mixed $variables): array
    {
        if (! is_array($variables)) {
            return [];
        }

        $safeVariables = [];

        foreach ($variables as $key => $value) {
            if (! is_string($key) || preg_match('/^--[a-z0-9-]+$/', $key) !== 1 || ! is_scalar($value)) {
                continue;
            }

            $value = trim((string) $value);

            if ($value === '' || str_contains($value, ';') || str_contains($value, '{') || str_contains($value, '}')) {
                continue;
            }

            $safeVariables[$key] = $value;
        }

        return $safeVariables;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function safeBehaviorOptions(array $options): array
    {
        return collect($options)
            ->filter(fn (mixed $value, mixed $key): bool => is_string($key) && preg_match('/^[A-Za-z0-9_]+$/', $key) === 1 && (is_scalar($value) || $value === null))
            ->all();
    }
}
