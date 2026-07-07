<?php

namespace App\Http\Controllers\Admin\Run\Query;

use App\Actions\Admin\Base\Query\RunSqlQueryAction;
use App\Actions\Admin\Base\Query\ValidateSqlQueryAction;
use App\Http\Controllers\Controller;
use App\Models\Query\Query;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantDatabaseGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class QueryChartController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function show(int $query, Request $request): Response
    {
        TenantDatabaseGuard::ensureTenantConnection();
        $query = $this->queryForActiveApplication($query);

        if ((string) ($query->output_mode ?? 'table') !== 'chart') {
            abort(404);
        }

        $returnTo = $this->normalizeReturnTo(
            (string) $request->query('returnTo', route('admin.run.dashboard')),
        );
        $bindings = $this->normalizeInputBindings($request->query('bindings'));

        $tablePreviewRouteParameters = [
            'query' => (int) $query->id,
            '__force_table' => 1,
            'returnTo' => $returnTo,
        ];

        foreach ($bindings as $key => $value) {
            if (! is_scalar($value) || $this->isMissingValue($value)) {
                continue;
            }

            $tablePreviewRouteParameters[$key] = (string) $value;
        }

        $initialPreview = null;
        $initialPreviewMeta = null;
        $initialPreviewError = '';

        $chartConfig = is_array($query->chart_config)
            ? $query->chart_config
            : [];

        if ($chartConfig !== []) {
            try {
                $payload = $this->buildPreviewPayload($query, $chartConfig, $bindings);
                $initialPreview = $payload['preview'] ?? null;
                $initialPreviewMeta = $payload['meta'] ?? null;
            } catch (Throwable $throwable) {
                $initialPreviewError = trim($throwable->getMessage());
            }
        }

        $this->auditLogger->success(
            action: 'run.query.chart.open',
            module: 'query_runner',
            subjectType: 'query',
            subjectKey: (string) $query->id,
            message: __('query_builder_ui.audit.query_chart_opened'),
            meta: [
                'has_initial_preview_error' => $initialPreviewError !== '',
                'bindings_count' => count($bindings),
            ],
            request: $request,
        );

        return Inertia::render('Admin/Query/QueryChartView', [
            'query' => [
                'id' => (int) $query->id,
                'description' => (string) ($query->description ?? ''),
                'output_mode' => (string) ($query->output_mode ?? 'table'),
                'memo' => (string) ($query->memo ?? ''),
                'chart_group_id' => $query->chart_group_id !== null ? (int) $query->chart_group_id : null,
                'chart_config' => $chartConfig,
            ],
            'returnTo' => $returnTo,
            'tablePreviewUrl' => route('admin.run.queries.show', $tablePreviewRouteParameters),
            'bindings' => $bindings,
            'initialPreview' => $initialPreview,
            'initialPreviewMeta' => $initialPreviewMeta,
            'initialPreviewError' => $initialPreviewError,
        ]);
    }

    public function preview(Request $request, int $query): JsonResponse
    {
        TenantDatabaseGuard::ensureTenantConnection();
        $query = $this->queryForActiveApplication($query);

        if ((string) ($query->output_mode ?? 'table') !== 'chart') {
            abort(404);
        }

        $validated = $request->validate([
            'config' => ['nullable', 'array'],
            'bindings' => ['nullable'],
        ]);

        $config = is_array($validated['config'] ?? null)
            ? $validated['config']
            : (is_array($query->chart_config) ? $query->chart_config : []);
        $bindings = $this->normalizeInputBindings($validated['bindings'] ?? []);

        try {
            $payload = $this->buildPreviewPayload($query, $config, $bindings);

            $this->auditLogger->success(
                action: 'run.query.chart.preview',
                module: 'query_runner',
                subjectType: 'query',
                subjectKey: (string) $query->id,
                message: __('query_builder_ui.audit.query_chart_preview_built'),
                meta: [
                    'bindings_count' => count($bindings),
                    'series_count' => (int) data_get($payload, 'meta.series_count', 0),
                ],
                request: $request,
            );

            return response()->json($payload);
        } catch (Throwable $throwable) {
            $this->auditLogger->failure(
                action: 'run.query.chart.preview.failed',
                module: 'query_runner',
                subjectType: 'query',
                subjectKey: (string) $query->id,
                message: trim($throwable->getMessage()) !== ''
                    ? trim($throwable->getMessage())
                    : __('query_builder_ui.chart_view.preview_build_failed'),
                meta: [
                    'bindings_count' => count($bindings),
                ],
                request: $request,
            );

            return response()->json([
                'message' => trim($throwable->getMessage()) !== ''
                    ? trim($throwable->getMessage())
                    : __('query_builder_ui.chart_view.preview_build_failed'),
            ], 422);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $providedBindings
     * @return array<string, mixed>
     */
    private function buildPreviewPayload(Query $query, array $config, array $providedBindings): array
    {
        if (! (bool) $query->is_active) {
            throw new \RuntimeException(__('query_builder_ui.runtime.query_inactive'));
        }

        $validation = ValidateSqlQueryAction::handle((string) ($query->query ?? ''));

        if (! (bool) ($validation['is_valid'] ?? false)) {
            throw new \RuntimeException(
                (string) ($validation['message'] ?? __('query_builder_ui.runtime.query_cannot_run')),
            );
        }

        $requiredBindings = array_values((array) ($validation['bindings'] ?? []));
        $resolvedBindings = $this->resolveBindings($requiredBindings, $providedBindings);
        $missingBindings = $this->missingBindings($requiredBindings, $resolvedBindings);

        if ($missingBindings !== []) {
            throw new \RuntimeException(
                __('query_builder_ui.runtime.missing_bindings_with_list', [
                    'bindings' => implode(', ', $missingBindings),
                ]),
            );
        }

        $result = RunSqlQueryAction::handleAll(
            (string) ($validation['sql'] ?? ''),
            $resolvedBindings,
        );

        $rows = (array) ($result['data'] ?? []);
        $columns = (array) ($result['columns'] ?? []);
        $normalizedConfig = $this->normalizePreviewConfig($config, $columns);
        $aggregatedRows = $this->aggregateRows($rows, $normalizedConfig);
        $preview = $this->buildPreviewFromAggregatedRows($aggregatedRows, $normalizedConfig);

        $groupCountTotal = (int) ($preview['group_count_total'] ?? 0);
        $groupCountShown = count((array) ($preview['labels'] ?? []));

        return [
            'config' => $normalizedConfig,
            'preview' => $preview,
            'meta' => [
                'sample_count' => $groupCountShown,
                'total_rows' => $groupCountTotal,
                'truncated' => $groupCountTotal > $groupCountShown || (bool) ($result['truncated'] ?? false),
                'source_row_count' => (int) ($result['total'] ?? 0),
                'series_count' => count((array) ($preview['series'] ?? [])),
            ],
        ];
    }

    private function queryForActiveApplication(int $queryId): Query
    {
        return Query::query()->findOrFail($queryId);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<int, string>  $columns
     * @return array<string, mixed>
     */
    private function normalizePreviewConfig(array $config, array $columns): array
    {
        $builder = is_array($config['builder'] ?? null) ? $config['builder'] : [];
        $dataset = is_array($builder['dataset'] ?? null) ? $builder['dataset'] : [];
        $chart = is_array($builder['chart'] ?? null) ? $builder['chart'] : [];
        $presentation = is_array($builder['presentation'] ?? null) ? $builder['presentation'] : [];

        $xField = trim((string) ($dataset['x_field'] ?? ''));

        if ($xField === '') {
            throw new \RuntimeException(__('query_builder_ui.chart_view.x_field_required'));
        }

        $aggregate = strtolower(trim((string) ($dataset['aggregate'] ?? 'count')));
        if (! in_array($aggregate, ['count', 'sum', 'avg', 'min', 'max'], true)) {
            $aggregate = 'count';
        }

        $resolvedXField = $this->resolveConfiguredColumn($xField, $columns);

        if ($resolvedXField === null) {
            throw new \RuntimeException(__('query_builder_ui.chart_view.x_field_missing'));
        }

        $metricField = trim((string) ($dataset['metric_field'] ?? ''));
        $resolvedMetricField = null;

        if ($aggregate !== 'count') {
            if ($metricField === '') {
                throw new \RuntimeException(__('query_builder_ui.chart_view.metric_field_required'));
            }

            $resolvedMetricField = $this->resolveConfiguredColumn($metricField, $columns);

            if ($resolvedMetricField === null) {
                throw new \RuntimeException(__('query_builder_ui.chart_view.metric_field_missing'));
            }
        }

        $seriesField = trim((string) ($dataset['series_field'] ?? ''));
        $resolvedSeriesField = null;

        if ($seriesField !== '') {
            $resolvedSeriesField = $this->resolveConfiguredColumn($seriesField, $columns);

            if ($resolvedSeriesField === null) {
                throw new \RuntimeException(__('query_builder_ui.chart_view.series_field_missing'));
            }
        }

        $chartType = strtolower(trim((string) ($chart['type'] ?? 'bar')));

        if (! in_array($chartType, ['bar', 'line', 'bar3d', 'line3d', 'bar3d_webgl', 'line3d_webgl', 'pie', 'doughnut'], true)) {
            $chartType = 'bar';
        }

        $orientation = strtolower(trim((string) ($chart['orientation'] ?? 'vertical')));

        if (! in_array($orientation, ['vertical', 'horizontal'], true)) {
            $orientation = 'vertical';
        }

        $sortDirection = strtolower(trim((string) ($dataset['sort_direction'] ?? 'desc')));

        if (! in_array($sortDirection, ['asc', 'desc'], true)) {
            $sortDirection = 'desc';
        }

        $limit = max(1, min(500, (int) ($dataset['limit'] ?? 25)));

        return [
            'chart_type' => $chartType,
            'orientation' => $orientation,
            'stacked' => (bool) ($chart['stacked'] ?? false),
            'show_legend' => (bool) ($chart['show_legend'] ?? true),
            'x_field' => $resolvedXField,
            'metric_field' => $resolvedMetricField,
            'aggregate' => $aggregate,
            'series_field' => $resolvedSeriesField,
            'sort_direction' => $sortDirection,
            'limit' => $limit,
            'title' => (string) ($presentation['title'] ?? ''),
            'subtitle' => (string) ($presentation['subtitle'] ?? ''),
            'show_source_table_button' => (bool) ($presentation['show_source_table_button'] ?? true),
            'allow_chart_type_change' => (bool) ($presentation['allow_chart_type_change'] ?? true),
            'show_pdf_print_button' => (bool) ($presentation['show_pdf_print_button'] ?? false),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $config
     * @return array<string, array<string, float>>
     */
    private function aggregateRows(array $rows, array $config): array
    {
        $aggregate = (string) ($config['aggregate'] ?? 'count');
        $xField = (string) ($config['x_field'] ?? '');
        $seriesField = $config['series_field'];
        $metricField = $config['metric_field'];
        $states = [];

        foreach ($rows as $row) {
            $xLabel = $this->labelValue($row[$xField] ?? null);
            $seriesLabel = is_string($seriesField)
                ? $this->labelValue($row[$seriesField] ?? null)
                : '__default__';

            if (! isset($states[$xLabel][$seriesLabel])) {
                $states[$xLabel][$seriesLabel] = [
                    'sum' => 0.0,
                    'count' => 0,
                    'min' => null,
                    'max' => null,
                ];
            }

            if ($aggregate === 'count') {
                $states[$xLabel][$seriesLabel]['count']++;

                continue;
            }

            $metricValue = is_string($metricField)
                ? $this->toNumeric($row[$metricField] ?? null)
                : null;

            if ($metricValue === null) {
                continue;
            }

            $states[$xLabel][$seriesLabel]['sum'] += $metricValue;
            $states[$xLabel][$seriesLabel]['count']++;
            $states[$xLabel][$seriesLabel]['min'] = $states[$xLabel][$seriesLabel]['min'] === null
                ? $metricValue
                : min((float) $states[$xLabel][$seriesLabel]['min'], $metricValue);
            $states[$xLabel][$seriesLabel]['max'] = $states[$xLabel][$seriesLabel]['max'] === null
                ? $metricValue
                : max((float) $states[$xLabel][$seriesLabel]['max'], $metricValue);
        }

        $aggregatedRows = [];

        foreach ($states as $xLabel => $seriesRows) {
            foreach ($seriesRows as $seriesLabel => $state) {
                $value = 0.0;

                if ($aggregate === 'count') {
                    $value = (float) ($state['count'] ?? 0);
                } elseif ($aggregate === 'sum') {
                    $value = (float) ($state['sum'] ?? 0.0);
                } elseif ($aggregate === 'avg') {
                    $count = (int) ($state['count'] ?? 0);
                    $value = $count > 0
                        ? (float) ($state['sum'] ?? 0.0) / $count
                        : 0.0;
                } elseif ($aggregate === 'min') {
                    $value = $state['min'] !== null ? (float) $state['min'] : 0.0;
                } elseif ($aggregate === 'max') {
                    $value = $state['max'] !== null ? (float) $state['max'] : 0.0;
                }

                $aggregatedRows[$xLabel][$seriesLabel] = $value;
            }
        }

        return $aggregatedRows;
    }

    /**
     * @param  array<string, array<string, float>>  $aggregatedRows
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function buildPreviewFromAggregatedRows(array $aggregatedRows, array $config): array
    {
        $sortDirection = (string) ($config['sort_direction'] ?? 'desc');
        $limit = (int) ($config['limit'] ?? 25);
        $limit = max(1, min(500, $limit));

        $totals = [];

        foreach ($aggregatedRows as $label => $seriesRows) {
            $totals[] = [
                'label' => $label,
                'total' => array_sum($seriesRows),
            ];
        }

        usort($totals, static function (array $left, array $right) use ($sortDirection): int {
            $leftTotal = (float) ($left['total'] ?? 0);
            $rightTotal = (float) ($right['total'] ?? 0);

            if ($leftTotal === $rightTotal) {
                return strnatcasecmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
            }

            if ($sortDirection === 'asc') {
                return $leftTotal <=> $rightTotal;
            }

            return $rightTotal <=> $leftTotal;
        });

        $groupCountTotal = count($totals);
        $labels = array_map(
            static fn (array $item): string => (string) ($item['label'] ?? ''),
            array_slice($totals, 0, $limit),
        );

        $seriesNames = [];

        foreach ($aggregatedRows as $seriesRows) {
            foreach (array_keys($seriesRows) as $seriesName) {
                $seriesNames[(string) $seriesName] = true;
            }
        }

        if ($seriesNames === []) {
            $seriesNames['__default__'] = true;
        }

        $seriesNameList = array_keys($seriesNames);
        natcasesort($seriesNameList);
        $seriesNameList = array_values($seriesNameList);
        $series = [];

        foreach ($seriesNameList as $seriesName) {
            $seriesData = [];

            foreach ($labels as $label) {
                $seriesData[] = (float) ($aggregatedRows[$label][$seriesName] ?? 0.0);
            }

            $series[] = [
                'name' => $seriesName === '__default__' ? 'Totaal' : $seriesName,
                'key' => $seriesName,
                'data' => $seriesData,
            ];
        }

        $tableRows = [];

        foreach ($labels as $index => $label) {
            $row = ['label' => $label];

            foreach ($series as $seriesRow) {
                $row[(string) $seriesRow['name']] = $seriesRow['data'][$index] ?? 0;
            }

            $tableRows[] = $row;
        }

        return [
            'labels' => $labels,
            'series' => $series,
            'table_rows' => $tableRows,
            'group_count_total' => $groupCountTotal,
        ];
    }

    /**
     * @param  array<int, string>  $requiredBindings
     * @param  array<string, mixed>  $providedBindings
     * @return array<string, mixed>
     */
    private function resolveBindings(array $requiredBindings, array $providedBindings): array
    {
        $resolved = [];

        foreach ($requiredBindings as $requiredBinding) {
            $binding = trim((string) $requiredBinding);

            if ($binding === '') {
                continue;
            }

            if (array_key_exists($binding, $providedBindings) && ! $this->isMissingValue($providedBindings[$binding])) {
                $resolved[$binding] = $providedBindings[$binding];

                continue;
            }

            $systemValue = $this->resolveSystemBindingValue($binding);

            if (! $this->isMissingValue($systemValue)) {
                $resolved[$binding] = $systemValue;

                continue;
            }

            $resolved[$binding] = null;
        }

        return $resolved;
    }

    /**
     * @param  array<int, string>  $requiredBindings
     * @param  array<string, mixed>  $resolvedBindings
     * @return array<int, string>
     */
    private function missingBindings(array $requiredBindings, array $resolvedBindings): array
    {
        return collect($requiredBindings)
            ->map(static fn (mixed $binding): string => trim((string) $binding))
            ->filter(static fn (string $binding): bool => $binding !== '')
            ->unique()
            ->values()
            ->filter(function (string $binding) use ($resolvedBindings): bool {
                if (! array_key_exists($binding, $resolvedBindings)) {
                    return true;
                }

                return $this->isMissingValue($resolvedBindings[$binding]);
            })
            ->values()
            ->all();
    }

    private function resolveSystemBindingValue(string $binding): mixed
    {
        $normalized = strtoupper(trim($binding));

        if ($normalized === 'CURRENTSCHOOLYEAR') {
            return (int) now()->format('Y');
        }

        return null;
    }

    private function isMissingValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            return true;
        }

        return false;
    }

    private function labelValue(mixed $value): string
    {
        $label = trim((string) ($value ?? ''));

        return $label === '' ? '(leeg)' : $label;
    }

    private function toNumeric(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        if ($normalized === '') {
            return null;
        }

        if (str_contains($normalized, ',') && ! str_contains($normalized, '.')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    /**
     * @param  array<int, string>  $availableColumns
     */
    private function resolveConfiguredColumn(string $requested, array $availableColumns): ?string
    {
        $normalizedRequested = trim($requested);

        if ($normalizedRequested === '') {
            return null;
        }

        if (in_array($normalizedRequested, $availableColumns, true)) {
            return $normalizedRequested;
        }

        $requestedLower = strtolower($normalizedRequested);

        foreach ($availableColumns as $column) {
            if (strtolower((string) $column) === $requestedLower) {
                return (string) $column;
            }
        }

        $requestedUnqualified = $this->unqualifyColumn($normalizedRequested);

        foreach ($availableColumns as $column) {
            $candidate = (string) $column;

            if (strtolower($this->unqualifyColumn($candidate)) === strtolower($requestedUnqualified)) {
                return $candidate;
            }
        }

        return null;
    }

    private function unqualifyColumn(string $column): string
    {
        $trimmed = trim($column, " \t\n\r\0\x0B`");

        if ($trimmed === '') {
            return '';
        }

        if (str_contains($trimmed, '.')) {
            $parts = explode('.', $trimmed);
            $trimmed = (string) end($parts);
        }

        return trim($trimmed, " \t\n\r\0\x0B`");
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeInputBindings(mixed $bindings): array
    {
        if (is_string($bindings)) {
            try {
                $decoded = json_decode($bindings, true, 512, JSON_THROW_ON_ERROR);
                $bindings = is_array($decoded) ? $decoded : [];
            } catch (Throwable) {
                return [];
            }
        }

        if (! is_array($bindings)) {
            return [];
        }

        return collect($bindings)
            ->mapWithKeys(static function (mixed $value, mixed $key): array {
                $bindingName = trim((string) $key);

                if ($bindingName === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $bindingName) !== 1) {
                    return [];
                }

                if (is_array($value) || is_object($value)) {
                    return [];
                }

                return [$bindingName => $value];
            })
            ->all();
    }

    private function normalizeReturnTo(string $returnTo): string
    {
        $trimmed = trim($returnTo);

        if ($trimmed === '') {
            return route('admin.run.dashboard');
        }

        if (str_starts_with($trimmed, '/')) {
            return $trimmed;
        }

        $parts = parse_url($trimmed);

        if (! is_array($parts)) {
            return route('admin.run.dashboard');
        }

        $path = trim((string) Arr::get($parts, 'path', ''));

        if ($path === '' || ! str_starts_with($path, '/')) {
            return route('admin.run.dashboard');
        }

        $query = trim((string) Arr::get($parts, 'query', ''));

        return $query !== '' ? $path.'?'.$query : $path;
    }
}
