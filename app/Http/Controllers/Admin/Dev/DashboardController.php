<?php

namespace App\Http\Controllers\Admin\Dev;

use App\Http\Controllers\Controller;
use App\Models\Cms\CmsForm;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPost;
use App\Models\Security\AclPermission;
use App\Models\Security\AclRole;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'users' => User::count(),
                'roles' => AclRole::count(),
                'permissions' => AclPermission::count(),
                'published_pages' => CmsPage::query()->where('status', 'published')->count(),
                'published_posts' => CmsPost::query()->where('status', 'published')->count(),
                'active_forms' => CmsForm::query()->where('is_active', true)->count(),
            ],
            'recentUsers' => User::query()
                ->latest()
                ->limit(5)
                ->get(['id', 'name', 'email', 'created_at']),
        ]);
    }
}
