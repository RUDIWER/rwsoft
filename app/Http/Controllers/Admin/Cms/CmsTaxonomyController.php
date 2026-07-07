<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsTag;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CmsTaxonomyController extends Controller
{
    public function index(Request $request): Response
    {
        $activeTab = in_array($request->query('tab'), ['categories', 'tags'], true)
            ? (string) $request->query('tab')
            : 'categories';

        return Inertia::render('Admin/Cms/Taxonomy/Index', [
            'activeTab' => $activeTab,
            'categories' => CmsCategory::query()
                ->with('parent:id,title')
                ->withCount('posts')
                ->orderBy('type')
                ->orderBy('locale')
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get(['id', 'parent_id', 'type', 'title', 'slug', 'locale', 'description', 'sort_order', 'is_active', 'updated_at']),
            'tags' => CmsTag::query()
                ->withCount('posts')
                ->orderBy('locale')
                ->orderBy('title')
                ->get(['id', 'title', 'slug', 'locale', 'description', 'is_active', 'updated_at']),
        ]);
    }
}
