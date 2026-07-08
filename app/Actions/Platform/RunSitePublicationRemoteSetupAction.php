<?php

namespace App\Actions\Platform;

use App\Models\Platform\HostingConnection;
use App\Models\Platform\SitePublication;
use App\Models\Platform\SitePublicationRun;
use App\Support\Hosting\HostingProviderInterface;
use App\Support\Hosting\HostingProviderManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class RunSitePublicationRemoteSetupAction
{
    private const MAX_POLLS = 30;

    private const POLL_SLEEP_SECONDS = 3;

    public function __construct(private readonly HostingProviderManager $providers) {}

    /**
     * @return array{run: SitePublicationRun, failed: bool}
     */
    public function handle(SitePublication $publication, ?int $userId = null): array
    {
        $publication->loadMissing(['site', 'hostingEnvironment.connection']);
        $preflight = $this->latestCompletedRun($publication, 'preflight');
        $deploymentRun = $this->latestCompletedRun($publication, 'deployment');

        if (! $preflight instanceof SitePublicationRun) {
            return $this->failedRun($publication, $userId, __('admin_common_ui.platform.publications.remote_setup.no_preflight'));
        }

        if (! $deploymentRun instanceof SitePublicationRun) {
            return $this->failedRun($publication, $userId, __('admin_common_ui.platform.publications.remote_setup.no_deployment'), $preflight);
        }

        $providerPlan = is_array($preflight->options['provider_plan'] ?? null) ? $preflight->options['provider_plan'] : [];
        $environmentId = (string) ($providerPlan['provider_environment_id'] ?? '');

        if ($environmentId === '') {
            return $this->failedRun($publication, $userId, __('admin_common_ui.platform.publications.remote_setup.invalid_preflight'), $preflight, $deploymentRun);
        }

        $startedAt = now();
        $commands = $this->commands($publication);
        $steps = [
            $this->step('preflight', 'passed', __('admin_common_ui.platform.publications.remote_setup.preflight_ready', ['id' => $preflight->id])),
            $this->step('deployment', 'passed', __('admin_common_ui.platform.publications.remote_setup.deployment_ready', ['id' => $deploymentRun->id])),
            $this->step('commands', 'running', __('admin_common_ui.platform.publications.remote_setup.running', ['count' => count($commands)])),
        ];
        $results = [];

        try {
            $connection = $publication->hostingEnvironment?->connection;

            if ($connection === null) {
                throw new RuntimeException(__('admin_common_ui.platform.publications.remote_setup.connection_missing'));
            }

            $provider = $this->providers->providerFor($connection);
            foreach ($commands as $command) {
                $startedCommand = $provider->runCommand($connection, $environmentId, $command['command']);
                $commandId = (string) ($startedCommand['command_id'] ?? '');

                if ($commandId === '') {
                    throw new RuntimeException(__('admin_common_ui.platform.publications.remote_setup.command_id_missing', ['key' => $command['key']]));
                }

                $completedCommand = $this->pollCommand($provider, $connection, $commandId);

                $results[] = [
                    'key' => $command['key'],
                    'command' => $command['command'],
                    'command_id' => $commandId,
                    'status' => $completedCommand['status'],
                    'exit_code' => $completedCommand['exit_code'],
                    'output' => Str::limit((string) ($completedCommand['output'] ?? ''), 4000, '...'),
                ];

                if (! $this->commandSucceeded($completedCommand)) {
                    throw new RuntimeException(__('admin_common_ui.platform.publications.remote_setup.command_failed', [
                        'key' => $command['key'],
                        'status' => $completedCommand['status'] ?? '-',
                    ]));
                }
            }

            $steps[2] = $this->step('commands', 'passed', __('admin_common_ui.platform.publications.remote_setup.completed', ['count' => count($results)]));

            $run = $this->storeRun($publication, $userId, $preflight, $deploymentRun, $startedAt, 'completed', $steps, [
                'commands' => $results,
            ]);

            return ['run' => $run, 'failed' => false];
        } catch (Throwable $exception) {
            report($exception);

            $steps[2] = $this->step('commands', 'failed', __('admin_common_ui.platform.publications.remote_setup.failed', [
                'message' => $exception->getMessage(),
            ]));

            $run = $this->storeRun($publication, $userId, $preflight, $deploymentRun, $startedAt, 'failed', $steps, [
                'commands' => $results,
            ], $exception->getMessage());

            return ['run' => $run, 'failed' => true];
        }
    }

    private function latestCompletedRun(SitePublication $publication, string $mode): ?SitePublicationRun
    {
        return $publication->runs()
            ->where('status', 'completed')
            ->where('options->mode', $mode)
            ->latest('id')
            ->first();
    }

    /**
     * @return array<int, array{key: string, command: string}>
     */
    private function commands(SitePublication $publication): array
    {
        $remoteSiteName = escapeshellarg($publication->site?->name ?: $publication->remote_site_slug);
        $remoteSlug = escapeshellarg($publication->remote_site_slug);
        $remoteDomain = blank($publication->remote_domain) ? null : escapeshellarg((string) $publication->remote_domain);
        $tenantStorage = $publication->remote_tenant_database_mode === 'shared_prefixed' ? 'shared_prefixed' : 'existing_database';
        $command = 'php artisan rwsoft:install --profile=laravel-cloud --tenant-storage='.$tenantStorage.' --force --site-name='.$remoteSiteName.' --site-slug='.$remoteSlug;

        if ($remoteDomain !== null) {
            $command .= ' --site-domain='.$remoteDomain;
        }

        if (filled($publication->remote_tenant_database)) {
            $command .= ' --site-tenant-database='.escapeshellarg((string) $publication->remote_tenant_database);
        }

        if ($publication->remote_tenant_database_mode === 'shared_prefixed' && filled($publication->remote_tenant_table_prefix)) {
            $command .= ' --site-tenant-prefix='.escapeshellarg((string) $publication->remote_tenant_table_prefix);
        }

        return [
            ['key' => 'rwsoft_install', 'command' => $command],
            ['key' => 'optimize_clear', 'command' => 'php artisan optimize:clear'],
        ];
    }

    /**
     * @return array{command_id: string|null, status: string|null, exit_code: int|null, output: string|null}
     */
    private function pollCommand(HostingProviderInterface $provider, HostingConnection $connection, string $commandId): array
    {
        for ($attempt = 0; $attempt < self::MAX_POLLS; $attempt++) {
            $result = $provider->getCommand($connection, $commandId);
            $status = (string) ($result['status'] ?? '');

            if ($this->commandFinishedStatus($status)) {
                return $result;
            }

            sleep(self::POLL_SLEEP_SECONDS);
        }

        throw new RuntimeException(__('admin_common_ui.platform.publications.remote_setup.command_timeout', ['id' => $commandId]));
    }

    /**
     * @param  array{command_id: string|null, status: string|null, exit_code: int|null, output: string|null}  $command
     */
    private function commandSucceeded(array $command): bool
    {
        return in_array($command['status'], ['command.success', 'success'], true)
            && ($command['exit_code'] === null || $command['exit_code'] === 0);
    }

    private function commandFinishedStatus(string $status): bool
    {
        return in_array($status, [
            'command.success',
            'command.failed',
            'command.cancelled',
            'success',
            'failed',
            'cancelled',
        ], true);
    }

    /**
     * @param  array<int, array{key: string, label: string, status: string, message: string}>  $steps
     * @param  array<string, mixed>  $extraOptions
     */
    private function storeRun(
        SitePublication $publication,
        ?int $userId,
        SitePublicationRun $preflight,
        SitePublicationRun $deploymentRun,
        \DateTimeInterface $startedAt,
        string $status,
        array $steps,
        array $extraOptions,
        ?string $errorMessage = null,
    ): SitePublicationRun {
        return DB::connection('central')->transaction(function () use ($publication, $userId, $preflight, $deploymentRun, $startedAt, $status, $steps, $extraOptions, $errorMessage): SitePublicationRun {
            $run = $publication->runs()->create([
                'direction' => 'push',
                'status' => $status,
                'started_at' => $startedAt,
                'finished_at' => now(),
                'steps' => $steps,
                'options' => array_merge([
                    'mode' => 'remote_setup',
                    'source_preflight_run_id' => $preflight->id,
                    'source_deployment_run_id' => $deploymentRun->id,
                ], $extraOptions),
                'error_message' => $errorMessage,
                'created_by' => $userId,
            ]);

            $publication->forceFill([
                'status' => $status === 'completed' ? 'synced' : 'failed',
                'metadata' => array_merge($publication->metadata ?? [], [
                    'last_remote_setup_run_id' => $run->id,
                    'last_remote_setup_status' => $run->status,
                    'last_remote_setup_at' => $run->finished_at?->toIso8601String(),
                ]),
            ])->save();

            return $run;
        });
    }

    /**
     * @return array{run: SitePublicationRun, failed: bool}
     */
    private function failedRun(SitePublication $publication, ?int $userId, string $message, ?SitePublicationRun $preflight = null, ?SitePublicationRun $deploymentRun = null): array
    {
        $startedAt = now();
        $run = DB::connection('central')->transaction(function () use ($publication, $userId, $message, $preflight, $deploymentRun, $startedAt): SitePublicationRun {
            $run = $publication->runs()->create([
                'direction' => 'push',
                'status' => 'failed',
                'started_at' => $startedAt,
                'finished_at' => now(),
                'steps' => [$this->step('commands', 'failed', $message)],
                'options' => [
                    'mode' => 'remote_setup',
                    'source_preflight_run_id' => $preflight?->id,
                    'source_deployment_run_id' => $deploymentRun?->id,
                ],
                'error_message' => $message,
                'created_by' => $userId,
            ]);

            $publication->forceFill(['status' => 'failed'])->save();

            return $run;
        });

        return ['run' => $run, 'failed' => true];
    }

    /**
     * @return array{key: string, label: string, status: string, message: string}
     */
    private function step(string $key, string $status, string $message): array
    {
        return [
            'key' => $key,
            'label' => __('admin_common_ui.platform.publications.remote_setup.steps.'.$key),
            'status' => $status,
            'message' => $message,
        ];
    }
}
