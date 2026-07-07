<?php

namespace App\Services\Admin\Cms;

use App\Models\Cms\CmsVisit;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CmsVisitStatisticsService
{
    /**
     * @param  array<int, string>  $paths
     * @return array<string, mixed>
     */
    public function forPaths(array $paths, mixed $fromValue = null, mixed $toValue = null): array
    {
        $paths = $this->cleanPaths($paths);
        [$from, $to] = $this->dateRange($fromValue, $toValue);

        $baseQuery = CmsVisit::query()
            ->whereIn('path', $paths)
            ->whereBetween('created_at', [$from->toDateTimeString(), $to->toDateTimeString()]);

        $summary = (clone $baseQuery)
            ->selectRaw("COUNT(*) as pageviews, COUNT(DISTINCT COALESCE(cms_visitor_id, NULLIF(uuid, ''), NULLIF(ip_hash, ''))) as unique_visitors, MAX(created_at) as last_visit_at")
            ->first();

        $monthlyRows = (clone $baseQuery)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as pageviews, COUNT(DISTINCT COALESCE(cms_visitor_id, NULLIF(uuid, ''), NULLIF(ip_hash, ''))) as unique_visitors")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $campaigns = (clone $baseQuery)
            ->where(function (Builder $query): void {
                $query
                    ->whereNotNull('utm_source')
                    ->where('utm_source', '!=', '')
                    ->orWhere(function (Builder $query): void {
                        $query->whereNotNull('utm_medium')->where('utm_medium', '!=', '');
                    })
                    ->orWhere(function (Builder $query): void {
                        $query->whereNotNull('utm_campaign')->where('utm_campaign', '!=', '');
                    });
            })
            ->selectRaw('utm_source, utm_medium, utm_campaign, COUNT(*) as visits, MAX(created_at) as last_visit_at')
            ->groupBy('utm_source', 'utm_medium', 'utm_campaign')
            ->orderByDesc('visits')
            ->limit(25)
            ->get()
            ->map(fn ($row): array => [
                'source' => (string) ($row->utm_source ?? ''),
                'medium' => (string) ($row->utm_medium ?? ''),
                'campaign' => (string) ($row->utm_campaign ?? ''),
                'visits' => (int) $row->visits,
                'lastVisitAt' => $row->last_visit_at ? Carbon::parse($row->last_visit_at)->toDateTimeString() : null,
            ])
            ->values();

        $referrers = $this->externalReferrers(clone $baseQuery);

        return [
            'paths' => $paths,
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'summary' => [
                'pageviews' => (int) ($summary?->pageviews ?? 0),
                'uniqueVisitors' => (int) ($summary?->unique_visitors ?? 0),
                'lastVisitAt' => $summary?->last_visit_at ? Carbon::parse($summary->last_visit_at)->toDateTimeString() : null,
                'externalReferrerCount' => $referrers->count(),
                'campaignVisitCount' => $campaigns->sum('visits'),
            ],
            'monthly' => $this->monthlyStatistics($from, $to, $monthlyRows),
            'referrers' => $referrers->values(),
            'campaigns' => $campaigns,
        ];
    }

    /**
     * @param  array<int, string>  $paths
     * @return array<int, string>
     */
    private function cleanPaths(array $paths): array
    {
        return collect($paths)
            ->map(fn (mixed $path): string => '/'.trim((string) $path, '/'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function dateRange(mixed $fromValue = null, mixed $toValue = null): array
    {
        $to = $this->statisticsDate($toValue, Carbon::today())->endOfDay();
        $from = $this->statisticsDate($fromValue, $to->copy()->subYear()->startOfDay())->startOfDay();

        if ($from->greaterThan($to)) {
            $from = $to->copy()->subYear()->startOfDay();
        }

        return [$from, $to];
    }

    private function statisticsDate(mixed $value, Carbon $fallback): Carbon
    {
        if (is_string($value) && trim($value) !== '') {
            return Carbon::parse($value);
        }

        return $fallback->copy();
    }

    /**
     * @param  Collection<string, mixed>  $monthlyRows
     * @return array<int, array{month: string, pageviews: int, uniqueVisitors: int}>
     */
    private function monthlyStatistics(Carbon $from, Carbon $to, Collection $monthlyRows): array
    {
        $monthly = [];
        $period = CarbonPeriod::create($from->copy()->startOfMonth(), '1 month', $to->copy()->startOfMonth());

        foreach ($period as $month) {
            $key = $month->format('Y-m');
            $row = $monthlyRows->get($key);

            $monthly[] = [
                'month' => $key,
                'pageviews' => (int) ($row?->pageviews ?? 0),
                'uniqueVisitors' => (int) ($row?->unique_visitors ?? 0),
            ];
        }

        return $monthly;
    }

    /**
     * @return Collection<int, array{host: string, visits: int, lastVisitAt: string|null}>
     */
    private function externalReferrers(Builder $query): Collection
    {
        $internalHost = $this->normalizeHost(request()->getHost());
        $rows = $query
            ->whereNotNull('referer')
            ->where('referer', '!=', '')
            ->selectRaw('referer, COUNT(*) as visits, MAX(created_at) as last_visit_at')
            ->groupBy('referer')
            ->get();

        $referrers = [];

        foreach ($rows as $row) {
            $host = $this->normalizeHost(parse_url((string) $row->referer, PHP_URL_HOST));

            if (! $host || $host === $internalHost) {
                continue;
            }

            if (! isset($referrers[$host])) {
                $referrers[$host] = [
                    'host' => $host,
                    'visits' => 0,
                    'lastVisitAt' => null,
                ];
            }

            $lastVisitAt = $row->last_visit_at ? Carbon::parse($row->last_visit_at)->toDateTimeString() : null;
            $referrers[$host]['visits'] += (int) $row->visits;

            if ($lastVisitAt && (! $referrers[$host]['lastVisitAt'] || $lastVisitAt > $referrers[$host]['lastVisitAt'])) {
                $referrers[$host]['lastVisitAt'] = $lastVisitAt;
            }
        }

        return collect($referrers)
            ->sortByDesc('visits')
            ->take(25)
            ->values();
    }

    private function normalizeHost(mixed $host): ?string
    {
        if (! is_string($host)) {
            return null;
        }

        $host = strtolower(trim($host));

        if ($host === '') {
            return null;
        }

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }
}
