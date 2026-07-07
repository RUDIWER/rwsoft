<?php

namespace Tests\Feature\Platform;

use App\Actions\Platform\Mail\ConfigurePlatformMailTransportAction;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Platform\PlatformMailTransport;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PlatformMailTransportTest extends TestCase
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

    public function test_store_encrypts_secret_and_masks_payload(): void
    {
        $user = $this->platformUser();

        $this
            ->actingAs($user)
            ->post(route('platform.mail-transport.store'), $this->payload())
            ->assertRedirect(route('platform.mail-transport.edit'))
            ->assertSessionHas('status');

        $transport = PlatformMailTransport::query()->firstOrFail();

        $this->assertNotSame('smtp-secret', $transport->encrypted_secret);
        $this->assertSame('smtp-secret', $transport->secret());
        $this->assertFalse($transport->is_active);
        $this->assertSame('not_tested', $transport->status);

        $this
            ->actingAs($user)
            ->get(route('platform.mail-transport.edit'), $this->inertiaHeaders('/platform/mail-transport'))
            ->assertOk()
            ->assertJsonPath('component', 'Platform/MailTransport/Edit')
            ->assertJsonPath('props.transport.has_secret', true)
            ->assertJsonMissingPath('props.transport.encrypted_secret');
    }

    public function test_successful_test_marks_transport_ready_but_not_active(): void
    {
        Mail::fake();
        $user = $this->platformUser();

        $this
            ->actingAs($user)
            ->post(route('platform.mail-transport.test'), $this->payload())
            ->assertRedirect()
            ->assertSessionHas('status');

        $transport = PlatformMailTransport::query()->firstOrFail();

        $this->assertSame('ready', $transport->status);
        $this->assertSame('success', $transport->last_test_status);
        $this->assertFalse($transport->is_active);
        $this->assertNotNull($transport->last_tested_at);
    }

    public function test_transport_can_only_be_activated_after_successful_test(): void
    {
        Mail::fake();
        $user = $this->platformUser();

        $this
            ->actingAs($user)
            ->post(route('platform.mail-transport.store'), $this->payload())
            ->assertRedirect();

        $this
            ->actingAs($user)
            ->post(route('platform.mail-transport.activate'))
            ->assertRedirect()
            ->assertSessionHas('warning');

        $this->assertFalse(PlatformMailTransport::query()->firstOrFail()->is_active);

        $this
            ->actingAs($user)
            ->post(route('platform.mail-transport.test'), $this->payload())
            ->assertRedirect();

        $this
            ->actingAs($user)
            ->post(route('platform.mail-transport.activate'))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertTrue(PlatformMailTransport::query()->firstOrFail()->is_active);
    }

    public function test_configure_action_registers_platform_mailer(): void
    {
        $transport = PlatformMailTransport::query()->create([
            'name' => 'Default SMTP',
            'provider' => 'smtp',
            'is_active' => true,
            'status' => 'ready',
            'from_name' => 'Platform Mail',
            'from_email' => 'mail@example.com',
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'smtp-user',
        ]);
        $transport->setSecret('smtp-secret');
        $transport->save();

        $mailerName = app(ConfigurePlatformMailTransportAction::class)->handle();

        $this->assertSame('platform_smtp', $mailerName);
        $this->assertSame('smtp.example.com', config('mail.mailers.platform_smtp.host'));
        $this->assertSame('smtp-secret', config('mail.mailers.platform_smtp.password'));
        $this->assertSame('mail@example.com', config('mail.from.address'));
        $this->assertSame('Platform Mail', config('mail.from.name'));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Default SMTP',
            'provider' => 'smtp',
            'from_name' => 'Platform Mail',
            'from_email' => 'mail@example.com',
            'reply_to_email' => 'reply@example.com',
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'smtp-user',
            'secret' => 'smtp-secret',
        ], $overrides);
    }

    private function platformUser(): User
    {
        return User::factory()->create([
            'is_platform_admin' => true,
            'email' => 'platform-admin@example.com',
            'two_factor_secret' => encrypt('platform-mail-transport-test-secret'),
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
