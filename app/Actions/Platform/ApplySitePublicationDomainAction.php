<?php

namespace App\Actions\Platform;

use App\Models\Platform\SitePublication;
use App\Models\Platform\SitePublicationRun;
use App\Support\Hosting\HostingProviderManager;
use Illuminate\Support\Facades\DB;
use Throwable;

class ApplySitePublicationDomainAction
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
            return $this->failedRun($publication, $userId, __('admin_common_ui.platform.publications.domains.no_preflight'));
        }

        $providerPlan = is_array($preflight->options['provider_plan'] ?? null) ? $preflight->options['provider_plan'] : [];
        $domains = is_array($providerPlan['domains'] ?? null) ? $providerPlan['domains'] : [];
        $applicationId = (string) ($providerPlan['provider_application_id'] ?? '');
        $environmentId = (string) ($providerPlan['provider_environment_id'] ?? '');

        if ($domains === [] || $applicationId === '' || $environmentId === '') {
            return $this->failedRun($publication, $userId, __('admin_common_ui.platform.publications.domains.invalid_preflight'), $preflight);
        }

        $domain = (string) ($domains[0]['domain'] ?? '');

        if ($domain === '') {
            return $this->failedRun($publication, $userId, __('admin_common_ui.platform.publications.domains.invalid_preflight'), $preflight);
        }

        $startedAt = now();
        $steps = [
            $this->step('preflight', 'passed', __('admin_common_ui.platform.publications.domains.preflight_ready', ['id' => $preflight->id])),
            $this->step('domain', 'running', __('admin_common_ui.platform.publications.domains.applying', ['domain' => $domain])),
        ];

        try {
            $connection = $publication->hostingEnvironment?->connection;

            if ($connection === null) {
                throw new \RuntimeException(__('admin_common_ui.platform.publications.domains.connection_missing'));
            }

            $result = $this->providers
                ->providerFor($connection)
                ->ensureDomain($connection, $applicationId, $environmentId, $domain);

            $steps[1] = $this->step('domain', 'passed', __('admin_common_ui.platform.publications.domains.applied', [
                'domain' => $result['domain'],
                'action' => $result['action'],
            ]));
            $steps[] = $this->step('verification', 'passed', __('admin_common_ui.platform.publications.domains.verification', [
                'status' => $result['verification'],
            ]));

            $run = $this->storeRun($publication, $userId, $preflight, $startedAt, 'completed', $steps, [
                'domain' => $result['domain'],
                'domain_action' => $result['action'],
                'domain_id' => $result['domain_id'],
                'verification' => $result['verification'],
            ]);

            return ['run' => $run, 'failed' => false];
        } catch (Throwable $exception) {
            report($exception);

            $steps[1] = $this->step('domain', 'failed', __('admin_common_ui.platform.publications.domains.apply_failed', [
                'message' => $exception->getMessage(),
            ]));

            $run = $this->storeRun($publication, $userId, $preflight, $startedAt, 'failed', $steps, [
                'domain' => $domain,
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
                    'mode' => 'apply_domain',
                    'source_preflight_run_id' => $preflight->id,
                ], $extraOptions),
                'error_message' => $errorMessage,
                'created_by' => $userId,
            ]);

            $publication->forceFill([
                'status' => $status === 'completed' ? 'ready' : 'failed',
                'metadata' => array_merge($publication->metadata ?? [], [
                    'last_domain_run_id' => $run->id,
                    'last_domain_status' => $run->status,
                    'last_domain_at' => $run->finished_at?->toIso8601String(),
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
                'steps' => [$this->step('preflight', 'failed', $message)],
                'options' => [
                    'mode' => 'apply_domain',
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
            'label' => __('admin_common_ui.platform.publications.domains.steps.'.$key),
            'status' => $status,
            'message' => $message,
        ];
    }
}
