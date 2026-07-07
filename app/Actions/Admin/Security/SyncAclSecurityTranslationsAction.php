<?php

namespace App\Actions\Admin\Security;

use App\Support\Translations\TranslationManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncAclSecurityTranslationsAction
{
    private const SOURCE = 'admin_security_ui';

    public function __construct(private readonly TranslationManager $translationManager) {}

    /**
     * @return array{acl_keys_requested:int,acl_keys_created:int,acl_created_keys:array<int, string>,roles_scanned:int,permissions_scanned:int}
     */
    public function handle(?string $sourceLocale = null): array
    {
        $sourceLocale = trim((string) ($sourceLocale ?: config('translation_editor.source_locale', 'en')));
        $keys = [];
        $rolesScanned = 0;
        $permissionsScanned = 0;

        if (Schema::hasTable('acl_roles')) {
            DB::table('acl_roles')
                ->select(['key', 'name', 'description'])
                ->orderBy('key')
                ->get()
                ->each(function (object $role) use (&$keys, &$rolesScanned): void {
                    $roleKey = $this->normalizeKey($role->key ?? '');

                    if ($roleKey === '') {
                        return;
                    }

                    $rolesScanned++;
                    $keys["role_labels.{$roleKey}"] = $this->fallback($role->name ?? '', $role->key ?? '');
                    $keys["role_descriptions.{$roleKey}"] = $this->fallback($role->description ?? '', $role->name ?? $role->key ?? '');
                });
        }

        if (Schema::hasTable('acl_permissions')) {
            $permissionsQuery = DB::table('acl_permissions')
                ->select(['acl_permissions.route_name', 'acl_permissions.description']);

            if (Schema::hasTable('acl_permission_modules') && Schema::hasColumn('acl_permissions', 'module_id')) {
                $permissionsQuery
                    ->leftJoin('acl_permission_modules', 'acl_permissions.module_id', '=', 'acl_permission_modules.id')
                    ->addSelect([
                        'acl_permission_modules.key as module_key',
                        'acl_permission_modules.name as module_name',
                    ]);
            } elseif (Schema::hasColumn('acl_permissions', 'module')) {
                $permissionsQuery->addSelect('acl_permissions.module as module_name');
            }

            if (Schema::hasTable('acl_permission_actions') && Schema::hasColumn('acl_permissions', 'action_id')) {
                $permissionsQuery
                    ->leftJoin('acl_permission_actions', 'acl_permissions.action_id', '=', 'acl_permission_actions.id')
                    ->addSelect([
                        'acl_permission_actions.key as action_key',
                        'acl_permission_actions.name as action_name',
                    ]);
            } elseif (Schema::hasColumn('acl_permissions', 'action')) {
                $permissionsQuery->addSelect('acl_permissions.action as action_name');
            }

            if (Schema::hasTable('acl_permission_types') && Schema::hasColumn('acl_permissions', 'type_id')) {
                $permissionsQuery
                    ->leftJoin('acl_permission_types', 'acl_permissions.type_id', '=', 'acl_permission_types.id')
                    ->addSelect([
                        'acl_permission_types.key as type_key',
                        'acl_permission_types.name as type_name',
                    ]);
            } elseif (Schema::hasColumn('acl_permissions', 'type')) {
                $permissionsQuery->addSelect('acl_permissions.type as type_name');
            }

            $permissionsQuery
                ->orderBy('route_name')
                ->get()
                ->each(function (object $permission) use (&$keys, &$permissionsScanned): void {
                    $routeKey = $this->normalizeKey($permission->route_name ?? '');

                    if ($routeKey === '') {
                        return;
                    }

                    $permissionsScanned++;
                    $keys["permission_descriptions.{$routeKey}"] = $this->fallback($permission->description ?? '', $permission->route_name ?? '');

                    $moduleKey = $this->normalizeKey($permission->module_key ?? $permission->module_name ?? '');
                    if ($moduleKey !== '') {
                        $keys["permission_modules.{$moduleKey}"] = $this->fallback($permission->module_name ?? '', $moduleKey);
                    }

                    $actionKey = $this->normalizeKey($permission->action_key ?? $permission->action_name ?? '');
                    if ($actionKey !== '') {
                        $keys["permission_actions.{$actionKey}"] = $this->fallback($permission->action_name ?? '', $actionKey);
                    }

                    $typeKey = $this->normalizeKey($permission->type_key ?? $permission->type_name ?? '');
                    if ($typeKey !== '') {
                        $keys["permission_types.{$typeKey}"] = $this->fallback($permission->type_name ?? '', $typeKey);
                    }
                });
        }

        $result = $this->translationManager->mergeMissingSourceKeys(self::SOURCE, $sourceLocale, $keys);

        return [
            'acl_keys_requested' => (int) Arr::get($result, 'requested', 0),
            'acl_keys_created' => (int) Arr::get($result, 'created', 0),
            'acl_created_keys' => array_values((array) Arr::get($result, 'created_keys', [])),
            'roles_scanned' => $rolesScanned,
            'permissions_scanned' => $permissionsScanned,
        ];
    }

    public function normalizeKey(mixed $value): string
    {
        $key = strtolower(trim((string) $value));
        $key = (string) preg_replace('/[^a-z0-9]+/', '_', $key);

        return trim($key, '_');
    }

    private function fallback(mixed $primary, mixed $fallback): string
    {
        $primary = trim((string) $primary);

        if ($primary !== '') {
            return $primary;
        }

        return trim((string) $fallback);
    }
}
