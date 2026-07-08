<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\ApplySitePublicationDomainAction;
use App\Actions\Platform\ApplySitePublicationEnvironmentVariablesAction;
use App\Actions\Platform\PrepareSitePublicationRunAction;
use App\Actions\Platform\ProvisionSitePublicationDatabaseAction;
use App\Actions\Platform\RunSitePublicationRemoteSetupAction;
use App\Actions\Platform\StartSitePublicationDeploymentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreSitePublicationRequest;
use App\Models\Platform\HostingEnvironment;
use App\Models\Platform\Site;
use App\Models\Platform\SitePublication;
use App\Models\Platform\SitePublicationRun;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SitePublicationController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(): Response
    {
        return Inertia::render('Platform/Publications/Index', [
            'publications' => SitePublication::query()
                ->with([
                    'site:id,name,slug,status',
                    'hostingEnvironment.connection:id,name,provider',
                    'latestRun',
                ])
                ->orderByDesc('updated_at')
                ->get()
                ->map(fn (SitePublication $publication): array => $this->publicationPayload($publication))
                ->values(),
        ]);
    }

    public function edit(int $id): Response
    {
        $publication = $id > 0
            ? SitePublication::query()
                ->with([
                    'site:id,name,slug,status',
                    'hostingEnvironment.connection:id,name,provider',
                    'latestRun',
                ])
                ->findOrFail($id)
            : null;

        return Inertia::render('Platform/Publications/Edit', [
            'publication' => $publication instanceof SitePublication ? $this->publicationPayload($publication) : null,
            'siteOptions' => Site::query()
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'tenant_database_mode', 'tenant_database', 'tenant_table_prefix'])
                ->map(fn (Site $site): array => [
                    'value' => $site->id,
                    'label' => $site->name.' ('.$site->slug.')',
                    'slug' => $site->slug,
                    'tenant_database_mode' => $site->tenant_database_mode,
                    'tenant_database' => $site->tenant_database,
                    'tenant_table_prefix' => $site->tenant_table_prefix,
                ])
                ->values(),
            'environmentOptions' => HostingEnvironment::query()
                ->with('connection:id,name,provider')
                ->orderBy('name')
                ->get()
                ->map(fn (HostingEnvironment $environment): array => [
                    'value' => $environment->id,
                    'label' => $this->environmentLabel($environment),
                    'default_tenant_database_mode' => $environment->default_tenant_database_mode,
                    'default_database_name' => $environment->default_database_name,
                    'provider_region' => $environment->provider_region,
                ])
                ->values(),
            'databaseModeOptions' => [
                ['value' => 'shared_prefixed', 'label' => __('admin_common_ui.platform.publications.database_modes.shared_prefixed')],
                ['value' => 'separate', 'label' => __('admin_common_ui.platform.publications.database_modes.separate')],
                ['value' => 'existing_database', 'label' => __('admin_common_ui.platform.publications.database_modes.existing_database')],
            ],
        ]);
    }

    public function store(StoreSitePublicationRequest $request, int $id): RedirectResponse
    {
        $validated = $request->validated();

        $publication = DB::connection('central')->transaction(function () use ($id, $validated): SitePublication {
            $publication = $id > 0
                ? SitePublication::query()->findOrFail($id)
                : new SitePublication;

            $publication->fill([
                'site_id' => $validated['site_id'],
                'hosting_environment_id' => $validated['hosting_environment_id'],
                'remote_site_slug' => $validated['remote_site_slug'],
                'remote_domain' => $validated['remote_domain'] ?? null,
                'remote_tenant_database_mode' => $validated['remote_tenant_database_mode'],
                'remote_tenant_database' => $validated['remote_tenant_database'] ?: null,
                'remote_tenant_table_prefix' => $validated['remote_tenant_table_prefix'] ?: null,
                'status' => $publication->exists ? $publication->status : 'draft',
            ]);

            $publication->save();

            return $publication;
        });

        $this->auditLogger->success(
            action: $id > 0 ? 'platform.site_publication.update' : 'platform.site_publication.create',
            module: 'platform',
            subjectType: 'site_publication',
            subjectKey: (string) $publication->id,
            message: __('admin_common_ui.platform.publications.flash.saved'),
            meta: [
                'site_id' => $publication->site_id,
                'hosting_environment_id' => $publication->hosting_environment_id,
                'remote_site_slug' => $publication->remote_site_slug,
                'remote_domain' => $publication->remote_domain,
                'remote_tenant_database_mode' => $publication->remote_tenant_database_mode,
            ],
            request: $request,
        );

        return redirect()
            ->route('platform.publications.edit', ['id' => $publication->id])
            ->with('status', __('admin_common_ui.platform.publications.flash.saved'));
    }

    public function preparePublish(SitePublication $publication, PrepareSitePublicationRunAction $prepareSitePublicationRun): RedirectResponse
    {
        $result = $prepareSitePublicationRun->handle($publication, request()->user()?->id);
        $message = $result['failed']
            ? __('admin_common_ui.platform.publications.flash.preflight_failed')
            : __('admin_common_ui.platform.publications.flash.preflight_completed');

        $this->auditLogger->log(
            action: 'platform.site_publication.prepare_publish',
            module: 'platform',
            subjectType: 'site_publication',
            subjectKey: (string) $publication->id,
            success: ! $result['failed'],
            severity: $result['failed'] ? 'warning' : 'info',
            message: $message,
            meta: [
                'run_id' => $result['run']->id,
                'run_status' => $result['run']->status,
                'site_id' => $publication->site_id,
                'hosting_environment_id' => $publication->hosting_environment_id,
            ],
            request: request(),
        );

        return back()->with($result['failed'] ? 'warning' : 'status', $message);
    }

    public function applyEnvVars(SitePublication $publication, ApplySitePublicationEnvironmentVariablesAction $applyEnvironmentVariables): RedirectResponse
    {
        $result = $applyEnvironmentVariables->handle($publication, request()->user()?->id);
        $message = $result['failed']
            ? __('admin_common_ui.platform.publications.flash.env_vars_failed')
            : __('admin_common_ui.platform.publications.flash.env_vars_applied');

        $this->auditLogger->log(
            action: 'platform.site_publication.apply_env_vars',
            module: 'platform',
            subjectType: 'site_publication',
            subjectKey: (string) $publication->id,
            success: ! $result['failed'],
            severity: $result['failed'] ? 'error' : 'info',
            message: $message,
            meta: [
                'run_id' => $result['run']->id,
                'run_status' => $result['run']->status,
                'applied_env_var_keys' => $result['run']->options['applied_env_var_keys'] ?? [],
            ],
            request: request(),
        );

        return back()->with($result['failed'] ? 'error' : 'status', $message);
    }

    public function provisionDatabase(SitePublication $publication, ProvisionSitePublicationDatabaseAction $provisionDatabase): RedirectResponse
    {
        $result = $provisionDatabase->handle($publication, request()->user()?->id);
        $message = $result['failed']
            ? __('admin_common_ui.platform.publications.flash.database_failed')
            : __('admin_common_ui.platform.publications.flash.database_ready');

        $this->auditLogger->log(
            action: 'platform.site_publication.provision_database',
            module: 'platform',
            subjectType: 'site_publication',
            subjectKey: (string) $publication->id,
            success: ! $result['failed'],
            severity: $result['failed'] ? 'error' : 'info',
            message: $message,
            meta: [
                'run_id' => $result['run']->id,
                'run_status' => $result['run']->status,
                'database_id' => $result['run']->options['database_id'] ?? null,
                'database_status' => $result['run']->options['status'] ?? null,
            ],
            request: request(),
        );

        return back()->with($result['failed'] ? 'error' : 'status', $message);
    }

    public function createDatabase(SitePublication $publication, ProvisionSitePublicationDatabaseAction $provisionDatabase): RedirectResponse
    {
        $result = $provisionDatabase->handle($publication, request()->user()?->id, true);
        $message = $result['failed']
            ? __('admin_common_ui.platform.publications.flash.database_create_failed')
            : __('admin_common_ui.platform.publications.flash.database_create_started');

        $this->auditLogger->log(
            action: 'platform.site_publication.create_database',
            module: 'platform',
            subjectType: 'site_publication',
            subjectKey: (string) $publication->id,
            success: ! $result['failed'],
            severity: $result['failed'] ? 'error' : 'warning',
            message: $message,
            meta: [
                'run_id' => $result['run']->id,
                'run_status' => $result['run']->status,
                'database_id' => $result['run']->options['database_id'] ?? null,
                'database_status' => $result['run']->options['status'] ?? null,
                'requires_attach' => $result['run']->options['requires_attach'] ?? false,
            ],
            request: request(),
        );

        return back()->with($result['failed'] ? 'error' : 'warning', $message);
    }

    public function applyDomain(SitePublication $publication, ApplySitePublicationDomainAction $applyDomain): RedirectResponse
    {
        $result = $applyDomain->handle($publication, request()->user()?->id);
        $message = $result['failed']
            ? __('admin_common_ui.platform.publications.flash.domain_failed')
            : __('admin_common_ui.platform.publications.flash.domain_applied');

        $this->auditLogger->log(
            action: 'platform.site_publication.apply_domain',
            module: 'platform',
            subjectType: 'site_publication',
            subjectKey: (string) $publication->id,
            success: ! $result['failed'],
            severity: $result['failed'] ? 'error' : 'info',
            message: $message,
            meta: [
                'run_id' => $result['run']->id,
                'run_status' => $result['run']->status,
                'domain' => $result['run']->options['domain'] ?? null,
                'domain_action' => $result['run']->options['domain_action'] ?? null,
                'verification' => $result['run']->options['verification'] ?? null,
            ],
            request: request(),
        );

        return back()->with($result['failed'] ? 'error' : 'status', $message);
    }

    public function startDeployment(SitePublication $publication, StartSitePublicationDeploymentAction $startDeployment): RedirectResponse
    {
        $result = $startDeployment->handle($publication, request()->user()?->id);
        $message = $result['failed']
            ? __('admin_common_ui.platform.publications.flash.deployment_failed')
            : __('admin_common_ui.platform.publications.flash.deployment_started');

        $this->auditLogger->log(
            action: 'platform.site_publication.start_deployment',
            module: 'platform',
            subjectType: 'site_publication',
            subjectKey: (string) $publication->id,
            success: ! $result['failed'],
            severity: $result['failed'] ? 'error' : 'info',
            message: $message,
            meta: [
                'run_id' => $result['run']->id,
                'run_status' => $result['run']->status,
                'deployment_id' => $result['run']->options['deployment_id'] ?? null,
                'deployment_status' => $result['run']->options['deployment_status'] ?? null,
            ],
            request: request(),
        );

        return back()->with($result['failed'] ? 'error' : 'status', $message);
    }

    public function runRemoteSetup(SitePublication $publication, RunSitePublicationRemoteSetupAction $runRemoteSetup): RedirectResponse
    {
        $result = $runRemoteSetup->handle($publication, request()->user()?->id);
        $message = $result['failed']
            ? __('admin_common_ui.platform.publications.flash.remote_setup_failed')
            : __('admin_common_ui.platform.publications.flash.remote_setup_completed');

        $this->auditLogger->log(
            action: 'platform.site_publication.remote_setup',
            module: 'platform',
            subjectType: 'site_publication',
            subjectKey: (string) $publication->id,
            success: ! $result['failed'],
            severity: $result['failed'] ? 'error' : 'info',
            message: $message,
            meta: [
                'run_id' => $result['run']->id,
                'run_status' => $result['run']->status,
                'commands' => collect($result['run']->options['commands'] ?? [])->pluck('key')->all(),
            ],
            request: request(),
        );

        return back()->with($result['failed'] ? 'error' : 'status', $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function publicationPayload(SitePublication $publication): array
    {
        $environment = $publication->hostingEnvironment;
        $latestRun = $publication->latestRun;
        $latestPreflightRun = $publication->runs()
            ->where('status', 'completed')
            ->where('options->mode', 'preflight')
            ->latest('id')
            ->first();

        return [
            'id' => $publication->id,
            'site_id' => $publication->site_id,
            'site' => $publication->site,
            'hosting_environment_id' => $publication->hosting_environment_id,
            'hosting_environment' => $environment,
            'hosting_connection' => $environment?->connection,
            'remote_site_slug' => $publication->remote_site_slug,
            'remote_domain' => $publication->remote_domain,
            'remote_tenant_database_mode' => $publication->remote_tenant_database_mode,
            'remote_tenant_database' => $publication->remote_tenant_database,
            'remote_tenant_table_prefix' => $publication->remote_tenant_table_prefix,
            'remote_site_id' => $publication->remote_site_id,
            'status' => $publication->status,
            'last_push_at' => $publication->last_push_at?->toIso8601String(),
            'last_pull_at' => $publication->last_pull_at?->toIso8601String(),
            'latest_run' => $latestRun instanceof SitePublicationRun ? [
                'id' => $latestRun->id,
                'direction' => $latestRun->direction,
                'status' => $latestRun->status,
                'mode' => $latestRun->options['mode'] ?? null,
                'started_at' => $latestRun->started_at?->toIso8601String(),
                'finished_at' => $latestRun->finished_at?->toIso8601String(),
                'steps' => $latestRun->steps ?? [],
                'options' => $latestRun->options ?? [],
                'error_message' => $latestRun->error_message,
            ] : null,
            'latest_preflight_run' => $latestPreflightRun instanceof SitePublicationRun ? [
                'id' => $latestPreflightRun->id,
                'status' => $latestPreflightRun->status,
                'finished_at' => $latestPreflightRun->finished_at?->toIso8601String(),
                'options' => $latestPreflightRun->options ?? [],
            ] : null,
            'metadata' => $publication->metadata ?? [],
            'created_at' => $publication->created_at?->toIso8601String(),
            'updated_at' => $publication->updated_at?->toIso8601String(),
        ];
    }

    private function environmentLabel(HostingEnvironment $environment): string
    {
        $connection = $environment->connection;
        $connectionName = $connection?->name ?: __('admin_common_ui.platform.hosting.title');

        return $connectionName.' / '.$environment->name;
    }
}
