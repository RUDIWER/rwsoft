<?php

namespace Tests\Unit\Cms;

use App\Actions\PublicSite\ResolveAllowedCmsDownloadsAction;
use App\Models\Cms\CmsDownloadAccessRule;
use App\Models\Cms\CmsDownloadAsset;
use App\Models\Cms\CmsDownloadFolder;
use App\Models\PublicSite\SiteUser;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CmsDownloadAccessTest extends TestCase
{
    public function test_public_download_is_allowed_without_site_user(): void
    {
        $asset = new CmsDownloadAsset(['access_mode' => 'public']);

        $this->assertTrue((new ResolveAllowedCmsDownloadsAction)->canDownload($asset, $this->request()));
    }

    public function test_authenticated_download_requires_active_site_user(): void
    {
        $asset = new CmsDownloadAsset(['access_mode' => 'authenticated']);

        $this->assertFalse((new ResolveAllowedCmsDownloadsAction)->canDownload($asset, $this->request()));
        $this->assertTrue((new ResolveAllowedCmsDownloadsAction)->canDownload($asset, $this->request($this->siteUser())));
    }

    public function test_folder_password_blocks_until_unlocked(): void
    {
        $resolver = new ResolveAllowedCmsDownloadsAction;
        $folder = new CmsDownloadFolder([
            'access_mode' => 'inherit',
            'password_hash' => Hash::make('secret'),
            'password_expires_minutes' => 10,
        ]);
        $folder->id = 123;

        $asset = new CmsDownloadAsset(['access_mode' => 'inherit']);
        $asset->setRelation('folder', $folder);

        $request = $this->request($this->siteUser());

        $this->assertFalse($resolver->canDownload($asset, $request));

        $resolver->markFolderUnlocked($folder, $request);

        $this->assertTrue($resolver->canDownload($asset, $request));
    }

    public function test_restricted_folder_allows_matching_site_user_rule(): void
    {
        $siteUser = $this->siteUser();
        $siteUser->id = 77;

        $folder = new CmsDownloadFolder(['access_mode' => 'restricted']);
        $folder->setRelation('accessRules', collect([
            new CmsDownloadAccessRule([
                'rule_type' => 'site_user',
                'site_user_id' => 77,
                'is_active' => true,
            ]),
        ]));

        $asset = new CmsDownloadAsset(['access_mode' => 'inherit']);
        $asset->setRelation('folder', $folder);
        $asset->setRelation('accessRules', collect());

        $this->assertTrue((new ResolveAllowedCmsDownloadsAction)->canDownload($asset, $this->request($siteUser)));
    }

    private function request(?SiteUser $siteUser = null): Request
    {
        $request = Request::create('/downloads/1/file.pdf');
        $request->setLaravelSession(new Store('testing', new ArraySessionHandler(120)));
        $request->setUserResolver(fn (?string $guard = null): ?SiteUser => $guard === 'site_user' ? $siteUser : null);

        return $request;
    }

    private function siteUser(): SiteUser
    {
        return new SiteUser([
            'name' => 'Site User',
            'email' => 'site-user@example.com',
            'status' => 'active',
        ]);
    }
}
