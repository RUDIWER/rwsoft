<?php

namespace Tests\Feature\Cms;

use App\Actions\Admin\Cms\Health\BuildCmsHealthReportAction;
use App\Actions\Admin\Cms\InstallPublicAccountModuleAction;
use App\Actions\Admin\Cms\RenderCmsEmailAction;
use App\Actions\PublicSite\StoreSiteUserProfileFieldValuesAction;
use App\Http\Middleware\AuthAdminUsers;
use App\Http\Middleware\AuthorizeAdminRoute;
use App\Http\Middleware\EnsureSiteMembership;
use App\Http\Middleware\EnsureTwoFactorIsEnabled;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveTenantSite;
use App\Models\Cms\CmsBlockPlacement;
use App\Models\Cms\CmsEmail;
use App\Models\Cms\CmsForm;
use App\Models\Cms\CmsMailTemplate;
use App\Models\Cms\CmsMediaAsset;
use App\Models\Cms\CmsModule;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPlaceableBlock;
use App\Models\Cms\CmsSection;
use App\Models\Cms\CmsSetting;
use App\Models\Cms\CmsTemplate;
use App\Models\PublicSite\SiteUser;
use App\Models\PublicSite\SiteUserProfileFieldDefinition;
use App\Models\User;
use App\Support\PublicSite\CmsTemplateResolver;
use App\Support\PublicSite\PublicAccountSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class CmsSettingsBackofficeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'tenant',
            'database.connections.mysql.database' => 'rwsoft',
            'database.connections.central.driver' => 'mysql',
            'database.connections.central.host' => config('database.connections.mysql.host'),
            'database.connections.central.port' => config('database.connections.mysql.port'),
            'database.connections.central.database' => 'rwsoft',
            'database.connections.central.username' => config('database.connections.mysql.username'),
            'database.connections.central.password' => config('database.connections.mysql.password'),
            'database.connections.tenant.driver' => 'mysql',
            'database.connections.tenant.host' => config('database.connections.mysql.host'),
            'database.connections.tenant.port' => config('database.connections.mysql.port'),
            'database.connections.tenant.database' => 'rwsoft_site_rwsoft',
            'database.connections.tenant.username' => config('database.connections.mysql.username'),
            'database.connections.tenant.password' => config('database.connections.mysql.password'),
        ]);

        DB::purge('central');
        DB::purge('tenant');
        DB::reconnect('central');
        DB::reconnect('tenant');
        DB::setDefaultConnection('tenant');
        DB::connection('central')->beginTransaction();
        DB::connection('tenant')->beginTransaction();

        $this->withoutMiddleware([
            AuthAdminUsers::class,
            AuthorizeAdminRoute::class,
            EnsureSiteMembership::class,
            EnsureTwoFactorIsEnabled::class,
            ResolveTenantSite::class,
        ]);
    }

    protected function tearDown(): void
    {
        if (DB::connection('tenant')->transactionLevel() > 0) {
            DB::connection('tenant')->rollBack();
        }

        if (DB::connection('central')->transactionLevel() > 0) {
            DB::connection('central')->rollBack();
        }

        parent::tearDown();
    }

    public function test_settings_page_renders_inertia_page(): void
    {
        $user = $this->createAdminUser();
        $page = $this->createPage();

        $this
            ->actingAs($user)
            ->get(route('admin.cms.settings.edit'), $this->inertiaHeaders('/admin/cms/settings'))
            ->assertOk()
            ->assertJsonPath('component', 'Admin/Cms/Settings/Edit')
            ->assertJsonFragment([
                'id' => $page->id,
                'title' => 'Homepage',
            ]);
    }

    public function test_settings_page_includes_cms_module_status(): void
    {
        $user = $this->createAdminUser();

        CmsModule::query()->updateOrCreate(
            ['key' => 'public-account'],
            [
                'name' => 'Public Account',
                'status' => 'active',
                'settings' => [],
                'installed_at' => now(),
            ],
        );

        $this
            ->actingAs($user)
            ->get(route('admin.cms.settings.edit'), $this->inertiaHeaders('/admin/cms/settings'))
            ->assertOk()
            ->assertJsonPath('props.modules.public_account.installed', true)
            ->assertJsonPath('props.modules.public_account.status', 'active');
    }

    public function test_public_account_module_can_be_installed_from_settings(): void
    {
        $user = $this->createAdminUser();

        $this
            ->actingAs($user)
            ->post(route('admin.cms.settings.modules.install', ['module' => 'public-account']))
            ->assertRedirect(route('admin.cms.settings.edit', ['tab' => 'modules']))
            ->assertSessionHas('status', __('cms_admin_ui.flash.cms_module_installed'))
            ->assertSessionHas('flash_details.cms_module_install.result.pages')
            ->assertSessionHas('flash_details.cms_module_install.result.blocks', 10)
            ->assertSessionHas('flash_details.cms_module_install.result.forms', 21)
            ->assertSessionHas('flash_details.cms_module_install.result.profile_fields', 3)
            ->assertSessionHas('flash_details.cms_module_install.result.templates', 27)
            ->assertSessionHas('flash_details.cms_module_install.result.mail_templates', 3)
            ->assertSessionHas('flash_details.cms_module_install.result.emails', 9)
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(
            'active',
            CmsModule::query()->where('key', 'public-account')->value('status'),
        );
        $this->assertNotNull(
            CmsPlaceableBlock::query()->where('key', 'site_user_account_controls')->first(),
        );
        $this->assertNotNull(
            CmsPlaceableBlock::query()->where('key', 'site_user_auth_panel')->first(),
        );
        $this->assertSettingValue(PublicAccountSettings::GROUP, PublicAccountSettings::REGISTRATION_ENABLED, false, false);
        $this->assertSettingValue(PublicAccountSettings::GROUP, PublicAccountSettings::EMAIL_VERIFICATION_REQUIRED, false, false);
        $this->assertSettingValue(PublicAccountSettings::GROUP, PublicAccountSettings::TWO_FACTOR_MODE, 'disabled', false);
        $this->assertSame(21, CmsForm::query()->where('form_kind', 'system')->whereNotNull('system_key')->count());
        $this->assertSame(27, CmsTemplate::query()->where('template_class', 'system')->where('template_key', 'like', 'system.account.%')->count());
        $this->assertSame(3, CmsMailTemplate::query()->whereIn('key', ['auth_action', 'reset_password', 'form_notification'])->count());
        $this->assertSame(9, CmsEmail::query()->whereIn('system_key', ['site_user.verify_email', 'site_user.reset_password', 'cms_form.admin_notification'])->count());
        $this->assertSame(
            'reset_password',
            CmsEmail::query()
                ->where('system_key', 'site_user.reset_password')
                ->where('locale', 'nl')
                ->firstOrFail()
                ->mailTemplate
                ->key,
        );
        $this->assertNotNull(CmsPlaceableBlock::query()->where('key', 'mail_company_logo')->where('category', 'mail')->first());
        $this->assertRenderedSystemMailContainsCompanyLogo();
        $this->assertSame(3, SiteUserProfileFieldDefinition::query()->count());
        $this->assertNotNull(
            CmsForm::query()
                ->where('form_kind', 'system')
                ->where('system_key', 'site_user_register')
                ->where('locale', 'nl')
                ->first(),
        );

        $loginTemplate = CmsTemplate::query()
            ->where('template_key', 'system.account.login')
            ->where('locale', 'nl')
            ->firstOrFail();
        $accountPage = CmsPage::query()
            ->whereNull('parent_id')
            ->where('slug', 'account')
            ->where('locale', 'nl')
            ->firstOrFail();
        $loginPage = CmsPage::query()
            ->where('parent_id', $accountPage->id)
            ->where('slug', 'login')
            ->where('locale', 'nl')
            ->firstOrFail();

        $this->assertSame($loginTemplate->id, $loginPage->detail_template_id);
        $this->assertTrue(
            CmsSection::query()
                ->where('owner_type', CmsTemplate::class)
                ->where('owner_id', $loginTemplate->id)
                ->whereHas('placements.block', fn ($query) => $query->where('type', 'site_user_auth_panel'))
                ->exists(),
        );

        $this->assertTrue(
            app(CmsTemplateResolver::class)->resolve('page.detail', 'nl', $loginPage)?->is($loginTemplate),
        );

        $this
            ->actingAs($user)
            ->get(route('admin.cms.pages.edit', ['id' => $loginPage->id]), $this->inertiaHeaders('/admin/cms/pages/'.$loginPage->id.'/edit'))
            ->assertOk()
            ->assertJsonPath('component', 'Admin/Cms/Pages/Edit')
            ->assertJsonPath('props.pageItem.detail_template_id', $loginTemplate->id)
            ->assertJsonFragment([
                'id' => $loginTemplate->id,
                'name' => $loginTemplate->name,
                'locale' => 'nl',
            ]);
    }

    private function assertRenderedSystemMailContainsCompanyLogo(): void
    {
        $asset = CmsMediaAsset::query()->create([
            'disk' => 'public',
            'visibility' => 'public',
            'asset_kind' => 'library',
            'path' => 'cms/test/company-logo.png',
            'filename' => 'company-logo.png',
            'original_filename' => 'company-logo.png',
            'mime_type' => 'image/png',
            'extension' => 'png',
            'size_bytes' => 1024,
            'width' => 320,
            'height' => 120,
            'alt_text' => 'Company logo',
            'metadata' => [],
            'sort_order' => 0,
        ]);

        CmsSetting::query()->updateOrCreate(
            ['group' => 'branding', 'key' => 'company_logo_media_asset_id'],
            [
                'label' => 'Company logo for emails',
                'type' => 'number',
                'value' => ['value' => $asset->id],
                'is_public' => true,
                'sort_order' => 60,
            ],
        );

        $email = CmsEmail::query()
            ->where('system_key', 'site_user.verify_email')
            ->where('locale', 'nl')
            ->firstOrFail();

        $rendered = app(RenderCmsEmailAction::class)->handle($email, [
            'user' => ['name' => 'Jane Example', 'email' => 'jane@example.test'],
            'site' => ['name' => 'Example', 'url' => 'https://example.test'],
            'action' => ['url' => 'https://example.test/verify', 'expires_at' => '05/07/2026 12:00'],
        ]);

        $this->assertStringContainsString('company-logo.png', $rendered['html']);
        $this->assertStringContainsString('max-width:180px', $rendered['html']);
        $this->assertStringContainsString('https://example.test/verify', $rendered['html']);
    }

    public function test_cms_health_reports_and_repairs_missing_public_account_template_block(): void
    {
        $user = $this->createAdminUser();

        app(InstallPublicAccountModuleAction::class)->handle();

        $dashboardTemplate = CmsTemplate::query()
            ->where('template_key', 'system.account.dashboard')
            ->where('locale', 'nl')
            ->firstOrFail();
        $dashboardPlacement = CmsBlockPlacement::query()
            ->whereHas('section', fn ($query) => $query
                ->where('owner_type', CmsTemplate::class)
                ->where('owner_id', $dashboardTemplate->id))
            ->whereHas('block', fn ($query) => $query->where('type', 'site_user_dashboard'))
            ->firstOrFail();

        $dashboardPlacement->forceFill(['is_active' => false])->save();

        $report = app(BuildCmsHealthReportAction::class)->handle();
        $issue = collect($report['issues'])
            ->where('category', 'public_account')
            ->where('record_id', $dashboardTemplate->id)
            ->firstWhere('message', __('cms_admin_ui.health.issues.public_account_template_block_missing', ['block' => 'site_user_dashboard']));

        $this->assertNotNull($issue);
        $this->assertSame('error', $issue['severity']);
        $this->assertSame('public_account', $issue['module']);
        $this->assertSame('cms_template', $issue['record_type']);

        $this
            ->actingAs($user)
            ->post(route('admin.cms.health.public-account.repair'))
            ->assertRedirect(route('admin.cms.health.index'))
            ->assertSessionHas('status', __('cms_admin_ui.health.public_account.repaired'));

        $this->assertTrue($dashboardPlacement->refresh()->is_active);

        $repairedReport = app(BuildCmsHealthReportAction::class)->handle();

        $this->assertCount(
            0,
            collect($repairedReport['issues'])->where('category', 'public_account'),
        );
    }

    public function test_system_form_locked_fields_are_protected(): void
    {
        $user = $this->createAdminUser();
        $user->forceFill(['is_platform_admin' => true])->save();

        app(InstallPublicAccountModuleAction::class)->handle();

        $form = CmsForm::query()
            ->with('fields')
            ->where('form_kind', 'system')
            ->where('system_key', 'site_user_login')
            ->where('locale', 'nl')
            ->firstOrFail();

        $payload = $this->cmsFormPayload($form);
        $passwordIndex = collect($payload['fields'])->search(fn (array $field): bool => $field['translation_key'] === 'password');

        $this->assertIsInt($passwordIndex);

        $payload['fields'][$passwordIndex]['type'] = 'email';
        $payload['fields'][$passwordIndex]['is_active'] = false;

        $this
            ->actingAs($user)
            ->from(route('admin.cms.forms.edit', ['id' => $form->id]))
            ->post(route('admin.cms.forms.store', ['id' => $form->id]), $payload)
            ->assertRedirect(route('admin.cms.forms.edit', ['id' => $form->id]))
            ->assertSessionHasErrors([
                "fields.{$passwordIndex}.type",
                "fields.{$passwordIndex}.label",
            ]);

        $form->refresh()->load('fields');

        $passwordField = $form->fields->firstWhere('translation_key', 'password');

        $this->assertSame('text', $passwordField?->type);
        $this->assertTrue((bool) $passwordField?->is_active);
    }

    public function test_system_form_edit_page_renders(): void
    {
        $user = $this->createAdminUser();

        app(InstallPublicAccountModuleAction::class)->handle();

        $form = CmsForm::query()
            ->where('form_kind', 'system')
            ->where('system_key', 'site_user_login')
            ->where('locale', 'nl')
            ->firstOrFail();

        $this
            ->actingAs($user)
            ->get(route('admin.cms.forms.edit', ['id' => $form->id]), $this->inertiaHeaders('/admin/cms/forms/'.$form->id.'/edit'))
            ->assertOk()
            ->assertJsonPath('component', 'Admin/Cms/Forms/Edit')
            ->assertJsonPath('props.formItem.form_kind', 'system')
            ->assertJsonPath('props.formItem.system_key', 'site_user_login');
    }

    public function test_public_account_profile_fields_are_stored_relationally(): void
    {
        app(InstallPublicAccountModuleAction::class)->handle();

        $siteUser = SiteUser::query()->create([
            'name' => 'Profile Fields User',
            'email' => 'profile-fields-user@example.test',
            'password' => 'secret-password',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        app(StoreSiteUserProfileFieldValuesAction::class)->handle($siteUser, [
            'company_name' => 'RwSoft BV',
            'vat_number' => 'BE0123456789',
            'customer_type' => 'business',
        ], 'profile');

        $this->assertSame(
            'RwSoft BV',
            $siteUser->profileFieldValues()->where('profile_field_key', 'company_name')->value('value'),
        );
        $this->assertSame(
            'BE0123456789',
            $siteUser->profileFieldValues()->where('profile_field_key', 'vat_number')->value('value'),
        );
        $this->assertSame(
            'business',
            $siteUser->profileFieldValues()->where('profile_field_key', 'customer_type')->value('value'),
        );
    }

    public function test_public_account_register_system_form_renders_profile_fields(): void
    {
        app(InstallPublicAccountModuleAction::class)->handle();

        $html = view('public.system.partials.site-user-system-form-fields', [
            'systemKey' => 'site_user_register',
            'locale' => 'nl',
            'idPrefix' => 'test-register',
            'errors' => new ViewErrorBag,
        ])->render();

        $this->assertStringContainsString('name="profile_fields[company_name]"', $html);
        $this->assertStringContainsString('name="profile_fields[vat_number]"', $html);
        $this->assertStringContainsString('name="profile_fields[customer_type]"', $html);
    }

    public function test_public_account_admin_actions_update_site_user_status_and_two_factor(): void
    {
        $user = $this->createAdminUser();
        $siteUser = SiteUser::query()->create([
            'name' => 'Managed Site User',
            'email' => 'managed-site-user@example.test',
            'password' => 'secret-password',
            'status' => 'active',
            'email_verified_at' => now(),
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['one', 'two'], JSON_THROW_ON_ERROR)),
            'two_factor_confirmed_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->post(route('admin.cms.site-users.deactivate', ['siteUser' => $siteUser->id]))
            ->assertRedirect(route('admin.cms.site-users.index'))
            ->assertSessionHas('status', __('cms_admin_ui.public_account.feedback_account_deactivated'));

        $this->assertSame('inactive', $siteUser->fresh()?->status);

        $this
            ->actingAs($user)
            ->post(route('admin.cms.site-users.activate', ['siteUser' => $siteUser->id]))
            ->assertRedirect(route('admin.cms.site-users.index'))
            ->assertSessionHas('status', __('cms_admin_ui.public_account.feedback_account_activated'));

        $this->assertSame('active', $siteUser->fresh()?->status);

        $this
            ->actingAs($user)
            ->post(route('admin.cms.site-users.reset-two-factor', ['siteUser' => $siteUser->id]))
            ->assertRedirect(route('admin.cms.site-users.index'))
            ->assertSessionHas('status', __('cms_admin_ui.public_account.feedback_two_factor_reset'));

        $siteUser->refresh();

        $this->assertNull($siteUser->two_factor_secret);
        $this->assertNull($siteUser->two_factor_recovery_codes);
        $this->assertNull($siteUser->two_factor_confirmed_at);
    }

    public function test_settings_can_be_stored(): void
    {
        $user = $this->createAdminUser();
        $page = $this->createPage();

        $this
            ->actingAs($user)
            ->post(route('admin.cms.settings.store'), $this->settingsPayload([
                'site_name' => 'RwSoft CMS',
                'site_tagline' => 'Slimme software',
                'homepage_id' => $page->id,
                'seo_default_title' => 'RwSoft standaard titel',
                'seo_default_description' => 'RwSoft standaard omschrijving',
                'global_noindex' => true,
                'robots_extra_rules' => "# Tijdelijk\nDisallow: /campagne",
            ]))
            ->assertRedirect(route('admin.cms.settings.edit'))
            ->assertSessionHas('status')
            ->assertSessionDoesntHaveErrors();

        $this->assertSettingValue('general', 'site_name', 'RwSoft CMS');
        $this->assertSettingValue('general', 'site_tagline', 'Slimme software');
        $this->assertSettingValue('general', 'homepage_id', $page->id);
        $this->assertSettingValue('seo', 'default_title', 'RwSoft standaard titel');
        $this->assertSettingValue('seo', 'default_description', 'RwSoft standaard omschrijving');
        $this->assertSettingValue('seo', 'global_noindex', true);
        $this->assertSettingValue('seo', 'robots_extra_rules', "# Tijdelijk\nDisallow: /campagne");
    }

    public function test_settings_validation_rejects_invalid_robots_rules(): void
    {
        $user = $this->createAdminUser();

        $this
            ->actingAs($user)
            ->from(route('admin.cms.settings.edit'))
            ->post(route('admin.cms.settings.store'), $this->settingsPayload([
                'robots_extra_rules' => '<script>alert(1)</script>',
            ]))
            ->assertRedirect(route('admin.cms.settings.edit'))
            ->assertSessionHasErrors(['robots_extra_rules']);

        $this
            ->actingAs($user)
            ->from(route('admin.cms.settings.edit'))
            ->post(route('admin.cms.settings.store'), $this->settingsPayload([
                'robots_extra_rules' => 'BadDirective: /campagne',
            ]))
            ->assertRedirect(route('admin.cms.settings.edit'))
            ->assertSessionHasErrors(['robots_extra_rules']);
    }

    public function test_settings_validation_rejects_missing_required_values(): void
    {
        $user = $this->createAdminUser();

        $this
            ->actingAs($user)
            ->from(route('admin.cms.settings.edit'))
            ->post(route('admin.cms.settings.store'), $this->settingsPayload([
                'site_name' => '',
                'default_locale' => '',
                'homepage_id' => 999999,
            ]))
            ->assertRedirect(route('admin.cms.settings.edit'))
            ->assertSessionHasErrors(['site_name', 'default_locale', 'homepage_id']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPage(array $overrides = []): CmsPage
    {
        return CmsPage::query()->create(array_merge([
            'title' => 'Homepage',
            'slug' => 'homepage-'.uniqid(),
            'locale' => 'nl',
            'status' => 'published',
            'content_blocks' => [],
            'noindex' => false,
            'is_home' => true,
            'is_searchable' => true,
            'sort_order' => 0,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function settingsPayload(array $overrides = []): array
    {
        return array_merge([
            'site_name' => 'RwSoft',
            'site_tagline' => null,
            'default_locale' => 'nl',
            'homepage_id' => null,
            'public_text_cache_enabled' => true,
            'public_text_cache_ttl' => 3600,
            'media_max_image_upload_mb' => 20,
            'seo_default_title' => null,
            'seo_default_description' => null,
            'seo_h1_min_length' => 20,
            'seo_h1_max_length' => 70,
            'seo_h2_max_length' => 90,
            'seo_h3_max_length' => 100,
            'seo_meta_title_min_length' => 30,
            'seo_meta_title_max_length' => 60,
            'seo_meta_description_min_length' => 120,
            'seo_meta_description_max_length' => 160,
            'seo_slug_min_length' => 3,
            'seo_slug_max_length' => 80,
            'seo_url_max_length' => 2000,
            'seo_content_min_words' => 80,
            'seo_require_meta_title_on_publish' => true,
            'seo_require_meta_description_on_publish' => true,
            'seo_require_single_h1' => true,
            'seo_require_valid_heading_hierarchy' => true,
            'seo_require_json_ld' => false,
            'seo_require_og_image_for_posts' => false,
            'global_noindex' => false,
            'robots_extra_rules' => null,
            'logo_show_tagline' => false,
            'setting_translations' => [
                'nl' => [
                    'site_name' => 'RwSoft',
                    'site_tagline' => null,
                    'seo_default_title' => null,
                    'seo_default_description' => null,
                ],
            ],
            'translation_ai' => [
                'provider' => 'gemini',
                'model' => 'gemini-2.5-flash',
                'api_key' => null,
                'clear_api_key' => false,
                'fill_limit_default' => 100,
                'fill_limit_max' => 500,
            ],
            'admin_settings' => [
                'admin_default_locale' => 'nl',
            ],
        ], $overrides);
    }

    private function assertSettingValue(string $group, string $key, mixed $expected, bool $isPublic = true): void
    {
        $setting = CmsSetting::query()
            ->where('group', $group)
            ->where('key', $key)
            ->first();

        $this->assertNotNull($setting);
        $this->assertSame($expected, $setting?->value['value'] ?? null);
        $this->assertSame($isPublic, (bool) $setting?->is_public);
    }

    /**
     * @return array<string, mixed>
     */
    private function cmsFormPayload(CmsForm $form): array
    {
        return [
            'title' => $form->title,
            'locale' => $form->locale,
            'description' => $form->description,
            'notification_email' => $form->notification_email,
            'submit_button_label' => $form->submit_button_label,
            'success_message' => $form->success_message,
            'is_active' => (bool) $form->is_active,
            'fields' => $form->fields->map(fn ($field): array => [
                'id' => $field->id,
                'type' => $field->type,
                'translation_key' => $field->translation_key,
                'translated_from_form_field_id' => $field->translated_from_form_field_id,
                'label' => $field->label,
                'placeholder' => $field->placeholder,
                'help_text' => $field->help_text,
                'options' => $field->options ?? [],
                'sort_order' => $field->sort_order,
                'is_required' => (bool) $field->is_required,
                'is_active' => (bool) $field->is_active,
                'width' => $field->width ?: 'full',
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function inertiaHeaders(string $path): array
    {
        $request = Request::create($path, 'GET');
        $version = app(HandleInertiaRequests::class)->version($request);

        return [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Inertia-Version' => (string) ($version ?? ''),
        ];
    }

    private function createAdminUser(): User
    {
        $user = User::factory()->create([
            'two_factor_secret' => encrypt('cms-settings-test-secret'),
            'two_factor_confirmed_at' => now(),
        ]);

        $central = DB::connection('central');
        $roleId = $central->table('acl_roles')->where('key', 'super_admin')->value('id');

        if (! $roleId) {
            $roleId = $central->table('acl_roles')->insertGetId([
                'key' => 'super_admin',
                'name' => 'Super administrator',
                'description' => 'Test super admin role',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $central->table('acl_role_user')->updateOrInsert(
            [
                'user_id' => $user->id,
                'acl_role_id' => $roleId,
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return $user;
    }
}
