<?php

namespace Tests\Feature\PublicSite;

use App\Http\Middleware\ResolveTenantSite;
use App\Models\Cms\CmsBlock;
use App\Models\Cms\CmsBlockPlacement;
use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsForm;
use App\Models\Cms\CmsFormField;
use App\Models\Cms\CmsLanguage;
use App\Models\Cms\CmsLayout;
use App\Models\Cms\CmsMediaAsset;
use App\Models\Cms\CmsMenu;
use App\Models\Cms\CmsMenuItem;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPlaceableBlock;
use App\Models\Cms\CmsPost;
use App\Models\Cms\CmsSection;
use App\Models\Cms\CmsSetting;
use App\Models\Cms\CmsTag;
use App\Models\Cms\CmsTemplate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class PublicCmsTestCase extends TestCase
{
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

        $this->withoutMiddleware(ResolveTenantSite::class);
    }

    protected function tearDown(): void
    {
        if (DB::connection('tenant')->transactionLevel() > 0) {
            DB::connection('tenant')->rollBack();
        }

        TenantContext::clear();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createPage(array $overrides = []): CmsPage
    {
        $locale = (string) ($overrides['locale'] ?? 'nl');

        if (! array_key_exists('detail_template_id', $overrides)) {
            $overrides['detail_template_id'] = $this->createDefaultPageDetailTemplate($locale)->id;
        }

        return CmsPage::query()->create(array_merge([
            'title' => 'Publieke pagina',
            'slug' => 'publieke-pagina-'.uniqid(),
            'locale' => $locale,
            'status' => 'published',
            'short_description' => null,
            'content_blocks' => [],
            'seo_title' => null,
            'seo_description' => null,
            'canonical_url' => null,
            'noindex' => false,
            'is_home' => false,
            'is_searchable' => true,
            'sort_order' => 0,
            'published_at' => null,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createLayout(array $overrides = []): CmsLayout
    {
        if ((bool) ($overrides['is_default'] ?? false)) {
            CmsLayout::query()
                ->where('locale', $overrides['locale'] ?? 'nl')
                ->update(['is_default' => false]);
        }

        return CmsLayout::query()->create(array_merge([
            'name' => 'Publieke layout',
            'locale' => 'nl',
            'is_default' => false,
            'is_active' => true,
            'cache_strategy' => 'inherit',
            'settings' => [],
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createSection(CmsLayout|CmsPage|CmsTemplate $owner, array $overrides = []): CmsSection
    {
        return $owner->sections()->create(array_merge([
            'zone' => 'content',
            'name' => 'Publieke sectie',
            'sort_order' => 0,
            'is_active' => true,
            'visible_mobile' => true,
            'visible_tablet' => true,
            'visible_desktop' => true,
            'settings' => [],
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createBlock(array $overrides = []): CmsBlock
    {
        $type = (string) ($overrides['type'] ?? 'text');
        $placeableBlock = CmsPlaceableBlock::query()
            ->with('latestPublishedRevision')
            ->where('key', $type)
            ->firstOrFail();

        return CmsBlock::query()->create(array_merge([
            'cms_placeable_block_id' => $placeableBlock->id,
            'placeable_block_revision_id' => $placeableBlock->latestPublishedRevision?->id,
            'type' => $type,
            'name' => 'Publiek blok',
            'content' => ['title' => 'Publieke titel', 'text' => 'Publieke tekst'],
            'settings' => [],
            'is_shared' => false,
            'is_dynamic' => false,
            'cache_strategy' => 'inherit',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createPlacement(CmsSection $section, CmsBlock $block, array $overrides = []): CmsBlockPlacement
    {
        return $section->placements()->create(array_merge([
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
            'height_value' => null,
            'cache_strategy' => 'inherit',
            'settings' => [],
        ], $overrides));
    }

    protected function createDefaultPageDetailTemplate(string $locale = 'nl'): CmsTemplate
    {
        $layout = $this->createLayout([
            'name' => 'Public page detail layout '.$locale.' '.uniqid(),
            'locale' => $locale,
            'is_default' => false,
        ]);

        $template = CmsTemplate::query()->create([
            'name' => 'Public page detail template '.$locale.' '.uniqid(),
            'locale' => $locale,
            'layout_id' => $layout->id,
            'template_class' => 'page',
            'template_key' => 'page.detail',
            'is_default' => false,
            'is_active' => true,
            'cache_strategy' => 'inherit',
            'settings' => [],
        ]);

        $titleSection = $this->createSection($template, [
            'name' => 'Page title',
            'zone' => 'content',
            'sort_order' => 0,
        ]);
        $this->createPlacement($titleSection, $this->createBlock([
            'type' => 'dynamic_field',
            'content' => [
                'field_key' => 'page.title',
                'heading_level' => 'h1',
            ],
        ]));

        $contentSection = $this->createSection($template, [
            'name' => 'Page content',
            'zone' => 'content',
            'sort_order' => 10,
        ]);
        $this->createPlacement($contentSection, $this->createBlock([
            'type' => 'content_slot',
            'content' => [
                'slot_key' => 'content',
            ],
        ]));

        return $template;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createPost(array $overrides = []): CmsPost
    {
        return CmsPost::query()->create(array_merge([
            'title' => 'Publieke post',
            'slug' => 'publieke-post-'.uniqid(),
            'locale' => 'nl',
            'status' => 'published',
            'excerpt' => null,
            'content_blocks' => [],
            'seo_title' => null,
            'seo_description' => null,
            'canonical_url' => null,
            'noindex' => false,
            'is_featured' => false,
            'is_searchable' => true,
            'published_at' => null,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createMediaAsset(array $overrides = []): CmsMediaAsset
    {
        return CmsMediaAsset::query()->create(array_merge([
            'disk' => 'public',
            'visibility' => 'public',
            'path' => 'cms/media/test-'.uniqid().'.jpg',
            'filename' => 'test.jpg',
            'original_filename' => 'test.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => 1024,
            'width' => 1200,
            'height' => 800,
            'hash' => hash('sha256', uniqid('', true)),
            'alt_text' => null,
            'caption' => null,
            'sort_order' => 0,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createMenu(array $overrides = []): CmsMenu
    {
        $legacyLocation = is_string($overrides['location'] ?? null) ? (string) $overrides['location'] : null;

        unset($overrides['locale']);
        unset($overrides['location']);

        if ($legacyLocation !== null && ! array_key_exists('placements', $overrides)) {
            $overrides['placements'] = [$legacyLocation];
        }

        return CmsMenu::query()->create(array_merge([
            'title' => 'Publiek menu',
            'placements' => ['header'],
            'is_active' => true,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createMenuItem(CmsMenu $menu, array $overrides = []): CmsMenuItem
    {
        return CmsMenuItem::query()->create(array_merge([
            'cms_menu_id' => $menu->id,
            'parent_id' => null,
            'locale' => $this->defaultLocale(),
            'type' => 'custom',
            'label' => 'Menu item',
            'url' => '/',
            'target' => null,
            'rel' => null,
            'sort_order' => 0,
            'is_active' => true,
        ], $overrides));
    }

    private function defaultLocale(): string
    {
        $setting = CmsSetting::query()
            ->where('group', 'general')
            ->where('key', 'default_locale')
            ->value('value');

        if (is_array($setting) && filled($setting['value'] ?? null)) {
            return (string) $setting['value'];
        }

        return 'nl';
    }

    protected function createForm(string $locale = 'fm'): CmsForm
    {
        return CmsForm::query()->create([
            'title' => 'Publiek contact',
            'locale' => $locale,
            'translation_key' => (string) Str::ulid(),
            'description' => 'Neem contact op.',
            'submit_button_label' => 'Versturen',
            'success_message' => 'Bedankt voor je bericht.',
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createFormField(CmsForm $form, array $overrides = []): CmsFormField
    {
        unset($overrides['key']);

        return $form->fields()->create(array_merge([
            'type' => 'text',
            'translation_key' => (string) Str::ulid(),
            'label' => 'Naam',
            'sort_order' => 10,
            'is_required' => true,
            'is_active' => true,
            'width' => 'half',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createCategory(array $overrides = []): CmsCategory
    {
        return CmsCategory::query()->create(array_merge([
            'type' => 'post',
            'title' => 'Nieuws',
            'slug' => 'nieuws-'.uniqid(),
            'locale' => 'nl',
            'is_active' => true,
            'sort_order' => 0,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createTag(array $overrides = []): CmsTag
    {
        return CmsTag::query()->create(array_merge([
            'title' => 'Release',
            'slug' => 'release-'.uniqid(),
            'locale' => 'nl',
            'is_active' => true,
        ], $overrides));
    }

    protected function storeSetting(string $group, string $key, mixed $value): void
    {
        if ($group === 'general' && $key === 'default_locale') {
            CmsLanguage::query()->updateOrCreate(
                ['locale' => (string) $value],
                [
                    'name' => strtoupper((string) $value),
                    'native_name' => strtoupper((string) $value),
                    'direction' => 'ltr',
                    'is_active' => true,
                    'sort_order' => 10,
                ]
            );
        }

        CmsSetting::query()->updateOrCreate(
            ['group' => $group, 'key' => $key],
            [
                'label' => $key,
                'type' => is_bool($value) ? 'boolean' : 'text',
                'value' => ['value' => $value],
                'is_public' => true,
                'sort_order' => 0,
            ]
        );
    }
}
