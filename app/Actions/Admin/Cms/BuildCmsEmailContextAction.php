<?php

namespace App\Actions\Admin\Cms;

use App\Models\Cms\CmsFormSubmission;
use App\Models\PublicSite\SiteUser;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;

class BuildCmsEmailContextAction
{
    /**
     * @return array<string, mixed>
     */
    public function auth(SiteUser $user, string $actionUrl, ?Carbon $expiresAt = null): array
    {
        return [
            'user' => [
                'name' => (string) ($user->name ?: $user->email),
                'email' => (string) $user->email,
            ],
            'site' => $this->sitePayload(),
            'action' => [
                'url' => $actionUrl,
                'expires_at' => $expiresAt?->format('d/m/Y H:i'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formSubmission(CmsFormSubmission $submission): array
    {
        $submission->loadMissing(['form', 'page', 'values']);

        return [
            'form' => [
                'id' => $submission->form?->id,
                'title' => (string) ($submission->form?->title ?? ''),
                'locale' => (string) ($submission->form?->locale ?? $submission->locale),
            ],
            'submission' => [
                'id' => $submission->id,
                'submitted_at' => $submission->submitted_at?->format('d/m/Y H:i'),
                'status' => (string) $submission->status,
            ],
            'page' => [
                'id' => $submission->page?->id,
                'title' => (string) ($submission->page?->title ?? ''),
            ],
            'site' => $this->sitePayload(),
            'answers' => $submission->values
                ->map(fn ($value): array => [
                    'key' => (string) $value->field_translation_key,
                    'label' => (string) $value->field_label_snapshot,
                    'value' => (string) $value->value,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{name: string, url: string}
     */
    private function sitePayload(): array
    {
        $site = TenantContext::site();

        return [
            'name' => (string) ($site?->name ?? config('app.name', 'Website')),
            'url' => (string) config('app.url'),
        ];
    }
}
