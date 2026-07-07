<?php

namespace App\Http\Controllers\Admin\Run\Action;

use App\Actions\Admin\Run\Core\InvokeScreenAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Run\InvokeScreenActionRequest;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Throwable;

class ActionInvokeController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function __invoke(
        InvokeScreenActionRequest $request,
        InvokeScreenAction $invokeScreenAction,
    ): JsonResponse {
        $validated = $request->validated();

        try {
            $result = $invokeScreenAction->handle($validated, $request);
        } catch (Throwable $throwable) {
            $this->auditLogger->failure(
                action: 'run.action.invoke.failed',
                module: 'run_action',
                subjectType: 'screen_action',
                subjectKey: trim((string) ($validated['action_key'] ?? '')) ?: null,
                message: trim($throwable->getMessage()) !== ''
                    ? trim($throwable->getMessage())
                    : 'Screen action invoke mislukt.',
                meta: [
                    'screen_key' => (string) data_get($validated, 'context.screen_key', ''),
                    'block_id' => (string) data_get($validated, 'context.block_id', ''),
                ],
                request: $request,
            );

            throw $throwable;
        }

        if ($result->status === 'success') {
            $this->auditLogger->success(
                action: 'run.action.invoke',
                module: 'run_action',
                subjectType: 'screen_action',
                subjectKey: trim((string) ($validated['action_key'] ?? '')) ?: null,
                message: $result->message,
                meta: [
                    'http_status' => $result->httpStatus,
                    'screen_key' => (string) data_get($validated, 'context.screen_key', ''),
                    'block_id' => (string) data_get($validated, 'context.block_id', ''),
                ],
                request: $request,
            );
        } else {
            $this->auditLogger->failure(
                action: 'run.action.invoke.failed',
                module: 'run_action',
                subjectType: 'screen_action',
                subjectKey: trim((string) ($validated['action_key'] ?? '')) ?: null,
                message: $result->message,
                meta: [
                    'http_status' => $result->httpStatus,
                    'screen_key' => (string) data_get($validated, 'context.screen_key', ''),
                    'block_id' => (string) data_get($validated, 'context.block_id', ''),
                ],
                request: $request,
            );
        }

        return response()->json($result->toArray(), $result->httpStatus);
    }
}
