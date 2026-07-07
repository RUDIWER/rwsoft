<?php

namespace Tests\Feature\Platform;

use App\Http\Controllers\Admin\Dev\RwDbDiagram\RwDbTableViewController;
use App\Jobs\Admin\Database\GenerateDatabaseBackupJob;
use App\Models\Platform\Site;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Database\DatabaseSchemaInspector;
use App\Support\Security\TenantAcl;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class TenantDatabaseBackupJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'central',
            'database.connections.central' => array_merge(config('database.connections.mysql'), [
                'database' => 'rwsoft',
            ]),
            'database.connections.tenant' => array_merge(config('database.connections.mysql'), [
                'database' => 'rwsoft',
            ]),
        ]);

        DB::purge('central');
        DB::purge('tenant');
        DB::reconnect('tenant');
        DB::setDefaultConnection('tenant');
        DB::connection('tenant')->beginTransaction();
    }

    protected function tearDown(): void
    {
        TenantContext::clear();

        if (DB::connection('tenant')->transactionLevel() > 0) {
            DB::connection('tenant')->rollBack();
        }

        parent::tearDown();
    }

    public function test_full_backup_dispatches_job_with_active_tenant_site_id(): void
    {
        Queue::fake();

        $site = new Site([
            'name' => 'Tenant Backup Test',
            'slug' => 'tenant-backup-test',
            'tenant_database' => 'rwsoft',
            'status' => 'active',
        ]);
        $site->id = 7654321;
        TenantContext::setSite($site);

        $user = User::query()->firstOrFail();
        $user->forceFill([
            'database_view_access' => true,
            'database_full_backup_access' => true,
        ]);

        $this->mock(TenantAcl::class, function ($mock): void {
            $mock->shouldReceive('canAccessRoute')
                ->with(Mockery::type(User::class), 'admin.db-diagram.backup-full.start')
                ->andReturn(true);
        });

        $schemaInspector = Mockery::mock(DatabaseSchemaInspector::class);
        $schemaInspector->shouldReceive('getViewableTables')
            ->once()
            ->andReturn(['query_groups']);

        $auditLogger = Mockery::mock(AuditLogger::class);
        $auditLogger->shouldReceive('success')->once();

        $request = Request::create('/admin/db-diagram/backup/full/start', 'POST', [
            'tables' => ['query_groups', 'users'],
            'project_name' => 'tenant-backup-test',
        ]);
        $request->setUserResolver(static fn (): User => $user);

        $response = (new RwDbTableViewController($schemaInspector, $auditLogger))->startFullBackup($request);
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Backup proces gestart.', $payload['message'] ?? null);

        Queue::assertPushed(GenerateDatabaseBackupJob::class, function (GenerateDatabaseBackupJob $job) use ($payload): bool {
            return $job->siteId === 7654321
                && $job->backupId === (int) ($payload['backup_id'] ?? 0);
        });

        $this->assertDatabaseHas('database_logs', [
            'id' => (int) $payload['backup_id'],
            'project_name' => 'tenant-backup-test',
            'status' => 'pending',
        ], 'tenant');
    }
}
