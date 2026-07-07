<?php

namespace App\Http\Controllers\Admin\Run\Query;

use App\Http\Controllers\Controller;
use App\Models\Query\Query;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantDatabaseGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class QueryLegacyReportController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function selections(int $id, Request $request): RedirectResponse
    {
        TenantDatabaseGuard::ensureTenantConnection();

        $query = $this->queryForActiveApplication($id, $request);

        $this->auditLogger->success(
            action: 'run.query.legacy.selections_redirect',
            module: 'query_runner',
            subjectType: 'query',
            subjectKey: (string) $query->id,
            message: __('query_builder_ui.audit.legacy_report_selections_redirect'),
            request: $request,
        );

        return redirect()->route('admin.run.queries.show', $this->runRoutePayload($query, $request));
    }

    public function create(int $id, Request $request): RedirectResponse
    {
        TenantDatabaseGuard::ensureTenantConnection();

        $query = $this->queryForActiveApplication($id, $request);

        $this->auditLogger->success(
            action: 'run.query.legacy.create_redirect',
            module: 'query_runner',
            subjectType: 'query',
            subjectKey: (string) $query->id,
            message: __('query_builder_ui.audit.legacy_report_create_redirect'),
            request: $request,
        );

        return $this->redirectByOutputMode($query, $request);
    }

    public function download(Request $request): RedirectResponse
    {
        TenantDatabaseGuard::ensureTenantConnection();

        $queryId = $this->resolveLegacyQueryId($request);

        if ($queryId === null) {
            $this->auditLogger->failure(
                action: 'run.query.legacy.download.failed',
                module: 'query_runner',
                subjectType: 'query',
                subjectKey: null,
                message: __('query_builder_ui.audit.legacy_report_download_invalid_id'),
                meta: [
                    'reason' => 'invalid_report_id',
                ],
                request: $request,
            );

            return redirect()
                ->route('admin.run.dashboard')
                ->with('error', __('query_builder_ui.runtime.invalid_report_id'));
        }

        $query = $this->queryForActiveApplication($queryId, $request);

        $this->auditLogger->success(
            action: 'run.query.legacy.download_redirect',
            module: 'query_runner',
            subjectType: 'query',
            subjectKey: (string) $query->id,
            message: __('query_builder_ui.audit.legacy_report_download_redirect'),
            request: $request,
        );

        return $this->redirectByOutputMode($query, $request, ['id', 'query', 'report_id']);
    }

    public function template(int $id, Request $request): RedirectResponse
    {
        TenantDatabaseGuard::ensureTenantConnection();

        $query = $this->queryForActiveApplication($id, $request);

        $this->auditLogger->success(
            action: 'run.query.legacy.template_redirect',
            module: 'query_runner',
            subjectType: 'query',
            subjectKey: (string) $query->id,
            message: __('query_builder_ui.audit.legacy_report_template_redirect'),
            request: $request,
        );

        return redirect()->route('admin.queries.builder.template', ['query' => $query->id]);
    }

    /**
     * @param  array<int, string>  $excludedKeys
     */
    private function redirectByOutputMode(Query $query, Request $request, array $excludedKeys = []): RedirectResponse
    {
        $payload = $this->runRoutePayload($query, $request, $excludedKeys);
        $outputMode = (string) ($query->output_mode ?? 'table');

        if ($outputMode === 'excel') {
            return redirect()->route('admin.run.queries.export', $payload);
        }

        if ($outputMode === 'report') {
            return redirect()->route('admin.run.queries.report', $payload);
        }

        $payload['__force_table'] = 1;

        return redirect()->route('admin.run.queries.show', $payload);
    }

    /**
     * @param  array<int, string>  $excludedKeys
     * @return array<string, mixed>
     */
    private function runRoutePayload(Query $query, Request $request, array $excludedKeys = []): array
    {
        $exclude = array_merge(['id'], $excludedKeys);

        return array_merge(
            ['query' => $query->id],
            $request->except($exclude),
        );
    }

    private function resolveLegacyQueryId(Request $request): ?int
    {
        $candidates = [
            $request->input('query'),
            $request->input('id'),
            $request->input('report_id'),
        ];

        foreach ($candidates as $candidate) {
            $value = (int) $candidate;

            if ($value > 0) {
                return $value;
            }
        }

        return null;
    }

    private function queryForActiveApplication(int $queryId, Request $request): Query
    {
        return Query::query()->findOrFail($queryId);
    }
}
