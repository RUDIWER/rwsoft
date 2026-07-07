<?php

namespace Tests\Feature\Cms;

use App\Http\Middleware\EnsureSiteMembership;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveTenantSite;
use App\Models\Cms\CmsLanguage;
use App\Models\Cms\CmsMenu;
use App\Models\Cms\CmsMenuItem;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPost;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CmsMenuBackofficeTest extends TestCase
{
    /**
     * @var array<int, int>
     */
    private array $createdUserIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'tenant',
            'database.connections.mysql.database' => 'rwsoft',
            'database.connections.central.database' => 'rwsoft',
            'database.connections.central.driver' => 'mysql',
            'database.connections.central.host' => config('database.connections.mysql.host'),
            'database.connections.central.port' => config('database.connections.mysql.port'),
            'database.connections.central.username' => config('database.connections.mysql.username'),
            'database.connections.central.password' => config('database.connections.mysql.password'),
            'database.connections.tenant.driver' => 'mysql',
            'database.connections.tenant.host' => config('database.connections.mysql.host'),
            'database.connections.tenant.port' => config('database.connections.mysql.port'),
            'database.connections.tenant.database' => 'rwsoft_site_rwsoft',
            'database.connections.tenant.username' => config('database.connections.mysql.username'),
            'database.connections.tenant.password' => config('database.connections.mysql.password'),
        ]);

        DB::purge('mysql');
        DB::purge('central');
        DB::purge('tenant');
        DB::reconnect('mysql');
        DB::reconnect('central');
        DB::reconnect('tenant');
        DB::setDefaultConnection('tenant');
        DB::beginTransaction();

        $this->withoutMiddleware([
            ResolveTenantSite::class,
            EnsureSiteMembership::class,
        ]);
    }

    protected function tearDown(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        if ($this->createdUserIds !== []) {
            DB::connection('central')->table('acl_role_user')
                ->whereIn('user_id', $this->createdUserIds)
                ->delete();
            DB::connection('central')->table('users')
                ->whereIn('id', $this->createdUserIds)
                ->delete();
        }

        parent::tearDown();
    }

    public function test_menu_pages_render_inertia_pages(): void
    {
        $user = $this->createAdminUser();
        $menu = $this->createMenu();

        $this
            ->actingAs($user)
            ->get(route('admin.cms.menus.index'), $this->inertiaHeaders('/admin/cms/menus'))
            ->assertOk()
            ->assertJsonPath('component', 'Admin/Cms/Menus/Index');

        $this
            ->actingAs($user)
            ->get(route('admin.cms.menus.create'), $this->inertiaHeaders('/admin/cms/menus/create'))
            ->assertOk()
            ->assertJsonPath('component', 'Admin/Cms/Menus/Edit');

        $this
            ->actingAs($user)
            ->get(route('admin.cms.menus.edit', ['id' => $menu->id]), $this->inertiaHeaders('/admin/cms/menus/'.$menu->id.'/edit'))
            ->assertOk()
            ->assertJsonPath('component', 'Admin/Cms/Menus/Edit')
            ->assertJsonPath('props.menu.id', $menu->id);
    }

    public function test_menu_can_be_stored(): void
    {
        $user = $this->createAdminUser();
        $location = 'header-'.uniqid();

        $this
            ->actingAs($user)
            ->post(route('admin.cms.menus.store', ['id' => 0]), $this->menuPayload([
                'title' => 'Hoofdmenu',
                'location' => $location,
                'translations' => [
                    'nl' => ['title' => 'Hoofdmenu'],
                ],
            ]))
            ->assertRedirect()
            ->assertSessionHas('status')
            ->assertSessionDoesntHaveErrors();

        $menu = CmsMenu::query()->where('location', $location)->first();

        $this->assertNotNull($menu);
        $this->assertStringStartsWith('header-', (string) $menu?->location);
        $this->assertTrue((bool) $menu?->is_active);
        $this->assertDatabaseHas('cms_menu_translations', [
            'cms_menu_id' => $menu?->id,
            'locale' => 'nl',
            'title' => 'Hoofdmenu',
        ]);
    }

    public function test_menu_can_be_stored_for_content_placement(): void
    {
        $user = $this->createAdminUser();
        $title = 'Content menu '.uniqid();

        $this
            ->actingAs($user)
            ->post(route('admin.cms.menus.store', ['id' => 0]), $this->menuPayload([
                'title' => $title,
                'placements' => ['content'],
                'translations' => [
                    'nl' => ['title' => $title],
                ],
            ]))
            ->assertRedirect()
            ->assertSessionHas('status')
            ->assertSessionDoesntHaveErrors();

        $menu = CmsMenu::query()->where('title', $title)->first();

        $this->assertNotNull($menu);
        $this->assertSame(['content'], $menu?->placements);

        $this
            ->actingAs($user)
            ->get(route('admin.cms.menus.edit', ['id' => $menu?->id]), $this->inertiaHeaders('/admin/cms/menus/'.$menu?->id.'/edit'))
            ->assertOk()
            ->assertJsonPath('props.menuPlacementOptions.0.value', 'content')
            ->assertJsonPath('props.menu.placements.0', 'content');
    }

    public function test_menu_title_translations_are_returned_to_editor(): void
    {
        $this->ensureLanguages(['nl', 'en']);
        $user = $this->createAdminUser();
        $menu = $this->createMenu();

        $menu->translations()->create(['locale' => 'nl', 'title' => 'Hoofdmenu']);
        $menu->translations()->create(['locale' => 'en', 'title' => 'Main navigation']);

        $this
            ->actingAs($user)
            ->get(route('admin.cms.menus.edit', ['id' => $menu->id]), $this->inertiaHeaders('/admin/cms/menus/'.$menu->id.'/edit'))
            ->assertOk()
            ->assertJsonPath('props.menu.translations.nl.title', 'Hoofdmenu')
            ->assertJsonPath('props.menu.translations.en.title', 'Main navigation');
    }

    public function test_menu_index_uses_global_locations_and_counts_item_groups(): void
    {
        $user = $this->createAdminUser();
        $location = 'header-'.uniqid();
        $menu = $this->createMenu(['location' => $location]);
        $translationKey = 'menu-item-group-'.uniqid();

        CmsMenuItem::query()->create($this->itemRecord([
            'cms_menu_id' => $menu->id,
            'locale' => 'nl',
            'translation_key' => $translationKey,
            'label' => 'Contact',
        ]));
        CmsMenuItem::query()->create($this->itemRecord([
            'cms_menu_id' => $menu->id,
            'locale' => 'fr',
            'translation_key' => $translationKey,
            'label' => 'Contact FR',
        ]));

        $response = $this
            ->actingAs($user)
            ->get(route('admin.cms.menus.index'), $this->inertiaHeaders('/admin/cms/menus'))
            ->assertOk();

        $menusForLocation = collect($response->json('props.menus'))
            ->where('location', $location)
            ->values();

        $this->assertCount(1, $menusForLocation);
        $this->assertSame(1, (int) $menusForLocation->first()['items_count']);
    }

    public function test_menu_item_can_be_stored_for_page_target(): void
    {
        $user = $this->createAdminUser();
        $menu = $this->createMenu();
        $page = $this->createPage(['locale' => 'nl']);
        $label = 'Home '.uniqid();

        $this
            ->actingAs($user)
            ->post(route('admin.cms.menu-items.store', ['menu' => $menu->id, 'item' => 0]), $this->itemPayload([
                'type' => 'page',
                'label' => $label,
                'cms_page_id' => $page->id,
            ]))
            ->assertRedirect()
            ->assertSessionHas('status')
            ->assertSessionDoesntHaveErrors();

        $item = CmsMenuItem::query()->where('label', $label)->first();

        $this->assertNotNull($item);
        $this->assertSame($menu->id, $item?->cms_menu_id);
        $this->assertSame($page->id, $item?->cms_page_id);
        $this->assertNull($item?->url);
    }

    public function test_menu_item_target_must_match_selected_locale(): void
    {
        $this->ensureLanguages(['nl', 'fr']);
        $user = $this->createAdminUser();
        $menu = $this->createMenu();
        $page = $this->createPage(['locale' => 'fr', 'slug' => 'franse-pagina']);

        $this
            ->actingAs($user)
            ->from(route('admin.cms.menus.edit', ['id' => $menu->id]))
            ->post(route('admin.cms.menu-items.store', ['menu' => $menu->id, 'item' => 0]), $this->itemPayload([
                'type' => 'page',
                'label' => 'FR pagina',
                'cms_page_id' => $page->id,
            ]))
            ->assertRedirect(route('admin.cms.menus.edit', ['id' => $menu->id]))
            ->assertSessionHasErrors(['cms_page_id']);
    }

    public function test_custom_menu_item_can_be_stored_for_selected_locale(): void
    {
        $this->ensureLanguages(['nl', 'fr']);
        $user = $this->createAdminUser();
        $menu = $this->createMenu();

        $this
            ->actingAs($user)
            ->post(route('admin.cms.menu-items.store', ['menu' => $menu->id, 'item' => 0]), $this->itemPayload([
                'locale' => 'fr',
                'type' => 'custom',
                'label' => 'Contact FR',
                'url' => '/fr/contact',
            ]))
            ->assertRedirect()
            ->assertSessionHas('status')
            ->assertSessionDoesntHaveErrors();

        $item = CmsMenuItem::query()->where('label', 'Contact FR')->first();

        $this->assertNotNull($item);
        $this->assertSame('fr', $item?->locale);
        $this->assertSame('/fr/contact', $item?->url);
    }

    public function test_page_menu_item_creation_creates_available_language_variants(): void
    {
        $this->ensureLanguages(['nl', 'en']);
        $user = $this->createAdminUser();
        $menu = $this->createMenu();
        $translationKey = 'menu-item-page-'.uniqid();
        $page = $this->createPage([
            'title' => 'Diensten',
            'locale' => 'nl',
            'translation_key' => $translationKey,
        ]);
        $translatedPage = $this->createPage([
            'title' => 'Services',
            'locale' => 'en',
            'translation_key' => $translationKey,
            'translated_from_page_id' => $page->id,
        ]);

        $this
            ->actingAs($user)
            ->post(route('admin.cms.menu-items.store', ['menu' => $menu->id, 'item' => 0]), $this->itemPayload([
                'type' => 'page',
                'label' => '',
                'cms_page_id' => $page->id,
            ]))
            ->assertRedirect()
            ->assertSessionHas('status')
            ->assertSessionDoesntHaveErrors();

        $sourceItem = CmsMenuItem::query()
            ->where('cms_menu_id', $menu->id)
            ->where('locale', 'nl')
            ->where('cms_page_id', $page->id)
            ->first();
        $translatedItem = CmsMenuItem::query()
            ->where('cms_menu_id', $menu->id)
            ->where('locale', 'en')
            ->where('cms_page_id', $translatedPage->id)
            ->first();

        $this->assertNotNull($sourceItem);
        $this->assertNotNull($translatedItem);
        $this->assertSame('Diensten', $sourceItem?->label);
        $this->assertSame('Services', $translatedItem?->label);
        $this->assertSame($sourceItem?->translation_key, $translatedItem?->translation_key);
        $this->assertSame($sourceItem?->id, $translatedItem?->translated_from_menu_item_id);
    }

    public function test_custom_menu_item_translation_can_be_created_without_ai(): void
    {
        $this->ensureLanguages(['nl', 'en']);
        $user = $this->createAdminUser();
        $menu = $this->createMenu();
        $item = CmsMenuItem::query()->create($this->itemRecord([
            'cms_menu_id' => $menu->id,
            'locale' => 'nl',
            'translation_key' => 'custom-menu-item-'.uniqid(),
            'type' => 'custom',
            'label' => 'Contact',
            'url' => '/contact',
        ]));

        $this
            ->actingAs($user)
            ->post(route('admin.cms.menu-items.translations.store', [
                'menu' => $menu->id,
                'item' => $item->id,
            ]), [
                'target_locale' => 'en',
                'use_ai' => false,
            ])
            ->assertRedirect()
            ->assertSessionHas('status')
            ->assertSessionDoesntHaveErrors();

        $translatedItem = CmsMenuItem::query()
            ->where('cms_menu_id', $menu->id)
            ->where('translation_key', $item->translation_key)
            ->where('locale', 'en')
            ->first();

        $this->assertNotNull($translatedItem);
        $this->assertSame('Contact', $translatedItem?->label);
        $this->assertSame('/contact', $translatedItem?->url);
        $this->assertSame($item->id, $translatedItem?->translated_from_menu_item_id);
    }

    public function test_page_menu_item_translation_preserves_custom_source_label(): void
    {
        $this->ensureLanguages(['nl', 'en']);
        $user = $this->createAdminUser();
        $menu = $this->createMenu();
        $translationKey = 'home-page-'.uniqid();
        $englishPage = $this->createPage([
            'title' => 'Laravel applications, RWTable and growth guidance',
            'locale' => 'en',
            'translation_key' => $translationKey,
        ]);
        $dutchPage = $this->createPage([
            'title' => 'Laravel applicaties, RWTable en groeibegeleiding',
            'locale' => 'nl',
            'translation_key' => $translationKey,
            'translated_from_page_id' => $englishPage->id,
        ]);
        $item = CmsMenuItem::query()->create($this->itemRecord([
            'cms_menu_id' => $menu->id,
            'locale' => 'en',
            'translation_key' => 'home-item-'.uniqid(),
            'type' => 'page',
            'label' => 'Home',
            'cms_page_id' => $englishPage->id,
        ]));

        $editResponse = $this
            ->actingAs($user)
            ->get(route('admin.cms.menus.edit', ['id' => $menu->id, 'item' => $item->id]), $this->inertiaHeaders('/admin/cms/menus/'.$menu->id.'/edit?item='.$item->id))
            ->assertOk();
        $missingDutch = collect($editResponse->json('props.itemMissingLanguages'))->firstWhere('locale', 'nl');

        $this->assertTrue((bool) ($missingDutch['can_create'] ?? false));

        $this
            ->actingAs($user)
            ->post(route('admin.cms.menu-items.translations.store', [
                'menu' => $menu->id,
                'item' => $item->id,
            ]), [
                'target_locale' => 'nl',
                'use_ai' => false,
            ])
            ->assertRedirect()
            ->assertSessionHas('status')
            ->assertSessionDoesntHaveErrors();

        $translatedItem = CmsMenuItem::query()
            ->where('cms_menu_id', $menu->id)
            ->where('translation_key', $item->translation_key)
            ->where('locale', 'nl')
            ->first();

        $this->assertNotNull($translatedItem);
        $this->assertSame('Home', $translatedItem?->label);
        $this->assertSame($dutchPage->id, $translatedItem?->cms_page_id);

        $listResponse = $this
            ->actingAs($user)
            ->get(route('admin.cms.menus.edit', ['id' => $menu->id]), $this->inertiaHeaders('/admin/cms/menus/'.$menu->id.'/edit'))
            ->assertOk();
        $listedItem = collect($listResponse->json('props.items'))->firstWhere('translation_key', $item->translation_key);

        $this->assertSame('Home', $listedItem['label'] ?? null);
    }

    public function test_existing_menu_item_locale_cannot_be_changed_by_payload(): void
    {
        $this->ensureLanguages(['nl', 'en']);
        $user = $this->createAdminUser();
        $menu = $this->createMenu();
        $item = CmsMenuItem::query()->create($this->itemRecord([
            'cms_menu_id' => $menu->id,
            'locale' => 'nl',
            'label' => 'Contact',
            'url' => '/contact',
        ]));

        $this
            ->actingAs($user)
            ->post(route('admin.cms.menu-items.store', ['menu' => $menu->id, 'item' => $item->id]), $this->itemPayload([
                'locale' => 'en',
                'label' => 'Contact aangepast',
                'url' => '/contact-aangepast',
            ]))
            ->assertRedirect()
            ->assertSessionHas('status')
            ->assertSessionDoesntHaveErrors();

        $item->refresh();

        $this->assertSame('nl', $item->locale);
        $this->assertSame('Contact aangepast', $item->label);
    }

    public function test_menu_item_parent_cannot_create_cycle(): void
    {
        $user = $this->createAdminUser();
        $menu = $this->createMenu();
        $parent = CmsMenuItem::query()->create($this->itemRecord([
            'cms_menu_id' => $menu->id,
            'label' => 'Parent',
            'sort_order' => 1,
        ]));
        $child = CmsMenuItem::query()->create($this->itemRecord([
            'cms_menu_id' => $menu->id,
            'parent_id' => $parent->id,
            'label' => 'Child',
            'sort_order' => 2,
        ]));

        $this
            ->actingAs($user)
            ->from(route('admin.cms.menus.edit', ['id' => $menu->id, 'item' => $parent->id]))
            ->post(route('admin.cms.menu-items.store', ['menu' => $menu->id, 'item' => $parent->id]), $this->itemPayload([
                'label' => 'Parent',
                'parent_id' => $child->id,
                'sort_order' => 1,
            ]))
            ->assertRedirect(route('admin.cms.menus.edit', ['id' => $menu->id, 'item' => $parent->id]))
            ->assertSessionHasErrors(['parent_id']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createMenu(array $overrides = []): CmsMenu
    {
        $payload = $this->menuPayload(array_merge([
            'title' => 'Test menu',
        ], $overrides));

        unset($payload['translations']);

        return CmsMenu::query()->create($payload);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPage(array $overrides = []): CmsPage
    {
        return CmsPage::query()->create(array_merge([
            'title' => 'Test pagina',
            'slug' => 'test-pagina-'.uniqid(),
            'locale' => 'nl',
            'status' => 'draft',
            'content_blocks' => [],
            'noindex' => false,
            'is_home' => false,
            'is_searchable' => true,
            'sort_order' => 0,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPost(array $overrides = []): CmsPost
    {
        return CmsPost::query()->create(array_merge([
            'title' => 'Test bericht',
            'slug' => 'test-bericht-'.uniqid(),
            'locale' => 'nl',
            'status' => 'draft',
            'content_blocks' => [],
            'noindex' => false,
            'is_featured' => false,
            'is_searchable' => true,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function menuPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Menu',
            'translations' => [
                'nl' => ['title' => 'Menu'],
            ],
            'placements' => ['header'],
            'location' => null,
            'is_active' => true,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function itemPayload(array $overrides = []): array
    {
        return array_merge([
            'parent_id' => null,
            'locale' => 'nl',
            'type' => 'custom',
            'label' => 'Item',
            'url' => '/item',
            'cms_page_id' => null,
            'cms_post_id' => null,
            'target' => null,
            'rel' => null,
            'sort_order' => 0,
            'is_active' => true,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function itemRecord(array $overrides = []): array
    {
        return array_merge([
            'cms_menu_id' => null,
            'parent_id' => null,
            'locale' => 'nl',
            'type' => 'custom',
            'label' => 'Item',
            'url' => '/item',
            'target' => null,
            'rel' => null,
            'sort_order' => 0,
            'is_active' => true,
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
            'two_factor_secret' => encrypt('cms-menu-test-secret'),
            'two_factor_confirmed_at' => now(),
        ]);

        $roleId = DB::connection('central')->table('acl_roles')->where('key', 'super_admin')->value('id');

        if (! $roleId) {
            $roleId = DB::connection('central')->table('acl_roles')->insertGetId([
                'key' => 'super_admin',
                'name' => 'Super administrator',
                'description' => 'Test super admin role',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::connection('central')->table('acl_role_user')->updateOrInsert(
            [
                'user_id' => $user->id,
                'acl_role_id' => $roleId,
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->createdUserIds[] = (int) $user->id;

        return $user;
    }

    /**
     * @param  array<int, string>  $locales
     */
    private function ensureLanguages(array $locales): void
    {
        foreach ($locales as $index => $locale) {
            CmsLanguage::query()->updateOrCreate(
                ['locale' => $locale],
                [
                    'name' => strtoupper($locale),
                    'native_name' => strtoupper($locale),
                    'direction' => 'ltr',
                    'is_active' => true,
                    'sort_order' => ($index + 1) * 10,
                ]
            );
        }
    }
}
