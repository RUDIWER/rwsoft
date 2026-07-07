<?php

use App\Actions\Admin\Cms\SyncSiteMenuCmsMenuIdContractAction;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    protected $connection = 'tenant';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app(SyncSiteMenuCmsMenuIdContractAction::class)->handle($this->connection);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally irreversible: site_menu now requires an explicit cms_menu_id.
    }
};
