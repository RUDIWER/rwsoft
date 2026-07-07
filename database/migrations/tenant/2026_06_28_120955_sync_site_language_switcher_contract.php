<?php

use App\Actions\Admin\Cms\SyncSiteLanguageSwitcherContractAction;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app(SyncSiteLanguageSwitcherContractAction::class)->handle('tenant');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Catalog sync migrations are intentionally forward-only.
    }
};
