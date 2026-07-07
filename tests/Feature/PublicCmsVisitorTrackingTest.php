<?php

namespace Tests\Feature;

use App\Actions\Cms\PruneCmsVisitorTrackingAction;
use App\Jobs\Cms\TrackPublicCmsVisitJob;
use App\Models\Cms\CmsVisit;
use App\Models\Cms\CmsVisitor;
use App\Models\Platform\Site;
use App\Support\PublicSite\CmsVisitorTrackingSettings;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\PublicSite\PublicCmsTestCase;

class PublicCmsVisitorTrackingTest extends PublicCmsTestCase
{
    public function test_public_visit_dispatches_tracking_job_when_enabled(): void
    {
        Bus::fake([TrackPublicCmsVisitJob::class]);
        $this->setTenantContext();
        $this->storeSetting('visitor_tracking', 'enabled', true);
        $this->createPage(['is_home' => true]);

        $this
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->get(route('cms.public.home'))
            ->assertOk()
            ->assertCookie(CmsVisitorTrackingSettings::COOKIE_NAME);

        Bus::assertDispatched(TrackPublicCmsVisitJob::class, function (TrackPublicCmsVisitJob $job): bool {
            return $job->siteId === 123
                && $job->payload['ip'] === '203.0.113.10'
                && $job->payload['path'] === '/'
                && $job->payload['is_crawler'] === false;
        });
    }

    public function test_public_visit_is_not_tracked_when_disabled(): void
    {
        Bus::fake([TrackPublicCmsVisitJob::class]);
        $this->setTenantContext();
        $this->storeSetting('visitor_tracking', 'enabled', false);
        $this->createPage(['is_home' => true]);

        $this->get(route('cms.public.home'))->assertOk();

        Bus::assertNotDispatched(TrackPublicCmsVisitJob::class);
    }

    public function test_crawler_visit_is_ignored_when_bot_filter_is_enabled(): void
    {
        Bus::fake([TrackPublicCmsVisitJob::class]);
        $this->setTenantContext();
        $this->storeSetting('visitor_tracking', 'enabled', true);
        $this->storeSetting('visitor_tracking', 'ignore_bots', true);
        $this->createPage(['is_home' => true]);

        $this
            ->withHeaders(['User-Agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)'])
            ->get(route('cms.public.home'))
            ->assertOk();

        Bus::assertNotDispatched(TrackPublicCmsVisitJob::class);
    }

    public function test_prune_action_removes_old_visits_but_keeps_recent_visits(): void
    {
        if (! Schema::hasTable('cms_visitors') || ! Schema::hasTable('cms_visits')) {
            $this->markTestSkipped('CMS visitor tracking tables are not migrated in this test database.');
        }

        $oldVisitor = CmsVisitor::query()->create([
            'uuid' => '11111111-1111-1111-1111-111111111111',
            'ip_hash' => 'old-hash',
            'first_seen_at' => Carbon::now()->subDays(40),
            'last_seen_at' => Carbon::now()->subDays(40),
        ]);
        $recentVisitor = CmsVisitor::query()->create([
            'uuid' => '22222222-2222-2222-2222-222222222222',
            'ip_hash' => 'recent-hash',
            'first_seen_at' => Carbon::now(),
            'last_seen_at' => Carbon::now(),
        ]);

        $oldVisit = CmsVisit::query()->create([
            'cms_visitor_id' => $oldVisitor->id,
            'uuid' => $oldVisitor->uuid,
            'ip_hash' => $oldVisitor->ip_hash,
            'method' => 'GET',
            'path' => '/',
        ]);
        $oldVisit->forceFill([
            'created_at' => Carbon::now()->subDays(40),
            'updated_at' => Carbon::now()->subDays(40),
        ])->save();
        CmsVisit::query()->create([
            'cms_visitor_id' => $recentVisitor->id,
            'uuid' => $recentVisitor->uuid,
            'ip_hash' => $recentVisitor->ip_hash,
            'method' => 'GET',
            'path' => '/',
        ]);

        app(PruneCmsVisitorTrackingAction::class)->handle(30);

        $this->assertModelMissing($oldVisitor);
        $this->assertModelExists($recentVisitor);
    }

    private function setTenantContext(): void
    {
        $site = new Site([
            'name' => 'Test site',
            'slug' => 'test-site',
            'tenant_database' => 'rwsoft_site_rwsoft',
            'status' => 'active',
        ]);
        $site->id = 123;

        TenantContext::setSite($site);
    }
}
