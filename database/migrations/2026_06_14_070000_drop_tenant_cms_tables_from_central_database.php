<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop tenant-owned CMS runtime tables that were created in the central database during early development.
     */
    public function up(): void
    {
        Schema::connection('central')->disableForeignKeyConstraints();

        foreach ($this->tenantCmsTables() as $table) {
            Schema::connection('central')->dropIfExists($table);
        }

        Schema::connection('central')->enableForeignKeyConstraints();
    }

    /**
     * This cleanup is intentionally not reversible. Tenant CMS tables are created by tenant migrations.
     */
    public function down(): void
    {
        // Intentionally empty.
    }

    /**
     * @return array<int, string>
     */
    private function tenantCmsTables(): array
    {
        return [
            'cms_shared_block_scopes',
            'cms_block_exclusions',
            'cms_block_overrides',
            'cms_block_placements',
            'cms_blocks',
            'cms_sections',
            'cms_layouts',
            'cms_public_text_translations',
            'cms_public_texts',
            'cms_media_asset_translations',
            'cms_form_submission_values',
            'cms_form_submissions',
            'cms_form_fields',
            'cms_forms',
            'cms_menu_translations',
            'cms_setting_translations',
            'cms_menu_items',
            'cms_menus',
            'cms_post_category',
            'cms_post_tag',
            'cms_categories',
            'cms_tags',
            'cms_posts',
            'cms_media_assets',
            'cms_media_folders',
            'cms_redirects',
            'cms_revisions',
            'cms_preview_tokens',
            'cms_settings',
            'cms_languages',
            'cms_pages',
            'cms_theme_versions',
            'cms_themes',
        ];
    }
};
