<?php

use App\Actions\Admin\Cms\SyncPublicTextKeysAction;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(SyncPublicTextKeysAction::class)->handle();
    }

    public function down(): void
    {
        // Public text defaults are intentionally preserved.
    }
};
