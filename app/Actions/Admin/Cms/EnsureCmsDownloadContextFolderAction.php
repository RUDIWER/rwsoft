<?php

namespace App\Actions\Admin\Cms;

use App\Models\Cms\CmsDownloadFolder;
use Illuminate\Support\Str;

class EnsureCmsDownloadContextFolderAction
{
    /**
     * @var array<string, array{group: string, record: string}>
     */
    private const CONTEXTS = [
        'page' => ['group' => 'pages', 'record' => 'page'],
        'post' => ['group' => 'blog', 'record' => 'post'],
        'category' => ['group' => 'categories', 'record' => 'category'],
        'tag' => ['group' => 'tags', 'record' => 'tag'],
    ];

    public function handle(?string $contextType, mixed $contextId): ?CmsDownloadFolder
    {
        $contextType = trim((string) $contextType);
        $contextId = (int) $contextId;

        if (! array_key_exists($contextType, self::CONTEXTS) || $contextId <= 0) {
            return null;
        }

        $root = $this->folder(
            name: (string) __('cms_admin_ui.downloads.context_folders.root'),
            parentId: null,
        );
        $group = $this->folder(
            name: (string) __('cms_admin_ui.downloads.context_folders.groups.'.self::CONTEXTS[$contextType]['group']),
            parentId: (int) $root->id,
        );

        return $this->folder(
            name: (string) __('cms_admin_ui.downloads.context_folders.records.'.self::CONTEXTS[$contextType]['record'], ['id' => $contextId]),
            parentId: (int) $group->id,
        );
    }

    private function folder(string $name, ?int $parentId): CmsDownloadFolder
    {
        $slug = Str::slug($name) ?: 'folder';
        $folder = CmsDownloadFolder::query()
            ->where('parent_id', $parentId)
            ->where('slug', $slug)
            ->first();

        if ($folder instanceof CmsDownloadFolder) {
            return $folder;
        }

        return CmsDownloadFolder::query()->create([
            'parent_id' => $parentId,
            'name' => $name,
            'slug' => $slug,
            'access_mode' => 'inherit',
            'sort_order' => ((int) CmsDownloadFolder::query()
                ->where('parent_id', $parentId)
                ->max('sort_order')) + 1,
        ]);
    }
}
