<?php

namespace App\Http\Controllers\Admin\Dev;

use App\Actions\Admin\Cms\BuildCmsContentTranslationMatrixAction;
use App\Actions\Admin\Security\SyncAclSecurityTranslationsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Dev\Translation\AddLocaleTranslationRequest;
use App\Http\Requests\Admin\Dev\Translation\AiFillTranslationsRequest;
use App\Http\Requests\Admin\Dev\Translation\ListTranslationsRequest;
use App\Http\Requests\Admin\Dev\Translation\SyncTranslationsRequest;
use App\Http\Requests\Admin\Dev\Translation\UpdateTranslationValueRequest;
use App\Support\Ai\AiProviderSettings;
use App\Support\PublicSite\CmsLocalePermission;
use App\Support\Translations\PublicTextTranslationManager;
use App\Support\Translations\TranslationAiFiller;
use App\Support\Translations\TranslationManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TranslationController extends Controller
{
    public function index(
        Request $request,
        TranslationManager $manager,
        PublicTextTranslationManager $publicTextManager,
        AiProviderSettings $providerSettings,
        CmsLocalePermission $cmsLocalePermission,
        BuildCmsContentTranslationMatrixAction $buildContentTranslationMatrix,
    ): Response {
        $translationSettings = $providerSettings->translationSettings();
        $isPlatform = Str::startsWith((string) $request->route()?->getName(), 'platform.');
        $contentMatrix = $isPlatform ? ['rows' => [], 'locales' => []] : $buildContentTranslationMatrix->handle();

        return Inertia::render('Admin/Translation/TranslationTable', [
            'mode' => $isPlatform ? 'platform' : 'site',
            'rows' => $isPlatform ? $manager->rows('all') : [],
            'locales' => $manager->locales(),
            'sources' => $manager->sources(),
            'public_rows' => $isPlatform ? [] : $publicTextManager->rows(),
            'public_locales' => $isPlatform ? [] : $publicTextManager->locales(),
            'content_rows' => $contentMatrix['rows'],
            'content_locales' => $contentMatrix['locales'],
            'editable_content_locales' => $isPlatform ? [] : $cmsLocalePermission->editableLocales($request->user()),
            'active_source' => 'all',
            'default_source_locale' => (string) config('translation_editor.source_locale', 'en'),
            'ai_defaults' => [
                'fill_limit_default' => (int) ($translationSettings['fill_limit_default'] ?? 100),
                'fill_limit_max' => (int) ($translationSettings['fill_limit_max'] ?? 500),
            ],
        ]);
    }

    public function rows(
        ListTranslationsRequest $request,
        TranslationManager $manager,
    ): JsonResponse {
        return response()->json([
            'rows' => $manager->rows('all'),
            'locales' => $manager->locales(),
            'sources' => $manager->sources(),
        ]);
    }

    public function update(
        UpdateTranslationValueRequest $request,
        string $row,
        TranslationManager $manager,
    ): JsonResponse {
        $validated = $request->validated();
        $locale = (string) $validated['locale'];
        $value = (string) ($validated['value'] ?? '');
        $updatedRow = $manager->updateByRowId($row, $locale, $value);

        return response()->json([
            'id' => $updatedRow['id'],
            'data' => $updatedRow,
            'row' => $updatedRow,
            'message' => __('translation_editor_ui.feedback.translation_saved'),
        ]);
    }

    public function sync(
        SyncTranslationsRequest $request,
        TranslationManager $manager,
        SyncAclSecurityTranslationsAction $syncAclSecurityTranslations,
    ): JsonResponse {
        $validated = $request->validated();
        $sourceLocale = (string) config('translation_editor.source_locale', 'en');
        $targets = array_values((array) ($validated['target_locales'] ?? []));
        $aclResult = $syncAclSecurityTranslations->handle($sourceLocale);
        $result = $manager->syncMissing(
            'all',
            $sourceLocale,
            $targets,
            [
                'admin_security_ui' => (array) ($aclResult['acl_created_keys'] ?? []),
            ],
        );

        return response()->json([
            'message' => __('translation_editor_ui.feedback.translations_synced'),
            'result' => array_merge($result, $aclResult),
            'rows' => $manager->rows('all'),
            'locales' => $manager->locales(),
        ]);
    }

    public function addLocale(
        AddLocaleTranslationRequest $request,
        TranslationManager $manager,
    ): JsonResponse {
        $validated = $request->validated();
        $result = $manager->addLocale(
            (string) $validated['locale'],
            isset($validated['source_locale']) && $validated['source_locale'] !== null
                ? (string) $validated['source_locale']
                : null,
        );

        return response()->json([
            'message' => __('translation_editor_ui.feedback.locale_added'),
            'result' => $result,
            'rows' => $manager->rows('all'),
            'locales' => $manager->locales(),
            'sources' => $manager->sources(),
        ]);
    }

    public function aiFill(
        AiFillTranslationsRequest $request,
        TranslationAiFiller $translationAiFiller,
        TranslationManager $manager,
        AiProviderSettings $providerSettings,
    ): JsonResponse {
        $validated = $request->validated();
        $translationSettings = $providerSettings->translationSettings();

        $result = $translationAiFiller->fillMissing(
            targetLocale: (string) $validated['target_locale'],
            sourceLocale: isset($validated['source_locale'])
                ? (string) $validated['source_locale']
                : null,
            limit: (int) ($validated['limit'] ?? (int) ($translationSettings['fill_limit_default'] ?? 100)),
        );

        return response()->json([
            'message' => __('translation_editor_ui.feedback.ai_fill_success', [
                'updated' => (int) ($result['updated'] ?? 0),
                'requested' => (int) ($result['requested'] ?? 0),
            ]),
            'result' => $result,
            'rows' => $manager->rows('all'),
            'locales' => $manager->locales(),
        ]);
    }
}
