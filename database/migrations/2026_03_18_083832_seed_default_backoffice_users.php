<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $now = now();

        DB::table('users')->updateOrInsert(
            ['email' => 'admin@rwsoft.local'],
            [
                'name' => 'RW Admin',
                'password' => Hash::make('Admin123!'),
                'email_verified_at' => $now,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        DB::table('users')->updateOrInsert(
            ['email' => 'user@rwsoft.local'],
            [
                'name' => 'RW User',
                'password' => Hash::make('User12345'),
                'email_verified_at' => $now,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        if (! Schema::hasTable('acl_roles') || ! Schema::hasTable('acl_role_user')) {
            return;
        }

        $adminUserId = DB::table('users')->where('email', 'admin@rwsoft.local')->value('id');
        $superAdminRoleId = DB::table('acl_roles')->where('key', 'super_admin')->value('id');

        if ($adminUserId && $superAdminRoleId) {
            DB::table('acl_role_user')->updateOrInsert(
                [
                    'user_id' => $adminUserId,
                    'acl_role_id' => $superAdminRoleId,
                ],
                [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $adminUserId = DB::table('users')->where('email', 'admin@rwsoft.local')->value('id');

        if ($adminUserId && Schema::hasTable('acl_role_user')) {
            DB::table('acl_role_user')
                ->where('user_id', $adminUserId)
                ->delete();
        }

        DB::table('users')
            ->whereIn('email', ['admin@rwsoft.local', 'user@rwsoft.local'])
            ->delete();
    }
};
