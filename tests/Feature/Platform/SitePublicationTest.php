<?php

namespace Tests\Feature\Platform;

use App\Actions\Admin\Cms\SitePackages\BuildCmsSitePackageZipAction;
use App\Actions\Platform\ConfigureTenantDatabaseAction;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Platform\HostingConnection;
use App\Models\Platform\HostingEnvironment;
use App\Models\Platform\Site;
use App\Models\Platform\SitePublication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

class SitePublicationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'central',
            'database.connections.mysql.database' => 'rwsoft',
            'database.connections.central.driver' => 'mysql',
            'database.connections.central.host' => config('database.connections.mysql.host'),
            'database.connections.central.port' => config('database.connections.mysql.port'),
            'database.connections.central.database' => 'rwsoft',
            'database.connections.central.username' => config('database.connections.mysql.username'),
            'database.connections.central.password' => config('database.connections.mysql.password'),
        ]);

        DB::purge('central');
        DB::reconnect('central');
        DB::setDefaultConnection('central');
        DB::connection('central')->beginTransaction();

        $this->withoutMiddleware([
            EnsurePlatformAdmin::class,
            ThrottleRequests::class,
        ]);
    }

    protected function tearDown(): void
    {
        if (DB::connection('central')->transactionLevel() > 0) {
            DB::connection('central')->rollBack();
        }

        Config::set('database.default', 'mysql');

        parent::tearDown();
    }

    public function test_platform_admin_can_store_site_publication(): void
    {
        $site = $this->site();
        $environment = $this->hostingEnvironment();

        $this
            ->actingAs($this->platformUser())
            ->post(route('platform.publications.store', ['id' => 0]), $this->payload($site, $environment))
            ->assertRedirect()
            ->assertSessionHas('status');

        $publication = SitePublication::query()
            ->where('site_id', $site->id)
            ->where('hosting_environment_id', $environment->id)
            ->where('remote_site_slug', 'customer-site')
            ->firstOrFail();

        $this->assertSame($site->id, $publication->site_id);
        $this->assertSame($environment->id, $publication->hosting_environment_id);
        $this->assertSame('customer-site', $publication->remote_site_slug);
        $this->assertSame('www.customer.test', $publication->remote_domain);
        $this->assertSame('shared_prefixed', $publication->remote_tenant_database_mode);
        $this->assertSame('rwsoft_cloud', $publication->remote_tenant_database);
        $this->assertSame('t_customer_site_', $publication->remote_tenant_table_prefix);
        $this->assertSame('draft', $publication->status);

        $this
            ->actingAs($this->platformUser())
            ->get(route('platform.publications.edit', ['id' => $publication->id]), $this->inertiaHeaders('/platform/publications/'.$publication->id.'/edit'))
            ->assertOk()
            ->assertJsonPath('component', 'Platform/Publications/Edit')
            ->assertJsonPath('props.publication.remote_site_slug', 'customer-site')
            ->assertJsonPath('props.publication.site.slug', 'customer-site')
            ->assertJsonPath('props.publication.hosting_connection.name', 'Laravel Cloud Production');
    }

    public function test_platform_admin_can_update_site_publication_database_mode(): void
    {
        $site = $this->site();
        $environment = $this->hostingEnvironment();
        $publication = $this->sitePublication($site, $environment);

        $this
            ->actingAs($this->platformUser())
            ->post(route('platform.publications.store', ['id' => $publication->id]), $this->payload($site, $environment, [
                'remote_tenant_database_mode' => 'separate',
                'remote_tenant_database' => 'customer_remote_db',
                'remote_tenant_table_prefix' => null,
            ]))
            ->assertRedirect()
            ->assertSessionHas('status');

        $publication->refresh();

        $this->assertSame('separate', $publication->remote_tenant_database_mode);
        $this->assertSame('customer_remote_db', $publication->remote_tenant_database);
        $this->assertNull($publication->remote_tenant_table_prefix);
        $this->assertSame('draft', $publication->status);
    }

    public function test_shared_prefixed_publication_requires_remote_table_prefix(): void
    {
        $site = $this->site();
        $environment = $this->hostingEnvironment();

        $this
            ->actingAs($this->platformUser())
            ->from(route('platform.publications.create'))
            ->post(route('platform.publications.store', ['id' => 0]), $this->payload($site, $environment, [
                'remote_tenant_table_prefix' => null,
            ]))
            ->assertRedirect(route('platform.publications.create'))
            ->assertSessionHasErrors('remote_tenant_table_prefix');

        $this->assertSame(
            0,
            SitePublication::query()
                ->where('site_id', $site->id)
                ->where('hosting_environment_id', $environment->id)
                ->where('remote_site_slug', 'customer-site')
                ->count()
        );
    }

    public function test_platform_admin_can_prepare_publish_preflight(): void
    {
        $site = $this->site(['provisioned_at' => now()]);
        $environment = $this->hostingEnvironment();
        $publication = $this->sitePublication($site, $environment);

        $this->mockSuccessfulPackageExport($site);

        $this
            ->actingAs($this->platformUser())
            ->post(route('platform.publications.prepare-publish', ['publication' => $publication->id]))
            ->assertRedirect()
            ->assertSessionHas('status');

        $publication->refresh();

        $run = $publication->runs()->firstOrFail();

        $this->assertSame('ready', $publication->status);
        $this->assertSame('push', $run->direction);
        $this->assertSame('completed', $run->status);
        $this->assertNull($run->error_message);
        $this->assertSame('preflight', $run->options['mode'] ?? null);
        $this->assertSame('/tmp/rwsoft-site-package.zip', $run->options['site_package_path'] ?? null);
        $this->assertSame('publish-customer-site.zip', $run->options['site_package_filename'] ?? null);
        $this->assertSame('publish-customer-site', $run->options['site_package_key'] ?? null);
        $this->assertSame('laravel_cloud', $run->options['provider_plan']['provider'] ?? null);
        $this->assertSame('app_123', $run->options['provider_plan']['provider_application_id'] ?? null);
        $this->assertSame('env_456', $run->options['provider_plan']['provider_environment_id'] ?? null);
        $this->assertSame(
            ['APP_URL', 'TENANT_DATABASE_MODE', 'RWSOFT_REMOTE_SITE_SLUG', 'TENANT_DATABASE', 'TENANT_TABLE_PREFIX'],
            collect($run->options['provider_plan']['env_vars'] ?? [])->pluck('key')->all()
        );
        $this->assertSame('https://www.customer.test', collect($run->options['provider_plan']['env_vars'])->firstWhere('key', 'APP_URL')['value'] ?? null);
        $this->assertSame('t_customer_site_', collect($run->options['provider_plan']['env_vars'])->firstWhere('key', 'TENANT_TABLE_PREFIX')['value'] ?? null);
        $this->assertSame('www.customer.test', $run->options['provider_plan']['domains'][0]['domain'] ?? null);
        $this->assertSame(
            ['bootstrap_site', 'import_site_package', 'clear_cache'],
            collect($run->options['provider_plan']['commands'] ?? [])->pluck('key')->all()
        );
        $this->assertSame(
            ['local_site', 'hosting_environment', 'remote_identity', 'remote_database', 'site_package', 'remote_plan'],
            collect($run->steps)->pluck('key')->all()
        );
        $this->assertFalse(collect($run->steps)->contains(fn (array $step): bool => $step['status'] === 'failed'));
        $this->assertSame('passed', collect($run->steps)->firstWhere('key', 'site_package')['status'] ?? null);
        $this->assertSame($run->id, $publication->metadata['last_preflight_run_id'] ?? null);
        $this->assertSame('completed', $publication->metadata['last_preflight_status'] ?? null);

        $this
            ->actingAs($this->platformUser())
            ->get(route('platform.publications.edit', ['id' => $publication->id]), $this->inertiaHeaders('/platform/publications/'.$publication->id.'/edit'))
            ->assertOk()
            ->assertJsonPath('props.publication.latest_run.status', 'completed')
            ->assertJsonPath('props.publication.latest_run.steps.0.key', 'local_site')
            ->assertJsonPath('props.publication.latest_run.options.site_package_filename', 'publish-customer-site.zip')
            ->assertJsonPath('props.publication.latest_run.options.provider_plan.env_vars.4.key', 'TENANT_TABLE_PREFIX');
    }

    public function test_prepare_publish_preflight_plans_separate_database_without_domain(): void
    {
        $site = $this->site(['provisioned_at' => now()]);
        $environment = $this->hostingEnvironment();
        $publication = $this->sitePublication($site, $environment, [
            'remote_domain' => null,
            'remote_tenant_database_mode' => 'separate',
            'remote_tenant_database' => 'customer_remote_db',
            'remote_tenant_table_prefix' => null,
        ]);

        $this->mockSuccessfulPackageExport($site);

        $this
            ->actingAs($this->platformUser())
            ->post(route('platform.publications.prepare-publish', ['publication' => $publication->id]))
            ->assertRedirect()
            ->assertSessionHas('status');

        $run = $publication->runs()->firstOrFail();
        $envVars = collect($run->options['provider_plan']['env_vars'] ?? []);

        $this->assertSame('completed', $run->status);
        $this->assertSame(
            ['APP_URL', 'TENANT_DATABASE_MODE', 'RWSOFT_REMOTE_SITE_SLUG', 'TENANT_DATABASE'],
            $envVars->pluck('key')->all()
        );
        $this->assertNull($envVars->firstWhere('key', 'APP_URL')['value'] ?? null);
        $this->assertSame('separate', $envVars->firstWhere('key', 'TENANT_DATABASE_MODE')['value'] ?? null);
        $this->assertSame('customer_remote_db', $envVars->firstWhere('key', 'TENANT_DATABASE')['value'] ?? null);
        $this->assertSame([], $run->options['provider_plan']['domains'] ?? null);
    }

    public function test_prepare_publish_preflight_does_not_plan_provider_vanity_domain_action(): void
    {
        $site = $this->site(['provisioned_at' => now()]);
        $environment = $this->hostingEnvironment([
            'metadata' => [
                'environment' => [
                    'vanity_domain' => 'rwsoft-production-pyxdyk.laravel.cloud',
                ],
            ],
        ]);
        $publication = $this->sitePublication($site, $environment, [
            'remote_domain' => 'rwsoft-production-pyxdyk.laravel.cloud',
        ]);

        $this->mockSuccessfulPackageExport($site);

        $this
            ->actingAs($this->platformUser())
            ->post(route('platform.publications.prepare-publish', ['publication' => $publication->id]))
            ->assertRedirect()
            ->assertSessionHas('status');

        $run = $publication->runs()->firstOrFail();

        $this->assertSame('https://rwsoft-production-pyxdyk.laravel.cloud', collect($run->options['provider_plan']['env_vars'])->firstWhere('key', 'APP_URL')['value'] ?? null);
        $this->assertSame([], $run->options['provider_plan']['domains'] ?? null);
    }

    public function test_prepare_publish_preflight_fails_when_site_is_not_provisioned(): void
    {
        $site = $this->site(['provisioned_at' => null]);
        $environment = $this->hostingEnvironment();
        $publication = $this->sitePublication($site, $environment);

        $this
            ->actingAs($this->platformUser())
            ->post(route('platform.publications.prepare-publish', ['publication' => $publication->id]))
            ->assertRedirect()
            ->assertSessionHas('warning');

        $publication->refresh();

        $run = $publication->runs()->firstOrFail();
        $localSiteStep = collect($run->steps)->firstWhere('key', 'local_site');

        $this->assertSame('failed', $publication->status);
        $this->assertSame('failed', $run->status);
        $this->assertNotNull($run->error_message);
        $this->assertSame('failed', $localSiteStep['status'] ?? null);
        $this->assertSame('failed', $publication->metadata['last_preflight_status'] ?? null);
    }

    public function test_platform_admin_can_apply_planned_environment_variables(): void
    {
        $site = $this->site(['provisioned_at' => now()]);
        $environment = $this->hostingEnvironment();
        $publication = $this->sitePublication($site, $environment);

        $this->mockSuccessfulPackageExport($site);

        $user = $this->platformUser();

        $this
            ->actingAs($user)
            ->post(route('platform.publications.prepare-publish', ['publication' => $publication->id]))
            ->assertRedirect()
            ->assertSessionHas('status');

        Http::fake([
            'https://cloud.laravel.com/api/environments/env_456' => Http::sequence()
                ->push([
                    'data' => [
                        'id' => 'env_456',
                        'type' => 'environments',
                        'attributes' => [
                            'environment_variables' => [
                                ['key' => 'APP_ENV', 'value' => 'production'],
                                ['key' => 'TENANT_DATABASE', 'value' => 'old_database'],
                            ],
                        ],
                    ],
                ])
                ->push(['data' => ['id' => 'env_456', 'type' => 'environments']]),
            'https://cloud.laravel.com/api/environments/env_456/variables' => Http::response([
                'data' => ['id' => 'env_456', 'type' => 'environments'],
            ]),
        ]);

        $this
            ->actingAs($user)
            ->post(route('platform.publications.apply-env-vars', ['publication' => $publication->id]))
            ->assertRedirect()
            ->assertSessionHas('status');

        $publication->refresh();

        $run = $publication->runs()->latest('id')->firstOrFail();

        $this->assertSame('ready', $publication->status);
        $this->assertSame('completed', $run->status);
        $this->assertSame('apply_env_vars', $run->options['mode'] ?? null);
        $this->assertSame(
            ['APP_URL', 'TENANT_DATABASE_MODE', 'RWSOFT_REMOTE_SITE_SLUG', 'TENANT_DATABASE', 'TENANT_TABLE_PREFIX'],
            $run->options['applied_env_var_keys'] ?? []
        );
        $this->assertSame('passed', collect($run->steps)->firstWhere('key', 'environment_variables')['status'] ?? null);
        $this->assertSame($run->id, $publication->metadata['last_env_var_run_id'] ?? null);
        $this->assertSame('completed', $publication->metadata['last_env_var_status'] ?? null);

        Http::assertSentCount(2);
        Http::assertSent(function ($request): bool {
            if ($request->method() !== 'POST' || (string) $request->url() !== 'https://cloud.laravel.com/api/environments/env_456/variables') {
                return false;
            }

            $variables = collect($request->data()['variables'] ?? []);

            return $request->data()['method'] === 'append'
                && $variables->pluck('key')->all() === ['APP_URL', 'TENANT_DATABASE_MODE', 'RWSOFT_REMOTE_SITE_SLUG', 'TENANT_DATABASE', 'TENANT_TABLE_PREFIX']
                && $variables->firstWhere('key', 'TENANT_DATABASE')['value'] === 'rwsoft_cloud'
                && $variables->firstWhere('key', 'TENANT_TABLE_PREFIX')['value'] === 't_customer_site_'
                && $variables->firstWhere('key', 'APP_URL')['value'] === 'https://www.customer.test';
        });

        $this
            ->actingAs($user)
            ->get(route('platform.publications.edit', ['id' => $publication->id]), $this->inertiaHeaders('/platform/publications/'.$publication->id.'/edit'))
            ->assertOk()
            ->assertJsonPath('props.publication.latest_run.options.mode', 'apply_env_vars')
            ->assertJsonPath('props.publication.latest_preflight_run.options.provider_plan.env_vars.4.key', 'TENANT_TABLE_PREFIX');
    }

    public function test_apply_environment_variables_requires_completed_preflight(): void
    {
        $site = $this->site(['provisioned_at' => now()]);
        $environment = $this->hostingEnvironment();
        $publication = $this->sitePublication($site, $environment);

        Http::fake();

        $this
            ->actingAs($this->platformUser())
            ->post(route('platform.publications.apply-env-vars', ['publication' => $publication->id]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $publication->refresh();

        $run = $publication->runs()->firstOrFail();

        $this->assertSame('failed', $publication->status);
        $this->assertSame('failed', $run->status);
        $this->assertSame('apply_env_vars', $run->options['mode'] ?? null);
        $this->assertSame('failed', collect($run->steps)->firstWhere('key', 'preflight')['status'] ?? null);

        Http::assertSentCount(0);
    }

    public function test_platform_admin_can_detect_attached_laravel_cloud_database(): void
    {
        $site = $this->site(['provisioned_at' => now()]);
        $environment = $this->hostingEnvironment();
        $publication = $this->sitePublication($site, $environment);

        $this->mockSuccessfulPackageExport($site);

        $user = $this->platformUser();

        $this
            ->actingAs($user)
            ->post(route('platform.publications.prepare-publish', ['publication' => $publication->id]))
            ->assertRedirect()
            ->assertSessionHas('status');

        Http::fake([
            'https://cloud.laravel.com/api/environments/env_456?include=database' => Http::response([
                'data' => [
                    'id' => 'env_456',
                    'type' => 'environments',
                    'relationships' => [
                        'database' => [
                            'data' => ['id' => 'db_123', 'type' => 'databases'],
                        ],
                    ],
                ],
                'included' => [
                    [
                        'id' => 'db_123',
                        'type' => 'databases',
                        'attributes' => [
                            'name' => 'rwsoft',
                            'type' => 'mysql',
                            'status' => 'available',
                        ],
                    ],
                ],
            ]),
        ]);

        $this
            ->actingAs($user)
            ->post(route('platform.publications.provision-database', ['publication' => $publication->id]))
            ->assertRedirect()
            ->assertSessionHas('status');

        $publication->refresh();

        $run = $publication->runs()->latest('id')->firstOrFail();

        $this->assertSame('ready', $publication->status);
        $this->assertSame('completed', $run->status);
        $this->assertSame('provision_database', $run->options['mode'] ?? null);
        $this->assertSame('db_123', $run->options['database_id'] ?? null);
        $this->assertSame('rwsoft', $run->options['name'] ?? null);
        $this->assertSame('mysql', $run->options['type'] ?? null);
        $this->assertSame('available', $run->options['status'] ?? null);
        $this->assertSame($run->id, $publication->metadata['last_database_run_id'] ?? null);
        $this->assertSame('completed', $publication->metadata['last_database_status'] ?? null);
        $this->assertSame('db_123', $publication->metadata['last_database_id'] ?? null);
    }

    public function test_database_detection_fails_when_laravel_cloud_database_is_missing(): void
    {
        $site = $this->site(['provisioned_at' => now()]);
        $environment = $this->hostingEnvironment();
        $publication = $this->sitePublication($site, $environment);

        $this->mockSuccessfulPackageExport($site);

        $user = $this->platformUser();

        $this
            ->actingAs($user)
            ->post(route('platform.publications.prepare-publish', ['publication' => $publication->id]))
            ->assertRedirect()
            ->assertSessionHas('status');

        Http::fake([
            'https://cloud.laravel.com/api/environments/env_456?include=database' => Http::response([
                'data' => [
                    'id' => 'env_456',
                    'type' => 'environments',
                    'relationships' => [
                        'database' => ['data' => null],
                    ],
                ],
                'included' => [],
            ]),
        ]);

        $this
            ->actingAs($user)
            ->post(route('platform.publications.provision-database', ['publication' => $publication->id]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $publication->refresh();

        $run = $publication->runs()->latest('id')->firstOrFail();

        $this->assertSame('failed', $publication->status);
        $this->assertSame('failed', $run->status);
        $this->assertSame('provision_database', $run->options['mode'] ?? null);
        $this->assertNull($run->options['database_id'] ?? null);
        $this->assertSame('failed', collect($run->steps)->firstWhere('key', 'database')['status'] ?? null);
    }

    public function test_platform_admin_can_create_laravel_cloud_database_cluster_when_missing(): void
    {
        $site = $this->site(['provisioned_at' => now()]);
        $environment = $this->hostingEnvironment();
        $publication = $this->sitePublication($site, $environment);

        $this->mockSuccessfulPackageExport($site);

        $user = $this->platformUser();

        $this
            ->actingAs($user)
            ->post(route('platform.publications.prepare-publish', ['publication' => $publication->id]))
            ->assertRedirect()
            ->assertSessionHas('status');

        Http::fake(function ($request) {
            $url = (string) $request->url();

            if ($request->method() === 'GET' && $url === 'https://cloud.laravel.com/api/environments/env_456?include=database') {
                return Http::response([
                    'data' => [
                        'id' => 'env_456',
                        'type' => 'environments',
                        'relationships' => [
                            'database' => ['data' => null],
                        ],
                    ],
                    'included' => [],
                ]);
            }

            if ($request->method() === 'POST' && $url === 'https://cloud.laravel.com/api/databases/clusters') {
                return Http::response([
                    'data' => [
                        'id' => 'database_cluster_123',
                        'type' => 'databaseClusters',
                        'attributes' => [
                            'name' => 'customer_site',
                            'type' => 'laravel_mysql_84',
                            'status' => 'creating',
                            'region' => 'eu-west-1',
                        ],
                    ],
                ], 201);
            }

            if ($request->method() === 'GET' && $url === 'https://cloud.laravel.com/api/databases/clusters/database_cluster_123/databases') {
                return Http::response([
                    'data' => [
                        [
                            'id' => 'schema_123',
                            'type' => 'databaseSchemas',
                            'attributes' => ['name' => 'main', 'status' => 'available'],
                        ],
                    ],
                ]);
            }

            if ($request->method() === 'PATCH' && $url === 'https://cloud.laravel.com/api/environments/env_456') {
                return Http::response([
                    'data' => [
                        'id' => 'env_456',
                        'type' => 'environments',
                        'relationships' => [
                            'database' => [
                                'data' => ['id' => 'schema_123', 'type' => 'databaseSchemas'],
                            ],
                        ],
                    ],
                    'included' => [
                        [
                            'id' => 'schema_123',
                            'type' => 'databaseSchemas',
                            'attributes' => ['name' => 'main', 'status' => 'available'],
                            'relationships' => [
                                'database' => [
                                    'data' => ['id' => 'database_cluster_123', 'type' => 'databases'],
                                ],
                            ],
                        ],
                        [
                            'id' => 'database_cluster_123',
                            'type' => 'databases',
                            'attributes' => [
                                'name' => 'customer_site',
                                'type' => 'laravel_mysql_84',
                                'status' => 'available',
                            ],
                        ],
                    ],
                ]);
            }

            return Http::response(['message' => 'Unexpected request.'], 500);
        });

        $this
            ->actingAs($user)
            ->post(route('platform.publications.create-database', ['publication' => $publication->id]))
            ->assertRedirect()
            ->assertSessionHas('warning');

        $publication->refresh();

        $run = $publication->runs()->latest('id')->firstOrFail();

        $this->assertSame('ready', $publication->status);
        $this->assertSame('completed', $run->status);
        $this->assertSame('provision_database', $run->options['mode'] ?? null);
        $this->assertSame('created_and_attached', $run->options['database_action'] ?? null);
        $this->assertSame('database_cluster_123', $run->options['database_id'] ?? null);
        $this->assertSame('schema_123', $run->options['schema_id'] ?? null);
        $this->assertSame('main', $run->options['name'] ?? null);
        $this->assertSame('laravel_mysql_84', $run->options['type'] ?? null);
        $this->assertSame('available', $run->options['status'] ?? null);
        $this->assertFalse($run->options['requires_attach'] ?? true);
        $this->assertSame('passed', collect($run->steps)->firstWhere('key', 'database')['status'] ?? null);
        $this->assertSame($run->id, $publication->metadata['last_database_run_id'] ?? null);
        $this->assertSame('completed', $publication->metadata['last_database_status'] ?? null);
        $this->assertSame('database_cluster_123', $publication->metadata['last_database_id'] ?? null);
        $this->assertFalse($publication->metadata['last_database_requires_attach'] ?? true);

        Http::assertSentCount(4);
        Http::assertSent(function ($request): bool {
            return $request->method() === 'POST'
                && (string) $request->url() === 'https://cloud.laravel.com/api/databases/clusters'
                && $request->data()['type'] === 'laravel_mysql_84'
                && $request->data()['name'] === 'customer_site'
                && $request->data()['region'] === 'eu-west-1'
                && $request->data()['config']['size'] === 'db-flex.m-1vcpu-512mb'
                && $request->data()['config']['storage'] === 5
                && $request->data()['config']['is_public'] === false;
        });
        Http::assertSent(function ($request): bool {
            return $request->method() === 'PATCH'
                && (string) $request->url() === 'https://cloud.laravel.com/api/environments/env_456'
                && $request->data()['database_schema_id'] === 'schema_123';
        });
    }

    public function test_laravel_cloud_html_fallback_response_fails_database_check(): void
    {
        $site = $this->site(['provisioned_at' => now()]);
        $environment = $this->hostingEnvironment();
        $publication = $this->sitePublication($site, $environment);

        $this->mockSuccessfulPackageExport($site);

        $user = $this->platformUser();

        $this
            ->actingAs($user)
            ->post(route('platform.publications.prepare-publish', ['publication' => $publication->id]))
            ->assertRedirect()
            ->assertSessionHas('status');

        Http::fake([
            'https://cloud.laravel.com/api/environments/env_456?include=database' => Http::response('<html>Laravel Cloud</html>', 200, [
                'Content-Type' => 'text/html; charset=utf-8',
            ]),
        ]);

        $this
            ->actingAs($user)
            ->post(route('platform.publications.provision-database', ['publication' => $publication->id]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $publication->refresh();

        $run = $publication->runs()->latest('id')->firstOrFail();

        $this->assertSame('failed', $publication->status);
        $this->assertSame('failed', $run->status);
        $this->assertSame('provision_database', $run->options['mode'] ?? null);
        $this->assertSame('Laravel Cloud API returned a non-JSON response.', $run->error_message);
        $this->assertSame('failed', collect($run->steps)->firstWhere('key', 'database')['status'] ?? null);

        Http::assertSentCount(1);
    }

    public function test_platform_admin_can_apply_planned_domain(): void
    {
        $site = $this->site(['provisioned_at' => now()]);
        $environment = $this->hostingEnvironment();
        $publication = $this->sitePublication($site, $environment);

        $this->mockSuccessfulPackageExport($site);

        $user = $this->platformUser();

        $this
            ->actingAs($user)
            ->post(route('platform.publications.prepare-publish', ['publication' => $publication->id]))
            ->assertRedirect()
            ->assertSessionHas('status');

        Http::fake(function ($request) {
            $url = (string) $request->url();

            if ($request->method() === 'GET' && $url === 'https://cloud.laravel.com/api/environments/env_456') {
                return Http::response([
                    'data' => [
                        'id' => 'env_456',
                        'type' => 'environments',
                        'relationships' => ['domains' => ['data' => []]],
                    ],
                    'included' => [],
                ]);
            }

            if ($request->method() === 'POST' && $url === 'https://cloud.laravel.com/api/environments/env_456/domains') {
                return Http::response([
                    'data' => [
                        'id' => 'domain_789',
                        'type' => 'domains',
                        'attributes' => ['domain' => 'www.customer.test'],
                    ],
                ]);
            }

            if ($request->method() === 'POST' && $url === 'https://cloud.laravel.com/api/environments/env_456/domains/domain_789/verify') {
                return Http::response([
                    'data' => [
                        'id' => 'domain_789',
                        'type' => 'domains',
                    ],
                ]);
            }

            return Http::response(['message' => 'Unexpected request.'], 500);
        });

        $this
            ->actingAs($user)
            ->post(route('platform.publications.apply-domain', ['publication' => $publication->id]))
            ->assertRedirect()
            ->assertSessionHas('status');

        $publication->refresh();

        $run = $publication->runs()->latest('id')->firstOrFail();

        $this->assertSame('ready', $publication->status);
        $this->assertSame('completed', $run->status);
        $this->assertSame('apply_domain', $run->options['mode'] ?? null);
        $this->assertSame('www.customer.test', $run->options['domain'] ?? null);
        $this->assertSame('created', $run->options['domain_action'] ?? null);
        $this->assertSame('domain_789', $run->options['domain_id'] ?? null);
        $this->assertSame('requested', $run->options['verification'] ?? null);
        $this->assertSame('passed', collect($run->steps)->firstWhere('key', 'domain')['status'] ?? null);
        $this->assertSame($run->id, $publication->metadata['last_domain_run_id'] ?? null);
        $this->assertSame('completed', $publication->metadata['last_domain_status'] ?? null);

        Http::assertSentCount(3);
        Http::assertSent(function ($request): bool {
            return $request->method() === 'POST'
                && (string) $request->url() === 'https://cloud.laravel.com/api/environments/env_456/domains'
                && $request->data()['domain'] === 'www.customer.test';
        });

        $this
            ->actingAs($user)
            ->get(route('platform.publications.edit', ['id' => $publication->id]), $this->inertiaHeaders('/platform/publications/'.$publication->id.'/edit'))
            ->assertOk()
            ->assertJsonPath('props.publication.latest_run.options.mode', 'apply_domain')
            ->assertJsonPath('props.publication.latest_run.options.domain_action', 'created');
    }

    public function test_apply_domain_requires_completed_preflight(): void
    {
        $site = $this->site(['provisioned_at' => now()]);
        $environment = $this->hostingEnvironment();
        $publication = $this->sitePublication($site, $environment);

        Http::fake();

        $this
            ->actingAs($this->platformUser())
            ->post(route('platform.publications.apply-domain', ['publication' => $publication->id]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $publication->refresh();

        $run = $publication->runs()->firstOrFail();

        $this->assertSame('failed', $publication->status);
        $this->assertSame('failed', $run->status);
        $this->assertSame('apply_domain', $run->options['mode'] ?? null);
        $this->assertSame('failed', collect($run->steps)->firstWhere('key', 'preflight')['status'] ?? null);

        Http::assertSentCount(0);
    }

    public function test_platform_admin_can_start_laravel_cloud_deployment_after_env_vars(): void
    {
        $site = $this->site(['provisioned_at' => now()]);
        $environment = $this->hostingEnvironment();
        $publication = $this->sitePublication($site, $environment);

        $this->mockSuccessfulPackageExport($site);

        $user = $this->platformUser();

        $this
            ->actingAs($user)
            ->post(route('platform.publications.prepare-publish', ['publication' => $publication->id]))
            ->assertRedirect()
            ->assertSessionHas('status');

        Http::fake([
            'https://cloud.laravel.com/api/environments/env_456' => Http::response([
                'data' => [
                    'id' => 'env_456',
                    'type' => 'environments',
                    'attributes' => [
                        'environment_variables' => [
                            ['key' => 'APP_KEY', 'value' => 'redacted'],
                        ],
                    ],
                ],
            ]),
            'https://cloud.laravel.com/api/environments/env_456/variables' => Http::response([
                'data' => ['id' => 'env_456', 'type' => 'environments'],
            ]),
            'https://cloud.laravel.com/api/environments/env_456/deployments' => Http::response([
                'data' => [
                    'id' => 'deployment_123',
                    'type' => 'deployments',
                    'attributes' => ['status' => 'pending'],
                ],
            ], 201),
        ]);

        $this
            ->actingAs($user)
            ->post(route('platform.publications.apply-env-vars', ['publication' => $publication->id]))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this
            ->actingAs($user)
            ->post(route('platform.publications.start-deployment', ['publication' => $publication->id]))
            ->assertRedirect()
            ->assertSessionHas('status');

        $publication->refresh();

        $run = $publication->runs()->latest('id')->firstOrFail();

        $this->assertSame('ready', $publication->status);
        $this->assertSame('completed', $run->status);
        $this->assertSame('deployment', $run->options['mode'] ?? null);
        $this->assertSame('deployment_123', $run->options['deployment_id'] ?? null);
        $this->assertSame('pending', $run->options['deployment_status'] ?? null);
        $this->assertSame('passed', collect($run->steps)->firstWhere('key', 'deployment')['status'] ?? null);
        $this->assertSame($run->id, $publication->metadata['last_deployment_run_id'] ?? null);
        $this->assertSame('completed', $publication->metadata['last_deployment_status'] ?? null);

        Http::assertSent(function ($request): bool {
            return $request->method() === 'POST'
                && (string) $request->url() === 'https://cloud.laravel.com/api/environments/env_456/deployments';
        });
    }

    public function test_platform_admin_can_run_remote_setup_after_deployment(): void
    {
        $site = $this->site(['provisioned_at' => now()]);
        $environment = $this->hostingEnvironment();
        $publication = $this->sitePublication($site, $environment);

        $this->mockSuccessfulPackageExport($site);

        $user = $this->platformUser();

        $this
            ->actingAs($user)
            ->post(route('platform.publications.prepare-publish', ['publication' => $publication->id]))
            ->assertRedirect()
            ->assertSessionHas('status');

        Http::fake(function ($request) {
            $url = (string) $request->url();

            if ($request->method() === 'GET' && $url === 'https://cloud.laravel.com/api/environments/env_456') {
                return Http::response([
                    'data' => [
                        'id' => 'env_456',
                        'type' => 'environments',
                        'attributes' => [
                            'environment_variables' => [
                                ['key' => 'APP_KEY', 'value' => 'redacted'],
                            ],
                        ],
                    ],
                ]);
            }

            if ($request->method() === 'POST' && $url === 'https://cloud.laravel.com/api/environments/env_456/variables') {
                return Http::response(['data' => ['id' => 'env_456', 'type' => 'environments']]);
            }

            if ($request->method() === 'POST' && $url === 'https://cloud.laravel.com/api/environments/env_456/deployments') {
                return Http::response([
                    'data' => [
                        'id' => 'deployment_123',
                        'type' => 'deployments',
                        'attributes' => ['status' => 'pending'],
                    ],
                ], 201);
            }

            if ($request->method() === 'POST' && $url === 'https://cloud.laravel.com/api/environments/env_456/commands') {
                $command = (string) $request->data()['command'];

                return Http::response([
                    'data' => [
                        'id' => str_contains($command, 'rwsoft:install') ? 'command_install' : 'command_clear',
                        'type' => 'commands',
                        'attributes' => ['status' => 'pending'],
                    ],
                ], 201);
            }

            if ($request->method() === 'GET' && $url === 'https://cloud.laravel.com/api/commands/command_install') {
                return Http::response([
                    'data' => [
                        'id' => 'command_install',
                        'type' => 'commands',
                        'attributes' => [
                            'status' => 'command.success',
                            'exit_code' => 0,
                            'output' => 'RwSoft installation completed.',
                        ],
                    ],
                ]);
            }

            if ($request->method() === 'GET' && $url === 'https://cloud.laravel.com/api/commands/command_clear') {
                return Http::response([
                    'data' => [
                        'id' => 'command_clear',
                        'type' => 'commands',
                        'attributes' => [
                            'status' => 'command.success',
                            'exit_code' => 0,
                            'output' => 'Caches cleared successfully.',
                        ],
                    ],
                ]);
            }

            return Http::response(['message' => 'Unexpected request.'], 500);
        });

        $this
            ->actingAs($user)
            ->post(route('platform.publications.apply-env-vars', ['publication' => $publication->id]))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this
            ->actingAs($user)
            ->post(route('platform.publications.start-deployment', ['publication' => $publication->id]))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this
            ->actingAs($user)
            ->post(route('platform.publications.remote-setup', ['publication' => $publication->id]))
            ->assertRedirect()
            ->assertSessionHas('status');

        $publication->refresh();

        $run = $publication->runs()->latest('id')->firstOrFail();

        $this->assertSame('synced', $publication->status);
        $this->assertSame('completed', $run->status);
        $this->assertSame('remote_setup', $run->options['mode'] ?? null);
        $this->assertSame(['rwsoft_install', 'optimize_clear'], collect($run->options['commands'] ?? [])->pluck('key')->all());
        $this->assertSame('command_install', $run->options['commands'][0]['command_id'] ?? null);
        $this->assertStringContainsString('rwsoft:install', $run->options['commands'][0]['command'] ?? '');
        $this->assertStringContainsString('--tenant-storage=shared_prefixed', $run->options['commands'][0]['command'] ?? '');
        $this->assertStringContainsString('--site-slug='.'\''.'customer-site'.'\'', $run->options['commands'][0]['command'] ?? '');
        $this->assertSame('command.success', $run->options['commands'][0]['status'] ?? null);
        $this->assertSame(0, $run->options['commands'][0]['exit_code'] ?? null);
        $this->assertSame('passed', collect($run->steps)->firstWhere('key', 'commands')['status'] ?? null);
        $this->assertSame($run->id, $publication->metadata['last_remote_setup_run_id'] ?? null);
        $this->assertSame('completed', $publication->metadata['last_remote_setup_status'] ?? null);

        Http::assertSent(function ($request): bool {
            return $request->method() === 'POST'
                && (string) $request->url() === 'https://cloud.laravel.com/api/environments/env_456/commands'
                && str_contains((string) $request->data()['command'], 'rwsoft:install');
        });
        Http::assertSent(function ($request): bool {
            return $request->method() === 'POST'
                && (string) $request->url() === 'https://cloud.laravel.com/api/environments/env_456/commands'
                && $request->data()['command'] === 'php artisan optimize:clear';
        });
    }

    public function test_start_deployment_requires_completed_environment_variable_run(): void
    {
        $site = $this->site(['provisioned_at' => now()]);
        $environment = $this->hostingEnvironment();
        $publication = $this->sitePublication($site, $environment);

        $this->mockSuccessfulPackageExport($site);

        $user = $this->platformUser();

        $this
            ->actingAs($user)
            ->post(route('platform.publications.prepare-publish', ['publication' => $publication->id]))
            ->assertRedirect()
            ->assertSessionHas('status');

        Http::fake();

        $this
            ->actingAs($user)
            ->post(route('platform.publications.start-deployment', ['publication' => $publication->id]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $publication->refresh();

        $run = $publication->runs()->latest('id')->firstOrFail();

        $this->assertSame('failed', $publication->status);
        $this->assertSame('failed', $run->status);
        $this->assertSame('deployment', $run->options['mode'] ?? null);
        $this->assertSame('failed', collect($run->steps)->firstWhere('key', 'deployment')['status'] ?? null);

        Http::assertSentCount(0);
    }

    public function test_remote_setup_requires_completed_deployment_run(): void
    {
        $site = $this->site(['provisioned_at' => now()]);
        $environment = $this->hostingEnvironment();
        $publication = $this->sitePublication($site, $environment);

        $this->mockSuccessfulPackageExport($site);

        $user = $this->platformUser();

        $this
            ->actingAs($user)
            ->post(route('platform.publications.prepare-publish', ['publication' => $publication->id]))
            ->assertRedirect()
            ->assertSessionHas('status');

        Http::fake();

        $this
            ->actingAs($user)
            ->post(route('platform.publications.remote-setup', ['publication' => $publication->id]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $publication->refresh();

        $run = $publication->runs()->latest('id')->firstOrFail();

        $this->assertSame('failed', $publication->status);
        $this->assertSame('failed', $run->status);
        $this->assertSame('remote_setup', $run->options['mode'] ?? null);
        $this->assertSame('failed', collect($run->steps)->firstWhere('key', 'commands')['status'] ?? null);

        Http::assertSentCount(0);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Site $site, HostingEnvironment $environment, array $overrides = []): array
    {
        return array_merge([
            'site_id' => $site->id,
            'hosting_environment_id' => $environment->id,
            'remote_site_slug' => 'customer-site',
            'remote_domain' => 'https://www.customer.test/demo',
            'remote_tenant_database_mode' => 'shared_prefixed',
            'remote_tenant_database' => 'rwsoft_cloud',
            'remote_tenant_table_prefix' => 't_customer_site_',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function site(array $overrides = []): Site
    {
        return Site::query()->create(array_merge([
            'name' => 'Customer Site',
            'slug' => 'customer-site',
            'tenant_database' => 'rwsoft_customer_site',
            'tenant_database_mode' => 'separate',
            'tenant_provisioning_mode' => 'create_database',
            'status' => 'active',
        ], $overrides));
    }

    private function hostingEnvironment(array $overrides = []): HostingEnvironment
    {
        $connection = HostingConnection::query()->create([
            'name' => 'Laravel Cloud Production',
            'provider' => 'laravel_cloud',
            'api_token' => 'cloud-token',
            'status' => 'ready',
        ]);

        return HostingEnvironment::query()->create(array_merge([
            'hosting_connection_id' => $connection->id,
            'name' => 'Production',
            'provider_application_id' => 'app_123',
            'provider_environment_id' => 'env_456',
            'provider_region' => 'eu-west-1',
            'default_tenant_database_mode' => 'shared_prefixed',
            'default_database_name' => 'rwsoft_cloud',
            'default_storage_mode' => 'environment',
            'status' => 'synced',
            'last_synced_at' => now(),
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function sitePublication(Site $site, HostingEnvironment $environment, array $overrides = []): SitePublication
    {
        return SitePublication::query()->create(array_merge([
            'site_id' => $site->id,
            'hosting_environment_id' => $environment->id,
            'remote_site_slug' => 'customer-site',
            'remote_domain' => 'www.customer.test',
            'remote_tenant_database_mode' => 'shared_prefixed',
            'remote_tenant_database' => 'rwsoft_cloud',
            'remote_tenant_table_prefix' => 't_customer_site_',
            'status' => 'draft',
        ], $overrides));
    }

    private function mockSuccessfulPackageExport(Site $site): void
    {
        $this->mock(ConfigureTenantDatabaseAction::class, function (MockInterface $mock) use ($site): void {
            $mock->expects('handle')->once()->withArgs(fn (Site $configuredSite): bool => $configuredSite->is($site));
        });

        $this->mock(BuildCmsSitePackageZipAction::class, function (MockInterface $mock): void {
            $mock->expects('handle')->once()->andReturn([
                'path' => '/tmp/rwsoft-site-package.zip',
                'filename' => 'publish-customer-site.zip',
                'key' => 'publish-customer-site',
            ]);
        });
    }

    private function platformUser(): User
    {
        return User::factory()->create([
            'is_platform_admin' => true,
            'email' => 'publication-platform-admin-'.uniqid().'@example.com',
            'two_factor_secret' => encrypt('platform-publication-test-secret'),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function inertiaHeaders(string $path): array
    {
        return [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(Request::create($path, 'GET')),
        ];
    }
}
