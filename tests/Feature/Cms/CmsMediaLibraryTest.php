<?php

namespace Tests\Feature\Cms;

use App\Actions\Admin\Cms\Health\BuildCmsHealthReportAction;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Cms\CmsMediaAsset;
use App\Models\Cms\CmsMediaFolder;
use App\Models\Cms\CmsPost;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CmsMediaLibraryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $mysqlConnection = config('database.connections.mysql');

        config([
            'database.default' => 'tenant',
            'database.connections.central' => array_merge($mysqlConnection, ['database' => 'rwsoft']),
            'database.connections.tenant' => array_merge($mysqlConnection, ['database' => 'rwsoft_site_rwsoft']),
        ]);

        DB::purge('central');
        DB::purge('tenant');
        DB::reconnect('central');
        DB::reconnect('tenant');
        DB::connection('central')->beginTransaction();
        DB::connection('tenant')->beginTransaction();
        Storage::fake('public');
        Storage::fake('private');
        $this->withoutMiddleware();
    }

    protected function tearDown(): void
    {
        if (DB::connection('tenant')->transactionLevel() > 0) {
            DB::connection('tenant')->rollBack();
        }

        if (DB::connection('central')->transactionLevel() > 0) {
            DB::connection('central')->rollBack();
        }

        Config::set('database.default', 'mysql');

        parent::tearDown();
    }

    public function test_media_index_renders_inertia_page(): void
    {
        $user = $this->createAdminUser();

        $this
            ->actingAs($user)
            ->get(route('admin.cms.media.index'), $this->inertiaHeaders('/admin/cms/media'))
            ->assertOk()
            ->assertJsonPath('component', 'Admin/Cms/Media/Index');
    }

    public function test_valid_image_upload_creates_media_asset_and_file(): void
    {
        $user = $this->createAdminUser();
        $file = UploadedFile::fake()->image('hero.jpg', 800, 450)->size(120);

        $this
            ->actingAs($user)
            ->post(route('admin.cms.media.store'), [
                'file' => $file,
                'alt_text' => 'Hero afbeelding',
                'caption' => 'Homepage hero',
            ])
            ->assertRedirect(route('admin.cms.media.index'))
            ->assertSessionHas('status')
            ->assertSessionDoesntHaveErrors();

        $asset = CmsMediaAsset::query()->where('original_filename', 'hero.jpg')->first();

        $this->assertNotNull($asset);
        $this->assertSame($user->id, $asset?->uploaded_by);
        $this->assertSame('Hero afbeelding', $asset?->alt_text);
        $this->assertSame('Homepage hero', $asset?->caption);
        $this->assertSame(800, $asset?->width);
        $this->assertSame(450, $asset?->height);
        $this->assertNotEmpty($asset?->hash);
        Storage::disk('public')->assertExists((string) $asset?->path);
    }

    public function test_media_folder_can_be_created(): void
    {
        $user = $this->createAdminUser();

        $this
            ->actingAs($user)
            ->post(route('admin.cms.media-folders.store'), [
                'name' => 'Nieuws afbeeldingen',
            ])
            ->assertRedirect(route('admin.cms.media.index'))
            ->assertSessionHas('status')
            ->assertSessionDoesntHaveErrors();

        $folder = CmsMediaFolder::query()->where('slug', 'nieuws-afbeeldingen')->first();

        $this->assertNotNull($folder);
        $this->assertSame('Nieuws afbeeldingen', $folder?->name);
    }

    public function test_media_subfolder_can_be_created_with_json_response(): void
    {
        $user = $this->createAdminUser();
        $parent = $this->createMediaFolder(['name' => 'Parent', 'slug' => 'parent-json']);

        $this
            ->actingAs($user)
            ->post(route('admin.cms.media-folders.store'), [
                'name' => 'Submap JSON',
                'parent_id' => $parent->id,
            ], [
                'Accept' => 'application/json',
            ])
            ->assertOk()
            ->assertJsonPath('folder.name', 'Submap JSON')
            ->assertJsonPath('folder.parent_id', $parent->id);

        $this->assertDatabaseHas('cms_media_folders', [
            'name' => 'Submap JSON',
            'parent_id' => $parent->id,
        ]);
    }

    public function test_media_folder_can_be_renamed(): void
    {
        $user = $this->createAdminUser();
        $folder = $this->createMediaFolder(['name' => 'Oude map', 'slug' => 'oude-map']);

        $this
            ->actingAs($user)
            ->patch(route('admin.cms.media-folders.update', ['folder' => $folder->id]), [
                'name' => 'Nieuwe map',
            ], [
                'Accept' => 'application/json',
            ])
            ->assertOk()
            ->assertJsonPath('folder.name', 'Nieuwe map')
            ->assertJsonPath('folder.slug', 'nieuwe-map');

        $folder->refresh();

        $this->assertSame('Nieuwe map', $folder->name);
        $this->assertSame('nieuwe-map', $folder->slug);
    }

    public function test_media_folder_can_be_moved_to_another_parent(): void
    {
        $user = $this->createAdminUser();
        $parent = $this->createMediaFolder(['name' => 'Parent', 'slug' => 'parent']);
        $folder = $this->createMediaFolder(['name' => 'Child', 'slug' => 'child']);

        $this
            ->actingAs($user)
            ->patch(route('admin.cms.media-folders.move', ['folder' => $folder->id]), [
                'parent_id' => $parent->id,
            ], [
                'Accept' => 'application/json',
            ])
            ->assertOk()
            ->assertJsonPath('folder.parent_id', $parent->id);

        $folder->refresh();

        $this->assertSame($parent->id, $folder->parent_id);
    }

    public function test_media_folder_move_blocks_cycles(): void
    {
        $user = $this->createAdminUser();
        $parent = $this->createMediaFolder(['name' => 'Parent', 'slug' => 'parent']);
        $child = $this->createMediaFolder([
            'parent_id' => $parent->id,
            'name' => 'Child',
            'slug' => 'child',
        ]);

        $this
            ->actingAs($user)
            ->patch(route('admin.cms.media-folders.move', ['folder' => $parent->id]), [
                'parent_id' => $child->id,
            ], [
                'Accept' => 'application/json',
            ])
            ->assertUnprocessable()
            ->assertInvalid(['parent_id']);
    }

    public function test_image_upload_can_be_assigned_to_folder(): void
    {
        $user = $this->createAdminUser();
        $folder = $this->createMediaFolder();

        $this
            ->actingAs($user)
            ->post(route('admin.cms.media.store'), [
                'file' => UploadedFile::fake()->image('folder-image.jpg', 800, 450)->size(120),
                'folder_id' => $folder->id,
            ])
            ->assertRedirect(route('admin.cms.media.index'))
            ->assertSessionHas('status')
            ->assertSessionDoesntHaveErrors();

        $asset = CmsMediaAsset::query()->where('original_filename', 'folder-image.jpg')->first();

        $this->assertNotNull($asset);
        $this->assertSame($folder->id, $asset?->folder_id);
    }

    public function test_invalid_file_type_is_rejected(): void
    {
        $user = $this->createAdminUser();

        $this
            ->actingAs($user)
            ->from(route('admin.cms.media.index'))
            ->post(route('admin.cms.media.store'), [
                'file' => UploadedFile::fake()->create('document.txt', 10, 'text/plain'),
            ], [
                'Accept' => 'application/json',
            ])
            ->assertUnprocessable()
            ->assertInvalid(['file']);
    }

    public function test_svg_upload_is_rejected(): void
    {
        $user = $this->createAdminUser();

        $this
            ->actingAs($user)
            ->from(route('admin.cms.media.index'))
            ->post(route('admin.cms.media.store'), [
                'file' => UploadedFile::fake()->create('vector.svg', 10, 'image/svg+xml'),
            ], [
                'Accept' => 'application/json',
            ])
            ->assertUnprocessable()
            ->assertInvalid(['file']);
    }

    public function test_json_image_upload_creates_media_asset_and_returns_payload(): void
    {
        $user = $this->createAdminUser();
        $folder = $this->createMediaFolder();

        $response = $this
            ->actingAs($user)
            ->post(route('admin.cms.media.store'), [
                'file' => UploadedFile::fake()->image('json-hero.jpg', 800, 450)->size(120),
                'folder_id' => $folder->id,
            ], [
                'Accept' => 'application/json',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('asset.original_filename', 'json-hero.jpg')
            ->assertJsonPath('asset.folder_id', $folder->id)
            ->assertJsonPath('asset.width', 800)
            ->assertJsonPath('asset.height', 450)
            ->assertJsonPath('already_exists', false);

        $asset = CmsMediaAsset::query()
            ->where('original_filename', 'json-hero.jpg')
            ->first();

        $this->assertNotNull($asset);
        $this->assertSame('public', $asset?->disk);
        $this->assertSame($user->id, $asset?->uploaded_by);
        Storage::disk('public')->assertExists((string) $asset?->path);
    }

    public function test_json_image_upload_accepts_cms_settings_context(): void
    {
        $user = $this->createAdminUser();

        $this
            ->actingAs($user)
            ->post(route('admin.cms.media.store'), [
                'file' => UploadedFile::fake()->image('settings-logo.jpg', 800, 450)->size(120),
                'uploaded_from' => 'cms_settings_company_logo',
                'context_type' => 'cms_settings',
            ], [
                'Accept' => 'application/json',
            ])
            ->assertOk()
            ->assertJsonPath('asset.original_filename', 'settings-logo.jpg')
            ->assertJsonPath('already_exists', false);

        $asset = CmsMediaAsset::query()
            ->where('original_filename', 'settings-logo.jpg')
            ->first();

        $this->assertNotNull($asset);
        $this->assertSame('cms_settings_company_logo', $asset?->metadata['uploaded_from'] ?? null);
        $this->assertSame('cms_settings', $asset?->metadata['context']['type'] ?? null);
        Storage::disk('public')->assertExists((string) $asset?->path);
    }

    public function test_json_duplicate_upload_reuses_existing_asset(): void
    {
        $user = $this->createAdminUser();
        $file = UploadedFile::fake()->image('json-duplicate.jpg', 640, 360)->size(80);
        $hash = hash_file('sha256', (string) $file->getRealPath());
        $existingAsset = $this->createMediaAsset(['hash' => $hash]);
        $countBefore = CmsMediaAsset::query()->count();

        $response = $this
            ->actingAs($user)
            ->post(route('admin.cms.media.store'), [
                'file' => $file,
            ], [
                'Accept' => 'application/json',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('asset.id', $existingAsset->id)
            ->assertJsonPath('already_exists', true);

        $this->assertSame($countBefore, CmsMediaAsset::query()->count());
    }

    public function test_edited_media_copy_accepts_cms_settings_context(): void
    {
        $user = $this->createAdminUser();
        $sourcePath = 'cms/media/settings-source.jpg';
        $sourceImage = UploadedFile::fake()->image('settings-source.jpg', 640, 360)->size(80);
        $sourceAsset = $this->createMediaAsset([
            'path' => $sourcePath,
            'filename' => 'settings-source.jpg',
            'original_filename' => 'settings-source.jpg',
            'hash' => hash_file('sha256', (string) $sourceImage->getRealPath()),
            'width' => 640,
            'height' => 360,
        ]);
        Storage::disk('public')->put($sourcePath, file_get_contents((string) $sourceImage->getRealPath()));

        $this
            ->actingAs($user)
            ->post(route('admin.cms.media.edit-copy', ['id' => $sourceAsset->id]), [
                'context_type' => 'cms_settings',
                'crop' => [
                    'x' => 0,
                    'y' => 0,
                    'width' => 320,
                    'height' => 180,
                ],
            ], [
                'Accept' => 'application/json',
            ])
            ->assertOk()
            ->assertJsonPath('asset.context_type', 'cms_settings')
            ->assertJsonPath('asset.source_media_asset_id', $sourceAsset->id);

        $editedAsset = CmsMediaAsset::query()
            ->where('source_media_asset_id', $sourceAsset->id)
            ->first();

        $this->assertNotNull($editedAsset);
        $this->assertSame('cms_settings', $editedAsset?->context_type);
        $this->assertNull($editedAsset?->context_id);
        Storage::disk('public')->assertExists((string) $editedAsset?->path);
    }

    public function test_duplicate_upload_redirects_to_existing_asset(): void
    {
        $user = $this->createAdminUser();
        $file = UploadedFile::fake()->image('duplicate.jpg', 640, 360)->size(80);
        $hash = hash_file('sha256', (string) $file->getRealPath());
        $existingAsset = $this->createMediaAsset(['hash' => $hash]);
        $countBefore = CmsMediaAsset::query()->count();

        $this
            ->actingAs($user)
            ->post(route('admin.cms.media.store'), ['file' => $file])
            ->assertRedirect(route('admin.cms.media.edit', ['id' => $existingAsset->id]))
            ->assertSessionHas('warning');

        $this->assertSame($countBefore, CmsMediaAsset::query()->count());
    }

    public function test_media_metadata_can_be_updated(): void
    {
        $user = $this->createAdminUser();
        $asset = $this->createMediaAsset();
        $folder = $this->createMediaFolder(['name' => 'Banners', 'slug' => 'banners']);

        $this
            ->actingAs($user)
            ->post(route('admin.cms.media.update', ['id' => $asset->id]), [
                'folder_id' => $folder->id,
                'alt_text' => 'Bijgewerkte alt tekst',
                'caption' => 'Bijgewerkt bijschrift',
                'sort_order' => 25,
            ])
            ->assertRedirect(route('admin.cms.media.index'))
            ->assertSessionHas('status')
            ->assertSessionDoesntHaveErrors();

        $asset->refresh();

        $this->assertSame($folder->id, $asset->folder_id);
        $this->assertSame('Bijgewerkte alt tekst', $asset->alt_text);
        $this->assertSame('Bijgewerkt bijschrift', $asset->caption);
        $this->assertSame(25, $asset->sort_order);
    }

    public function test_media_metadata_can_be_updated_with_json_endpoint(): void
    {
        $user = $this->createAdminUser();
        $asset = $this->createMediaAsset();
        $folder = $this->createMediaFolder(['name' => 'Dialog folder', 'slug' => 'dialog-folder']);

        $this
            ->actingAs($user)
            ->patch(route('admin.cms.media.metadata', ['id' => $asset->id]), [
                'folder_id' => $folder->id,
                'translations' => [
                    'nl' => [
                        'alt_text' => 'Nederlandse alt tekst',
                        'caption' => 'Nederlands bijschrift',
                    ],
                    'en' => [
                        'alt_text' => 'English alt text',
                        'caption' => 'English caption',
                    ],
                ],
                'sort_order' => 35,
            ], [
                'Accept' => 'application/json',
            ])
            ->assertOk()
            ->assertJsonPath('asset.id', $asset->id)
            ->assertJsonPath('asset.folder_id', $folder->id)
            ->assertJsonPath('asset.alt_text', 'Nederlandse alt tekst')
            ->assertJsonPath('asset.caption', 'Nederlands bijschrift')
            ->assertJsonPath('asset.translations.nl.alt_text', 'Nederlandse alt tekst')
            ->assertJsonPath('asset.translations.en.alt_text', 'English alt text');

        $asset->refresh();

        $this->assertSame($folder->id, $asset->folder_id);
        $this->assertSame('Nederlandse alt tekst', $asset->alt_text);
        $this->assertSame('Nederlands bijschrift', $asset->caption);
        $this->assertSame(35, $asset->sort_order);
        $this->assertDatabaseHas('cms_media_asset_translations', [
            'cms_media_asset_id' => $asset->id,
            'locale' => 'en',
            'alt_text' => 'English alt text',
        ]);
    }

    public function test_media_assets_can_be_sorted(): void
    {
        $user = $this->createAdminUser();
        $first = $this->createMediaAsset(['sort_order' => 10]);
        $second = $this->createMediaAsset(['sort_order' => 20]);

        $this
            ->actingAs($user)
            ->post(route('admin.cms.media.sort'), [
                'items' => [
                    ['id' => $first->id, 'sort_order' => 30],
                    ['id' => $second->id, 'sort_order' => 10],
                ],
            ], [
                'Accept' => 'application/json',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        $first->refresh();
        $second->refresh();

        $this->assertSame(30, $first->sort_order);
        $this->assertSame(10, $second->sort_order);
    }

    public function test_cms_health_reports_missing_media_alt_text(): void
    {
        $asset = $this->createMediaAsset([
            'alt_text' => null,
            'original_filename' => 'missing-alt.jpg',
        ]);

        $report = app(BuildCmsHealthReportAction::class)->handle();
        $issue = collect($report['issues'])->firstWhere('record_id', $asset->id);

        $this->assertNotNull($issue);
        $this->assertSame('media', $issue['category']);
        $this->assertSame('warning', $issue['severity']);
        $this->assertSame('cms_media_asset', $issue['record_type']);
        $this->assertSame(__('cms_admin_ui.health.issues.media_missing_alt_text'), $issue['message']);
    }

    public function test_cms_health_reports_missing_media_translation_alt_text(): void
    {
        DB::table('cms_languages')->update(['is_active' => false]);

        DB::table('cms_settings')->updateOrInsert(
            ['group' => 'general', 'key' => 'multilingual_enabled'],
            [
                'label' => 'Multilingual enabled',
                'type' => 'boolean',
                'value' => json_encode(['value' => true]),
                'is_public' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        DB::table('cms_settings')->updateOrInsert(
            ['group' => 'general', 'key' => 'default_locale'],
            [
                'label' => 'Default locale',
                'type' => 'text',
                'value' => json_encode(['value' => 'nl']),
                'is_public' => true,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        DB::table('cms_languages')->updateOrInsert(
            ['locale' => 'nl'],
            [
                'name' => 'Nederlands',
                'native_name' => 'Nederlands',
                'direction' => 'ltr',
                'is_active' => true,
                'sort_order' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        DB::table('cms_languages')->updateOrInsert(
            ['locale' => 'en'],
            [
                'name' => 'English',
                'native_name' => 'English',
                'direction' => 'ltr',
                'is_active' => true,
                'sort_order' => 20,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $asset = $this->createMediaAsset([
            'alt_text' => 'Nederlandse alt tekst',
            'original_filename' => 'missing-translation-alt.jpg',
        ]);
        $asset->translations()->create([
            'locale' => 'nl',
            'alt_text' => 'Nederlandse alt tekst',
            'caption' => null,
        ]);

        $report = app(BuildCmsHealthReportAction::class)->handle();
        $issue = collect($report['issues'])
            ->where('record_id', $asset->id)
            ->firstWhere('message', __('cms_admin_ui.health.issues.media_missing_translation_alt_text', ['locales' => 'en']));

        $this->assertNotNull($issue);
        $this->assertSame('media', $issue['category']);
        $this->assertSame('warning', $issue['severity']);
        $this->assertSame('cms_media_asset', $issue['record_type']);
    }

    public function test_media_delete_soft_deletes_asset_and_removes_file(): void
    {
        $user = $this->createAdminUser();
        $asset = $this->createMediaAsset();
        Storage::disk('public')->assertExists($asset->path);

        $this
            ->actingAs($user)
            ->delete(route('admin.cms.media.destroy', ['id' => $asset->id]))
            ->assertRedirect(route('admin.cms.media.index'))
            ->assertSessionHas('status');

        $deletedAsset = CmsMediaAsset::withTrashed()->find($asset->id);

        $this->assertNotNull($deletedAsset);
        $this->assertTrue((bool) $deletedAsset?->trashed());
        Storage::disk('public')->assertMissing($asset->path);
    }

    public function test_media_delete_removes_asset_directory_with_variants(): void
    {
        $user = $this->createAdminUser();
        $assetDirectory = 'cms/media/site-43/assets/delete-with-variants-'.uniqid();
        $originalPath = $assetDirectory.'/original/original-test.jpg';
        $variantPaths = [
            $assetDirectory.'/variants/320-test.webp',
            $assetDirectory.'/variants/640-test.webp',
        ];
        $asset = $this->createMediaAsset([
            'path' => $originalPath,
            'metadata' => [
                'variants' => [
                    'webp' => [
                        '320' => ['path' => $variantPaths[0], 'width' => 320, 'height' => 180, 'size_bytes' => 1200],
                        '640' => ['path' => $variantPaths[1], 'width' => 640, 'height' => 360, 'size_bytes' => 2400],
                    ],
                ],
            ],
        ]);

        foreach ($variantPaths as $variantPath) {
            Storage::disk('public')->put($variantPath, 'fake-webp-variant');
            Storage::disk('public')->assertExists($variantPath);
        }

        $this
            ->actingAs($user)
            ->delete(route('admin.cms.media.destroy', ['id' => $asset->id]))
            ->assertRedirect(route('admin.cms.media.index'))
            ->assertSessionHas('status');

        $deletedAsset = CmsMediaAsset::withTrashed()->find($asset->id);

        $this->assertNotNull($deletedAsset);
        $this->assertTrue((bool) $deletedAsset?->trashed());
        Storage::disk('public')->assertMissing($originalPath);
        foreach ($variantPaths as $variantPath) {
            Storage::disk('public')->assertMissing($variantPath);
        }
        $this->assertSame([], Storage::disk('public')->allFiles($assetDirectory));
    }

    public function test_media_delete_can_return_json_response(): void
    {
        $user = $this->createAdminUser();
        $asset = $this->createMediaAsset();
        Storage::disk('public')->assertExists($asset->path);

        $this
            ->actingAs($user)
            ->delete(route('admin.cms.media.destroy', ['id' => $asset->id]), [], [
                'Accept' => 'application/json',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'deleted');

        $this->assertNotNull(CmsMediaAsset::withTrashed()->find($asset->id));
        Storage::disk('public')->assertMissing($asset->path);
    }

    public function test_post_can_store_featured_media_asset(): void
    {
        $user = $this->createAdminUser();
        $asset = $this->createMediaAsset();
        $slug = 'media-test-bericht-'.uniqid();

        $this
            ->actingAs($user)
            ->post(route('admin.cms.posts.store', ['id' => 0]), $this->postPayload([
                'slug' => $slug,
                'featured_media_asset_id' => $asset->id,
            ]))
            ->assertRedirect(route('admin.cms.posts.index'))
            ->assertSessionHas('status')
            ->assertSessionDoesntHaveErrors();

        $post = CmsPost::query()->where('slug', $slug)->first();

        $this->assertNotNull($post);
        $featuredAsset = CmsMediaAsset::query()->find($post?->featured_media_asset_id);

        $this->assertNotNull($featuredAsset);
        $this->assertTrue(
            (int) $featuredAsset?->id === (int) $asset->id
            || (int) $featuredAsset?->source_media_asset_id === (int) $asset->id
        );
    }

    public function test_post_form_receives_media_options_with_preview_url(): void
    {
        $user = $this->createAdminUser();
        $asset = $this->createMediaAsset([
            'original_filename' => 'preview.jpg',
            'alt_text' => 'Preview afbeelding',
            'caption' => 'Preview bijschrift',
            'width' => 1280,
            'height' => 720,
        ]);

        $this
            ->actingAs($user)
            ->get(route('admin.cms.posts.create'), $this->inertiaHeaders('/admin/cms/posts/create'))
            ->assertOk()
            ->assertJsonPath('component', 'Admin/Cms/Posts/Edit')
            ->assertJsonPath('props.mediaOptions.0.id', $asset->id)
            ->assertJsonPath('props.mediaOptions.0.original_filename', 'preview.jpg')
            ->assertJsonPath('props.mediaOptions.0.alt_text', 'Preview afbeelding')
            ->assertJsonPath('props.mediaOptions.0.caption', 'Preview bijschrift')
            ->assertJsonPath('props.mediaOptions.0.width', 1280)
            ->assertJsonPath('props.mediaOptions.0.height', 720)
            ->assertJsonPath('props.mediaOptions.0.url', Storage::disk('public')->url($asset->path));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createMediaFolder(array $overrides = []): CmsMediaFolder
    {
        return CmsMediaFolder::query()->create(array_merge([
            'name' => 'Test map',
            'slug' => 'test-map-'.uniqid(),
            'sort_order' => 1,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createMediaAsset(array $overrides = []): CmsMediaAsset
    {
        $path = $overrides['path'] ?? 'cms/media/test-'.uniqid().'.jpg';
        Storage::disk('public')->put($path, 'fake-image-content');

        return CmsMediaAsset::query()->create(array_merge([
            'disk' => 'public',
            'visibility' => 'public',
            'path' => $path,
            'filename' => basename($path),
            'original_filename' => basename($path),
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => 1024,
            'width' => 640,
            'height' => 360,
            'hash' => hash('sha256', $path),
            'alt_text' => 'Test afbeelding',
            'caption' => null,
            'sort_order' => 1,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function postPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Media test bericht',
            'slug' => 'media-test-bericht',
            'locale' => 'nl',
            'status' => 'draft',
            'excerpt' => null,
            'content_blocks' => [],
            'featured_media_asset_id' => null,
            'seo_title' => null,
            'seo_description' => null,
            'canonical_url' => null,
            'og_image_path' => null,
            'noindex' => false,
            'is_featured' => false,
            'is_searchable' => true,
            'published_at' => null,
            'category_ids' => [],
            'tag_ids' => [],
        ], $overrides);
    }

    /**
     * @return array<string, string>
     */
    private function inertiaHeaders(string $path): array
    {
        $request = Request::create($path, 'GET');
        $version = app(HandleInertiaRequests::class)->version($request);

        return [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Inertia-Version' => (string) ($version ?? ''),
        ];
    }

    private function createAdminUser(): User
    {
        $user = User::factory()->create([
            'is_platform_admin' => true,
            'two_factor_secret' => encrypt('cms-media-library-test-secret'),
            'two_factor_confirmed_at' => now(),
        ]);

        $roleId = DB::table('acl_roles')->where('key', 'super_admin')->value('id');

        if (! $roleId) {
            $roleId = DB::table('acl_roles')->insertGetId([
                'key' => 'super_admin',
                'name' => 'Super administrator',
                'description' => 'Test super admin role',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('acl_role_user')->updateOrInsert(
            [
                'user_id' => $user->id,
                'acl_role_id' => $roleId,
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return $user;
    }
}
