<?php

namespace Tests\Feature\Cms;

use App\Http\Middleware\AuthAdminUsers;
use App\Http\Middleware\AuthorizeAdminRoute;
use App\Http\Middleware\EnsureSiteMembership;
use App\Http\Middleware\EnsureTwoFactorIsEnabled;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveTenantSite;
use App\Models\Cms\CmsPlaceableBlock;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CmsBlockDefinitionBackofficeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'tenant',
            'database.connections.mysql.database' => 'rwsoft',
            'database.connections.central.driver' => 'mysql',
            'database.connections.central.host' => config('database.connections.mysql.host'),
            'database.connections.central.port' => config('database.connections.mysql.port'),
            'database.connections.central.database' => 'rwsoft',
            'database.connections.central.username' => config('database.connections.mysql.username'),
            'database.connections.central.password' => config('database.connections.mysql.password'),
            'database.connections.tenant.driver' => 'mysql',
            'database.connections.tenant.host' => config('database.connections.mysql.host'),
            'database.connections.tenant.port' => config('database.connections.mysql.port'),
            'database.connections.tenant.database' => 'rwsoft_site_rwsoft',
            'database.connections.tenant.username' => config('database.connections.mysql.username'),
            'database.connections.tenant.password' => config('database.connections.mysql.password'),
        ]);

        DB::purge('central');
        DB::purge('tenant');
        DB::reconnect('central');
        DB::reconnect('tenant');
        DB::setDefaultConnection('tenant');
        DB::connection('central')->beginTransaction();
        DB::connection('tenant')->beginTransaction();

        $this->withoutMiddleware([
            AuthAdminUsers::class,
            AuthorizeAdminRoute::class,
            EnsureSiteMembership::class,
            EnsureTwoFactorIsEnabled::class,
            ResolveTenantSite::class,
        ]);
    }

    protected function tearDown(): void
    {
        if (DB::connection('tenant')->transactionLevel() > 0) {
            DB::connection('tenant')->rollBack();
        }

        if (DB::connection('central')->transactionLevel() > 0) {
            DB::connection('central')->rollBack();
        }

        TenantContext::clear();

        parent::tearDown();
    }

    public function test_block_definition_pages_render_inertia_components(): void
    {
        $user = $this->createAdminUser();
        $block = $this->createBlockDefinition();

        $this
            ->actingAs($user)
            ->get(route('admin.cms.blocks.index'), $this->inertiaHeaders('/admin/cms/blocks'))
            ->assertOk()
            ->assertJsonPath('component', 'Admin/Cms/Blocks/Index')
            ->assertJsonFragment(['key' => $block->key]);

        $this
            ->actingAs($user)
            ->get(route('admin.cms.blocks.create'), $this->inertiaHeaders('/admin/cms/blocks/create'))
            ->assertOk()
            ->assertJsonPath('component', 'Admin/Cms/Blocks/Edit');

        $this
            ->actingAs($user)
            ->get(route('admin.cms.blocks.edit', ['block' => $block->id]), $this->inertiaHeaders('/admin/cms/blocks/'.$block->id.'/edit'))
            ->assertOk()
            ->assertJsonPath('component', 'Admin/Cms/Blocks/Edit')
            ->assertJsonPath('props.blockItem.id', $block->id);
    }

    public function test_safe_blade_block_definition_can_be_saved_and_published(): void
    {
        $user = $this->createAdminUser();

        $this
            ->actingAs($user)
            ->post(route('admin.cms.blocks.store-new'), $this->blockPayload([
                'key' => 'feature_notice',
                'name' => 'Feature notice',
                'publish' => true,
            ]))
            ->assertRedirect()
            ->assertSessionHas('status')
            ->assertSessionDoesntHaveErrors();

        $block = CmsPlaceableBlock::query()->where('key', 'feature_notice')->firstOrFail();

        $this->assertSame('published', $block->status);
        $this->assertSame('safe_blade', $block->rendering_mode);
        $this->assertSame(['content'], $block->allowed_zones);
        $this->assertEquals(['category' => 'content', 'fields' => ['title', 'text'], 'editor_fields' => [], 'preview' => [], 'slots' => []], $block->schema);
        $this->assertSame(1, $block->revisions()->where('status', 'published')->count());
    }

    public function test_safe_blade_block_definition_can_store_slot_definitions(): void
    {
        $user = $this->createAdminUser();

        $this
            ->actingAs($user)
            ->post(route('admin.cms.blocks.store-new'), $this->blockPayload([
                'key' => 'feature_card_test',
                'name' => 'Feature card test',
                'template_source' => '<article>{{ block.title }} @cmsSlot(actions)</article>',
                'schema' => [
                    'fields' => ['title'],
                    'editor_fields' => [],
                    'preview' => [],
                    'slots' => [[
                        'key' => 'actions',
                        'label' => 'Actions',
                        'allowed_block_keys' => ['button'],
                        'min_items' => 0,
                        'max_items' => 2,
                        'layout' => 'inline',
                        'responsive' => 'wrap_mobile',
                    ]],
                ],
            ]))
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $block = CmsPlaceableBlock::query()->where('key', 'feature_card_test')->firstOrFail();

        $this->assertSame('actions', $block->schema['slots'][0]['key']);
        $this->assertSame(['button'], $block->schema['slots'][0]['allowed_block_keys']);
        $this->assertSame(2, $block->schema['slots'][0]['max_items']);
    }

    public function test_block_definition_rejects_style_tags_in_css(): void
    {
        $user = $this->createAdminUser();

        $this
            ->actingAs($user)
            ->from(route('admin.cms.blocks.create'))
            ->post(route('admin.cms.blocks.store-new'), $this->blockPayload([
                'css_source' => '.bad { color: red; }</style>',
            ]))
            ->assertRedirect(route('admin.cms.blocks.create'))
            ->assertSessionHasErrors(['css_source']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function blockPayload(array $overrides = []): array
    {
        return array_merge([
            'key' => 'test_notice',
            'name' => 'Test notice',
            'description' => 'Reusable content block.',
            'category' => 'content',
            'source' => 'user',
            'status' => 'draft',
            'allowed_zones' => ['content'],
            'rendering_mode' => 'safe_blade',
            'renderer_key' => 'test_notice',
            'template_source' => '<article>{{ block.title }}</article>',
            'css_source' => '.test-notice { color: green; }',
            'schema' => ['fields' => ['title', 'text'], 'editor_fields' => [], 'preview' => []],
            'defaults' => ['title' => 'Default title'],
            'capabilities' => [],
            'admin_component_key' => null,
            'package_key' => null,
            'sort_order' => 0,
            'is_locked' => false,
            'requires_permission' => null,
            'publish' => false,
        ], $overrides);
    }

    private function createBlockDefinition(): CmsPlaceableBlock
    {
        return CmsPlaceableBlock::query()->create($this->blockPayload([
            'key' => 'existing_notice',
            'name' => 'Existing notice',
        ]));
    }

    private function createAdminUser(): User
    {
        return User::factory()->create([
            'is_platform_admin' => true,
            'two_factor_secret' => encrypt('cms-block-definition-test-secret'),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function inertiaHeaders(string $url): array
    {
        $request = Request::create($url, 'GET');
        $version = app(HandleInertiaRequests::class)->version($request);

        return [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Inertia-Version' => (string) ($version ?? ''),
        ];
    }
}
