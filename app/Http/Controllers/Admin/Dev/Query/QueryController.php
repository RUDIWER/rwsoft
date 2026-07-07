<?php

namespace App\Http\Controllers\Admin\Dev\Query;

use App\Actions\Admin\Base\Query\BuildQueryFromBuilderAction;
use App\Actions\Admin\Base\Query\ValidateSqlQueryAction;
use App\Actions\Admin\Base\ResolveLocalizedHelpContentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Dev\Query\InspectQueryRequest;
use App\Http\Requests\Admin\Dev\Query\StoreQueryRequest;
use App\Models\Query\Query;
use App\Models\Query\QueryBuilderSelectTable;
use App\Models\Security\AclPermission;
use App\Support\Audit\AuditLogger;
use App\Support\ModelDiscovery\ModelClassLocator;
use App\Support\Tenancy\TenantDatabaseGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use ReflectionClass;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QueryController extends Controller
{
    public function __construct(
        private readonly ModelClassLocator $modelClassLocator,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(): Response
    {
        TenantDatabaseGuard::ensureTenantConnection();

        $queries = Query::query()
            ->orderByDesc('id')
            ->get([
                'id',
                'slug',
                'description',
                'query_mode',
                'output_mode',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->map(static fn (Query $query): array => [
                'id' => (int) $query->id,
                'slug' => (string) $query->slug,
                'description' => (string) $query->description,
                'query_mode' => (string) $query->query_mode,
                'output_mode' => (string) $query->output_mode,
                'is_active' => (bool) $query->is_active,
                'created_at' => optional($query->created_at)?->toDateTimeString(),
                'updated_at' => optional($query->updated_at)?->toDateTimeString(),
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/Query/QueryTable', [
            'queries' => $queries,
        ]);
    }

    public function create(Request $request): Response
    {
        TenantDatabaseGuard::ensureTenantConnection();

        $databaseStructure = $this->generateDatabaseStructure();

        return Inertia::render('Admin/Query/QueryForm', [
            'query' => $this->queryPayload(new Query),
            'store_url' => route('admin.queries.builder.store-new'),
            'db_structure' => $databaseStructure,
            'table_options' => $this->tableOptions($databaseStructure),
            'template_help_html' => ResolveLocalizedHelpContentAction::handle(
                'admin/query/template-cheatsheet',
                $request->getLocale(),
            ),
        ]);
    }

    public function edit(Request $request, int|string $query): Response
    {
        TenantDatabaseGuard::ensureTenantConnection();

        $query = $this->findQuery($query);

        $databaseStructure = $this->generateDatabaseStructure();

        return Inertia::render('Admin/Query/QueryForm', [
            'query' => $this->queryPayload($query),
            'store_url' => route('admin.queries.builder.store', ['query' => $query->id]),
            'db_structure' => $databaseStructure,
            'table_options' => $this->tableOptions($databaseStructure),
            'template_help_html' => ResolveLocalizedHelpContentAction::handle(
                'admin/query/template-cheatsheet',
                $request->getLocale(),
            ),
        ]);
    }

    public function store(StoreQueryRequest $request): RedirectResponse
    {
        TenantDatabaseGuard::ensureTenantConnection();

        $validated = $request->validated();
        $queryId = $this->queryIdFromRequest($request);
        $query = $queryId > 0 ? $this->findQuery($queryId) : new Query;
        $isNew = ! $query->exists;

        $description = trim((string) $validated['description']);
        $slug = trim((string) ($validated['slug'] ?? ''));
        $resolvedSql = $this->resolveQuerySql($validated);

        if ($slug === '') {
            $slug = Str::slug($description);
        }

        $query->fill([
            'description' => $description,
            'slug' => $slug,
            'memo' => (string) ($validated['memo'] ?? ''),
            'query_mode' => (string) $validated['query_mode'],
            'output_mode' => (string) $validated['output_mode'],
            'report_data_source' => $validated['report_data_source'] ?? null,
            'report_output_format' => $validated['report_output_format'] ?? null,
            'table_name' => (string) ($validated['table_name'] ?? ''),
            'all_fields' => (bool) ($validated['all_fields'] ?? false),
            'distinct_select' => (bool) ($validated['distinct_select'] ?? false),
            'query' => (string) ($resolvedSql['query'] ?? ''),
            'test_query' => (string) ($resolvedSql['test_query'] ?? ''),
            'selected_fields' => $validated['selected_fields'] ?? [],
            'join_rows' => $validated['join_rows'] ?? [],
            'where_rows' => $validated['where_rows'] ?? [],
            'group_by' => (bool) ($validated['group_by'] ?? false),
            'group_rows' => $validated['group_rows'] ?? [],
            'aggregate_rows' => $validated['aggregate_rows'] ?? [],
            'having_rows' => $validated['having_rows'] ?? [],
            'binding_rows' => $validated['binding_rows'] ?? [],
            'query_group_id' => null,
            'report_group_id' => null,
            'chart_group_id' => null,
            'chart_config' => $validated['chart_config'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'updated_by' => $this->currentUserId(),
        ]);

        if ($isNew) {
            $query->created_by = $this->currentUserId();
        }

        $templateUpload = $request->file('report_template_upload');

        if ($templateUpload instanceof UploadedFile && (string) $validated['output_mode'] === 'report') {
            $this->storeReportTemplate($query, $templateUpload);
        }

        $query->save();

        $this->auditLogger->success(
            action: $isNew ? 'query.create' : 'query.update',
            module: 'query_builder',
            subjectType: 'query',
            subjectKey: (string) $query->id,
            message: __('query_builder_ui.feedback.query_saved'),
            meta: [
                'query_id' => (int) $query->id,
                'output_mode' => (string) $query->output_mode,
                'query_mode' => (string) $query->query_mode,
            ],
            request: $request,
        );

        return redirect()
            ->route('admin.queries.builder.edit', ['query' => $query->id])
            ->with('status', __('query_builder_ui.feedback.query_saved'));
    }

    public function delete(Request $request, int|string $query): RedirectResponse
    {
        TenantDatabaseGuard::ensureTenantConnection();

        $query = $this->findQuery($query);

        $blockers = $this->deleteBlockersForQuery($query);

        if ($blockers !== []) {
            $message = $this->deleteBlockedMessage($blockers);

            $this->auditLogger->failure(
                action: 'query.delete.blocked',
                module: 'query_builder',
                subjectType: 'query',
                subjectKey: (string) $query->id,
                message: $message,
                meta: [
                    'query_id' => (int) $query->id,
                ],
                request: $request,
            );

            return redirect()
                ->route('admin.queries.builder.edit', ['query' => $query->id])
                ->with('warning', $message);
        }

        $queryId = (int) $query->id;
        $queryName = trim((string) ($query->description ?? ''));
        $templatePath = trim((string) ($query->report_template_path ?? ''));
        $label = $queryName !== '' ? $queryName : '#'.$queryId;

        if ($templatePath !== '' && Storage::disk('private')->exists($templatePath)) {
            Storage::disk('private')->delete($templatePath);
        }

        $query->delete();

        $this->auditLogger->success(
            action: 'query.delete',
            module: 'query_builder',
            subjectType: 'query',
            subjectKey: (string) $queryId,
            message: __('query_builder_ui.feedback.query_deleted', ['label' => $label]),
            meta: [
                'query_id' => $queryId,
            ],
            request: $request,
        );

        return redirect()
            ->route('admin.queries.builder.index')
            ->with('status', __('query_builder_ui.feedback.query_deleted', ['label' => $label]));
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{query:string,test_query:string}
     */
    private function resolveQuerySql(array $validated): array
    {
        if ((string) ($validated['query_mode'] ?? 'sql') !== 'builder') {
            return [
                'query' => (string) ($validated['query'] ?? ''),
                'test_query' => (string) ($validated['test_query'] ?? ''),
            ];
        }

        return BuildQueryFromBuilderAction::handle($validated);
    }

    public function inspectSql(InspectQueryRequest $request): JsonResponse
    {
        TenantDatabaseGuard::ensureTenantConnection();

        $validation = ValidateSqlQueryAction::handle((string) $request->validated('query'));

        $this->auditLogger->success(
            action: 'query.inspect_sql',
            module: 'query_builder',
            subjectType: 'query_sql',
            subjectKey: null,
            message: __('query_builder_ui.audit.sql_inspection_executed'),
            meta: [
                'valid' => (bool) $validation['is_valid'],
                'bindings_count' => count((array) ($validation['bindings'] ?? [])),
            ],
            request: $request,
        );

        return response()->json([
            'valid' => (bool) $validation['is_valid'],
            'message' => (string) ($validation['message'] ?? ''),
            'bindings' => $validation['bindings'] ?? [],
        ]);
    }

    public function template(Request $request, int|string $query): StreamedResponse|RedirectResponse
    {
        TenantDatabaseGuard::ensureTenantConnection();

        $query = $this->findQuery($query);

        $templatePath = trim((string) ($query->report_template_path ?? ''));

        if ($templatePath === '' || ! Storage::disk('private')->exists($templatePath)) {
            $this->auditLogger->failure(
                action: 'query.template.download.failed',
                module: 'query_builder',
                subjectType: 'query',
                subjectKey: (string) $query->id,
                message: __('query_builder_ui.feedback.template_download_missing'),
                meta: [
                    'query_id' => (int) $query->id,
                ],
                request: $request,
            );

            return redirect()
                ->route('admin.queries.builder.edit', ['query' => $query->id])
                ->with('error', __('query_builder_ui.feedback.template_missing'));
        }

        $extension = trim((string) ($query->report_template_extension ?? ''));

        if ($extension === '') {
            $extension = pathinfo($templatePath, PATHINFO_EXTENSION);
        }

        $filenameBase = Str::slug((string) ($query->slug ?: $query->description ?: 'query-template'));

        if ($filenameBase === '') {
            $filenameBase = 'query-template';
        }

        $downloadFilename = $extension !== ''
            ? sprintf('%s.%s', $filenameBase, strtolower($extension))
            : $filenameBase;

        $this->auditLogger->success(
            action: 'query.template.download',
            module: 'query_builder',
            subjectType: 'query',
            subjectKey: (string) $query->id,
            message: __('query_builder_ui.feedback.template_downloaded'),
            meta: [
                'query_id' => (int) $query->id,
                'template_extension' => $extension,
            ],
            request: $request,
        );

        return response()->streamDownload(function () use ($templatePath): void {
            $stream = Storage::disk('private')->readStream($templatePath);

            if (! is_resource($stream)) {
                return;
            }

            fpassthru($stream);
            fclose($stream);
        }, $downloadFilename);
    }

    public function bindingSourceOptions(Request $request): JsonResponse
    {
        TenantDatabaseGuard::ensureTenantConnection();

        $search = trim((string) $request->query('q', ''));

        $sources = QueryBuilderSelectTable::query()
            ->where('is_active', true)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('table_name', 'like', "%{$search}%");
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'table_name', 'select_field', 'label_fields', 'search_fields'])
            ->map(static fn (QueryBuilderSelectTable $source): array => [
                'id' => (int) $source->id,
                'name' => (string) $source->name,
                'table_name' => (string) $source->table_name,
                'select_field' => (string) $source->select_field,
                'label_fields' => (array) ($source->label_fields ?? []),
                'search_fields' => (array) ($source->search_fields ?? []),
            ])
            ->values()
            ->all();

        $this->auditLogger->success(
            action: 'query.binding_source.list_options',
            module: 'query_builder',
            subjectType: 'query_binding_source',
            subjectKey: null,
            message: __('query_builder_ui.feedback.binding_source_options_listed'),
            meta: [
                'search' => $search,
                'count' => count($sources),
            ],
            request: $request,
        );

        return response()->json([
            'sources' => $sources,
        ]);
    }

    private function queryIdFromRequest(Request $request): int
    {
        $routeQuery = $request->route('query');

        if ($routeQuery instanceof Query) {
            return (int) $routeQuery->id;
        }

        if (is_numeric($routeQuery)) {
            return max(0, (int) $routeQuery);
        }

        return 0;
    }

    private function findQuery(int|string $query): Query
    {
        $queryId = (int) $query;

        if ($queryId <= 0) {
            abort(404);
        }

        return Query::query()->findOrFail($queryId);
    }

    /** @return array<string, mixed> */
    private function queryPayload(Query $query): array
    {
        return [
            'id' => $query->exists ? (int) $query->id : 0,
            'created_at' => $query->exists ? $query->created_at?->toISOString() : null,
            'updated_at' => $query->exists ? $query->updated_at?->toISOString() : null,
            'slug' => (string) ($query->slug ?? ''),
            'description' => (string) ($query->description ?? ''),
            'memo' => (string) ($query->memo ?? ''),
            'query_mode' => (string) ($query->query_mode ?? 'builder'),
            'output_mode' => (string) ($query->output_mode ?? 'table'),
            'report_data_source' => (string) ($query->report_data_source ?? 'query'),
            'report_output_format' => (string) ($query->report_output_format ?? 'same_format'),
            'report_template_filename' => (string) ($query->report_template_filename ?? ''),
            'report_template_extension' => (string) ($query->report_template_extension ?? ''),
            'report_template_size_kb' => $query->report_template_size_kb !== null ? (int) $query->report_template_size_kb : null,
            'table_name' => (string) ($query->table_name ?? ''),
            'all_fields' => (bool) ($query->all_fields ?? false),
            'distinct_select' => (bool) ($query->distinct_select ?? false),
            'query' => (string) ($query->query ?? ''),
            'test_query' => (string) ($query->test_query ?? ''),
            'selected_fields' => (array) ($query->selected_fields ?? []),
            'join_rows' => (array) ($query->join_rows ?? []),
            'where_rows' => (array) ($query->where_rows ?? []),
            'group_by' => (bool) ($query->group_by ?? false),
            'group_rows' => (array) ($query->group_rows ?? []),
            'aggregate_rows' => (array) ($query->aggregate_rows ?? []),
            'having_rows' => (array) ($query->having_rows ?? []),
            'binding_rows' => (array) ($query->binding_rows ?? []),
            'chart_config' => (array) ($query->chart_config ?? []),
            'is_active' => (bool) ($query->is_active ?? true),
        ];
    }

    private function currentUserId(): ?int
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        return (int) $user->id;
    }

    /** @return array<int, string> */
    private function deleteBlockersForQuery(Query $query): array
    {
        $messages = [];

        $permissions = AclPermission::query()
            ->where('query_id', $query->id)
            ->orderBy('route_name')
            ->orderBy('id')
            ->get(['id', 'route_name', 'description'])
            ->map(static function (AclPermission $permission): string {
                $routeName = trim((string) ($permission->route_name ?? ''));
                $description = trim((string) ($permission->description ?? ''));

                if ($routeName !== '') {
                    return $routeName;
                }

                if ($description !== '') {
                    return $description;
                }

                return '#'.(string) $permission->id;
            })
            ->values()
            ->all();

        if ($permissions !== []) {
            $messages[] = __('query_builder_ui.feedback.delete_blocked_permissions', [
                'permissions' => implode(', ', $permissions),
            ]);
        }

        return $messages;
    }

    /** @param array<int, string> $blockers */
    private function deleteBlockedMessage(array $blockers): string
    {
        $normalized = collect($blockers)
            ->map(static fn (mixed $message): string => trim((string) $message))
            ->filter(static fn (string $message): bool => $message !== '')
            ->values()
            ->all();

        if ($normalized === []) {
            return __('query_builder_ui.feedback.delete_blocked_default');
        }

        return implode(' ', $normalized);
    }

    private function storeReportTemplate(Query $query, UploadedFile $templateUpload): void
    {
        $storageDisk = Storage::disk('private');
        $existingTemplatePath = trim((string) ($query->report_template_path ?? ''));
        $extension = strtolower((string) $templateUpload->getClientOriginalExtension());
        $filename = pathinfo((string) $templateUpload->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = Str::slug($filename);

        if ($safeFilename === '') {
            $safeFilename = 'template';
        }

        $storedFilename = sprintf(
            '%s-%s.%s',
            $safeFilename,
            now()->format('YmdHis'),
            $extension,
        );

        $storedPath = $templateUpload->storeAs('query-reports/templates', $storedFilename, 'private');

        if ($storedPath === false) {
            return;
        }

        if ($existingTemplatePath !== '' && $existingTemplatePath !== $storedPath && $storageDisk->exists($existingTemplatePath)) {
            $storageDisk->delete($existingTemplatePath);
        }

        $query->report_template_path = $storedPath;
        $query->report_template_filename = (string) $templateUpload->getClientOriginalName();
        $query->report_template_extension = $extension;
        $templateSize = (int) ($templateUpload->getSize() ?? 0);
        $query->report_template_size_kb = (int) ceil($templateSize / 1024);
    }

    /** @return array<string, array{fields: array<int, string>, relationships: array<int, string>}> */
    private function generateDatabaseStructure(): array
    {
        TenantDatabaseGuard::ensureTenantConnection();

        $structure = [];

        foreach ($this->modelClassLocator->all() as $className) {
            /** @var Model $model */
            $model = new $className;
            $tableName = (string) $model->getTable();

            if ($tableName === '' || isset($structure[$tableName]) || ! Schema::hasTable($tableName)) {
                continue;
            }

            $structure[$tableName] = [
                'fields' => array_values(array_filter(
                    Schema::getColumnListing($tableName),
                    static fn (mixed $column): bool => is_string($column) && $column !== '',
                )),
                'relationships' => $this->getModelRelationships($model),
            ];
        }

        ksort($structure);

        return $structure;
    }

    /** @return array<int, string> */
    private function getModelRelationships(Model $model): array
    {
        $relationships = [];
        $methods = (new ReflectionClass($model))->getMethods();

        foreach ($methods as $method) {
            if ($method->class !== get_class($model)) {
                continue;
            }

            try {
                $result = $model->{$method->name}();

                if ($result instanceof Relation) {
                    $relatedTable = (string) $result->getRelated()->getTable();

                    if ($relatedTable !== '') {
                        $relationships[] = $relatedTable;
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return array_values(array_unique($relationships));
    }

    /**
     * @param  array<string, array{fields: array<int, string>, relationships: array<int, string>}>  $databaseStructure
     * @return array<int, array{value:string,title:string}>
     */
    private function tableOptions(array $databaseStructure): array
    {
        return collect(array_keys($databaseStructure))
            ->map(static fn (string $table): array => [
                'value' => $table,
                'title' => $table,
            ])
            ->values()
            ->all();
    }
}
