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
                ->get(['id', 'name', 'slug', 'tenant_database', 'status', 'provisioned_at', 'created_at']),
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
                $site->tenant_database = $this->tenantDatabaseName($validated['slug']);
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
}
