<?php

namespace Tests\Feature\Cms;

use App\Actions\Admin\Cms\Revisions\BuildCmsCategoryRevisionSnapshotAction;
use App\Actions\Admin\Cms\Revisions\BuildCmsFormRevisionSnapshotAction;
use App\Actions\Admin\Cms\Revisions\BuildCmsLayoutRevisionSnapshotAction;
use App\Actions\Admin\Cms\Revisions\BuildCmsMenuRevisionSnapshotAction;
use App\Actions\Admin\Cms\Revisions\BuildCmsPageRevisionSnapshotAction;
use App\Actions\Admin\Cms\Revisions\BuildCmsPostRevisionSnapshotAction;
use App\Actions\Admin\Cms\Revisions\BuildCmsTemplateRevisionSnapshotAction;
use App\Actions\Admin\Cms\Revisions\CreateCmsRevisionAction;
use App\Actions\Admin\Cms\Revisions\RestoreCmsCategoryRevisionAction;
use App\Actions\Admin\Cms\Revisions\RestoreCmsFormRevisionAction;
use App\Actions\Admin\Cms\Revisions\RestoreCmsLayoutRevisionAction;
use App\Actions\Admin\Cms\Revisions\RestoreCmsMenuRevisionAction;
use App\Actions\Admin\Cms\Revisions\RestoreCmsPageRevisionAction;
use App\Actions\Admin\Cms\Revisions\RestoreCmsPostRevisionAction;
use App\Actions\Admin\Cms\Revisions\RestoreCmsTemplateRevisionAction;
use App\Models\Cms\CmsBlock;
use App\Models\Cms\CmsBlockPlacement;
use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsForm;
use App\Models\Cms\CmsFormField;
use App\Models\Cms\CmsFormSubmission;
use App\Models\Cms\CmsLayout;
use App\Models\Cms\CmsMenu;
use App\Models\Cms\CmsMenuItem;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPost;
use App\Models\Cms\CmsRevision;
use App\Models\Cms\CmsSection;
use App\Models\Cms\CmsTemplate;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CmsRevisionTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        if (DB::connection('tenant')->transactionLevel() > 0) {
            DB::connection('tenant')->rollBack();
        }

        if (DB::connection('central')->transactionLevel() > 0) {
            DB::connection('central')->rollBack();
        }

        TenantContext::clear();

        parent::tearDown();
    }

    public function test_page_content_restore_keeps_current_structure(): void
    {
        [$page, $placement, $block] = $this->createPageTree('Originele tekst');

        $revision = app(CreateCmsRevisionAction::class)->handle(
            $page,
            'full',
            app(BuildCmsPageRevisionSnapshotAction::class)->handle($page),
        );

        $block->forceFill(['content' => ['text' => 'Gewijzigde tekst']])->save();
        $placement->forceFill(['desktop_span' => 6])->save();

        app(RestoreCmsPageRevisionAction::class)->handle($page, $revision, 'content');

        $this->assertSame('Originele tekst', $block->fresh()->content['text']);
        $this->assertSame(6, $placement->fresh()->desktop_span);
    }

    public function test_restore_always_creates_backup_even_when_current_snapshot_matches_latest_revision(): void
    {
        [$page] = $this->createPageTree('Ongewijzigde tekst');

        $revision = app(CreateCmsRevisionAction::class)->handle(
            $page,
            'full',
            app(BuildCmsPageRevisionSnapshotAction::class)->handle($page),
        );

        app(RestoreCmsPageRevisionAction::class)->handle($page, $revision, 'content');

        $scopes = CmsRevision::query()
            ->where('subject_type', CmsPage::class)
            ->where('subject_id', $page->id)
            ->orderBy('revision_number')
            ->pluck('scope')
            ->all();

        $this->assertSame(['full', 'restore_backup', 'restore'], $scopes);
    }

    public function test_page_full_restore_restores_deactivated_placements(): void
    {
        [$page, $placement] = $this->createPageTree('Eerste tekst');
        $secondBlock = CmsBlock::query()->create([
            'type' => 'text',
            'content' => ['text' => 'Tweede tekst'],
            'is_shared' => false,
            'is_dynamic' => false,
            'cache_strategy' => 'inherit',
        ]);
        $secondPlacement = CmsBlockPlacement::query()->create([
            'cms_section_id' => $placement->cms_section_id,
            'cms_block_id' => $secondBlock->id,
            'sort_order' => 1,
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

        $revision = app(CreateCmsRevisionAction::class)->handle(
            $page,
            'full',
            app(BuildCmsPageRevisionSnapshotAction::class)->handle($page->fresh()),
        );

        $secondPlacement->forceFill(['is_active' => false])->save();

        app(RestoreCmsPageRevisionAction::class)->handle($page, $revision, 'full');

        $this->assertTrue((bool) $secondPlacement->fresh()->is_active);
        $this->assertSame(1, $secondPlacement->fresh()->sort_order);
    }

    public function test_layout_code_block_restore_requires_code_block_permission(): void
    {
        [$layout] = $this->createLayoutTree('custom_head_code', ['code' => '<script></script>']);
        $revision = app(CreateCmsRevisionAction::class)->handle(
            $layout,
            'full',
            app(BuildCmsLayoutRevisionSnapshotAction::class)->handle($layout),
        );

        $this->expectException(AuthorizationException::class);

        app(RestoreCmsLayoutRevisionAction::class)->handle($layout, $revision, 'full', false);
    }

    public function test_post_content_restore_keeps_current_content_blocks(): void
    {
        $post = CmsPost::query()->create([
            'title' => 'Origineel bericht',
            'slug' => 'origineel-bericht-'.uniqid(),
            'locale' => 'nl',
            'status' => 'draft',
            'content_blocks' => [['type' => 'text', 'text' => 'Originele blocktekst']],
            'is_searchable' => true,
        ]);

        $revision = app(CreateCmsRevisionAction::class)->handle(
            $post,
            'full',
            app(BuildCmsPostRevisionSnapshotAction::class)->handle($post),
        );

        $post->forceFill([
            'title' => 'Gewijzigd bericht',
            'content_blocks' => [['type' => 'text', 'text' => 'Nieuwe blocktekst']],
        ])->save();

        app(RestoreCmsPostRevisionAction::class)->handle($post, $revision, 'content');

        $this->assertSame('Origineel bericht', $post->fresh()->title);
        $this->assertSame('Nieuwe blocktekst', $post->fresh()->content_blocks[0]['text']);
    }

    public function test_menu_full_restore_deactivates_items_not_in_revision_and_keeps_parent_tree(): void
    {
        $menu = CmsMenu::query()->create(['title' => 'Hoofdmenu', 'is_active' => true]);
        $parent = CmsMenuItem::query()->create([
            'cms_menu_id' => $menu->id,
            'locale' => 'nl',
            'type' => 'custom',
            'label' => 'Parent',
            'url' => '/parent',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $child = CmsMenuItem::query()->create([
            'cms_menu_id' => $menu->id,
            'locale' => 'nl',
            'parent_id' => $parent->id,
            'type' => 'custom',
            'label' => 'Child',
            'url' => '/child',
            'sort_order' => 20,
            'is_active' => true,
        ]);

        $revision = app(CreateCmsRevisionAction::class)->handle(
            $menu,
            'full',
            app(BuildCmsMenuRevisionSnapshotAction::class)->handle($menu),
        );

        $extra = CmsMenuItem::query()->create([
            'cms_menu_id' => $menu->id,
            'locale' => 'nl',
            'type' => 'custom',
            'label' => 'Extra',
            'url' => '/extra',
            'sort_order' => 30,
            'is_active' => true,
        ]);

        app(RestoreCmsMenuRevisionAction::class)->handle($menu, $revision, 'full');

        $this->assertFalse((bool) $extra->fresh()->is_active);
        $this->assertSame($parent->id, $child->fresh()->parent_id);
    }

    public function test_form_full_restore_blocks_removing_answered_field(): void
    {
        $form = CmsForm::query()->create([
            'key' => 'revision-form-'.uniqid(),
            'title' => 'Revisie formulier',
            'locale' => 'nl',
            'is_active' => true,
        ]);

        $revision = app(CreateCmsRevisionAction::class)->handle(
            $form,
            'full',
            app(BuildCmsFormRevisionSnapshotAction::class)->handle($form),
        );

        $field = CmsFormField::query()->create([
            'cms_form_id' => $form->id,
            'type' => 'text',
            'key' => 'name',
            'translation_key' => 'field-name',
            'label' => 'Naam',
            'sort_order' => 10,
            'is_required' => true,
            'is_active' => true,
            'width' => 'full',
        ]);
        $submission = CmsFormSubmission::query()->create([
            'cms_form_id' => $form->id,
            'locale' => 'nl',
            'status' => 'new',
            'submitted_at' => now(),
        ]);
        $submission->values()->create([
            'cms_form_field_id' => $field->id,
            'field_key' => 'name',
            'field_translation_key' => 'field-name',
            'value' => 'Jan',
        ]);

        $this->expectException(ValidationException::class);

        app(RestoreCmsFormRevisionAction::class)->handle($form, $revision, 'full');
    }

    public function test_category_full_restore_blocks_deactivating_used_term(): void
    {
        $category = CmsCategory::query()->create([
            'type' => 'post',
            'title' => 'Gebruikte categorie',
            'slug' => 'gebruikte-categorie-'.uniqid(),
            'locale' => 'nl',
            'is_active' => false,
        ]);

        $revision = app(CreateCmsRevisionAction::class)->handle(
            $category,
            'full',
            app(BuildCmsCategoryRevisionSnapshotAction::class)->handle($category),
        );

        $post = CmsPost::query()->create([
            'title' => 'Bericht met categorie',
            'slug' => 'bericht-met-categorie-'.uniqid(),
            'locale' => 'nl',
            'status' => 'draft',
            'is_searchable' => true,
        ]);
        $category->forceFill(['is_active' => true])->save();
        $post->categories()->attach($category->id);

        $this->expectException(ValidationException::class);

        app(RestoreCmsCategoryRevisionAction::class)->handle($category, $revision, 'full');
    }

    public function test_template_content_restore_keeps_current_structure(): void
    {
        [$template, $placement, $block] = $this->createTemplateTree('Originele template tekst');

        $revision = app(CreateCmsRevisionAction::class)->handle(
            $template,
            'full',
            app(BuildCmsTemplateRevisionSnapshotAction::class)->handle($template),
        );

        $template->forceFill(['name' => 'Gewijzigde template'])->save();
        $block->forceFill(['content' => ['text' => 'Gewijzigde template tekst']])->save();
        $placement->forceFill(['desktop_span' => 6])->save();

        app(RestoreCmsTemplateRevisionAction::class)->handle($template, $revision, 'content');

        $this->assertSame('Revisie template', $template->fresh()->name);
        $this->assertSame('Originele template tekst', $block->fresh()->content['text']);
        $this->assertSame(6, $placement->fresh()->desktop_span);
        $this->assertSame(
            ['full', 'restore_backup', 'restore'],
            CmsRevision::query()
                ->where('subject_type', CmsTemplate::class)
                ->where('subject_id', $template->id)
                ->orderBy('revision_number')
                ->pluck('scope')
                ->all(),
        );
    }

    public function test_template_full_restore_restores_deactivated_placements(): void
    {
        [$template, $placement] = $this->createTemplateTree('Eerste template tekst');
        $secondBlock = CmsBlock::query()->create([
            'type' => 'text',
            'content' => ['text' => 'Tweede template tekst'],
            'is_shared' => false,
            'is_dynamic' => false,
            'cache_strategy' => 'inherit',
        ]);
        $secondPlacement = CmsBlockPlacement::query()->create([
            'cms_section_id' => $placement->cms_section_id,
            'cms_block_id' => $secondBlock->id,
            'sort_order' => 1,
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

        $revision = app(CreateCmsRevisionAction::class)->handle(
            $template,
            'full',
            app(BuildCmsTemplateRevisionSnapshotAction::class)->handle($template->fresh()),
        );

        $secondPlacement->forceFill(['is_active' => false])->save();

        app(RestoreCmsTemplateRevisionAction::class)->handle($template, $revision, 'full');

        $this->assertTrue((bool) $secondPlacement->fresh()->is_active);
        $this->assertSame(1, $secondPlacement->fresh()->sort_order);
    }

    /**
     * @return array{0: CmsPage, 1: CmsBlockPlacement, 2: CmsBlock}
     */
    private function createPageTree(string $text): array
    {
        $layout = CmsLayout::query()->create([
            'name' => 'Page revision layout',
            'locale' => 'nl',
            'is_default' => false,
            'is_active' => true,
            'cache_strategy' => 'inherit',
        ]);
        $template = CmsTemplate::query()->create([
            'name' => 'Page revision template',
            'locale' => 'nl',
            'layout_id' => $layout->id,
            'template_class' => 'page',
            'template_key' => 'page.detail',
            'is_default' => false,
            'is_active' => true,
            'cache_strategy' => 'inherit',
            'settings' => [],
        ]);

        $page = CmsPage::query()->create([
            'title' => 'Revisie pagina',
            'slug' => 'revisie-pagina-'.uniqid(),
            'locale' => 'nl',
            'detail_template_id' => $template->id,
            'status' => 'draft',
            'is_searchable' => true,
        ]);

        $section = CmsSection::query()->create([
            'owner_type' => CmsPage::class,
            'owner_id' => $page->id,
            'zone' => 'content',
            'sort_order' => 0,
            'is_active' => true,
            'visible_mobile' => true,
            'visible_tablet' => true,
            'visible_desktop' => true,
            'settings' => [],
        ]);

        $block = CmsBlock::query()->create([
            'type' => 'text',
            'content' => ['text' => $text],
            'is_shared' => false,
            'is_dynamic' => false,
            'cache_strategy' => 'inherit',
        ]);

        $placement = CmsBlockPlacement::query()->create([
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

        return [$page, $placement, $block];
    }

    /**
     * @return array{0: CmsTemplate, 1: CmsBlockPlacement, 2: CmsBlock}
     */
    private function createTemplateTree(string $text): array
    {
        $layout = CmsLayout::query()->create([
            'name' => 'Template revision layout',
            'locale' => 'nl',
            'is_default' => false,
            'is_active' => true,
            'cache_strategy' => 'inherit',
        ]);

        $template = CmsTemplate::query()->create([
            'name' => 'Revisie template',
            'locale' => 'nl',
            'layout_id' => $layout->id,
            'template_class' => 'page',
            'template_key' => 'page.detail',
            'is_default' => false,
            'is_active' => true,
            'cache_strategy' => 'inherit',
            'settings' => [],
        ]);

        $section = CmsSection::query()->create([
            'owner_type' => CmsTemplate::class,
            'owner_id' => $template->id,
            'zone' => 'content',
            'sort_order' => 0,
            'is_active' => true,
            'visible_mobile' => true,
            'visible_tablet' => true,
            'visible_desktop' => true,
            'settings' => [],
        ]);

        $block = CmsBlock::query()->create([
            'type' => 'text',
            'content' => ['text' => $text],
            'is_shared' => false,
            'is_dynamic' => false,
            'cache_strategy' => 'inherit',
        ]);

        $placement = CmsBlockPlacement::query()->create([
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

        return [$template, $placement, $block];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array{0: CmsLayout}
     */
    private function createLayoutTree(string $blockType, array $content): array
    {
        $layout = CmsLayout::query()->create([
            'name' => 'Revisie layout',
            'locale' => 'nl',
            'is_default' => false,
            'is_active' => true,
            'cache_strategy' => 'inherit',
        ]);

        $section = CmsSection::query()->create([
            'owner_type' => CmsLayout::class,
            'owner_id' => $layout->id,
            'zone' => 'head',
            'sort_order' => 0,
            'is_active' => true,
            'visible_mobile' => true,
            'visible_tablet' => true,
            'visible_desktop' => true,
            'settings' => [],
        ]);

        $block = CmsBlock::query()->create([
            'type' => $blockType,
            'content' => $content,
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

        return [$layout];
    }
}
