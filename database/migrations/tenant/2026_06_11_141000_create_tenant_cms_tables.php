<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! $this->hasTable('cms_pages')) {
            Schema::create('cms_pages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('parent_id')->nullable()->constrained('cms_pages')->nullOnDelete();
                $table->unsignedBigInteger('author_id')->nullable()->index();
                $table->string('title');
                $table->string('slug');
                $table->string('locale', 12)->default(config('app.locale', 'en'));
                $table->string('status', 32)->default('draft')->index();
                $table->string('template')->nullable();
                $table->text('short_description')->nullable();
                $table->json('content_blocks')->nullable();
                $table->string('seo_title')->nullable();
                $table->text('seo_description')->nullable();
                $table->string('canonical_url')->nullable();
                $table->string('og_image_path')->nullable();
                $table->boolean('noindex')->default(false);
                $table->boolean('is_home')->default(false)->index();
                $table->boolean('is_searchable')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamp('published_at')->nullable()->index();
                $table->json('settings')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['locale', 'slug']);
                $table->index(['parent_id', 'sort_order']);
            });
        }

        if (! $this->hasTable('cms_posts')) {
            Schema::create('cms_posts', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('author_id')->nullable()->index();
                $table->unsignedBigInteger('featured_media_asset_id')->nullable()->index();
                $table->string('title');
                $table->string('slug');
                $table->string('locale', 12)->default(config('app.locale', 'en'));
                $table->string('status', 32)->default('draft')->index();
                $table->text('excerpt')->nullable();
                $table->json('content_blocks')->nullable();
                $table->string('seo_title')->nullable();
                $table->text('seo_description')->nullable();
                $table->string('canonical_url')->nullable();
                $table->string('og_image_path')->nullable();
                $table->boolean('noindex')->default(false);
                $table->boolean('is_featured')->default(false)->index();
                $table->boolean('is_searchable')->default(true);
                $table->timestamp('published_at')->nullable()->index();
                $table->json('settings')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['locale', 'slug']);
            });
        }

        if (! $this->hasTable('cms_categories')) {
            Schema::create('cms_categories', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('parent_id')->nullable()->constrained('cms_categories')->nullOnDelete();
                $table->string('type', 32)->default('post')->index();
                $table->string('title');
                $table->string('slug');
                $table->string('locale', 12)->default(config('app.locale', 'en'));
                $table->string('translation_key', 64)->nullable()->index();
                $table->foreignId('translated_from_category_id')->nullable()->constrained('cms_categories')->nullOnDelete();
                $table->foreignId('landing_page_id')->nullable()->constrained('cms_pages')->nullOnDelete();
                $table->text('description')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true)->index();
                $table->json('settings')->nullable();
                $table->timestamps();

                $table->unique(['type', 'locale', 'slug']);
                $table->unique(['type', 'translation_key', 'locale'], 'cms_categories_type_translation_locale_unique');
                $table->index(['parent_id', 'sort_order']);
            });
        }

        if (! $this->hasTable('cms_tags')) {
            Schema::create('cms_tags', function (Blueprint $table): void {
                $table->id();
                $table->string('title');
                $table->string('slug');
                $table->string('locale', 12)->default(config('app.locale', 'en'));
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->json('settings')->nullable();
                $table->timestamps();

                $table->unique(['locale', 'slug']);
            });
        }

        if (! $this->hasTable('cms_post_category')) {
            Schema::create('cms_post_category', function (Blueprint $table): void {
                $table->foreignId('cms_post_id')->constrained('cms_posts')->cascadeOnDelete();
                $table->foreignId('cms_category_id')->constrained('cms_categories')->cascadeOnDelete();

                $table->primary(['cms_post_id', 'cms_category_id']);
            });
        }

        if (! $this->hasTable('cms_post_tag')) {
            Schema::create('cms_post_tag', function (Blueprint $table): void {
                $table->foreignId('cms_post_id')->constrained('cms_posts')->cascadeOnDelete();
                $table->foreignId('cms_tag_id')->constrained('cms_tags')->cascadeOnDelete();

                $table->primary(['cms_post_id', 'cms_tag_id']);
            });
        }

        if (! $this->hasTable('cms_media_folders')) {
            Schema::create('cms_media_folders', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('parent_id')->nullable()->constrained('cms_media_folders')->nullOnDelete();
                $table->string('name');
                $table->string('slug');
                $table->unsignedInteger('sort_order')->default(0);
                $table->json('settings')->nullable();
                $table->timestamps();

                $table->unique(['parent_id', 'slug']);
            });
        }

        if (! $this->hasTable('cms_media_assets')) {
            Schema::create('cms_media_assets', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('folder_id')->nullable()->constrained('cms_media_folders')->nullOnDelete();
                $table->unsignedBigInteger('uploaded_by')->nullable()->index();
                $table->string('disk')->default('public');
                $table->string('visibility', 24)->default('public')->index();
                $table->string('path')->unique();
                $table->string('filename');
                $table->string('original_filename')->nullable();
                $table->string('mime_type', 160)->nullable();
                $table->string('extension', 32)->nullable()->index();
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->unsignedInteger('width')->nullable();
                $table->unsignedInteger('height')->nullable();
                $table->string('hash', 64)->nullable()->index();
                $table->string('alt_text')->nullable();
                $table->text('caption')->nullable();
                $table->json('focal_point')->nullable();
                $table->json('metadata')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['folder_id', 'sort_order']);
            });
        }

        if (! $this->hasTable('cms_media_asset_translations')) {
            Schema::create('cms_media_asset_translations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('cms_media_asset_id')->constrained('cms_media_assets')->cascadeOnDelete();
                $table->string('locale', 12);
                $table->string('alt_text')->nullable();
                $table->text('caption')->nullable();
                $table->timestamps();

                $table->unique(['cms_media_asset_id', 'locale'], 'cms_media_asset_translations_asset_locale_unique');
            });
        }

        if ($this->hasTable('cms_posts') && $this->hasTable('cms_media_assets') && ! $this->hasForeignKey('cms_posts', 'featured_media_asset_id')) {
            Schema::table('cms_posts', function (Blueprint $table): void {
                $table->foreign('featured_media_asset_id')
                    ->references('id')
                    ->on('cms_media_assets')
                    ->nullOnDelete();
            });
        }

        if (! $this->hasTable('cms_menus')) {
            Schema::create('cms_menus', function (Blueprint $table): void {
                $table->id();
                $table->string('title');
                $table->string('location')->nullable()->index();
                $table->boolean('is_active')->default(true)->index();
                $table->json('settings')->nullable();
                $table->timestamps();

                $table->unique('location');
            });
        }

        if (! $this->hasTable('cms_menu_items')) {
            Schema::create('cms_menu_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('cms_menu_id')->constrained('cms_menus')->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('cms_menu_items')->cascadeOnDelete();
                $table->foreignId('cms_page_id')->nullable()->constrained('cms_pages')->nullOnDelete();
                $table->foreignId('cms_post_id')->nullable()->constrained('cms_posts')->nullOnDelete();
                $table->string('type', 32)->default('custom')->index();
                $table->string('label');
                $table->string('url')->nullable();
                $table->string('target', 32)->nullable();
                $table->string('rel')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true)->index();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['cms_menu_id', 'parent_id', 'sort_order']);
            });
        }

        if (! $this->hasTable('cms_redirects')) {
            Schema::create('cms_redirects', function (Blueprint $table): void {
                $table->id();
                $table->string('source_path');
                $table->string('target_url');
                $table->unsignedSmallInteger('status_code')->default(301);
                $table->string('locale', 12)->nullable()->index();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->unsignedBigInteger('hit_count')->default(0);
                $table->timestamp('last_hit_at')->nullable();
                $table->timestamps();

                $table->unique(['source_path', 'locale']);
            });
        }

        if (! $this->hasTable('cms_forms')) {
            Schema::create('cms_forms', function (Blueprint $table): void {
                $table->id();
                $table->string('title');
                $table->string('locale', 12)->default(config('app.locale', 'en'));
                $table->string('translation_key', 32)->index();
                $table->foreignId('translated_from_form_id')->nullable()->constrained('cms_forms')->nullOnDelete();
                $table->text('description')->nullable();
                $table->string('notification_email')->nullable();
                $table->string('submit_button_label')->nullable();
                $table->text('success_message')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->json('settings')->nullable();
                $table->timestamps();

                $table->unique(['translation_key', 'locale']);
            });
        }

        if (! $this->hasTable('cms_form_fields')) {
            Schema::create('cms_form_fields', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('cms_form_id')->constrained('cms_forms')->cascadeOnDelete();
                $table->string('type', 48)->default('text')->index();
                $table->string('translation_key', 32)->index();
                $table->foreignId('translated_from_form_field_id')->nullable()->constrained('cms_form_fields')->nullOnDelete();
                $table->string('label');
                $table->string('placeholder')->nullable();
                $table->text('help_text')->nullable();
                $table->json('options')->nullable();
                $table->json('validation_rules')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_required')->default(false);
                $table->boolean('is_active')->default(true)->index();
                $table->string('width', 24)->default('full');
                $table->json('settings')->nullable();
                $table->timestamps();

                $table->index(['cms_form_id', 'sort_order']);
            });
        }

        if (! $this->hasTable('cms_form_submissions')) {
            Schema::create('cms_form_submissions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('cms_form_id')->constrained('cms_forms')->cascadeOnDelete();
                $table->foreignId('cms_page_id')->nullable()->constrained('cms_pages')->nullOnDelete();
                $table->string('locale', 12)->index();
                $table->string('form_translation_key', 32)->index();
                $table->string('status', 32)->default('new')->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('submitted_at')->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! $this->hasTable('cms_form_submission_values')) {
            Schema::create('cms_form_submission_values', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('cms_form_submission_id');
                $table->foreignId('cms_form_field_id')->nullable();
                $table->string('field_translation_key', 32)->index();
                $table->string('field_label_snapshot')->nullable();
                $table->longText('value')->nullable();
                $table->timestamps();

                $table->foreign('cms_form_submission_id', 'cms_form_sub_val_submission_fk')
                    ->references('id')
                    ->on('cms_form_submissions')
                    ->cascadeOnDelete();
                $table->foreign('cms_form_field_id', 'cms_form_sub_val_field_fk')
                    ->references('id')
                    ->on('cms_form_fields')
                    ->nullOnDelete();
            });
        }

        if ($this->hasTable('cms_form_submission_values') && $this->hasTable('cms_form_submissions') && ! $this->hasForeignKey('cms_form_submission_values', 'cms_form_submission_id')) {
            Schema::table('cms_form_submission_values', function (Blueprint $table): void {
                $table->foreign('cms_form_submission_id', 'cms_form_sub_val_submission_fk')
                    ->references('id')
                    ->on('cms_form_submissions')
                    ->cascadeOnDelete();
            });
        }

        if ($this->hasTable('cms_form_submission_values') && $this->hasTable('cms_form_fields') && ! $this->hasForeignKey('cms_form_submission_values', 'cms_form_field_id')) {
            Schema::table('cms_form_submission_values', function (Blueprint $table): void {
                $table->foreign('cms_form_field_id', 'cms_form_sub_val_field_fk')
                    ->references('id')
                    ->on('cms_form_fields')
                    ->nullOnDelete();
            });
        }

        if (! $this->hasTable('cms_revisions')) {
            Schema::create('cms_revisions', function (Blueprint $table): void {
                $table->id();
                $table->string('subject_type');
                $table->unsignedBigInteger('subject_id');
                $table->unsignedBigInteger('author_id')->nullable()->index();
                $table->unsignedInteger('revision_number');
                $table->string('title')->nullable();
                $table->json('snapshot');
                $table->timestamps();

                $table->index(['subject_type', 'subject_id']);
            });
        }

        if (! $this->hasTable('cms_preview_tokens')) {
            Schema::create('cms_preview_tokens', function (Blueprint $table): void {
                $table->id();
                $table->string('subject_type');
                $table->unsignedBigInteger('subject_id');
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->string('token', 64)->unique();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();

                $table->index(['subject_type', 'subject_id']);
            });
        }

        if (! $this->hasTable('cms_settings')) {
            Schema::create('cms_settings', function (Blueprint $table): void {
                $table->id();
                $table->string('group')->default('general')->index();
                $table->string('key');
                $table->string('label')->nullable();
                $table->string('type', 32)->default('text');
                $table->json('value')->nullable();
                $table->boolean('is_public')->default(false)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['group', 'key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_settings');
        Schema::dropIfExists('cms_preview_tokens');
        Schema::dropIfExists('cms_revisions');
        Schema::dropIfExists('cms_form_submission_values');
        Schema::dropIfExists('cms_form_submissions');
        Schema::dropIfExists('cms_form_fields');
        Schema::dropIfExists('cms_forms');
        Schema::dropIfExists('cms_redirects');
        Schema::dropIfExists('cms_menu_items');
        Schema::dropIfExists('cms_menus');
        Schema::table('cms_posts', function (Blueprint $table): void {
            $table->dropForeign(['featured_media_asset_id']);
        });
        Schema::dropIfExists('cms_media_asset_translations');
        Schema::dropIfExists('cms_media_assets');
        Schema::dropIfExists('cms_media_folders');
        Schema::dropIfExists('cms_post_tag');
        Schema::dropIfExists('cms_post_category');
        Schema::dropIfExists('cms_tags');
        Schema::dropIfExists('cms_categories');
        Schema::dropIfExists('cms_posts');
        Schema::dropIfExists('cms_pages');
    }

    private function hasTable(string $table): bool
    {
        return Schema::hasTable($table) || $this->hasPrefixedTable($table);
    }

    private function hasPrefixedTable(string $table): bool
    {
        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();

        return $prefix !== '' && $connection->selectOne(
            'select 1 from information_schema.tables where table_schema = ? and table_name = ? limit 1',
            [$connection->getDatabaseName(), $prefix.$table],
        ) !== null;
    }

    private function hasForeignKey(string $table, string $column): bool
    {
        $connection = Schema::getConnection();
        $prefix = $connection->getTablePrefix();

        return $connection->selectOne(
            'select 1 from information_schema.key_column_usage where table_schema = ? and table_name = ? and column_name = ? and referenced_table_name is not null limit 1',
            [$connection->getDatabaseName(), $prefix.$table, $column],
        ) !== null;
    }
};
