<?php

namespace App\Http\Requests\Admin\Cms;

use App\Models\Cms\CmsPlaceableBlock;
use App\Support\Cms\CmsBlockRegistry;
use App\Support\Cms\CmsCssSourceValidator;
use App\Support\Cms\SafeBladeRenderer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCmsBlockDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->canManageBlocks();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $registry = app(CmsBlockRegistry::class);
        $zones = array_values(array_unique(array_merge($registry->contentZones(), $registry->layoutZones())));

        return [
            'key' => ['required', 'string', 'max:128', 'regex:/^[a-z0-9][a-z0-9_-]*$/'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['required', 'string', Rule::in(['content', 'header', 'navigation', 'system', 'code', 'mail'])],
            'source' => ['required', 'string', Rule::in(['user', 'system', 'package'])],
            'status' => ['required', 'string', Rule::in(['draft', 'published', 'archived'])],
            'allowed_zones' => ['required', 'array', 'min:1'],
            'allowed_zones.*' => ['required', 'string', Rule::in($zones)],
            'rendering_mode' => ['required', 'string', Rule::in($registry->renderingModes())],
            'renderer_key' => ['required', 'string', 'max:128', 'regex:/^[a-z0-9][a-z0-9_]*$/'],
            'template_source' => ['nullable', 'string'],
            'css_source' => ['nullable', 'string', 'max:100000'],
            'schema' => ['nullable', 'array'],
            'schema.fields' => ['nullable', 'array'],
            'schema.fields.*' => ['string', 'regex:/^[a-z0-9_]+$/'],
            'schema.editor_fields' => ['nullable', 'array'],
            'schema.editor_fields.*.name' => ['required_with:schema.editor_fields', 'string', 'max:120', 'regex:/^[a-z0-9_]+$/'],
            'schema.editor_fields.*.type' => ['required_with:schema.editor_fields', 'string', Rule::in($registry->editorFieldTypes())],
            'schema.editor_fields.*.required' => ['nullable', 'boolean'],
            'schema.editor_fields.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'schema.editor_fields.*.options' => ['nullable', 'array'],
            'schema.editor_fields.*.options.*.value' => ['nullable', 'string', 'max:255'],
            'schema.editor_fields.*.options.*.label' => ['nullable', 'string', 'max:255'],
            'schema.editor_fields.*.translations' => ['nullable', 'array'],
            'schema.editor_fields.*.translations.*' => ['nullable', 'array'],
            'schema.editor_fields.*.translations.*.label' => ['nullable', 'string', 'max:255'],
            'schema.editor_fields.*.translations.*.help' => ['nullable', 'string', 'max:1000'],
            'schema.editor_fields.*.translations.*.placeholder' => ['nullable', 'string', 'max:255'],
            'schema.editor_fields.*.fields' => ['nullable', 'array'],
            'schema.editor_fields.*.fields.*.name' => ['required_with:schema.editor_fields.*.fields', 'string', 'max:120', 'regex:/^[a-z0-9_]+$/'],
            'schema.editor_fields.*.fields.*.type' => ['required_with:schema.editor_fields.*.fields', 'string', Rule::in(['text', 'textarea', 'number', 'checkbox', 'select', 'media_select'])],
            'schema.editor_fields.*.fields.*.required' => ['nullable', 'boolean'],
            'schema.editor_fields.*.fields.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'schema.editor_fields.*.fields.*.options' => ['nullable', 'array'],
            'schema.editor_fields.*.fields.*.options.*.value' => ['nullable', 'string', 'max:255'],
            'schema.editor_fields.*.fields.*.options.*.label' => ['nullable', 'string', 'max:255'],
            'schema.editor_fields.*.fields.*.translations' => ['nullable', 'array'],
            'schema.editor_fields.*.fields.*.translations.*' => ['nullable', 'array'],
            'schema.editor_fields.*.fields.*.translations.*.label' => ['nullable', 'string', 'max:255'],
            'schema.editor_fields.*.fields.*.translations.*.help' => ['nullable', 'string', 'max:1000'],
            'schema.editor_fields.*.fields.*.translations.*.placeholder' => ['nullable', 'string', 'max:255'],
            'schema.preview' => ['nullable', 'array'],
            'schema.slots' => ['nullable', 'array'],
            'schema.slots.*.key' => ['required_with:schema.slots', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/'],
            'schema.slots.*.label' => ['required_with:schema.slots', 'string', 'max:120'],
            'schema.slots.*.help' => ['nullable', 'string', 'max:1000'],
            'schema.slots.*.allowed_block_keys' => ['required_with:schema.slots', 'array', 'min:1'],
            'schema.slots.*.allowed_block_keys.*' => ['required', 'string', 'max:128', 'regex:/^[a-z0-9][a-z0-9_-]*$/', Rule::exists('cms_placeable_blocks', 'key')],
            'schema.slots.*.min_items' => ['nullable', 'integer', 'min:0', 'max:50'],
            'schema.slots.*.max_items' => ['nullable', 'integer', 'min:1', 'max:50'],
            'schema.slots.*.layout' => ['required_with:schema.slots', 'string', Rule::in(['stack', 'inline', 'grid'])],
            'schema.slots.*.columns' => ['nullable', 'integer', 'min:1', 'max:12'],
            'schema.slots.*.responsive' => ['nullable', 'string', Rule::in(['same', 'wrap_mobile', 'stack_mobile'])],
            'defaults' => ['nullable', 'array'],
            'capabilities' => ['nullable', 'array'],
            'capabilities.can_edit_template' => ['nullable', 'boolean'],
            'capabilities.can_edit_css' => ['nullable', 'boolean'],
            'capabilities.can_edit_fields' => ['nullable', 'boolean'],
            'capabilities.can_edit_allowed_zones' => ['nullable', 'boolean'],
            'capabilities.can_edit_renderer' => ['nullable', 'boolean'],
            'capabilities.can_edit_defaults' => ['nullable', 'boolean'],
            'capabilities.can_edit_category' => ['nullable', 'boolean'],
            'capabilities.can_edit_admin_component' => ['nullable', 'boolean'],
            'capabilities.can_edit_slots' => ['nullable', 'boolean'],
            'admin_component_key' => ['nullable', 'string', 'max:128', 'regex:/^[a-z0-9][a-z0-9_-]*$/'],
            'package_key' => ['nullable', 'string', 'max:128', 'regex:/^[a-z0-9][a-z0-9_.-]*$/'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_locked' => ['nullable', 'boolean'],
            'requires_permission' => ['nullable', 'string', 'max:255'],
            'publish' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $blockId = (int) $this->route('block');

                $duplicateExists = CmsPlaceableBlock::query()
                    ->where('key', (string) $this->input('key'))
                    ->when($blockId > 0, fn ($query) => $query->where('id', '!=', $blockId))
                    ->exists();

                if ($duplicateExists) {
                    $validator->errors()->add('key', __('cms_admin_ui.validation.block_duplicate_key'));
                }

                if (! app(CmsCssSourceValidator::class)->isSafe((string) $this->input('css_source'))) {
                    $validator->errors()->add('css_source', __('cms_admin_ui.validation.layout_css_forbidden_syntax'));
                }

                if ($this->input('rendering_mode') === 'safe_blade' && blank($this->input('template_source'))) {
                    $validator->errors()->add('template_source', __('cms_admin_ui.validation.block_template_required'));
                }

                if ($this->input('rendering_mode') === 'safe_blade') {
                    try {
                        app(SafeBladeRenderer::class)->render((string) $this->input('template_source', ''), []);
                    } catch (\InvalidArgumentException) {
                        $validator->errors()->add('template_source', __('cms_admin_ui.validation.layout_variant_template_invalid'));
                    }
                }

                if ($this->input('rendering_mode') === 'raw_code_permissioned' && ! $this->canManageCodeBlocks()) {
                    $validator->errors()->add('rendering_mode', __('cms_admin_ui.validation.layout_code_block_forbidden'));
                }

                if ($this->input('rendering_mode') !== 'safe_blade' && ! in_array($this->input('renderer_key'), app(CmsBlockRegistry::class)->typeKeys(), true)) {
                    $validator->errors()->add('renderer_key', __('cms_admin_ui.validation.block_renderer_key_unregistered'));
                }

                $this->validateFieldSchema($validator);
                $this->validateSlotSchema($validator);
                $this->validateTemplateSlots($validator);
            },
        ];
    }

    private function validateFieldSchema(Validator $validator): void
    {
        $fields = is_array($this->input('schema.fields')) ? $this->input('schema.fields') : [];
        $editorFields = is_array($this->input('schema.editor_fields')) ? $this->input('schema.editor_fields') : [];
        $fieldKeys = [];

        foreach ($fields as $index => $fieldKey) {
            if (! is_string($fieldKey)) {
                continue;
            }

            if (in_array($fieldKey, $fieldKeys, true)) {
                $validator->errors()->add("schema.fields.{$index}", __('cms_admin_ui.validation.duplicate_template_field'));
            }

            $fieldKeys[] = $fieldKey;
        }

        foreach ($editorFields as $index => $field) {
            $name = is_array($field) ? (string) ($field['name'] ?? '') : '';
            $type = is_array($field) ? (string) ($field['type'] ?? '') : '';

            if ($name !== '' && ! in_array($name, $fieldKeys, true)) {
                $validator->errors()->add("schema.editor_fields.{$index}.name", __('cms_admin_ui.validation.template_field_forbidden'));
            }

            if ($type !== 'repeater' && is_array($field) && array_key_exists('fields', $field)) {
                $validator->errors()->add("schema.editor_fields.{$index}.fields", __('cms_admin_ui.validation.invalid_choice'));
            }

            if ($type === 'repeater') {
                $this->validateRepeaterFields($validator, is_array($field) ? $field : [], $index);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function validateRepeaterFields(Validator $validator, array $field, int $index): void
    {
        $childKeys = [];

        foreach ((array) ($field['fields'] ?? []) as $childIndex => $childField) {
            $name = is_array($childField) ? (string) ($childField['name'] ?? '') : '';

            if ($name === '') {
                continue;
            }

            if (in_array($name, $childKeys, true)) {
                $validator->errors()->add("schema.editor_fields.{$index}.fields.{$childIndex}.name", __('cms_admin_ui.validation.duplicate_template_field'));
            }

            $childKeys[] = $name;
        }
    }

    private function validateSlotSchema(Validator $validator): void
    {
        $slots = is_array($this->input('schema.slots')) ? $this->input('schema.slots') : [];
        $slotKeys = [];
        $blockKey = (string) $this->input('key', '');

        foreach ($slots as $index => $slot) {
            if (! is_array($slot)) {
                continue;
            }

            $key = (string) ($slot['key'] ?? '');

            if ($key !== '' && in_array($key, $slotKeys, true)) {
                $validator->errors()->add("schema.slots.{$index}.key", __('cms_admin_ui.validation.duplicate_slot_key'));
            }

            $slotKeys[] = $key;

            $minItems = (int) ($slot['min_items'] ?? 0);
            $maxItems = array_key_exists('max_items', $slot) && $slot['max_items'] !== null
                ? (int) $slot['max_items']
                : null;

            if ($maxItems !== null && $maxItems < $minItems) {
                $validator->errors()->add("schema.slots.{$index}.max_items", __('cms_admin_ui.validation.slot_max_items_too_low'));
            }

            foreach ((array) ($slot['allowed_block_keys'] ?? []) as $childIndex => $allowedBlockKey) {
                if ((string) $allowedBlockKey === $blockKey) {
                    $validator->errors()->add("schema.slots.{$index}.allowed_block_keys.{$childIndex}", __('cms_admin_ui.validation.slot_self_nesting_forbidden'));
                }

                $this->validateSlotAllowedBlock($validator, (string) $allowedBlockKey, $index, $childIndex);
            }
        }
    }

    private function validateSlotAllowedBlock(Validator $validator, string $allowedBlockKey, int $slotIndex, int $childIndex): void
    {
        if ($allowedBlockKey === '') {
            return;
        }

        $allowedBlock = CmsPlaceableBlock::query()->where('key', $allowedBlockKey)->first();

        if (! $allowedBlock instanceof CmsPlaceableBlock) {
            return;
        }

        if ($allowedBlock->category === 'code' || $allowedBlock->requires_permission !== null) {
            $validator->errors()->add("schema.slots.{$slotIndex}.allowed_block_keys.{$childIndex}", __('cms_admin_ui.validation.slot_child_block_forbidden'));
        }

        if (is_array($allowedBlock->schema) && ! empty($allowedBlock->schema['slots'])) {
            $validator->errors()->add("schema.slots.{$slotIndex}.allowed_block_keys.{$childIndex}", __('cms_admin_ui.validation.slot_nested_slots_forbidden'));
        }
    }

    private function validateTemplateSlots(Validator $validator): void
    {
        $slots = is_array($this->input('schema.slots')) ? $this->input('schema.slots') : [];
        $definedSlotKeys = collect($slots)
            ->filter(fn (mixed $slot): bool => is_array($slot))
            ->pluck('key')
            ->filter(fn (mixed $key): bool => is_string($key) && $key !== '')
            ->values()
            ->all();
        $template = (string) $this->input('template_source', '');

        preg_match_all('/@cmsSlot\s*\((.*?)\)/s', $template, $matches);

        $usedSlotKeys = collect($matches[1] ?? [])
            ->map(fn (mixed $key): string => trim((string) $key))
            ->filter()
            ->values()
            ->all();

        foreach ($usedSlotKeys as $usedSlotKey) {
            if (! in_array($usedSlotKey, $definedSlotKeys, true)) {
                $validator->errors()->add('template_source', __('cms_admin_ui.validation.slot_template_unknown'));
            }
        }

        foreach ($definedSlotKeys as $definedSlotKey) {
            if (! in_array($definedSlotKey, $usedSlotKeys, true)) {
                $validator->errors()->add('template_source', __('cms_admin_ui.validation.slot_template_missing'));
            }
        }
    }

    private function canManageBlocks(): bool
    {
        $user = $this->user();

        return (bool) ($user?->is_platform_admin || $user?->canAccessRoute('admin.cms.blocks.edit'));
    }

    private function canManageCodeBlocks(): bool
    {
        $user = $this->user();

        return (bool) ($user?->is_platform_admin || $user?->canAccessRoute('admin.cms.layouts.code-blocks.manage'));
    }
}
