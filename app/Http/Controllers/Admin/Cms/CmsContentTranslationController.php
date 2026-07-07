<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Actions\Admin\Cms\BuildCmsContentTranslationMatrixAction;
use App\Actions\Admin\Cms\CreateCmsCategoryTranslationAction;
use App\Actions\Admin\Cms\CreateCmsFormTranslationAction;
use App\Actions\Admin\Cms\CreateCmsMenuItemTranslationAction;
use App\Actions\Admin\Cms\CreateCmsPageTranslationAction;
use App\Actions\Admin\Cms\CreateCmsPostTranslationAction;
use App\Actions\Admin\Cms\CreateCmsTagTranslationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cms\BulkCreateCmsContentTranslationsRequest;
use App\Http\Requests\Admin\Cms\CreateCmsContentTranslationRequest;
use App\Http\Requests\Admin\Cms\MarkCmsContentTranslationReviewedRequest;
use App\Models\Cms\CmsCategory;
use App\Models\Cms\CmsForm;
use App\Models\Cms\CmsMenuItem;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPost;
use App\Models\Cms\CmsTag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Throwable;

class CmsContentTranslationController extends Controller
{
    public function rows(BuildCmsContentTranslationMatrixAction $buildMatrix): JsonResponse
    {
        return response()->json($buildMatrix->handle());
    }

    public function store(
        CreateCmsContentTranslationRequest $request,
        CreateCmsPageTranslationAction $createPageTranslation,
        CreateCmsPostTranslationAction $createPostTranslation,
        CreateCmsCategoryTranslationAction $createCategoryTranslation,
        CreateCmsTagTranslationAction $createTagTranslation,
        CreateCmsFormTranslationAction $createFormTranslation,
        CreateCmsMenuItemTranslationAction $createMenuItemTranslation,
    ): JsonResponse {
        $validated = $request->validated();
        $type = (string) $validated['type'];
        $sourceId = (int) $validated['source_id'];
        $targetLocale = (string) $validated['target_locale'];
        $useAi = (bool) ($validated['use_ai'] ?? false);
        $userId = (int) $request->user()->id;
        $source = $this->source($type, $sourceId);

        $existingTranslation = $this->existingTranslation($type, $source, $targetLocale);

        if ($existingTranslation instanceof Model) {
            return response()->json([
                'message' => __('cms_admin_ui.flash.translation_created'),
                'url' => $this->editUrl($type, (int) $existingTranslation->getKey(), $existingTranslation),
            ]);
        }

        $translation = $this->createTranslation(
            $type,
            $source,
            $targetLocale,
            $useAi,
            $userId,
            $createPageTranslation,
            $createPostTranslation,
            $createCategoryTranslation,
            $createTagTranslation,
            $createFormTranslation,
            $createMenuItemTranslation,
        );

        return response()->json([
            'message' => $useAi
                ? __('cms_admin_ui.flash.translation_created_ai')
                : __('cms_admin_ui.flash.translation_created'),
            'url' => $this->editUrl($type, (int) $translation->getKey(), $translation),
        ]);
    }

    public function bulkAi(
        BulkCreateCmsContentTranslationsRequest $request,
        CreateCmsPageTranslationAction $createPageTranslation,
        CreateCmsPostTranslationAction $createPostTranslation,
        CreateCmsCategoryTranslationAction $createCategoryTranslation,
        CreateCmsTagTranslationAction $createTagTranslation,
        CreateCmsFormTranslationAction $createFormTranslation,
        CreateCmsMenuItemTranslationAction $createMenuItemTranslation,
    ): JsonResponse {
        $validated = $request->validated();
        $targetLocale = (string) $validated['target_locale'];
        $limit = (int) $validated['limit'];
        $userId = (int) $request->user()->id;
        $created = 0;
        $skipped = 0;
        $failed = 0;

        $items = collect($validated['items'])
            ->unique(fn (array $item): string => $item['type'].':'.$item['source_id'])
            ->take($limit);

        foreach ($items as $item) {
            try {
                $type = (string) $item['type'];
                $source = $this->source($type, (int) $item['source_id']);

                if ($this->existingTranslation($type, $source, $targetLocale) instanceof Model) {
                    $skipped++;

                    continue;
                }

                $this->createTranslation(
                    $type,
                    $source,
                    $targetLocale,
                    true,
                    $userId,
                    $createPageTranslation,
                    $createPostTranslation,
                    $createCategoryTranslation,
                    $createTagTranslation,
                    $createFormTranslation,
                    $createMenuItemTranslation,
                );
                $created++;
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
            }
        }

        $status = $created === 0 && $failed > 0 ? 422 : 200;

        return response()->json([
            'message' => __('translation_editor_ui.feedback.content_bulk_ai_success', [
                'created' => $created,
                'skipped' => $skipped,
                'failed' => $failed,
            ]),
            'error_message' => $status === 422
                ? __('translation_editor_ui.feedback.content_bulk_ai_failed_count', ['failed' => $failed])
                : null,
            'created' => $created,
            'skipped' => $skipped,
            'failed' => $failed,
        ], $status);
    }

    public function markReviewed(MarkCmsContentTranslationReviewedRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $record = $request->record();
        $field = (string) $validated['type'] === 'menu_item' ? 'metadata' : 'settings';
        $data = (array) ($record->getAttribute($field) ?? []);

        $data['translation_source'] = 'ai';
        $data['translation_review_status'] = 'reviewed';

        $record->forceFill([$field => $data])->save();

        return response()->json([
            'message' => __('cms_admin_ui.flash.ai_translation_reviewed'),
        ]);
    }

    private function editUrl(string $type, int $id, object $translation): string
    {
        return match ($type) {
            'page' => route('admin.cms.pages.edit', ['id' => $id]),
            'post' => route('admin.cms.posts.edit', ['id' => $id]),
            'category' => route('admin.cms.categories.edit', ['id' => $id]),
            'tag' => route('admin.cms.tags.edit', ['id' => $id]),
            'form' => route('admin.cms.forms.edit', ['id' => $id]),
            'menu_item' => route('admin.cms.menus.edit', ['id' => $translation->cms_menu_id, 'item' => $id]),
        };
    }

    private function source(string $type, int $sourceId): Model
    {
        return match ($type) {
            'page' => CmsPage::query()->findOrFail($sourceId),
            'post' => CmsPost::query()->findOrFail($sourceId),
            'category' => CmsCategory::query()->with('landingPage')->findOrFail($sourceId),
            'tag' => CmsTag::query()->with('landingPage')->findOrFail($sourceId),
            'form' => CmsForm::query()->with('fields')->findOrFail($sourceId),
            'menu_item' => CmsMenuItem::query()->findOrFail($sourceId),
        };
    }

    private function existingTranslation(string $type, Model $source, string $targetLocale): ?Model
    {
        if ((string) $source->getAttribute('locale') === $targetLocale) {
            return $source;
        }

        $translationKey = (string) $source->getAttribute('translation_key');

        if ($translationKey === '') {
            return null;
        }

        $query = match ($type) {
            'page' => CmsPage::query(),
            'post' => CmsPost::query(),
            'category' => CmsCategory::query(),
            'tag' => CmsTag::query(),
            'form' => CmsForm::query(),
            'menu_item' => CmsMenuItem::query()
                ->where('cms_menu_id', $source->getAttribute('cms_menu_id')),
        };

        return $query
            ->where('translation_key', $translationKey)
            ->where('locale', $targetLocale)
            ->first();
    }

    private function createTranslation(
        string $type,
        Model $source,
        string $targetLocale,
        bool $useAi,
        int $userId,
        CreateCmsPageTranslationAction $createPageTranslation,
        CreateCmsPostTranslationAction $createPostTranslation,
        CreateCmsCategoryTranslationAction $createCategoryTranslation,
        CreateCmsTagTranslationAction $createTagTranslation,
        CreateCmsFormTranslationAction $createFormTranslation,
        CreateCmsMenuItemTranslationAction $createMenuItemTranslation,
    ): Model {
        return match ($type) {
            'page' => $createPageTranslation->handle($source, $targetLocale, $userId, $useAi),
            'post' => $createPostTranslation->handle($source, $targetLocale, $userId, $useAi),
            'category' => $createCategoryTranslation->handle($source, $targetLocale, $userId, $useAi),
            'tag' => $createTagTranslation->handle($source, $targetLocale, $userId, $useAi),
            'form' => $createFormTranslation->handle($source, $targetLocale, $useAi),
            'menu_item' => $createMenuItemTranslation->handle($source, $targetLocale, $useAi),
        };
    }
}
