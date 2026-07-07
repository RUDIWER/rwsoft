<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createLookupTables();
        $this->seedLookups();
        $this->addLookupColumns();
        $this->backfillPermissionLookups();
        $this->dropLegacyColumns();
    }

    public function down(): void
    {
        $this->restoreLegacyColumns();
        $this->backfillLegacyColumns();
        $this->dropLookupColumns();

        Schema::dropIfExists('acl_permission_actions');
        Schema::dropIfExists('acl_permission_modules');
    }

    private function createLookupTables(): void
    {
        if (! Schema::hasTable('acl_permission_modules')) {
            Schema::create('acl_permission_modules', function (Blueprint $table): void {
                $table->id();
                $table->string('key')->unique();
                $table->string('name');
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('acl_permission_actions')) {
            Schema::create('acl_permission_actions', function (Blueprint $table): void {
                $table->id();
                $table->string('key')->unique();
                $table->string('name');
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }
    }

    private function seedLookups(): void
    {
        $now = now();

        foreach ((array) config('acl_permissions.modules', []) as $module) {
            DB::table('acl_permission_modules')->updateOrInsert(
                ['id' => (int) $module['id']],
                [
                    'key' => (string) $module['key'],
                    'name' => (string) $module['name'],
                    'sort_order' => (int) $module['sort_order'],
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        foreach ((array) config('acl_permissions.actions', []) as $action) {
            DB::table('acl_permission_actions')->updateOrInsert(
                ['id' => (int) $action['id']],
                [
                    'key' => (string) $action['key'],
                    'name' => (string) $action['name'],
                    'sort_order' => (int) $action['sort_order'],
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    private function addLookupColumns(): void
    {
        if (! Schema::hasTable('acl_permissions')) {
            return;
        }

        Schema::table('acl_permissions', function (Blueprint $table): void {
            if (! Schema::hasColumn('acl_permissions', 'module_id')) {
                $table->foreignId('module_id')
                    ->nullable()
                    ->after('description')
                    ->constrained('acl_permission_modules')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('acl_permissions', 'action_id')) {
                $table->foreignId('action_id')
                    ->nullable()
                    ->after('module_id')
                    ->constrained('acl_permission_actions')
                    ->nullOnDelete();
            }
        });
    }

    private function backfillPermissionLookups(): void
    {
        if (! Schema::hasTable('acl_permissions')) {
            return;
        }

        if (Schema::hasColumn('acl_permissions', 'module') && Schema::hasColumn('acl_permissions', 'module_id')) {
            DB::table('acl_permissions')
                ->leftJoin('acl_permission_modules', 'acl_permissions.module', '=', 'acl_permission_modules.name')
                ->whereNotNull('acl_permission_modules.id')
                ->update(['acl_permissions.module_id' => DB::raw('acl_permission_modules.id')]);
        }

        if (Schema::hasColumn('acl_permissions', 'action') && Schema::hasColumn('acl_permissions', 'action_id')) {
            DB::table('acl_permissions')
                ->leftJoin('acl_permission_actions', 'acl_permissions.action', '=', 'acl_permission_actions.name')
                ->whereNotNull('acl_permission_actions.id')
                ->update(['acl_permissions.action_id' => DB::raw('acl_permission_actions.id')]);
        }
    }

    private function dropLegacyColumns(): void
    {
        if (! Schema::hasTable('acl_permissions')) {
            return;
        }

        Schema::table('acl_permissions', function (Blueprint $table): void {
            if (Schema::hasColumn('acl_permissions', 'module')) {
                $table->dropColumn('module');
            }

            if (Schema::hasColumn('acl_permissions', 'action')) {
                $table->dropColumn('action');
            }
        });
    }

    private function restoreLegacyColumns(): void
    {
        if (! Schema::hasTable('acl_permissions')) {
            return;
        }

        Schema::table('acl_permissions', function (Blueprint $table): void {
            if (! Schema::hasColumn('acl_permissions', 'module')) {
                $table->string('module')->nullable()->after('description');
            }

            if (! Schema::hasColumn('acl_permissions', 'action')) {
                $table->string('action')->nullable()->after('module');
            }
        });
    }

    private function backfillLegacyColumns(): void
    {
        if (! Schema::hasTable('acl_permissions')) {
            return;
        }

        if (Schema::hasColumn('acl_permissions', 'module') && Schema::hasColumn('acl_permissions', 'module_id')) {
            DB::table('acl_permissions')
                ->leftJoin('acl_permission_modules', 'acl_permissions.module_id', '=', 'acl_permission_modules.id')
                ->whereNotNull('acl_permission_modules.name')
                ->update(['acl_permissions.module' => DB::raw('acl_permission_modules.name')]);
        }

        if (Schema::hasColumn('acl_permissions', 'action') && Schema::hasColumn('acl_permissions', 'action_id')) {
            DB::table('acl_permissions')
                ->leftJoin('acl_permission_actions', 'acl_permissions.action_id', '=', 'acl_permission_actions.id')
                ->whereNotNull('acl_permission_actions.name')
                ->update(['acl_permissions.action' => DB::raw('acl_permission_actions.name')]);
        }
    }

    private function dropLookupColumns(): void
    {
        if (! Schema::hasTable('acl_permissions')) {
            return;
        }

        Schema::table('acl_permissions', function (Blueprint $table): void {
            if (Schema::hasColumn('acl_permissions', 'module_id')) {
                $table->dropConstrainedForeignId('module_id');
            }

            if (Schema::hasColumn('acl_permissions', 'action_id')) {
                $table->dropConstrainedForeignId('action_id');
            }
        });
    }
};
