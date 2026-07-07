<?php

namespace App\Http\Controllers\Admin\Dev;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Dev\Translation\AiFillPublicTextTranslationsRequest;
use App\Http\Requests\Admin\Dev\Translation\SyncPublicTextTranslationsRequest;
use App\Http\Requests\Admin\Dev\Translation\UpdatePublicTextTranslationValueRequest;
use App\Support\Ai\AiProviderSettings;
use App\Support\Translations\PublicTextTranslationAiFiller;
use App\Support\Translations\PublicTextTranslationManager;
use Illuminate\Http\JsonResponse;

class PublicTextTranslationController extends Controller
{
    public function rows(PublicTextTranslationManager $manager): JsonResponse
    {
        return response()->json([
            'rows' => $manager->rows(),
            'locales' => $manager->locales(),
        ]);
    }

    public function update(
        UpdatePublicTextTranslationValueRequest $request,
        string $row,
        PublicTextTranslationManager $manager,
    ): JsonResponse {
        $validated = $request->validated();
        $updatedRow = $manager->updateByRowId(
            $row,
            (string) $validated['locale'],
            (string) ($validated['value'] ?? ''),
        );

        return response()->json([
            'id' => $updatedRow['id'],
            'data' => $updatedRow,
            'row' => $updatedRow,
            'message' => __('translation_editor_ui.feedback.translation_saved'),
        ]);
    }

    public function sync(
        SyncPublicTextTranslationsRequest $request,
        PublicTextTranslationManager $manager,
    ): JsonResponse {
        $result = $manager->syncMissing();
        $hardcodedCount = count((array) ($result['hardcoded_warnings'] ?? []));
        $unusedCount = count((array) ($result['unused_warnings'] ?? []));
        $changedDefaultCount = count((array) ($result['changed_default_warnings'] ?? []));
        $warningMessage = $hardcodedCount > 0 || $unusedCount > 0 || $changedDefaultCount > 0
            ? __('translation_editor_ui.feedback.public_sync_warnings', [
                'hardcoded' => $hardcodedCount,
                'unused' => $unusedCount,
                'changed' => $changedDefaultCount,
            ])
            : null;

        return response()->json([
            'message' => __('translation_editor_ui.feedback.public_sync_success', [
                'keys_found' => (int) ($result['keys_found'] ?? 0),
                'texts_created' => (int) ($result['texts_created'] ?? 0),
                'translations_created' => (int) ($result['translations_created'] ?? 0),
            ]),
            'warning_message' => $warningMessage,
            'result' => $result,
            'rows' => $manager->rows(),
            'locales' => $manager->locales(),
        ]);
    }

    public function aiFill(
        AiFillPublicTextTranslationsRequest $request,
        PublicTextTranslationAiFiller $translationAiFiller,
        PublicTextTranslationManager $manager,
        AiProviderSettings $providerSettings,
    ): JsonResponse {
        $validated = $request->validated();
        $translationSettings = $providerSettings->translationSettings();
        $result = $translationAiFiller->fillMissing(
            targetLocale: (string) $validated['target_locale'],
            sourceLocale: isset($validated['source_locale']) ? (string) $validated['source_locale'] : null,
            limit: (int) ($validated['limit'] ?? (int) ($translationSettings['fill_limit_default'] ?? 100)),
        );

        return response()->json([
            'message' => __('translation_editor_ui.feedback.ai_fill_success', [
                'updated' => (int) ($result['updated'] ?? 0),
                'requested' => (int) ($result['requested'] ?? 0),
            ]),
            'result' => $result,
            'rows' => $manager->rows(),
            'locales' => $manager->locales(),
        ]);
    }
}
