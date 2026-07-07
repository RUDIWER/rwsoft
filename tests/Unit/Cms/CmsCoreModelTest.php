<?php

namespace Tests\Unit\Cms;

use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsForm;
use App\Models\Cms\CmsFormField;
use App\Models\Cms\CmsFormSubmission;
use App\Models\Cms\CmsMediaAsset;
use App\Models\Cms\CmsMediaAssetTranslation;
use App\Models\Cms\CmsMediaFolder;
use App\Models\Cms\CmsMenu;
use App\Models\Cms\CmsMenuItem;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPost;
use App\Models\Cms\CmsPreviewToken;
use App\Models\Cms\CmsRedirect;
use App\Models\Cms\CmsRevision;
use App\Models\Cms\CmsSetting;
use App\Models\Cms\CmsTag;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Tests\TestCase;

class CmsCoreModelTest extends TestCase
{
    public function test_cms_models_use_expected_tables(): void
    {
        $models = [
            CmsPage::class => 'cms_pages',
            CmsPost::class => 'cms_posts',
            CmsCategory::class => 'cms_categories',
            CmsTag::class => 'cms_tags',
            CmsMediaFolder::class => 'cms_media_folders',
            CmsMediaAsset::class => 'cms_media_assets',
            CmsMediaAssetTranslation::class => 'cms_media_asset_translations',
            CmsMenu::class => 'cms_menus',
            CmsMenuItem::class => 'cms_menu_items',
            CmsRedirect::class => 'cms_redirects',
            CmsRevision::class => 'cms_revisions',
            CmsPreviewToken::class => 'cms_preview_tokens',
            CmsForm::class => 'cms_forms',
            CmsFormField::class => 'cms_form_fields',
            CmsFormSubmission::class => 'cms_form_submissions',
            CmsSetting::class => 'cms_settings',
        ];

        foreach ($models as $modelClass => $tableName) {
            $this->assertSame($tableName, (new $modelClass)->getTable());
        }
    }

    public function test_cms_models_cast_json_boolean_and_date_fields(): void
    {
        $this->assertSame('array', (new CmsPage)->getCasts()['content_blocks']);
        $this->assertSame('datetime', (new CmsPage)->getCasts()['published_at']);
        $this->assertSame('boolean', (new CmsPage)->getCasts()['is_home']);
        $this->assertSame('array', (new CmsPost)->getCasts()['content_blocks']);
        $this->assertSame('array', (new CmsMediaAsset)->getCasts()['metadata']);
        $this->assertSame('boolean', (new CmsMenu)->getCasts()['is_active']);
        $this->assertSame('datetime', (new CmsRedirect)->getCasts()['starts_at']);
        $this->assertSame('array', (new CmsRevision)->getCasts()['snapshot']);
        $this->assertSame('datetime', (new CmsPreviewToken)->getCasts()['expires_at']);
        $this->assertSame('array', (new CmsFormField)->getCasts()['validation_rules']);
        $this->assertSame('array', (new CmsSetting)->getCasts()['value']);
    }

    public function test_cms_model_relationships_are_defined(): void
    {
        $this->assertInstanceOf(BelongsTo::class, (new CmsPage)->parent());
        $this->assertInstanceOf(BelongsTo::class, (new CmsPage)->author());
        $this->assertInstanceOf(HasMany::class, (new CmsPage)->children());
        $this->assertInstanceOf(BelongsTo::class, (new CmsPost)->featuredMedia());
        $this->assertInstanceOf(BelongsToMany::class, (new CmsPost)->categories());
        $this->assertInstanceOf(BelongsToMany::class, (new CmsPost)->tags());
        $this->assertInstanceOf(HasMany::class, (new CmsCategory)->children());
        $this->assertInstanceOf(BelongsToMany::class, (new CmsTag)->posts());
        $this->assertInstanceOf(HasMany::class, (new CmsMediaFolder)->assets());
        $this->assertInstanceOf(BelongsTo::class, (new CmsMediaAsset)->uploader());
        $this->assertInstanceOf(HasMany::class, (new CmsMediaAsset)->translations());
        $this->assertInstanceOf(BelongsTo::class, (new CmsMediaAssetTranslation)->asset());
        $this->assertInstanceOf(HasMany::class, (new CmsMenu)->items());
        $this->assertInstanceOf(BelongsTo::class, (new CmsMenuItem)->page());
        $this->assertInstanceOf(MorphTo::class, (new CmsRevision)->subject());
        $this->assertInstanceOf(MorphTo::class, (new CmsPreviewToken)->subject());
        $this->assertInstanceOf(HasMany::class, (new CmsForm)->fields());
        $this->assertInstanceOf(BelongsTo::class, (new CmsForm)->translatedFrom());
        $this->assertInstanceOf(HasMany::class, (new CmsForm)->translatedForms());
        $this->assertInstanceOf(BelongsTo::class, (new CmsFormField)->translatedFrom());
        $this->assertInstanceOf(HasMany::class, (new CmsFormField)->translatedFields());
        $this->assertInstanceOf(BelongsTo::class, (new CmsFormSubmission)->form());
    }
}
