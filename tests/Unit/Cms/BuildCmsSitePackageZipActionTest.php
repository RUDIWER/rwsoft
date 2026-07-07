<?php

namespace Tests\Unit\Cms;

use App\Actions\Admin\Cms\SitePackages\ActivateCmsSitePackageAction;
use App\Actions\Admin\Cms\SitePackages\BuildCmsSitePackageZipAction;
use App\Actions\Admin\Cms\SitePackages\ImportCmsSitePackageZipAction;
use App\Actions\Admin\Cms\SitePackages\PreviewCmsSitePackageZipAction;
use App\Actions\Admin\Cms\Starters\BuildExampleCmsStarterZipAction;
use App\Actions\Admin\Cms\Starters\ImportCmsStarterZipAction;
use App\Models\Cms\CmsBlock;
use App\Models\Cms\CmsBlockPlacement;
use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsForm;
use App\Models\Cms\CmsFormField;
use App\Models\Cms\CmsFormSubmission;
use App\Models\Cms\CmsFormSubmissionValue;
use App\Models\Cms\CmsLanguage;
use App\Models\Cms\CmsLayout;
use App\Models\Cms\CmsMediaAsset;
use App\Models\Cms\CmsMenu;
use App\Models\Cms\CmsMenuItem;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPost;
use App\Models\Cms\CmsPublicText;
use App\Models\Cms\CmsRedirect;
use App\Models\Cms\CmsSection;
use App\Models\Cms\CmsSetting;
use App\Models\Cms\CmsTag;
use App\Models\Cms\CmsTemplate;
use App\Models\Cms\CmsTheme;
use App\Models\Cms\CmsThemeVersion;
use App\Models\Platform\Site;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use ZipArchive;

class BuildCmsSitePackageZipActionTest extends TestCase
{
    /**
     * @var array<int, string>
     */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'tenant',
            'database.connections.mysql.database' => 'rwsoft',
            'database.connections.tenant.driver' => 'mysql',
            'database.connections.tenant.host' => config('database.connections.mysql.host'),
            'database.connections.tenant.port' => config('database.connections.mysql.port'),
            'database.connections.tenant.database' => 'rwsoft_site_rwsoft',
            'database.connections.tenant.username' => config('database.connections.mysql.username'),
            'database.connections.tenant.password' => config('database.connections.mysql.password'),
        ]);

        DB::purge('tenant');
        DB::reconnect('tenant');
        DB::setDefaultConnection('tenant');
        DB::connection('tenant')->beginTransaction();
        $this->clearCmsTestData();
        Storage::fake('public');
        Storage::fake('local');
        TenantContext::setSite((new Site)->forceFill([
            'id' => 1,
            'name' => 'Test site',
            'slug' => 'test-site',
            'tenant_database' => 'rwsoft_site_rwsoft',
            'status' => 'active',
        ]));
    }

    private function clearCmsTestData(): void
    {
        $tables = [
            'cms_block_exclusions',
            'cms_block_overrides',
            'cms_block_placement_style_revisions',
            'cms_block_placements',
            'cms_blocks',
            'cms_categories',
            'cms_color_palette_items',
            'cms_doc_pages',
            'cms_doc_versions',
            'cms_doc_collections',
            'cms_docs_tables',
            'cms_email_deliveries',
            'cms_emails',
            'cms_form_submission_values',
            'cms_form_submissions',
            'cms_form_fields',
            'cms_forms',
            'cms_languages',
            'cms_layouts',
            'cms_mail_templates',
            'cms_media_asset_translations',
            'cms_media_assets',
            'cms_media_folders',
            'cms_menu_items',
            'cms_menu_translations',
            'cms_menus',
            'cms_modules',
            'cms_pages',
            'cms_post_category',
            'cms_post_tag',
            'cms_posts',
            'cms_preview_tokens',
            'cms_public_text_translations',
            'cms_public_texts',
            'cms_redirects',
            'cms_revisions',
            'cms_search_chunks',
            'cms_search_documents',
            'cms_sections',
            'cms_setting_translations',
            'cms_settings',
            'cms_shared_block_scopes',
            'cms_tags',
            'cms_templates',
            'cms_theme_versions',
            'cms_themes',
            'cms_download_access_rules',
            'cms_download_asset_translations',
            'cms_download_assets',
            'cms_download_events',
            'cms_download_folders',
            'cms_download_group_site_user',
            'cms_download_groups',
        ];

        DB::connection('tenant')->statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($tables as $table) {
                if (Schema::connection('tenant')->hasTable($table)) {
                    DB::connection('tenant')->table($table)->delete();
                }
            }
        } finally {
            DB::connection('tenant')->statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    protected function tearDown(): void
    {
        if (DB::connection('tenant')->transactionLevel() > 0) {
            DB::connection('tenant')->rollBack();
        }

        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        TenantContext::clear();

        parent::tearDown();
    }

    public function test_it_exports_and_imports_a_cms_site_package(): void
    {
        $example = app(BuildExampleCmsStarterZipAction::class)->handle();
        $this->temporaryFiles[] = $example['path'];

        app(ImportCmsStarterZipAction::class)->handle(
            new UploadedFile($example['path'], $example['filename'], 'application/zip', null, true)
        );

        $sourceAsset = $this->createMediaAsset();
        $sourceTheme = $this->createTheme();
        $this->createSiteSettings();
        $this->createPublicText();
        $this->createRedirect();
        $sourcePage = CmsPage::query()->where('settings->starter_import_key', 'starter:example-starter:page.home')->firstOrFail();
        $sourceTemplate = CmsTemplate::query()->where('import_key', 'starter:example-starter:template.page-detail')->firstOrFail();
        CmsPage::query()->update(['is_home' => false]);
        CmsPage::query()->whereKey($sourcePage->id)->update(['translation_key' => 'roundtrip-home']);
        $sourcePage->refresh();
        $sourcePage->forceFill(['is_home' => true])->save();
        $sourceLayoutlessPage = $this->createLayoutlessPage('nl');
        $sourceLayoutlessEnglishPage = $this->createLayoutlessPage('en');
        $sourceDuplicateStarterPage = $this->createDuplicateStarterPage();
        $sourceEnglishHomepage = $this->createEnglishHomepage();
        CmsPage::query()->whereKey($sourceEnglishHomepage->id)->update(['translation_key' => 'roundtrip-home']);
        $sourceEnglishHomepage->refresh();
        $this->createHeaderMenu($sourcePage, $sourceEnglishHomepage);
        $layoutlessPageKey = 'page.nl-layoutless-roundtrip-'.$sourceLayoutlessPage->id;
        $layoutlessEnglishPageKey = 'page.en-layoutless-roundtrip-'.$sourceLayoutlessEnglishPage->id;
        $duplicateStarterPageKey = 'page.home-'.$sourceDuplicateStarterPage->id;
        $englishHomepageKey = 'page.en-english-home-'.$sourceEnglishHomepage->id;
        $sourceLayout = $sourceTemplate->layout;
        $this->assertInstanceOf(CmsLayout::class, $sourceLayout);
        $sourceLayout->forceFill(['is_default' => true])->save();
        $sourceTemplate->forceFill(['is_default' => true])->save();
        $sourceBlogTemplate = $this->createBlogTemplate($sourceTemplate);
        [$sourceCategory, $sourceTag] = $this->createTaxonomies($sourcePage, $sourceTemplate);
        $sourcePost = $this->createPost($sourceAsset, $sourceBlogTemplate, $sourceCategory, $sourceTag);
        $sourceForm = $this->createFormWithSubmission($sourcePage);
        $this->attachImageBlock($sourcePage, $sourceAsset);
        $this->attachFormBlock($sourcePage, $sourceForm);

        $export = app(BuildCmsSitePackageZipAction::class)->handle([
            'package_key' => 'site-roundtrip',
            'package_name' => 'Site Roundtrip',
            'modules' => ['site', 'public_texts', 'layouts', 'templates', 'pages', 'menus', 'media', 'themes', 'redirects', 'taxonomies', 'blogs', 'forms'],
        ]);
        $this->temporaryFiles[] = $export['path'];

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($export['path']));

        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        $siteSettings = json_decode((string) $zip->getFromName('site.json'), true, flags: JSON_THROW_ON_ERROR);
        $publicTexts = json_decode((string) $zip->getFromName('public_texts.json'), true, flags: JSON_THROW_ON_ERROR);
        $mediaManifest = json_decode((string) $zip->getFromName('media/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        $themesManifest = json_decode((string) $zip->getFromName('themes/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        $redirects = json_decode((string) $zip->getFromName('redirects.json'), true, flags: JSON_THROW_ON_ERROR);
        $taxonomies = json_decode((string) $zip->getFromName('taxonomies.json'), true, flags: JSON_THROW_ON_ERROR);
        $posts = json_decode((string) $zip->getFromName('posts.json'), true, flags: JSON_THROW_ON_ERROR);
        $forms = json_decode((string) $zip->getFromName('forms.json'), true, flags: JSON_THROW_ON_ERROR);
        $menus = json_decode((string) $zip->getFromName('menus.json'), true, flags: JSON_THROW_ON_ERROR);
        $pages = json_decode((string) $zip->getFromName('pages.json'), true, flags: JSON_THROW_ON_ERROR);
        $sourcePageManifest = collect($pages)->first(fn (array $page): bool => ($page['locale'] ?? null) === 'nl'
            && ($page['translation_key'] ?? null) === 'roundtrip-home');
        $this->assertIsArray($sourcePageManifest);
        $sourcePageKey = (string) ($sourcePageManifest['import_key'] ?? '');
        $mediaFilePath = (string) ($mediaManifest[0]['file'] ?? '');
        $themeManifest = collect($themesManifest)->firstWhere('key', $sourceTheme->key);
        $this->assertIsArray($themeManifest);
        $themeCssPath = (string) data_get($themeManifest, 'versions.0.developer_css_file', '');

        $this->assertNotFalse($zip->locateName($mediaFilePath));
        $this->assertNotFalse($zip->locateName($themeCssPath));
        $this->assertNotFalse($zip->locateName('site/files/logo_path.png'));
        $this->assertNotFalse($zip->locateName('site/files/favicon_32_path.png'));
        $this->assertNotFalse($zip->locateName('site/files/favicon_192_path.png'));
        $this->assertNotFalse($zip->locateName('site/files/apple_touch_icon_path.png'));
        $this->assertFalse($zip->locateName('submissions.json'));
        $this->assertFalse($zip->locateName('form_submissions.json'));
        $zip->close();

        $previewPageCountBefore = CmsPage::query()
            ->where('settings->site_package_import_key', 'site-package:site-roundtrip:'.$sourcePageKey)
            ->count();
        $preview = app(PreviewCmsSitePackageZipAction::class)->handle(
            new UploadedFile($export['path'], $export['filename'], 'application/zip', null, true)
        );

        $this->assertSame('site-roundtrip', $preview['manifest']['key']);
        $this->assertGreaterThanOrEqual(1, $preview['modules']['forms'] ?? 0);
        $this->assertSame($previewPageCountBefore, CmsPage::query()
            ->where('settings->site_package_import_key', 'site-package:site-roundtrip:'.$sourcePageKey)
            ->count());

        $this->assertSame('rwsoft-cms-site-package', $manifest['type']);
        $this->assertSame('site-roundtrip', $manifest['key']);
        $this->assertContains('site', $manifest['modules']);
        $this->assertContains('public_texts', $manifest['modules']);
        $this->assertContains('pages', $manifest['modules']);
        $this->assertContains('media', $manifest['modules']);
        $this->assertContains('themes', $manifest['modules']);
        $this->assertContains('redirects', $manifest['modules']);
        $this->assertContains('taxonomies', $manifest['modules']);
        $this->assertContains('blogs', $manifest['modules']);
        $this->assertContains('forms', $manifest['modules']);
        $this->assertTrue(collect($siteSettings)->contains(fn (array $setting): bool => ($setting['group'] ?? null) === 'general'
            && ($setting['key'] ?? null) === 'site_name'
            && data_get($setting, 'value.value') === 'Roundtrip Site'));
        $this->assertTrue(collect($publicTexts)->contains(fn (array $text): bool => ($text['group'] ?? null) === 'roundtrip'
            && ($text['key'] ?? null) === 'headline'
            && data_get($text, 'translations.en.value') === 'Roundtrip headline'));
        $this->assertSame('media.rwsoft-testpng', $mediaManifest[0]['import_key']);
        $this->assertSame($sourceTheme->key, $themeManifest['key']);
        $this->assertSame('theme.rwsoft-test-theme', $themeManifest['import_key'] ?? null);
        $this->assertSame(count($menus), collect($menus)->pluck('import_key')->unique()->count());
        $headerMenuManifest = collect($menus)->firstWhere('location', 'header');
        $this->assertIsArray($headerMenuManifest);
        $headerMenuImportKey = (string) ($headerMenuManifest['import_key'] ?? '');
        $this->assertNotSame('menu.header', $headerMenuManifest['import_key'] ?? null);
        $this->assertSame(count($headerMenuManifest['items'] ?? []), collect($headerMenuManifest['items'] ?? [])->pluck('import_key')->unique()->count());
        $this->assertTrue(collect($headerMenuManifest['items'] ?? [])->contains(fn (array $item): bool => ($item['translation_key'] ?? null) === 'roundtrip-menu-home'));
        $this->assertTrue(collect($redirects)->contains(fn (array $redirect): bool => ($redirect['source_path'] ?? null) === '/old-roundtrip'
            && ($redirect['target_url'] ?? null) === '/new-roundtrip'
            && ($redirect['status_code'] ?? null) === 301
            && ($redirect['import_key'] ?? null) === 'redirect.old-roundtrip'));
        $pageImportKeys = collect($pages)->pluck('import_key')->all();
        $categoryManifest = collect($taxonomies['categories'] ?? [])->firstWhere('import_key', 'category.post-nl-'.$sourceCategory->slug);
        $tagManifest = collect($taxonomies['tags'] ?? [])->firstWhere('import_key', 'tag.nl-'.$sourceTag->slug);

        $this->assertIsArray($categoryManifest);
        $this->assertIsArray($tagManifest);
        $this->assertContains($categoryManifest['landing_page_import_key'] ?? null, $pageImportKeys);
        $this->assertContains($tagManifest['landing_page_import_key'] ?? null, $pageImportKeys);
        $this->assertSame('template.page-detail', $categoryManifest['detail_template_import_key'] ?? null);
        $this->assertSame('template.page-detail', $tagManifest['detail_template_import_key'] ?? null);
        $postManifest = collect($posts)->firstWhere('import_key', 'post.nl-'.$sourcePost->slug);
        $this->assertIsArray($postManifest);
        $this->assertSame('media.rwsoft-testpng', $postManifest['featured_media_import_key'] ?? null);
        $this->assertSame('category.post-nl-'.$sourceCategory->slug, $postManifest['category_import_keys'][0] ?? null);
        $this->assertSame('tag.nl-'.$sourceTag->slug, $postManifest['tag_import_keys'][0] ?? null);
        $this->assertSame('media.rwsoft-testpng', data_get($postManifest, 'content_blocks.0.media_import_key'));
        $this->assertSame(['media.rwsoft-testpng'], data_get($postManifest, 'content_blocks.1.media_import_keys'));
        $this->assertSame('category.post-nl-'.$sourceCategory->slug, data_get($postManifest, 'content_blocks.2.category_import_key'));
        $this->assertSame('tag.nl-'.$sourceTag->slug, data_get($postManifest, 'content_blocks.2.tag_import_key'));
        $formManifest = collect($forms)->firstWhere('translation_key', $sourceForm->translation_key);
        $this->assertIsArray($formManifest);
        $this->assertSame('Roundtrip contact', $formManifest['title'] ?? null);
        $this->assertTrue((bool) ($formManifest['is_active'] ?? false));
        $this->assertSame('Roundtrip name', data_get($formManifest, 'fields.0.label'));
        $this->assertArrayNotHasKey('submissions', $formManifest);
        $this->assertNotSame('', $sourcePageKey);
        $this->assertSame(count($pages), collect($pages)->pluck('import_key')->unique()->count());
        $layoutlessPageManifest = collect($pages)->firstWhere('import_key', $layoutlessPageKey);
        $layoutlessEnglishPageManifest = collect($pages)->firstWhere('import_key', $layoutlessEnglishPageKey);
        $duplicateStarterPageManifest = collect($pages)->firstWhere('import_key', $duplicateStarterPageKey);
        $englishHomepageManifest = collect($pages)->firstWhere('import_key', $englishHomepageKey);
        $this->assertIsArray($layoutlessPageManifest);
        $this->assertIsArray($layoutlessEnglishPageManifest);
        $this->assertIsArray($duplicateStarterPageManifest);
        $this->assertIsArray($englishHomepageManifest);
        $this->assertArrayNotHasKey('layout_import_key', $layoutlessPageManifest);
        $this->assertArrayNotHasKey('layout_import_key', $layoutlessEnglishPageManifest);
        $this->assertArrayNotHasKey('layout_import_key', $duplicateStarterPageManifest);
        $this->assertSame($sourceLayoutlessPage->title, $layoutlessPageManifest['title'] ?? null);
        $this->assertSame($sourceLayoutlessEnglishPage->title, $layoutlessEnglishPageManifest['title'] ?? null);
        $this->assertSame($sourceDuplicateStarterPage->title, $duplicateStarterPageManifest['title'] ?? null);
        $this->assertSame('roundtrip-home', $sourcePageManifest['translation_key'] ?? null);
        $this->assertSame('roundtrip-home', $englishHomepageManifest['translation_key'] ?? null);
        $this->assertTrue((bool) ($englishHomepageManifest['is_home'] ?? false));
        $this->assertTrue(collect($pages)
            ->flatMap(fn (array $page): array => (array) data_get($page, 'sections.content', []))
            ->flatMap(fn (array $section): array => (array) ($section['placements'] ?? []))
            ->contains(fn (array $placement): bool => data_get($placement, 'block.media_import_key') === 'media.rwsoft-testpng'));
        $this->assertTrue(collect($pages)
            ->flatMap(fn (array $page): array => (array) data_get($page, 'sections.content', []))
            ->flatMap(fn (array $section): array => (array) ($section['placements'] ?? []))
            ->contains(fn (array $placement): bool => data_get($placement, 'block.form_import_key') === ($formManifest['import_key'] ?? null)));

        CmsSetting::query()
            ->where('group', 'general')
            ->where('key', 'site_name')
            ->firstOrFail()
            ->update(['value' => ['value' => 'Changed Site']]);
        CmsPublicText::query()
            ->where('group', 'roundtrip')
            ->where('key', 'headline')
            ->firstOrFail()
            ->translations()
            ->where('locale', 'en')
            ->update(['value' => 'Changed headline']);

        try {
            app(ImportCmsSitePackageZipAction::class)->handle(
                new UploadedFile($export['path'], $export['filename'], 'application/zip', null, true)
            );
            $this->fail('The guarded site package importer should reject non-empty CMS sites.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('site_package_zip', $exception->errors());
        }

        $result = app(ImportCmsStarterZipAction::class)->handle(
            new UploadedFile($export['path'], $export['filename'], 'application/zip', null, true),
            [
                'config' => 'cms_site_packages.import',
                'manifest_type' => config('cms_site_packages.manifest_type'),
                'importable_modules' => config('cms_site_packages.importable_modules', []),
                'import_prefix' => 'site-package',
                'import_marker_key' => 'site_package_import_key',
                'allow_code_blocks' => config('cms_site_packages.import.allow_code_blocks_by_default', false),
            ],
        );

        $this->assertGreaterThanOrEqual(1, $result['imported']['layouts']);
        $this->assertGreaterThanOrEqual(1, $result['imported']['templates']);
        $this->assertGreaterThanOrEqual(1, $result['imported']['pages']);
        $this->assertGreaterThanOrEqual(1, $result['imported']['menus']);
        $this->assertGreaterThanOrEqual(1, $result['imported']['site']);
        $this->assertGreaterThanOrEqual(1, $result['imported']['public_texts']);
        $this->assertSame(1, $result['imported']['media']);
        $this->assertGreaterThanOrEqual(1, $result['imported']['themes']);
        $this->assertGreaterThanOrEqual(1, $result['imported']['redirects']);
        $this->assertGreaterThanOrEqual(2, $result['imported']['taxonomies']);
        $this->assertGreaterThanOrEqual(1, $result['imported']['blogs']);
        $this->assertGreaterThanOrEqual(1, $result['imported']['forms']);

        $this->assertSame(
            'Roundtrip Site',
            CmsSetting::query()->where('group', 'general')->where('key', 'site_name')->firstOrFail()->value['value'] ?? null,
        );
        $this->assertSame(
            'Roundtrip headline',
            CmsPublicText::query()
                ->where('group', 'roundtrip')
                ->where('key', 'headline')
                ->firstOrFail()
                ->translations()
                ->where('locale', 'en')
                ->value('value'),
        );
        $importedRedirect = CmsRedirect::query()
            ->where('source_path', '/old-roundtrip-imported-2')
            ->where('locale', 'nl')
            ->firstOrFail();

        $this->assertSame('/new-roundtrip', $importedRedirect->target_url);
        $this->assertSame(301, (int) $importedRedirect->status_code);
        $this->assertFalse((bool) $importedRedirect->is_active);
        $this->assertSame(0, (int) $importedRedirect->hit_count);
        $this->assertSame('site-package:site-roundtrip:redirect.old-roundtrip', $importedRedirect->import_key);

        $importedCategory = CmsCategory::query()
            ->where('settings->site_package_import_key', 'site-package:site-roundtrip:category.post-nl-'.$sourceCategory->slug)
            ->firstOrFail();
        $importedTag = CmsTag::query()
            ->where('settings->site_package_import_key', 'site-package:site-roundtrip:tag.nl-'.$sourceTag->slug)
            ->firstOrFail();
        $importedTaxonomyPage = CmsPage::query()
            ->where('settings->site_package_import_key', 'site-package:site-roundtrip:'.($categoryManifest['landing_page_import_key'] ?? ''))
            ->firstOrFail();

        $this->assertFalse((bool) $importedCategory->is_active);
        $this->assertFalse((bool) $importedTag->is_active);
        $this->assertSame($importedTaxonomyPage->id, $importedCategory->landing_page_id);
        $this->assertSame($importedTaxonomyPage->id, $importedTag->landing_page_id);

        $importedPost = CmsPost::query()
            ->where('settings->site_package_import_key', 'site-package:site-roundtrip:post.nl-'.$sourcePost->slug)
            ->firstOrFail();

        $this->assertSame('draft', $importedPost->status);
        $this->assertNull($importedPost->published_at);
        $this->assertSame($sourceAsset->id, $importedPost->featured_media_asset_id);
        $this->assertSame($importedCategory->id, $importedPost->categories()->value('cms_categories.id'));
        $this->assertSame($importedTag->id, $importedPost->tags()->value('cms_tags.id'));
        $this->assertSame($sourceAsset->id, data_get($importedPost->content_blocks, '0.media_asset_id'));
        $this->assertSame([$sourceAsset->id], data_get($importedPost->content_blocks, '1.media_asset_ids'));
        $this->assertSame($importedCategory->id, data_get($importedPost->content_blocks, '2.category_id'));
        $this->assertSame($importedTag->id, data_get($importedPost->content_blocks, '2.tag_id'));

        $importedForm = CmsForm::query()
            ->where('settings->site_package_import_key', 'site-package:site-roundtrip:'.($formManifest['import_key'] ?? ''))
            ->firstOrFail();
        $importedField = $importedForm->fields()->firstOrFail();

        $this->assertFalse((bool) $importedForm->is_active);
        $this->assertFalse((bool) $importedField->is_active);
        $this->assertSame('Roundtrip name', $importedField->label);
        $this->assertSame(0, $importedForm->submissions()->count());
        $this->assertSame(1, CmsFormSubmission::query()->count());
        $this->assertSame('sites/1/cms/branding/logo.png', CmsSetting::query()->where('group', 'branding')->where('key', 'logo_path')->firstOrFail()->value['value'] ?? null);
        $this->assertSame('sites/1/cms/favicon/favicon-32x32.png', CmsSetting::query()->where('group', 'branding')->where('key', 'favicon_32_path')->firstOrFail()->value['value'] ?? null);
        Storage::disk('public')->assertExists('sites/1/cms/branding/logo.png');
        Storage::disk('public')->assertExists('sites/1/cms/favicon/favicon-32x32.png');

        $layout = CmsLayout::query()->where('import_key', 'site-package:site-roundtrip:layout.main')->firstOrFail();
        $template = CmsTemplate::query()->where('import_key', 'site-package:site-roundtrip:template.page-detail')->firstOrFail();
        $page = CmsPage::query()->where('settings->site_package_import_key', 'site-package:site-roundtrip:'.$sourcePageKey)->firstOrFail();
        $importedLayoutlessPage = CmsPage::query()->where('settings->site_package_import_key', 'site-package:site-roundtrip:'.$layoutlessPageKey)->firstOrFail();
        $importedLayoutlessEnglishPage = CmsPage::query()->where('settings->site_package_import_key', 'site-package:site-roundtrip:'.$layoutlessEnglishPageKey)->firstOrFail();
        $importedDuplicateStarterPage = CmsPage::query()->where('settings->site_package_import_key', 'site-package:site-roundtrip:'.$duplicateStarterPageKey)->firstOrFail();
        $importedEnglishHomepage = CmsPage::query()->where('settings->site_package_import_key', 'site-package:site-roundtrip:'.$englishHomepageKey)->firstOrFail();
        $menu = CmsMenu::query()->where('settings->site_package_import_key', 'site-package:site-roundtrip:'.$headerMenuImportKey)->firstOrFail();

        $this->assertFalse((bool) $layout->is_active);
        $this->assertFalse((bool) $template->is_active);
        $this->assertSame('none', data_get($layout->sections()->where('zone', 'header')->firstOrFail()->settings, 'spacing'));
        $this->assertSame('none', data_get($layout->sections()->where('zone', 'footer')->firstOrFail()->settings, 'spacing'));
        $this->assertSame('draft', $page->status);
        $this->assertSame($importedLayoutlessPage->slug, $importedLayoutlessEnglishPage->slug);
        $this->assertSame($page->translation_key, $importedEnglishHomepage->translation_key);
        $this->assertSame('draft', $importedLayoutlessPage->status);
        $this->assertSame('draft', $importedLayoutlessEnglishPage->status);
        $this->assertSame('draft', $importedDuplicateStarterPage->status);
        $this->assertFalse((bool) $page->is_home);
        $this->assertFalse((bool) $menu->is_active);

        $importedTheme = CmsTheme::query()
            ->where('name', 'RwSoft Test Theme')
            ->where('id', '!=', $sourceTheme->id)
            ->firstOrFail();

        $this->assertFalse((bool) $importedTheme->is_active);
        $this->assertSame('draft', $importedTheme->status);
        $this->assertNull($importedTheme->active_version_id);
        $this->assertSame('site-package:site-roundtrip:theme.rwsoft-test-theme', $importedTheme->import_key);
        $this->assertTrue($importedTheme->versions()->exists());

        $importedImageBlock = CmsBlock::query()
            ->where('type', 'image')
            ->where('content->media_asset_id', $sourceAsset->id)
            ->latest('id')
            ->first();

        $this->assertInstanceOf(CmsBlock::class, $importedImageBlock);

        $importedFormBlock = CmsBlock::query()
            ->where('type', 'form')
            ->where('content->form_translation_key', $importedForm->translation_key)
            ->latest('id')
            ->first();

        $this->assertInstanceOf(CmsBlock::class, $importedFormBlock);

        $activated = app(ActivateCmsSitePackageAction::class)->handle([
            'package_key' => 'site-roundtrip',
            'modules' => ['layouts', 'templates', 'pages', 'menus', 'redirects', 'taxonomies', 'blogs', 'forms', 'themes'],
            'publish_pages' => true,
            'publish_blogs' => true,
            'set_homepage' => true,
            'set_default_layouts' => true,
            'set_default_templates' => true,
            'activate_theme_import_key' => 'theme.rwsoft-test-theme',
        ]);

        $this->assertGreaterThanOrEqual(1, $activated['forms']);
        $this->assertGreaterThanOrEqual(1, $activated['redirects']);
        $this->assertGreaterThanOrEqual(1, $activated['themes']);
        $this->assertSame(1, $activated['homepage']);
        $this->assertGreaterThanOrEqual(1, $activated['default_layouts']);
        $this->assertGreaterThanOrEqual(1, $activated['default_templates']);
        $this->assertTrue((bool) $importedForm->fresh()->is_active);
        $this->assertTrue((bool) $importedField->fresh()->is_active);
        $this->assertTrue((bool) $importedRedirect->fresh()->is_active);
        $this->assertTrue((bool) $importedTheme->fresh()->is_active);
        $this->assertSame('active', $importedTheme->fresh()->status);
        $this->assertTrue(CmsLayout::query()
            ->where('import_key', 'like', 'site-package:site-roundtrip:%')
            ->where('is_default', true)
            ->exists());
        $this->assertTrue(CmsTemplate::query()
            ->where('import_key', 'like', 'site-package:site-roundtrip:%')
            ->where('is_default', true)
            ->exists());
        $activatedHomepage = CmsPage::query()
            ->where('settings->site_package_import_key', 'like', 'site-package:site-roundtrip:%')
            ->where('is_home', true)
            ->first();
        $this->assertInstanceOf(CmsPage::class, $activatedHomepage);
        $this->assertSame('nl', $activatedHomepage->locale);
        $this->assertTrue((bool) data_get($activatedHomepage->settings, 'site_package_was_home'));
        $this->assertSame($activatedHomepage->id, CmsSetting::query()->where('group', 'general')->where('key', 'homepage_id')->firstOrFail()->value['value'] ?? null);
        $this->assertSame('published', $importedPost->fresh()->status);
        $this->assertSame('published', $page->fresh()->status);
        $this->assertSame('published', $importedLayoutlessPage->fresh()->status);
        $this->assertSame('published', $importedLayoutlessEnglishPage->fresh()->status);
        $this->assertSame('published', $importedDuplicateStarterPage->fresh()->status);
        $this->assertTrue((bool) $importedCategory->fresh()->is_active);
        $this->assertTrue((bool) $importedTag->fresh()->is_active);
    }

    public function test_it_roundtrips_language_flags_through_site_packages(): void
    {
        $flagAsset = $this->createMediaAsset();
        CmsLanguage::query()->updateOrCreate(
            ['locale' => 'en'],
            [
                'name' => 'English',
                'native_name' => 'English',
                'flag_media_asset_id' => $flagAsset->id,
                'direction' => 'ltr',
                'is_active' => true,
                'sort_order' => 20,
            ],
        );

        $export = app(BuildCmsSitePackageZipAction::class)->handle([
            'package_key' => 'language-roundtrip',
            'package_name' => 'Language Roundtrip',
            'modules' => ['languages'],
        ]);
        $this->temporaryFiles[] = $export['path'];

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($export['path']));

        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        $languages = json_decode((string) $zip->getFromName('languages.json'), true, flags: JSON_THROW_ON_ERROR);
        $mediaManifest = json_decode((string) $zip->getFromName('media/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        $language = collect($languages)->firstWhere('locale', 'en');

        $this->assertContains('languages', $manifest['modules']);
        $this->assertContains('media', $manifest['modules']);
        $this->assertSame('media.rwsoft-testpng', $language['flag_media_import_key'] ?? null);
        $this->assertSame('media.rwsoft-testpng', $mediaManifest[0]['import_key'] ?? null);
        $zip->close();

        CmsLanguage::query()->where('locale', 'en')->update([
            'name' => 'Changed English',
            'native_name' => 'Changed English',
            'flag_media_asset_id' => null,
            'direction' => 'rtl',
            'is_active' => false,
            'sort_order' => 90,
        ]);

        $result = app(ImportCmsStarterZipAction::class)->handle(
            new UploadedFile($export['path'], $export['filename'], 'application/zip', null, true),
            [
                'config' => 'cms_site_packages.import',
                'manifest_type' => config('cms_site_packages.manifest_type'),
                'importable_modules' => config('cms_site_packages.importable_modules', []),
                'import_prefix' => 'site-package',
                'import_marker_key' => 'site_package_import_key',
                'allow_code_blocks' => config('cms_site_packages.import.allow_code_blocks_by_default', false),
            ],
        );

        $importedLanguage = CmsLanguage::query()->where('locale', 'en')->firstOrFail();

        $this->assertGreaterThanOrEqual(1, $result['imported']['languages']);
        $this->assertSame($importedLanguage->id, $result['mappings']['languages']['en'] ?? null);
        $this->assertSame($flagAsset->id, $importedLanguage->flag_media_asset_id);
        $this->assertSame('English', $importedLanguage->name);
        $this->assertSame('English', $importedLanguage->native_name);
        $this->assertSame('ltr', $importedLanguage->direction);
        $this->assertTrue((bool) $importedLanguage->is_active);
        $this->assertSame(20, (int) $importedLanguage->sort_order);
    }

    private function createDuplicateStarterPage(): CmsPage
    {
        return CmsPage::query()->create([
            'parent_id' => null,
            'detail_template_id' => $this->pageDetailTemplateId('nl'),
            'title' => 'Duplicate starter home',
            'slug' => 'duplicate-starter-home',
            'locale' => 'nl',
            'status' => 'published',
            'template' => null,
            'short_description' => null,
            'content_blocks' => [],
            'seo_title' => 'Duplicate starter home',
            'seo_description' => null,
            'canonical_url' => null,
            'og_image_path' => null,
            'noindex' => false,
            'is_home' => false,
            'is_searchable' => true,
            'sort_order' => 30,
            'published_at' => now()->subMinute(),
            'settings' => ['starter_import_key' => 'starter:duplicate:page.home'],
        ]);
    }

    private function createEnglishHomepage(): CmsPage
    {
        return CmsPage::query()->create([
            'parent_id' => null,
            'detail_template_id' => $this->pageDetailTemplateId('en'),
            'title' => 'English home',
            'slug' => 'english-home',
            'locale' => 'en',
            'status' => 'published',
            'template' => null,
            'short_description' => null,
            'content_blocks' => [],
            'seo_title' => 'English home',
            'seo_description' => null,
            'canonical_url' => null,
            'og_image_path' => null,
            'noindex' => false,
            'is_home' => true,
            'is_searchable' => true,
            'sort_order' => 1,
            'published_at' => now()->subMinute(),
            'settings' => [],
        ]);
    }

    private function createLayoutlessPage(string $locale): CmsPage
    {
        return CmsPage::query()->create([
            'parent_id' => null,
            'detail_template_id' => $this->pageDetailTemplateId($locale),
            'title' => 'Layoutless roundtrip '.strtoupper($locale),
            'slug' => 'layoutless-roundtrip',
            'locale' => $locale,
            'status' => 'published',
            'template' => null,
            'short_description' => null,
            'content_blocks' => [],
            'seo_title' => 'Layoutless roundtrip',
            'seo_description' => null,
            'canonical_url' => null,
            'og_image_path' => null,
            'noindex' => false,
            'is_home' => false,
            'is_searchable' => true,
            'sort_order' => 20,
            'published_at' => now()->subMinute(),
            'settings' => ['source' => 'roundtrip'],
        ]);
    }

    private function pageDetailTemplateId(string $locale): int
    {
        $layout = CmsLayout::query()->firstOrCreate(
            ['name' => 'Roundtrip page layout '.$locale, 'locale' => $locale],
            [
                'is_default' => true,
                'is_active' => true,
                'cache_strategy' => 'inherit',
                'settings' => [],
            ]
        );

        return (int) CmsTemplate::query()->firstOrCreate(
            [
                'name' => 'Roundtrip page detail '.$locale,
                'locale' => $locale,
                'template_class' => 'page',
                'template_key' => 'page.detail',
            ],
            [
                'layout_id' => $layout->id,
                'is_default' => true,
                'is_active' => true,
                'cache_strategy' => 'inherit',
                'settings' => [],
            ]
        )->id;
    }

    private function createMediaAsset(): CmsMediaAsset
    {
        $contents = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/lZ3HkwAAAABJRU5ErkJggg==');
        $this->assertIsString($contents);
        Storage::disk('public')->put('cms/media/rwsoft-test.png', $contents);

        return CmsMediaAsset::query()->create([
            'disk' => 'public',
            'visibility' => 'public',
            'path' => 'cms/media/rwsoft-test.png',
            'filename' => 'rwsoft-test.png',
            'original_filename' => 'rwsoft-test.png',
            'mime_type' => 'image/png',
            'extension' => 'png',
            'size_bytes' => strlen($contents),
            'width' => 1,
            'height' => 1,
            'hash' => hash('sha256', $contents),
            'alt_text' => 'RwSoft test image',
            'caption' => 'RwSoft test caption',
            'sort_order' => 1,
        ]);
    }

    private function createTheme(): CmsTheme
    {
        $theme = CmsTheme::query()->create([
            'key' => 'rwsoft-test-theme',
            'name' => 'RwSoft Test Theme',
            'description' => 'Theme for site package tests',
            'author' => 'RwSoft',
            'version' => '1.0.0',
            'status' => 'draft',
            'is_active' => false,
        ]);

        $hash = 'abcdef1234567890abcdef1234567890';
        $developerPath = 'themes/rwsoft-test-theme/versions/'.$hash.'/developer.css';
        $generatedPath = 'themes/rwsoft-test-theme/versions/'.$hash.'/generated.css';
        $minifiedPath = 'themes/rwsoft-test-theme/versions/'.$hash.'/theme.min.css';

        Storage::disk('local')->put($developerPath, ':root { --rw-public-color-primary: #123456; }');
        Storage::disk('local')->put($generatedPath, ':root { --rw-public-color-page: #ffffff; }');
        Storage::disk('local')->put($minifiedPath, ':root{--rw-public-color-primary:#123456;}');

        CmsThemeVersion::query()->create([
            'cms_theme_id' => $theme->id,
            'version_hash' => $hash,
            'developer_css_path' => $developerPath,
            'generated_css_path' => $generatedPath,
            'minified_css_path' => $minifiedPath,
            'settings' => ['primary_color' => '#123456'],
            'source_manifest' => [
                'type' => 'rwsoft-css-theme',
                'key' => 'rwsoft-test-theme',
                'name' => 'RwSoft Test Theme',
            ],
            'external_assets' => [],
            'file_size_kb' => 1,
        ]);

        return $theme->fresh(['versions']) ?: $theme;
    }

    private function createSiteSettings(): void
    {
        $setting = CmsSetting::query()->updateOrCreate(
            ['group' => 'general', 'key' => 'site_name'],
            [
                'label' => 'Site name',
                'type' => 'text',
                'value' => ['value' => 'Roundtrip Site'],
                'is_public' => true,
                'sort_order' => 10,
            ],
        );

        $setting->translations()->updateOrCreate(
            ['locale' => 'en'],
            ['value' => ['value' => 'Roundtrip Site EN']],
        );

        CmsSetting::query()->updateOrCreate(
            ['group' => 'general', 'key' => 'default_locale'],
            [
                'label' => 'Default locale',
                'type' => 'text',
                'value' => ['value' => 'nl'],
                'is_public' => true,
                'sort_order' => 15,
            ],
        );

        $contents = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/lZ3HkwAAAABJRU5ErkJggg==');
        $this->assertIsString($contents);

        foreach ([
            'logo_path' => 'sites/99/cms/branding/logo.png',
            'favicon_32_path' => 'sites/99/cms/favicon/favicon-32x32.png',
            'favicon_192_path' => 'sites/99/cms/favicon/favicon-192x192.png',
            'apple_touch_icon_path' => 'sites/99/cms/favicon/apple-touch-icon.png',
        ] as $key => $path) {
            Storage::disk('public')->put($path, $contents);

            CmsSetting::query()->updateOrCreate(
                ['group' => 'branding', 'key' => $key],
                [
                    'label' => $key,
                    'type' => 'text',
                    'value' => ['value' => $path],
                    'is_public' => true,
                    'sort_order' => 20,
                ],
            );
        }
    }

    private function createHeaderMenu(CmsPage $sourcePage, CmsPage $sourceEnglishHomepage): CmsMenu
    {
        $menu = CmsMenu::query()->create([
            'title' => 'Header',
            'placements' => ['header'],
            'is_active' => true,
            'settings' => ['starter_import_key' => 'starter:roundtrip-header:menu.header'],
        ]);

        CmsMenuItem::query()->create([
            'cms_menu_id' => $menu->id,
            'locale' => 'nl',
            'translation_key' => 'roundtrip-menu-home',
            'parent_id' => null,
            'cms_page_id' => $sourcePage->id,
            'type' => 'page',
            'label' => 'Home',
            'sort_order' => 10,
            'is_active' => true,
            'metadata' => ['starter_import_key' => 'starter:roundtrip-header:menu.header.home'],
        ]);

        CmsMenuItem::query()->create([
            'cms_menu_id' => $menu->id,
            'locale' => 'en',
            'translation_key' => 'roundtrip-menu-home',
            'parent_id' => null,
            'cms_page_id' => $sourceEnglishHomepage->id,
            'type' => 'page',
            'label' => 'Home EN',
            'sort_order' => 20,
            'is_active' => true,
            'metadata' => ['starter_import_key' => 'starter:roundtrip-header:menu.header.home-en'],
        ]);

        return $menu;
    }

    private function createPublicText(): CmsPublicText
    {
        $text = CmsPublicText::query()->updateOrCreate(
            ['group' => 'roundtrip', 'key' => 'headline'],
            [
                'label' => 'Roundtrip headline',
                'description' => 'Roundtrip public text',
                'default_value' => 'Roundtrip headline default',
                'type' => 'text',
                'is_system' => true,
                'sort_order' => 10,
            ],
        );

        $text->translations()->updateOrCreate(
            ['locale' => 'en'],
            ['value' => 'Roundtrip headline'],
        );

        return $text;
    }

    private function createRedirect(): CmsRedirect
    {
        return CmsRedirect::query()->create([
            'source_path' => '/old-roundtrip',
            'target_url' => '/new-roundtrip',
            'status_code' => 301,
            'locale' => 'nl',
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'hit_count' => 12,
            'last_hit_at' => now(),
        ]);
    }

    private function createBlogTemplate(CmsTemplate $sourceTemplate): CmsTemplate
    {
        return CmsTemplate::query()->create([
            'layout_id' => $sourceTemplate->layout_id,
            'name' => 'Roundtrip blog detail',
            'locale' => 'nl',
            'template_class' => 'blog',
            'template_key' => 'blog.detail',
            'is_active' => true,
            'is_default' => false,
            'cache_strategy' => 'inherit',
            'settings' => [],
        ]);
    }

    /**
     * @return array{0: CmsCategory, 1: CmsTag}
     */
    private function createTaxonomies(CmsPage $page, CmsTemplate $template): array
    {
        $suffix = uniqid();
        $category = CmsCategory::query()->create([
            'type' => 'post',
            'title' => 'Roundtrip category',
            'slug' => 'roundtrip-category-'.$suffix,
            'locale' => 'nl',
            'translation_key' => 'roundtrip-category-'.$suffix,
            'landing_page_id' => $page->id,
            'archive_template_id' => $template->id,
            'detail_template_id' => $template->id,
            'description' => 'Roundtrip category description',
            'sort_order' => 10,
            'is_active' => true,
            'settings' => ['source' => 'roundtrip'],
        ]);
        $tag = CmsTag::query()->create([
            'title' => 'Roundtrip tag',
            'slug' => 'roundtrip-tag-'.$suffix,
            'locale' => 'nl',
            'translation_key' => 'roundtrip-tag-'.$suffix,
            'landing_page_id' => $page->id,
            'archive_template_id' => $template->id,
            'detail_template_id' => $template->id,
            'description' => 'Roundtrip tag description',
            'is_active' => true,
            'settings' => ['source' => 'roundtrip'],
        ]);

        return [$category, $tag];
    }

    private function createPost(CmsMediaAsset $asset, CmsTemplate $template, CmsCategory $category, CmsTag $tag): CmsPost
    {
        $suffix = uniqid();
        $post = CmsPost::query()->create([
            'author_id' => null,
            'featured_media_asset_id' => $asset->id,
            'detail_template_id' => $template->id,
            'title' => 'Roundtrip blog post',
            'slug' => 'roundtrip-blog-'.$suffix,
            'locale' => 'nl',
            'translation_key' => 'roundtrip-blog-'.$suffix,
            'status' => 'published',
            'excerpt' => 'Roundtrip blog excerpt',
            'content_blocks' => [
                [
                    'type' => 'image',
                    'media_asset_id' => $asset->id,
                    'caption' => 'Roundtrip post image',
                    'width_mode' => 'content',
                ],
                [
                    'type' => 'logo_strip',
                    'title' => 'Roundtrip logos',
                    'media_asset_ids' => [$asset->id],
                ],
                [
                    'type' => 'list_rows',
                    'title' => 'Roundtrip taxonomy list',
                    'source_type' => 'category',
                    'category_source' => 'fixed',
                    'category_id' => $category->id,
                    'tag_source' => 'fixed',
                    'tag_id' => $tag->id,
                ],
            ],
            'seo_title' => 'Roundtrip blog SEO',
            'seo_description' => 'Roundtrip blog SEO description',
            'noindex' => false,
            'is_featured' => true,
            'is_searchable' => true,
            'published_at' => now()->subDay(),
            'settings' => ['structured_data_schema_type' => 'BlogPosting'],
        ]);

        $post->categories()->sync([$category->id]);
        $post->tags()->sync([$tag->id]);

        return $post;
    }

    private function createFormWithSubmission(CmsPage $page): CmsForm
    {
        $suffix = uniqid();
        $form = CmsForm::query()->create([
            'title' => 'Roundtrip contact',
            'locale' => 'nl',
            'translation_key' => 'roundtrip-form-'.$suffix,
            'description' => 'Roundtrip form description',
            'notification_email' => 'forms@example.test',
            'submit_button_label' => 'Versturen',
            'success_message' => 'Bedankt',
            'is_active' => true,
            'settings' => ['source' => 'roundtrip'],
        ]);
        $field = CmsFormField::query()->create([
            'cms_form_id' => $form->id,
            'type' => 'text',
            'translation_key' => 'roundtrip-name-'.$suffix,
            'label' => 'Roundtrip name',
            'placeholder' => 'Naam',
            'help_text' => 'Vul je naam in',
            'options' => [],
            'validation_rules' => ['required', 'string', 'max:255'],
            'sort_order' => 10,
            'is_required' => true,
            'is_active' => true,
            'width' => 'full',
            'settings' => ['source' => 'roundtrip'],
        ]);
        $submission = CmsFormSubmission::query()->create([
            'cms_form_id' => $form->id,
            'cms_page_id' => $page->id,
            'locale' => 'nl',
            'form_translation_key' => $form->translation_key,
            'status' => 'new',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test browser',
            'submitted_at' => now(),
            'metadata' => ['privacy' => 'private'],
        ]);

        CmsFormSubmissionValue::query()->create([
            'cms_form_submission_id' => $submission->id,
            'cms_form_field_id' => $field->id,
            'field_translation_key' => $field->translation_key,
            'field_label_snapshot' => $field->label,
            'value' => 'Private answer',
        ]);

        return $form->fresh(['fields']) ?: $form;
    }

    private function attachImageBlock(CmsPage $page, CmsMediaAsset $asset): void
    {
        $section = CmsSection::query()->create([
            'owner_type' => CmsPage::class,
            'owner_id' => $page->id,
            'zone' => 'content',
            'name' => 'Media test',
            'sort_order' => 99,
            'is_active' => true,
            'visible_mobile' => true,
            'visible_tablet' => true,
            'visible_desktop' => true,
            'settings' => [],
        ]);

        $block = CmsBlock::query()->create([
            'type' => 'image',
            'name' => 'Media test image',
            'content' => [
                'media_asset_id' => $asset->id,
                'caption' => 'RwSoft test caption',
            ],
            'settings' => [],
            'is_shared' => false,
            'is_dynamic' => false,
            'cache_strategy' => 'inherit',
        ]);

        CmsBlockPlacement::query()->create([
            'cms_section_id' => $section->id,
            'cms_block_id' => $block->id,
            'sort_order' => 0,
            'is_active' => true,
            'visible_mobile' => true,
            'visible_tablet' => true,
            'visible_desktop' => true,
            'mobile_span' => 12,
            'tablet_span' => 12,
            'desktop_span' => 12,
            'height_mode' => 'auto',
            'cache_strategy' => 'inherit',
            'settings' => [],
        ]);
    }

    private function attachFormBlock(CmsPage $page, CmsForm $form): void
    {
        $section = CmsSection::query()->create([
            'owner_type' => CmsPage::class,
            'owner_id' => $page->id,
            'zone' => 'content',
            'name' => 'Form test',
            'sort_order' => 100,
            'is_active' => true,
            'visible_mobile' => true,
            'visible_tablet' => true,
            'visible_desktop' => true,
            'settings' => [],
        ]);

        $block = CmsBlock::query()->create([
            'type' => 'form',
            'name' => 'Form test block',
            'content' => [
                'form_translation_key' => $form->translation_key,
            ],
            'settings' => [],
            'is_shared' => false,
            'is_dynamic' => false,
            'cache_strategy' => 'inherit',
        ]);

        CmsBlockPlacement::query()->create([
            'cms_section_id' => $section->id,
            'cms_block_id' => $block->id,
            'sort_order' => 0,
            'is_active' => true,
            'visible_mobile' => true,
            'visible_tablet' => true,
            'visible_desktop' => true,
            'mobile_span' => 12,
            'tablet_span' => 12,
            'desktop_span' => 12,
            'height_mode' => 'auto',
            'cache_strategy' => 'inherit',
            'settings' => [],
        ]);
    }
}
