<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->deleteObsoletePermissions();

        Schema::dropIfExists('cms_media_temporary_uploads');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally not reversible: these were unused development-only leftovers.
    }

    private function deleteObsoletePermissions(): void
    {
        if (! Schema::hasTable('acl_permissions')) {
            return;
        }

        $permissionIds = DB::table('acl_permissions')
            ->whereIn('route_name', $this->obsoleteRouteNames())
            ->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        if (Schema::hasTable('acl_permission_role')) {
            DB::table('acl_permission_role')
                ->whereIn('acl_permission_id', $permissionIds)
                ->delete();
        }

        DB::table('acl_permissions')
            ->whereIn('id', $permissionIds)
            ->delete();
    }

    /**
     * @return array<int, string>
     */
    private function obsoleteRouteNames(): array
    {
        return [
            'admin.cms.media.temp-upload',
            'admin.cms.media.temp-preview',
            'admin.cms.media.finalize',
        ];
    }
};
