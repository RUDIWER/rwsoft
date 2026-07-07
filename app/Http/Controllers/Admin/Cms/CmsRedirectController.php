<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cms\StoreCmsRedirectRequest;
use App\Models\Cms\CmsRedirect;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class CmsRedirectController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Cms/Redirects/Index', [
            'redirects' => CmsRedirect::query()
                ->orderBy('locale')
                ->orderBy('source_path')
                ->get(['id', 'source_path', 'target_url', 'status_code', 'locale', 'is_active', 'starts_at', 'ends_at', 'hit_count', 'updated_at']),
        ]);
    }

    public function create(): Response
    {
        return $this->edit(0);
    }

    public function edit(int $id): Response
    {
        $redirect = $id > 0 ? CmsRedirect::query()->findOrFail($id) : null;

        return Inertia::render('Admin/Cms/Redirects/Edit', [
            'redirectItem' => $redirect ? $this->redirectPayload($redirect) : null,
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function store(StoreCmsRedirectRequest $request, int $id): RedirectResponse
    {
        $validated = $request->validated();
        $redirect = $id > 0 ? CmsRedirect::query()->findOrFail($id) : new CmsRedirect;
        $isCreate = ! $redirect->exists;

        $redirect->fill(array_merge(
            Arr::only($validated, ['source_path', 'target_url', 'status_code', 'starts_at', 'ends_at']),
            [
                'locale' => blank($validated['locale'] ?? null) ? null : $validated['locale'],
                'is_active' => (bool) ($validated['is_active'] ?? false),
            ],
        ));
        $redirect->save();

        $this->auditLogger->success(
            action: $isCreate ? 'cms.redirect.create' : 'cms.redirect.update',
            module: 'cms',
            subjectType: 'cms_redirect',
            subjectKey: (string) $redirect->id,
            message: __('cms_admin_ui.flash.saved.redirect'),
            meta: ['source_path' => (string) $redirect->source_path],
            request: $request,
        );

        return redirect()
            ->route('admin.cms.redirects.edit', ['id' => $redirect->id])
            ->with('status', __('cms_admin_ui.flash.saved.redirect'));
    }

    /**
     * @return array<string, mixed>
     */
    private function redirectPayload(CmsRedirect $redirect): array
    {
        return [
            'id' => $redirect->id,
            'source_path' => $redirect->source_path,
            'target_url' => $redirect->target_url,
            'status_code' => $redirect->status_code,
            'locale' => $redirect->locale,
            'is_active' => (bool) $redirect->is_active,
            'starts_at' => optional($redirect->starts_at)->format('Y-m-d\TH:i'),
            'ends_at' => optional($redirect->ends_at)->format('Y-m-d\TH:i'),
            'created_at' => $redirect->created_at?->toIso8601String(),
            'updated_at' => $redirect->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    private function statusOptions(): array
    {
        return [
            ['value' => 301, 'label' => '301 '.__('cms_admin_ui.redirect_status.permanent')],
            ['value' => 302, 'label' => '302 '.__('cms_admin_ui.redirect_status.temporary')],
            ['value' => 307, 'label' => '307 '.__('cms_admin_ui.redirect_status.temporary_method_safe')],
            ['value' => 308, 'label' => '308 '.__('cms_admin_ui.redirect_status.permanent_method_safe')],
        ];
    }
}
