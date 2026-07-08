<?php

namespace Tests\Feature\Platform;

use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Platform\HostingConnection;
use App\Models\Platform\HostingEnvironment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HostingConnectionTest extends TestCase
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

    public function test_platform_admin_can_store_hosting_connection_without_exposing_token(): void
    {
        $user = $this->platformUser();

        $this
            ->actingAs($user)
            ->post(route('platform.hosting.store', ['id' => 0]), $this->payload())
            ->assertRedirect()
            ->assertSessionHas('status');

        $connection = HostingConnection::query()
            ->where('name', 'Laravel Cloud Production')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('Laravel Cloud Production', $connection->name);
        $this->assertSame('laravel_cloud', $connection->provider);
        $this->assertSame('not_tested', $connection->status);
        $this->assertSame('cloud-token', $connection->api_token);
        $this->assertTrue($connection->hasApiToken());
        $this->assertNotSame(
            'cloud-token',
            DB::connection('central')
                ->table('platform_hosting_connections')
                ->where('id', $connection->id)
                ->value('api_token')
        );

        $this
            ->actingAs($user)
            ->get(route('platform.hosting.edit', ['id' => $connection->id]), $this->inertiaHeaders('/platform/hosting/'.$connection->id.'/edit'))
            ->assertOk()
            ->assertJsonPath('component', 'Platform/Hosting/Edit')
            ->assertJsonPath('props.connection.has_api_token', true)
            ->assertJsonMissingPath('props.connection.api_token');
    }

    public function test_successful_laravel_cloud_test_marks_connection_ready(): void
    {
        Http::fake([
            'https://cloud.laravel.com/api/applications*' => Http::response([
                'data' => [
                    ['id' => 'app_123', 'type' => 'applications'],
                ],
            ]),
        ]);

        $connection = $this->hostingConnection();

        $this
            ->actingAs($this->platformUser())
            ->post(route('platform.hosting.test', ['connection' => $connection->id]))
            ->assertRedirect()
            ->assertSessionHas('status');

        $connection->refresh();

        $this->assertSame('ready', $connection->status);
        $this->assertNull($connection->last_error);
        $this->assertSame(1, $connection->metadata['last_applications_count'] ?? null);
        $this->assertNotNull($connection->last_checked_at);

        Http::assertSentCount(1);
    }

    public function test_failed_laravel_cloud_test_marks_connection_failed(): void
    {
        Http::fake([
            'https://cloud.laravel.com/api/applications*' => Http::response([
                'message' => 'Unauthenticated.',
            ], 401),
        ]);

        $connection = $this->hostingConnection();

        $this
            ->actingAs($this->platformUser())
            ->post(route('platform.hosting.test', ['connection' => $connection->id]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $connection->refresh();

        $this->assertSame('failed', $connection->status);
        $this->assertStringContainsString('Laravel Cloud API error 401', (string) $connection->last_error);
        $this->assertNotNull($connection->last_checked_at);

        Http::assertSentCount(1);
    }

    public function test_laravel_cloud_environments_can_be_synced(): void
    {
        Http::fake([
            'https://cloud.laravel.com/api/applications?*' => Http::response([
                'data' => [
                    [
                        'id' => 'app_123',
                        'type' => 'applications',
                        'attributes' => [
                            'name' => 'Customer App',
                            'slug' => 'customer-app',
                            'region' => 'eu-west-1',
                        ],
                    ],
                ],
            ]),
            'https://cloud.laravel.com/api/applications/app_123/environments*' => Http::response([
                'data' => [
                    [
                        'id' => 'env_456',
                        'type' => 'environments',
                        'attributes' => [
                            'name' => 'Production',
                            'slug' => 'production',
                            'status' => 'running',
                            'vanity_domain' => 'customer-app.laravel.cloud',
                            'php_major_version' => '8.5',
                            'node_version' => '24',
                        ],
                    ],
                ],
            ]),
        ]);

        $connection = $this->hostingConnection();

        $this
            ->actingAs($this->platformUser())
            ->post(route('platform.hosting.environments.sync', ['connection' => $connection->id]))
            ->assertRedirect()
            ->assertSessionHas('status');

        $environment = HostingEnvironment::query()
            ->where('hosting_connection_id', $connection->id)
            ->where('provider_environment_id', 'env_456')
            ->firstOrFail();

        $this->assertSame($connection->id, $environment->hosting_connection_id);
        $this->assertSame('Production', $environment->name);
        $this->assertSame('app_123', $environment->provider_application_id);
        $this->assertSame('env_456', $environment->provider_environment_id);
        $this->assertSame('eu-west-1', $environment->provider_region);
        $this->assertSame('synced', $environment->status);
        $this->assertSame('Customer App', $environment->metadata['application']['name'] ?? null);
        $this->assertSame('running', $environment->metadata['environment']['status'] ?? null);
        $this->assertNotNull($environment->last_synced_at);

        $connection->refresh();

        $this->assertSame('ready', $connection->status);
        $this->assertSame(1, $connection->metadata['last_applications_count'] ?? null);
        $this->assertSame(1, $connection->metadata['last_environments_count'] ?? null);

        $this
            ->actingAs($this->platformUser())
            ->get(route('platform.hosting.edit', ['id' => $connection->id]), $this->inertiaHeaders('/platform/hosting/'.$connection->id.'/edit'))
            ->assertOk()
            ->assertJsonPath('props.connection.environments.0.provider_environment_id', 'env_456')
            ->assertJsonPath('props.connection.environments.0.metadata.application.name', 'Customer App');

        Http::assertSentCount(2);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Laravel Cloud Production',
            'provider' => 'laravel_cloud',
            'api_base_url' => null,
            'api_token' => 'cloud-token',
        ], $overrides);
    }

    private function hostingConnection(): HostingConnection
    {
        return HostingConnection::query()->create($this->payload());
    }

    private function platformUser(): User
    {
        return User::factory()->create([
            'is_platform_admin' => true,
            'email' => 'hosting-platform-admin-'.uniqid().'@example.com',
            'two_factor_secret' => encrypt('platform-hosting-test-secret'),
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
