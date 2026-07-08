<?php

namespace App\Actions\Platform;

use App\Models\Platform\SitePublication;
use App\Models\Platform\SitePublicationRun;
use App\Support\Hosting\HostingProviderManager;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProvisionSitePublicationDatabaseAction
{
    public function __construct(private readonly HostingProviderManager $providers) {}

    /**
     * @return array{run: SitePublicationRun, failed: bool}
     */
    public function handle(SitePublication $publication, ?int $userId = null, bool $createIfMissing = false): array
    {
        $publication->loadMissing(['hostingEnvironment.connection']);
        $preflight = $this->latestCompletedPreflight($publication);

        if (! $preflight instanceof SitePublicationRun) {
            return $this->failedRun($publication, $userId, __('admin_common_ui.platform.publications.databases.no_preflight'));
        }

        $providerPlan = is_array($preflight->options['provider_plan'] ?? null) ? $preflight->options['provider_plan'] : [];
        $applicationId = (string) ($providerPlan['provider_application_id'] ?? '');
        $environmentId = (string) ($providerPlan['provider_environment_id'] ?? '');

        if ($applicationId === '' || $environmentId === '') {
            return $this->failedRun($publication, $userId, __('admin_common_ui.platform.publications.databases.invalid_preflight'), $preflight);
        }

        $startedAt = now();
        $steps = [
            $this->step('preflight', 'passed', __('admin_common_ui.platform.publications.databases.preflight_ready', ['id' => $preflight->id])),
            $this->step('database', 'running', __('admin_common_ui.platform.publications.databases.checking')),
        ];

        try {
            $connection = $publication->hostingEnvironment?->connection;

            if ($connection === null) {
                throw new \RuntimeException(__('admin_common_ui.platform.publications.databases.connection_missing'));
            }

            $provider = $this->providers->providerFor($connection);
            $database = $provider->getEnvironmentDatabase($connection, $applicationId, $environmentId);

            if ($database['database_id'] === null && $createIfMissing) {
                $createdDatabase = $provider->createDatabaseCluster(
                    $connection,
                    $this->databaseName($publication),
                    $this->databaseRegion($publication),
                    'laravel_mysql_84',
                    $this->databaseConfig(),
                );
                $schemaId = $createdDatabase['schema_id'] ?? null;

                if ($schemaId === null && isset($createdDatabase['database_id'])) {
                    $schemas = collect($provider->listDatabaseSchemas($connection, $createdDatabase['database_id']));
                    $schemaId = ($schemas->firstWhere('status', 'available') ?? $schemas->first())['id'] ?? null;
                }

                if ($schemaId !== null) {
                    $attachedDatabase = $provider->attachDatabaseToEnvironment($connection, $environmentId, $schemaId);

                    $steps[1] = $this->step('database', 'passed', __('admin_common_ui.platform.publications.databases.created_and_attached', [
                        'id' => $attachedDatabase['database_id'] ?? $createdDatabase['database_id'] ?? '-',
                        'schema' => $schemaId,
                    ]));

                    $run = $this->storeRun($publication, $userId, $preflight, $startedAt, 'completed', $steps, array_merge($createdDatabase, $attachedDatabase, [
                        'database_action' => 'created_and_attached',
                        'schema_id' => $schemaId,
                        'requires_attach' => false,
                    ]));

                    return ['run' => $run, 'failed' => false];
                }

                $steps[1] = $this->step('database', 'warning', __('admin_common_ui.platform.publications.databases.created_not_attached', [
                    'id' => $createdDatabase['database_id'] ?? '-',
                    'status' => $createdDatabase['status'] ?? '-',
                ]));

                $run = $this->storeRun($publication, $userId, $preflight, $startedAt, 'pending', $steps, array_merge($createdDatabase, [
                    'database_action' => 'created',
                    'requires_attach' => true,
                ]));

                return ['run' => $run, 'failed' => false];
            }

            if ($database['database_id'] === null) {
                $steps[1] = $this->step('database', 'failed', __('admin_common_ui.platform.publications.databases.missing'));

                $run = $this->storeRun($publication, $userId, $preflight, $startedAt, 'failed', $steps, array_merge($database, [
                    'database_action' => 'detect',
                ]), __('admin_common_ui.platform.publications.databases.missing'));

                return ['run' => $run, 'failed' => true];
            }

            $steps[1] = $this->step('database', 'passed', __('admin_common_ui.platform.publications.databases.detected', [
                'id' => $database['database_id'],
                'status' => $database['status'] ?? '-',
            ]));

            $run = $this->storeRun($publication, $userId, $preflight, $startedAt, 'completed', $steps, array_merge($database, [
                'database_action' => 'detected',
                'requires_attach' => false,
            ]));

            return ['run' => $run, 'failed' => false];
        } catch (Throwable $exception) {
            report($exception);

            $steps[1] = $this->step('database', 'failed', __('admin_common_ui.platform.publications.databases.check_failed', [
                'message' => $exception->getMessage(),
            ]));

            $run = $this->storeRun($publication, $userId, $preflight, $startedAt, 'failed', $steps, [], $exception->getMessage());

            return ['run' => $run, 'failed' => true];
        }
    }

    private function latestCompletedPreflight(SitePublication $publication): ?SitePublicationRun
    {
        return $publication->runs()
            ->where('status', 'completed')
            ->where('options->mode', 'preflight')
            ->latest('id')
            ->first();
    }

    /**
     * @param  array<int, array{key: string, label: string, status: string, message: string}>  $steps
     * @param  array<string, mixed>  $database
     */
    private function storeRun(
        SitePublication $publication,
        ?int $userId,
        SitePublicationRun $preflight,
        \DateTimeInterface $startedAt,
        string $status,
        array $steps,
        array $database,
        ?string $errorMessage = null,
    ): SitePublicationRun {
        return DB::connection('central')->transaction(function () use ($publication, $userId, $preflight, $startedAt, $status, $steps, $database, $errorMessage): SitePublicationRun {
            $run = $publication->runs()->create([
                'direction' => 'push',
                'status' => $status,
                'started_at' => $startedAt,
                'finished_at' => now(),
                'steps' => $steps,
                'options' => array_merge([
                    'mode' => 'provision_database',
                    'source_preflight_run_id' => $preflight->id,
                ], $database),
                'error_message' => $errorMessage,
                'created_by' => $userId,
            ]);

            $publication->forceFill([
                'status' => $status === 'failed' ? 'failed' : 'ready',
                'metadata' => array_merge($publication->metadata ?? [], [
                    'last_database_run_id' => $run->id,
                    'last_database_status' => $run->status,
                    'last_database_at' => $run->finished_at?->toIso8601String(),
                    'last_database_id' => $database['database_id'] ?? null,
                    'last_database_requires_attach' => $database['requires_attach'] ?? false,
                ]),
            ])->save();

            return $run;
        });
    }

    /**
     * @return array{run: SitePublicationRun, failed: bool}
     */
    private function failedRun(SitePublication $publication, ?int $userId, string $message, ?SitePublicationRun $preflight = null): array
    {
        $startedAt = now();
        $run = DB::connection('central')->transaction(function () use ($publication, $userId, $message, $preflight, $startedAt): SitePublicationRun {
            $run = $publication->runs()->create([
                'direction' => 'push',
                'status' => 'failed',
                'started_at' => $startedAt,
                'finished_at' => now(),
                'steps' => [$this->step('database', 'failed', $message)],
                'options' => [
                    'mode' => 'provision_database',
                    'source_preflight_run_id' => $preflight?->id,
                ],
                'error_message' => $message,
                'created_by' => $userId,
            ]);

            $publication->forceFill(['status' => 'failed'])->save();

            return $run;
        });

        return ['run' => $run, 'failed' => true];
    }

    private function databaseName(SitePublication $publication): string
    {
        $name = str($publication->remote_site_slug ?: 'rwsoft')
            ->lower()
            ->replace('-', '_')
            ->replaceMatches('/[^a-z0-9_]/', '')
            ->trim('_')
            ->toString();

        return str($name !== '' ? $name : 'rwsoft')->limit(40, '')->toString();
    }

    private function databaseRegion(SitePublication $publication): string
    {
        $environment = $publication->hostingEnvironment;
        $region = (string) ($environment?->provider_region ?: data_get($environment?->metadata, 'application.region', ''));

        return $region !== '' ? $region : 'eu-central-1';
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseConfig(): array
    {
        return [
            'size' => 'db-flex.m-1vcpu-512mb',
            'storage' => 5,
            'is_public' => false,
            'uses_scheduled_snapshots' => false,
            'retention_days' => 0,
        ];
    }

    /**
     * @return array{key: string, label: string, status: string, message: string}
     */
    private function step(string $key, string $status, string $message): array
    {
        return [
            'key' => $key,
            'label' => __('admin_common_ui.platform.publications.databases.steps.'.$key),
            'status' => $status,
            'message' => $message,
        ];
    }
}
