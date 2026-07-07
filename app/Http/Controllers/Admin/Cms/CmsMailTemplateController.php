<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Actions\Admin\Base\RenderPlaceholdersAction;
use App\Actions\Admin\Cms\BuildCmsEmailContextAction;
use App\Actions\Admin\Cms\RenderCmsEmailAction;
use App\Actions\Admin\Cms\Revisions\BuildCmsEmailRevisionSnapshotAction;
use App\Actions\Admin\Cms\Revisions\BuildCmsMailTemplateRevisionSnapshotAction;
use App\Actions\Admin\Cms\Revisions\CmsRevisionPayloadAction;
use App\Actions\Admin\Cms\Revisions\CreateCmsRevisionAction;
use App\Actions\Admin\Cms\SaveCmsSectionsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cms\StoreCmsEmailRequest;
use App\Http\Requests\Admin\Cms\StoreCmsEmailTranslationRequest;
use App\Http\Requests\Admin\Cms\StoreCmsMailTemplateRequest;
use App\Mail\CmsRenderedEmail;
use App\Models\Cms\CmsBlock;
use App\Models\Cms\CmsBlockPlacement;
use App\Models\Cms\CmsEmail;
use App\Models\Cms\CmsEmailDelivery;
use App\Models\Cms\CmsFormSubmission;
use App\Models\Cms\CmsMailTemplate;
use App\Models\Cms\CmsPlaceableBlock;
use App\Models\Cms\CmsSection;
use App\Support\Cms\CmsMediaLibraryPayload;
use App\Support\Cms\CmsResponsiveLayoutNormalizer;
use App\Support\Cms\CmsSystemMailRegistry;
use App\Support\PublicSite\CmsLanguageSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CmsMailTemplateController extends Controller
{
    public function __construct(
        private readonly CmsLanguageSettings $languageSettings,
        private readonly CmsSystemMailRegistry $systemMailRegistry,
        private readonly CmsMediaLibraryPayload $mediaLibraryPayload,
        private readonly CmsRevisionPayloadAction $revisionPayload,
        private readonly BuildCmsMailTemplateRevisionSnapshotAction $buildMailTemplateRevisionSnapshot,
        private readonly BuildCmsEmailRevisionSnapshotAction $buildEmailRevisionSnapshot,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Cms/Mail/Index', [
            'mailTemplates' => CmsMailTemplate::query()
                ->withCount('emails')
                ->orderBy('name')
                ->get()
                ->map(fn (CmsMailTemplate $template): array => $this->templatePayload($template))
                ->values(),
        ]);
    }

    public function emailsIndex(): Response
    {
        return Inertia::render('Admin/Cms/Mail/EmailIndex', [
            'emails' => CmsEmail::query()
                ->with('mailTemplate:id,name,key')
                ->orderBy('email_type')
                ->orderBy('system_key')
                ->orderBy('locale')
                ->orderBy('title')
                ->get()
                ->map(fn (CmsEmail $email): array => $this->emailPayload($email))
                ->values(),
            'deliveries' => CmsEmailDelivery::query()
                ->with('email:id,title,system_key')
                ->latest('created_at')
                ->limit(250)
                ->get()
                ->map(fn (CmsEmailDelivery $delivery): array => $this->deliveryPayload($delivery))
                ->values(),
        ]);
    }

    public function create(): Response
    {
        return $this->edit(0);
    }

    public function edit(int $id): Response
    {
        $template = $id > 0
            ? CmsMailTemplate::query()
                ->with([
                    'sections.placements.block.placeableBlock.latestPublishedRevision',
                    'sections.placements.publishedStyleRevision',
                    'sections.placements.styleRevisions.author:id,name,email',
                    'sections.placements.childPlacements.block.placeableBlock.latestPublishedRevision',
                    'sections.placements.childPlacements.publishedStyleRevision',
                    'sections.placements.childPlacements.styleRevisions.author:id,name,email',
                ])
                ->withCount('emails')
                ->findOrFail($id)
            : null;

        return Inertia::render('Admin/Cms/Mail/TemplateEdit', [
            'mailTemplate' => $template ? $this->templatePayload($template) : null,
            'revisions' => $template ? $this->revisionPayload->handle($template) : [],
            'contextOptions' => $this->contextOptions(),
            'placeableBlocks' => $this->mailPlaceableBlockOptions(),
            'mediaOptions' => $this->mediaLibraryPayload->assets(),
            'mediaFolders' => $this->mediaLibraryPayload->folders(),
        ]);
    }

    public function store(StoreCmsMailTemplateRequest $request, int $id, SaveCmsSectionsAction $saveSections, CreateCmsRevisionAction $createRevision): RedirectResponse
    {
        $validated = $request->validated();
        $template = $id > 0 ? CmsMailTemplate::query()->findOrFail($id) : new CmsMailTemplate;
        $sections = $this->prepareMailSections((array) ($validated['sections'] ?? []));

        DB::transaction(function () use ($template, $validated, $sections, $saveSections): void {
            $key = $template->exists
                ? (string) $template->key
                : $this->uniqueTemplateKey((string) $validated['name']);

            $template->fill([
                'name' => $validated['name'],
                'key' => $key,
                'description' => $validated['description'] ?? null,
                'context_key' => $validated['context_key'],
                'body_blocks' => $this->bodyBlocks($validated['body_blocks'] ?? []),
                'is_active' => (bool) ($validated['is_active'] ?? false),
            ])->save();

            $saveSections->handle($template, $sections, ['content']);
        });

        $createRevision->handle(
            $template->fresh() ?: $template,
            'full',
            $this->buildMailTemplateRevisionSnapshot->handle($template->fresh() ?: $template),
            $request->user()?->id,
            __('cms_admin_ui.revisions.saved_revision_title'),
        );

        return redirect()
            ->route('admin.cms.mail-templates.edit', ['id' => $template->id])
            ->with('status', __('cms_admin_ui.flash.saved.mail_template'));
    }

    public function createEmail(): Response
    {
        return $this->editEmail(0);
    }

    public function editEmail(int $id): Response
    {
        $email = $id > 0 ? CmsEmail::query()->with('mailTemplate')->findOrFail($id) : null;

        return Inertia::render('Admin/Cms/Mail/EmailEdit', [
            'emailItem' => $email ? $this->emailPayload($email) : null,
            'revisions' => $email ? $this->revisionPayload->handle($email) : [],
            'translations' => $email ? $this->emailTranslationPayload($email) : [],
            'missingLanguages' => $email ? $this->missingEmailLanguages($email) : [],
            'mailTemplates' => CmsMailTemplate::query()
                ->active()
                ->with(['sections.placements.block.placeableBlock.latestPublishedRevision'])
                ->orderBy('name')
                ->get()
                ->map(fn (CmsMailTemplate $template): array => $this->templatePayload($template))
                ->values(),
            'activeLanguages' => $this->languageSettings->languages(true),
            'systemMailOptions' => $this->systemMailOptions(),
            'placeholdersByContext' => $this->placeholdersByContext(),
            'mediaOptions' => $this->mediaLibraryPayload->assets(),
            'mailTestDeliveryUrl' => $this->mailTestDeliveryUrl(),
            'previewFormSubmissions' => $email ? $this->previewFormSubmissionOptions($email) : [],
        ]);
    }

    public function storeEmail(StoreCmsEmailRequest $request, int $id, CreateCmsRevisionAction $createRevision): RedirectResponse
    {
        $validated = $request->validated();
        $email = $id > 0 ? CmsEmail::query()->findOrFail($id) : new CmsEmail;
        $template = CmsMailTemplate::query()->findOrFail((int) $validated['cms_mail_template_id']);

        $email->fill([
            'cms_mail_template_id' => $template->id,
            'title' => $validated['title'],
            'locale' => $validated['locale'],
            'email_type' => $validated['email_type'],
            'system_key' => ($validated['email_type'] ?? 'custom') === 'system' ? ($validated['system_key'] ?? null) : null,
            'context_key' => $template->context_key,
            'subject' => $validated['subject'],
            'preheader' => $validated['preheader'] ?? null,
            'content_blocks' => is_array($validated['content_blocks'] ?? null) ? $validated['content_blocks'] : [],
            'plain_text' => $validated['plain_text'] ?? null,
            'settings' => $this->emailSettings($validated['settings'] ?? []),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        if (blank($email->translation_key)) {
            $email->translation_key = (string) Str::ulid();
        }

        $email->save();

        $createRevision->handle(
            $email->fresh() ?: $email,
            'full',
            $this->buildEmailRevisionSnapshot->handle($email->fresh() ?: $email),
            $request->user()?->id,
            __('cms_admin_ui.revisions.saved_revision_title'),
        );

        return redirect()
            ->route('admin.cms.emails.edit', ['id' => $email->id])
            ->with('status', __('cms_admin_ui.flash.saved.email'));
    }

    public function previewEmail(Request $request, int $id, RenderCmsEmailAction $renderEmail, BuildCmsEmailContextAction $buildContext): JsonResponse
    {
        $email = CmsEmail::query()->with('mailTemplate')->findOrFail($id);
        $data = $this->previewEmailContext($email, $request, $buildContext);

        return response()->json($renderEmail->handle($email, $data));
    }

    public function testEmail(Request $request, int $id): RedirectResponse
    {
        $email = CmsEmail::query()->with('mailTemplate')->findOrFail($id);
        $recipient = (string) ($request->user()?->email ?? '');

        if ($recipient === '') {
            return back()->with('error', __('cms_admin_ui.mail.test_email_missing_recipient'));
        }

        $data = $email->context_key === 'cms.form_submission.email'
            ? $this->sampleFormContext((string) $email->locale)
            : $this->sampleAuthContext();

        $delivery = CmsEmailDelivery::query()->create([
            'cms_email_id' => $email->id,
            'context_type' => 'test',
            'context_id' => $request->user()?->id,
            'recipient_email' => $recipient,
            'status' => 'pending',
            'subject_snapshot' => (string) $email->subject,
            'metadata' => ['sample_context' => $data],
        ]);

        try {
            Mail::to($recipient)->send(new CmsRenderedEmail($email, $data));

            $delivery->forceFill([
                'status' => 'sent',
                'sent_at' => now(),
                'subject_snapshot' => app(RenderCmsEmailAction::class)->handle($email, $data)['subject'],
            ])->save();
        } catch (\Throwable $exception) {
            report($exception);

            $delivery->forceFill([
                'status' => 'failed',
                'error_message' => mb_substr($exception->getMessage(), 0, 1000),
            ])->save();

            return back()->with('error', __('cms_admin_ui.mail.test_email_failed'));
        }

        return back()->with('status', __('cms_admin_ui.mail.test_email_sent', ['recipient' => $recipient]));
    }

    public function storeEmailTranslation(StoreCmsEmailTranslationRequest $request, int $id): RedirectResponse
    {
        $validated = $request->validated();
        $email = CmsEmail::query()->findOrFail($id);
        $targetLocale = (string) $validated['target_locale'];

        $translation = DB::transaction(function () use ($email, $targetLocale): CmsEmail {
            if ($email->email_type !== 'system' && blank($email->translation_key)) {
                $email->forceFill(['translation_key' => (string) Str::ulid()])->save();
            }

            return CmsEmail::query()->create([
                'cms_mail_template_id' => $email->cms_mail_template_id,
                'title' => $email->title,
                'locale' => $targetLocale,
                'translation_key' => $email->translation_key,
                'email_type' => $email->email_type,
                'system_key' => $email->email_type === 'system' ? $email->system_key : null,
                'context_key' => $email->context_key,
                'subject' => $email->subject,
                'preheader' => $email->preheader,
                'content_blocks' => $email->content_blocks ?? [],
                'plain_text' => $email->plain_text,
                'settings' => $email->settings ?? [],
                'is_active' => false,
            ]);
        });

        return redirect()
            ->route('admin.cms.emails.edit', ['id' => $translation->id])
            ->with('status', __('cms_admin_ui.flash.email_translation_created'));
    }

    private function mailTestDeliveryUrl(): ?string
    {
        $smtpHost = Str::lower((string) config('mail.mailers.smtp.host', ''));

        if (! app()->environment('local') || ! str_contains($smtpHost, 'mailpit')) {
            return null;
        }

        return 'http://localhost:8025';
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    private function previewFormSubmissionOptions(CmsEmail $email): array
    {
        if ($email->context_key !== 'cms.form_submission.email' || ! $this->canPreviewFormSubmissions(request())) {
            return [];
        }

        return CmsFormSubmission::query()
            ->with(['form:id,title', 'page:id,title'])
            ->where('locale', $email->locale)
            ->latest('submitted_at')
            ->limit(20)
            ->get()
            ->map(fn (CmsFormSubmission $submission): array => [
                'id' => $submission->id,
                'label' => $this->previewFormSubmissionLabel($submission),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function previewEmailContext(CmsEmail $email, Request $request, BuildCmsEmailContextAction $buildContext): array
    {
        if ($email->context_key !== 'cms.form_submission.email') {
            return $this->sampleAuthContext();
        }

        $submissionId = (int) $request->integer('form_submission_id');

        if ($submissionId > 0 && $this->canPreviewFormSubmissions($request)) {
            $submission = CmsFormSubmission::query()
                ->whereKey($submissionId)
                ->where('locale', $email->locale)
                ->first();

            if ($submission instanceof CmsFormSubmission) {
                return $buildContext->formSubmission($submission);
            }
        }

        return $this->sampleFormContext((string) $email->locale);
    }

    private function canPreviewFormSubmissions(Request $request): bool
    {
        return (bool) $request->user()?->canAccessRoute('admin.cms.form-submissions.index');
    }

    private function previewFormSubmissionLabel(CmsFormSubmission $submission): string
    {
        $submittedAt = $submission->submitted_at?->format('d/m/Y H:i') ?? '-';
        $formTitle = (string) ($submission->form?->title ?: __('cms_admin_ui.mail.preview_context_unknown_form'));
        $pageTitle = (string) ($submission->page?->title ?: __('cms_admin_ui.mail.preview_context_unknown_page'));

        return "#{$submission->id} - {$formTitle} - {$pageTitle} - {$submittedAt}";
    }

    /**
     * @return array<string, mixed>
     */
    private function templatePayload(CmsMailTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'key' => $template->key,
            'description' => $template->description,
            'context_key' => $template->context_key,
            'body_blocks' => $template->body_blocks ?? [],
            'sections' => $template->relationLoaded('sections')
                ? $this->sectionsPayload($template)
                : $this->emptySectionsPayload(),
            'content_contract' => $template->relationLoaded('sections')
                ? $this->contentContract($template)
                : $this->legacyContentContract($template),
            'emails_count' => $template->emails_count ?? null,
            'is_active' => (bool) $template->is_active,
            'created_at' => $template->created_at?->toIso8601String(),
            'updated_at' => $template->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{content: array<int, array<string, mixed>>}
     */
    private function sectionsPayload(CmsMailTemplate $template): array
    {
        return [
            'content' => $template->sections
                ->where('zone', 'content')
                ->where('is_active', true)
                ->sortBy('sort_order')
                ->map(fn (CmsSection $section): array => [
                    'id' => $section->id,
                    'name' => $section->name,
                    'is_active' => (bool) $section->is_active,
                    'visible_mobile' => (bool) $section->visible_mobile,
                    'visible_tablet' => (bool) $section->visible_tablet,
                    'visible_desktop' => (bool) $section->visible_desktop,
                    'settings' => $this->sectionSettingsPayload($section->settings ?? []),
                    'placements' => $section->placements
                        ->where('is_active', true)
                        ->sortBy('sort_order')
                        ->map(fn (CmsBlockPlacement $placement): array => $this->placementPayload($placement))
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function sectionSettingsPayload(array $settings): array
    {
        $background = app(CmsResponsiveLayoutNormalizer::class)
            ->normalizeBackground(is_array($settings['background'] ?? null) ? $settings['background'] : null);

        return [
            'html_anchor' => is_string($settings['html_anchor'] ?? null) ? trim($settings['html_anchor']) : null,
            'layout_type' => in_array($settings['layout_type'] ?? null, ['standard', 'grid'], true)
                ? $settings['layout_type']
                : 'standard',
            'width_mode' => 'content',
            'spacing' => in_array($settings['spacing'] ?? null, ['none', 'compact', 'normal', 'spacious'], true)
                ? $settings['spacing']
                : 'normal',
            'scroll_behavior' => 'normal',
            'background' => $background,
            'box' => app(CmsResponsiveLayoutNormalizer::class)
                ->normalizeBoxSpacing(is_array($settings['box'] ?? null) ? $settings['box'] : null),
        ];
    }

    /**
     * @return array{content: array<int, array<string, mixed>>}
     */
    private function emptySectionsPayload(): array
    {
        return ['content' => []];
    }

    /**
     * @return array<string, mixed>
     */
    private function placementPayload(CmsBlockPlacement $placement): array
    {
        return [
            'id' => $placement->id,
            'is_active' => (bool) $placement->is_active,
            'visible_mobile' => (bool) $placement->visible_mobile,
            'visible_tablet' => (bool) $placement->visible_tablet,
            'visible_desktop' => (bool) $placement->visible_desktop,
            'mobile_span' => (int) $placement->mobile_span,
            'tablet_span' => (int) $placement->tablet_span,
            'desktop_span' => (int) $placement->desktop_span,
            'layout_config' => $placement->layout_config ?? [],
            'style_config' => $placement->style_config ?? [],
            'published_style_revision_id' => $placement->published_style_revision_id,
            'published_style_revision' => $placement->publishedStyleRevision ? [
                'id' => (int) $placement->publishedStyleRevision->id,
                'revision_number' => (int) $placement->publishedStyleRevision->revision_number,
                'css_source' => (string) $placement->publishedStyleRevision->css_source,
                'published_at' => $placement->publishedStyleRevision->published_at?->toIso8601String(),
            ] : null,
            'style_revisions' => $placement->styleRevisions
                ->map(fn ($revision): array => [
                    'id' => (int) $revision->id,
                    'revision_number' => (int) $revision->revision_number,
                    'status' => (string) $revision->status,
                    'title' => (string) $revision->title,
                    'css_source' => (string) $revision->css_source,
                    'css_preview' => mb_strimwidth((string) $revision->css_source, 0, 160, '...'),
                    'author' => $revision->author ? [
                        'id' => (int) $revision->author->id,
                        'name' => (string) ($revision->author->name ?? $revision->author->email),
                    ] : null,
                    'published_at' => $revision->published_at?->toIso8601String(),
                    'is_current' => (int) $placement->published_style_revision_id === (int) $revision->id,
                ])
                ->values()
                ->all(),
            'height_mode' => $placement->height_mode,
            'height_value' => $placement->height_value,
            'cache_strategy' => $placement->cache_strategy,
            'settings' => [
                'html_anchor' => is_string($placement->settings['html_anchor'] ?? null) ? trim($placement->settings['html_anchor']) : null,
                'content_key' => is_string($placement->settings['content_key'] ?? null) ? trim($placement->settings['content_key']) : null,
                'editor_label' => is_string($placement->settings['editor_label'] ?? null) ? trim($placement->settings['editor_label']) : null,
                'page_editable' => (bool) ($placement->settings['page_editable'] ?? true),
                'page_editable_fields' => is_array($placement->settings['page_editable_fields'] ?? null) ? array_values($placement->settings['page_editable_fields']) : [],
                'page_editable_meta' => is_array($placement->settings['page_editable_meta'] ?? null) ? array_values($placement->settings['page_editable_meta']) : [],
                'alignment' => in_array($placement->settings['alignment'] ?? null, ['left', 'center', 'right'], true) ? $placement->settings['alignment'] : null,
                'content_alignment' => in_array($placement->settings['content_alignment'] ?? null, ['left', 'center', 'right'], true) ? $placement->settings['content_alignment'] : null,
            ],
            'block' => $this->blockPayload($placement->block),
            'slots' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function blockPayload(CmsBlock $block): array
    {
        return array_merge([
            'id' => $block->id,
            'cms_placeable_block_id' => $block->cms_placeable_block_id,
            'placeable_block_revision_id' => $block->placeable_block_revision_id,
            'name' => $block->name,
            'cache_strategy' => $block->cache_strategy,
        ], $block->content ?? []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function contentContract(CmsMailTemplate $template): array
    {
        return $template->sections
            ->where('zone', 'content')
            ->where('is_active', true)
            ->flatMap(fn (CmsSection $section) => $section->placements->where('is_active', true)->sortBy('sort_order'))
            ->map(fn (CmsBlockPlacement $placement): ?array => $this->contentContractRow($placement))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function legacyContentContract(CmsMailTemplate $template): array
    {
        return collect($template->body_blocks ?? [])
            ->filter(fn (mixed $block): bool => is_array($block))
            ->map(fn (array $block): array => [
                'key' => (string) ($block['key'] ?? ''),
                'label' => (string) ($block['label'] ?? $block['key'] ?? ''),
                'type' => (string) ($block['type'] ?? 'text'),
                'fields' => $this->mailContentFields((string) ($block['type'] ?? 'text')),
            ])
            ->values()
            ->all();
    }

    private function contentContractRow(CmsBlockPlacement $placement): ?array
    {
        $rendererKey = (string) ($placement->block?->type ?? '');

        if (! str_starts_with($rendererKey, 'mail_')) {
            return null;
        }

        $key = (string) ($placement->settings['content_key'] ?? '');

        if ($key === '') {
            return null;
        }

        return [
            'key' => $key,
            'label' => (string) ($placement->settings['editor_label'] ?? $placement->block?->name ?? $key),
            'type' => $rendererKey,
            'fields' => $this->mailContentFields($rendererKey),
        ];
    }

    /**
     * @return array<int, array{name: string, type: string, required: bool}>
     */
    private function mailContentFields(string $type): array
    {
        return match ($type) {
            'mail_button', 'button' => [
                ['name' => 'label', 'type' => 'text', 'required' => true],
                ['name' => 'url', 'type' => 'text', 'required' => false],
            ],
            'mail_image' => [
                ['name' => 'media_asset_id', 'type' => 'media', 'required' => false],
                ['name' => 'alt', 'type' => 'text', 'required' => false],
                ['name' => 'caption', 'type' => 'text', 'required' => false],
            ],
            'mail_company_logo', 'mail_divider', 'mail_spacer', 'mail_form_answers', 'company_logo', 'divider', 'spacer', 'form_answers' => [],
            default => [
                ['name' => 'text', 'type' => 'textarea', 'required' => true],
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function emailPayload(CmsEmail $email): array
    {
        return [
            'id' => $email->id,
            'cms_mail_template_id' => $email->cms_mail_template_id,
            'mail_template_name' => $email->mailTemplate?->name,
            'title' => $email->title,
            'locale' => $email->locale,
            'translation_key' => $email->translation_key,
            'email_type' => $email->email_type,
            'system_key' => $email->system_key,
            'context_key' => $email->context_key,
            'subject' => $email->subject,
            'preheader' => $email->preheader,
            'content_blocks' => $email->content_blocks ?? [],
            'plain_text' => $email->plain_text,
            'settings' => $email->settings ?? [],
            'is_active' => (bool) $email->is_active,
            'created_at' => $email->created_at?->toIso8601String(),
            'updated_at' => $email->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function emailSettings(mixed $settings): array
    {
        $settings = is_array($settings) ? $settings : [];

        return collect([
            'from_name' => $settings['from_name'] ?? null,
            'from_email' => $settings['from_email'] ?? null,
            'reply_to_name' => $settings['reply_to_name'] ?? null,
            'reply_to_email' => $settings['reply_to_email'] ?? null,
        ])
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter(fn (string $value): bool => $value !== '')
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function emailTranslationPayload(CmsEmail $email): array
    {
        return $this->emailTranslationQuery($email)
            ->orderBy('locale')
            ->get()
            ->map(fn (CmsEmail $translation): array => [
                'id' => $translation->id,
                'title' => $translation->title,
                'locale' => $translation->locale,
                'is_active' => (bool) $translation->is_active,
                'updated_at' => $translation->updated_at?->toIso8601String(),
                'is_current' => $translation->is($email),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function missingEmailLanguages(CmsEmail $email): array
    {
        $existingLocales = $this->emailTranslationQuery($email)
            ->pluck('locale')
            ->filter()
            ->map(fn (string $locale): string => $locale)
            ->all();

        return collect($this->languageSettings->languages(true))
            ->reject(fn (array $language): bool => in_array((string) ($language['locale'] ?? ''), $existingLocales, true))
            ->values()
            ->all();
    }

    private function emailTranslationQuery(CmsEmail $email): Builder
    {
        if ($email->email_type === 'system' && filled($email->system_key)) {
            return CmsEmail::query()
                ->where('email_type', 'system')
                ->where('system_key', $email->system_key);
        }

        if (filled($email->translation_key)) {
            return CmsEmail::query()->where('translation_key', $email->translation_key);
        }

        return CmsEmail::query()->whereKey($email->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function deliveryPayload(CmsEmailDelivery $delivery): array
    {
        return [
            'id' => $delivery->id,
            'email_title' => $delivery->email?->title ?? '-',
            'system_key' => $delivery->email?->system_key,
            'context_type' => $delivery->context_type,
            'context_id' => $delivery->context_id,
            'recipient_email' => $delivery->recipient_email,
            'status' => $delivery->status,
            'subject_snapshot' => $delivery->subject_snapshot,
            'error_message' => $delivery->error_message,
            'sent_at' => $delivery->sent_at?->toIso8601String(),
            'created_at' => $delivery->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<int, array<string, mixed>>
     */
    private function bodyBlocks(array $blocks): array
    {
        return collect($blocks)
            ->filter(fn (mixed $block): bool => is_array($block))
            ->map(fn (array $block): array => array_filter([
                'key' => (string) $block['key'],
                'type' => (string) $block['type'],
                'label' => (string) $block['label'],
                'required' => (bool) ($block['required'] ?? false),
                'url_source' => filled($block['url_source'] ?? null) ? (string) $block['url_source'] : null,
            ], fn (mixed $value): bool => $value !== null))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $sections
     * @return array{content: array<int, array<string, mixed>>}
     */
    private function prepareMailSections(array $sections): array
    {
        $contentSections = is_array($sections['content'] ?? null) ? array_values($sections['content']) : [];

        return [
            'content' => collect($contentSections)
                ->filter(fn (mixed $section): bool => is_array($section))
                ->map(fn (array $section, int $sectionIndex): array => $this->prepareMailSection($section, $sectionIndex))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>
     */
    private function prepareMailSection(array $section, int $sectionIndex): array
    {
        $placements = is_array($section['placements'] ?? null) ? array_values($section['placements']) : [];

        $section['name'] = filled($section['name'] ?? null)
            ? (string) $section['name']
            : __('cms_admin_ui.mail.default_section_name', ['number' => $sectionIndex + 1]);
        $section['placements'] = collect($placements)
            ->filter(fn (mixed $placement): bool => is_array($placement))
            ->map(fn (array $placement, int $placementIndex): array => $this->prepareMailPlacement($placement, $sectionIndex, $placementIndex))
            ->values()
            ->all();

        return $section;
    }

    /**
     * @param  array<string, mixed>  $placement
     * @return array<string, mixed>
     */
    private function prepareMailPlacement(array $placement, int $sectionIndex, int $placementIndex): array
    {
        $block = is_array($placement['block'] ?? null) ? $placement['block'] : [];
        $placeableBlock = $this->mailPlaceableBlockForPlacement($block);
        $rendererKey = (string) $placeableBlock->renderer_key;
        $settings = is_array($placement['settings'] ?? null) ? $placement['settings'] : [];

        $placement['block'] = array_merge($block, [
            'cms_placeable_block_id' => (int) $placeableBlock->id,
            'renderer_key' => $rendererKey,
            'type' => $rendererKey,
        ]);
        $placement['settings'] = array_merge($settings, [
            'content_key' => $this->mailContentKey($settings, $block, $rendererKey, $sectionIndex, $placementIndex),
            'editor_label' => filled($settings['editor_label'] ?? null)
                ? (string) $settings['editor_label']
                : (string) ($block['name'] ?? $placeableBlock->name),
            'page_editable' => true,
        ]);
        $placement['slots'] = [];

        return $placement;
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function mailPlaceableBlockForPlacement(array $block): CmsPlaceableBlock
    {
        $query = CmsPlaceableBlock::query()
            ->where('category', 'mail')
            ->where('status', 'published');

        $placeableBlockId = (int) ($block['cms_placeable_block_id'] ?? 0);

        if ($placeableBlockId > 0) {
            $placeableBlock = (clone $query)->whereKey($placeableBlockId)->first();

            if ($placeableBlock instanceof CmsPlaceableBlock) {
                return $placeableBlock;
            }
        }

        $rendererKey = (string) ($block['renderer_key'] ?? $block['type'] ?? '');

        if ($rendererKey !== '') {
            $placeableBlock = (clone $query)->where('renderer_key', $rendererKey)->first();

            if ($placeableBlock instanceof CmsPlaceableBlock) {
                return $placeableBlock;
            }
        }

        throw ValidationException::withMessages([
            'sections' => __('cms_admin_ui.validation.mail_block_forbidden'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $block
     */
    private function mailContentKey(array $settings, array $block, string $rendererKey, int $sectionIndex, int $placementIndex): string
    {
        $existing = is_string($settings['content_key'] ?? null) ? trim($settings['content_key']) : '';

        if ($existing !== '' && preg_match('/^[a-z0-9_]+$/', $existing) === 1) {
            return $existing;
        }

        $source = (string) ($block['name'] ?? $rendererKey ?: 'mail_block');
        $key = Str::slug($source, '_');
        $key = trim($key, '_') !== '' ? trim($key, '_') : 'mail_block';
        $key = Str::limit($key, 64, '');

        return trim($key, '_').'_'.$sectionIndex.'_'.$placementIndex;
    }

    private function uniqueTemplateKey(string $name): string
    {
        $base = Str::slug($name, '.');
        $base = trim($base, '.') !== '' ? trim($base, '.') : 'mail-template';
        $base = Str::limit($base, 80, '');
        $key = $base;
        $counter = 2;

        while (CmsMailTemplate::query()->where('key', $key)->exists()) {
            $key = $base.'.'.$counter;
            $counter++;
        }

        return $key;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mailPlaceableBlockOptions(): array
    {
        return CmsPlaceableBlock::query()
            ->with('latestPublishedRevision')
            ->where('status', 'published')
            ->where('category', 'mail')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (CmsPlaceableBlock $block): bool => $block->latestPublishedRevision !== null)
            ->map(fn (CmsPlaceableBlock $block): array => [
                'id' => (int) $block->id,
                'key' => (string) $block->key,
                'name' => (string) $block->name,
                'description' => $block->description,
                'category' => (string) ($block->category ?: 'mail'),
                'source' => (string) ($block->source ?: 'system'),
                'status' => (string) $block->status,
                'allowed_zones' => $block->allowed_zones ?? [],
                'rendering_mode' => (string) $block->rendering_mode,
                'renderer_key' => (string) $block->renderer_key,
                'requires_permission' => $block->requires_permission,
                'schema' => $block->schema ?? [],
                'defaults' => $block->defaults ?? [],
                'capabilities' => $block->capabilities ?? [],
                'admin_component_key' => $block->admin_component_key,
                'package_key' => $block->package_key,
                'is_locked' => (bool) $block->is_locked,
                'revision_id' => (int) $block->latestPublishedRevision->id,
                'revision_number' => (int) $block->latestPublishedRevision->revision_number,
                'published_at' => $block->latestPublishedRevision->published_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function contextOptions(): array
    {
        return [
            ['value' => 'public_site.auth_email', 'label' => __('cms_admin_ui.mail.contexts.public_site_auth_email')],
            ['value' => 'cms.form_submission.email', 'label' => __('cms_admin_ui.mail.contexts.cms_form_submission_email')],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function blockTypeOptions(): array
    {
        return collect(['heading', 'text', 'button', 'divider', 'spacer', 'form_answers'])
            ->map(fn (string $type): array => ['value' => $type, 'label' => __('cms_admin_ui.mail.block_types.'.$type)])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string, context_key: string}>
     */
    private function systemMailOptions(): array
    {
        return collect($this->systemMailRegistry->all())
            ->map(fn (array $mail, string $key): array => [
                'value' => $key,
                'label' => (string) $mail['label'],
                'context_key' => (string) $mail['context_key'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<int, array{key: string, label: string}>>
     */
    private function placeholdersByContext(): array
    {
        return [
            'public_site.auth_email' => RenderPlaceholdersAction::placeholders('public_site.auth_email'),
            'cms.form_submission.email' => RenderPlaceholdersAction::placeholders('cms.form_submission.email'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleAuthContext(): array
    {
        return [
            'user' => ['name' => 'Alex Example', 'email' => 'alex@example.test'],
            'site' => ['name' => config('app.name', 'Website'), 'url' => config('app.url')],
            'action' => ['url' => config('app.url').'/account/action-token', 'expires_at' => now()->addHour()->format('d/m/Y H:i')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleFormContext(string $locale): array
    {
        $locale = trim($locale) !== '' ? trim($locale) : 'en';

        return [
            'form' => ['id' => 1, 'title' => __('cms_admin_ui.mail.preview_sample.form_title', [], $locale), 'locale' => $locale],
            'submission' => ['id' => 1001, 'submitted_at' => now()->format('d/m/Y H:i'), 'status' => 'new'],
            'page' => ['id' => 10, 'title' => __('cms_admin_ui.mail.preview_sample.page_title', [], $locale)],
            'site' => ['name' => config('app.name', 'Website'), 'url' => config('app.url')],
            'answers' => [
                ['key' => 'name', 'label' => __('cms_admin_ui.mail.preview_sample.name_label', [], $locale), 'value' => __('cms_admin_ui.mail.preview_sample.name_value', [], $locale)],
                ['key' => 'email', 'label' => __('cms_admin_ui.mail.preview_sample.email_label', [], $locale), 'value' => 'alex@example.test'],
                ['key' => 'message', 'label' => __('cms_admin_ui.mail.preview_sample.message_label', [], $locale), 'value' => __('cms_admin_ui.mail.preview_sample.message_value', [], $locale)],
            ],
        ];
    }
}
