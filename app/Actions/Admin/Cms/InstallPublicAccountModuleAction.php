<?php

namespace App\Actions\Admin\Cms;

use App\Models\Cms\CmsBlock;
use App\Models\Cms\CmsBlockPlacement;
use App\Models\Cms\CmsEmail;
use App\Models\Cms\CmsForm;
use App\Models\Cms\CmsFormField;
use App\Models\Cms\CmsLayout;
use App\Models\Cms\CmsMailTemplate;
use App\Models\Cms\CmsModule;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPlaceableBlock;
use App\Models\Cms\CmsPlaceableBlockRevision;
use App\Models\Cms\CmsSection;
use App\Models\Cms\CmsSetting;
use App\Models\Cms\CmsTemplate;
use App\Models\PublicSite\SiteUserProfileFieldDefinition;
use App\Support\Cms\CmsSystemMailRegistry;
use App\Support\PublicSite\CmsLanguageSettings;
use App\Support\PublicSite\PublicAccountSettings;
use App\Support\PublicSite\PublicAccountSystemFormRegistry;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InstallPublicAccountModuleAction
{
    public function __construct(
        private readonly SyncPublicTextKeysAction $syncPublicTextKeys,
        private readonly CmsLanguageSettings $languageSettings,
        private readonly PublicAccountSystemFormRegistry $systemForms,
        private readonly CmsSystemMailRegistry $systemMails,
    ) {}

    /**
     * @return array{pages:int,blocks:int,forms:int,profile_fields:int,templates:int,mail_templates:int,emails:int}
     */
    public function handle(): array
    {
        return DB::connection('tenant')->transaction(function (): array {
            $this->ensureModuleRecord();
            $this->ensureSettings();
            $profileFields = $this->ensureProfileFieldDefinitions();
            $forms = $this->ensureFormsSynced();

            $blocks = $this->ensureBlocksSynced();
            $this->migrateLegacyAccountControlBlocks($blocks['site_user_account_controls']);
            $this->migrateStandardAuthPanelBlocks($blocks['site_user_auth_panel']);
            $this->removeLegacyAccountControlBlocks();
            $mail = $this->ensureSystemMailsSynced();
            $templates = $this->ensureSystemTemplates($blocks);
            $pages = $this->ensurePages($templates['items']);
            $this->syncPublicTextKeys->handle();

            return [
                'pages' => $pages,
                'blocks' => count($blocks),
                'forms' => $forms,
                'profile_fields' => $profileFields,
                'templates' => $templates['count'],
                'mail_templates' => $mail['templates'],
                'emails' => $mail['emails'],
            ];
        });
    }

    private function ensureModuleRecord(): void
    {
        CmsModule::query()->updateOrCreate(
            ['key' => 'public-account'],
            [
                'name' => 'Public Account',
                'status' => 'active',
                'settings' => [],
                'installed_at' => now(),
            ],
        );
    }

    private function ensureSettings(): void
    {
        $this->upsertSetting(PublicAccountSettings::REGISTRATION_ENABLED, 'Registration enabled', 'boolean', false, 10);
        $this->upsertSetting(PublicAccountSettings::EMAIL_VERIFICATION_REQUIRED, 'Email verification required', 'boolean', false, 20);
        $this->upsertSetting(PublicAccountSettings::TWO_FACTOR_MODE, 'Two-factor authentication mode', 'select', 'disabled', 30);
    }

    private function upsertSetting(string $key, string $label, string $type, mixed $value, int $sortOrder): void
    {
        CmsSetting::query()->updateOrCreate(
            ['group' => PublicAccountSettings::GROUP, 'key' => $key],
            [
                'label' => $label,
                'type' => $type,
                'value' => ['value' => $value],
                'is_public' => false,
                'sort_order' => $sortOrder,
            ],
        );
    }

    private function ensureProfileFieldDefinitions(): int
    {
        $synced = 0;

        foreach ($this->systemForms->profileFieldDefinitions() as $definition) {
            $field = SiteUserProfileFieldDefinition::query()->firstOrNew([
                'key' => (string) $definition['key'],
            ]);
            $isNew = ! $field->exists;

            if ($isNew) {
                $field->label = (string) __((string) $definition['label_key'], [], 'en');
            }

            $field->fill([
                'type' => (string) $definition['type'],
                'options' => $this->translatedOptions((array) ($definition['options'] ?? []), 'en'),
                'validation_rules' => (array) ($definition['validation_rules'] ?? ['nullable']),
                'is_required' => (bool) ($definition['is_required'] ?? false),
                'is_active' => true,
                'show_on_register' => (bool) ($definition['show_on_register'] ?? false),
                'show_on_profile' => (bool) ($definition['show_on_profile'] ?? true),
                'sort_order' => (int) ($definition['sort_order'] ?? 0),
                'settings' => [
                    'source' => 'public-account',
                ],
            ]);
            $field->save();
            $synced++;
        }

        return $synced;
    }

    private function ensureFormsSynced(): int
    {
        $synced = 0;

        foreach ($this->accountPageLocales() as $locale) {
            foreach ($this->systemForms->forms() as $systemKey => $definition) {
                $form = CmsForm::query()->firstOrNew([
                    'system_key' => $systemKey,
                    'locale' => $locale,
                ]);
                $isNew = ! $form->exists;

                if ($isNew) {
                    $form->fill([
                        'title' => (string) __((string) $definition['title_key'], [], $locale),
                        'translation_key' => 'paf.'.str($systemKey)->after('site_user_')->replace('_', '-')->toString(),
                        'description' => null,
                        'notification_email' => null,
                        'submit_button_label' => (string) __((string) $definition['submit_key'], [], $locale),
                        'success_message' => (string) __((string) $definition['success_key'], [], $locale),
                        'is_active' => true,
                    ]);
                }

                $form->forceFill([
                    'form_kind' => 'system',
                    'system_key' => $systemKey,
                    'locale' => $locale,
                    'settings' => array_merge((array) ($form->settings ?? []), [
                        'system' => [
                            'module' => 'public-account',
                            'locked' => true,
                        ],
                    ]),
                ])->save();

                $this->ensureSystemFormFields($form, $definition, $locale);
                $synced++;
            }
        }

        return $synced;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function ensureSystemFormFields(CmsForm $form, array $definition, string $locale): void
    {
        $fields = collect((array) ($definition['fields'] ?? []));

        if ((bool) ($definition['profile_fields'] ?? false)) {
            $fields = $fields->merge(collect($this->systemForms->profileFieldDefinitions())->map(function (array $profileField): array {
                return [
                    'key' => 'profile_'.$profileField['key'],
                    'type' => $profileField['type'],
                    'label_key' => $profileField['label_key'],
                    'is_required' => (bool) ($profileField['is_required'] ?? false),
                    'options' => (array) ($profileField['options'] ?? []),
                    'settings' => [
                        'system' => [
                            'locked' => true,
                            'source' => 'profile_field',
                            'profile_field_key' => $profileField['key'],
                        ],
                    ],
                ];
            }));
        }

        $fields->values()->each(function (array $fieldDefinition, int $index) use ($form, $locale): void {
            $field = CmsFormField::query()->firstOrNew([
                'cms_form_id' => $form->id,
                'translation_key' => (string) $fieldDefinition['key'],
            ]);
            $isNew = ! $field->exists;

            if ($isNew) {
                $field->fill([
                    'label' => (string) __((string) $fieldDefinition['label_key'], [], $locale),
                    'placeholder' => null,
                    'help_text' => null,
                    'width' => 'full',
                ]);
            }

            $field->forceFill([
                'cms_form_id' => $form->id,
                'type' => (string) $fieldDefinition['type'],
                'translated_from_form_field_id' => null,
                'options' => $this->translatedOptions((array) ($fieldDefinition['options'] ?? []), $locale),
                'validation_rules' => (bool) ($fieldDefinition['is_required'] ?? false) ? ['required'] : ['nullable'],
                'sort_order' => ($index + 1) * 10,
                'is_required' => (bool) ($fieldDefinition['is_required'] ?? false),
                'is_active' => true,
                'settings' => (array) ($fieldDefinition['settings'] ?? []),
            ])->save();
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     * @return array<int, array{key:string,label:string}>
     */
    private function translatedOptions(array $options, string $locale): array
    {
        return collect($options)
            ->map(fn (array $option): array => [
                'key' => (string) $option['key'],
                'label' => (string) __((string) $option['label_key'], [], $locale),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, array{block:CmsPlaceableBlock,revision:CmsPlaceableBlockRevision}>
     */
    private function ensureBlocksSynced(): array
    {
        $keys = [
            'site_user_auth_panel',
            'site_user_login_form',
            'site_user_register_form',
            'site_user_forgot_password_form',
            'site_user_reset_password_form',
            'site_user_dashboard',
            'site_user_profile_form',
            'site_user_security_settings',
            'site_user_two_factor_challenge',
            'site_user_account_controls',
        ];

        return collect($keys)
            ->mapWithKeys(fn (string $key): array => [$key => $this->ensurePlaceableBlock($key)])
            ->all();
    }

    /**
     * @return array{block:CmsPlaceableBlock,revision:CmsPlaceableBlockRevision}
     */
    private function ensurePlaceableBlock(string $key): array
    {
        $definition = (array) config("cms_blocks.types.{$key}", []);
        $now = now();
        $zones = array_values(array_filter((array) ($definition['zones'] ?? ['content']), fn (mixed $zone): bool => is_string($zone) && $zone !== ''));
        $schema = [
            'category' => $definition['category'] ?? null,
            'fields' => array_values((array) ($definition['fields'] ?? [])),
            'editor_fields' => array_values((array) Arr::get($definition, 'editor.fields', [])),
            'editor_visible' => (bool) ($definition['editor_visible'] ?? true),
            'preview' => is_array($definition['preview'] ?? null) ? $definition['preview'] : [],
        ];
        $payload = [
            'name' => $this->blockName($key),
            'description' => null,
            'category' => (string) ($definition['category'] ?? 'system'),
            'source' => 'system',
            'status' => 'published',
            'allowed_zones' => $zones,
            'rendering_mode' => (string) ($definition['rendering_mode'] ?? 'platform_blade'),
            'renderer_key' => $key,
            'template_source' => null,
            'css_source' => null,
            'schema' => $schema,
            'defaults' => is_array($definition['defaults'] ?? null) ? $definition['defaults'] : [],
            'capabilities' => [
                'can_edit_template' => false,
                'can_edit_css' => false,
                'can_edit_fields' => false,
                'can_edit_allowed_zones' => false,
                'can_edit_renderer' => false,
                'can_edit_defaults' => false,
            ],
            'behavior_config' => [],
            'context_config' => [],
            'admin_component_key' => null,
            'package_key' => 'public-account',
            'sort_order' => 0,
            'is_locked' => true,
            'requires_permission' => null,
            'published_at' => $now,
        ];

        $block = CmsPlaceableBlock::query()->withTrashed()->firstOrNew(['key' => $key]);
        $block->fill($payload);
        $block->deleted_at = null;
        $block->save();

        $revision = CmsPlaceableBlockRevision::query()->firstOrNew([
            'cms_placeable_block_id' => $block->id,
            'revision_number' => 1,
        ]);
        $revision->fill(array_merge($payload, [
            'status' => 'published',
            'title' => $payload['name'],
            'snapshot_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
            'author_id' => null,
            'metadata' => [],
        ]));
        $revision->save();

        return ['block' => $block, 'revision' => $revision];
    }

    /**
     * @return array{templates:int,emails:int}
     */
    private function ensureSystemMailsSynced(): array
    {
        $mailBlocks = $this->ensureMailPlaceableBlocks();
        $templates = 0;
        $emails = 0;

        foreach ($this->systemMails->templates() as $templateKey => $definition) {
            $template = $this->ensureMailTemplate($templateKey, $definition, $mailBlocks);

            if ($template instanceof CmsMailTemplate) {
                $templates++;
            }
        }

        foreach ($this->systemMails->all() as $systemKey => $definition) {
            $template = CmsMailTemplate::query()
                ->where('key', (string) $definition['template_key'])
                ->first();

            if (! $template instanceof CmsMailTemplate) {
                continue;
            }

            foreach ($this->accountPageLocales() as $locale) {
                $this->ensureSystemEmail($systemKey, $definition, $template, $locale);
                $emails++;
            }
        }

        return ['templates' => $templates, 'emails' => $emails];
    }

    /**
     * @return array<string, array{block:CmsPlaceableBlock,revision:CmsPlaceableBlockRevision}>
     */
    private function ensureMailPlaceableBlocks(): array
    {
        return collect($this->mailBlockDefinitions())
            ->mapWithKeys(fn (array $definition, string $key): array => [$key => $this->ensureMailPlaceableBlock($key, $definition)])
            ->all();
    }

    /**
     * @param  array{name:string,description:string,fields:list<string>,editor_fields:list<array<string, mixed>>,defaults:array<string, mixed>}  $definition
     * @return array{block:CmsPlaceableBlock,revision:CmsPlaceableBlockRevision}
     */
    private function ensureMailPlaceableBlock(string $key, array $definition): array
    {
        $now = now();
        $payload = [
            'name' => $definition['name'],
            'description' => $definition['description'],
            'category' => 'mail',
            'source' => 'system',
            'status' => 'published',
            'allowed_zones' => ['content'],
            'rendering_mode' => 'safe_blade',
            'renderer_key' => $key,
            'template_source' => null,
            'css_source' => null,
            'schema' => [
                'fields' => $definition['fields'],
                'editor_fields' => $definition['editor_fields'],
            ],
            'defaults' => $definition['defaults'],
            'capabilities' => [
                'can_edit_template' => false,
                'can_edit_css' => false,
                'can_edit_fields' => false,
                'can_edit_allowed_zones' => false,
                'can_edit_renderer' => false,
                'can_edit_defaults' => false,
                'can_edit_category' => false,
                'can_edit_admin_component' => false,
                'can_edit_slots' => false,
            ],
            'behavior_config' => [],
            'context_config' => [],
            'admin_component_key' => null,
            'package_key' => 'public-account',
            'sort_order' => (int) ($definition['sort_order'] ?? 1000),
            'is_locked' => true,
            'requires_permission' => null,
            'published_at' => $now,
        ];

        $block = CmsPlaceableBlock::query()->withTrashed()->firstOrNew(['key' => $key]);
        $block->fill($payload);
        $block->deleted_at = null;
        $block->save();

        $revision = CmsPlaceableBlockRevision::query()->firstOrNew([
            'cms_placeable_block_id' => $block->id,
            'revision_number' => 1,
        ]);
        $revision->fill(array_merge($payload, [
            'status' => 'published',
            'title' => $payload['name'],
            'snapshot_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
            'author_id' => null,
            'metadata' => ['source' => 'public-account-mail'],
        ]));
        $revision->save();

        return ['block' => $block, 'revision' => $revision];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, array{block:CmsPlaceableBlock,revision:CmsPlaceableBlockRevision}>  $mailBlocks
     */
    private function ensureMailTemplate(string $templateKey, array $definition, array $mailBlocks): ?CmsMailTemplate
    {
        $template = CmsMailTemplate::query()->firstOrNew(['key' => $templateKey]);
        $settings = array_replace_recursive((array) ($template->settings ?? []), [
            'system' => [
                'module' => 'public-account',
                'locked_key' => true,
            ],
        ]);

        $template->fill([
            'name' => (string) $definition['name'],
            'description' => (string) ($definition['description'] ?? ''),
            'context_key' => (string) $definition['context_key'],
            'body_blocks' => array_values((array) ($definition['body_blocks'] ?? [])),
            'settings' => $settings,
            'is_active' => true,
        ]);
        $template->save();

        $section = CmsSection::query()->firstOrCreate(
            [
                'owner_type' => CmsMailTemplate::class,
                'owner_id' => $template->id,
                'zone' => 'content',
                'import_key' => 'mail-template.'.$templateKey.'.content',
            ],
            [
                'name' => (string) $definition['name'],
                'sort_order' => 0,
                'is_active' => true,
                'settings' => [
                    'source' => 'public_account_mail_install',
                    'layout_type' => 'standard',
                    'width_mode' => 'content',
                    'spacing' => 'normal',
                    'scroll_behavior' => 'normal',
                ],
            ],
        );

        foreach (array_values((array) ($definition['body_blocks'] ?? [])) as $index => $bodyBlock) {
            if (! is_array($bodyBlock)) {
                continue;
            }

            $this->ensureMailPlacement($section, $templateKey, $bodyBlock, $mailBlocks, $index * 10);
        }

        return $template;
    }

    /**
     * @param  array<string, mixed>  $bodyBlock
     * @param  array<string, array{block:CmsPlaceableBlock,revision:CmsPlaceableBlockRevision}>  $mailBlocks
     */
    private function ensureMailPlacement(CmsSection $section, string $templateKey, array $bodyBlock, array $mailBlocks, int $sortOrder): void
    {
        $contentKey = (string) ($bodyBlock['key'] ?? '');
        $mailBlockKey = $this->mailBlockKey((string) ($bodyBlock['type'] ?? ''));

        if ($contentKey === '' || $mailBlockKey === '' || ! isset($mailBlocks[$mailBlockKey])) {
            return;
        }

        $placeableBlock = $mailBlocks[$mailBlockKey]['block'];
        $revision = $mailBlocks[$mailBlockKey]['revision'];
        $importPrefix = 'mail-template.'.$templateKey;
        $block = CmsBlock::query()->firstOrCreate(
            ['import_key' => $importPrefix.'.block.'.$contentKey],
            [
                'cms_placeable_block_id' => $placeableBlock->id,
                'placeable_block_revision_id' => $revision->id,
                'type' => $mailBlockKey,
                'name' => (string) ($bodyBlock['label'] ?? $contentKey),
                'content' => $this->mailBlockContent((string) ($bodyBlock['type'] ?? '')),
                'settings' => ['source' => 'public_account_mail_install'],
                'is_shared' => false,
                'is_dynamic' => false,
                'cache_strategy' => 'inherit',
            ],
        );

        $block->forceFill([
            'cms_placeable_block_id' => $placeableBlock->id,
            'placeable_block_revision_id' => $revision->id,
            'type' => $mailBlockKey,
        ])->save();

        $placement = CmsBlockPlacement::query()->firstOrCreate(
            ['import_key' => $importPrefix.'.placement.'.$contentKey],
            [
                'cms_section_id' => $section->id,
                'cms_block_id' => $block->id,
                'sort_order' => $sortOrder,
                'is_active' => true,
                'settings' => [],
            ],
        );

        $placement->forceFill([
            'cms_section_id' => $section->id,
            'cms_block_id' => $block->id,
            'sort_order' => $sortOrder,
            'is_active' => true,
            'visible_mobile' => true,
            'visible_tablet' => true,
            'visible_desktop' => true,
            'mobile_span' => 12,
            'tablet_span' => 12,
            'desktop_span' => 12,
            'height_mode' => 'auto',
            'height_value' => null,
            'cache_strategy' => 'inherit',
            'settings' => array_replace((array) ($placement->settings ?? []), [
                'source' => 'public_account_mail_install',
                'content_key' => $contentKey,
                'editor_label' => (string) ($bodyBlock['label'] ?? $contentKey),
                'page_editable' => $contentKey !== 'company_logo',
            ]),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function ensureSystemEmail(string $systemKey, array $definition, CmsMailTemplate $template, string $locale): void
    {
        $defaults = $this->systemMails->defaults($systemKey, $locale);
        $email = CmsEmail::query()->firstOrNew([
            'system_key' => $systemKey,
            'locale' => $locale,
        ]);
        $isNew = ! $email->exists;
        $settings = array_replace_recursive((array) ($email->settings ?? []), [
            'system' => [
                'module' => 'public-account',
                'locked_key' => true,
            ],
        ]);

        $email->fill([
            'cms_mail_template_id' => $template->id,
            'title' => (string) $definition['label'],
            'translation_key' => $email->translation_key ?: (string) Str::ulid(),
            'email_type' => 'system',
            'context_key' => (string) $definition['context_key'],
            'subject' => $isNew ? (string) ($defaults['subject'] ?? '') : $email->subject,
            'preheader' => $isNew ? ($defaults['preheader'] ?? null) : $email->preheader,
            'content_blocks' => $isNew ? (array) ($defaults['content_blocks'] ?? []) : (array) ($email->content_blocks ?? []),
            'plain_text' => $email->plain_text,
            'settings' => $settings,
            'is_active' => true,
        ]);
        $email->save();
    }

    private function mailBlockKey(string $type): string
    {
        return match ($type) {
            'company_logo' => 'mail_company_logo',
            'heading' => 'mail_heading',
            'button' => 'mail_button',
            'image' => 'mail_image',
            'divider' => 'mail_divider',
            'spacer' => 'mail_spacer',
            'form_answers' => 'mail_form_answers',
            default => 'mail_text',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function mailBlockContent(string $type): array
    {
        return match ($type) {
            'button' => ['url' => '{{ action.url }}'],
            'spacer' => ['height' => 24],
            default => [],
        };
    }

    /**
     * @return array<string, array{name:string,description:string,fields:list<string>,editor_fields:list<array<string, mixed>>,defaults:array<string, mixed>,sort_order:int}>
     */
    private function mailBlockDefinitions(): array
    {
        return [
            'mail_company_logo' => $this->mailBlock('Mail company logo', 'Company logo configured in CMS settings.', [], [], [], 999),
            'mail_heading' => $this->mailBlock('Mail heading', 'Large heading text.', ['text'], [
                ['name' => 'text', 'type' => 'textarea', 'required' => true, 'sort_order' => 10],
            ], ['text' => 'Heading'], 1000),
            'mail_text' => $this->mailBlock('Mail text', 'Paragraph text.', ['text'], [
                ['name' => 'text', 'type' => 'textarea', 'required' => true, 'sort_order' => 10],
            ], ['text' => 'Text'], 1001),
            'mail_button' => $this->mailBlock('Mail button', 'Call-to-action button.', ['label', 'url'], [
                ['name' => 'label', 'type' => 'text', 'required' => true, 'sort_order' => 10],
                ['name' => 'url', 'type' => 'text', 'required' => false, 'sort_order' => 20],
            ], ['label' => 'Open', 'url' => ''], 1002),
            'mail_image' => $this->mailBlock('Mail image', 'Public image for emails.', ['media_asset_id', 'alt', 'caption'], [
                ['name' => 'media_asset_id', 'type' => 'media_select', 'required' => false, 'sort_order' => 10],
                ['name' => 'alt', 'type' => 'text', 'required' => false, 'sort_order' => 20],
                ['name' => 'caption', 'type' => 'text', 'required' => false, 'sort_order' => 30],
            ], [], 1003),
            'mail_divider' => $this->mailBlock('Mail divider', 'Simple horizontal divider.', [], [], [], 1004),
            'mail_spacer' => $this->mailBlock('Mail spacer', 'Vertical whitespace.', ['height'], [
                ['name' => 'height', 'type' => 'number', 'required' => false, 'sort_order' => 10],
            ], ['height' => 24], 1005),
            'mail_form_answers' => $this->mailBlock('Mail form answers', 'Generated table with submitted form answers.', [], [], [], 1006),
            'mail_footer_text' => $this->mailBlock('Mail footer text', 'Small footer paragraph.', ['text'], [
                ['name' => 'text', 'type' => 'textarea', 'required' => false, 'sort_order' => 10],
            ], ['text' => ''], 1007),
        ];
    }

    /**
     * @param  list<string>  $fields
     * @param  list<array<string, mixed>>  $editorFields
     * @param  array<string, mixed>  $defaults
     * @return array{name:string,description:string,fields:list<string>,editor_fields:list<array<string, mixed>>,defaults:array<string, mixed>,sort_order:int}
     */
    private function mailBlock(string $name, string $description, array $fields, array $editorFields, array $defaults, int $sortOrder): array
    {
        return [
            'name' => $name,
            'description' => $description,
            'fields' => $fields,
            'editor_fields' => $editorFields,
            'defaults' => $defaults,
            'sort_order' => $sortOrder,
        ];
    }

    /**
     * @param  array<string, array{block:CmsPlaceableBlock,revision:CmsPlaceableBlockRevision}>  $blocks
     * @return array{count:int,items:array<string, CmsTemplate>}
     */
    private function ensureSystemTemplates(array $blocks): array
    {
        $templates = [];
        $synced = 0;

        foreach ($this->accountPageLocales() as $locale) {
            foreach ($this->systemTemplateDefinitions() as $pageSlug => $definition) {
                $template = $this->ensureSystemTemplate($pageSlug, $locale, $definition, $blocks);

                if ($template instanceof CmsTemplate) {
                    $templates[$this->templateMapKey($locale, $pageSlug)] = $template;
                    $synced++;
                }
            }
        }

        return ['count' => $synced, 'items' => $templates];
    }

    /**
     * @param  array{template_key:string,name_key:string,blocks:list<string>}  $definition
     * @param  array<string, array{block:CmsPlaceableBlock,revision:CmsPlaceableBlockRevision}>  $blocks
     */
    private function ensureSystemTemplate(string $pageSlug, string $locale, array $definition, array $blocks): ?CmsTemplate
    {
        $layoutId = $this->defaultLayoutId($locale);

        if ($layoutId === null) {
            return null;
        }

        $template = CmsTemplate::query()
            ->where('template_key', $definition['template_key'])
            ->where('locale', $locale)
            ->first();
        $isNew = ! $template instanceof CmsTemplate;

        if ($isNew) {
            $template = new CmsTemplate;
            $template->fill([
                'import_key' => $this->systemTemplateImportKey($locale, $pageSlug),
                'name' => (string) __($definition['name_key'], [], $locale),
                'locale' => $locale,
                'translation_key' => 'pat.'.$pageSlug,
                'layout_id' => $layoutId,
                'template_class' => 'system',
                'template_key' => $definition['template_key'],
                'is_default' => false,
                'is_active' => true,
                'cache_strategy' => 'inherit',
                'settings' => [],
                'data_contract' => [],
            ]);
        }

        $settings = array_replace_recursive((array) ($template->settings ?? []), [
            'system' => [
                'module' => 'public-account',
                'locked_key' => true,
                'page_slug' => $pageSlug,
            ],
        ]);

        $template->forceFill([
            'import_key' => $template->import_key ?: $this->systemTemplateImportKey($locale, $pageSlug),
            'locale' => $locale,
            'template_class' => 'system',
            'template_key' => $definition['template_key'],
            'translation_key' => $template->translation_key ?: 'pat.'.$pageSlug,
            'layout_id' => $template->layout_id ?: $layoutId,
            'is_active' => true,
            'settings' => $settings,
        ])->save();

        $section = CmsSection::query()->firstOrCreate(
            [
                'owner_type' => CmsTemplate::class,
                'owner_id' => $template->id,
                'zone' => 'content',
                'import_key' => $this->systemTemplateImportKey($locale, $pageSlug).':content',
            ],
            [
                'name' => (string) __($definition['name_key'], [], $locale),
                'sort_order' => 0,
                'is_active' => true,
                'settings' => [],
            ],
        );

        foreach ($definition['blocks'] as $index => $blockKey) {
            $this->ensurePlacementWithPrefix(
                $section,
                $blockKey,
                $blocks,
                $this->systemTemplateImportKey($locale, $pageSlug),
                $index * 10,
            );
        }

        return $template;
    }

    private function defaultLayoutId(string $locale): ?int
    {
        $layoutId = CmsLayout::query()
            ->active()
            ->defaultForLocale($locale)
            ->value('id');

        if ($layoutId !== null) {
            return (int) $layoutId;
        }

        $fallbackLayoutId = CmsLayout::query()
            ->active()
            ->where('locale', $locale)
            ->orderBy('name')
            ->value('id');

        return $fallbackLayoutId !== null ? (int) $fallbackLayoutId : null;
    }

    /**
     * @return array<string, array{template_key:string,name_key:string,blocks:list<string>}>
     */
    private function systemTemplateDefinitions(): array
    {
        return [
            'account' => [
                'template_key' => 'system.account.auth',
                'name_key' => 'public_account.system_templates.auth',
                'blocks' => ['site_user_auth_panel'],
            ],
            'login' => [
                'template_key' => 'system.account.login',
                'name_key' => 'public_account.system_templates.login',
                'blocks' => ['site_user_auth_panel'],
            ],
            'register' => [
                'template_key' => 'system.account.register',
                'name_key' => 'public_account.system_templates.register',
                'blocks' => ['site_user_auth_panel'],
            ],
            'forgot-password' => [
                'template_key' => 'system.account.forgot_password',
                'name_key' => 'public_account.system_templates.forgot_password',
                'blocks' => ['site_user_forgot_password_form'],
            ],
            'reset-password' => [
                'template_key' => 'system.account.reset_password',
                'name_key' => 'public_account.system_templates.reset_password',
                'blocks' => ['site_user_reset_password_form'],
            ],
            'dashboard' => [
                'template_key' => 'system.account.dashboard',
                'name_key' => 'public_account.system_templates.dashboard',
                'blocks' => ['site_user_account_controls', 'site_user_dashboard'],
            ],
            'profile' => [
                'template_key' => 'system.account.profile',
                'name_key' => 'public_account.system_templates.profile',
                'blocks' => ['site_user_account_controls', 'site_user_profile_form'],
            ],
            'security' => [
                'template_key' => 'system.account.security',
                'name_key' => 'public_account.system_templates.security',
                'blocks' => ['site_user_account_controls', 'site_user_security_settings'],
            ],
            'two-factor-challenge' => [
                'template_key' => 'system.account.two_factor_challenge',
                'name_key' => 'public_account.system_templates.two_factor_challenge',
                'blocks' => ['site_user_two_factor_challenge'],
            ],
        ];
    }

    private function systemTemplateImportKey(string $locale, string $pageSlug): string
    {
        return 'public-account:template:'.$locale.':'.$pageSlug;
    }

    private function templateMapKey(string $locale, string $pageSlug): string
    {
        return $locale.':'.$pageSlug;
    }

    /**
     * @param  array<string, CmsTemplate>  $templates
     */
    private function ensurePages(array $templates): int
    {
        $created = 0;

        foreach ($this->accountPageLocales() as $locale) {
            $accountPage = $this->ensurePage(null, $this->accountPageTitle('account', $locale), 'account', $locale, $templates[$this->templateMapKey($locale, 'account')] ?? null, 0, $created);

            $this->ensurePage($accountPage, $this->accountPageTitle('login', $locale), 'login', $locale, $templates[$this->templateMapKey($locale, 'login')] ?? null, 10, $created);
            $this->ensurePage($accountPage, $this->accountPageTitle('register', $locale), 'register', $locale, $templates[$this->templateMapKey($locale, 'register')] ?? null, 20, $created);
            $this->ensurePage($accountPage, $this->accountPageTitle('forgot-password', $locale), 'forgot-password', $locale, $templates[$this->templateMapKey($locale, 'forgot-password')] ?? null, 30, $created);
            $this->ensurePage($accountPage, $this->accountPageTitle('reset-password', $locale), 'reset-password', $locale, $templates[$this->templateMapKey($locale, 'reset-password')] ?? null, 40, $created);
            $this->ensurePage($accountPage, $this->accountPageTitle('dashboard', $locale), 'dashboard', $locale, $templates[$this->templateMapKey($locale, 'dashboard')] ?? null, 50, $created);
            $this->ensurePage($accountPage, $this->accountPageTitle('profile', $locale), 'profile', $locale, $templates[$this->templateMapKey($locale, 'profile')] ?? null, 60, $created);
            $this->ensurePage($accountPage, $this->accountPageTitle('security', $locale), 'security', $locale, $templates[$this->templateMapKey($locale, 'security')] ?? null, 70, $created);
            $this->ensurePage($accountPage, $this->accountPageTitle('two-factor-challenge', $locale), 'two-factor-challenge', $locale, $templates[$this->templateMapKey($locale, 'two-factor-challenge')] ?? null, 80, $created);
        }

        return $created;
    }

    /**
     * @return list<string>
     */
    private function accountPageLocales(): array
    {
        $locales = $this->languageSettings->multilingualEnabled()
            ? $this->languageSettings->activeLocales()
            : [$this->languageSettings->defaultLocale()];

        return collect($locales)
            ->push($this->languageSettings->defaultLocale())
            ->map(fn (mixed $locale): string => trim((string) $locale))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function accountPageTitle(string $slug, string $locale): string
    {
        $titles = [
            'nl' => [
                'account' => 'Account',
                'login' => 'Inloggen',
                'register' => 'Account aanmaken',
                'forgot-password' => 'Wachtwoord vergeten',
                'reset-password' => 'Wachtwoord opnieuw instellen',
                'dashboard' => 'Dashboard',
                'profile' => 'Profiel',
                'security' => 'Beveiliging',
                'two-factor-challenge' => 'Tweestapsauthenticatie',
            ],
        ];

        return $titles[$locale][$slug] ?? [
            'account' => 'Account',
            'login' => 'Sign in',
            'register' => 'Create account',
            'forgot-password' => 'Forgot password',
            'reset-password' => 'Reset password',
            'dashboard' => 'Dashboard',
            'profile' => 'Profile',
            'security' => 'Security',
            'two-factor-challenge' => 'Two-factor authentication',
        ][$slug];
    }

    private function ensurePage(?CmsPage $parent, string $title, string $slug, string $locale, ?CmsTemplate $template, int $sortOrder, int &$created): CmsPage
    {
        $page = CmsPage::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->where('parent_id', $parent?->id)
            ->first();

        if (! $page instanceof CmsPage) {
            $page = new CmsPage;
            $created++;
        }

        $page->fill([
            'parent_id' => $parent?->id,
            'detail_template_id' => $template?->id,
            'author_id' => null,
            'title' => $title,
            'slug' => $slug,
            'locale' => $locale,
            'translation_key' => 'pa.'.$slug,
            'status' => 'published',
            'short_description' => null,
            'content_blocks' => [],
            'seo_title' => $title,
            'seo_description' => null,
            'canonical_url' => null,
            'og_image_path' => null,
            'noindex' => true,
            'is_home' => false,
            'is_searchable' => false,
            'sort_order' => $sortOrder,
            'published_at' => now(),
            'settings' => array_filter([
                'system_page_key' => 'public-account.'.$slug,
            ]),
        ]);
        $page->save();

        return $page;
    }

    /**
     * @param  array<string, array{block:CmsPlaceableBlock,revision:CmsPlaceableBlockRevision}>  $blocks
     */
    private function ensurePlacement(CmsSection $section, string $blockKey, array $blocks, string $pageSlug, int $sortOrder): void
    {
        $this->ensurePlacementWithPrefix($section, $blockKey, $blocks, 'public-account:'.$pageSlug, $sortOrder);
    }

    /**
     * @param  array<string, array{block:CmsPlaceableBlock,revision:CmsPlaceableBlockRevision}>  $blocks
     */
    private function ensurePlacementWithPrefix(CmsSection $section, string $blockKey, array $blocks, string $importPrefix, int $sortOrder): void
    {
        $placeableBlock = $blocks[$blockKey]['block'];
        $revision = $blocks[$blockKey]['revision'];
        $block = CmsBlock::query()->firstOrCreate(
            ['import_key' => $importPrefix.':'.$blockKey],
            [
                'cms_placeable_block_id' => $placeableBlock->id,
                'placeable_block_revision_id' => $revision->id,
                'type' => $blockKey,
                'name' => $this->blockName($blockKey),
                'content' => [],
                'settings' => [],
                'is_shared' => false,
                'is_dynamic' => false,
                'cache_strategy' => 'none',
                'created_by' => null,
            ],
        );

        $block->forceFill([
            'cms_placeable_block_id' => $placeableBlock->id,
            'placeable_block_revision_id' => $revision->id,
            'type' => $blockKey,
        ])->save();

        CmsBlockPlacement::query()->updateOrCreate(
            [
                'cms_section_id' => $section->id,
                'cms_block_id' => $block->id,
                'import_key' => $importPrefix.':'.$blockKey.':placement',
            ],
            [
                'sort_order' => $sortOrder,
                'is_active' => true,
                'visible_mobile' => true,
                'visible_tablet' => true,
                'visible_desktop' => true,
                'mobile_span' => 12,
                'tablet_span' => 12,
                'desktop_span' => 12,
                'height_mode' => 'auto',
                'height_value' => null,
                'cache_strategy' => 'none',
                'settings' => [],
            ],
        );
    }

    /**
     * @param  array{block:CmsPlaceableBlock,revision:CmsPlaceableBlockRevision}  $accountControls
     */
    private function migrateLegacyAccountControlBlocks(array $accountControls): void
    {
        CmsBlock::query()
            ->whereIn('type', $this->legacyAccountControlBlockTypes())
            ->update([
                'cms_placeable_block_id' => $accountControls['block']->id,
                'placeable_block_revision_id' => $accountControls['revision']->id,
                'type' => 'site_user_account_controls',
                'name' => $this->blockName('site_user_account_controls'),
                'updated_at' => now(),
            ]);
    }

    /**
     * @param  array{block:CmsPlaceableBlock,revision:CmsPlaceableBlockRevision}  $authPanel
     */
    private function migrateStandardAuthPanelBlocks(array $authPanel): void
    {
        foreach ($this->standardAuthPanelReplacements() as $pageSlug => $oldBlockKey) {
            $oldImportKey = 'public-account:'.$pageSlug.':'.$oldBlockKey;
            $newImportKey = 'public-account:'.$pageSlug.':site_user_auth_panel';
            $oldBlock = CmsBlock::query()->where('import_key', $oldImportKey)->first();
            $newBlock = CmsBlock::query()->where('import_key', $newImportKey)->first();

            if ($oldBlock instanceof CmsBlock && ! ($newBlock instanceof CmsBlock)) {
                $oldBlock->forceFill([
                    'import_key' => $newImportKey,
                    'cms_placeable_block_id' => $authPanel['block']->id,
                    'placeable_block_revision_id' => $authPanel['revision']->id,
                    'type' => 'site_user_auth_panel',
                    'name' => $this->blockName('site_user_auth_panel'),
                    'updated_at' => now(),
                ])->save();

                CmsBlockPlacement::query()
                    ->where('cms_block_id', $oldBlock->id)
                    ->where('import_key', $oldImportKey.':placement')
                    ->update([
                        'import_key' => $newImportKey.':placement',
                        'updated_at' => now(),
                    ]);

                continue;
            }

            if ($oldBlock instanceof CmsBlock && $newBlock instanceof CmsBlock) {
                CmsBlockPlacement::query()
                    ->where('cms_block_id', $oldBlock->id)
                    ->where('import_key', $oldImportKey.':placement')
                    ->update([
                        'is_active' => false,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function standardAuthPanelReplacements(): array
    {
        return [
            'account' => 'site_user_account_controls',
            'login' => 'site_user_login_form',
            'register' => 'site_user_register_form',
        ];
    }

    private function removeLegacyAccountControlBlocks(): void
    {
        CmsPlaceableBlock::query()
            ->whereIn('key', $this->legacyAccountControlBlockTypes())
            ->delete();
    }

    /**
     * @return list<string>
     */
    private function legacyAccountControlBlockTypes(): array
    {
        return [
            'site_user_account_link',
            'site_user_logout_button',
            'site_user_account_nav',
        ];
    }

    private function blockName(string $key): string
    {
        return str($key)->replace('_', ' ')->title()->toString();
    }
}
