<?php

namespace App\Console\Commands\Platform;

use App\Actions\Platform\ProvisionSiteDatabaseAction;
use App\Models\Platform\Site;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class InstallRwSoftCommand extends Command
{
    private const DEFAULT_PLATFORM_ADMIN_EMAIL = 'admin@rwsoft.local';

    protected $signature = 'rwsoft:install
        {--profile= : Installation profile: docker, lerd, herd, laravel-cloud}
        {--tenant-storage= : Tenant storage mode: create_database, existing_database, shared_prefixed}
        {--skip-central-migrations : Do not run central database migrations}
        {--skip-tenant-migrations : Do not run tenant migrations for existing sites}
        {--skip-tenant-seeding : Do not run tenant ACL and CMS default seeders for existing sites}
        {--platform-admin-email= : Existing central user email to promote as platform admin}
        {--skip-site : Do not create the first site when no sites exist}
        {--site-name= : First site name}
        {--site-slug= : First site slug}
        {--site-domain= : First site primary domain}
        {--site-admin-email= : Existing central user email to attach as first site admin}
        {--site-tenant-database= : First site tenant database name}
        {--site-tenant-prefix= : First site tenant table prefix for shared_prefixed mode}
        {--dry-run : Show the resolved install plan without executing commands}
        {--force : Force migrations without confirmation}';

    protected $description = 'Install or synchronize the RwSoft Laravel application layer.';

    /**
     * Execute the console command.
     */
    public function handle(ProvisionSiteDatabaseAction $provisionSiteDatabase): int
    {
        $profile = $this->resolveProfile();

        if ($profile === null) {
            return self::FAILURE;
        }

        $tenantStorage = $this->resolveTenantStorage($profile);

        if ($tenantStorage === null) {
            return self::FAILURE;
        }

        $this->applyTenantStorageDefaults($tenantStorage);
        $this->writeInstallPlan($profile, $tenantStorage);

        if ((bool) $this->option('dry-run')) {
            $this->info(__('admin_common_ui.platform.install.dry_run_complete'));

            return self::SUCCESS;
        }

        if (! (bool) $this->option('skip-central-migrations')) {
            $this->info(__('admin_common_ui.platform.install.central_migrations'));
            $exitCode = $this->call('migrate', [
                '--database' => 'central',
                '--force' => (bool) $this->option('force'),
            ]);

            if ($exitCode !== self::SUCCESS) {
                return $exitCode;
            }
        } else {
            $this->warn(__('admin_common_ui.platform.install.central_migrations_skipped'));
        }

        if (! $this->hasSitesTable()) {
            $this->warn(__('admin_common_ui.platform.install.sites_table_missing'));

            return self::SUCCESS;
        }

        $platformAdminEmail = $this->resolvePlatformAdminEmail();

        if ($platformAdminEmail !== null && ! $this->promotePlatformAdmin($platformAdminEmail, $this->requiresExistingPlatformAdmin())) {
            return self::FAILURE;
        }

        $tenantLayerHandled = false;

        if (Site::query()->count() === 0) {
            if ((bool) $this->option('skip-site')) {
                $this->line(__('admin_common_ui.platform.install.no_sites'));

                return self::SUCCESS;
            }

            if (! $this->validateFirstSiteOptions($tenantStorage)) {
                return self::FAILURE;
            }

            $site = $this->createFirstSite($tenantStorage);

            if (! (bool) $this->option('skip-tenant-migrations') && ! (bool) $this->option('skip-tenant-seeding')) {
                if (! $provisionSiteDatabase->handle($site)) {
                    $this->error($site->fresh()?->provisioning_error ?: __('admin_common_ui.platform.sites.flash.provisioning_failed'));

                    return self::FAILURE;
                }

                $tenantLayerHandled = true;
            }
        }

        if (! $tenantLayerHandled && ! (bool) $this->option('skip-tenant-migrations')) {
            $this->info(__('admin_common_ui.platform.install.tenant_migrations'));
            $exitCode = $this->call('tenants:migrate', [
                '--force' => (bool) $this->option('force'),
            ]);

            if ($exitCode !== self::SUCCESS) {
                return $exitCode;
            }
        } else {
            $this->warn(__('admin_common_ui.platform.install.tenant_migrations_skipped'));
        }

        if (! $tenantLayerHandled && ! (bool) $this->option('skip-tenant-seeding')) {
            $this->info(__('admin_common_ui.platform.install.tenant_acl'));
            $exitCode = $this->call('tenants:seed-acl');

            if ($exitCode !== self::SUCCESS) {
                return $exitCode;
            }

            $this->info(__('admin_common_ui.platform.install.tenant_cms_defaults'));
            $exitCode = $this->call('tenants:seed-cms-defaults');

            if ($exitCode !== self::SUCCESS) {
                return $exitCode;
            }
        } else {
            $this->warn(__('admin_common_ui.platform.install.tenant_seeding_skipped'));
        }

        $this->info(__('admin_common_ui.platform.install.complete'));

        return self::SUCCESS;
    }

    private function resolveProfile(): ?string
    {
        $profile = (string) ($this->option('profile') ?: config('rwsoft.default_install_profile', 'docker'));
        $profiles = array_keys((array) config('rwsoft.install_profiles', []));

        if (! in_array($profile, $profiles, true)) {
            $this->error(__('admin_common_ui.platform.install.invalid_profile', [
                'profile' => $profile,
                'profiles' => implode(', ', $profiles),
            ]));

            return null;
        }

        return $profile;
    }

    private function resolveTenantStorage(string $profile): ?string
    {
        $tenantStorage = (string) ($this->option('tenant-storage') ?: config("rwsoft.install_profiles.{$profile}.default_tenant_storage", config('tenancy.default_provisioning_mode')));
        $storageModes = (array) config('tenancy.provisioning_modes', []);

        if (! in_array($tenantStorage, $storageModes, true)) {
            $this->error(__('admin_common_ui.platform.install.invalid_tenant_storage', [
                'tenant_storage' => $tenantStorage,
                'storage_modes' => implode(', ', $storageModes),
            ]));

            return null;
        }

        return $tenantStorage;
    }

    private function applyTenantStorageDefaults(string $tenantStorage): void
    {
        Config::set('tenancy.default_provisioning_mode', $tenantStorage);
        Config::set('tenancy.default_database_mode', $tenantStorage === 'shared_prefixed' ? 'shared_prefixed' : 'separate');
    }

    private function writeInstallPlan(string $profile, string $tenantStorage): void
    {
        $this->info(__('admin_common_ui.platform.install.start'));
        $this->line(__('admin_common_ui.platform.install.profile', ['profile' => $profile]));
        $this->line(__('admin_common_ui.platform.install.tenant_storage', ['tenant_storage' => $tenantStorage]));
        $this->line(__('admin_common_ui.platform.install.central_database', ['database' => (string) config('database.connections.central.database')]));

        if ($tenantStorage === 'shared_prefixed') {
            $this->line(__('admin_common_ui.platform.install.shared_database', ['database' => (string) config('tenancy.shared_database')]));
        }
    }

    private function createFirstSite(string $tenantStorage): Site
    {
        $siteData = $this->resolveFirstSiteData($tenantStorage);

        $this->info(__('admin_common_ui.platform.install.creating_first_site', [
            'name' => $siteData['name'],
            'slug' => $siteData['slug'],
        ]));

        return DB::connection('central')->transaction(function () use ($siteData, $tenantStorage): Site {
            $site = Site::query()->create([
                'name' => $siteData['name'],
                'slug' => $siteData['slug'],
                'tenant_database' => $siteData['tenant_database'],
                'tenant_table_prefix' => $siteData['tenant_table_prefix'],
                'tenant_database_mode' => $tenantStorage === 'shared_prefixed' ? 'shared_prefixed' : 'separate',
                'tenant_provisioning_mode' => $tenantStorage,
                'status' => 'draft',
            ]);

            if ($siteData['domain'] !== null) {
                $site->domains()->create([
                    'host' => $siteData['domain'],
                    'is_primary' => true,
                    'force_https' => str_starts_with((string) config('app.url'), 'https://'),
                ]);
            }

            if ($siteData['admin_email'] !== null) {
                $user = User::query()
                    ->where('email', $siteData['admin_email'])
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
    }

    private function resolvePlatformAdminEmail(): ?string
    {
        $explicitEmail = $this->normalizeEmail((string) $this->option('platform-admin-email'));

        if ($explicitEmail !== null) {
            return $explicitEmail;
        }

        $siteAdminEmail = $this->normalizeEmail((string) $this->option('site-admin-email'));

        if ($siteAdminEmail !== null) {
            return $siteAdminEmail;
        }

        if (! $this->hasUsersTable()) {
            return null;
        }

        return User::query()->where('email', self::DEFAULT_PLATFORM_ADMIN_EMAIL)->exists()
            ? self::DEFAULT_PLATFORM_ADMIN_EMAIL
            : null;
    }

    private function requiresExistingPlatformAdmin(): bool
    {
        return $this->normalizeEmail((string) $this->option('platform-admin-email')) !== null
            || $this->normalizeEmail((string) $this->option('site-admin-email')) !== null;
    }

    private function promotePlatformAdmin(string $email, bool $failWhenMissing): bool
    {
        if (! $this->hasUsersTable()) {
            if ($failWhenMissing) {
                $this->error(__('admin_common_ui.platform.install.users_table_missing'));
            }

            return ! $failWhenMissing;
        }

        $user = User::query()
            ->where('email', $email)
            ->first();

        if (! $user instanceof User) {
            if ($failWhenMissing) {
                $this->error(__('admin_common_ui.platform.install.platform_admin_missing', ['email' => $email]));
            }

            return ! $failWhenMissing;
        }

        if (! (bool) $user->is_platform_admin) {
            $user->forceFill(['is_platform_admin' => true])->save();
        }

        $this->info(__('admin_common_ui.platform.install.platform_admin_promoted', ['email' => $email]));

        return true;
    }

    private function validateFirstSiteOptions(string $tenantStorage): bool
    {
        $tenantDatabase = trim((string) $this->option('site-tenant-database'));
        $centralDatabase = (string) config('database.connections.central.database');
        $tenantTablePrefix = strtolower(trim((string) $this->option('site-tenant-prefix')));

        if ($tenantStorage === 'existing_database' && $tenantDatabase === '') {
            $this->error(__('admin_common_ui.errors.tenant_database_required'));

            return false;
        }

        if (in_array($tenantStorage, ['create_database', 'existing_database'], true)
            && $tenantDatabase !== ''
            && $tenantDatabase === $centralDatabase) {
            $this->error(__('admin_common_ui.errors.tenant_database_must_not_be_central'));

            return false;
        }

        if ($tenantTablePrefix !== '' && preg_match((string) config('tenancy.table_prefix_pattern'), $tenantTablePrefix) !== 1) {
            $this->error(__('admin_common_ui.errors.tenant_table_prefix_invalid'));

            return false;
        }

        return true;
    }

    /**
     * @return array{name: string, slug: string, domain: ?string, admin_email: ?string, tenant_database: string, tenant_table_prefix: ?string}
     */
    private function resolveFirstSiteData(string $tenantStorage): array
    {
        $name = trim((string) ($this->option('site-name') ?: config('app.name', 'RwSoft')));
        $slug = trim((string) ($this->option('site-slug') ?: Str::slug($name)));

        if ($slug === '') {
            $slug = 'rwsoft';
        }

        return [
            'name' => $name !== '' ? $name : 'RwSoft',
            'slug' => $this->uniqueSiteSlug($slug),
            'domain' => $this->normalizeHost((string) ($this->option('site-domain') ?: (parse_url((string) config('app.url'), PHP_URL_HOST) ?: ''))),
            'admin_email' => $this->normalizeEmail((string) $this->option('site-admin-email'))
                ?? $this->resolvePlatformAdminEmail(),
            'tenant_database' => $this->tenantDatabaseName($tenantStorage, $slug),
            'tenant_table_prefix' => $tenantStorage === 'shared_prefixed'
                ? $this->tenantTablePrefix($slug)
                : null,
        ];
    }

    private function tenantDatabaseName(string $tenantStorage, string $slug): string
    {
        $configured = trim((string) $this->option('site-tenant-database'));

        if ($tenantStorage === 'shared_prefixed') {
            return $configured !== '' ? $configured : (string) config('tenancy.shared_database');
        }

        if ($configured !== '') {
            return $configured;
        }

        $base = 'rwsoft_site_'.str_replace('-', '_', $slug);
        $database = $base;
        $counter = 1;

        while (Site::query()->where('tenant_database', $database)->exists()) {
            $counter++;
            $database = $base.'_'.$counter;
        }

        return $database;
    }

    private function tenantTablePrefix(string $slug): string
    {
        $configured = strtolower(trim((string) $this->option('site-tenant-prefix')));

        if ($configured !== '') {
            return $configured;
        }

        $base = 't_'.substr(preg_replace('/[^a-z0-9_]/', '_', str_replace('-', '_', $slug)) ?: 'site', 0, 22);
        $prefix = $base.'_';
        $counter = 1;

        while (Site::query()->where('tenant_table_prefix', $prefix)->exists()) {
            $counter++;
            $prefix = $base.'_'.$counter.'_';
        }

        return $prefix;
    }

    private function uniqueSiteSlug(string $slug): string
    {
        $slug = Str::slug($slug) ?: 'rwsoft';
        $candidate = $slug;
        $counter = 1;

        while (Site::query()->where('slug', $candidate)->exists()) {
            $counter++;
            $candidate = $slug.'-'.$counter;
        }

        return $candidate;
    }

    private function normalizeHost(string $host): ?string
    {
        $host = strtolower(trim($host));

        if ($host === '') {
            return null;
        }

        $host = preg_replace('#^https?://#', '', $host) ?? $host;
        $host = explode('/', $host, 2)[0];
        $host = explode(':', $host, 2)[0];

        return trim($host, '.') ?: null;
    }

    private function normalizeEmail(string $email): ?string
    {
        $email = strtolower(trim($email));

        return $email !== '' ? $email : null;
    }

    private function hasSitesTable(): bool
    {
        return Schema::connection('central')->hasTable((new Site)->getTable());
    }

    private function hasUsersTable(): bool
    {
        return Schema::connection('central')->hasTable((new User)->getTable());
    }
}
