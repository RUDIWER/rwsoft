<?php

namespace Tests\Feature\Cms;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CmsCoreSchemaTest extends TestCase
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
    }

    public function test_cms_core_tables_exist(): void
    {
        foreach ($this->cmsTables() as $tableName) {
            $this->assertTrue(Schema::hasTable($tableName), "Missing table [{$tableName}].");
        }
    }

    public function test_cms_content_tables_have_required_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('cms_menus', [
            'title',
            'placements',
            'is_active',
            'settings',
        ]));
        $this->assertFalse(Schema::hasColumn('cms_menus', 'slug'));
        $this->assertFalse(Schema::hasColumn('cms_menus', 'locale'));

        $this->assertTrue(Schema::hasColumns('cms_menu_translations', [
            'cms_menu_id',
            'locale',
            'title',
        ]));

        $this->assertTrue(Schema::hasColumns('cms_menu_items', [
            'cms_menu_id',
            'locale',
            'translation_key',
            'translated_from_menu_item_id',
            'label',
            'url',
        ]));

        $this->assertTrue(Schema::hasColumns('cms_pages', [
            'parent_id',
            'detail_template_id',
            'author_id',
            'title',
            'slug',
            'locale',
            'status',
            'content_blocks',
            'published_at',
            'settings',
        ]));
        $this->assertFalse(Schema::hasColumn('cms_pages', 'layout_id'));

        $this->assertTrue(Schema::hasColumns('cms_posts', [
            'author_id',
            'featured_media_asset_id',
            'title',
            'slug',
            'locale',
            'status',
            'content_blocks',
            'published_at',
        ]));

        $this->assertTrue(Schema::hasColumns('cms_media_assets', [
            'folder_id',
            'uploaded_by',
            'disk',
            'visibility',
            'path',
            'mime_type',
            'metadata',
        ]));

        $this->assertTrue(Schema::hasColumns('cms_media_asset_translations', [
            'cms_media_asset_id',
            'locale',
            'alt_text',
            'caption',
        ]));

        $this->assertTrue(Schema::hasColumns('cms_forms', [
            'title',
            'locale',
            'translation_key',
            'translated_from_form_id',
            'notification_email',
            'success_message',
            'settings',
        ]));

        $this->assertTrue(Schema::hasColumns('cms_form_fields', [
            'translation_key',
            'translated_from_form_field_id',
        ]));

        $this->assertTrue(Schema::hasColumns('cms_form_submissions', [
            'locale',
            'form_translation_key',
        ]));

        $this->assertTrue(Schema::hasColumns('cms_form_submission_values', [
            'field_translation_key',
        ]));
    }

    public function test_legacy_menu_item_translation_table_does_not_exist(): void
    {
        $this->assertFalse(Schema::hasTable('cms_menu_item_translations'));
        $this->assertTrue(Schema::hasTable('cms_menu_translations'));
        $this->assertTrue(Schema::hasTable('cms_setting_translations'));
    }

    /**
     * @return array<int, string>
     */
    private function cmsTables(): array
    {
        return [
            'cms_pages',
            'cms_posts',
            'cms_categories',
            'cms_tags',
            'cms_post_category',
            'cms_post_tag',
            'cms_media_folders',
            'cms_media_assets',
            'cms_media_asset_translations',
            'cms_menus',
            'cms_menu_translations',
            'cms_menu_items',
            'cms_redirects',
            'cms_revisions',
            'cms_preview_tokens',
            'cms_forms',
            'cms_form_fields',
            'cms_form_submissions',
            'cms_form_submission_values',
            'cms_settings',
            'cms_setting_translations',
        ];
    }
}
