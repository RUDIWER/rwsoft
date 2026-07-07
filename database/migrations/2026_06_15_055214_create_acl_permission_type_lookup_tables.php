<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createLookupTable();
        $this->seedLookups();
        $this->addLookupColumn();
        $this->backfillPermissionLookups();
        $this->dropLegacyColumn();
    }

    public function down(): void
    {
        $this->restoreLegacyColumn();
        $this->backfillLegacyColumn();
        $this->dropLookupColumn();

        Schema::dropIfExists('acl_permission_types');
    }

    private function createLookupTable(): void
    {
        if (! Schema::hasTable('acl_permission_types')) {
            Schema::create('acl_permission_types', function (Blueprint $table): void {
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

        foreach ((array) config('acl_permissions.types', []) as $type) {
            DB::table('acl_permission_types')->updateOrInsert(
                ['id' => (int) $type['id']],
                [
                    'key' => (string) $type['key'],
                    'name' => (string) $type['name'],
                    'sort_order' => (int) $type['sort_order'],
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    private function addLookupColumn(): void
    {
        if (! Schema::hasTable('acl_permissions')) {
            return;
        }

        Schema::table('acl_permissions', function (Blueprint $table): void {
            if (! Schema::hasColumn('acl_permissions', 'type_id')) {
                $table->foreignId('type_id')
                    ->nullable()
                    ->after('action_id')
                    ->constrained('acl_permission_types')
                    ->nullOnDelete();
            }
        });
    }

    private function backfillPermissionLookups(): void
    {
        if (! Schema::hasTable('acl_permissions')) {
            return;
        }

        if (Schema::hasColumn('acl_permissions', 'type') && Schema::hasColumn('acl_permissions', 'type_id')) {
            DB::table('acl_permission_types')
                ->select(['id', 'key'])
                ->orderBy('id')
                ->each(function (object $type): void {
                    DB::table('acl_permissions')
                        ->where('type', $type->key)
                        ->update(['type_id' => $type->id]);
                });
        }
    }

    private function dropLegacyColumn(): void
    {
        if (! Schema::hasTable('acl_permissions')) {
            return;
        }

        Schema::table('acl_permissions', function (Blueprint $table): void {
            if (Schema::hasColumn('acl_permissions', 'type')) {
                $table->dropColumn('type');
            }
        });
    }

    private function restoreLegacyColumn(): void
    {
        if (! Schema::hasTable('acl_permissions')) {
            return;
        }

        Schema::table('acl_permissions', function (Blueprint $table): void {
            if (! Schema::hasColumn('acl_permissions', 'type')) {
                $table->string('type')->nullable()->after('action_id');
            }
        });
    }

    private function backfillLegacyColumn(): void
    {
        if (! Schema::hasTable('acl_permissions')) {
            return;
        }

        if (Schema::hasColumn('acl_permissions', 'type') && Schema::hasColumn('acl_permissions', 'type_id')) {
            DB::table('acl_permission_types')
                ->select(['id', 'key'])
                ->orderBy('id')
                ->each(function (object $type): void {
                    DB::table('acl_permissions')
                        ->where('type_id', $type->id)
                        ->update(['type' => $type->key]);
                });
        }
    }

    private function dropLookupColumn(): void
    {
        if (! Schema::hasTable('acl_permissions')) {
            return;
        }

        Schema::table('acl_permissions', function (Blueprint $table): void {
            if (Schema::hasColumn('acl_permissions', 'type_id')) {
                $table->dropConstrainedForeignId('type_id');
            }
        });
    }
};
