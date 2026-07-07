<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Actions\Admin\Cms\BuildCmsContentStatisticsUrlsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cms\CmsContentStatisticsRequest;
use App\Services\Admin\Cms\CmsVisitStatisticsService;
use App\Services\Admin\Cms\GoogleSearchConsoleService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class CmsContentStatisticsController extends Controller
{
    public function visits(
        CmsContentStatisticsRequest $request,
        BuildCmsContentStatisticsUrlsAction $urlBuilder,
        CmsVisitStatisticsService $statistics,
    ): JsonResponse {
        $validated = $request->validated();
        $urlPayload = $urlBuilder->handle((string) $validated['content_type'], (int) $validated['record_id']);

        return response()->json([
            'urls' => $urlPayload,
            'statistics' => $statistics->forPaths(
                $urlPayload['paths'],
                $validated['from'] ?? null,
                $validated['to'] ?? null,
            ),
        ]);
    }

    public function searchConsole(
        CmsContentStatisticsRequest $request,
        BuildCmsContentStatisticsUrlsAction $urlBuilder,
        GoogleSearchConsoleService $searchConsole,
    ): JsonResponse {
        $validated = $request->validated();
        $urlPayload = $urlBuilder->handle((string) $validated['content_type'], (int) $validated['record_id']);
        [$from, $to] = $this->dateRange($validated['from'] ?? null, $validated['to'] ?? null);

        return response()->json([
            'urls' => $urlPayload,
            'searchConsole' => $searchConsole->forUrls($urlPayload['urls'], $from, $to),
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function dateRange(mixed $fromValue = null, mixed $toValue = null): array
    {
        $to = is_string($toValue) && trim($toValue) !== ''
            ? Carbon::parse($toValue)->endOfDay()
            : Carbon::today()->endOfDay();
        $from = is_string($fromValue) && trim($fromValue) !== ''
            ? Carbon::parse($fromValue)->startOfDay()
            : $to->copy()->subYear()->startOfDay();

        if ($from->greaterThan($to)) {
            $from = $to->copy()->subYear()->startOfDay();
        }

        return [$from, $to];
    }
}
