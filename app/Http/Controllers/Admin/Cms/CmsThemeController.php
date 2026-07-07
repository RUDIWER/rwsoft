<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Actions\Admin\Cms\Themes\CompileThemeCssAction;
use App\Actions\Admin\Cms\Themes\ExportThemeZipAction;
use App\Actions\Admin\Cms\Themes\GenerateThemeCssFromSettingsAction;
use App\Actions\Admin\Cms\Themes\ImportThemeZipAction;
use App\Actions\Admin\Cms\Themes\PublishThemeAction;
use App\Actions\Admin\Cms\Themes\ValidateThemeCssAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cms\ImportCmsThemeRequest;
use App\Http\Requests\Admin\Cms\StoreCmsThemeRequest;
use App\Models\Cms\CmsTheme;
use App\Models\Cms\CmsThemeVersion;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CmsThemeController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly CompileThemeCssAction $compileThemeCss,
        private readonly ExportThemeZipAction $exportThemeZip,
        private readonly GenerateThemeCssFromSettingsAction $generateCssFromSettings,
        private readonly ImportThemeZipAction $importThemeZip,
        private readonly PublishThemeAction $publishTheme,
        private readonly ValidateThemeCssAction $validateThemeCss,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Cms/Themes/Index', [
            'themes' => CmsTheme::query()
                ->with('activeVersion')
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get()
                ->map(fn (CmsTheme $theme): array => $this->themePayload($theme))
                ->values(),
        ]);
    }

    public function create(): Response
    {
        return $this->renderEdit(null);
    }

    public function edit(int $theme): Response
    {
        return $this->renderEdit(CmsTheme::query()->findOrFail($theme));
    }

    public function store(StoreCmsThemeRequest $request, ?int $theme = null): RedirectResponse
    {
        return $this->storeTheme($request, $theme ? CmsTheme::query()->findOrFail($theme) : null);
    }

    public function publish(int $theme): RedirectResponse
    {
        $themeModel = CmsTheme::query()->findOrFail($theme);
        $version = $themeModel->versions()->first();

        if (! $version instanceof CmsThemeVersion) {
            return back()->with('error', __('cms_admin_ui.flash.theme_no_publishable_css'));
        }

        $this->publishTheme->handle($themeModel, $version);

        return redirect()
            ->route('admin.cms.themes.index')
            ->with('status', __('cms_admin_ui.flash.theme_published'));
    }

    public function activate(int $theme): RedirectResponse
    {
        return $this->publish($theme);
    }

    public function preview(int $theme): RedirectResponse
    {
        $themeModel = CmsTheme::query()->findOrFail($theme);
        $version = $themeModel->activeVersion ?: $themeModel->versions()->first();

        if (! $version instanceof CmsThemeVersion) {
            return back()->with('error', __('cms_admin_ui.flash.theme_no_previewable_css'));
        }

        return redirect()->route('cms.public.home', [
            'theme_preview' => $themeModel->id,
            'theme_version' => $version->version_hash,
        ]);
    }

    public function delete(int $theme): RedirectResponse
    {
        $themeModel = CmsTheme::query()->findOrFail($theme);

        if ($themeModel->is_active) {
            return back()->with('error', __('cms_admin_ui.flash.theme_active_delete_blocked'));
        }

        Storage::disk((string) config('cms_themes.storage_disk', 'local'))
            ->deleteDirectory('sites/'.TenantContext::siteId().'/themes/'.$themeModel->key);

        $themeModel->delete();

        return redirect()
            ->route('admin.cms.themes.index')
            ->with('status', __('cms_admin_ui.flash.deleted.theme'));
    }

    public function download(int $theme): BinaryFileResponse
    {
        $export = $this->exportThemeZip->handle(CmsTheme::query()->findOrFail($theme));

        return response()
            ->download($export['path'], $export['filename'], ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    public function import(ImportCmsThemeRequest $request): RedirectResponse
    {
        $result = $this->importThemeZip->handle($request->file('theme_zip'), $request->user()?->id);
        $theme = $result['theme'];

        $redirect = redirect()->route('admin.cms.themes.edit', ['theme' => $theme->id]);

        if (($result['validation']['warnings'] ?? []) !== []) {
            return $redirect->with('warning', __('cms_admin_ui.flash.theme_imported_with_external_assets'));
        }

        return $redirect->with('status', __('cms_admin_ui.flash.theme_imported'));
    }

    public function restoreVersion(int $theme, int $version): RedirectResponse
    {
        $themeModel = CmsTheme::query()->findOrFail($theme);
        $versionModel = CmsThemeVersion::query()->findOrFail($version);

        abort_unless($versionModel->cms_theme_id === $themeModel->id, 404);

        $themeModel->forceFill([
            'active_version_id' => $versionModel->id,
        ])->save();

        return redirect()
            ->route('admin.cms.themes.edit', ['theme' => $themeModel->id])
            ->with('status', __('cms_admin_ui.flash.theme_version_restored'));
    }

    private function renderEdit(?CmsTheme $theme = null): Response
    {
        $theme?->load(['activeVersion', 'versions']);

        return Inertia::render('Admin/Cms/Themes/Edit', [
            'themeItem' => $theme ? $this->themePayload($theme) : null,
            'developerCss' => $theme ? $this->developerCss($theme) : $this->defaultDeveloperCss(),
            'themeSettings' => $theme ? $this->themeSettings($theme) : [],
            'settingsFields' => $this->settingsFields(),
            'versions' => $theme
                ? $theme->versions->map(fn (CmsThemeVersion $version): array => $this->versionPayload($version, $theme))->values()
                : [],
        ]);
    }

    private function storeTheme(StoreCmsThemeRequest $request, ?CmsTheme $theme = null): RedirectResponse
    {
        $validated = $request->validated();
        $developerCss = (string) ($validated['developer_css'] ?? '');
        $settings = $this->validatedThemeSettings($validated['theme_settings'] ?? []);
        $validation = $this->validateThemeCss->handle($this->generateCssFromSettings->handle($settings)."\n".$developerCss);

        if (! $validation['valid']) {
            return back()
                ->withInput()
                ->withErrors(['developer_css' => $this->validationMessages($validation['errors'])])
                ->with('error', __('cms_admin_ui.flash.theme_css_forbidden'));
        }

        $existingTheme = CmsTheme::query()
            ->where('key', $validated['key'])
            ->when($theme instanceof CmsTheme, fn ($query) => $query->where('id', '!=', $theme->id))
            ->first();

        if ($existingTheme instanceof CmsTheme) {
            return back()
                ->withInput()
                ->withErrors(['key' => __('cms_admin_ui.flash.theme_duplicate_key_field')])
                ->with('error', __('cms_admin_ui.flash.theme_duplicate_key'));
        }

        $theme = $theme ?: new CmsTheme;
        $isCreate = ! $theme->exists;
        $theme->fill([
            'name' => $validated['name'],
            'key' => $validated['key'],
            'description' => $validated['description'] ?? null,
            'author' => $validated['author'] ?? null,
            'version' => $validated['version'],
            'status' => $theme->status ?: 'draft',
            'created_by' => $theme->created_by ?: $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ])->save();

        $result = $this->compileThemeCss->handle($theme, $developerCss, $settings, [], $request->user()?->id);

        $this->auditLogger->success(
            action: $isCreate ? 'cms.theme.create' : 'cms.theme.update',
            module: 'cms',
            subjectType: 'cms_theme',
            subjectKey: (string) $theme->id,
            message: __('cms_admin_ui.flash.theme_css_saved'),
            meta: ['key' => (string) $theme->key],
            request: $request,
        );

        $redirect = redirect()->route('admin.cms.themes.edit', ['theme' => $theme->id]);

        if (($result['validation']['warnings'] ?? []) !== []) {
            return $redirect->with('warning', __('cms_admin_ui.flash.theme_css_saved_with_external_assets'));
        }

        return $redirect->with('status', __('cms_admin_ui.flash.theme_css_saved'));
    }

    /**
     * @return array<string, mixed>
     */
    private function themePayload(CmsTheme $theme): array
    {
        return [
            'id' => $theme->id,
            'key' => $theme->key,
            'name' => $theme->name,
            'description' => $theme->description,
            'author' => $theme->author,
            'version' => $theme->version,
            'status' => $theme->status,
            'is_active' => (bool) $theme->is_active,
            'active_version_hash' => $theme->activeVersion?->version_hash,
            'active_version_id' => $theme->active_version_id,
            'preview_url' => $theme->exists ? route('admin.cms.themes.preview', ['theme' => $theme->id]) : null,
            'updated_at' => optional($theme->updated_at)->toDateTimeString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function versionPayload(CmsThemeVersion $version, CmsTheme $theme): array
    {
        return [
            'id' => $version->id,
            'version_hash' => $version->version_hash,
            'file_size_kb' => $version->file_size_kb,
            'external_assets' => $version->external_assets ?? [],
            'is_active' => $theme->active_version_id === $version->id,
            'published_at' => optional($version->published_at)->toDateTimeString(),
            'created_at' => optional($version->created_at)->toDateTimeString(),
        ];
    }

    private function developerCss(CmsTheme $theme): string
    {
        $version = $theme->versions()->first();

        if (! $version instanceof CmsThemeVersion) {
            return $this->defaultDeveloperCss();
        }

        $disk = Storage::disk((string) config('cms_themes.storage_disk', 'local'));

        return $disk->exists($version->developer_css_path)
            ? (string) $disk->get($version->developer_css_path)
            : $this->defaultDeveloperCss();
    }

    private function defaultDeveloperCss(): string
    {
        return "/* Theme CSS overrides. system.css blijft de basis. */\n:root {\n    --rw-public-color-primary: #2563eb;\n}\n";
    }

    /**
     * @return array<string, string>
     */
    private function themeSettings(CmsTheme $theme): array
    {
        $version = $theme->versions()->first();

        return $version instanceof CmsThemeVersion && is_array($version->settings)
            ? $this->validatedThemeSettings($version->settings)
            : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function settingsFields(): array
    {
        return collect((array) config('cms_themes.settings_fields', []))
            ->filter(fn ($field): bool => is_array($field) && ! empty($field['key']))
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function validatedThemeSettings(mixed $settings): array
    {
        if (! is_array($settings)) {
            return [];
        }

        $allowedKeys = collect($this->settingsFields())
            ->pluck('key')
            ->filter()
            ->map(fn ($key): string => (string) $key)
            ->all();

        return collect($settings)
            ->only($allowedKeys)
            ->filter(fn ($value): bool => is_scalar($value) && trim((string) $value) !== '')
            ->map(fn ($value): string => trim((string) $value))
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $errors
     */
    private function validationMessages(array $errors): string
    {
        return collect($errors)
            ->map(fn (array $error): string => 'Regel '.$error['line'].': '.$error['message'].' Waarde: '.$error['value'])
            ->implode(' ');
    }
}
