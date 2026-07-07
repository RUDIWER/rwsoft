<?php

namespace Tests\Feature\PublicSite;

use App\Models\Cms\CmsLanguage;
use App\Models\Cms\CmsLayout;
use App\Models\Cms\CmsPlaceableBlock;
use App\Models\Cms\CmsPublicText;
use App\Models\Cms\CmsRedirect;
use App\Models\Cms\CmsSetting;
use App\Models\Cms\CmsTemplate;
use App\Support\PublicSite\PublicMediaUrl;

class PublicCmsBladeRenderingTest extends PublicCmsTestCase
{
    public function test_homepage_renders_real_blade_html_with_meta_and_content_sections(): void
    {
        $media = $this->createMediaAsset([
            'alt_text' => 'Publieke afbeelding',
        ]);
        $media->translations()->create([
            'locale' => 'nl',
            'alt_text' => 'Nederlandse afbeelding',
            'caption' => 'Nederlandse caption',
        ]);
        $page = $this->createPage([
            'title' => 'Publieke homepage',
            'slug' => 'publieke-homepage-'.uniqid(),
            'is_home' => true,
            'short_description' => 'Welkom op de publieke CMS homepage.',
            'seo_description' => 'Server-side meta omschrijving.',
        ]);
        $section = $this->createSection($page, [
            'settings' => [
                'layout_type' => 'hero',
                'width_mode' => 'display',
                'spacing' => 'spacious',
                'background_color' => '#2563eb',
            ],
        ]);
        $this->createPlacement($section, $this->createBlock([
            'content' => ['title' => 'Intro', 'text' => 'Dit is publieke content.'],
        ]));
        $this->createPlacement($section, $this->createBlock([
            'type' => 'image',
            'content' => ['media_asset_id' => $media->id, 'caption' => 'Afbeelding caption'],
        ]), ['sort_order' => 1]);
        $this->storeSetting('general', 'homepage_id', $page->id);
        $this->storeSetting('general', 'site_name', 'RwSoft publiek');
        $this->storeSetting('general', 'default_locale', 'nl');

        $this
            ->get(route('cms.public.home'))
            ->assertOk()
            ->assertSee('<h1 class="rw-public-title">Publieke homepage</h1>', false)
            ->assertSee('Dit is publieke content.')
            ->assertSee('rw-public-section--layout-hero', false)
            ->assertSee('rw-public-section--width-display', false)
            ->assertSee('rw-public-section--spacing-spacious', false)
            ->assertSee('rw-public-section--background-custom', false)
            ->assertSee('--rw-public-section-background-color: #2563eb;', false)
            ->assertSee('data-layout-type="hero"', false)
            ->assertDontSee('rw-public-placement--display-width', false)
            ->assertSee('Server-side meta omschrijving.')
            ->assertSee('<meta name="description" content="Server-side meta omschrijving.">', false)
            ->assertSee('<img', false)
            ->assertSee('Nederlandse afbeelding')
            ->assertDontSee('component&quot;:&quot;Public/Cms/Page', false);
    }

    public function test_public_page_without_section_settings_uses_safe_section_rendering_defaults(): void
    {
        $page = $this->createPage([
            'title' => 'Default section page',
            'slug' => 'default-section-page-'.uniqid(),
        ]);
        $section = $this->createSection($page, ['settings' => []]);
        $this->createPlacement($section, $this->createBlock([
            'content' => ['title' => 'Default section block', 'text' => 'Default settings content.'],
        ]));
        $this->storeSetting('general', 'default_locale', 'nl');

        $this
            ->get(route('cms.public.show', ['slug' => $page->slug]))
            ->assertOk()
            ->assertSee('Default settings content.')
            ->assertSee('rw-public-section--layout-standard', false)
            ->assertSee('rw-public-section--width-content', false)
            ->assertSee('rw-public-section--spacing-none', false)
            ->assertDontSee('rw-public-section--background-custom', false);
    }

    public function test_public_page_without_footer_sections_does_not_render_fallback_footer(): void
    {
        $page = $this->createPage([
            'title' => 'No footer page',
            'slug' => 'no-footer-page-'.uniqid(),
        ]);
        $section = $this->createSection($page, ['settings' => []]);
        $this->createPlacement($section, $this->createBlock([
            'content' => ['title' => 'No footer content', 'text' => 'This page has no footer section.'],
        ]));
        $this->storeSetting('general', 'default_locale', 'nl');

        $this
            ->get(route('cms.public.show', ['slug' => $page->slug]))
            ->assertOk()
            ->assertSee('This page has no footer section.')
            ->assertDontSee('<footer class="rw-public-footer"', false)
            ->assertDontSee('rw-public-footer-stack', false);
    }

    public function test_public_page_renders_feature_card_block_from_registry(): void
    {
        $page = $this->createPage([
            'title' => 'Feature card page',
            'slug' => 'feature-card-page-'.uniqid(),
        ]);
        $section = $this->createSection($page, [
            'settings' => ['layout_type' => 'grid'],
        ]);
        $this->createPlacement($section, $this->createBlock([
            'type' => 'feature_card',
            'content' => [
                'title' => 'Snelle implementatie',
                'text' => 'Een compacte kaart voor diensten of voordelen.',
            ],
        ]));
        $this->storeSetting('general', 'default_locale', 'nl');

        $this
            ->get(route('cms.public.show', ['slug' => $page->slug]))
            ->assertOk()
            ->assertSee('rw-public-block--feature-card', false)
            ->assertSee('Snelle implementatie')
            ->assertSee('Een compacte kaart voor diensten of voordelen.');
    }

    public function test_public_page_renders_testimonial_block_from_registry(): void
    {
        $page = $this->createPage([
            'title' => 'Testimonial page',
            'slug' => 'testimonial-page-'.uniqid(),
        ]);
        $section = $this->createSection($page, [
            'settings' => ['layout_type' => 'standard'],
        ]);
        $this->createPlacement($section, $this->createBlock([
            'type' => 'testimonial',
            'content' => [
                'text' => 'RwSoft bracht snel structuur in onze Laravel applicatie.',
                'source' => 'Klantcase Demo',
            ],
        ]));
        $this->storeSetting('general', 'default_locale', 'nl');

        $this
            ->get(route('cms.public.show', ['slug' => $page->slug]))
            ->assertOk()
            ->assertSee('rw-public-block--testimonial', false)
            ->assertSee('RwSoft bracht snel structuur in onze Laravel applicatie.')
            ->assertSee('Klantcase Demo');
    }

    public function test_public_page_renders_stats_block_from_registry(): void
    {
        $page = $this->createPage([
            'title' => 'Stats page',
            'slug' => 'stats-page-'.uniqid(),
        ]);
        $section = $this->createSection($page, [
            'settings' => ['layout_type' => 'grid'],
        ]);
        $this->createPlacement($section, $this->createBlock([
            'type' => 'stats',
            'content' => [
                'value' => '250',
                'suffix' => '+',
                'label' => 'tevreden klanten',
            ],
        ]));
        $this->storeSetting('general', 'default_locale', 'nl');

        $this
            ->get(route('cms.public.show', ['slug' => $page->slug]))
            ->assertOk()
            ->assertSee('rw-public-block--stats', false)
            ->assertSee('250')
            ->assertSee('rw-public-stats__suffix', false)
            ->assertSee('tevreden klanten');
    }

    public function test_public_page_renders_placement_spans_without_tailwind_grid_classes(): void
    {
        $page = $this->createPage([
            'title' => 'Span page',
            'slug' => 'span-page-'.uniqid(),
        ]);
        $section = $this->createSection($page, [
            'settings' => ['layout_type' => 'grid'],
        ]);
        $placement = $this->createPlacement($section, $this->createBlock([
            'content' => ['title' => 'Span block', 'text' => 'Span content.'],
        ]), [
            'mobile_span' => 12,
            'tablet_span' => 6,
            'desktop_span' => 4,
            'settings' => ['alignment' => 'center', 'content_alignment' => 'right'],
        ]);
        $rightPlacement = $this->createPlacement($section, $this->createBlock([
            'content' => ['title' => 'Right span block', 'text' => 'Right span content.'],
        ]), [
            'sort_order' => 1,
            'mobile_span' => 12,
            'tablet_span' => 12,
            'desktop_span' => 4,
            'settings' => ['alignment' => 'right'],
        ]);
        $this->storeSetting('general', 'default_locale', 'nl');

        $this
            ->get(route('cms.public.show', ['slug' => $page->slug]))
            ->assertOk()
            ->assertSee('rw-public-section--visible-all', false)
            ->assertSee('rw-public-section__grid', false)
            ->assertSee('data-cms-placement-id="'.$placement->id.'"', false)
            ->assertSee('rw-public-placement--place-center', false)
            ->assertSee('rw-public-placement--content-right', false)
            ->assertSee('--rw-public-placement-mobile-span: 12', false)
            ->assertSee('--rw-public-placement-tablet-span: 6', false)
            ->assertSee('--rw-public-placement-desktop-span: 4', false)
            ->assertSee('--rw-public-placement-tablet-start: 4', false)
            ->assertSee('--rw-public-placement-desktop-start: 5', false)
            ->assertSee('data-cms-placement-id="'.$rightPlacement->id.'"', false)
            ->assertSee('rw-public-placement--place-right', false)
            ->assertSee('--rw-public-placement-desktop-start: 9', false)
            ->assertDontSee('grid grid-cols-12', false)
            ->assertDontSee('lg:col-span-4', false)
            ->assertDontSee('hidden md:block', false);
    }

    public function test_public_page_renders_style_preset_classes_without_developer_css_draft(): void
    {
        $page = $this->createPage([
            'title' => 'Style preset page',
            'slug' => 'style-preset-page-'.uniqid(),
        ]);
        $section = $this->createSection($page, [
            'settings' => ['layout_type' => 'grid'],
        ]);
        $this->createPlacement($section, $this->createBlock([
            'content' => ['title' => 'Style block', 'text' => 'Style content.'],
        ]), [
            'style_config' => [
                'appearance' => [
                    'background_color' => '#f8fafc',
                    'padding' => 'md',
                    'radius' => 'lg',
                    'border' => 'subtle',
                    'shadow' => 'sm',
                ],
                'developer' => [
                    'css_source' => '.cms-placement-draft { color: hotpink; }',
                ],
            ],
        ]);
        $this->storeSetting('general', 'default_locale', 'nl');

        $this
            ->get(route('cms.public.show', ['slug' => $page->slug]))
            ->assertOk()
            ->assertSee('rw-public-placement--background-custom', false)
            ->assertSee('--rw-public-placement-background-color: #f8fafc;', false)
            ->assertSee('rw-public-placement--padding-md', false)
            ->assertSee('rw-public-placement--radius-lg', false)
            ->assertSee('rw-public-placement--border-subtle', false)
            ->assertSee('rw-public-placement--shadow-sm', false)
            ->assertDontSee('.cms-placement-draft', false)
            ->assertDontSee('hotpink', false);
    }

    public function test_public_page_renders_published_placement_style_revision_css(): void
    {
        $page = $this->createPage([
            'title' => 'Published style page',
            'slug' => 'published-style-page-'.uniqid(),
        ]);
        $section = $this->createSection($page, [
            'settings' => ['layout_type' => 'grid'],
        ]);
        $placement = $this->createPlacement($section, $this->createBlock([
            'content' => ['title' => 'Published style block', 'text' => 'Published style content.'],
        ]), [
            'style_config' => [
                'developer' => [
                    'css_source' => '.draft-placement-css { color: hotpink; }',
                ],
            ],
        ]);
        $secondPlacement = $this->createPlacement($section, $this->createBlock([
            'content' => ['title' => 'Second published style block', 'text' => 'Second published style content.'],
        ]), [
            'sort_order' => 1,
            'style_config' => [
                'developer' => [
                    'css_source' => '.second-draft-placement-css { color: hotpink; }',
                ],
            ],
        ]);
        $revision = $placement->styleRevisions()->create([
            'revision_number' => 1,
            'status' => 'published',
            'title' => 'Published placement CSS',
            'style_config' => [
                'developer' => [
                    'css_source' => '.published-placement-css { color: green; }',
                ],
            ],
            'css_source' => '.published-placement-css { color: green; }',
            'snapshot_hash' => hash('sha256', 'published-placement-css'),
            'metadata' => [],
            'published_at' => now(),
        ]);
        $placement->forceFill(['published_style_revision_id' => $revision->id])->save();
        $secondPlacement->forceFill(['published_style_revision_id' => $revision->id])->save();
        $this->storeSetting('general', 'default_locale', 'nl');

        $response = $this
            ->get(route('cms.public.show', ['slug' => $page->slug]))
            ->assertOk()
            ->assertSee('data-cms-placement-style-revision="'.$revision->id.'"', false)
            ->assertSee('.published-placement-css { color: green; }', false)
            ->assertSee('Second published style content.')
            ->assertDontSee('.draft-placement-css', false)
            ->assertDontSee('.second-draft-placement-css', false)
            ->assertDontSee('hotpink', false);
        $content = $response->getContent();
        $headEndPosition = strpos($content, '</head>');
        $stylePosition = strpos($content, 'data-cms-placement-style-revision="'.$revision->id.'"');

        $this->assertNotFalse($headEndPosition);
        $this->assertNotFalse($stylePosition);
        $this->assertLessThan($headEndPosition, $stylePosition);
        $this->assertSame(1, substr_count($content, 'data-cms-placement-style-revision="'.$revision->id.'"'));
        $this->assertSame(1, substr_count($content, '.published-placement-css { color: green; }'));
    }

    public function test_public_page_renders_header_grid_layout_config(): void
    {
        $layout = $this->createLayout([
            'name' => 'Header grid layout',
            'is_default' => false,
            'settings' => ['scroll_mode' => 'browser'],
        ]);
        $headerSection = $this->createSection($layout, [
            'zone' => 'header',
            'name' => 'Grid header',
            'settings' => ['spacing' => 'none', 'scroll_behavior' => 'sticky'],
        ]);
        $this->createPlacement($headerSection, $this->createBlock([
            'type' => 'site_logo',
            'content' => [
                'logo_url' => '/logo.svg',
                'alt_text' => 'Header logo',
                'link_url' => '/',
            ],
        ]), [
            'mobile_span' => 12,
            'tablet_span' => 6,
            'desktop_span' => 4,
            'layout_config' => [
                'desktop' => ['x' => 2, 'y' => 1, 'w' => 4, 'h' => 2],
                'tablet' => ['x' => 1, 'y' => 0, 'w' => 6, 'h' => 1],
                'mobile' => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 1],
            ],
        ]);
        $menu = $this->createMenu([
            'title' => 'Hoofdnavigatie',
            'location' => 'header',
            'placements' => ['header'],
        ]);
        $this->createMenuItem($menu, [
            'label' => 'Diensten',
            'url' => '/diensten',
            'translation_key' => 'header-services',
        ]);
        $menuPlacement = $this->createPlacement($headerSection, $this->createBlock([
            'type' => 'site_menu',
            'content' => [
                'cms_menu_id' => $menu->id,
            ],
        ]), [
            'sort_order' => 1,
            'mobile_span' => 12,
            'tablet_span' => 6,
            'desktop_span' => 8,
        ]);
        $template = CmsTemplate::query()->create([
            'name' => 'Header grid template',
            'locale' => 'nl',
            'layout_id' => $layout->id,
            'template_class' => 'page',
            'template_key' => 'page.detail',
            'is_default' => false,
            'is_active' => true,
            'cache_strategy' => 'inherit',
            'settings' => [],
        ]);
        $page = $this->createPage([
            'title' => 'Header grid page',
            'slug' => 'header-grid-page-'.uniqid(),
            'detail_template_id' => $template->id,
        ]);
        $this->createPlacement($this->createSection($page), $this->createBlock([
            'content' => ['title' => 'Pagina inhoud', 'text' => 'Content onder header.'],
        ]));
        $this->storeSetting('general', 'default_locale', 'nl');

        $this
            ->get(route('cms.public.show', ['slug' => $page->slug]))
            ->assertOk()
            ->assertSee('rw-public-section--zone-header', false)
            ->assertSee('rw-public-logo', false)
            ->assertSee('--rw-public-placement-desktop-start: 3', false)
            ->assertSee('--rw-public-placement-desktop-row: 2', false)
            ->assertSee('--rw-public-placement-desktop-row-span: 2', false)
            ->assertSee('--rw-public-placement-tablet-start: 2', false)
            ->assertSee('--rw-public-placement-mobile-span: 12', false)
            ->assertSee('data-cms-behavior="menu"', false)
            ->assertSee('data-rw-public-menu', false)
            ->assertSee('rw-public-menu__desktop', false)
            ->assertSee('rw-public-menu-toggle', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertSee('aria-controls="rw-public-site-menu-panel-'.$menuPlacement->id.'"', false)
            ->assertSee('id="rw-public-site-menu-panel-'.$menuPlacement->id.'"', false)
            ->assertSee('data-rw-public-menu-panel', false)
            ->assertSee('data-rw-public-menu-backdrop', false)
            ->assertSee('Hoofdnavigatie')
            ->assertSee('Diensten');
    }

    public function test_public_page_renders_composable_footer_menu_layout_config(): void
    {
        $layout = $this->createLayout([
            'name' => 'Footer grid layout',
            'is_default' => false,
            'settings' => ['scroll_mode' => 'browser'],
        ]);
        $footerSection = $this->createSection($layout, [
            'zone' => 'footer',
            'name' => 'Grid footer',
            'settings' => [
                'layout_type' => 'grid',
                'width_mode' => 'display',
                'spacing' => 'normal',
                'background_color' => null,
            ],
        ]);
        $logoPlacement = $this->createPlacement($footerSection, $this->createBlock([
            'type' => 'site_logo',
            'content' => [
                'alt_text' => 'Footer logo',
                'link_url' => '/nl',
            ],
        ]), [
            'mobile_span' => 12,
            'tablet_span' => 4,
            'desktop_span' => 4,
            'style_config' => [
                'devices' => [
                    'desktop' => [
                        'appearance' => [
                            'logo_size' => 'small',
                        ],
                    ],
                ],
            ],
            'layout_config' => [
                'desktop' => ['x' => 0, 'y' => 0, 'w' => 4, 'h' => 1],
                'tablet' => ['x' => 0, 'y' => 0, 'w' => 4, 'h' => 1],
                'mobile' => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 1],
            ],
        ]);
        $menu = $this->createMenu([
            'title' => 'Footernavigatie',
            'location' => 'footer',
            'placements' => ['footer'],
        ]);
        $this->createMenuItem($menu, [
            'label' => 'Contact',
            'url' => '/contact',
            'translation_key' => 'footer-contact',
        ]);
        $menuPlacement = $this->createPlacement($footerSection, $this->createBlock([
            'type' => 'site_menu',
            'content' => [
                'cms_menu_id' => $menu->id,
            ],
        ]), [
            'sort_order' => 1,
            'mobile_span' => 12,
            'tablet_span' => 8,
            'desktop_span' => 8,
            'layout_config' => [
                'desktop' => ['x' => 4, 'y' => 0, 'w' => 8, 'h' => 1],
                'tablet' => ['x' => 4, 'y' => 0, 'w' => 8, 'h' => 1],
                'mobile' => ['x' => 0, 'y' => 1, 'w' => 12, 'h' => 1],
            ],
        ]);
        $template = CmsTemplate::query()->create([
            'name' => 'Footer grid template',
            'locale' => 'nl',
            'layout_id' => $layout->id,
            'template_class' => 'page',
            'template_key' => 'page.detail',
            'is_default' => false,
            'is_active' => true,
            'cache_strategy' => 'inherit',
            'settings' => [],
        ]);
        $page = $this->createPage([
            'title' => 'Footer grid page',
            'slug' => 'footer-grid-page-'.uniqid(),
            'detail_template_id' => $template->id,
        ]);
        $this->createPlacement($this->createSection($page), $this->createBlock([
            'content' => ['title' => 'Pagina inhoud', 'text' => 'Content boven footer.'],
        ]));
        $this->storeSetting('general', 'default_locale', 'nl');

        $this
            ->get(route('cms.public.show', ['slug' => $page->slug]))
            ->assertOk()
            ->assertSee('rw-public-section--zone-footer', false)
            ->assertSee('rw-public-section--layout-grid', false)
            ->assertSee('rw-public-section--width-display', false)
            ->assertSee('data-cms-placement-id="'.$logoPlacement->id.'"', false)
            ->assertSee('data-cms-placement-id="'.$menuPlacement->id.'"', false)
            ->assertSee('rw-public-placement--logo-size-device', false)
            ->assertSee('--rw-public-placement-desktop-logo-max-height: 2.25rem', false)
            ->assertSee('rw-public-logo', false)
            ->assertSee('Footer logo')
            ->assertSee('data-cms-behavior="menu"', false)
            ->assertSee('data-rw-public-menu', false)
            ->assertSee('rw-public-menu__desktop', false)
            ->assertSee('rw-public-menu-toggle', false)
            ->assertSee('aria-controls="rw-public-site-menu-panel-'.$menuPlacement->id.'"', false)
            ->assertSee('data-rw-public-menu-panel', false)
            ->assertSee('data-rw-public-menu-backdrop', false)
            ->assertSee('Footernavigatie')
            ->assertSee('Contact')
            ->assertDontSee('<footer class="rw-public-footer"', false);
    }

    public function test_public_page_renders_content_menu_only_when_menu_allows_content_placement(): void
    {
        $page = $this->createPage([
            'title' => 'Content menu page',
            'slug' => 'content-menu-page-'.uniqid(),
        ]);
        $section = $this->createSection($page);
        $contentMenu = $this->createMenu([
            'title' => 'Content navigation',
            'placements' => ['content'],
        ]);
        $headerOnlyMenu = $this->createMenu([
            'title' => 'Header-only navigation',
            'placements' => ['header'],
        ]);

        $this->createMenuItem($contentMenu, [
            'label' => 'Content link',
            'url' => '/content-link',
            'translation_key' => 'content-menu-link',
        ]);
        $this->createMenuItem($headerOnlyMenu, [
            'label' => 'Header-only link',
            'url' => '/header-only-link',
            'translation_key' => 'header-menu-link',
        ]);
        $this->createPlacement($section, $this->createBlock([
            'type' => 'site_menu',
            'content' => [
                'cms_menu_id' => $contentMenu->id,
            ],
        ]));
        $this->createPlacement($section, $this->createBlock([
            'type' => 'site_menu',
            'content' => [
                'cms_menu_id' => $headerOnlyMenu->id,
            ],
        ]), ['sort_order' => 1]);
        $this->storeSetting('general', 'default_locale', 'nl');

        $this
            ->get(route('cms.public.show', ['slug' => $page->slug]))
            ->assertOk()
            ->assertSee('data-rw-public-menu', false)
            ->assertSee('Content link')
            ->assertDontSee('Header-only link');
    }

    public function test_public_site_logo_uses_media_alt_for_active_locale_when_block_alt_is_empty(): void
    {
        $media = $this->createMediaAsset([
            'path' => 'cms/media/site-logo.png',
            'filename' => 'site-logo.png',
            'original_filename' => 'site-logo.png',
            'mime_type' => 'image/png',
            'extension' => 'png',
            'alt_text' => 'Fallback logo alt',
        ]);
        $media->translations()->create([
            'locale' => 'nl',
            'alt_text' => 'Nederlandse media logo alt',
            'caption' => null,
        ]);
        $layout = $this->createLayout(['name' => 'Logo fallback layout']);
        $headerSection = $this->createSection($layout, ['zone' => 'header']);
        $this->createPlacement($headerSection, $this->createBlock([
            'type' => 'site_logo',
            'content' => [
                'media_asset_id' => $media->id,
                'alt_text' => '',
                'link_url' => '/',
            ],
        ]));
        $template = CmsTemplate::query()->create([
            'name' => 'Logo fallback template',
            'locale' => 'nl',
            'layout_id' => $layout->id,
            'template_class' => 'page',
            'template_key' => 'page.detail',
            'is_default' => false,
            'is_active' => true,
            'cache_strategy' => 'inherit',
            'settings' => [],
        ]);
        $page = $this->createPage([
            'title' => 'Logo fallback page',
            'slug' => 'logo-fallback-page-'.uniqid(),
            'detail_template_id' => $template->id,
        ]);
        $this->createPlacement($this->createSection($page), $this->createBlock([
            'content' => ['title' => 'Pagina inhoud', 'text' => 'Content onder logo.'],
        ]));
        $this->storeSetting('general', 'default_locale', 'nl');

        $this->assertSame(
            'Nederlandse media logo alt',
            app(PublicMediaUrl::class)->payload($media->fresh('translations'), 'nl')['alt_text'] ?? null
        );

        $this
            ->get(route('cms.public.show', ['slug' => $page->slug]))
            ->assertOk()
            ->assertSee('cms/media/site-logo.png', false)
            ->assertSee('alt="Nederlandse media logo alt"', false)
            ->assertDontSee('alt="Fallback logo alt"', false);
    }

    public function test_public_site_logo_block_alt_overrides_media_alt(): void
    {
        $media = $this->createMediaAsset([
            'path' => 'cms/media/site-logo-override.png',
            'filename' => 'site-logo-override.png',
            'original_filename' => 'site-logo-override.png',
            'mime_type' => 'image/png',
            'extension' => 'png',
            'alt_text' => 'Fallback logo alt',
        ]);
        $media->translations()->create([
            'locale' => 'nl',
            'alt_text' => 'Nederlandse media logo alt',
            'caption' => null,
        ]);
        $layout = $this->createLayout(['name' => 'Logo override layout']);
        $headerSection = $this->createSection($layout, ['zone' => 'header']);
        $this->createPlacement($headerSection, $this->createBlock([
            'type' => 'site_logo',
            'content' => [
                'media_asset_id' => $media->id,
                'alt_text' => 'Layout specifieke logo alt',
                'link_url' => '/',
            ],
        ]));
        $template = CmsTemplate::query()->create([
            'name' => 'Logo override template',
            'locale' => 'nl',
            'layout_id' => $layout->id,
            'template_class' => 'page',
            'template_key' => 'page.detail',
            'is_default' => false,
            'is_active' => true,
            'cache_strategy' => 'inherit',
            'settings' => [],
        ]);
        $page = $this->createPage([
            'title' => 'Logo override page',
            'slug' => 'logo-override-page-'.uniqid(),
            'detail_template_id' => $template->id,
        ]);
        $this->createPlacement($this->createSection($page), $this->createBlock([
            'content' => ['title' => 'Pagina inhoud', 'text' => 'Content onder logo.'],
        ]));
        $this->storeSetting('general', 'default_locale', 'nl');

        $this
            ->get(route('cms.public.show', ['slug' => $page->slug]))
            ->assertOk()
            ->assertSee('cms/media/site-logo-override.png', false)
            ->assertSee('alt="Layout specifieke logo alt"', false)
            ->assertDontSee('alt="Nederlandse media logo alt"', false);
    }

    public function test_public_page_uses_published_placeable_block_revision(): void
    {
        $blockDefinition = CmsPlaceableBlock::query()->create([
            'key' => 'published_notice',
            'name' => 'Published notice',
            'category' => 'content',
            'source' => 'user',
            'status' => 'published',
            'allowed_zones' => ['header'],
            'rendering_mode' => 'safe_blade',
            'renderer_key' => 'published_notice',
            'template_source' => '<strong class="published-variant">{{ block.title }}</strong>',
            'css_source' => '.published-variant { color: green; }',
            'schema' => [],
            'defaults' => [],
            'capabilities' => [],
            'behavior_config' => [],
            'context_config' => [],
            'published_at' => now(),
        ]);
        $publishedRevision = $blockDefinition->revisions()->create([
            'revision_number' => 1,
            'status' => 'published',
            'title' => 'Published notice',
            'category' => 'content',
            'source' => 'user',
            'allowed_zones' => ['header'],
            'rendering_mode' => 'safe_blade',
            'renderer_key' => 'published_notice',
            'template_source' => '<strong class="published-variant">{{ block.title }}</strong>',
            'css_source' => '.published-variant { color: green; }',
            'schema' => [],
            'defaults' => [],
            'capabilities' => [],
            'behavior_config' => [],
            'context_config' => [],
            'snapshot_hash' => hash('sha256', 'published'),
            'metadata' => [],
            'published_at' => now(),
        ]);
        $draftRevision = $blockDefinition->revisions()->create([
            'revision_number' => 2,
            'status' => 'draft',
            'title' => 'Draft notice',
            'category' => 'content',
            'source' => 'user',
            'allowed_zones' => ['header'],
            'rendering_mode' => 'safe_blade',
            'renderer_key' => 'published_notice',
            'template_source' => '<strong class="draft-variant">{{ block.title }}</strong>',
            'css_source' => '.draft-variant { color: red; }',
            'schema' => [],
            'defaults' => [],
            'capabilities' => [],
            'behavior_config' => [],
            'context_config' => [],
            'snapshot_hash' => hash('sha256', 'draft'),
            'metadata' => [],
            'published_at' => null,
        ]);
        $layout = $this->createLayout([
            'name' => 'Variant render layout',
            'is_default' => false,
            'settings' => ['scroll_mode' => 'browser'],
        ]);
        $headerSection = $this->createSection($layout, [
            'zone' => 'header',
            'name' => 'Variant header',
            'settings' => ['scroll_behavior' => 'sticky'],
        ]);
        $this->createPlacement($headerSection, $this->createBlock([
            'type' => 'published_notice',
            'cms_placeable_block_id' => $blockDefinition->id,
            'placeable_block_revision_id' => $draftRevision->id,
            'content' => ['title' => 'Variant titel', 'text' => 'Registry tekst'],
        ]));
        $this->createPlacement($headerSection, $this->createBlock([
            'type' => 'published_notice',
            'cms_placeable_block_id' => $blockDefinition->id,
            'placeable_block_revision_id' => $draftRevision->id,
            'content' => ['title' => 'Tweede variant titel', 'text' => 'Tweede registry tekst'],
        ]), ['sort_order' => 1]);
        $template = CmsTemplate::query()->create([
            'name' => 'Variant render template',
            'locale' => 'nl',
            'layout_id' => $layout->id,
            'template_class' => 'page',
            'template_key' => 'page.detail',
            'is_default' => false,
            'is_active' => true,
            'cache_strategy' => 'inherit',
            'settings' => [],
        ]);
        $page = $this->createPage([
            'title' => 'Variant render page',
            'slug' => 'variant-render-page-'.uniqid(),
            'detail_template_id' => $template->id,
        ]);
        $this->storeSetting('general', 'default_locale', 'nl');

        $response = $this
            ->get(route('cms.public.show', ['slug' => $page->slug]))
            ->assertOk()
            ->assertSee('published-variant', false)
            ->assertSee('Variant titel')
            ->assertSee('Tweede variant titel')
            ->assertSee('.published-variant { color: green; }', false)
            ->assertSee('data-cms-placeable-block-revision="'.$publishedRevision->id.'"', false)
            ->assertDontSee('draft-variant', false);
        $content = $response->getContent();
        $headEndPosition = strpos($content, '</head>');
        $stylePosition = strpos($content, 'data-cms-placeable-block-revision="'.$publishedRevision->id.'"');

        $this->assertNotFalse($headEndPosition);
        $this->assertNotFalse($stylePosition);
        $this->assertLessThan($headEndPosition, $stylePosition);
        $this->assertSame(1, substr_count($content, 'data-cms-placeable-block-revision="'.$publishedRevision->id.'"'));
        $this->assertSame(1, substr_count($content, '.published-variant { color: green; }'));
    }

    public function test_public_page_renders_video_block_with_safe_embed_url(): void
    {
        $page = $this->createPage([
            'title' => 'Video page',
            'slug' => 'video-page-'.uniqid(),
        ]);
        $section = $this->createSection($page, [
            'settings' => ['layout_type' => 'standard'],
        ]);
        $this->createPlacement($section, $this->createBlock([
            'type' => 'video',
            'content' => [
                'title' => 'Intro video',
                'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            ],
        ]));
        $this->storeSetting('general', 'default_locale', 'nl');

        $this
            ->get(route('cms.public.show', ['slug' => $page->slug]))
            ->assertOk()
            ->assertSee('rw-public-block--video', false)
            ->assertSee('Intro video')
            ->assertSee('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', false)
            ->assertSee('<iframe', false);
    }

    public function test_public_page_does_not_render_video_if_url_is_not_allowed(): void
    {
        $page = $this->createPage([
            'title' => 'Unsafe video page',
            'slug' => 'unsafe-video-page-'.uniqid(),
        ]);
        $section = $this->createSection($page);
        $this->createPlacement($section, $this->createBlock([
            'type' => 'video',
            'content' => [
                'title' => 'Blocked video',
                'video_url' => 'https://example.com/embed/not-allowed',
            ],
        ]));
        $this->storeSetting('general', 'default_locale', 'nl');

        $this
            ->get(route('cms.public.show', ['slug' => $page->slug]))
            ->assertOk()
            ->assertSee('rw-public-block--video', false)
            ->assertSee('Blocked video')
            ->assertDontSee('<iframe', false)
            ->assertDontSee('https://example.com/embed/not-allowed', false);
    }

    public function test_public_page_renders_logo_strip_block_with_public_media_only(): void
    {
        $firstLogo = $this->createMediaAsset([
            'path' => 'cms/media/logo-one.png',
            'filename' => 'logo-one.png',
            'original_filename' => 'logo-one.png',
            'mime_type' => 'image/png',
            'extension' => 'png',
            'alt_text' => 'Logo een',
            'width' => 320,
            'height' => 120,
        ]);
        $secondLogo = $this->createMediaAsset([
            'path' => 'cms/media/logo-two.png',
            'filename' => 'logo-two.png',
            'original_filename' => 'logo-two.png',
            'mime_type' => 'image/png',
            'extension' => 'png',
            'alt_text' => 'Logo twee',
            'width' => 280,
            'height' => 100,
        ]);
        $privateLogo = $this->createMediaAsset([
            'path' => 'cms/media/private-logo.png',
            'filename' => 'private-logo.png',
            'original_filename' => 'private-logo.png',
            'visibility' => 'private',
            'alt_text' => 'Prive logo',
        ]);
        $page = $this->createPage([
            'title' => 'Logo strip page',
            'slug' => 'logo-strip-page-'.uniqid(),
        ]);
        $section = $this->createSection($page, [
            'settings' => ['layout_type' => 'standard'],
        ]);
        $this->createPlacement($section, $this->createBlock([
            'type' => 'logo_strip',
            'content' => [
                'title' => 'Onze partners',
                'media_asset_ids' => [$firstLogo->id, $privateLogo->id, 999999, $secondLogo->id],
            ],
        ]));
        $this->storeSetting('general', 'default_locale', 'nl');

        $this
            ->get(route('cms.public.show', ['slug' => $page->slug]))
            ->assertOk()
            ->assertSee('rw-public-block--logo-strip', false)
            ->assertSee('Onze partners')
            ->assertSee('Logo een')
            ->assertSee('Logo twee')
            ->assertSee('cms/media/logo-one.png', false)
            ->assertSee('cms/media/logo-two.png', false)
            ->assertDontSee('Prive logo')
            ->assertDontSee('private-logo.png', false);
    }

    public function test_public_page_renders_automatic_and_extra_json_ld(): void
    {
        $page = $this->createPage([
            'title' => 'Schema pagina',
            'slug' => 'schema-pagina-'.uniqid(),
            'short_description' => 'Schema intro.',
            'settings' => [
                'structured_data_extra' => json_encode([
                    '@type' => 'FAQPage',
                    'name' => '{{ page.title }}',
                ], JSON_THROW_ON_ERROR),
            ],
        ]);
        $this->storeSetting('general', 'default_locale', 'nl');

        $this
            ->get(route('cms.public.show', ['slug' => $page->slug]))
            ->assertOk()
            ->assertSee('<script type="application/ld+json">', false)
            ->assertSee('"@type": "WebPage"', false)
            ->assertSee('"@type": "FAQPage"', false)
            ->assertSee('"name": "Schema pagina"', false);
    }

    public function test_system_header_and_footer_blocks_keep_head_seo_output(): void
    {
        $this->storeSetting('general', 'default_locale', 'nl');
        $this->storeSetting('general', 'site_name', 'System layout site');
        $layout = $this->createLayout([
            'is_default' => true,
            'settings' => ['scroll_mode' => 'internal'],
        ]);
        $headerSection = $this->createSection($layout, [
            'zone' => 'header',
            'settings' => ['scroll_behavior' => 'sticky'],
        ]);
        $this->createPlacement($headerSection, $this->createBlock([
            'type' => 'site_header',
            'content' => [],
        ]));
        $footerSection = $this->createSection($layout, [
            'zone' => 'footer',
            'settings' => ['scroll_behavior' => 'sticky'],
        ]);
        $this->createPlacement($footerSection, $this->createBlock([
            'type' => 'site_footer',
            'content' => [],
        ]));
        $page = $this->createPage([
            'title' => 'System blocks page',
            'slug' => 'system-blocks-'.uniqid(),
            'seo_description' => 'System blocks SEO description.',
            'settings' => [
                'structured_data_extra' => json_encode([
                    '@type' => 'FAQPage',
                    'name' => '{{ page.title }}',
                ], JSON_THROW_ON_ERROR),
            ],
        ]);

        $this
            ->get(route('cms.public.show', ['slug' => $page->slug]))
            ->assertOk()
            ->assertSee('rw-public-section--zone-header', false)
            ->assertSee('rw-public-section--zone-footer', false)
            ->assertSee('rw-public-header', false)
            ->assertSee('rw-public-footer', false)
            ->assertSee('<meta name="description" content="System blocks SEO description.">', false)
            ->assertSee('<script type="application/ld+json">', false)
            ->assertSee('"@type": "FAQPage"', false)
            ->assertSee('"name": "System blocks page"', false);
    }

    public function test_layout_head_and_body_end_code_blocks_render_without_visible_wrappers(): void
    {
        $this->storeSetting('general', 'default_locale', 'nl');
        $layout = $this->createLayout([
            'is_default' => true,
            'settings' => ['scroll_mode' => 'internal'],
        ]);
        $headSection = $this->createSection($layout, ['zone' => 'head']);
        $this->createPlacement($headSection, $this->createBlock([
            'type' => 'site_head',
            'content' => [],
        ]));
        $this->createPlacement($headSection, $this->createBlock([
            'type' => 'custom_head_code',
            'content' => ['code' => '<meta name="x-test-head" content="ok">'],
        ]), ['sort_order' => 1]);
        $bodyEndSection = $this->createSection($layout, ['zone' => 'body_end']);
        $this->createPlacement($bodyEndSection, $this->createBlock([
            'type' => 'custom_body_end_code',
            'content' => ['code' => '<script>window.__cmsBodyEnd = true;</script>'],
        ]));
        $template = CmsTemplate::query()->create([
            'name' => 'Head block template',
            'locale' => 'nl',
            'layout_id' => $layout->id,
            'template_class' => 'page',
            'template_key' => 'page.detail',
            'is_default' => false,
            'is_active' => true,
            'cache_strategy' => 'inherit',
            'settings' => [],
        ]);
        $page = $this->createPage([
            'title' => 'Head block page',
            'slug' => 'head-block-page-'.uniqid(),
            'detail_template_id' => $template->id,
            'seo_description' => 'Head block SEO description.',
        ]);

        $response = $this
            ->get(route('cms.public.show', ['slug' => $page->slug]))
            ->assertOk()
            ->assertSee('<meta name="description" content="Head block SEO description.">', false)
            ->assertSee('<meta name="x-test-head" content="ok">', false)
            ->assertSee('<script>window.__cmsBodyEnd = true;</script>', false)
            ->assertDontSee('data-cms-section-id="'.$headSection->id.'"', false);

        $content = $response->getContent();
        $headCodePosition = strpos($content, '<meta name="x-test-head" content="ok">');
        $bodyCodePosition = strpos($content, '<script>window.__cmsBodyEnd = true;</script>');

        $this->assertNotFalse($headCodePosition);
        $this->assertNotFalse($bodyCodePosition);
        $this->assertLessThan(strpos($content, '</head>'), $headCodePosition);
        $this->assertLessThan(strpos($content, '</body>'), $bodyCodePosition);
    }

    public function test_layout_renders_ordered_locked_head_stack_with_custom_snippets(): void
    {
        $this->storeSetting('general', 'default_locale', 'nl');
        $layout = $this->createLayout([
            'is_default' => true,
            'settings' => ['scroll_mode' => 'browser'],
        ]);
        $headSection = $this->createSection($layout, ['zone' => 'head']);
        $this->createPlacement($headSection, $this->createBlock([
            'type' => 'site_head_meta',
            'content' => [],
        ]), ['sort_order' => 0]);
        $this->createPlacement($headSection, $this->createBlock([
            'type' => 'custom_head_code',
            'content' => ['code' => '<meta name="x-before-favicons" content="ok">'],
        ]), ['sort_order' => 1]);
        $this->createPlacement($headSection, $this->createBlock([
            'type' => 'site_head_favicons',
            'content' => [],
        ]), ['sort_order' => 2]);
        $this->createPlacement($headSection, $this->createBlock([
            'type' => 'site_head_system_assets',
            'content' => [],
        ]), ['sort_order' => 3]);
        $this->createPlacement($headSection, $this->createBlock([
            'type' => 'custom_head_code',
            'content' => ['code' => '<meta name="x-before-theme" content="ok">'],
        ]), ['sort_order' => 4]);
        $this->createPlacement($headSection, $this->createBlock([
            'type' => 'site_head_theme',
            'content' => [],
        ]), ['sort_order' => 5]);
        $template = CmsTemplate::query()->create([
            'name' => 'Ordered head template',
            'locale' => 'nl',
            'layout_id' => $layout->id,
            'template_class' => 'page',
            'template_key' => 'page.detail',
            'is_default' => false,
            'is_active' => true,
            'cache_strategy' => 'inherit',
            'settings' => [],
        ]);
        $page = $this->createPage([
            'title' => 'Ordered head page',
            'slug' => 'ordered-head-page-'.uniqid(),
            'detail_template_id' => $template->id,
            'seo_description' => 'Ordered head SEO description.',
        ]);

        $content = $this
            ->get(route('cms.public.show', ['slug' => $page->slug]))
            ->assertOk()
            ->assertSee('<meta name="description" content="Ordered head SEO description.">', false)
            ->assertSee('<meta name="x-before-favicons" content="ok">', false)
            ->assertSee('<meta name="x-before-theme" content="ok">', false)
            ->getContent();

        $metaPosition = strpos($content, '<meta name="description" content="Ordered head SEO description.">');
        $firstCustomPosition = strpos($content, '<meta name="x-before-favicons" content="ok">');
        $systemAssetsPosition = strpos($content, '<link rel="preconnect" href="https://fonts.bunny.net">');
        $secondCustomPosition = strpos($content, '<meta name="x-before-theme" content="ok">');

        $this->assertNotFalse($metaPosition);
        $this->assertNotFalse($firstCustomPosition);
        $this->assertNotFalse($systemAssetsPosition);
        $this->assertNotFalse($secondCustomPosition);
        $this->assertLessThan($firstCustomPosition, $metaPosition);
        $this->assertLessThan($systemAssetsPosition, $firstCustomPosition);
        $this->assertLessThan($secondCustomPosition, $systemAssetsPosition);
    }

    public function test_layout_footer_block_controls_sticky_footer_shell(): void
    {
        $this->storeSetting('general', 'default_locale', 'nl');
        $layout = $this->createLayout(['is_default' => true]);
        $footerSection = $this->createSection($layout, [
            'zone' => 'footer',
            'settings' => ['scroll_behavior' => 'normal'],
        ]);
        $this->createPlacement($footerSection, $this->createBlock([
            'content' => ['title' => 'Scroll footer block', 'text' => 'Footer scrollt mee.'],
        ]));
        $template = $this->createPageDetailTemplate($layout);
        $page = $this->createPage([
            'slug' => 'scroll-footer-'.uniqid(),
            'detail_template_id' => $template->id,
        ]);

        $response = $this
            ->get(route('cms.public.show', ['slug' => $page->slug]))
            ->assertOk()
            ->assertSee('Scroll footer block')
            ->assertDontSee('rw-public-shell--sticky-footer', false);

        $content = $response->getContent();
        $mainEndPosition = strpos($content, '</main>');
        $footerBlockPosition = strpos($content, 'Scroll footer block');

        $this->assertNotFalse($mainEndPosition);
        $this->assertNotFalse($footerBlockPosition);
        $this->assertGreaterThan($mainEndPosition, $footerBlockPosition);

        $layout = $this->createLayout([
            'is_default' => true,
            'settings' => ['scroll_mode' => 'internal'],
        ]);
        $footerSection = $this->createSection($layout, [
            'zone' => 'footer',
            'settings' => ['scroll_behavior' => 'sticky'],
        ]);
        $this->createPlacement($footerSection, $this->createBlock([
            'content' => ['title' => 'Sticky footer block', 'text' => 'Footer blijft staan.'],
        ]));
        $template = $this->createPageDetailTemplate($layout);
        $page = $this->createPage([
            'slug' => 'sticky-footer-'.uniqid(),
            'detail_template_id' => $template->id,
        ]);

        $this
            ->get(route('cms.public.show', ['slug' => $page->slug]))
            ->assertOk()
            ->assertSee('Sticky footer block')
            ->assertSee('rw-public-shell--sticky-footer', false);

        $layout = $this->createLayout([
            'is_default' => true,
            'settings' => ['scroll_mode' => 'internal'],
        ]);
        $footerSection = $this->createSection($layout, [
            'zone' => 'footer',
            'settings' => ['scroll_behavior' => 'auto_hide'],
        ]);
        $this->createPlacement($footerSection, $this->createBlock([
            'content' => ['title' => 'Auto-hide footer block', 'text' => 'Footer verbergt automatisch.'],
        ]));
        $template = $this->createPageDetailTemplate($layout);
        $page = $this->createPage([
            'slug' => 'auto-hide-footer-'.uniqid(),
            'detail_template_id' => $template->id,
        ]);

        $this
            ->get(route('cms.public.show', ['slug' => $page->slug]))
            ->assertOk()
            ->assertSee('Auto-hide footer block')
            ->assertSee('rw-public-shell--sticky-footer', false)
            ->assertSee('rw-public-section--scroll-auto-hide', false)
            ->assertSee('data-cms-behavior="auto-hide-edge"', false)
            ->assertSee('&quot;edge&quot;:&quot;footer&quot;', false);
    }

    public function test_sticky_header_section_stays_above_auto_hide_header_section(): void
    {
        $this->storeSetting('general', 'default_locale', 'nl');
        $layout = $this->createLayout([
            'is_default' => true,
            'settings' => ['scroll_mode' => 'browser'],
        ]);
        $stickyHeaderSection = $this->createSection($layout, [
            'zone' => 'header',
            'name' => 'Always visible header',
            'sort_order' => 0,
            'settings' => ['scroll_behavior' => 'sticky'],
        ]);
        $this->createPlacement($stickyHeaderSection, $this->createBlock([
            'content' => ['title' => 'Always visible header block', 'text' => 'Sticky header remains visible.'],
        ]));
        $autoHideHeaderSection = $this->createSection($layout, [
            'zone' => 'header',
            'name' => 'Auto hide header',
            'sort_order' => 1,
            'settings' => ['scroll_behavior' => 'auto_hide'],
        ]);
        $this->createPlacement($autoHideHeaderSection, $this->createBlock([
            'content' => ['title' => 'Auto hide header block', 'text' => 'Auto hide header moves below sticky header.'],
        ]));
        $template = $this->createPageDetailTemplate($layout);
        $page = $this->createPage([
            'slug' => 'sticky-auto-hide-header-'.uniqid(),
            'detail_template_id' => $template->id,
        ]);

        $response = $this
            ->get(route('cms.public.show', ['slug' => $page->slug]))
            ->assertOk()
            ->assertSee('rw-public-header-stack--sticky', false)
            ->assertSee('rw-public-section--scroll-sticky', false)
            ->assertSee('rw-public-section--scroll-auto-hide', false)
            ->assertSee('data-cms-behavior="auto-hide-edge"', false)
            ->assertSee('&quot;edge&quot;:&quot;header&quot;', false)
            ->assertSee('Always visible header block')
            ->assertSee('Auto hide header block');

        $content = $response->getContent();
        $stickyPosition = strpos($content, 'Always visible header block');
        $autoHidePosition = strpos($content, 'Auto hide header block');

        $this->assertNotFalse($stickyPosition);
        $this->assertNotFalse($autoHidePosition);
        $this->assertLessThan($autoHidePosition, $stickyPosition);
    }

    public function test_post_index_uses_database_public_text_translations(): void
    {
        $this->storeSetting('general', 'default_locale', 'en');
        $this->storeSetting('general', 'site_name', 'Public text site');
        $this->storePublicText('post_index', 'title', 'en', 'Newsroom');
        $this->storePublicText('post_index', 'lead', 'en', 'Latest public updates.');
        $this->storePublicText('post_index', 'read_more', 'en', 'Continue reading');
        $this->storePublicText('post_index', 'seo_title', 'en', 'Newsroom SEO');
        $this->storePublicText('post_index', 'seo_description', 'en', 'Public SEO description.');

        $post = $this->createPost([
            'title' => 'Translated public post',
            'slug' => 'translated-public-post-'.uniqid(),
            'locale' => 'en',
            'excerpt' => 'Post excerpt.',
        ]);

        $this
            ->get('/en/posts')
            ->assertOk()
            ->assertSee('Newsroom')
            ->assertSee('Latest public updates.')
            ->assertSee('Continue reading')
            ->assertSee('Newsroom SEO')
            ->assertSee('Public SEO description.')
            ->assertSee($post->title)
            ->assertDontSee('Berichten')
            ->assertDontSee('Lees meer');
    }

    public function test_noindex_page_does_not_render_json_ld(): void
    {
        $page = $this->createPage([
            'title' => 'Noindex schema',
            'slug' => 'noindex-schema-'.uniqid(),
            'noindex' => true,
        ]);
        $this->storeSetting('general', 'default_locale', 'nl');

        $this
            ->get(route('cms.public.show', ['slug' => $page->slug]))
            ->assertOk()
            ->assertDontSee('application/ld+json', false);
    }

    private function storePublicText(string $group, string $key, string $locale, string $value): void
    {
        $publicText = CmsPublicText::query()->updateOrCreate(
            ['group' => $group, 'key' => $key],
            [
                'label' => $group.'.'.$key,
                'description' => null,
                'default_value' => $value,
                'type' => 'text',
                'is_system' => true,
                'sort_order' => 0,
            ],
        );

        $publicText->translations()->updateOrCreate(
            ['locale' => $locale],
            ['value' => $value],
        );
    }

    public function test_slug_page_renders_only_published_pages(): void
    {
        $page = $this->createPage([
            'title' => 'Over ons',
            'slug' => 'over-ons-'.uniqid(),
        ]);
        $section = $this->createSection($page);
        $this->createPlacement($section, $this->createBlock([
            'content' => ['title' => 'Missie', 'text' => 'Wij bouwen software.'],
        ]));
        $this->storeSetting('general', 'default_locale', 'nl');

        $this
            ->get(route('cms.public.show', ['slug' => $page->slug]))
            ->assertOk()
            ->assertSee('Over ons')
            ->assertSee('Wij bouwen software.')
            ->assertDontSee('component&quot;:&quot;Public/Cms/Page', false);

        $draft = $this->createPage([
            'title' => 'Concept',
            'slug' => 'concept-'.uniqid(),
            'status' => 'draft',
        ]);

        $this
            ->get(route('cms.public.show', ['slug' => $draft->slug]))
            ->assertNotFound();
    }

    public function test_nested_page_renders_only_with_full_public_path(): void
    {
        $this->storeSetting('general', 'default_locale', 'np');
        $parent = $this->createPage([
            'title' => 'Ouderpagina',
            'slug' => 'ouder-'.uniqid(),
            'locale' => 'np',
        ]);
        $child = $this->createPage([
            'parent_id' => $parent->id,
            'title' => 'Kindpagina',
            'slug' => 'kind-'.uniqid(),
            'locale' => 'np',
        ]);
        $childSection = $this->createSection($child);
        $this->createPlacement($childSection, $this->createBlock([
            'content' => ['title' => 'Kind intro', 'text' => 'Nested content.'],
        ]));
        $draftParent = $this->createPage([
            'title' => 'Concept ouder',
            'slug' => 'concept-ouder-'.uniqid(),
            'locale' => 'np',
            'status' => 'draft',
        ]);
        $hiddenChild = $this->createPage([
            'parent_id' => $draftParent->id,
            'title' => 'Verborgen kind',
            'slug' => 'verborgen-kind-'.uniqid(),
            'locale' => 'np',
        ]);
        $header = $this->createMenu(['location' => 'header', 'locale' => 'np']);
        $this->createMenuItem($header, [
            'type' => 'page',
            'label' => 'Kind',
            'cms_page_id' => $child->id,
        ]);
        $this->createMenuItem($header, [
            'type' => 'page',
            'label' => 'Verborgen kind',
            'cms_page_id' => $hiddenChild->id,
            'sort_order' => 1,
        ]);

        $this
            ->get('/'.$parent->slug.'/'.$child->slug)
            ->assertOk()
            ->assertSee('Kindpagina')
            ->assertSee('Nested content.')
            ->assertSee('/'.$parent->slug.'/'.$child->slug, false)
            ->assertDontSee('Verborgen kind');

        $this->get(route('cms.public.show', ['slug' => $child->slug]))->assertNotFound();
        $this->get('/'.$draftParent->slug.'/'.$hiddenChild->slug)->assertNotFound();
    }

    public function test_breadcrumb_block_renders_category_landing_page_hierarchy(): void
    {
        $this->storeSetting('general', 'default_locale', 'nl');
        $rootSlug = 'breadcrumb-root-'.uniqid();
        $childSlug = 'breadcrumb-child-'.uniqid();
        $grandchildSlug = 'breadcrumb-grandchild-'.uniqid();

        $rootPage = $this->createPage([
            'title' => 'Inzichten test',
            'slug' => $rootSlug,
        ]);
        $childPage = $this->createPage([
            'parent_id' => $rootPage->id,
            'title' => 'Strategie test',
            'slug' => $childSlug,
        ]);
        $grandchildPage = $this->createPage([
            'parent_id' => $childPage->id,
            'title' => 'Positionering test',
            'slug' => $grandchildSlug,
        ]);
        $template = $this->createPageDetailTemplate(
            $this->createLayout(['is_default' => true]),
            true,
        );
        $grandchildPage->forceFill(['detail_template_id' => $template->id])->save();
        $breadcrumbSection = $this->createSection($grandchildPage);
        $this->createPlacement($breadcrumbSection, $this->createBlock([
            'type' => 'breadcrumb',
            'content' => ['show_current' => true, 'separator' => '•'],
        ]));

        $rootCategory = $this->createCategory([
            'title' => 'Inzichten test',
            'slug' => $rootSlug,
            'landing_page_id' => $rootPage->id,
        ]);
        $childCategory = $this->createCategory([
            'parent_id' => $rootCategory->id,
            'title' => 'Strategie test',
            'slug' => $childSlug,
            'landing_page_id' => $childPage->id,
        ]);
        $this->createCategory([
            'parent_id' => $childCategory->id,
            'title' => 'Positionering test',
            'slug' => $grandchildSlug,
            'landing_page_id' => $grandchildPage->id,
        ]);

        $this
            ->get('/'.$rootSlug.'/'.$childSlug.'/'.$grandchildSlug)
            ->assertOk()
            ->assertSee('<nav class="rw-public-breadcrumb"', false)
            ->assertSee('Home')
            ->assertSee('Inzichten test')
            ->assertSee('Strategie test')
            ->assertSee('Positionering test')
            ->assertSee('<span class="rw-public-breadcrumb__separator" aria-hidden="true">•</span>', false)
            ->assertSee('href="/nl/'.$rootSlug.'"', false)
            ->assertSee('href="/nl/'.$rootSlug.'/'.$childSlug.'"', false)
            ->assertSee('aria-current="page">Positionering test</span>', false);
    }

    public function test_public_page_includes_header_and_footer_navigation(): void
    {
        $home = $this->createPage([
            'title' => 'Home navigatie',
            'slug' => 'home-navigatie-'.uniqid(),
            'locale' => 'zz',
            'is_home' => true,
        ]);
        $about = $this->createPage([
            'title' => 'Over navigatie',
            'slug' => 'over-navigatie-'.uniqid(),
            'locale' => 'zz',
        ]);
        $contact = $this->createPage([
            'title' => 'Contact navigatie',
            'slug' => 'contact-navigatie-'.uniqid(),
            'locale' => 'zz',
        ]);
        $this->storeSetting('general', 'default_locale', 'zz');
        $this->storeSetting('general', 'homepage_id', $home->id);

        $header = $this->createMenu(['location' => 'header', 'locale' => 'zz']);
        $parent = $this->createMenuItem($header, [
            'type' => 'page',
            'label' => 'Over',
            'cms_page_id' => $about->id,
            'sort_order' => 1,
        ]);
        $this->createMenuItem($header, [
            'parent_id' => $parent->id,
            'type' => 'custom',
            'label' => 'Team',
            'url' => '/team',
            'sort_order' => 2,
        ]);
        $this->createMenuItem($header, [
            'type' => 'page',
            'label' => 'Verborgen',
            'cms_page_id' => $contact->id,
            'is_active' => false,
            'sort_order' => 3,
        ]);

        $footer = $this->createMenu(['location' => 'footer', 'locale' => 'zz']);
        $this->createMenuItem($footer, [
            'type' => 'page',
            'label' => 'Contact',
            'cms_page_id' => $contact->id,
        ]);

        $this
            ->get(route('cms.public.home'))
            ->assertOk()
            ->assertSee('Over')
            ->assertSee('/'.$about->slug, false)
            ->assertSee('Team')
            ->assertSee('Contact')
            ->assertSee('/'.$contact->slug, false)
            ->assertDontSee('Verborgen');
    }

    public function test_public_navigation_payload_contains_titles_and_items_per_location(): void
    {
        $this->ensureLanguages(['nl', 'en']);
        $this->storeSetting('general', 'default_locale', 'nl');
        $this->storeSetting('general', 'multilingual_enabled', true);

        $this->createPage([
            'title' => 'Home English',
            'slug' => 'home-en-'.uniqid(),
            'locale' => 'en',
            'is_home' => true,
        ]);

        $header = $this->createMenu(['location' => 'header', 'title' => 'Header fallback']);
        $header->translations()->create(['locale' => 'nl', 'title' => 'Hoofdnavigatie']);
        $header->translations()->create(['locale' => 'en', 'title' => 'Main navigation']);
        $this->createMenuItem($header, [
            'locale' => 'en',
            'type' => 'custom',
            'label' => 'Services',
            'url' => '/en/services',
            'translation_key' => 'navigation-payload-'.uniqid(),
        ]);

        $footer = $this->createMenu(['location' => 'footer', 'title' => 'Footer fallback']);
        $footer->translations()->create(['locale' => 'nl', 'title' => 'Voetnavigatie']);

        $this
            ->get('/en')
            ->assertOk()
            ->assertViewHas('navigation', function (array $navigation): bool {
                $this->assertSame('Main navigation', $navigation['header']['title'] ?? null);
                $this->assertSame('Services', $navigation['header']['items'][0]['label'] ?? null);
                $this->assertSame('Voetnavigatie', $navigation['footer']['title'] ?? null);
                $this->assertSame([], $navigation['footer']['items'] ?? null);

                return true;
            });
    }

    public function test_navigation_uses_menu_item_language_variant_and_resolves_page_translation(): void
    {
        $this->ensureLanguages(['ma', 'mb']);
        $this->storeSetting('general', 'default_locale', 'ma');
        $this->storeSetting('general', 'multilingual_enabled', true);

        $home = $this->createPage([
            'title' => 'Home',
            'slug' => 'home-'.uniqid(),
            'locale' => 'mb',
            'is_home' => true,
        ]);
        $this->storeSetting('general', 'homepage_id', $home->id);

        $translationKey = 'menu-page-'.uniqid();
        $sourcePage = $this->createPage([
            'title' => 'Diensten',
            'slug' => 'diensten-'.uniqid(),
            'locale' => 'ma',
            'translation_key' => $translationKey,
        ]);
        $translatedPage = $this->createPage([
            'title' => 'Services',
            'slug' => 'services-'.uniqid(),
            'locale' => 'mb',
            'translation_key' => $translationKey,
            'translated_from_page_id' => $sourcePage->id,
        ]);

        $menu = $this->createMenu(['location' => 'header', 'locale' => 'ma']);
        $item = $this->createMenuItem($menu, [
            'locale' => 'ma',
            'translation_key' => 'menu-item-'.uniqid(),
            'type' => 'page',
            'label' => 'Diensten',
            'cms_page_id' => $sourcePage->id,
        ]);
        $this->createMenuItem($menu, [
            'locale' => 'mb',
            'translation_key' => $item->translation_key,
            'translated_from_menu_item_id' => $item->id,
            'type' => 'page',
            'label' => 'Services',
            'cms_page_id' => $translatedPage->id,
        ]);

        $this
            ->get('/mb')
            ->assertOk()
            ->assertSee('Services')
            ->assertSee('/mb/'.$translatedPage->slug, false)
            ->assertDontSee('Diensten');
    }

    public function test_navigation_uses_locale_specific_menu_item_records(): void
    {
        $this->ensureLanguages(['la', 'lb']);
        $this->storeSetting('general', 'default_locale', 'la');
        $this->storeSetting('general', 'multilingual_enabled', true);

        $home = $this->createPage([
            'title' => 'Home',
            'slug' => 'home-'.uniqid(),
            'locale' => 'lb',
            'is_home' => true,
        ]);
        $this->storeSetting('general', 'homepage_id', $home->id);

        $translationKey = 'locale-menu-item-'.uniqid();
        $sourcePage = $this->createPage([
            'title' => 'Projecten',
            'slug' => 'projecten-'.uniqid(),
            'locale' => 'la',
            'translation_key' => $translationKey,
        ]);
        $translatedPage = $this->createPage([
            'title' => 'Projects',
            'slug' => 'projects-'.uniqid(),
            'locale' => 'lb',
            'translation_key' => $translationKey,
            'translated_from_page_id' => $sourcePage->id,
        ]);
        $missingPage = $this->createPage([
            'title' => 'Alleen default',
            'slug' => 'alleen-default-'.uniqid(),
            'locale' => 'la',
            'translation_key' => 'missing-menu-page-'.uniqid(),
        ]);

        $menu = $this->createMenu(['location' => 'header', 'locale' => 'la']);
        $itemKey = 'item-key-'.uniqid();
        $sourceItem = $this->createMenuItem($menu, [
            'locale' => 'la',
            'translation_key' => $itemKey,
            'type' => 'page',
            'label' => 'Projecten',
            'cms_page_id' => $sourcePage->id,
        ]);
        $this->createMenuItem($menu, [
            'locale' => 'lb',
            'translation_key' => $itemKey,
            'translated_from_menu_item_id' => $sourceItem->id,
            'type' => 'page',
            'label' => 'Projects',
            'cms_page_id' => $translatedPage->id,
        ]);
        $this->createMenuItem($menu, [
            'locale' => 'la',
            'translation_key' => 'missing-item-'.uniqid(),
            'type' => 'page',
            'label' => 'Alleen default',
            'cms_page_id' => $missingPage->id,
            'sort_order' => 10,
        ]);
        $this->createMenuItem($menu, [
            'locale' => 'la',
            'translation_key' => 'missing-custom-item-'.uniqid(),
            'type' => 'custom',
            'label' => 'Alleen default custom',
            'url' => '/alleen-default-custom',
            'sort_order' => 20,
        ]);

        $this
            ->get('/lb')
            ->assertOk()
            ->assertSee('Projects')
            ->assertSee('/lb/'.$translatedPage->slug, false)
            ->assertDontSee('Projecten')
            ->assertDontSee('Alleen default')
            ->assertDontSee('Alleen default custom')
            ->assertDontSee('/alleen-default-custom', false);
    }

    public function test_translated_settings_fall_back_to_default_locale(): void
    {
        $this->ensureLanguages(['sa', 'sb']);
        $this->storeSetting('general', 'default_locale', 'sa');
        $this->storeSetting('general', 'multilingual_enabled', true);
        $this->storeSetting('general', 'site_name', 'Nederlandse site');
        $this->storeSetting('general', 'site_tagline', 'Nederlandse baseline');

        $siteName = CmsSetting::query()
            ->where('group', 'general')
            ->where('key', 'site_name')
            ->firstOrFail();
        $siteTagline = CmsSetting::query()
            ->where('group', 'general')
            ->where('key', 'site_tagline')
            ->firstOrFail();
        $siteName->translations()->updateOrCreate(['locale' => 'sb'], ['value' => ['value' => 'English site']]);
        $siteTagline->translations()->updateOrCreate(['locale' => 'sa'], ['value' => ['value' => 'Nederlandse baseline']]);

        $home = $this->createPage([
            'title' => 'Home',
            'slug' => 'home-'.uniqid(),
            'locale' => 'sb',
            'is_home' => true,
        ]);
        $this->storeSetting('general', 'homepage_id', $home->id);

        $this
            ->get('/sb')
            ->assertOk()
            ->assertSee('English site')
            ->assertSee('Nederlandse baseline');
    }

    public function test_active_redirect_redirects_and_counts_hit(): void
    {
        $this->storeSetting('general', 'default_locale', 'nl');
        $redirect = CmsRedirect::query()->create([
            'source_path' => '/oude-pagina',
            'target_url' => '/nieuwe-pagina',
            'status_code' => 301,
            'locale' => 'nl',
            'is_active' => true,
            'hit_count' => 0,
        ]);

        $this
            ->get('/oude-pagina')
            ->assertRedirect('/nieuwe-pagina')
            ->assertStatus(301);

        $redirect->refresh();

        $this->assertSame(1, (int) $redirect->hit_count);
        $this->assertNotNull($redirect->last_hit_at);
    }

    private function createPageDetailTemplate(CmsLayout $layout, bool $includeContentSlot = false): CmsTemplate
    {
        $template = CmsTemplate::query()->create([
            'name' => 'Test page detail template '.uniqid(),
            'locale' => 'nl',
            'layout_id' => $layout->id,
            'template_class' => 'page',
            'template_key' => 'page.detail',
            'is_default' => false,
            'is_active' => true,
            'cache_strategy' => 'inherit',
            'settings' => [],
        ]);

        if ($includeContentSlot) {
            $section = $template->sections()->create([
                'zone' => 'content',
                'name' => 'Template content',
                'sort_order' => 0,
                'is_active' => true,
                'visible_mobile' => true,
                'visible_tablet' => true,
                'visible_desktop' => true,
                'settings' => [],
            ]);
            $this->createPlacement($section, $this->createBlock([
                'type' => 'content_slot',
                'content' => ['slot_key' => 'content'],
            ]));
        }

        return $template;
    }

    /**
     * @param  array<int, string>  $locales
     */
    private function ensureLanguages(array $locales): void
    {
        foreach ($locales as $index => $locale) {
            CmsLanguage::query()->updateOrCreate(
                ['locale' => $locale],
                [
                    'name' => strtoupper($locale),
                    'native_name' => strtoupper($locale),
                    'direction' => 'ltr',
                    'is_active' => true,
                    'sort_order' => ($index + 1) * 10,
                ]
            );
        }
    }
}
