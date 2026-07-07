<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreSiteRequest;
use App\Models\Platform\Site;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SiteController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(): Response
    {
        return Inertia::render('Platform/Sites/Index', [
            'sites' => Site::query()
                ->with(['primaryDomain:id,site_id,host', 'domains:id,site_id,host,is_primary'])
                ->withCount('memberships')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'tenant_database', 'tenant_table_prefix', 'tenant_database_mode', 'tenant_provisioning_mode', 'status', 'provisioned_at', 'created_at']),
        ]);
    }

    public function edit(int $id): Response
    {
        $site = $id > 0
            ? Site::query()
                ->with([
                    'domains' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('host'),
                    'memberships.user:id,name,email',
                ])
                ->findOrFail($id)
            : null;

        return Inertia::render('Platform/Sites/Edit', [
            'site' => $site,
        ]);
    }

    public function store(StoreSiteRequest $request, int $id): RedirectResponse
    {
        $validated = $request->validated();

        $site = DB::connection('central')->transaction(function () use ($id, $request, $validated): Site {
            $site = $id > 0
                ? Site::query()->findOrFail($id)
                : new Site;
            $isCreate = ! $site->exists;

            $site->fill([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
            ]);

            if ($isCreate) {
                $storageOption = $this->tenantStorageOption($validated);

                $site->tenant_database_mode = $storageOption === 'shared_prefixed' ? 'shared_prefixed' : 'separate';
                $site->tenant_provisioning_mode = $storageOption;
                $site->tenant_database = $this->tenantDatabaseNameForStorageOption($storageOption, $validated);
                $site->tenant_table_prefix = $storageOption === 'shared_prefixed'
                    ? ($validated['tenant_table_prefix'] ?: $this->tenantTablePrefix($validated['slug']))
                    : null;
                $site->status = 'draft';
                $site->created_by = $request->user()?->id;
            }

            $site->save();

            if ($isCreate && filled($validated['primary_domain'] ?? null)) {
                $site->domains()->create([
                    'host' => $validated['primary_domain'],
                    'is_primary' => true,
                    'force_https' => true,
                ]);
            }

            if ($isCreate && filled($validated['first_admin_email'] ?? null)) {
                $user = User::query()
                    ->where('email', $validated['first_admin_email'])
                    ->first();

                if ($user instanceof User) {
                    $site->memberships()->updateOrCreate(
                        ['user_id' => $user->id],
                        ['is_active' => true]
                    );
                }
            }

            return $site;
        });

        $this->auditLogger->success(
            action: $id > 0 ? 'platform.site.update' : 'platform.site.create',
            module: 'platform',
            subjectType: 'site',
            subjectKey: (string) $site->id,
            message: 'Site succesvol bewaard.',
            meta: [
                'name' => $site->name,
                'slug' => $site->slug,
                'tenant_database' => $site->tenant_database,
                'tenant_database_mode' => $site->tenant_database_mode,
                'tenant_provisioning_mode' => $site->tenant_provisioning_mode,
                'tenant_table_prefix' => $site->tenant_table_prefix,
            ],
            request: $request,
        );

        return redirect()
            ->route('platform.sites.edit', ['id' => $site->id])
            ->with('status', 'Site succesvol bewaard.');
    }

    private function tenantDatabaseName(string $slug): string
    {
        $base = 'rwsoft_site_'.str_replace('-', '_', $slug);
        $database = $base;
        $counter = 1;

        while (Site::query()->where('tenant_database', $database)->exists()) {
            $counter++;
            $database = $base.'_'.$counter;
        }

        return $database;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function tenantStorageOption(array $validated): string
    {
        $option = (string) ($validated['tenant_storage_option'] ?? config('tenancy.default_provisioning_mode', 'create_database'));

        return in_array($option, (array) config('tenancy.provisioning_modes', []), true) ? $option : 'create_database';
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function tenantDatabaseNameForStorageOption(string $storageOption, array $validated): string
    {
        if ($storageOption === 'shared_prefixed') {
            return (string) ($validated['tenant_database'] ?: config('tenancy.shared_database'));
        }

        if (filled($validated['tenant_database'] ?? null)) {
            return (string) $validated['tenant_database'];
        }

        return $this->tenantDatabaseName((string) $validated['slug']);
    }

    private function tenantTablePrefix(string $slug): string
    {
        $base = 't_'.substr(preg_replace('/[^a-z0-9_]/', '_', str_replace('-', '_', $slug)) ?: 'site', 0, 22);
        $prefix = $base.'_';
        $counter = 1;

        while (Site::query()->where('tenant_table_prefix', $prefix)->exists()) {
            $counter++;
            $prefix = $base.'_'.$counter.'_';
        }

        return $prefix;
    }
}
