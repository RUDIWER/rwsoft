<?php

namespace App\Actions\Platform;

use App\Models\Platform\SitePublication;
use App\Models\Platform\SitePublicationRun;
use App\Support\Hosting\HostingProviderManager;
use Illuminate\Support\Facades\DB;
use Throwable;

class ApplySitePublicationEnvironmentVariablesAction
{
    public function __construct(private readonly HostingProviderManager $providers) {}

    /**
     * @return array{run: SitePublicationRun, failed: bool}
     */
    public function handle(SitePublication $publication, ?int $userId = null): array
    {
        $publication->loadMissing(['hostingEnvironment.connection']);
        $preflight = $this->latestCompletedPreflight($publication);

        if (! $preflight instanceof SitePublicationRun) {
            return $this->failedRun(
                $publication,
                $userId,
                __('admin_common_ui.platform.publications.env_vars.no_preflight'),
            );
        }

        $providerPlan = is_array($preflight->options['provider_plan'] ?? null) ? $preflight->options['provider_plan'] : [];
        $variables = is_array($providerPlan['env_vars'] ?? null) ? $providerPlan['env_vars'] : [];
        $applicationId = (string) ($providerPlan['provider_application_id'] ?? '');
        $environmentId = (string) ($providerPlan['provider_environment_id'] ?? '');

        if ($variables === [] || $applicationId === '' || $environmentId === '') {
            return $this->failedRun(
                $publication,
                $userId,
                __('admin_common_ui.platform.publications.env_vars.invalid_preflight'),
                $preflight,
            );
        }

        $startedAt = now();
        $steps = [
            $this->step('preflight', 'passed', __('admin_common_ui.platform.publications.env_vars.preflight_ready', ['id' => $preflight->id])),
            $this->step('environment_variables', 'running', __('admin_common_ui.platform.publications.env_vars.applying', [
                'count' => count($variables),
            ])),
        ];

        try {
            $connection = $publication->hostingEnvironment?->connection;

            if ($connection === null) {
                throw new \RuntimeException(__('admin_common_ui.platform.publications.env_vars.connection_missing'));
            }

            $result = $this->providers
                ->providerFor($connection)
                ->syncEnvironmentVariables($connection, $applicationId, $environmentId, $variables);

            $steps[1] = $this->step('environment_variables', 'passed', __('admin_common_ui.platform.publications.env_vars.applied', [
                'count' => $result['updated'],
            ]));
            $steps[] = $this->step('provider_response', 'passed', __('admin_common_ui.platform.publications.env_vars.provider_response', [
                'keys' => implode(', ', $result['keys']),
            ]));

            $run = $this->storeRun($publication, $userId, $preflight, $startedAt, 'completed', $steps, [
                'applied_env_var_keys' => $result['keys'],
            ]);

            return ['run' => $run, 'failed' => false];
        } catch (Throwable $exception) {
            report($exception);

            $steps[1] = $this->step('environment_variables', 'failed', __('admin_common_ui.platform.publications.env_vars.apply_failed', [
                'message' => $exception->getMessage(),
            ]));

            $run = $this->storeRun($publication, $userId, $preflight, $startedAt, 'failed', $steps, [
                'applied_env_var_keys' => [],
            ], $exception->getMessage());

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
     * @param  array<string, mixed>  $extraOptions
     */
    private function storeRun(
        SitePublication $publication,
        ?int $userId,
        SitePublicationRun $preflight,
        \DateTimeInterface $startedAt,
        string $status,
        array $steps,
        array $extraOptions,
        ?string $errorMessage = null,
    ): SitePublicationRun {
        return DB::connection('central')->transaction(function () use ($publication, $userId, $preflight, $startedAt, $status, $steps, $extraOptions, $errorMessage): SitePublicationRun {
            $run = $publication->runs()->create([
                'direction' => 'push',
                'status' => $status,
                'started_at' => $startedAt,
                'finished_at' => now(),
                'steps' => $steps,
                'options' => array_merge([
                    'mode' => 'apply_env_vars',
                    'source_preflight_run_id' => $preflight->id,
                ], $extraOptions),
                'error_message' => $errorMessage,
                'created_by' => $userId,
            ]);

            $publication->forceFill([
                'status' => $status === 'completed' ? 'ready' : 'failed',
                'metadata' => array_merge($publication->metadata ?? [], [
                    'last_env_var_run_id' => $run->id,
                    'last_env_var_status' => $run->status,
                    'last_env_var_at' => $run->finished_at?->toIso8601String(),
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
                'steps' => [
                    $this->step('preflight', 'failed', $message),
                ],
                'options' => [
                    'mode' => 'apply_env_vars',
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

    /**
     * @return array{key: string, label: string, status: string, message: string}
     */
    private function step(string $key, string $status, string $message): array
    {
        return [
            'key' => $key,
            'label' => __('admin_common_ui.platform.publications.env_vars.steps.'.$key),
            'status' => $status,
            'message' => $message,
        ];
    }
}
