<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\SyncHostingEnvironmentsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreHostingConnectionRequest;
use App\Models\Platform\HostingConnection;
use App\Models\Platform\HostingEnvironment;
use App\Support\Audit\AuditLogger;
use App\Support\Hosting\HostingProviderManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class HostingConnectionController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(): Response
    {
        return Inertia::render('Platform/Hosting/Index', [
            'connections' => HostingConnection::query()
                ->withCount('environments')
                ->orderBy('name')
                ->get()
                ->map(fn (HostingConnection $connection): array => $this->connectionPayload($connection))
                ->values(),
        ]);
    }

    public function edit(int $id): Response
    {
        $connection = $id > 0
            ? HostingConnection::query()
                ->with(['environments' => fn ($query) => $query->orderBy('name')])
                ->withCount('environments')
                ->findOrFail($id)
            : null;

        return Inertia::render('Platform/Hosting/Edit', [
            'connection' => $connection instanceof HostingConnection ? $this->connectionPayload($connection) : null,
            'providerOptions' => [
                ['value' => 'laravel_cloud', 'label' => __('admin_common_ui.platform.hosting.providers.laravel_cloud')],
            ],
        ]);
    }

    public function store(StoreHostingConnectionRequest $request, int $id): RedirectResponse
    {
        $validated = $request->validated();

        $connection = DB::connection('central')->transaction(function () use ($id, $request, $validated): HostingConnection {
            $connection = $id > 0
                ? HostingConnection::query()->findOrFail($id)
                : new HostingConnection;

            $connection->fill([
                'name' => $validated['name'],
                'provider' => $validated['provider'],
                'api_base_url' => $validated['api_base_url'] ?? null,
                'status' => 'not_tested',
                'last_checked_at' => null,
                'last_error' => null,
            ]);

            if (filled($validated['api_token'] ?? null)) {
                $connection->api_token = $validated['api_token'];
            }

            if (! $connection->exists) {
                $connection->created_by = $request->user()?->id;
            }

            $connection->save();

            return $connection;
        });

        $this->auditLogger->success(
            action: $id > 0 ? 'platform.hosting_connection.update' : 'platform.hosting_connection.create',
            module: 'platform',
            subjectType: 'hosting_connection',
            subjectKey: (string) $connection->id,
            message: __('admin_common_ui.platform.hosting.flash.saved'),
            meta: $this->auditMeta($connection),
            request: $request,
        );

        return redirect()
            ->route('platform.hosting.edit', ['id' => $connection->id])
            ->with('status', __('admin_common_ui.platform.hosting.flash.saved'));
    }

    public function test(HostingConnection $connection, HostingProviderManager $providers): RedirectResponse
    {
        $result = $providers->providerFor($connection)->testConnection($connection);

        $connection->forceFill([
            'status' => $result['ok'] ? 'ready' : 'failed',
            'last_checked_at' => now(),
            'last_error' => $result['ok'] ? null : mb_substr((string) ($result['error'] ?? $result['message']), 0, 2000),
            'metadata' => array_merge($connection->metadata ?? [], [
                'last_applications_count' => $result['applications_count'] ?? null,
            ]),
        ])->save();

        $this->auditLogger->log(
            action: 'platform.hosting_connection.test',
            module: 'platform',
            subjectType: 'hosting_connection',
            subjectKey: (string) $connection->id,
            success: (bool) $result['ok'],
            severity: $result['ok'] ? 'info' : 'error',
            message: (string) $result['message'],
            meta: $this->auditMeta($connection),
            request: request(),
        );

        return back()->with($result['ok'] ? 'status' : 'error', $result['message']);
    }

    public function syncEnvironments(HostingConnection $connection, SyncHostingEnvironmentsAction $syncHostingEnvironments): RedirectResponse
    {
        try {
            $result = $syncHostingEnvironments->handle($connection);

            $message = __('admin_common_ui.platform.hosting.flash.environments_synced', [
                'applications' => $result['applications'],
                'environments' => $result['environments'],
            ]);

            $this->auditLogger->success(
                action: 'platform.hosting_connection.environments.sync',
                module: 'platform',
                subjectType: 'hosting_connection',
                subjectKey: (string) $connection->id,
                message: $message,
                meta: array_merge($this->auditMeta($connection), $result),
                request: request(),
            );

            return back()->with('status', $message);
        } catch (Throwable $exception) {
            report($exception);

            $connection->forceFill([
                'status' => 'failed',
                'last_checked_at' => now(),
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();

            $this->auditLogger->failure(
                action: 'platform.hosting_connection.environments.sync',
                module: 'platform',
                subjectType: 'hosting_connection',
                subjectKey: (string) $connection->id,
                message: __('admin_common_ui.platform.hosting.flash.environments_sync_failed'),
                meta: $this->auditMeta($connection),
                request: request(),
            );

            return back()->with('error', __('admin_common_ui.platform.hosting.flash.environments_sync_failed'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function connectionPayload(HostingConnection $connection): array
    {
        return [
            'id' => $connection->id,
            'name' => $connection->name,
            'provider' => $connection->provider,
            'api_base_url' => $connection->api_base_url,
            'status' => $connection->status,
            'last_checked_at' => $connection->last_checked_at?->toIso8601String(),
            'last_error' => $connection->last_error,
            'has_api_token' => $connection->hasApiToken(),
            'environments_count' => (int) ($connection->environments_count ?? 0),
            'environments' => $connection->relationLoaded('environments')
                ? $connection->environments->map(fn (HostingEnvironment $environment): array => $this->environmentPayload($environment))->values()
                : [],
            'metadata' => $connection->metadata ?? [],
            'created_at' => $connection->created_at?->toIso8601String(),
            'updated_at' => $connection->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function environmentPayload(HostingEnvironment $environment): array
    {
        return [
            'id' => $environment->id,
            'name' => $environment->name,
            'provider_application_id' => $environment->provider_application_id,
            'provider_environment_id' => $environment->provider_environment_id,
            'provider_region' => $environment->provider_region,
            'default_tenant_database_mode' => $environment->default_tenant_database_mode,
            'default_database_name' => $environment->default_database_name,
            'default_storage_mode' => $environment->default_storage_mode,
            'status' => $environment->status,
            'last_synced_at' => $environment->last_synced_at?->toIso8601String(),
            'metadata' => $environment->metadata ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function auditMeta(HostingConnection $connection): array
    {
        return [
            'provider' => $connection->provider,
            'status' => $connection->status,
            'api_base_url' => $connection->api_base_url,
            'has_api_token' => $connection->hasApiToken(),
        ];
    }
}
