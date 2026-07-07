<?php

namespace App\Http\Controllers\Admin\Dev;

use App\Actions\Admin\Run\Core\ClientValidationRuleCodeManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Dev\ClientValidationRule\FetchClientValidationRuleCodeRequest;
use App\Http\Requests\Admin\Dev\ClientValidationRule\PublishClientValidationRuleVersionRequest;
use App\Http\Requests\Admin\Dev\ClientValidationRule\SaveClientValidationRuleVersionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientValidationRuleController extends Controller
{
    public function index(ClientValidationRuleCodeManager $manager): Response
    {
        $bootstrap = $manager->bootstrap();

        return Inertia::render('Admin/Validation/ClientRulesEditor', [
            'versions' => $bootstrap['versions'],
            'selected_version' => $bootstrap['selected_version'],
            'code' => $bootstrap['code'],
        ]);
    }

    public function code(
        FetchClientValidationRuleCodeRequest $request,
        ClientValidationRuleCodeManager $manager,
    ): JsonResponse {
        $version = $manager->readCode((int) $request->validated('version_id'));

        return response()->json([
            'version' => $version,
        ]);
    }

    public function save(
        SaveClientValidationRuleVersionRequest $request,
        ClientValidationRuleCodeManager $manager,
    ): JsonResponse {
        $saved = $manager->saveDraft(
            (string) $request->validated('code'),
            $this->currentUserId($request),
        );

        return response()->json([
            'message' => 'Client validatie regelversie bewaard.',
            'version' => $saved['version'],
            'build' => $saved['build'],
            'versions' => $manager->list(),
        ]);
    }

    public function publish(
        PublishClientValidationRuleVersionRequest $request,
        ClientValidationRuleCodeManager $manager,
    ): JsonResponse {
        $published = $manager->publish(
            (int) $request->validated('version_id'),
            $this->currentUserId($request),
        );

        return response()->json([
            'message' => 'Client validatie regelversie gepubliceerd.',
            'version' => $published['version'],
            'build' => $published['build'],
            'versions' => $manager->list(),
        ]);
    }

    private function currentUserId(Request $request): ?int
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        return (int) $user->getAuthIdentifier();
    }
}
