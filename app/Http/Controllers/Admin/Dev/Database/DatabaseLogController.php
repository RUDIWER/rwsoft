<?php

namespace App\Http\Controllers\Admin\Dev\Database;

use App\Actions\Admin\Base\RwTableAction;
use App\Http\Controllers\Controller;
use App\Models\DatabaseLog;
use App\Support\Database\DatabaseAccessGate;
use App\Support\Tenancy\TenantDatabaseGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DatabaseLogController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        TenantDatabaseGuard::ensureTenantConnection();

        if (! DatabaseAccessGate::canAccess($request->user(), 'admin.database-logs', ['view', 'full_backup'])) {
            return redirect()->route('admin.db-diagram')->with('error', __('db_diagram_ui.backend.no_access_backup_logs'));
        }

        $logs = RwTableAction::runtimeData(
            $request,
            DatabaseLog::class,
            25,
            static function ($query): void {
                $query->with('user')->orderBy('created_at', 'desc');
            }
        );

        return Inertia::render('Admin/Database/DatabaseLogTable', [
            'logs' => $logs,
        ]);
    }
}
