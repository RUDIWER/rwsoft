<?php

namespace Tests\Unit\Cms;

use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsTag;
use App\Models\Cms\CmsTemplate;
use App\Support\PublicSite\CmsTemplateResolver;
use Tests\TestCase;

class CmsTemplateResolverTest extends TestCase
{
    public function test_resolves_active_assigned_archive_template_without_database_lookup(): void
    {
        $template = new CmsTemplate([
            'template_class' => 'category',
            'template_key' => 'category.archive',
            'locale' => 'nl',
            'is_active' => true,
        ]);
        $category = new CmsCategory([
            'locale' => 'nl',
        ]);
        $category->setRelation('archiveTemplate', $template);

        $resolved = app(CmsTemplateResolver::class)->resolve('category.archive', 'nl', $category);

        $this->assertSame($template, $resolved);
    }

    public function test_resolves_active_assigned_detail_template_without_database_lookup(): void
    {
        $template = new CmsTemplate([
            'template_class' => 'tag',
            'template_key' => 'tag.detail',
            'locale' => 'nl',
            'is_active' => true,
        ]);
        $tag = new CmsTag([
            'locale' => 'nl',
        ]);
        $tag->setRelation('detailTemplate', $template);

        $resolved = app(CmsTemplateResolver::class)->resolve('tag.detail', 'nl', $tag);

        $this->assertSame($template, $resolved);
    }
}
