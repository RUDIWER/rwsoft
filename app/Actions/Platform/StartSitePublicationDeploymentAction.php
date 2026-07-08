<?php

namespace App\Actions\Platform;

use App\Models\Platform\SitePublication;
use App\Models\Platform\SitePublicationRun;
use App\Support\Hosting\HostingProviderManager;
use Illuminate\Support\Facades\DB;
use Throwable;

class StartSitePublicationDeploymentAction
{
    public function __construct(private readonly HostingProviderManager $providers) {}

    /**
     * @return array{run: SitePublicationRun, failed: bool}
     */
    public function handle(SitePublication $publication, ?int $userId = null): array
    {
        $publication->loadMissing(['hostingEnvironment.connection']);
        $preflight = $this->latestCompletedRun($publication, 'preflight');
        $envVarRun = $this->latestCompletedRun($publication, 'apply_env_vars');

        if (! $preflight instanceof SitePublicationRun) {
            return $this->failedRun($publication, $userId, __('admin_common_ui.platform.publications.deployments.no_preflight'));
        }

        if (! $envVarRun instanceof SitePublicationRun) {
            return $this->failedRun($publication, $userId, __('admin_common_ui.platform.publications.deployments.no_env_vars'), $preflight);
        }

        $providerPlan = is_array($preflight->options['provider_plan'] ?? null) ? $preflight->options['provider_plan'] : [];
        $applicationId = (string) ($providerPlan['provider_application_id'] ?? '');
        $environmentId = (string) ($providerPlan['provider_environment_id'] ?? '');

        if ($applicationId === '' || $environmentId === '') {
            return $this->failedRun($publication, $userId, __('admin_common_ui.platform.publications.deployments.invalid_preflight'), $preflight, $envVarRun);
        }

        $startedAt = now();
        $steps = [
            $this->step('preflight', 'passed', __('admin_common_ui.platform.publications.deployments.preflight_ready', ['id' => $preflight->id])),
            $this->step('environment_variables', 'passed', __('admin_common_ui.platform.publications.deployments.env_vars_ready', ['id' => $envVarRun->id])),
            $this->step('deployment', 'running', __('admin_common_ui.platform.publications.deployments.starting')),
        ];

        try {
            $connection = $publication->hostingEnvironment?->connection;

            if ($connection === null) {
                throw new \RuntimeException(__('admin_common_ui.platform.publications.deployments.connection_missing'));
            }

            $result = $this->providers
                ->providerFor($connection)
                ->startDeployment($connection, $applicationId, $environmentId);

            $steps[2] = $this->step('deployment', 'passed', __('admin_common_ui.platform.publications.deployments.started', [
                'id' => $result['deployment_id'] ?? '-',
                'status' => $result['status'] ?? '-',
            ]));

            $run = $this->storeRun($publication, $userId, $preflight, $envVarRun, $startedAt, 'completed', $steps, [
                'deployment_id' => $result['deployment_id'],
                'deployment_status' => $result['status'],
            ]);

            return ['run' => $run, 'failed' => false];
        } catch (Throwable $exception) {
            report($exception);

            $steps[2] = $this->step('deployment', 'failed', __('admin_common_ui.platform.publications.deployments.start_failed', [
                'message' => $exception->getMessage(),
            ]));

            $run = $this->storeRun($publication, $userId, $preflight, $envVarRun, $startedAt, 'failed', $steps, [], $exception->getMessage());

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
     * @param  array<int, array{key: string, label: string, status: string, message: string}>  $steps
     * @param  array<string, mixed>  $extraOptions
     */
    private function storeRun(
        SitePublication $publication,
        ?int $userId,
        SitePublicationRun $preflight,
        SitePublicationRun $envVarRun,
        \DateTimeInterface $startedAt,
        string $status,
        array $steps,
        array $extraOptions,
        ?string $errorMessage = null,
    ): SitePublicationRun {
        return DB::connection('central')->transaction(function () use ($publication, $userId, $preflight, $envVarRun, $startedAt, $status, $steps, $extraOptions, $errorMessage): SitePublicationRun {
            $run = $publication->runs()->create([
                'direction' => 'push',
                'status' => $status,
                'started_at' => $startedAt,
                'finished_at' => now(),
                'steps' => $steps,
                'options' => array_merge([
                    'mode' => 'deployment',
                    'source_preflight_run_id' => $preflight->id,
                    'source_env_var_run_id' => $envVarRun->id,
                ], $extraOptions),
                'error_message' => $errorMessage,
                'created_by' => $userId,
            ]);

            $publication->forceFill([
                'status' => $status === 'completed' ? 'ready' : 'failed',
                'metadata' => array_merge($publication->metadata ?? [], [
                    'last_deployment_run_id' => $run->id,
                    'last_deployment_status' => $run->status,
                    'last_deployment_at' => $run->finished_at?->toIso8601String(),
                ]),
            ])->save();

            return $run;
        });
    }

    /**
     * @return array{run: SitePublicationRun, failed: bool}
     */
    private function failedRun(SitePublication $publication, ?int $userId, string $message, ?SitePublicationRun $preflight = null, ?SitePublicationRun $envVarRun = null): array
    {
        $startedAt = now();
        $run = DB::connection('central')->transaction(function () use ($publication, $userId, $message, $preflight, $envVarRun, $startedAt): SitePublicationRun {
            $run = $publication->runs()->create([
                'direction' => 'push',
                'status' => 'failed',
                'started_at' => $startedAt,
                'finished_at' => now(),
                'steps' => [$this->step('deployment', 'failed', $message)],
                'options' => [
                    'mode' => 'deployment',
                    'source_preflight_run_id' => $preflight?->id,
                    'source_env_var_run_id' => $envVarRun?->id,
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
            'label' => __('admin_common_ui.platform.publications.deployments.steps.'.$key),
            'status' => $status,
            'message' => $message,
        ];
    }
}
