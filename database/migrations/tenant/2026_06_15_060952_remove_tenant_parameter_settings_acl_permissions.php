<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('acl_permissions')) {
            return;
        }

        DB::table('acl_permissions')
            ->whereIn('route_name', ['admin.parameters.edit', 'admin.parameters.store'])
            ->delete();
    }

    public function down(): void
    {
        // The removed routes no longer exist, so recreating their ACL records would leave dead permissions.
    }
};
