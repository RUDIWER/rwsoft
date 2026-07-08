<?php

namespace App\Actions\Platform;

use App\Actions\Admin\Cms\SitePackages\BuildCmsSitePackageZipAction;
use App\Models\Platform\Site;
use App\Models\Platform\SitePublication;
use App\Models\Platform\SitePublicationRun;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Throwable;

class PrepareSitePublicationRunAction
{
    public function __construct(
        private readonly ConfigureTenantDatabaseAction $configureTenantDatabase,
        private readonly BuildCmsSitePackageZipAction $buildCmsSitePackageZip,
    ) {}

    /**
     * @return array{run: SitePublicationRun, failed: bool}
     */
    public function handle(SitePublication $publication, ?int $userId = null): array
    {
        $publication->loadMissing(['site', 'hostingEnvironment.connection']);
        $startedAt = now();
        $artifact = null;
        $steps = $this->steps($publication, $artifact);
        $failed = collect($steps)->contains(fn (array $step): bool => $step['status'] === 'failed');

        $run = DB::connection('central')->transaction(function () use ($publication, $userId, $startedAt, $steps, $failed, $artifact): SitePublicationRun {
            $run = $publication->runs()->create([
                'direction' => 'push',
                'status' => $failed ? 'failed' : 'completed',
                'started_at' => $startedAt,
                'finished_at' => now(),
                'steps' => $steps,
                'options' => [
                    'mode' => 'preflight',
                    'remote_tenant_database_mode' => $publication->remote_tenant_database_mode,
                    'remote_domain' => $publication->remote_domain,
                    'site_package_path' => $artifact['path'] ?? null,
                    'site_package_filename' => $artifact['filename'] ?? null,
                    'site_package_key' => $artifact['key'] ?? null,
                    'provider_plan' => $failed ? null : $this->providerPlan($publication, $artifact),
                ],
                'error_message' => $failed ? __('admin_common_ui.platform.publications.preflight.failed') : null,
                'created_by' => $userId,
            ]);

            $publication->forceFill([
                'status' => $failed ? 'failed' : 'ready',
                'metadata' => array_merge($publication->metadata ?? [], [
                    'last_preflight_run_id' => $run->id,
                    'last_preflight_status' => $run->status,
                    'last_preflight_at' => $run->finished_at?->toIso8601String(),
                ]),
            ])->save();

            return $run;
        });

        return ['run' => $run, 'failed' => $failed];
    }

    /**
     * @return array<int, array{key: string, label: string, status: string, message: string}>
     */
    private function steps(SitePublication $publication, ?array &$artifact): array
    {
        $steps = [
            $this->localSiteStep($publication),
            $this->hostingEnvironmentStep($publication),
            $this->remoteIdentityStep($publication),
            $this->remoteDatabaseStep($publication),
        ];

        if (collect($steps)->contains(fn (array $step): bool => $step['status'] === 'failed')) {
            $steps[] = $this->step('site_package', 'warning', __('admin_common_ui.platform.publications.preflight.site_package_skipped'));
        } else {
            $steps[] = $this->sitePackageStep($publication, $artifact);
        }

        $steps[] = $this->remotePlanStep($publication);

        return $steps;
    }

    /**
     * @return array{key: string, label: string, status: string, message: string}
     */
    private function localSiteStep(SitePublication $publication): array
    {
        $site = $publication->site;

        if (! $site instanceof Site) {
            return $this->step('local_site', 'failed', __('admin_common_ui.platform.publications.preflight.local_site_missing'));
        }

        if ($site->provisioned_at === null) {
            return $this->step('local_site', 'failed', __('admin_common_ui.platform.publications.preflight.local_site_not_provisioned'));
        }

        return $this->step('local_site', 'passed', __('admin_common_ui.platform.publications.preflight.local_site_ready'));
    }

    /**
     * @return array{key: string, label: string, status: string, message: string}
     */
    private function hostingEnvironmentStep(SitePublication $publication): array
    {
        $environment = $publication->hostingEnvironment;

        if ($environment === null) {
            return $this->step('hosting_environment', 'failed', __('admin_common_ui.platform.publications.preflight.environment_missing'));
        }

        if ($environment->last_synced_at === null) {
            return $this->step('hosting_environment', 'failed', __('admin_common_ui.platform.publications.preflight.environment_not_synced'));
        }

        return $this->step('hosting_environment', 'passed', __('admin_common_ui.platform.publications.preflight.environment_ready'));
    }

    /**
     * @return array{key: string, label: string, status: string, message: string}
     */
    private function remoteIdentityStep(SitePublication $publication): array
    {
        if ($publication->remote_site_slug === '') {
            return $this->step('remote_identity', 'failed', __('admin_common_ui.platform.publications.preflight.remote_slug_missing'));
        }

        return $this->step('remote_identity', 'passed', __('admin_common_ui.platform.publications.preflight.remote_identity_ready'));
    }

    /**
     * @return array{key: string, label: string, status: string, message: string}
     */
    private function remoteDatabaseStep(SitePublication $publication): array
    {
        $mode = (string) $publication->remote_tenant_database_mode;

        if (in_array($mode, ['separate', 'existing_database'], true) && blank($publication->remote_tenant_database)) {
            return $this->step('remote_database', 'failed', __('admin_common_ui.platform.publications.preflight.remote_database_missing'));
        }

        if ($mode === 'shared_prefixed' && blank($publication->remote_tenant_table_prefix)) {
            return $this->step('remote_database', 'failed', __('admin_common_ui.platform.publications.preflight.remote_prefix_missing'));
        }

        return $this->step('remote_database', 'passed', __('admin_common_ui.platform.publications.preflight.remote_database_ready'));
    }

    /**
     * @return array{key: string, label: string, status: string, message: string}
     */
    private function sitePackageStep(SitePublication $publication, ?array &$artifact): array
    {
        if (! class_exists(BuildCmsSitePackageZipAction::class)) {
            return $this->step('site_package', 'failed', __('admin_common_ui.platform.publications.preflight.site_package_unavailable'));
        }

        $site = $publication->site;

        if (! $site instanceof Site) {
            return $this->step('site_package', 'failed', __('admin_common_ui.platform.publications.preflight.local_site_missing'));
        }

        try {
            $this->configureTenantDatabase->handle($site);

            $artifact = $this->buildCmsSitePackageZip->handle([
                'package_key' => 'publish-'.$publication->remote_site_slug,
                'package_name' => $site->name,
                'modules' => (array) config('cms_site_packages.implemented_modules', []),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return $this->step('site_package', 'failed', __('admin_common_ui.platform.publications.preflight.site_package_failed', [
                'message' => $exception->getMessage(),
            ]));
        } finally {
            TenantContext::clear();
            DB::purge('tenant');
        }

        return $this->step('site_package', 'passed', __('admin_common_ui.platform.publications.preflight.site_package_created', [
            'filename' => $artifact['filename'],
        ]));
    }

    /**
     * @return array{key: string, label: string, status: string, message: string}
     */
    private function remotePlanStep(SitePublication $publication): array
    {
        $requiredKeys = ['APP_URL', 'TENANT_DATABASE_MODE'];

        if ($publication->remote_tenant_database_mode === 'shared_prefixed') {
            $requiredKeys[] = 'TENANT_TABLE_PREFIX';
        } else {
            $requiredKeys[] = 'TENANT_DATABASE';
        }

        return $this->step('remote_plan', 'passed', __('admin_common_ui.platform.publications.preflight.remote_plan_ready', [
            'keys' => implode(', ', $requiredKeys),
        ]));
    }

    /**
     * @param  array{path?: string, filename?: string, key?: string}|null  $artifact
     * @return array<string, mixed>
     */
    private function providerPlan(SitePublication $publication, ?array $artifact): array
    {
        $environment = $publication->hostingEnvironment;
        $connection = $environment?->connection;

        return [
            'provider' => $connection?->provider,
            'hosting_connection_id' => $connection?->id,
            'hosting_environment_id' => $environment?->id,
            'provider_application_id' => $environment?->provider_application_id,
            'provider_environment_id' => $environment?->provider_environment_id,
            'env_vars' => $this->plannedEnvironmentVariables($publication),
            'domains' => $this->plannedDomains($publication),
            'commands' => $this->plannedCommands($publication, $artifact),
        ];
    }

    /**
     * @return array<int, array{key: string, value: string|null, action: string}>
     */
    private function plannedEnvironmentVariables(SitePublication $publication): array
    {
        $variables = [
            ['key' => 'APP_URL', 'value' => $this->remoteAppUrl($publication), 'action' => 'set'],
            ['key' => 'TENANT_DATABASE_MODE', 'value' => $publication->remote_tenant_database_mode, 'action' => 'set'],
            ['key' => 'RWSOFT_REMOTE_SITE_SLUG', 'value' => $publication->remote_site_slug, 'action' => 'set'],
        ];

        if ($publication->remote_tenant_database_mode === 'shared_prefixed') {
            $variables[] = ['key' => 'TENANT_DATABASE', 'value' => $publication->remote_tenant_database, 'action' => 'set'];
            $variables[] = ['key' => 'TENANT_TABLE_PREFIX', 'value' => $publication->remote_tenant_table_prefix, 'action' => 'set'];

            return $variables;
        }

        $variables[] = ['key' => 'TENANT_DATABASE', 'value' => $publication->remote_tenant_database, 'action' => 'set'];

        return $variables;
    }

    /**
     * @return array<int, array{domain: string, action: string, verify: bool}>
     */
    private function plannedDomains(SitePublication $publication): array
    {
        if (blank($publication->remote_domain)) {
            return [];
        }

        if ($this->isProviderVanityDomain($publication)) {
            return [];
        }

        return [[
            'domain' => (string) $publication->remote_domain,
            'action' => 'ensure_and_verify',
            'verify' => true,
        ]];
    }

    /**
     * @param  array{path?: string, filename?: string, key?: string}|null  $artifact
     * @return array<int, array{key: string, command: string, action: string}>
     */
    private function plannedCommands(SitePublication $publication, ?array $artifact): array
    {
        $remoteSlug = escapeshellarg($publication->remote_site_slug);
        $packageFilename = escapeshellarg((string) ($artifact['filename'] ?? 'site-package.zip'));

        return [
            [
                'key' => 'bootstrap_site',
                'action' => 'run_remote_command',
                'command' => 'php artisan rwsoft:remote-site-bootstrap --site='.$remoteSlug,
            ],
            [
                'key' => 'import_site_package',
                'action' => 'run_remote_command',
                'command' => 'php artisan cms:site-package-import --site='.$remoteSlug.' --file='.$packageFilename,
            ],
            [
                'key' => 'clear_cache',
                'action' => 'run_remote_command',
                'command' => 'php artisan optimize:clear',
            ],
        ];
    }

    private function remoteAppUrl(SitePublication $publication): ?string
    {
        if (blank($publication->remote_domain)) {
            return null;
        }

        return 'https://'.$publication->remote_domain;
    }

    private function isProviderVanityDomain(SitePublication $publication): bool
    {
        $vanityDomain = (string) data_get($publication->hostingEnvironment?->metadata, 'environment.vanity_domain', '');

        return $vanityDomain !== '' && strcasecmp($vanityDomain, (string) $publication->remote_domain) === 0;
    }

    /**
     * @return array{key: string, label: string, status: string, message: string}
     */
    private function step(string $key, string $status, string $message): array
    {
        return [
            'key' => $key,
            'label' => __('admin_common_ui.platform.publications.preflight.steps.'.$key),
            'status' => $status,
            'message' => $message,
        ];
    }
}
