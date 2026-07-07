<?php

namespace Tests\Unit\Admin\Cms;

use App\Services\Admin\Cms\GoogleSearchConsoleService;
use App\Support\PublicSite\CmsSearchConsoleSettings;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class GoogleSearchConsoleServiceTest extends TestCase
{
    public function test_combined_queries_weights_position_only_by_queries_with_position(): void
    {
        $settings = $this->createMock(CmsSearchConsoleSettings::class);
        $settings->method('queryLimit')->willReturn(10);

        $service = new GoogleSearchConsoleService($settings);

        $method = new ReflectionMethod($service, 'combinedQueries');

        $queries = $method->invoke($service, [
            [
                'summary' => [
                    'clicks' => 0,
                    'impressions' => 0,
                    'ctr' => 0.0,
                    'position' => null,
                ],
                'queries' => [
                    [
                        'query' => 'cms statistics',
                        'clicks' => 2,
                        'impressions' => 10,
                        'ctr' => 0.2,
                        'position' => 4.0,
                    ],
                    [
                        'query' => 'CMS Statistics',
                        'clicks' => 3,
                        'impressions' => 90,
                        'ctr' => 0.0333333333,
                        'position' => null,
                    ],
                ],
            ],
        ]);

        $this->assertCount(1, $queries);
        $this->assertSame('cms statistics', $queries[0]['query']);
        $this->assertSame(5, $queries[0]['clicks']);
        $this->assertSame(100, $queries[0]['impressions']);
        $this->assertSame(0.05, $queries[0]['ctr']);
        $this->assertSame(4.0, $queries[0]['position']);
    }
}
