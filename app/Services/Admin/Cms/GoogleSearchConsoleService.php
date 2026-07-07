<?php

namespace App\Services\Admin\Cms;

use App\Support\PublicSite\CmsSearchConsoleSettings;
use App\Support\Tenancy\TenantContext;
use Carbon\Carbon;
use Google\Client;
use Google\Service\Exception as GoogleServiceException;
use Google\Service\SearchConsole;
use Google\Service\SearchConsole\ApiDimensionFilter;
use Google\Service\SearchConsole\ApiDimensionFilterGroup;
use Google\Service\SearchConsole\IndexStatusInspectionResult;
use Google\Service\SearchConsole\InspectUrlIndexRequest;
use Google\Service\SearchConsole\SearchAnalyticsQueryRequest;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class GoogleSearchConsoleService
{
    private ?SearchConsole $searchConsole = null;

    public function __construct(private readonly CmsSearchConsoleSettings $settings) {}

    /**
     * @param  array<int, string>  $urls
     * @return array<string, mixed>
     */
    public function forUrls(array $urls, Carbon $from, Carbon $to): array
    {
        $absoluteUrls = $this->absoluteUrls($urls);
        $response = $this->emptyResponse($absoluteUrls, $from, $to);

        if (! $this->settings->enabled()) {
            $response['message'] = __('cms_admin_ui.statistics.search_console.disabled');

            return $response;
        }

        if (! $this->isConfigured()) {
            $response['message'] = __('cms_admin_ui.statistics.search_console.not_configured');

            return $response;
        }

        if ($absoluteUrls === []) {
            $response['message'] = __('cms_admin_ui.statistics.search_console.no_urls');

            return $response;
        }

        try {
            $analytics = collect($absoluteUrls)
                ->map(fn (string $url): array => $this->analyticsForUrl($url, $from, $to))
                ->all();

            $inspections = collect($absoluteUrls)
                ->map(fn (string $url): array => $this->inspectionForUrl($url))
                ->all();

            $this->settings->markSuccess();

            return [
                ...$response,
                'configured' => true,
                'available' => true,
                'message' => null,
                'summary' => $this->combinedSummary($analytics),
                'queries' => $this->combinedQueries($analytics),
                'inspections' => $inspections,
            ];
        } catch (Throwable $exception) {
            report($exception);
            $message = $this->exceptionMessage($exception);
            $this->settings->markError($message);

            return [
                ...$response,
                'configured' => true,
                'message' => $message,
            ];
        }
    }

    /**
     * @return array{ok: bool, message: string|null}
     */
    public function testConnection(): array
    {
        if (! $this->settings->enabled() || ! $this->isConfigured()) {
            return ['ok' => false, 'message' => __('cms_admin_ui.statistics.search_console.not_configured')];
        }

        try {
            $request = new SearchAnalyticsQueryRequest;
            $request->setStartDate(Carbon::today()->subDays(7)->toDateString());
            $request->setEndDate(Carbon::today()->toDateString());
            $request->setRowLimit(1);

            $this->searchConsole()->searchanalytics->query($this->settings->siteUrl(), $request);
            $this->settings->markSuccess();

            return ['ok' => true, 'message' => null];
        } catch (Throwable $exception) {
            report($exception);
            $message = $this->exceptionMessage($exception);
            $this->settings->markError($message);

            return ['ok' => false, 'message' => $message];
        }
    }

    public function oauthClient(string $redirectUri): Client
    {
        $client = new Client;
        $client->setApplicationName(config('app.name').' Search Console');
        $client->setClientId((string) $this->settings->clientId());
        $client->setClientSecret((string) $this->settings->clientSecret());
        $client->setRedirectUri($redirectUri);
        $client->addScope(SearchConsole::WEBMASTERS_READONLY);
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        return $client;
    }

    /**
     * @param  array<int, string>  $urls
     * @return array<int, string>
     */
    private function absoluteUrls(array $urls): array
    {
        return collect($urls)
            ->map(fn (mixed $url): string => trim((string) $url))
            ->filter(fn (string $url): bool => str_starts_with($url, 'http://') || str_starts_with($url, 'https://'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $absoluteUrls
     * @return array<string, mixed>
     */
    private function emptyResponse(array $absoluteUrls, Carbon $from, Carbon $to): array
    {
        return [
            'enabled' => $this->settings->enabled(),
            'configured' => false,
            'available' => false,
            'message' => null,
            'siteUrl' => $this->settings->siteUrl(),
            'urls' => $absoluteUrls,
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'summary' => [
                'clicks' => 0,
                'impressions' => 0,
                'ctr' => 0.0,
                'position' => null,
            ],
            'queries' => [],
            'inspections' => [],
        ];
    }

    private function isConfigured(): bool
    {
        return $this->settings->siteUrl() !== ''
            && $this->settings->hasOAuthClient()
            && $this->settings->hasOauthToken();
    }

    /**
     * @return array{summary: array{clicks: int, impressions: int, ctr: float, position: float|null}, queries: array<int, array{query: string, clicks: int, impressions: int, ctr: float, position: float|null}>}
     */
    private function analyticsForUrl(string $url, Carbon $from, Carbon $to): array
    {
        $cacheKey = 'cms:gsc:analytics:'.TenantContext::siteId().':'.sha1($this->settings->siteUrl().'|'.$url.'|'.$from->toDateString().'|'.$to->toDateString());

        return Cache::remember($cacheKey, $this->settings->analyticsCacheSeconds(), function () use ($url, $from, $to): array {
            return [
                'summary' => $this->analyticsSummaryForUrl($url, $from, $to),
                'queries' => $this->analyticsQueriesForUrl($url, $from, $to),
            ];
        });
    }

    /**
     * @return array{clicks: int, impressions: int, ctr: float, position: float|null}
     */
    private function analyticsSummaryForUrl(string $url, Carbon $from, Carbon $to): array
    {
        $request = $this->searchAnalyticsRequest($url, $from, $to);
        $request->setRowLimit(1);

        $rows = $this->searchConsole()->searchanalytics->query($this->settings->siteUrl(), $request)->getRows() ?? [];
        $row = $rows[0] ?? null;

        return [
            'clicks' => (int) ($row?->getClicks() ?? 0),
            'impressions' => (int) ($row?->getImpressions() ?? 0),
            'ctr' => (float) ($row?->getCtr() ?? 0),
            'position' => $row ? (float) $row->getPosition() : null,
        ];
    }

    /**
     * @return array<int, array{query: string, clicks: int, impressions: int, ctr: float, position: float|null}>
     */
    private function analyticsQueriesForUrl(string $url, Carbon $from, Carbon $to): array
    {
        $request = $this->searchAnalyticsRequest($url, $from, $to);
        $request->setDimensions(['query']);
        $request->setRowLimit($this->settings->queryLimit());

        $rows = $this->searchConsole()->searchanalytics->query($this->settings->siteUrl(), $request)->getRows() ?? [];

        return collect($rows)
            ->map(fn ($row): array => [
                'query' => (string) ($row->getKeys()[0] ?? ''),
                'clicks' => (int) ($row->getClicks() ?? 0),
                'impressions' => (int) ($row->getImpressions() ?? 0),
                'ctr' => (float) ($row->getCtr() ?? 0),
                'position' => $row->getPosition() !== null ? (float) $row->getPosition() : null,
            ])
            ->filter(fn (array $row): bool => $row['query'] !== '')
            ->values()
            ->all();
    }

    private function searchAnalyticsRequest(string $url, Carbon $from, Carbon $to): SearchAnalyticsQueryRequest
    {
        $request = new SearchAnalyticsQueryRequest;
        $request->setStartDate($from->toDateString());
        $request->setEndDate($to->toDateString());
        $request->setType('web');
        $request->setAggregationType('auto');
        $request->setDimensionFilterGroups([$this->pageFilterGroup($url)]);

        return $request;
    }

    private function pageFilterGroup(string $url): ApiDimensionFilterGroup
    {
        $filter = new ApiDimensionFilter;
        $filter->setDimension('page');
        $filter->setOperator('equals');
        $filter->setExpression($url);

        $group = new ApiDimensionFilterGroup;
        $group->setGroupType('and');
        $group->setFilters([$filter]);

        return $group;
    }

    /**
     * @return array<string, mixed>
     */
    private function inspectionForUrl(string $url): array
    {
        $cacheKey = 'cms:gsc:inspection:'.TenantContext::siteId().':'.sha1($this->settings->siteUrl().'|'.$url);

        return Cache::remember($cacheKey, $this->settings->inspectionCacheSeconds(), function () use ($url): array {
            $request = new InspectUrlIndexRequest;
            $request->setInspectionUrl($url);
            $request->setSiteUrl($this->settings->siteUrl());

            $inspectionResult = $this->searchConsole()->urlInspection_index->inspect($request)->getInspectionResult();
            $indexStatus = $inspectionResult?->getIndexStatusResult();

            return [
                'url' => $url,
                'status' => $this->indexStatus($indexStatus),
                'inspectionResultLink' => $inspectionResult?->getInspectionResultLink(),
                'verdict' => $indexStatus?->getVerdict(),
                'coverageState' => $indexStatus?->getCoverageState(),
                'robotsTxtState' => $indexStatus?->getRobotsTxtState(),
                'indexingState' => $indexStatus?->getIndexingState(),
                'lastCrawlTime' => $indexStatus?->getLastCrawlTime(),
                'pageFetchState' => $indexStatus?->getPageFetchState(),
                'googleCanonical' => $indexStatus?->getGoogleCanonical(),
                'userCanonical' => $indexStatus?->getUserCanonical(),
                'crawledAs' => $indexStatus?->getCrawledAs(),
            ];
        });
    }

    private function indexStatus(?IndexStatusInspectionResult $indexStatus): string
    {
        if (! $indexStatus) {
            return 'unknown';
        }

        return match ($indexStatus->getIndexingState()) {
            'BLOCKED_BY_META_TAG', 'BLOCKED_BY_HTTP_HEADER' => 'noindex',
            default => match ($indexStatus->getVerdict()) {
                'PASS' => 'indexed',
                'FAIL' => 'not_indexed',
                'NEUTRAL' => 'excluded',
                default => 'unknown',
            },
        };
    }

    /**
     * @param  array<int, array{summary: array{clicks: int, impressions: int, ctr: float, position: float|null}, queries: array<int, array{query: string, clicks: int, impressions: int, ctr: float, position: float|null}>}>  $analytics
     * @return array{clicks: int, impressions: int, ctr: float, position: float|null}
     */
    private function combinedSummary(array $analytics): array
    {
        $clicks = 0;
        $impressions = 0;
        $positionWeight = 0.0;
        $positionImpressions = 0;

        foreach ($analytics as $row) {
            $summary = $row['summary'];
            $clicks += $summary['clicks'];
            $impressions += $summary['impressions'];

            if ($summary['position'] !== null) {
                $positionWeight += $summary['position'] * $summary['impressions'];
                $positionImpressions += $summary['impressions'];
            }
        }

        return [
            'clicks' => $clicks,
            'impressions' => $impressions,
            'ctr' => $impressions > 0 ? $clicks / $impressions : 0.0,
            'position' => $positionImpressions > 0 ? $positionWeight / $positionImpressions : null,
        ];
    }

    /**
     * @param  array<int, array{summary: array{clicks: int, impressions: int, ctr: float, position: float|null}, queries: array<int, array{query: string, clicks: int, impressions: int, ctr: float, position: float|null}>}>  $analytics
     * @return array<int, array{query: string, clicks: int, impressions: int, ctr: float, position: float|null}>
     */
    private function combinedQueries(array $analytics): array
    {
        $queries = [];

        foreach ($analytics as $row) {
            foreach ($row['queries'] as $query) {
                $key = mb_strtolower($query['query']);

                if (! isset($queries[$key])) {
                    $queries[$key] = [
                        'query' => $query['query'],
                        'clicks' => 0,
                        'impressions' => 0,
                        'positionWeight' => 0.0,
                        'positionImpressions' => 0,
                    ];
                }

                $queries[$key]['clicks'] += $query['clicks'];
                $queries[$key]['impressions'] += $query['impressions'];

                if ($query['position'] !== null) {
                    $queries[$key]['positionWeight'] += $query['position'] * $query['impressions'];
                    $queries[$key]['positionImpressions'] += $query['impressions'];
                }
            }
        }

        return collect($queries)
            ->map(fn (array $query): array => [
                'query' => $query['query'],
                'clicks' => $query['clicks'],
                'impressions' => $query['impressions'],
                'ctr' => $query['impressions'] > 0 ? $query['clicks'] / $query['impressions'] : 0.0,
                'position' => $query['positionImpressions'] > 0 ? $query['positionWeight'] / $query['positionImpressions'] : null,
            ])
            ->sortByDesc('impressions')
            ->sortByDesc('clicks')
            ->take($this->settings->queryLimit())
            ->values()
            ->all();
    }

    private function searchConsole(): SearchConsole
    {
        if ($this->searchConsole) {
            return $this->searchConsole;
        }

        $client = $this->oauthClient(route('admin.cms.search-console.callback'));
        $token = $this->settings->oauthToken();

        if ($token === null) {
            throw new RuntimeException('Google Search Console OAuth token is missing.');
        }

        $client->setAccessToken($token);

        if ($client->isAccessTokenExpired()) {
            $refreshToken = $client->getRefreshToken();

            if (! is_string($refreshToken) || $refreshToken === '') {
                throw new RuntimeException('Google Search Console OAuth token has no refresh token.');
            }

            $refreshedToken = $client->fetchAccessTokenWithRefreshToken($refreshToken);

            if (isset($refreshedToken['error'])) {
                throw new RuntimeException('Google Search Console OAuth token could not be refreshed.');
            }

            $updatedToken = $client->getAccessToken();
            $updatedToken['refresh_token'] ??= $refreshToken;
            $this->settings->storeOauthToken($updatedToken);
        }

        return $this->searchConsole = new SearchConsole($client);
    }

    private function exceptionMessage(Throwable $exception): string
    {
        if ($exception instanceof GoogleServiceException) {
            $errors = $exception->getErrors();
            $reason = is_array($errors[0] ?? null) ? ($errors[0]['reason'] ?? null) : null;

            if ($reason === 'accessNotConfigured') {
                return __('cms_admin_ui.statistics.search_console.api_disabled');
            }

            if ($exception->getCode() === 403) {
                return __('cms_admin_ui.statistics.search_console.no_access');
            }
        }

        return __('cms_admin_ui.statistics.search_console.load_failed');
    }
}
