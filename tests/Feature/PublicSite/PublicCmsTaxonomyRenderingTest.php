<?php

namespace Tests\Feature\PublicSite;

class PublicCmsTaxonomyRenderingTest extends PublicCmsTestCase
{
    public function test_category_route_renders_public_posts_for_nested_category_path(): void
    {
        $this->storeSetting('general', 'default_locale', 'cr');
        $parent = $this->createCategory([
            'title' => 'Nieuws',
            'slug' => 'nieuws-'.uniqid(),
            'locale' => 'cr',
        ]);
        $child = $this->createCategory([
            'title' => 'Schoolnieuws',
            'slug' => 'schoolnieuws-'.uniqid(),
            'locale' => 'cr',
            'parent_id' => $parent->id,
        ]);
        $post = $this->createPost([
            'title' => 'Zichtbaar category bericht',
            'slug' => 'zichtbaar-category-'.uniqid(),
            'locale' => 'cr',
        ]);
        $draft = $this->createPost([
            'title' => 'Verborgen category bericht',
            'slug' => 'verborgen-category-'.uniqid(),
            'locale' => 'cr',
            'status' => 'draft',
        ]);
        $post->categories()->sync([$child->id]);
        $draft->categories()->sync([$child->id]);

        $this
            ->get('/posts/category/'.$parent->slug.'/'.$child->slug)
            ->assertOk()
            ->assertSee('Schoolnieuws')
            ->assertSee('Zichtbaar category bericht')
            ->assertDontSee('Verborgen category bericht');
    }

    public function test_category_route_returns_not_found_for_invalid_or_empty_categories(): void
    {
        $this->storeSetting('general', 'default_locale', 'ci');
        $parent = $this->createCategory([
            'title' => 'Parent',
            'slug' => 'parent-'.uniqid(),
            'locale' => 'ci',
        ]);
        $child = $this->createCategory([
            'title' => 'Child',
            'slug' => 'child-'.uniqid(),
            'locale' => 'ci',
            'parent_id' => $parent->id,
        ]);
        $empty = $this->createCategory([
            'title' => 'Empty',
            'slug' => 'empty-'.uniqid(),
            'locale' => 'ci',
        ]);
        $inactive = $this->createCategory([
            'title' => 'Inactive',
            'slug' => 'inactive-'.uniqid(),
            'locale' => 'ci',
            'is_active' => false,
        ]);

        $this->get('/posts/category/'.$child->slug)->assertNotFound();
        $this->get('/posts/category/'.$empty->slug)->assertNotFound();
        $this->get('/posts/category/'.$inactive->slug)->assertNotFound();
    }

    public function test_tag_route_renders_only_public_posts(): void
    {
        $this->storeSetting('general', 'default_locale', 'tg');
        $tag = $this->createTag([
            'title' => 'Release',
            'slug' => 'release-'.uniqid(),
            'locale' => 'tg',
        ]);
        $post = $this->createPost([
            'title' => 'Zichtbaar tag bericht',
            'slug' => 'zichtbaar-tag-'.uniqid(),
            'locale' => 'tg',
        ]);
        $draft = $this->createPost([
            'title' => 'Verborgen tag bericht',
            'slug' => 'verborgen-tag-'.uniqid(),
            'locale' => 'tg',
            'status' => 'draft',
        ]);
        $post->tags()->sync([$tag->id]);
        $draft->tags()->sync([$tag->id]);

        $this
            ->get('/posts/tag/'.$tag->slug)
            ->assertOk()
            ->assertSee('#Release')
            ->assertSee('Zichtbaar tag bericht')
            ->assertDontSee('Verborgen tag bericht');
    }

    public function test_tag_route_returns_not_found_for_inactive_or_empty_tags(): void
    {
        $this->storeSetting('general', 'default_locale', 'tn');
        $empty = $this->createTag([
            'title' => 'Empty',
            'slug' => 'empty-tag-'.uniqid(),
            'locale' => 'tn',
        ]);
        $inactive = $this->createTag([
            'title' => 'Inactive',
            'slug' => 'inactive-tag-'.uniqid(),
            'locale' => 'tn',
            'is_active' => false,
        ]);

        $this->get('/posts/tag/'.$empty->slug)->assertNotFound();
        $this->get('/posts/tag/'.$inactive->slug)->assertNotFound();
    }
}
