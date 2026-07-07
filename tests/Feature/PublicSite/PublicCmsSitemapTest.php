<?php

namespace Tests\Feature\PublicSite;

class PublicCmsSitemapTest extends PublicCmsTestCase
{
    public function test_sitemap_index_lists_only_sitemaps_with_public_urls(): void
    {
        $this->storeSetting('general', 'default_locale', 'sm');
        $this->createPage([
            'title' => 'Home',
            'slug' => 'home-'.uniqid(),
            'locale' => 'sm',
            'is_home' => true,
        ]);

        $this
            ->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<sitemapindex', false)
            ->assertSee(url('/sitemap-pages.xml'), false)
            ->assertDontSee(url('/sitemap-posts.xml'), false)
            ->assertDontSee(url('/sitemap-categories.xml'), false)
            ->assertDontSee(url('/sitemap-tags.xml'), false);
    }

    public function test_pages_sitemap_contains_homepage_and_nested_public_pages(): void
    {
        $this->storeSetting('general', 'default_locale', 'pg');
        $this->createPage([
            'title' => 'Home',
            'slug' => 'home-'.uniqid(),
            'locale' => 'pg',
            'is_home' => true,
        ]);
        $parent = $this->createPage([
            'title' => 'Diensten',
            'slug' => 'diensten-'.uniqid(),
            'locale' => 'pg',
        ]);
        $child = $this->createPage([
            'title' => 'Webdesign',
            'slug' => 'webdesign-'.uniqid(),
            'locale' => 'pg',
            'parent_id' => $parent->id,
        ]);
        $draft = $this->createPage([
            'title' => 'Concept',
            'slug' => 'concept-'.uniqid(),
            'locale' => 'pg',
            'status' => 'draft',
        ]);
        $future = $this->createPage([
            'title' => 'Toekomst',
            'slug' => 'toekomst-'.uniqid(),
            'locale' => 'pg',
            'published_at' => now()->addDay(),
        ]);
        $noindex = $this->createPage([
            'title' => 'Noindex',
            'slug' => 'noindex-'.uniqid(),
            'locale' => 'pg',
            'noindex' => true,
        ]);

        $this
            ->get('/sitemap-pages.xml')
            ->assertOk()
            ->assertSee('<urlset', false)
            ->assertSee('<loc>'.url('/').'</loc>', false)
            ->assertSee(url('/'.$parent->slug), false)
            ->assertSee(url('/'.$parent->slug.'/'.$child->slug), false)
            ->assertDontSee($draft->slug)
            ->assertDontSee($future->slug)
            ->assertDontSee($noindex->slug);
    }

    public function test_posts_sitemap_filters_non_indexable_posts(): void
    {
        $this->storeSetting('general', 'default_locale', 'ps');
        $post = $this->createPost([
            'title' => 'Publiek',
            'slug' => 'publiek-'.uniqid(),
            'locale' => 'ps',
        ]);
        $draft = $this->createPost([
            'title' => 'Concept',
            'slug' => 'concept-'.uniqid(),
            'locale' => 'ps',
            'status' => 'draft',
        ]);
        $future = $this->createPost([
            'title' => 'Toekomst',
            'slug' => 'toekomst-'.uniqid(),
            'locale' => 'ps',
            'published_at' => now()->addDay(),
        ]);
        $noindex = $this->createPost([
            'title' => 'Noindex',
            'slug' => 'noindex-'.uniqid(),
            'locale' => 'ps',
            'noindex' => true,
        ]);

        $this
            ->get('/sitemap-posts.xml')
            ->assertOk()
            ->assertSee('<loc>'.url('/posts').'</loc>', false)
            ->assertSee(url('/posts/'.$post->slug), false)
            ->assertDontSee($draft->slug)
            ->assertDontSee($future->slug)
            ->assertDontSee($noindex->slug);
    }

    public function test_category_and_tag_sitemaps_include_only_active_taxonomy_with_public_posts(): void
    {
        $this->storeSetting('general', 'default_locale', 'tx');
        $parent = $this->createCategory([
            'title' => 'Nieuws',
            'slug' => 'nieuws-'.uniqid(),
            'locale' => 'tx',
        ]);
        $child = $this->createCategory([
            'title' => 'School',
            'slug' => 'school-'.uniqid(),
            'locale' => 'tx',
            'parent_id' => $parent->id,
        ]);
        $empty = $this->createCategory([
            'title' => 'Leeg',
            'slug' => 'leeg-'.uniqid(),
            'locale' => 'tx',
        ]);
        $inactive = $this->createCategory([
            'title' => 'Inactief',
            'slug' => 'inactief-'.uniqid(),
            'locale' => 'tx',
            'is_active' => false,
        ]);
        $tag = $this->createTag([
            'title' => 'Release',
            'slug' => 'release-'.uniqid(),
            'locale' => 'tx',
        ]);
        $emptyTag = $this->createTag([
            'title' => 'Lege tag',
            'slug' => 'lege-tag-'.uniqid(),
            'locale' => 'tx',
        ]);
        $inactiveTag = $this->createTag([
            'title' => 'Inactieve tag',
            'slug' => 'inactieve-tag-'.uniqid(),
            'locale' => 'tx',
            'is_active' => false,
        ]);
        $post = $this->createPost([
            'title' => 'Taxonomy post',
            'slug' => 'taxonomy-post-'.uniqid(),
            'locale' => 'tx',
        ]);
        $post->categories()->sync([$child->id, $inactive->id]);
        $post->tags()->sync([$tag->id, $inactiveTag->id]);

        $this
            ->get('/sitemap-categories.xml')
            ->assertOk()
            ->assertSee(url('/posts/category/'.$parent->slug), false)
            ->assertSee(url('/posts/category/'.$parent->slug.'/'.$child->slug), false)
            ->assertDontSee($empty->slug)
            ->assertDontSee($inactive->slug);

        $this
            ->get('/sitemap-tags.xml')
            ->assertOk()
            ->assertSee(url('/posts/tag/'.$tag->slug), false)
            ->assertDontSee($emptyTag->slug)
            ->assertDontSee($inactiveTag->slug);
    }

    public function test_global_noindex_hides_all_sitemap_urls(): void
    {
        $this->storeSetting('general', 'default_locale', 'gn');
        $this->storeSetting('seo', 'global_noindex', true);
        $page = $this->createPage([
            'title' => 'Home',
            'slug' => 'home-'.uniqid(),
            'locale' => 'gn',
            'is_home' => true,
        ]);
        $post = $this->createPost([
            'title' => 'Post',
            'slug' => 'post-'.uniqid(),
            'locale' => 'gn',
        ]);

        $this
            ->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('<sitemapindex', false)
            ->assertDontSee('sitemap-pages.xml')
            ->assertDontSee('sitemap-posts.xml');

        $this
            ->get('/sitemap-pages.xml')
            ->assertOk()
            ->assertDontSee($page->slug);

        $this
            ->get('/sitemap-posts.xml')
            ->assertOk()
            ->assertDontSee($post->slug);
    }
}
