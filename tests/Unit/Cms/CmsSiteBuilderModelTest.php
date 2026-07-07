<?php

namespace Tests\Unit\Cms;

use App\Models\Cms\CmsBlock;
use App\Models\Cms\CmsBlockExclusion;
use App\Models\Cms\CmsBlockOverride;
use App\Models\Cms\CmsBlockPlacement;
use App\Models\Cms\CmsLayout;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsSection;
use App\Models\Cms\CmsSharedBlockScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Tests\TestCase;

class CmsSiteBuilderModelTest extends TestCase
{
    public function test_sitebuilder_models_use_expected_tables(): void
    {
        $models = [
            CmsLayout::class => 'cms_layouts',
            CmsSection::class => 'cms_sections',
            CmsBlock::class => 'cms_blocks',
            CmsBlockPlacement::class => 'cms_block_placements',
            CmsBlockOverride::class => 'cms_block_overrides',
            CmsBlockExclusion::class => 'cms_block_exclusions',
            CmsSharedBlockScope::class => 'cms_shared_block_scopes',
        ];

        foreach ($models as $modelClass => $tableName) {
            $this->assertSame($tableName, (new $modelClass)->getTable());
        }
    }

    public function test_sitebuilder_models_cast_layout_settings_and_flags(): void
    {
        $this->assertSame('array', (new CmsLayout)->getCasts()['settings']);
        $this->assertSame('boolean', (new CmsLayout)->getCasts()['is_default']);
        $this->assertSame('boolean', (new CmsLayout)->getCasts()['is_active']);

        $this->assertSame('array', (new CmsSection)->getCasts()['settings']);
        $this->assertSame('boolean', (new CmsSection)->getCasts()['visible_mobile']);
        $this->assertSame('boolean', (new CmsSection)->getCasts()['visible_tablet']);
        $this->assertSame('boolean', (new CmsSection)->getCasts()['visible_desktop']);

        $this->assertSame('array', (new CmsBlock)->getCasts()['content']);
        $this->assertSame('array', (new CmsBlock)->getCasts()['settings']);
        $this->assertSame('boolean', (new CmsBlock)->getCasts()['is_shared']);
        $this->assertSame('boolean', (new CmsBlock)->getCasts()['is_dynamic']);

        $placementCasts = (new CmsBlockPlacement)->getCasts();
        $this->assertSame('integer', $placementCasts['mobile_span']);
        $this->assertSame('integer', $placementCasts['tablet_span']);
        $this->assertSame('integer', $placementCasts['desktop_span']);
        $this->assertSame('boolean', $placementCasts['visible_mobile']);
        $this->assertSame('array', $placementCasts['settings']);

        $this->assertSame('array', (new CmsBlockOverride)->getCasts()['content']);
        $this->assertSame('array', (new CmsSharedBlockScope)->getCasts()['settings']);
        $this->assertSame('integer', (new CmsPage)->getCasts()['detail_template_id']);
    }

    public function test_sitebuilder_model_relationships_are_defined(): void
    {
        $this->assertInstanceOf(MorphMany::class, (new CmsLayout)->sections());
        $this->assertInstanceOf(MorphMany::class, (new CmsLayout)->headerSections());
        $this->assertInstanceOf(MorphMany::class, (new CmsLayout)->footerSections());

        $this->assertInstanceOf(BelongsTo::class, (new CmsPage)->detailTemplate());
        $this->assertInstanceOf(HasMany::class, (new CmsPage)->blockOverrides());
        $this->assertInstanceOf(HasMany::class, (new CmsPage)->blockExclusions());
        $this->assertInstanceOf(MorphMany::class, (new CmsPage)->sections());
        $this->assertInstanceOf(MorphMany::class, (new CmsPage)->contentSections());

        $this->assertInstanceOf(MorphTo::class, (new CmsSection)->owner());
        $this->assertInstanceOf(HasMany::class, (new CmsSection)->placements());

        $this->assertInstanceOf(BelongsTo::class, (new CmsBlock)->creator());
        $this->assertInstanceOf(HasMany::class, (new CmsBlock)->placements());

        $this->assertInstanceOf(BelongsTo::class, (new CmsBlockPlacement)->section());
        $this->assertInstanceOf(BelongsTo::class, (new CmsBlockPlacement)->block());
        $this->assertInstanceOf(HasMany::class, (new CmsBlockPlacement)->overrides());
        $this->assertInstanceOf(HasMany::class, (new CmsBlockPlacement)->exclusions());
        $this->assertInstanceOf(HasMany::class, (new CmsBlockPlacement)->scopes());

        $this->assertInstanceOf(BelongsTo::class, (new CmsBlockOverride)->page());
        $this->assertInstanceOf(BelongsTo::class, (new CmsBlockOverride)->placement());
        $this->assertInstanceOf(BelongsTo::class, (new CmsBlockExclusion)->page());
        $this->assertInstanceOf(BelongsTo::class, (new CmsBlockExclusion)->placement());
        $this->assertInstanceOf(BelongsTo::class, (new CmsSharedBlockScope)->placement());
    }
}
