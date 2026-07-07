<?php

use App\Http\Controllers\Admin\Base\PermissionController;
use App\Http\Controllers\Admin\Base\RoleController;
use App\Http\Controllers\Admin\Base\UserController;
use App\Http\Controllers\Admin\Cms\CmsBlockDefinitionController;
use App\Http\Controllers\Admin\Cms\CmsBlockPlacementStyleRevisionController;
use App\Http\Controllers\Admin\Cms\CmsCategoryController;
use App\Http\Controllers\Admin\Cms\CmsColorPaletteController;
use App\Http\Controllers\Admin\Cms\CmsContentStatisticsController;
use App\Http\Controllers\Admin\Cms\CmsContentTranslationController;
use App\Http\Controllers\Admin\Cms\CmsDocController;
use App\Http\Controllers\Admin\Cms\CmsDownloadController;
use App\Http\Controllers\Admin\Cms\CmsDownloadFolderController;
use App\Http\Controllers\Admin\Cms\CmsDownloadGroupController;
use App\Http\Controllers\Admin\Cms\CmsFormController;
use App\Http\Controllers\Admin\Cms\CmsFormSubmissionController;
use App\Http\Controllers\Admin\Cms\CmsHealthController;
use App\Http\Controllers\Admin\Cms\CmsLanguageController;
use App\Http\Controllers\Admin\Cms\CmsLayoutController;
use App\Http\Controllers\Admin\Cms\CmsMailTemplateController;
use App\Http\Controllers\Admin\Cms\CmsMediaController;
use App\Http\Controllers\Admin\Cms\CmsMediaFolderController;
use App\Http\Controllers\Admin\Cms\CmsMenuController;
use App\Http\Controllers\Admin\Cms\CmsPageController;
use App\Http\Controllers\Admin\Cms\CmsPostController;
use App\Http\Controllers\Admin\Cms\CmsRedirectController;
use App\Http\Controllers\Admin\Cms\CmsRevisionController;
use App\Http\Controllers\Admin\Cms\CmsSearchConsoleController;
use App\Http\Controllers\Admin\Cms\CmsSettingController;
use App\Http\Controllers\Admin\Cms\CmsTagController;
use App\Http\Controllers\Admin\Cms\CmsTaxonomyController;
use App\Http\Controllers\Admin\Cms\CmsTemplateController;
use App\Http\Controllers\Admin\Cms\CmsThemeController;
use App\Http\Controllers\Admin\Cms\SiteUserController as AdminSiteUserController;
use App\Http\Controllers\Admin\Dev\DashboardController as DevDashboardController;
use App\Http\Controllers\Admin\Dev\Database\DatabaseLogController;
use App\Http\Controllers\Admin\Dev\PublicTextTranslationController;
use App\Http\Controllers\Admin\Dev\Query\QueryController;
use App\Http\Controllers\Admin\Dev\RwDbDiagram\RwDbDiagramController;
use App\Http\Controllers\Admin\Dev\RwDbDiagram\RwDbSqlController;
use App\Http\Controllers\Admin\Dev\RwDbDiagram\RwDbTableViewController;
use App\Http\Controllers\Admin\Dev\TranslationController;
use App\Http\Controllers\Admin\Locale\AdminLocaleController;
use App\Http\Controllers\Admin\Run\Query\QueryChartController;
use App\Http\Controllers\Admin\Run\Query\QueryLegacyReportController;
use App\Http\Controllers\Admin\Run\Query\QueryRunController;
use App\Http\Controllers\Auth\SiteSwitcherController;
use App\Http\Controllers\Auth\SiteSwitchTokenController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Platform\PlatformDashboardController;
use App\Http\Controllers\Platform\PlatformMailTransportController;
use App\Http\Controllers\Platform\SiteController as PlatformSiteController;
use App\Http\Controllers\Platform\SiteDomainController as PlatformSiteDomainController;
use App\Http\Controllers\Platform\SiteProvisioningController as PlatformSiteProvisioningController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicSite\CmsDocsController;
use App\Http\Controllers\PublicSite\CmsDownloadController as PublicCmsDownloadController;
use App\Http\Controllers\PublicSite\CmsMarkdownController;
use App\Http\Controllers\PublicSite\CmsPublicPageController;
use App\Http\Controllers\PublicSite\CmsPublicPdfController;
use App\Http\Controllers\PublicSite\CmsPublicTaxonomyController;
use App\Http\Controllers\PublicSite\CmsRobotsTxtController;
use App\Http\Controllers\PublicSite\CmsSearchController;
use App\Http\Controllers\PublicSite\CmsSitemapController;
use App\Http\Controllers\PublicSite\CmsThemeAssetController;
use App\Http\Controllers\PublicSite\PublicAccountPageController;
use App\Http\Controllers\PublicSite\SiteUserAuthenticatedSessionController;
use App\Http\Controllers\PublicSite\SiteUserEmailVerificationController;
use App\Http\Controllers\PublicSite\SiteUserNewPasswordController;
use App\Http\Controllers\PublicSite\SiteUserPasswordResetLinkController;
use App\Http\Controllers\PublicSite\SiteUserProfileController;
use App\Http\Controllers\PublicSite\SiteUserRegisteredController;
use App\Http\Controllers\PublicSite\SiteUserSessionController;
use App\Http\Controllers\PublicSite\SiteUserTwoFactorController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['tenant.resolve', 'public.track'])->group(function (): void {
    Route::get('/themes/active/{hash}.css', [CmsThemeAssetController::class, 'active'])
        ->where('hash', '[A-Za-z0-9]+')
        ->name('cms.theme.active');
    Route::get('/themes/preview/{theme}/{hash}.css', [CmsThemeAssetController::class, 'preview'])
        ->whereNumber('theme')
        ->where('hash', '[A-Za-z0-9]+')
        ->name('cms.theme.preview');

    Route::get('/', [CmsPublicPageController::class, 'home'])->name('cms.public.home');
    Route::get('/robots.txt', [CmsRobotsTxtController::class, 'show'])->name('cms.robots');
    Route::get('/llms.txt', [CmsMarkdownController::class, 'llms'])->name('cms.llms');
    Route::get('/sitemap.xml', [CmsSitemapController::class, 'index'])->name('cms.sitemap.index');
    Route::get('/sitemap-pages.xml', [CmsSitemapController::class, 'pages'])->name('cms.sitemap.pages');
    Route::get('/sitemap-posts.xml', [CmsSitemapController::class, 'posts'])->name('cms.sitemap.posts');
    Route::get('/sitemap-categories.xml', [CmsSitemapController::class, 'categories'])->name('cms.sitemap.categories');
    Route::get('/sitemap-tags.xml', [CmsSitemapController::class, 'tags'])->name('cms.sitemap.tags');
    Route::get('/downloads/{download}/{filename?}', [PublicCmsDownloadController::class, 'download'])
        ->whereNumber('download')
        ->where('filename', '[A-Za-z0-9\-\._ ]+')
        ->middleware('throttle:120,1')
        ->name('cms.downloads.download');
    Route::post('/downloads/folders/{folder}/unlock', [PublicCmsDownloadController::class, 'unlockFolder'])
        ->whereNumber('folder')
        ->middleware('throttle:20,1')
        ->name('cms.downloads.folders.unlock');

    Route::prefix('account')
        ->name('site-user.')
        ->middleware('public.locale')
        ->group(function (): void {
            Route::middleware('site-user.guest')->group(function (): void {
                Route::get('/login', [PublicAccountPageController::class, 'login'])->name('login');
                Route::post('/login', [SiteUserAuthenticatedSessionController::class, 'store'])
                    ->middleware('throttle:5,1')
                    ->name('login.store');
                Route::get('/register', [PublicAccountPageController::class, 'register'])->name('register');
                Route::post('/register', [SiteUserRegisteredController::class, 'store'])
                    ->middleware('throttle:5,1')
                    ->name('register.store');
                Route::get('/forgot-password', [PublicAccountPageController::class, 'forgotPassword'])->name('password.request');
                Route::post('/forgot-password', [SiteUserPasswordResetLinkController::class, 'store'])
                    ->middleware('throttle:5,1')
                    ->name('password.email');
                Route::get('/reset-password/{token}', [PublicAccountPageController::class, 'resetPassword'])->name('password.reset');
                Route::post('/reset-password', [SiteUserNewPasswordController::class, 'store'])
                    ->middleware('throttle:5,1')
                    ->name('password.store');
                Route::get('/two-factor-challenge', [PublicAccountPageController::class, 'twoFactorChallenge'])->name('two-factor.challenge');
                Route::post('/two-factor-challenge', [SiteUserTwoFactorController::class, 'challenge'])
                    ->middleware('throttle:5,1')
                    ->name('two-factor.challenge.store');
            });

            Route::get('/email/verify/{id}/{hash}', [SiteUserEmailVerificationController::class, 'verify'])
                ->middleware(['signed', 'throttle:6,1'])
                ->name('verification.verify');

            Route::middleware(['site-user.auth', 'site-user.session'])->group(function (): void {
                Route::post('/logout', [SiteUserAuthenticatedSessionController::class, 'destroy'])->name('logout');
                Route::post('/email/verification-notification', [SiteUserEmailVerificationController::class, 'send'])
                    ->middleware('throttle:6,1')
                    ->name('verification.send');
                Route::get('/security', [PublicAccountPageController::class, 'security'])->name('security');
                Route::post('/profile', [SiteUserProfileController::class, 'update'])->name('profile.update');
                Route::post('/sessions/logout-other-devices', [SiteUserSessionController::class, 'destroyOtherDevices'])->name('sessions.logout-other-devices');
                Route::post('/two-factor/enable', [SiteUserTwoFactorController::class, 'enable'])->name('two-factor.enable');
                Route::post('/two-factor/confirm', [SiteUserTwoFactorController::class, 'confirm'])->name('two-factor.confirm');
                Route::post('/two-factor/disable', [SiteUserTwoFactorController::class, 'disable'])->name('two-factor.disable');
                Route::get('/two-factor/qr-code', [SiteUserTwoFactorController::class, 'qrCode'])->name('two-factor.qr-code');
                Route::get('/two-factor/recovery-codes', [SiteUserTwoFactorController::class, 'recoveryCodes'])->name('two-factor.recovery-codes');

                Route::middleware('site-user.ready')->group(function (): void {
                    Route::get('/dashboard', [PublicAccountPageController::class, 'dashboard'])->name('dashboard');
                    Route::get('/profile', [PublicAccountPageController::class, 'profile'])->name('profile');
                });
            });
        });

    Route::prefix('{locale}/account')
        ->where(['locale' => '[a-z]{2}(?:[_-][A-Z]{2})?'])
        ->name('site-user.localized.')
        ->middleware('public.locale')
        ->group(function (): void {
            Route::middleware('site-user.guest')->group(function (): void {
                Route::get('/login', [PublicAccountPageController::class, 'localizedLogin'])->name('login');
                Route::get('/register', [PublicAccountPageController::class, 'localizedRegister'])->name('register');
                Route::get('/forgot-password', [PublicAccountPageController::class, 'localizedForgotPassword'])->name('password.request');
                Route::get('/reset-password/{token}', [PublicAccountPageController::class, 'localizedResetPassword'])->name('password.reset');
                Route::get('/two-factor-challenge', [PublicAccountPageController::class, 'localizedTwoFactorChallenge'])->name('two-factor.challenge');
            });

            Route::middleware(['site-user.auth', 'site-user.session'])->group(function (): void {
                Route::get('/security', [PublicAccountPageController::class, 'localizedSecurity'])->name('security');

                Route::middleware('site-user.ready')->group(function (): void {
                    Route::get('/dashboard', [PublicAccountPageController::class, 'localizedDashboard'])->name('dashboard');
                    Route::get('/profile', [PublicAccountPageController::class, 'localizedProfile'])->name('profile');
                });
            });
        });

    Route::prefix('{locale}')
        ->where(['locale' => '[a-z]{2}(?:[_-][A-Z]{2})?'])
        ->middleware('public.locale')
        ->group(function (): void {
            Route::get('/', [CmsPublicPageController::class, 'localizedHome'])->name('cms.public.localized.home');
            Route::get('/index.pdf', [CmsPublicPdfController::class, 'localizedHome'])->middleware('throttle:30,1')->name('cms.public.localized.pdf.home');
            Route::get('/search', [CmsSearchController::class, 'index'])->middleware('throttle:60,1')->name('cms.public.localized.search');
            Route::get('/search/results', [CmsSearchController::class, 'results'])->middleware('throttle:60,1')->name('cms.public.localized.search.results');

            foreach (['markdown' => 'raw', 'markdown-view' => 'preview', 'markdown-download' => 'download'] as $markdownPrefix => $markdownMode) {
                Route::prefix($markdownPrefix)
                    ->name('cms.public.localized.'.str_replace('-', '_', $markdownPrefix).'.')
                    ->group(function () use ($markdownMode): void {
                        Route::get('/', [CmsMarkdownController::class, 'index'])->defaults('markdownMode', $markdownMode)->name('index');
                        Route::get('/pages/{path}', [CmsMarkdownController::class, 'page'])->defaults('markdownMode', $markdownMode)->where('path', '[A-Za-z0-9\-/]+')->name('pages.show');
                        Route::get('/blogs', [CmsMarkdownController::class, 'blogIndex'])->defaults('markdownMode', $markdownMode)->name('blogs.index');
                        Route::get('/blogs/categories', [CmsMarkdownController::class, 'categoryIndex'])->defaults('markdownMode', $markdownMode)->name('categories.index');
                        Route::get('/blogs/categories/{path}', [CmsMarkdownController::class, 'category'])->defaults('markdownMode', $markdownMode)->where('path', '[A-Za-z0-9\-/]+')->name('categories.show');
                        Route::get('/blogs/tags', [CmsMarkdownController::class, 'tagIndex'])->defaults('markdownMode', $markdownMode)->name('tags.index');
                        Route::get('/blogs/tags/{slug}', [CmsMarkdownController::class, 'tag'])->defaults('markdownMode', $markdownMode)->where('slug', '[A-Za-z0-9\-]+')->name('tags.show');
                        Route::get('/blogs/{slug}', [CmsMarkdownController::class, 'blogPost'])->defaults('markdownMode', $markdownMode)->where('slug', '[A-Za-z0-9\-]+')->name('blogs.show');
                        Route::get('/docs', [CmsMarkdownController::class, 'docsIndex'])->defaults('markdownMode', $markdownMode)->name('docs.index');
                        Route::get('/docs/{collection}', [CmsMarkdownController::class, 'docsCollection'])->defaults('markdownMode', $markdownMode)->where('collection', '[A-Za-z0-9\-]+')->name('docs.collection');
                        Route::get('/docs/{collection}/{version}', [CmsMarkdownController::class, 'docsVersion'])->defaults('markdownMode', $markdownMode)->where(['collection' => '[A-Za-z0-9\-]+', 'version' => '[A-Za-z0-9\.\-]+'])->name('docs.version');
                        Route::get('/docs/{collection}/{version}/{path}', [CmsMarkdownController::class, 'docsPage'])->defaults('markdownMode', $markdownMode)->where(['collection' => '[A-Za-z0-9\-]+', 'version' => '[A-Za-z0-9\.\-]+', 'path' => '[A-Za-z0-9\-/]+'])->name('docs.show');
                    });
            }

            Route::get('/blogs', [CmsPublicPageController::class, 'localizedPosts'])->name('cms.public.localized.blogs.index');
            Route::get('/blogs/{slug}.pdf', [CmsPublicPdfController::class, 'localizedPost'])
                ->where('slug', '[A-Za-z0-9\-]+')
                ->middleware('throttle:30,1')
                ->name('cms.public.localized.pdf.blogs.show');
            Route::get('/blogs/categories', [CmsPublicTaxonomyController::class, 'localizedCategoryIndex'])->name('cms.public.localized.categories.index');
            Route::get('/blogs/categories/{path}/info.pdf', [CmsPublicPdfController::class, 'localizedCategoryDetail'])
                ->where('path', '[A-Za-z0-9\-/]+')
                ->middleware('throttle:30,1')
                ->name('cms.public.localized.pdf.categories.detail');
            Route::get('/blogs/categories/{path}.pdf', [CmsPublicPdfController::class, 'localizedCategoryArchive'])
                ->where('path', '[A-Za-z0-9\-/]+')
                ->middleware('throttle:30,1')
                ->name('cms.public.localized.pdf.categories.show');
            Route::get('/blogs/categories/{path}', [CmsPublicTaxonomyController::class, 'localizedCategory'])
                ->where('path', '[A-Za-z0-9\-/]+')
                ->name('cms.public.localized.categories.show');
            Route::get('/blogs/tags', [CmsPublicTaxonomyController::class, 'localizedTagIndex'])->name('cms.public.localized.tags.index');
            Route::get('/blogs/tags/{slug}/info.pdf', [CmsPublicPdfController::class, 'localizedTagDetail'])
                ->where('slug', '[A-Za-z0-9\-]+')
                ->middleware('throttle:30,1')
                ->name('cms.public.localized.pdf.tags.detail');
            Route::get('/blogs/tags/{slug}.pdf', [CmsPublicPdfController::class, 'localizedTagArchive'])
                ->where('slug', '[A-Za-z0-9\-]+')
                ->middleware('throttle:30,1')
                ->name('cms.public.localized.pdf.tags.show');
            Route::get('/blogs/tags/{slug}/info', [CmsPublicTaxonomyController::class, 'localizedTagDetail'])
                ->where('slug', '[A-Za-z0-9\-]+')
                ->name('cms.public.localized.tags.detail');
            Route::get('/blogs/tags/{slug}', [CmsPublicTaxonomyController::class, 'localizedTag'])
                ->where('slug', '[A-Za-z0-9\-]+')
                ->name('cms.public.localized.tags.show');
            Route::get('/blogs/{slug}', [CmsPublicPageController::class, 'localizedPost'])
                ->where('slug', '[A-Za-z0-9\-]+')
                ->name('cms.public.localized.blogs.show');
            Route::get('/docs', [CmsDocsController::class, 'index'])->name('cms.public.docs.index');
            Route::get('/docs/{collection}', [CmsDocsController::class, 'collection'])
                ->where('collection', '[A-Za-z0-9\-]+')
                ->name('cms.public.docs.collection');
            Route::get('/docs/{collection}/{version}', [CmsDocsController::class, 'version'])
                ->where(['collection' => '[A-Za-z0-9\-]+', 'version' => '[A-Za-z0-9\.\-]+'])
                ->name('cms.public.docs.version');
            Route::get('/docs/{collection}/{version}/{path}', [CmsDocsController::class, 'show'])
                ->where(['collection' => '[A-Za-z0-9\-]+', 'version' => '[A-Za-z0-9\.\-]+', 'path' => '[A-Za-z0-9\-/]+'])
                ->name('cms.public.docs.show');
            Route::get('/posts', [CmsPublicPageController::class, 'localizedPosts'])->name('cms.public.localized.posts.index');
            Route::get('/posts/category/{path}', [CmsPublicTaxonomyController::class, 'localizedCategory'])
                ->where('path', '[A-Za-z0-9\-/]+')
                ->name('cms.public.localized.posts.categories.show');
            Route::get('/posts/tag/{slug}', [CmsPublicTaxonomyController::class, 'localizedTag'])
                ->where('slug', '[A-Za-z0-9\-]+')
                ->name('cms.public.localized.posts.tags.show');
            Route::get('/posts/{slug}', [CmsPublicPageController::class, 'localizedPost'])
                ->where('slug', '[A-Za-z0-9\-]+')
                ->name('cms.public.localized.posts.show');
            Route::get('/{path}.pdf', [CmsPublicPdfController::class, 'localizedPage'])
                ->where('path', '[A-Za-z0-9\-/]+')
                ->middleware('throttle:30,1')
                ->name('cms.public.localized.pdf.page');
            Route::get('/{slug}', [CmsPublicPageController::class, 'localizedShow'])
                ->where('slug', '[A-Za-z0-9\-]+')
                ->name('cms.public.localized.show');
            Route::get('/{path}', [CmsPublicPageController::class, 'localizedShowPath'])
                ->where('path', '[A-Za-z0-9\-/]+')
                ->name('cms.public.localized.path');
        });
    Route::get('/posts', [CmsPublicPageController::class, 'posts'])->name('cms.public.posts.index');
    Route::get('/index.pdf', [CmsPublicPdfController::class, 'home'])->middleware('throttle:30,1')->name('cms.public.pdf.home');
    Route::get('/blogs', [CmsPublicPageController::class, 'posts'])->name('cms.public.blogs.index');
    Route::get('/blogs/{slug}.pdf', [CmsPublicPdfController::class, 'post'])
        ->where('slug', '[A-Za-z0-9\-]+')
        ->middleware('throttle:30,1')
        ->name('cms.public.pdf.blogs.show');
    Route::get('/blogs/categories', [CmsPublicTaxonomyController::class, 'categoryIndex'])->name('cms.public.categories.index');
    Route::get('/blogs/categories/{path}/info.pdf', [CmsPublicPdfController::class, 'categoryDetail'])
        ->where('path', '[A-Za-z0-9\-/]+')
        ->middleware('throttle:30,1')
        ->name('cms.public.pdf.categories.detail');
    Route::get('/blogs/categories/{path}.pdf', [CmsPublicPdfController::class, 'categoryArchive'])
        ->where('path', '[A-Za-z0-9\-/]+')
        ->middleware('throttle:30,1')
        ->name('cms.public.pdf.categories.show');
    Route::get('/blogs/categories/{path}', [CmsPublicTaxonomyController::class, 'category'])
        ->where('path', '[A-Za-z0-9\-/]+')
        ->name('cms.public.categories.show');
    Route::get('/blogs/tags', [CmsPublicTaxonomyController::class, 'tagIndex'])->name('cms.public.tags.index');
    Route::get('/blogs/tags/{slug}/info.pdf', [CmsPublicPdfController::class, 'tagDetail'])
        ->where('slug', '[A-Za-z0-9\-]+')
        ->middleware('throttle:30,1')
        ->name('cms.public.pdf.tags.detail');
    Route::get('/blogs/tags/{slug}.pdf', [CmsPublicPdfController::class, 'tagArchive'])
        ->where('slug', '[A-Za-z0-9\-]+')
        ->middleware('throttle:30,1')
        ->name('cms.public.pdf.tags.show');
    Route::get('/blogs/tags/{slug}/info', [CmsPublicTaxonomyController::class, 'tagDetail'])
        ->where('slug', '[A-Za-z0-9\-]+')
        ->name('cms.public.tags.detail');
    Route::get('/blogs/tags/{slug}', [CmsPublicTaxonomyController::class, 'tag'])
        ->where('slug', '[A-Za-z0-9\-]+')
        ->name('cms.public.tags.show');
    Route::get('/blogs/{slug}', [CmsPublicPageController::class, 'post'])
        ->where('slug', '[A-Za-z0-9\-]+')
        ->name('cms.public.blogs.show');
    Route::get('/posts/category/{path}', [CmsPublicTaxonomyController::class, 'category'])
        ->where('path', '[A-Za-z0-9\-/]+')
        ->name('cms.public.posts.categories.show');
    Route::get('/posts/tag/{slug}', [CmsPublicTaxonomyController::class, 'tag'])
        ->where('slug', '[A-Za-z0-9\-]+')
        ->name('cms.public.posts.tags.show');
    Route::get('/posts/{slug}', [CmsPublicPageController::class, 'post'])
        ->where('slug', '[A-Za-z0-9\-]+')
        ->name('cms.public.posts.show');
    Route::get('/{path}.pdf', [CmsPublicPdfController::class, 'page'])
        ->where('path', '[A-Za-z0-9\-/]+')
        ->middleware('throttle:30,1')
        ->name('cms.public.pdf.page');
});

Route::get('/dashboard', function () {
    return redirect()->route('admin');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function (): void {
    Route::get('/user/setup-2fa', function () {
        return Inertia::render('Auth/SetupTwoFactor');
    })->name('2fa.setup');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');
});

Route::middleware(['auth', '2fa.required'])->group(function (): void {
    Route::get('/site-switcher', [SiteSwitcherController::class, 'index'])->name('site-switcher.index');
    Route::post('/site-switcher/{site}/switch', [SiteSwitcherController::class, 'switch'])->name('site-switcher.switch');
});

Route::middleware('tenant.resolve')
    ->get('/auth/site-switch', SiteSwitchTokenController::class)
    ->name('site-switch-token.consume');

Route::middleware(['auth', '2fa.required', 'platform.admin'])
    ->prefix('platform')
    ->name('platform.')
    ->group(function (): void {
        Route::get('/', PlatformDashboardController::class)->name('dashboard');
        Route::get('/sites', [PlatformSiteController::class, 'index'])->name('sites.index');
        Route::get('/sites/create', [PlatformSiteController::class, 'edit'])->defaults('id', 0)->name('sites.create');
        Route::get('/sites/{id}/edit', [PlatformSiteController::class, 'edit'])->whereNumber('id')->name('sites.edit');
        Route::post('/sites/{id}/store', [PlatformSiteController::class, 'store'])->whereNumber('id')->name('sites.store');
        Route::post('/sites/{site}/domains/store', [PlatformSiteDomainController::class, 'store'])->name('sites.domains.store');
        Route::post('/sites/{site}/provision', [PlatformSiteProvisioningController::class, 'store'])->name('sites.provision');
        Route::get('/mail-transport', [PlatformMailTransportController::class, 'edit'])->name('mail-transport.edit');
        Route::post('/mail-transport/store', [PlatformMailTransportController::class, 'store'])->name('mail-transport.store');
        Route::post('/mail-transport/test', [PlatformMailTransportController::class, 'test'])->middleware('throttle:10,1')->name('mail-transport.test');
        Route::post('/mail-transport/activate', [PlatformMailTransportController::class, 'activate'])->name('mail-transport.activate');
        Route::get('/translations', [TranslationController::class, 'index'])->name('translations.index');
        Route::get('/translations/rows', [TranslationController::class, 'rows'])->name('translations.rows');
        Route::patch('/translations/rows/{row}', [TranslationController::class, 'update'])->name('translations.update');
        Route::post('/translations/sync', [TranslationController::class, 'sync'])->name('translations.sync');
        Route::post('/translations/add-locale', [TranslationController::class, 'addLocale'])->name('translations.add-locale');
        Route::post('/translations/ai-fill', [TranslationController::class, 'aiFill'])
            ->middleware('throttle:20,1')
            ->name('translations.ai-fill');
    });

Route::middleware(['tenant.resolve', 'locale.resolve', 'AuthAdmin', 'site.member', '2fa.required', 'AdminAcl'])
    ->prefix('admin')
    ->group(function (): void {
        Route::get('/', [DevDashboardController::class, 'index'])->name('admin');
        Route::post('/locale', [AdminLocaleController::class, 'update'])->name('admin.locale.update');
        Route::get('/run', fn () => redirect()->route('admin'))->name('admin.run.dashboard');
        Route::get('/redirect', fn () => redirect()->route('admin'))->name('admin.redirect');

        Route::get('/translations', [TranslationController::class, 'index'])->name('admin.translations.index');
        Route::get('/translations/public/rows', [PublicTextTranslationController::class, 'rows'])->name('admin.translations.public.rows');
        Route::patch('/translations/public/rows/{row}', [PublicTextTranslationController::class, 'update'])->name('admin.translations.public.update');
        Route::post('/translations/public/sync', [PublicTextTranslationController::class, 'sync'])->name('admin.translations.public.sync');
        Route::post('/translations/public/ai-fill', [PublicTextTranslationController::class, 'aiFill'])
            ->middleware('throttle:20,1')
            ->name('admin.translations.public.ai-fill');
        Route::get('/translations/content/rows', [CmsContentTranslationController::class, 'rows'])->name('admin.translations.content.rows');
        Route::post('/translations/content', [CmsContentTranslationController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('admin.translations.content.store');
        Route::post('/translations/content/bulk-ai', [CmsContentTranslationController::class, 'bulkAi'])
            ->middleware('throttle:5,1')
            ->name('admin.translations.content.bulk-ai');
        Route::post('/translations/content/mark-reviewed', [CmsContentTranslationController::class, 'markReviewed'])
            ->middleware('throttle:30,1')
            ->name('admin.translations.content.mark-reviewed');

        Route::get('/cms/health', [CmsHealthController::class, 'index'])->name('admin.cms.health.index');
        Route::post('/cms/health/public-account/repair', [CmsHealthController::class, 'repairPublicAccount'])->name('admin.cms.health.public-account.repair');
        Route::get('/cms/layouts', [CmsLayoutController::class, 'index'])->name('admin.cms.layouts.index');
        Route::get('/cms/layouts/create', [CmsLayoutController::class, 'create'])->name('admin.cms.layouts.create');
        Route::get('/cms/layouts/{id}/edit', [CmsLayoutController::class, 'edit'])->whereNumber('id')->name('admin.cms.layouts.edit');
        Route::post('/cms/layouts/{id}/store', [CmsLayoutController::class, 'store'])->whereNumber('id')->name('admin.cms.layouts.store');
        Route::post('/cms/section-preview', [CmsLayoutController::class, 'previewSection'])->middleware('throttle:120,1')->name('admin.cms.section-preview');
        Route::get('/cms/color-palette', [CmsColorPaletteController::class, 'index'])->name('admin.cms.color-palette.index');
        Route::post('/cms/color-palette', [CmsColorPaletteController::class, 'store'])->middleware('throttle:30,1')->name('admin.cms.color-palette.store');
        Route::delete('/cms/color-palette/{item}', [CmsColorPaletteController::class, 'destroy'])->whereNumber('item')->middleware('throttle:30,1')->name('admin.cms.color-palette.destroy');
        Route::get('/cms/block-placements/{placement}/style-revisions', [CmsBlockPlacementStyleRevisionController::class, 'index'])->whereNumber('placement')->name('admin.cms.block-placements.style-revisions.index');
        Route::post('/cms/block-placements/{placement}/style-revisions/publish', [CmsBlockPlacementStyleRevisionController::class, 'publish'])->whereNumber('placement')->middleware('throttle:30,1')->name('admin.cms.block-placements.style-revisions.publish');
        Route::post('/cms/block-placements/{placement}/style-revisions/{revision}/republish', [CmsBlockPlacementStyleRevisionController::class, 'republish'])->whereNumber(['placement', 'revision'])->middleware('throttle:30,1')->name('admin.cms.block-placements.style-revisions.republish');
        Route::post('/cms/layouts/{id}/translations', [CmsLayoutController::class, 'storeTranslation'])->whereNumber('id')->middleware('throttle:20,1')->name('admin.cms.layouts.translations.store');
        Route::get('/cms/layouts/{layout}/revisions', [CmsRevisionController::class, 'layoutIndex'])->whereNumber('layout')->name('admin.cms.layouts.revisions.index');
        Route::post('/cms/layouts/{layout}/revisions/{revision}/restore', [CmsRevisionController::class, 'layoutRestore'])->whereNumber(['layout', 'revision'])->name('admin.cms.layouts.revisions.restore');
        Route::delete('/cms/layouts/{id}', [CmsLayoutController::class, 'destroy'])->whereNumber('id')->name('admin.cms.layouts.destroy');
        Route::get('/cms/templates', [CmsTemplateController::class, 'index'])->name('admin.cms.templates.index');
        Route::get('/cms/templates/create', [CmsTemplateController::class, 'create'])->name('admin.cms.templates.create');
        Route::get('/cms/templates/{id}/edit', [CmsTemplateController::class, 'edit'])->whereNumber('id')->name('admin.cms.templates.edit');
        Route::get('/cms/templates/{id}/preview', [CmsTemplateController::class, 'preview'])->whereNumber('id')->name('admin.cms.templates.preview');
        Route::get('/cms/templates/{template}/revisions', [CmsRevisionController::class, 'templateIndex'])->whereNumber('template')->name('admin.cms.templates.revisions.index');
        Route::post('/cms/templates/{template}/revisions/{revision}/restore', [CmsRevisionController::class, 'templateRestore'])->whereNumber(['template', 'revision'])->name('admin.cms.templates.revisions.restore');
        Route::post('/cms/templates/{id}/translations', [CmsTemplateController::class, 'storeTranslation'])->whereNumber('id')->middleware('throttle:20,1')->name('admin.cms.templates.translations.store');
        Route::post('/cms/templates/{id}/store', [CmsTemplateController::class, 'store'])->whereNumber('id')->name('admin.cms.templates.store');
        Route::delete('/cms/templates/{id}', [CmsTemplateController::class, 'destroy'])->whereNumber('id')->name('admin.cms.templates.destroy');
        Route::get('/cms/pages', [CmsPageController::class, 'index'])->name('admin.cms.pages.index');
        Route::get('/cms/pages/create', [CmsPageController::class, 'create'])->name('admin.cms.pages.create');
        Route::get('/cms/pages/{id}/edit', [CmsPageController::class, 'edit'])->whereNumber('id')->name('admin.cms.pages.edit');
        Route::post('/cms/pages/{id}/store', [CmsPageController::class, 'store'])->whereNumber('id')->name('admin.cms.pages.store');
        Route::post('/cms/pages/{id}/translations', [CmsPageController::class, 'storeTranslation'])->whereNumber('id')->middleware('throttle:20,1')->name('admin.cms.pages.translations.store');
        Route::get('/cms/pages/{page}/revisions', [CmsRevisionController::class, 'pageIndex'])->whereNumber('page')->name('admin.cms.pages.revisions.index');
        Route::post('/cms/pages/{page}/revisions/{revision}/restore', [CmsRevisionController::class, 'pageRestore'])->whereNumber(['page', 'revision'])->name('admin.cms.pages.revisions.restore');
        Route::delete('/cms/pages/{id}', [CmsPageController::class, 'destroy'])->whereNumber('id')->name('admin.cms.pages.destroy');
        Route::get('/cms/docs', [CmsDocController::class, 'index'])->name('admin.cms.docs.index');
        Route::get('/cms/docs/collections/create', [CmsDocController::class, 'createCollection'])->name('admin.cms.docs.collections.create');
        Route::get('/cms/docs/collections/{collection}/pages', [CmsDocController::class, 'collectionPages'])->whereNumber('collection')->name('admin.cms.docs.collections.pages');
        Route::get('/cms/docs/collections/{collection}/edit', [CmsDocController::class, 'editCollection'])->whereNumber('collection')->name('admin.cms.docs.collections.edit');
        Route::post('/cms/docs/collections/{collection}/store', [CmsDocController::class, 'storeCollection'])->whereNumber('collection')->name('admin.cms.docs.collections.store');
        Route::get('/cms/docs/versions/create', [CmsDocController::class, 'createVersion'])->name('admin.cms.docs.versions.create');
        Route::get('/cms/docs/versions/{version}/edit', [CmsDocController::class, 'editVersion'])->whereNumber('version')->name('admin.cms.docs.versions.edit');
        Route::post('/cms/docs/versions/{version}/store', [CmsDocController::class, 'storeVersion'])->whereNumber('version')->name('admin.cms.docs.versions.store');
        Route::get('/cms/docs/pages/create', [CmsDocController::class, 'createPage'])->name('admin.cms.docs.pages.create');
        Route::get('/cms/docs/pages/{page}/edit', [CmsDocController::class, 'editPage'])->whereNumber('page')->name('admin.cms.docs.pages.edit');
        Route::post('/cms/docs/pages/{page}/store', [CmsDocController::class, 'storePage'])->whereNumber('page')->name('admin.cms.docs.pages.store');
        Route::post('/cms/docs/pages/{page}/translations', [CmsDocController::class, 'storePageTranslation'])->whereNumber('page')->middleware('throttle:20,1')->name('admin.cms.docs.pages.translations.store');
        Route::post('/cms/docs/pages/bulk-status', [CmsDocController::class, 'bulkStatus'])->name('admin.cms.docs.pages.bulk-status');
        Route::get('/cms/languages', [CmsLanguageController::class, 'index'])->name('admin.cms.languages.index');
        Route::get('/cms/languages/create', [CmsLanguageController::class, 'create'])->name('admin.cms.languages.create');
        Route::get('/cms/languages/{id}/edit', [CmsLanguageController::class, 'edit'])->whereNumber('id')->name('admin.cms.languages.edit');
        Route::post('/cms/languages/{id}/store', [CmsLanguageController::class, 'store'])->whereNumber('id')->name('admin.cms.languages.store');
        Route::post('/cms/languages/reorder', [CmsLanguageController::class, 'reorder'])->name('admin.cms.languages.reorder');
        Route::get('/cms/country-flags/{code}', [CmsLanguageController::class, 'previewSystemFlag'])->where('code', '[a-z0-9-]{2,16}')->name('admin.cms.country-flags.preview');
        Route::post('/cms/country-flags/copy', [CmsLanguageController::class, 'copySystemFlag'])->middleware('throttle:30,1')->name('admin.cms.country-flags.copy');
        Route::get('/cms/posts', [CmsPostController::class, 'index'])->name('admin.cms.posts.index');
        Route::get('/cms/posts/create', [CmsPostController::class, 'create'])->name('admin.cms.posts.create');
        Route::get('/cms/posts/{id}/edit', [CmsPostController::class, 'edit'])->whereNumber('id')->name('admin.cms.posts.edit');
        Route::post('/cms/posts/{id}/store', [CmsPostController::class, 'store'])->whereNumber('id')->name('admin.cms.posts.store');
        Route::post('/cms/posts/{id}/translations', [CmsPostController::class, 'storeTranslation'])->whereNumber('id')->middleware('throttle:20,1')->name('admin.cms.posts.translations.store');
        Route::get('/cms/posts/{post}/revisions', [CmsRevisionController::class, 'postIndex'])->whereNumber('post')->name('admin.cms.posts.revisions.index');
        Route::post('/cms/posts/{post}/revisions/{revision}/restore', [CmsRevisionController::class, 'postRestore'])->whereNumber(['post', 'revision'])->name('admin.cms.posts.revisions.restore');
        Route::get('/cms/taxonomy', [CmsTaxonomyController::class, 'index'])->name('admin.cms.taxonomy.index');
        Route::get('/cms/categories', [CmsCategoryController::class, 'index'])->name('admin.cms.categories.index');
        Route::get('/cms/categories/create', [CmsCategoryController::class, 'create'])->name('admin.cms.categories.create');
        Route::get('/cms/categories/{id}/edit', [CmsCategoryController::class, 'edit'])->whereNumber('id')->name('admin.cms.categories.edit');
        Route::post('/cms/categories/{id}/store', [CmsCategoryController::class, 'store'])->whereNumber('id')->name('admin.cms.categories.store');
        Route::post('/cms/categories/{id}/translations', [CmsCategoryController::class, 'storeTranslation'])->whereNumber('id')->middleware('throttle:20,1')->name('admin.cms.categories.translations.store');
        Route::get('/cms/categories/{category}/revisions', [CmsRevisionController::class, 'categoryIndex'])->whereNumber('category')->name('admin.cms.categories.revisions.index');
        Route::post('/cms/categories/{category}/revisions/{revision}/restore', [CmsRevisionController::class, 'categoryRestore'])->whereNumber(['category', 'revision'])->name('admin.cms.categories.revisions.restore');
        Route::get('/cms/tags', [CmsTagController::class, 'index'])->name('admin.cms.tags.index');
        Route::get('/cms/tags/create', [CmsTagController::class, 'create'])->name('admin.cms.tags.create');
        Route::get('/cms/tags/{id}/edit', [CmsTagController::class, 'edit'])->whereNumber('id')->name('admin.cms.tags.edit');
        Route::post('/cms/tags/{id}/store', [CmsTagController::class, 'store'])->whereNumber('id')->name('admin.cms.tags.store');
        Route::post('/cms/tags/{id}/translations', [CmsTagController::class, 'storeTranslation'])->whereNumber('id')->middleware('throttle:20,1')->name('admin.cms.tags.translations.store');
        Route::get('/cms/tags/{tag}/revisions', [CmsRevisionController::class, 'tagIndex'])->whereNumber('tag')->name('admin.cms.tags.revisions.index');
        Route::post('/cms/tags/{tag}/revisions/{revision}/restore', [CmsRevisionController::class, 'tagRestore'])->whereNumber(['tag', 'revision'])->name('admin.cms.tags.revisions.restore');
        Route::get('/cms/media', [CmsMediaController::class, 'index'])->name('admin.cms.media.index');
        Route::post('/cms/media/store', [CmsMediaController::class, 'store'])->name('admin.cms.media.store');
        Route::post('/cms/media/sort', [CmsMediaController::class, 'sort'])->middleware('throttle:60,1')->name('admin.cms.media.sort');
        Route::get('/cms/downloads', [CmsDownloadController::class, 'index'])->name('admin.cms.downloads.index');
        Route::post('/cms/downloads/store', [CmsDownloadController::class, 'store'])->name('admin.cms.downloads.store');
        Route::get('/cms/downloads/{download}/edit', [CmsDownloadController::class, 'edit'])->whereNumber('download')->name('admin.cms.downloads.edit');
        Route::post('/cms/downloads/{download}/update', [CmsDownloadController::class, 'update'])->whereNumber('download')->name('admin.cms.downloads.update');
        Route::post('/cms/downloads/{download}/replace-file', [CmsDownloadController::class, 'replaceFile'])->whereNumber('download')->name('admin.cms.downloads.replace-file');
        Route::delete('/cms/downloads/{download}', [CmsDownloadController::class, 'destroy'])->whereNumber('download')->name('admin.cms.downloads.destroy');
        Route::get('/cms/download-groups', [CmsDownloadGroupController::class, 'index'])->name('admin.cms.download-groups.index');
        Route::post('/cms/download-groups/store', [CmsDownloadGroupController::class, 'store'])->name('admin.cms.download-groups.store');
        Route::post('/cms/download-groups/{group}/store', [CmsDownloadGroupController::class, 'store'])->whereNumber('group')->name('admin.cms.download-groups.update');
        Route::post('/cms/download-folders/store', [CmsDownloadFolderController::class, 'store'])->name('admin.cms.download-folders.store');
        Route::patch('/cms/download-folders/{folder}', [CmsDownloadFolderController::class, 'update'])->whereNumber('folder')->name('admin.cms.download-folders.update');
        Route::patch('/cms/download-folders/{folder}/move', [CmsDownloadFolderController::class, 'move'])->whereNumber('folder')->name('admin.cms.download-folders.move');
        Route::post('/cms/media-folders/store', [CmsMediaFolderController::class, 'store'])->name('admin.cms.media-folders.store');
        Route::patch('/cms/media-folders/{folder}', [CmsMediaFolderController::class, 'update'])->whereNumber('folder')->name('admin.cms.media-folders.update');
        Route::patch('/cms/media-folders/{folder}/move', [CmsMediaFolderController::class, 'move'])->whereNumber('folder')->name('admin.cms.media-folders.move');
        Route::get('/cms/media/{id}/edit', [CmsMediaController::class, 'edit'])->whereNumber('id')->name('admin.cms.media.edit');
        Route::post('/cms/media/{id}/edit-copy', [CmsMediaController::class, 'editCopy'])->whereNumber('id')->middleware('throttle:30,1')->name('admin.cms.media.edit-copy');
        Route::post('/cms/media/{id}/update', [CmsMediaController::class, 'update'])->whereNumber('id')->name('admin.cms.media.update');
        Route::patch('/cms/media/{id}/metadata', [CmsMediaController::class, 'metadata'])->whereNumber('id')->name('admin.cms.media.metadata');
        Route::delete('/cms/media/{id}', [CmsMediaController::class, 'destroy'])->whereNumber('id')->name('admin.cms.media.destroy');
        Route::get('/cms/menus', [CmsMenuController::class, 'index'])->name('admin.cms.menus.index');
        Route::get('/cms/menus/create', [CmsMenuController::class, 'create'])->name('admin.cms.menus.create');
        Route::get('/cms/menus/{id}/edit', [CmsMenuController::class, 'edit'])->whereNumber('id')->name('admin.cms.menus.edit');
        Route::post('/cms/menus/{id}/store', [CmsMenuController::class, 'store'])->whereNumber('id')->name('admin.cms.menus.store');
        Route::get('/cms/menus/{menu}/revisions', [CmsRevisionController::class, 'menuIndex'])->whereNumber('menu')->name('admin.cms.menus.revisions.index');
        Route::post('/cms/menus/{menu}/revisions/{revision}/restore', [CmsRevisionController::class, 'menuRestore'])->whereNumber(['menu', 'revision'])->name('admin.cms.menus.revisions.restore');
        Route::post('/cms/menus/{menu}/items/{item}/store', [CmsMenuController::class, 'storeItem'])->whereNumber(['menu', 'item'])->name('admin.cms.menu-items.store');
        Route::post('/cms/menus/{menu}/items/{item}/translations', [CmsMenuController::class, 'storeItemTranslation'])->whereNumber(['menu', 'item'])->middleware('throttle:20,1')->name('admin.cms.menu-items.translations.store');
        Route::delete('/cms/menus/{menu}/items/{item}', [CmsMenuController::class, 'destroyItem'])->whereNumber(['menu', 'item'])->name('admin.cms.menu-items.destroy');
        Route::get('/cms/redirects', [CmsRedirectController::class, 'index'])->name('admin.cms.redirects.index');
        Route::get('/cms/redirects/create', [CmsRedirectController::class, 'create'])->name('admin.cms.redirects.create');
        Route::get('/cms/redirects/{id}/edit', [CmsRedirectController::class, 'edit'])->whereNumber('id')->name('admin.cms.redirects.edit');
        Route::post('/cms/redirects/{id}/store', [CmsRedirectController::class, 'store'])->whereNumber('id')->name('admin.cms.redirects.store');
        Route::get('/cms/forms', [CmsFormController::class, 'index'])->name('admin.cms.forms.index');
        Route::get('/cms/forms/create', [CmsFormController::class, 'create'])->name('admin.cms.forms.create');
        Route::get('/cms/forms/{id}/edit', [CmsFormController::class, 'edit'])->whereNumber('id')->name('admin.cms.forms.edit');
        Route::post('/cms/forms/{id}/store', [CmsFormController::class, 'store'])->whereNumber('id')->name('admin.cms.forms.store');
        Route::post('/cms/forms/{id}/translations', [CmsFormController::class, 'storeTranslation'])->whereNumber('id')->middleware('throttle:20,1')->name('admin.cms.forms.translations.store');
        Route::get('/cms/forms/{form}/revisions', [CmsRevisionController::class, 'formIndex'])->whereNumber('form')->name('admin.cms.forms.revisions.index');
        Route::post('/cms/forms/{form}/revisions/{revision}/restore', [CmsRevisionController::class, 'formRestore'])->whereNumber(['form', 'revision'])->name('admin.cms.forms.revisions.restore');
        Route::get('/cms/form-submissions', [CmsFormSubmissionController::class, 'index'])->name('admin.cms.form-submissions.index');
        Route::get('/cms/mail-templates', [CmsMailTemplateController::class, 'index'])->name('admin.cms.mail-templates.index');
        Route::get('/cms/mail-templates/create', [CmsMailTemplateController::class, 'create'])->name('admin.cms.mail-templates.create');
        Route::get('/cms/mail-templates/{id}/edit', [CmsMailTemplateController::class, 'edit'])->whereNumber('id')->name('admin.cms.mail-templates.edit');
        Route::post('/cms/mail-templates/{id}/store', [CmsMailTemplateController::class, 'store'])->whereNumber('id')->name('admin.cms.mail-templates.store');
        Route::get('/cms/mail-templates/{mailTemplate}/revisions', [CmsRevisionController::class, 'mailTemplateIndex'])->whereNumber('mailTemplate')->name('admin.cms.mail-templates.revisions.index');
        Route::post('/cms/mail-templates/{mailTemplate}/revisions/{revision}/restore', [CmsRevisionController::class, 'mailTemplateRestore'])->whereNumber(['mailTemplate', 'revision'])->name('admin.cms.mail-templates.revisions.restore');
        Route::get('/cms/emails', [CmsMailTemplateController::class, 'emailsIndex'])->name('admin.cms.emails.index');
        Route::get('/cms/emails/create', [CmsMailTemplateController::class, 'createEmail'])->name('admin.cms.emails.create');
        Route::get('/cms/emails/{id}/edit', [CmsMailTemplateController::class, 'editEmail'])->whereNumber('id')->name('admin.cms.emails.edit');
        Route::post('/cms/emails/{id}/store', [CmsMailTemplateController::class, 'storeEmail'])->whereNumber('id')->name('admin.cms.emails.store');
        Route::post('/cms/emails/{id}/translations', [CmsMailTemplateController::class, 'storeEmailTranslation'])->whereNumber('id')->middleware('throttle:20,1')->name('admin.cms.emails.translations.store');
        Route::get('/cms/emails/{email}/revisions', [CmsRevisionController::class, 'emailIndex'])->whereNumber('email')->name('admin.cms.emails.revisions.index');
        Route::post('/cms/emails/{email}/revisions/{revision}/restore', [CmsRevisionController::class, 'emailRestore'])->whereNumber(['email', 'revision'])->name('admin.cms.emails.revisions.restore');
        Route::get('/cms/emails/{id}/preview', [CmsMailTemplateController::class, 'previewEmail'])->whereNumber('id')->name('admin.cms.emails.preview');
        Route::post('/cms/emails/{id}/test-send', [CmsMailTemplateController::class, 'testEmail'])->whereNumber('id')->middleware('throttle:10,1')->name('admin.cms.emails.test-send');
        Route::get('/cms/site-users', [AdminSiteUserController::class, 'index'])->name('admin.cms.site-users.index');
        Route::post('/cms/site-users/settings', [AdminSiteUserController::class, 'storeSettings'])->name('admin.cms.site-users.settings.store');
        Route::post('/cms/site-users/{siteUser}/activate', [AdminSiteUserController::class, 'activate'])->name('admin.cms.site-users.activate');
        Route::post('/cms/site-users/{siteUser}/deactivate', [AdminSiteUserController::class, 'deactivate'])->name('admin.cms.site-users.deactivate');
        Route::post('/cms/site-users/{siteUser}/reset-two-factor', [AdminSiteUserController::class, 'resetTwoFactor'])->name('admin.cms.site-users.reset-two-factor');
        Route::get('/cms/themes', [CmsThemeController::class, 'index'])->name('admin.cms.themes.index');
        Route::get('/cms/themes/create', [CmsThemeController::class, 'create'])->name('admin.cms.themes.create');
        Route::get('/cms/themes/{theme}/edit', [CmsThemeController::class, 'edit'])->name('admin.cms.themes.edit');
        Route::post('/cms/themes/store', [CmsThemeController::class, 'store'])->name('admin.cms.themes.store-new');
        Route::post('/cms/themes/{theme}/store', [CmsThemeController::class, 'store'])->name('admin.cms.themes.store');
        Route::post('/cms/themes/{theme}/publish', [CmsThemeController::class, 'publish'])->name('admin.cms.themes.publish');
        Route::post('/cms/themes/{theme}/activate', [CmsThemeController::class, 'activate'])->name('admin.cms.themes.activate');
        Route::post('/cms/themes/{theme}/delete', [CmsThemeController::class, 'delete'])->name('admin.cms.themes.delete');
        Route::get('/cms/themes/{theme}/preview', [CmsThemeController::class, 'preview'])->name('admin.cms.themes.preview');
        Route::get('/cms/themes/{theme}/download', [CmsThemeController::class, 'download'])->name('admin.cms.themes.download');
        Route::post('/cms/themes/import', [CmsThemeController::class, 'import'])->name('admin.cms.themes.import');
        Route::post('/cms/themes/{theme}/versions/{version}/restore', [CmsThemeController::class, 'restoreVersion'])->name('admin.cms.themes.restore-version');
        Route::get('/cms/blocks', [CmsBlockDefinitionController::class, 'index'])->name('admin.cms.blocks.index');
        Route::get('/cms/blocks/create', [CmsBlockDefinitionController::class, 'create'])->name('admin.cms.blocks.create');
        Route::get('/cms/blocks/{block}/edit', [CmsBlockDefinitionController::class, 'edit'])->whereNumber('block')->name('admin.cms.blocks.edit');
        Route::post('/cms/blocks/store', [CmsBlockDefinitionController::class, 'store'])->name('admin.cms.blocks.store-new');
        Route::post('/cms/blocks/{block}/store', [CmsBlockDefinitionController::class, 'store'])->whereNumber('block')->name('admin.cms.blocks.store');
        Route::post('/cms/blocks/{block}/publish', [CmsBlockDefinitionController::class, 'publish'])->whereNumber('block')->name('admin.cms.blocks.publish');
        Route::post('/cms/blocks/{block}/revisions/{revision}/restore', [CmsBlockDefinitionController::class, 'restoreRevision'])->whereNumber(['block', 'revision'])->name('admin.cms.blocks.restore-revision');
        Route::get('/cms/settings', [CmsSettingController::class, 'edit'])->name('admin.cms.settings.edit');
        Route::post('/cms/settings/store', [CmsSettingController::class, 'store'])->name('admin.cms.settings.store');
        Route::post('/cms/settings/modules/{module}/install', [CmsSettingController::class, 'installModule'])->name('admin.cms.settings.modules.install');
        Route::post('/cms/settings/modules/{module}/demo-data', [CmsSettingController::class, 'installModuleDemoData'])->name('admin.cms.settings.modules.demo-data');
        Route::get('/cms/settings/starter-example', [CmsSettingController::class, 'downloadExampleStarter'])->name('admin.cms.settings.starter-example');
        Route::get('/cms/settings/starter-export', [CmsSettingController::class, 'exportStarter'])->name('admin.cms.settings.starter-export');
        Route::post('/cms/settings/starter-import', [CmsSettingController::class, 'importStarter'])->name('admin.cms.settings.starter-import');
        Route::get('/cms/settings/site-package-export', [CmsSettingController::class, 'exportSitePackage'])->name('admin.cms.settings.site-package-export');
        Route::post('/cms/settings/site-package-preview', [CmsSettingController::class, 'previewSitePackage'])->name('admin.cms.settings.site-package-preview');
        Route::post('/cms/settings/site-package-import', [CmsSettingController::class, 'importSitePackage'])->name('admin.cms.settings.site-package-import');
        Route::post('/cms/settings/site-package-activate', [CmsSettingController::class, 'activateSitePackage'])->name('admin.cms.settings.site-package-activate');
        Route::get('/cms/search-console/connect', [CmsSearchConsoleController::class, 'connect'])->name('admin.cms.search-console.connect');
        Route::get('/cms/search-console/callback', [CmsSearchConsoleController::class, 'callback'])->name('admin.cms.search-console.callback');
        Route::post('/cms/search-console/disconnect', [CmsSearchConsoleController::class, 'disconnect'])->name('admin.cms.search-console.disconnect');
        Route::post('/cms/search-console/test', [CmsSearchConsoleController::class, 'test'])->middleware('throttle:10,1')->name('admin.cms.search-console.test');
        Route::get('/cms/statistics/visits', [CmsContentStatisticsController::class, 'visits'])->name('admin.cms.statistics.visits');
        Route::get('/cms/statistics/search-console', [CmsContentStatisticsController::class, 'searchConsole'])->middleware('throttle:30,1')->name('admin.cms.statistics.search-console');

        Route::get('/query-builder', [QueryController::class, 'index'])->name('admin.queries.builder.index');
        Route::get('/query-builder/create', [QueryController::class, 'create'])->name('admin.queries.builder.create');
        Route::get('/query-builder/{query}/template', [QueryController::class, 'template'])->whereNumber('query')->name('admin.queries.builder.template');
        Route::get('/query-builder/{query}/edit', [QueryController::class, 'edit'])->whereNumber('query')->name('admin.queries.builder.edit');
        Route::post('/query-builder/store', [QueryController::class, 'store'])->name('admin.queries.builder.store-new');
        Route::post('/query-builder/{query}/store', [QueryController::class, 'store'])->whereNumber('query')->name('admin.queries.builder.store');
        Route::post('/query-builder/{query}/delete', [QueryController::class, 'delete'])->whereNumber('query')->name('admin.queries.builder.delete');
        Route::post('/query-builder/inspect-sql', [QueryController::class, 'inspectSql'])->name('admin.queries.builder.inspect');
        Route::get('/query-builder/binding-source-options', [QueryController::class, 'bindingSourceOptions'])->name('admin.queries.builder.binding-source-options');

        Route::get('/run/queries/binding-source-options', [QueryRunController::class, 'bindingSourceOptions'])->name('admin.run.queries.binding-source-options');
        Route::get('/run/queries/{query}', [QueryRunController::class, 'show'])->whereNumber('query')->name('admin.run.queries.show');
        Route::post('/run/queries/{query}/data', [QueryRunController::class, 'data'])->whereNumber('query')->name('admin.run.queries.data');
        Route::get('/run/queries/{query}/report', [QueryRunController::class, 'report'])->whereNumber('query')->name('admin.run.queries.report');
        Route::get('/run/queries/{query}/export', [QueryRunController::class, 'export'])->whereNumber('query')->name('admin.run.queries.export');
        Route::get('/run/queries/{query}/chart', [QueryChartController::class, 'show'])->whereNumber('query')->name('admin.run.queries.chart.show');
        Route::post('/run/queries/{query}/chart/preview', [QueryChartController::class, 'preview'])->whereNumber('query')->name('admin.run.queries.chart.preview');

        Route::get('/report-selections/{id}', [QueryLegacyReportController::class, 'selections'])
            ->whereNumber('id')
            ->name('admin.report-selections');
        Route::get('/reports/create/{id}', [QueryLegacyReportController::class, 'create'])
            ->whereNumber('id')
            ->name('admin.reports.create');
        Route::get('/reports/download', [QueryLegacyReportController::class, 'download'])
            ->name('admin.reports.download');
        Route::get('/reports/{id}/template', [QueryLegacyReportController::class, 'template'])
            ->whereNumber('id')
            ->name('admin.reports.template');

        Route::get('/db-diagram', [RwDbDiagramController::class, 'index'])->name('admin.db-diagram');
        Route::get('/db-diagram/sql', [RwDbSqlController::class, 'index'])->name('admin.db-diagram.sql-editor');
        Route::post('/db-diagram/sql/execute', [RwDbSqlController::class, 'executeReadonly'])
            ->middleware('throttle:20,1')
            ->name('admin.db-diagram.sql-execute');
        Route::post('/db-diagram/sql/execute-destructive', [RwDbSqlController::class, 'executeDestructive'])
            ->middleware('throttle:10,1')
            ->name('admin.db-diagram.sql-execute-destructive');
        Route::get('/db-diagram/table/{table}/data', [RwDbTableViewController::class, 'apiData'])->name('admin.db-diagram.table-data');
        Route::get('/db-diagram/table/{table}/export-sql', [RwDbTableViewController::class, 'exportSql'])->name('admin.db-diagram.table-export-sql');
        Route::post('/db-diagram/backup/full/start', [RwDbTableViewController::class, 'startFullBackup'])->name('admin.db-diagram.backup-full.start');
        Route::get('/db-diagram/backup/full/status/{id}', [RwDbTableViewController::class, 'getBackupStatus'])->name('admin.db-diagram.backup-full.status');
        Route::get('/db-diagram/backup/full/download/{id}', [RwDbTableViewController::class, 'downloadBackup'])->name('admin.db-diagram.backup-full.download');
        Route::get('/database-logs', [DatabaseLogController::class, 'index'])->name('admin.database-logs');
        Route::get('/db-diagram/table/{table}/create', [RwDbTableViewController::class, 'createForm'])->name('admin.db-diagram.table-create');
        Route::get('/db-diagram/table/{table}/{id}/edit', [RwDbTableViewController::class, 'editForm'])->name('admin.db-diagram.table-edit-form');
        Route::post('/db-diagram/table/{table}/store', [RwDbTableViewController::class, 'store'])->name('admin.db-diagram.table-store');
        Route::post('/db-diagram/table/{table}/{id}/store', [RwDbTableViewController::class, 'updateForm'])->name('admin.db-diagram.table-update-form');
        Route::post('/db-diagram/table/{table}/analyze-update', [RwDbTableViewController::class, 'analyzeUpdate'])->name('admin.db-diagram.table-analyze-update');
        Route::post('/db-diagram/table/{table}/{id}/analyze-delete', [RwDbTableViewController::class, 'analyzeDelete'])->name('admin.db-diagram.table-analyze-delete');
        Route::patch('/db-diagram/table/{table}/{id}', [RwDbTableViewController::class, 'update'])->name('admin.db-diagram.table-edit');
        Route::delete('/db-diagram/table/{table}/{id}', [RwDbTableViewController::class, 'destroy'])->name('admin.db-diagram.table-delete');

        Route::get('/users', [UserController::class, 'index'])->name('admin.users');
        Route::post('/users/{id}/store', [UserController::class, 'store'])->name('admin.users.store');
        Route::get('/users/{id}/edit', [UserController::class, 'edit'])->name('admin.users.edit');

        Route::get('/roles', [RoleController::class, 'index'])->name('admin.roles');
        Route::post('/roles/{id}/store', [RoleController::class, 'store'])->name('admin.roles.store');
        Route::get('/roles/{id}/edit', [RoleController::class, 'edit'])->name('admin.roles.edit');

        Route::get('/permissions', [PermissionController::class, 'index'])->name('admin.permissions');
        Route::post('/permissions/{id}/store', [PermissionController::class, 'store'])->name('admin.permissions.store');
        Route::get('/permissions/{id}/edit', [PermissionController::class, 'edit'])->name('admin.permissions.edit');
    });

require __DIR__.'/auth.php';

Route::middleware('tenant.resolve')->group(function (): void {
    Route::get('/{slug}', [CmsPublicPageController::class, 'show'])
        ->where('slug', '[A-Za-z0-9\-]+')
        ->name('cms.public.show');
    Route::get('/{path}', [CmsPublicPageController::class, 'showPath'])
        ->where('path', '[A-Za-z0-9\-/]+')
        ->name('cms.public.path');
});
