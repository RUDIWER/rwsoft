<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Models\Cms\CmsFormSubmission;
use Inertia\Inertia;
use Inertia\Response;

class CmsFormSubmissionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Cms/FormSubmissions/Index', [
            'submissions' => CmsFormSubmission::query()
                ->with(['form:id,title,translation_key,locale', 'page:id,title,slug', 'values.field:id,label'])
                ->orderByDesc('submitted_at')
                ->orderByDesc('id')
                ->limit(250)
                ->get()
                ->map(fn (CmsFormSubmission $submission): array => [
                    'id' => $submission->id,
                    'form_title' => $submission->form?->title,
                    'form_translation_key' => $submission->form?->translation_key,
                    'locale' => $submission->locale,
                    'page_title' => $submission->page?->title,
                    'status' => $submission->status,
                    'submitted_at' => $submission->submitted_at?->toDateTimeString(),
                    'ip_address' => $submission->ip_address,
                    'values' => $submission->values->map(fn ($value): array => [
                        'field_translation_key' => $value->field_translation_key,
                        'label' => $value->field?->label ?: ($value->field_label_snapshot ?: $value->field_translation_key),
                        'value' => $value->value,
                    ])->values(),
                ]),
        ]);
    }
}
