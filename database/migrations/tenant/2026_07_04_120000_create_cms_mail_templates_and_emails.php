<?php

use App\Support\Cms\CmsSystemMailRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cms_mail_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('key', 96)->unique();
            $table->text('description')->nullable();
            $table->string('context_key', 96)->index();
            $table->json('body_blocks')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('cms_emails', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_mail_template_id')->constrained('cms_mail_templates')->restrictOnDelete();
            $table->string('title');
            $table->string('locale', 12)->default(config('app.locale', 'en'))->index();
            $table->string('translation_key', 32)->index();
            $table->string('email_type', 24)->default('custom')->index();
            $table->string('system_key', 96)->nullable()->index();
            $table->string('context_key', 96)->index();
            $table->string('subject');
            $table->string('preheader')->nullable();
            $table->json('content_blocks')->nullable();
            $table->text('plain_text')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['system_key', 'locale'], 'cms_emails_system_locale_unique');
            $table->unique(['translation_key', 'locale'], 'cms_emails_translation_locale_unique');
        });

        Schema::create('cms_email_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_email_id')->nullable()->constrained('cms_emails')->nullOnDelete();
            $table->string('context_type', 96)->nullable()->index();
            $table->unsignedBigInteger('context_id')->nullable()->index();
            $table->string('recipient_email');
            $table->string('recipient_name')->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->string('subject_snapshot')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamps();

            $table->index(['context_type', 'context_id']);
        });

        $this->seedDefaultSystemMailTemplates();
        $this->seedAclPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_email_deliveries');
        Schema::dropIfExists('cms_emails');
        Schema::dropIfExists('cms_mail_templates');

        if (Schema::hasTable('acl_permissions')) {
            $permissionIds = DB::table('acl_permissions')
                ->whereIn('route_name', $this->permissionRoutes())
                ->pluck('id');

            if ($permissionIds->isNotEmpty() && Schema::hasTable('acl_permission_role')) {
                DB::table('acl_permission_role')->whereIn('acl_permission_id', $permissionIds->all())->delete();
            }

            DB::table('acl_permissions')->whereIn('route_name', $this->permissionRoutes())->delete();
        }
    }

    private function seedDefaultSystemMailTemplates(): void
    {
        $registry = app(CmsSystemMailRegistry::class);
        $now = now();

        foreach ($registry->templates() as $key => $template) {
            DB::table('cms_mail_templates')->updateOrInsert(
                ['key' => $key],
                [
                    'name' => $template['name'],
                    'description' => $template['description'] ?? null,
                    'context_key' => $template['context_key'],
                    'body_blocks' => json_encode($template['body_blocks'] ?? [], JSON_THROW_ON_ERROR),
                    'settings' => json_encode(['system_seeded' => true], JSON_THROW_ON_ERROR),
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $locales = $this->activeLocales();

        foreach ($registry->all() as $systemKey => $mail) {
            $templateId = DB::table('cms_mail_templates')->where('key', $mail['template_key'])->value('id');

            if (! $templateId) {
                continue;
            }

            foreach ($locales as $locale) {
                $translationKey = (string) Str::ulid();
                $defaults = $mail['defaults'];

                DB::table('cms_emails')->updateOrInsert(
                    ['system_key' => $systemKey, 'locale' => $locale],
                    [
                        'cms_mail_template_id' => $templateId,
                        'title' => $mail['label'],
                        'translation_key' => $translationKey,
                        'email_type' => 'system',
                        'context_key' => $mail['context_key'],
                        'subject' => $defaults['subject'],
                        'preheader' => $defaults['preheader'] ?? null,
                        'content_blocks' => json_encode($defaults['content_blocks'] ?? [], JSON_THROW_ON_ERROR),
                        'plain_text' => null,
                        'settings' => json_encode(['system_seeded' => true], JSON_THROW_ON_ERROR),
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function activeLocales(): array
    {
        if (Schema::hasTable('cms_languages')) {
            $locales = DB::table('cms_languages')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->pluck('locale')
                ->filter()
                ->map(fn (mixed $locale): string => (string) $locale)
                ->unique()
                ->values()
                ->all();

            if ($locales !== []) {
                return $locales;
            }
        }

        return collect([(string) config('app.locale', 'en'), 'en', 'nl'])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function seedAclPermissions(): void
    {
        if (! Schema::hasTable('acl_permissions')) {
            return;
        }

        $now = now();

        foreach ($this->permissionData($now) as $routeName => $permissionData) {
            DB::table('acl_permissions')->updateOrInsert(['route_name' => $routeName], $permissionData);
        }

        if (! Schema::hasTable('acl_roles') || ! Schema::hasTable('acl_permission_role')) {
            return;
        }

        $adminRoleId = DB::table('acl_roles')->where('key', 'admin')->value('id');

        if (! $adminRoleId) {
            return;
        }

        $permissionIds = DB::table('acl_permissions')->whereIn('route_name', $this->permissionRoutes())->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('acl_permission_role')->updateOrInsert(
                ['acl_role_id' => $adminRoleId, 'acl_permission_id' => $permissionId],
                ['active' => true, 'created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function permissionData(mixed $now): array
    {
        $definitions = [
            'admin.cms.mail-templates.index' => ['[CMS] Mail templates overzicht', 'Overzicht', true, 'admin/cms/mail-templates'],
            'admin.cms.mail-templates.create' => ['[CMS] Mail template toevoegen', 'Toevoegen', false, 'admin/cms/mail-templates/create'],
            'admin.cms.mail-templates.edit' => ['[CMS] Mail template bewerken', 'Bewerken', false, 'admin/cms/mail-templates/{id}/edit'],
            'admin.cms.mail-templates.store' => ['[CMS] Mail template bewaren', 'Bewaren', false, 'admin/cms/mail-templates/{id}/store'],
            'admin.cms.emails.create' => ['[CMS] E-mail toevoegen', 'Toevoegen', false, 'admin/cms/emails/create'],
            'admin.cms.emails.edit' => ['[CMS] E-mail bewerken', 'Bewerken', false, 'admin/cms/emails/{id}/edit'],
            'admin.cms.emails.store' => ['[CMS] E-mail bewaren', 'Bewaren', false, 'admin/cms/emails/{id}/store'],
            'admin.cms.emails.preview' => ['[CMS] E-mail preview', 'Preview', false, 'admin/cms/emails/{id}/preview'],
        ];

        return collect($definitions)->mapWithKeys(function (array $definition, string $routeName) use ($now): array {
            $data = [
                'description' => $definition[0],
                'menu' => (bool) $definition[2],
                'url' => $definition[3],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (Schema::hasColumn('acl_permissions', 'module_id')) {
                $data['module_id'] = $this->lookupId('acl_permission_modules', 'cms', 'CMS');
                $data['action_id'] = $this->lookupId('acl_permission_actions', Str::slug((string) $definition[1], '_'), (string) $definition[1]);
                $data['type_id'] = $this->lookupId('acl_permission_types', 'core', 'Core');
            } else {
                $data['module'] = 'CMS';
                $data['action'] = $definition[1];
                $data['type'] = 'core';
            }

            return [$routeName => $data];
        })->all();
    }

    /**
     * @return array<int, string>
     */
    private function permissionRoutes(): array
    {
        return [
            'admin.cms.mail-templates.index',
            'admin.cms.mail-templates.create',
            'admin.cms.mail-templates.edit',
            'admin.cms.mail-templates.store',
            'admin.cms.emails.create',
            'admin.cms.emails.edit',
            'admin.cms.emails.store',
            'admin.cms.emails.preview',
        ];
    }

    private function lookupId(string $table, string $key, string $name): ?int
    {
        if (! Schema::hasTable($table)) {
            return null;
        }

        DB::table($table)->updateOrInsert(
            ['key' => $key],
            [
                'name' => $name,
                'active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        return DB::table($table)->where('key', $key)->value('id');
    }
};
