<?php

namespace Tests\Feature\PublicSite;

class PublicCmsPostRenderingTest extends PublicCmsTestCase
{
    public function test_public_post_renders_published_post_with_featured_image_and_taxonomy(): void
    {
        $this->storeSetting('general', 'default_locale', 'pp');

        $media = $this->createMediaAsset([
            'path' => 'cms/media/post-'.uniqid().'.jpg',
            'alt_text' => 'Post afbeelding',
            'caption' => 'Publieke afbeelding',
        ]);
        $category = $this->createCategory([
            'title' => 'Nieuws',
            'locale' => 'pp',
        ]);
        $tag = $this->createTag([
            'title' => 'Release',
            'locale' => 'pp',
        ]);
        $post = $this->createPost([
            'title' => 'Publieke post',
            'slug' => 'publieke-post-'.uniqid(),
            'locale' => 'pp',
            'featured_media_asset_id' => $media->id,
            'content_blocks' => [
                ['width_mode' => 'display', 'title' => 'Post intro', 'text' => 'Dit is publieke postcontent.'],
            ],
            'published_at' => now()->subDay(),
        ]);
        $post->categories()->sync([$category->id]);
        $post->tags()->sync([$tag->id]);

        $this
            ->get(route('cms.public.posts.show', ['slug' => $post->slug]))
            ->assertOk()
            ->assertSee('Publieke post')
            ->assertSee('Dit is publieke postcontent.')
            ->assertSee('class="rw-public-content-block"', false)
            ->assertDontSee('rw-public-placement--display-width', false)
            ->assertDontSee('data-width-mode="display"', false)
            ->assertSee('<img', false)
            ->assertSee('Post afbeelding')
            ->assertSee('Nieuws')
            ->assertSee('#Release');

        $draft = $this->createPost([
            'slug' => 'concept-post-'.uniqid(),
            'locale' => 'pp',
            'status' => 'draft',
        ]);
        $future = $this->createPost([
            'slug' => 'toekomst-post-'.uniqid(),
            'locale' => 'pp',
            'published_at' => now()->addDay(),
        ]);

        $this->get(route('cms.public.posts.show', ['slug' => $draft->slug]))->assertNotFound();
        $this->get(route('cms.public.posts.show', ['slug' => $future->slug]))->assertNotFound();
    }

    public function test_public_post_renders_blogposting_json_ld(): void
    {
        $this->storeSetting('general', 'default_locale', 'bp');
        $post = $this->createPost([
            'title' => 'Schema post',
            'slug' => 'schema-post-'.uniqid(),
            'locale' => 'bp',
            'excerpt' => 'Schema post intro.',
            'published_at' => now()->subDay(),
            'settings' => [
                'structured_data_extra' => json_encode([
                    '@type' => 'SpeakableSpecification',
                    'cssSelector' => ['.rw-public-title'],
                ], JSON_THROW_ON_ERROR),
            ],
        ]);

        $this
            ->get(route('cms.public.posts.show', ['slug' => $post->slug]))
            ->assertOk()
            ->assertSee('<script type="application/ld+json">', false)
            ->assertSee('"@type": "BlogPosting"', false)
            ->assertSee('"headline": "Schema post"', false)
            ->assertSee('"@type": "SpeakableSpecification"', false);
    }

    public function test_public_post_pdf_requires_opt_in_and_returns_pdf(): void
    {
        $this->storeSetting('general', 'default_locale', 'pf');
        $post = $this->createPost([
            'title' => 'Downloadbare post',
            'slug' => 'downloadbare-post-'.uniqid(),
            'locale' => 'pf',
            'excerpt' => 'Deze post kan als PDF gedownload worden.',
            'settings' => [
                'pdf_download_enabled' => false,
            ],
        ]);

        $this->get('/blogs/'.$post->slug.'.pdf')->assertNotFound();

        $post->forceFill([
            'settings' => [
                'pdf_download_enabled' => true,
            ],
        ])->save();

        $this
            ->get('/blogs/'.$post->slug.'.pdf')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_public_posts_index_lists_only_published_posts_for_default_locale(): void
    {
        $this->storeSetting('general', 'default_locale', 'ix');
        $older = $this->createPost([
            'title' => 'Ouder bericht',
            'slug' => 'ouder-bericht-'.uniqid(),
            'locale' => 'ix',
            'excerpt' => 'Ouder zichtbaar bericht.',
            'published_at' => now()->subDays(2),
        ]);
        $newer = $this->createPost([
            'title' => 'Nieuw bericht',
            'slug' => 'nieuw-bericht-'.uniqid(),
            'locale' => 'ix',
            'excerpt' => 'Nieuw zichtbaar bericht.',
            'published_at' => now()->subDay(),
        ]);
        $draft = $this->createPost([
            'title' => 'Concept bericht',
            'slug' => 'concept-index-'.uniqid(),
            'locale' => 'ix',
            'status' => 'draft',
        ]);
        $future = $this->createPost([
            'title' => 'Toekomst bericht',
            'slug' => 'toekomst-index-'.uniqid(),
            'locale' => 'ix',
            'published_at' => now()->addDay(),
        ]);
        $otherLocale = $this->createPost([
            'title' => 'Andere locale',
            'slug' => 'andere-locale-'.uniqid(),
            'locale' => 'yy',
        ]);

        $this
            ->get(route('cms.public.posts.index'))
            ->assertOk()
            ->assertSeeInOrder(['Nieuw bericht', 'Ouder bericht'])
            ->assertSee('/posts/'.$newer->slug, false)
            ->assertSee('/posts/'.$older->slug, false)
            ->assertDontSee('Concept bericht')
            ->assertDontSee('Toekomst bericht')
            ->assertDontSee('Andere locale');

        $this->assertNotSame($draft->id, $newer->id);
        $this->assertNotSame($future->id, $newer->id);
        $this->assertNotSame($otherLocale->id, $newer->id);
    }
}
