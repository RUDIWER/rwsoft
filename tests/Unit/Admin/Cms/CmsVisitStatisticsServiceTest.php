<?php

namespace Tests\Unit\Admin\Cms;

use App\Services\Admin\Cms\CmsVisitStatisticsService;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class CmsVisitStatisticsServiceTest extends TestCase
{
    public function test_monthly_statistics_fills_missing_months(): void
    {
        $service = new CmsVisitStatisticsService;
        $method = new ReflectionMethod($service, 'monthlyStatistics');

        $rows = collect([
            '2026-01' => (object) [
                'pageviews' => 12,
                'unique_visitors' => 4,
            ],
            '2026-03' => (object) [
                'pageviews' => 7,
                'unique_visitors' => 3,
            ],
        ]);

        $monthly = $method->invoke(
            $service,
            Carbon::parse('2026-01-15'),
            Carbon::parse('2026-03-20'),
            $rows,
        );

        $this->assertSame([
            [
                'month' => '2026-01',
                'pageviews' => 12,
                'uniqueVisitors' => 4,
            ],
            [
                'month' => '2026-02',
                'pageviews' => 0,
                'uniqueVisitors' => 0,
            ],
            [
                'month' => '2026-03',
                'pageviews' => 7,
                'uniqueVisitors' => 3,
            ],
        ], $monthly);
    }
}
